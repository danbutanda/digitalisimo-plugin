<?php
defined( 'ABSPATH' ) || exit;

/**
 * Análisis del sitio (tandas B y C de la auditoría SEO del 2026-11-24, de digitalisimo.lat). Sólo informa; las
 * acciones que cambian algo (301, 410, noindex) las propone `Digitalisimo_Integrations_SEO_Cleanup`, que pide
 * confirmación y guarda respaldo.
 *
 *   B1  Shortcodes y variables sin resolver en títulos, descripciones, migas y ajustes del sitio («[descripcion_sitio]
 *       [contenido_pilar]» como primera miga en 186 páginas). Usa el detector de A5 (`Index_Hygiene`): forma real de
 *       shortcode y lista de plugins conocidos; en el contenido, lo que ya marca A5. Las variables %x% que el sitio no
 *       conoce (se imprimirían vacías) y las que quedan escritas en un texto sin plantilla (título del contenido, texto
 *       de Inicio de las migas).
 *   B2  Canibalización: similitud del texto propio de cada página (tejas de 5 palabras, MinHash de 64 valores y LSH),
 *       comprobada con el Jaccard exacto en los pares candidatos. Dos o más páginas por encima del 70 % compiten.
 *       También avisa de contenidos con la misma keyword principal.
 *   B4  Canonical propio hacia otro dominio o hacia una URL que no responde (dominio inexistente, 404 o 410, o que
 *       redirige). Las peticiones van en el cron, con `wp_safe_remote_*` (protección SSRF), sin seguir redirecciones,
 *       con presupuesto de tiempo y con caché de una semana. Un fallo se confirma en una segunda comprobación.
 *   C1  Descripción para redes (og:description) escrita a mano que quedó vieja: la página cambió mucho desde que se
 *       escribió (se guarda la huella del texto al ver cada descripción nueva) o anuncia un plazo con una fecha que ya
 *       pasó y la página se actualizó después («El Buen Fin · Termina el martes 17 de noviembre» en /black-friday/).
 *
 * Nada corre al servir una página: el repaso va en WP-Cron por lotes de 25 (y uno por contenido al guardarlo), cada
 * contenido guarda su huella en una meta protegida y el resultado se reúne en una opción sin autoload que sólo leen el
 * escritorio y el MCP. Con otro plugin SEO activo no se analiza (sus señales no son las nuestras).
 */
class Digitalisimo_Integrations_Site_Analysis {
	const META       = '_digitalisimo_seo_analysis';
	const STATE      = 'digitalisimo_seo_analysis_state';
	const RESULTS    = 'digitalisimo_seo_analysis_results';
	const CANON      = 'digitalisimo_seo_canonical_check';
	const CRON       = 'digitalisimo_seo_analysis_batch';
	const CANON_CRON = 'digitalisimo_seo_canonical_batch';
	const VERSION    = 1;
	const BATCH      = 25;
	const HASHES     = 64;
	const BAND       = 4;
	const SHINGLE    = 5;
	/** Menos palabras propias que esto no se compara: dos páginas casi vacías siempre se parecen. */
	const MIN_WORDS  = 80;
	/** Desde aquí (Jaccard de las tejas) dos páginas compiten por lo mismo. */
	const SIMILAR    = 0.70;
	/** Candidatos del MinHash que se comprueban con el Jaccard exacto. */
	const CANDIDATE  = 0.55;
	const MAX_PAIRS  = 400;
	const MAX_POSTS  = 3000;
	/** Por debajo de esta similitud con el texto de cuando se escribió, la descripción quedó vieja. */
	const STALE_SIM  = 0.5;
	const LIST_MAX   = 300;
	const CANON_TTL  = WEEK_IN_SECONDS;
	/** Un fallo inequívoco del canonical se confirma con otra comprobación al menos así de después. */
	const CONFIRM_AFTER = 1800;
	public static $budget = 20.0;

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'run_batch' ) );
		add_action( self::CANON_CRON, array( __CLASS__, 'run_canonical' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_start' ), 30 );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 40, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_digitalisimo_seo_analysis_rescan', array( __CLASS__, 'handle_rescan' ) );
	}

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	/* ------------------------------------------------------------------ */
	/* Texto, tejas y similitud (puras).                                    */
	/* ------------------------------------------------------------------ */

	/** Palabras de un texto: sin etiquetas ni entidades, en minúsculas, letras y números Unicode. */
	public static function words( $text ) {
		$text = html_entity_decode( (string) preg_replace( '/<[^>]+>/', ' ', (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? $parts : array();
	}

	/** Tejas de $k palabras seguidas, sin repetir. */
	public static function shingles( array $words, $k = self::SHINGLE ) {
		$out = array();
		$n   = count( $words );
		if ( $n < $k ) return $n ? array( implode( ' ', $words ) ) : array();
		for ( $i = 0; $i + $k <= $n; $i++ ) $out[ implode( ' ', array_slice( $words, $i, $k ) ) ] = true;
		return array_keys( $out );
	}

	/** Jaccard exacto de dos conjuntos de tejas. */
	public static function jaccard( array $a, array $b ) {
		if ( ! $a || ! $b ) return 0.0;
		$a = array_flip( $a ); $b = array_flip( $b );
		$inter = count( array_intersect_key( $a, $b ) );
		return $inter / ( count( $a ) + count( $b ) - $inter );
	}

	/** MinHash de 64 valores: h_i(x) = (a_i·x + b_i) mod (2^31 − 1), con x = crc32 de la teja. */
	public static function signature( array $shingles ) {
		if ( ! $shingles ) return array();
		static $coef = null;
		$p = 2147483647;
		if ( null === $coef ) { $coef = array(); for ( $i = 0; $i < self::HASHES; $i++ ) $coef[] = array( ( crc32( 'a' . $i ) % ( $p - 1 ) ) + 1, crc32( 'b' . $i ) % $p ); }
		$xs  = array_map( function( $s ) { return crc32( $s ) & 0x7fffffff; }, $shingles );
		$sig = array();
		foreach ( $coef as $c ) {
			$min = $p;
			foreach ( $xs as $x ) { $h = ( $c[0] * $x + $c[1] ) % $p; if ( $h < $min ) $min = $h; }
			$sig[] = $min;
		}
		return $sig;
	}

	/** Similitud estimada: proporción de valores iguales. */
	public static function similarity( array $a, array $b ) {
		$n = min( count( $a ), count( $b ) );
		if ( ! $n ) return 0.0;
		$same = 0;
		for ( $i = 0; $i < $n; $i++ ) if ( $a[ $i ] === $b[ $i ] ) $same++;
		return $same / $n;
	}

	public static function pack( array $sig ) { return implode( ',', $sig ); }
	public static function unpack( $packed ) { return '' === (string) $packed ? array() : array_map( 'intval', explode( ',', (string) $packed ) ); }

	/**
	 * Pares candidatos por LSH (bandas de 4 valores): dos firmas que coinciden en una banda entera. Con un 70 % de
	 * similitud la probabilidad de ser candidatas es del 99 %; al 30 %, del 12 %. Devuelve "a-b" => similitud estimada.
	 *
	 * @param array $sigs id => firma.
	 */
	public static function candidates( array $sigs, $min = self::CANDIDATE, $cap = 5000 ) {
		$buckets = array();
		foreach ( $sigs as $id => $sig ) {
			if ( count( $sig ) < self::HASHES ) continue;
			for ( $b = 0; $b < self::HASHES / self::BAND; $b++ ) $buckets[ $b . ':' . implode( '.', array_slice( $sig, $b * self::BAND, self::BAND ) ) ][] = (int) $id;
		}
		$pairs = array();
		foreach ( $buckets as $ids ) {
			$ids = array_values( array_unique( $ids ) );
			$n   = count( $ids );
			for ( $i = 0; $i < $n; $i++ ) for ( $j = $i + 1; $j < $n; $j++ ) {
				$a = min( $ids[ $i ], $ids[ $j ] ); $z = max( $ids[ $i ], $ids[ $j ] );
				$key = $a . '-' . $z;
				if ( isset( $pairs[ $key ] ) ) continue;
				$sim = self::similarity( $sigs[ $a ], $sigs[ $z ] );
				if ( $sim >= $min ) $pairs[ $key ] = $sim;
				if ( count( $pairs ) >= $cap ) break 3;
			}
		}
		arsort( $pairs );
		return $pairs;
	}

	/** Grupos (componentes conexas) de los pares aceptados; cada grupo ordenado. */
	public static function clusters( array $pairs ) {
		$parent = array();
		$find   = function( $x ) use ( &$parent, &$find ) { if ( ! isset( $parent[ $x ] ) ) $parent[ $x ] = $x; return $parent[ $x ] === $x ? $x : ( $parent[ $x ] = $find( $parent[ $x ] ) ); };
		foreach ( array_keys( $pairs ) as $key ) {
			list( $a, $b ) = array_map( 'intval', explode( '-', (string) $key ) );
			$ra = $find( $a ); $rb = $find( $b );
			if ( $ra !== $rb ) $parent[ max( $ra, $rb ) ] = min( $ra, $rb );
		}
		$groups = array();
		foreach ( array_keys( $parent ) as $x ) $groups[ $find( $x ) ][] = (int) $x;
		$groups = array_map( function( $g ) { sort( $g ); return $g; }, array_values( $groups ) );
		usort( $groups, function( $a, $b ) { return count( $b ) - count( $a ) ?: $a[0] - $b[0]; } );
		return $groups;
	}

	/** Palabras vacías que no forman una palabra clave. */
	const STOPWORDS = array( 'de', 'del', 'la', 'el', 'los', 'las', 'en', 'y', 'e', 'o', 'u', 'a', 'al', 'para', 'con', 'por', 'un', 'una', 'unos', 'unas', 'que', 'tu', 'su', 'sus', 'tus', 'mi', 'es', 'se', 'lo', 'como', 'mas', 'más', 'the', 'of', 'and', 'in', 'for', 'to', 'on', 'with', 'your', 'a', 'an' );

	/**
	 * Palabra clave en disputa (pura): la keyword principal si todos la comparten; si no, las palabras que aparecen en
	 * todos los títulos, en el orden del primero. '' si no hay nada en común.
	 *
	 * @param string[] $titles   Títulos de los contenidos del grupo.
	 * @param string[] $keywords Keyword principal de cada uno ('' si no tiene).
	 */
	public static function shared_keyword( array $titles, array $keywords = array() ) {
		$norm = function( $s ) { return trim( implode( ' ', self::words( $s ) ) ); };
		$kw   = array_unique( array_map( $norm, $keywords ) );
		if ( 1 === count( $kw ) && '' !== reset( $kw ) && count( $keywords ) === count( $titles ) ) return reset( $kw );
		$sets = array();
		foreach ( $titles as $title ) $sets[] = array_values( array_diff( self::words( $title ), self::STOPWORDS ) );
		if ( ! $sets ) return '';
		$common = $sets[0];
		foreach ( array_slice( $sets, 1 ) as $set ) $common = array_values( array_intersect( $common, $set ) );
		return implode( ' ', array_unique( $common ) );
	}

	/**
	 * Cuál se queda (pura): la de más enlaces internos entrantes; a igualdad, la de más texto; después, la más antigua.
	 *
	 * @param array $members id => array( incoming, words, date ).
	 */
	public static function keeper( array $members ) {
		uksort( $members, function( $a, $b ) use ( $members ) {
			$x = $members[ $a ]; $y = $members[ $b ];
			return ( (int) $y['incoming'] - (int) $x['incoming'] ) ?: ( ( (int) $y['words'] - (int) $x['words'] ) ?: ( strcmp( (string) $x['date'], (string) $y['date'] ) ?: $a - $b ) );
		} );
		return (int) array_key_first( $members );
	}

	/* ------------------------------------------------------------------ */
	/* B1: shortcodes y variables sin resolver (puras).                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Lo que quedaría sin resolver en un campo de texto (título, descripción, miga, nombre). Ni WordPress ni el plugin
	 * ejecutan shortcodes ahí, así que cuenta toda etiqueta con forma de shortcode (el detector de A5) con «_» o «-» en el
	 * nombre ([descripcion_sitio], [ubicacion_usuario]) o de un plugin conocido. Una palabra suelta entre corchetes
	 * ([nuevo], y también [video] o [gallery] en «Receta [video]») se deja: puede ser texto escrito a propósito. Con
	 * $template, las variables %x% que el sitio no conoce (se imprimirían vacías); sin él, cualquier %x% (el texto no
	 * pasa por las plantillas y se vería literal).
	 *
	 * @return string[] Las marcas tal como se ven: «[descripcion_sitio]», «%seo_title%».
	 */
	public static function unresolved_in( $text, $template = false ) {
		$text = (string) $text;
		$out  = array();
		if ( false !== strpos( $text, '[' ) && class_exists( 'Digitalisimo_Integrations_Index_Hygiene' ) ) {
			$skip = array_flip( Digitalisimo_Integrations_Index_Hygiene::EXCLUDED );
			foreach ( Digitalisimo_Integrations_Index_Hygiene::shortcode_tags_in( $text ) as $tag ) {
				if ( ! isset( $skip[ $tag ] ) && ( false !== strpbrk( $tag, '_-' ) || Digitalisimo_Integrations_Index_Hygiene::known_plugin_tag( $tag ) ) ) $out[] = '[' . $tag . ']';
			}
		}
		if ( false !== strpos( $text, '%' ) && preg_match_all( '/%([a-z_]+)(?:\([^)%]*\))?%/i', $text, $m ) ) {
			$known = $template && class_exists( 'Digitalisimo_Integrations_SEO_Variables' ) ? array_flip( array_keys( Digitalisimo_Integrations_SEO_Variables::catalog() ) ) : array();
			foreach ( $m[1] as $i => $name ) if ( ! $template || ! isset( $known[ strtolower( $name ) ] ) ) $out[] = $m[0][ $i ];
		}
		return array_values( array_unique( $out ) );
	}

	/* ------------------------------------------------------------------ */
	/* C1: descripción para redes desactualizada (puras).                   */
	/* ------------------------------------------------------------------ */

	const MONTHS = array( 'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
		'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12 );

	/** Palabras de plazo: una fecha junto a ellas caduca. */
	const DEADLINE = '/(?<![\p{L}\p{N}])(?:termina|terminan|termin[oó]|hasta|vence|vencen|vigente|v[aá]lid[oa]s?|[uú]ltimo d[ií]a|cierra|cierran|finaliza|finalizan|acaba|acaban|ends?|until|expires?|valid|deadline|last day|through)(?![\p{L}\p{N}])/u';

	/**
	 * Fechas que menciona un texto («17 de noviembre», «17 de noviembre de 2026», «November 17, 2026») como marcas de
	 * tiempo del final de ese día (UTC). Sin año, el del momento de referencia $now.
	 *
	 * @return array<int,array{0:int,1:string}> (fin del día, texto encontrado).
	 */
	public static function dates_in( $text, $now ) {
		$text   = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
		$months = implode( '|', array_keys( self::MONTHS ) );
		$out    = array();
		$year   = (int) gmdate( 'Y', (int) $now );
		if ( preg_match_all( '/(?<!\d)(\d{1,2})\s+de\s+(' . $months . ')(?:\s+(?:de|del)\s+(\d{4}))?/u', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) $out[] = array( (int) $hit[1], self::MONTHS[ $hit[2] ], isset( $hit[3] ) && '' !== $hit[3] ? (int) $hit[3] : $year, trim( $hit[0] ), isset( $hit[3] ) && '' !== $hit[3] );
		}
		if ( preg_match_all( '/\b(' . $months . ')\s+(\d{1,2})(?!\d)(?:st|nd|rd|th)?(?:,?\s+(\d{4}))?/u', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) $out[] = array( (int) $hit[2], self::MONTHS[ $hit[1] ], isset( $hit[3] ) && '' !== $hit[3] ? (int) $hit[3] : $year, trim( $hit[0] ), isset( $hit[3] ) && '' !== $hit[3] );
		}
		$dates = array();
		foreach ( $out as $d ) {
			if ( $d[0] < 1 || $d[0] > 31 || ! checkdate( $d[1], $d[0], $d[2] ) ) continue;
			$ts = gmmktime( 23, 59, 59, $d[1], $d[0], $d[2] );
			// Sin año, una fecha de hace más de medio año es la del año que viene («vigente hasta el 6 de enero», en diciembre).
			if ( ! $d[4] && $ts < (int) $now - 180 * DAY_IN_SECONDS && checkdate( $d[1], $d[0], $d[2] + 1 ) ) $ts = gmmktime( 23, 59, 59, $d[1], $d[0], $d[2] + 1 );
			$dates[] = array( $ts, $d[3] );
		}
		return $dates;
	}

	/**
	 * Motivos para pensar que la descripción para redes quedó vieja (pura).
	 *
	 * @param string $desc     La descripción escrita a mano.
	 * @param array  $current  Firma del texto actual.
	 * @param array  $basis    Firma del texto cuando se vio por primera vez esta descripción (vacía si no se sabe).
	 * @param int    $modified Última modificación de la página (UTC).
	 * @param int    $now      Ahora.
	 * @param bool   $fresh    La descripción cambió desde el repaso anterior: se acaba de escribir, no está vieja.
	 * @return array<int,array{0:string,1:string}> (código, texto).
	 */
	public static function stale_reasons( $desc, array $current, array $basis, $modified, $now, $fresh = false ) {
		if ( $fresh ) return array();
		$out = array();
		if ( $current && $basis ) {
			$sim = self::similarity( $current, $basis );
			if ( $sim < self::STALE_SIM ) $out[] = array( 'changed', sprintf( 'la página cambió mucho desde que se escribió la descripción (sólo un %d %% del texto sigue igual)', (int) round( $sim * 100 ) ) );
		}
		// Un plazo («termina», «hasta», «vence»…) con una fecha que ya pasó, y la página se editó después: lo más probable es
		// que se actualizara sin ella. Una fecha sin plazo («se fundó el 3 de marzo») no dice nada.
		if ( preg_match( self::DEADLINE, function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $desc, 'UTF-8' ) : strtolower( (string) $desc ) ) ) {
			foreach ( self::dates_in( $desc, $now ) as $date ) {
				if ( $date[0] < (int) $now && (int) $modified > $date[0] ) { $out[] = array( 'date', 'anuncia un plazo («' . $date[1] . '») que ya pasó, y la página se actualizó después' ); break; }
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* B4: canonical (puras).                                               */
	/* ------------------------------------------------------------------ */

	/** Host sin www, en minúsculas. */
	public static function host( $url ) { return (string) preg_replace( '/^www\./', '', strtolower( (string) parse_url( (string) $url, PHP_URL_HOST ) ) ); }

	/** ¿El canonical va a otro dominio? Un subdominio del sitio (o el sitio de un subdominio) no cuenta. */
	public static function offsite( $canonical, $home ) {
		$host = self::host( $canonical ); $home = self::host( $home );
		if ( '' === $host || '' === $home || $host === $home ) return false;
		$tail = function( $a, $b ) { return strlen( $a ) > strlen( $b ) && '.' . $b === substr( $a, - ( strlen( $b ) + 1 ) ); };
		return ! $tail( $host, $home ) && ! $tail( $home, $host );
	}

	/**
	 * Veredicto de una comprobación (pura): ok, gone (el dominio no existe, 404 o 410), redirect (3xx: el canonical
	 * debe ser la URL final) o unknown (403, 5xx, sin respuesta: no se puede afirmar nada).
	 */
	public static function verdict( $code, $error = '', $resolves = true ) {
		$code = (int) $code;
		if ( 0 === $code ) return ( preg_match( '/cURL error 6\b|could not resolve host|name or service not known|nodename nor servname|getaddrinfo/i', (string) $error ) || ! $resolves ) ? 'gone' : 'unknown';
		if ( in_array( $code, array( 404, 410 ), true ) ) return 'gone';
		if ( $code >= 300 && $code < 400 ) return 'redirect';
		if ( $code >= 200 && $code < 300 ) return 'ok';
		return 'unknown';
	}

	/* ------------------------------------------------------------------ */
	/* Repaso en segundo plano.                                             */
	/* ------------------------------------------------------------------ */

	/** Se analiza sólo con DIGITALÍSIMO SEO publicando las señales. */
	public static function active() { return '' === Digitalisimo_Integrations_SEO_Resolver::foreign_seo(); }

	/** Los mismos tipos que el sitemap: lo que el sitio publica para buscadores. */
	public static function types() {
		$types = class_exists( 'Digitalisimo_Integrations_Links' ) ? Digitalisimo_Integrations_Links::types() : array( 'post', 'page' );
		return array_values( array_diff( $types, array( 'attachment', 'elementor_library' ) ) );
	}

	public static function state() { $s = get_option( self::STATE, array() ); return is_array( $s ) ? $s : array(); }
	public static function results() { $r = get_option( self::RESULTS, array() ); return is_array( $r ) ? $r : array(); }

	/** Primera vez, reglas nuevas o más de una semana desde el último repaso: empieza otro en segundo plano. */
	public static function maybe_start() {
		if ( ! self::active() || ! current_user_can( 'manage_options' ) ) return;
		$state = self::state();
		// Vencido sólo un repaso terminado Y reunido (sin «dirty»): uno que acaba de terminar y espera su finalize() no
		// se reinicia, o con el escritorio abierto (el heartbeat) se repasaría todo una y otra vez sin resultado.
		$stale = ! empty( $state['done'] ) && empty( $state['dirty'] ) && (int) ( $state['started'] ?? 0 ) < time() - WEEK_IN_SECONDS;
		if ( (int) ( $state['version'] ?? 0 ) >= self::VERSION && ! $stale ) {
			if ( ( empty( $state['done'] ) || ! empty( $state['dirty'] ) ) && ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON );
			return;
		}
		self::restart();
	}

	public static function restart() {
		update_option( self::STATE, array( 'version' => self::VERSION, 'cursor' => 0, 'done' => false, 'started' => time(), 'finished' => (int) ( self::state()['finished'] ?? 0 ) ), false );
		wp_clear_scheduled_hook( self::CRON );
		wp_schedule_single_event( time() + 30, self::CRON );
	}

	public static function handle_rescan() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_analysis_rescan' ) ) wp_die( 'No autorizado.' );
		self::restart();
		$canon = get_option( self::CANON, array() );
		if ( is_array( $canon ) ) { foreach ( $canon as $url => $row ) if ( is_array( $row ) ) $canon[ $url ]['at'] = 0; update_option( self::CANON, $canon, false ); }
		wp_safe_redirect( self::url( array( 'analysis' => 'restarted' ) ) );
		exit;
	}

	public static function url( array $args = array() ) { return add_query_arg( $args, admin_url( 'admin.php?page=digitalisimo-seo-audit&audit=site' ) ); }

	/** Al guardar un contenido publicado: su huella al momento y el resultado se rehace en unos minutos. */
	public static function on_save( $post_id, $post ) {
		if ( ! self::active() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post || 'publish' !== $post->post_status ) return;
		$state = self::state();
		// Sin un primer repaso (nadie abrió el escritorio desde que se instaló) no se analiza nada: él lo incluirá.
		if ( empty( $state['version'] ) || ! in_array( $post->post_type, self::types(), true ) ) return;
		self::scan_post( $post );
		$state['dirty'] = 1;
		update_option( self::STATE, $state, false );
		if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON );
	}

	/** Un lote del cron: 25 contenidos; al acabar, reúne el resultado (en otro turno del cron). */
	public static function run_batch() {
		global $wpdb;
		if ( ! self::active() ) return 0;
		$state = self::state();
		if ( ! empty( $state['done'] ) ) { if ( ! empty( $state['dirty'] ) ) self::finalize(); return 0; }
		$types = self::types();
		if ( ! $types ) return 0;
		$in   = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND ID > %d ORDER BY ID ASC LIMIT %d", array_merge( $types, array( (int) ( $state['cursor'] ?? 0 ), self::BATCH ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$started = microtime( true );
		$last    = (int) ( $state['cursor'] ?? 0 );
		foreach ( $ids as $id ) {
			self::scan_post( (int) $id );
			$last = (int) $id;
			wp_cache_delete( (int) $id, 'post_meta' );
			wp_cache_delete( (int) $id, 'posts' );
			if ( microtime( true ) - $started > self::$budget ) break;
		}
		$done = count( $ids ) < self::BATCH && $last === (int) ( $ids ? end( $ids ) : $last );
		$state = array_merge( $state, array( 'version' => self::VERSION, 'cursor' => $done ? 0 : $last, 'done' => $done, 'dirty' => $done ? 1 : 0, 'ran' => time() ) );
		update_option( self::STATE, $state, false );
		wp_schedule_single_event( time() + ( $done ? 10 : 30 ), self::CRON );
		return count( $ids );
	}

	/** Texto propio que se ve: el de los widgets en una página del constructor; si no, el del editor. */
	public static function visible_text( $post ) {
		if ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) && class_exists( 'Digitalisimo_Integrations_Index_Hygiene' ) ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );
			$data = is_string( $data ) ? json_decode( $data, true ) : $data;
			if ( is_array( $data ) ) return wp_strip_all_tags( Digitalisimo_Integrations_Index_Hygiene::elementor_text( $data ) );
		}
		$html = (string) $post->post_content;
		$html = (string) preg_replace( '#<(script|style|noscript|template|svg)\b[^>]*>.*?</\1>#is', ' ', $html );
		if ( function_exists( 'strip_shortcodes' ) ) $html = strip_shortcodes( $html );
		$html = (string) preg_replace( '/\[\/?[a-z][a-z0-9_\-]*(?:\s[^\]]*)?\]/i', ' ', $html );
		return wp_strip_all_tags( $html );
	}

	/** Huella e incidencias de un contenido publicado. Sólo escribe si algo cambió. */
	public static function scan_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) return array();
		$prev = get_post_meta( $post->ID, self::META, true );
		$prev = is_array( $prev ) ? $prev : array();
		if ( 'publish' !== $post->post_status ) { if ( $prev ) delete_post_meta( $post->ID, self::META ); return array(); }
		$P     = 'digitalisimo_seo_';
		$text  = self::visible_text( $post );
		$words = self::words( $text );
		$sig   = count( $words ) >= self::MIN_WORDS ? self::signature( self::shingles( $words ) ) : array();
		$row   = array( 'v' => self::VERSION, 'w' => count( $words ), 's' => self::pack( $sig ) );

		// B1: los campos que se publican como texto.
		$issues     = array();
		$fields     = array( 'post_title' => array( 'Título del contenido', $post->post_title, false ), 'title' => array( 'Título SEO', get_post_meta( $post->ID, $P . 'title', true ), true ), 'description' => array( 'Descripción SEO', get_post_meta( $post->ID, $P . 'description', true ), true ), 'social_title' => array( 'Título para redes', get_post_meta( $post->ID, $P . 'social_title', true ), true ), 'social_description' => array( 'Descripción para redes', get_post_meta( $post->ID, $P . 'social_description', true ), true ), 'breadcrumb_title' => array( 'Título en las migas', get_post_meta( $post->ID, $P . 'breadcrumb_title', true ), false ) );
		foreach ( $fields as $field => $def ) foreach ( self::unresolved_in( (string) $def[1], $def[2] ) as $token ) $issues[] = array( $field, $def[0], $token );
		if ( $issues ) $row['x'] = $issues;

		// C1: descripción para redes escrita a mano (sin variables: la automática sigue al contenido).
		$desc = trim( (string) ( get_post_meta( $post->ID, $P . 'social_description', true ) ?: get_post_meta( $post->ID, $P . 'description', true ) ) );
		if ( '' !== $desc && false === strpos( $desc, '%' ) ) {
			$hash  = md5( $desc );
			$same  = ( $prev['d'] ?? '' ) === $hash;
			// Cambió desde el repaso anterior: se acaba de escribir. (La primera vez no se sabe cuándo se escribió.)
			$fresh = isset( $prev['d'] ) && ! $same;
			$row['d']  = $hash;
			$row['b']  = $same && isset( $prev['b'] ) ? (string) $prev['b'] : $row['s'];
			$row['bt'] = $same && isset( $prev['bt'] ) ? (int) $prev['bt'] : time();
			$stale = self::stale_reasons( $desc, $sig, $same ? self::unpack( $row['b'] ) : array(), (int) strtotime( $post->post_modified_gmt . ' UTC' ), time(), $fresh );
			if ( $stale ) $row['st'] = $stale;
		}

		// B4: canonical propio distinto de la URL del contenido.
		$canonical = trim( (string) get_post_meta( $post->ID, $P . 'canonical', true ) );
		if ( '' !== $canonical && untrailingslashit( $canonical ) !== untrailingslashit( (string) get_permalink( $post ) ) ) {
			$row['c'] = $canonical;
			if ( self::offsite( $canonical, home_url( '/' ) ) ) $row['co'] = 1;
			else {
				$target = (int) url_to_postid( $canonical );
				if ( $target && 'publish' === get_post_status( $target ) ) $row['cok'] = 1;
			}
		}
		// Fuera de Google (noindex) o con canonical hacia otra página del sitio: duplicado a propósito, no compite.
		if ( '1' === (string) get_post_meta( $post->ID, $P . 'noindex', true ) || false !== strpos( (string) get_post_meta( $post->ID, $P . 'robots', true ), 'noindex' ) ) $row['n'] = 1;
		elseif ( ! empty( $row['cok'] ) ) $row['n'] = 1;
		$keyword = trim( explode( ',', (string) get_post_meta( $post->ID, $P . 'keywords', true ) )[0] );
		if ( '' !== $keyword ) $row['k'] = $keyword;
		if ( $row != $prev ) update_post_meta( $post->ID, self::META, $row ); // phpcs:ignore Universal.Operators.StrictComparisons -- mismo contenido.
		return $row;
	}

	/** Ajustes del sitio que se publican como texto o plantilla: etiqueta => array( valor, ¿plantilla? ). */
	public static function site_fields() {
		$out = array(
			'Migas: texto de Inicio'                => array( self::o( 'seo_breadcrumbs_home_label', 'Inicio' ), false ),
			'Migas: texto antes de las migas'       => array( self::o( 'seo_breadcrumbs_prefix', '' ), false ),
			'Migas: archivos de autor y fecha'      => array( self::o( 'seo_breadcrumbs_archive_format', '' ), false ),
			'Migas: resultados de búsqueda'         => array( self::o( 'seo_breadcrumbs_search_format', '' ), false ),
			'Migas: página no encontrada'           => array( self::o( 'seo_breadcrumbs_404_label', '' ), false ),
			'Nombre SEO del sitio'                  => array( self::o( 'seo_site_name', '' ), false ),
			'Nombre de la organización'             => array( self::o( 'seo_organization_name', '' ), false ),
			'Nombre del sitio (Ajustes → Generales)' => array( get_bloginfo( 'name' ), false ),
			'Descripción corta (Ajustes → Generales)' => array( get_bloginfo( 'description' ), false ),
		);
		foreach ( array( 'post' => 'entradas', 'page' => 'páginas', 'archive' => 'archivos', 'taxonomy' => 'categorías y etiquetas', 'author' => 'autores', 'home' => 'la portada de entradas' ) as $key => $label ) {
			$out[ 'Plantilla de títulos de ' . $label ]       = array( self::o( 'seo_title_' . $key, '' ), true );
			$out[ 'Plantilla de descripciones de ' . $label ] = array( self::o( 'seo_description_' . $key, '' ), true );
		}
		foreach ( array( 'seo_type_templates' => 'tipo', 'seo_tax_templates' => 'taxonomía' ) as $option => $what ) {
			$rows = self::o( $option, array() );
			foreach ( is_array( $rows ) ? $rows : array() as $name => $row ) foreach ( is_array( $row ) ? $row : array() as $field => $value ) if ( is_string( $value ) ) $out[ 'Plantilla del ' . $what . ' ' . sanitize_key( (string) $name ) . ' (' . sanitize_key( (string) $field ) . ')' ] = array( $value, true );
		}
		return $out;
	}

	/** Términos con un shortcode o una variable en su nombre o en sus datos SEO (una consulta). */
	private static function term_issues() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT t.term_id, t.name, tt.taxonomy, m.meta_key, m.meta_value FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id LEFT JOIN {$wpdb->termmeta} m ON m.term_id = t.term_id AND m.meta_key IN ('digitalisimo_seo_title','digitalisimo_seo_description','digitalisimo_seo_breadcrumb_title') WHERE t.name LIKE '%[%' OR t.name LIKE '%\\%%' OR m.meta_value LIKE '%[%' OR m.meta_value LIKE '%\\%%' LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$tax = get_taxonomy( $row['taxonomy'] );
			if ( ! $tax || empty( $tax->public ) ) continue;
			foreach ( self::unresolved_in( (string) $row['name'], false ) as $token ) $out[ $row['term_id'] . '|name|' . $token ] = array( (int) $row['term_id'], $row['taxonomy'], 'Nombre', $token );
			if ( ! empty( $row['meta_key'] ) ) {
				$label = array( 'digitalisimo_seo_title' => 'Título SEO', 'digitalisimo_seo_description' => 'Descripción SEO', 'digitalisimo_seo_breadcrumb_title' => 'Título en las migas' )[ $row['meta_key'] ] ?? $row['meta_key'];
				foreach ( self::unresolved_in( (string) $row['meta_value'], 'digitalisimo_seo_breadcrumb_title' !== $row['meta_key'] ) as $token ) $out[ $row['term_id'] . '|' . $row['meta_key'] . '|' . $token ] = array( (int) $row['term_id'], $row['taxonomy'], $label, $token );
			}
		}
		return array_values( $out );
	}

	/** Contenidos enlazados desde los menús o desde las plantillas del constructor (cabecera, pie): no son huérfanos. */
	public static function linked_elsewhere() {
		global $wpdb;
		$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT o.meta_value FROM {$wpdb->postmeta} o JOIN {$wpdb->postmeta} t ON t.post_id = o.post_id AND t.meta_key = '_menu_item_type' AND t.meta_value = 'post_type' JOIN {$wpdb->posts} p ON p.ID = o.post_id AND p.post_type = 'nav_menu_item' AND p.post_status = 'publish' WHERE o.meta_key = '_menu_item_object_id'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$home = home_url( '/' );
		// Enlaces personalizados de los menús que apuntan a una página del sitio.
		foreach ( (array) $wpdb->get_col( "SELECT u.meta_value FROM {$wpdb->postmeta} u JOIN {$wpdb->posts} p ON p.ID = u.post_id AND p.post_type = 'nav_menu_item' AND p.post_status = 'publish' WHERE u.meta_key = '_menu_item_url' AND u.meta_value <> '' LIMIT 1000" ) as $url ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$url = 0 === strpos( (string) $url, '/' ) ? home_url( (string) $url ) : (string) $url;
			if ( self::host( $url ) === self::host( $home ) ) { $id = (int) url_to_postid( $url ); if ( $id ) $ids[] = $id; }
		}
		$templates = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 200, 'no_found_rows' => true, 'suppress_filters' => true ) );
		$started = microtime( true );
		foreach ( $templates as $template ) {
			if ( microtime( true ) - $started > self::$budget ) break;
			$html = (string) $template->post_content;
			$data = get_post_meta( $template->ID, '_elementor_data', true );
			if ( is_string( $data ) ) $html .= ' ' . str_replace( '\\/', '/', $data );
			if ( class_exists( 'Digitalisimo_Integrations_Links' ) ) foreach ( Digitalisimo_Integrations_Links::extract( $html, $home ) as $link ) if ( $link['internal'] ) { $id = (int) url_to_postid( explode( '#', $link['url'] )[0] ); if ( $id ) $ids[] = $id; }
			if ( preg_match_all( '#"url"\s*:\s*"(https?:[^"]+)"#', $html, $m ) ) foreach ( $m[1] as $url ) if ( self::host( $url ) === self::host( $home ) ) { $id = (int) url_to_postid( $url ); if ( $id ) $ids[] = $id; }
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/** Reúne el resultado de todos los contenidos: canibalización, errores B1, canonical y descripciones viejas. */
	public static function finalize() {
		global $wpdb;
		$types = self::types();
		$in    = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_date_gmt, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ($in) ORDER BY p.post_date_gmt DESC LIMIT %d", self::META, self::MAX_POSTS ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$data = array();
		foreach ( (array) $rows as $row ) { $meta = maybe_unserialize( $row['meta_value'] ); if ( is_array( $meta ) ) $data[ (int) $row['ID'] ] = $meta + array( 'title' => (string) $row['post_title'], 'date' => (string) $row['post_date_gmt'] ); }
		// Lo que un lote de «Limpieza SEO» ya redirigió (301 o 410) no se sirve: no compite ni tiene incidencias.
		foreach ( Digitalisimo_Integrations_SEO_Cleanup::retired_ids() as $id ) unset( $data[ $id ] );
		$started = microtime( true );

		// B2: pares candidatos, comprobados con el Jaccard exacto mientras alcance el tiempo.
		$sigs = array();
		foreach ( $data as $id => $meta ) { if ( ! empty( $meta['n'] ) ) continue; $sig = self::unpack( $meta['s'] ?? '' ); if ( count( $sig ) === self::HASHES ) $sigs[ $id ] = $sig; }
		$pairs    = array_slice( self::candidates( $sigs ), 0, self::MAX_PAIRS, true );
		$accepted = array();
		$cache    = array();
		$shingle  = function( $id ) use ( &$cache ) { if ( ! isset( $cache[ $id ] ) ) { $post = get_post( $id ); $cache[ $id ] = $post ? self::shingles( self::words( self::visible_text( $post ) ) ) : array(); } return $cache[ $id ]; };
		foreach ( $pairs as $key => $estimate ) {
			list( $a, $b ) = array_map( 'intval', explode( '-', $key ) );
			$exact = microtime( true ) - $started < self::$budget ? self::jaccard( $shingle( $a ), $shingle( $b ) ) : null;
			$sim   = null === $exact ? $estimate : $exact;
			if ( $sim >= self::SIMILAR ) $accepted[ $key ] = array( round( $sim, 3 ), null === $exact );
		}
		$incoming = class_exists( 'Digitalisimo_Integrations_Links' ) && Digitalisimo_Integrations_Links::installed() ? Digitalisimo_Integrations_Links::counts( array_keys( $sigs ) ) : array();
		$groups   = array();
		foreach ( self::clusters( $accepted ) as $members ) {
			$info = array(); $sims = array();
			foreach ( $members as $id ) $info[ $id ] = array( 'incoming' => (int) ( $incoming[ $id ]['incoming'] ?? 0 ), 'words' => (int) ( $data[ $id ]['w'] ?? 0 ), 'date' => $data[ $id ]['date'] ?? '' );
			foreach ( $accepted as $key => $pair ) { list( $a, $b ) = array_map( 'intval', explode( '-', $key ) ); if ( isset( $info[ $a ] ) && isset( $info[ $b ] ) ) $sims[] = $pair[0]; }
			// Una página que nunca se redirige (la portada, la del blog…) es la que se queda.
			$keeper = self::keeper( $info );
			foreach ( $members as $id ) if ( Digitalisimo_Integrations_SEO_Cleanup::protected_reason( $id ) ) { $keeper = $id; break; }
			$groups[] = array( 'ids' => $members, 'max' => max( $sims ), 'min' => min( $sims ), 'keeper' => $keeper, 'keyword' => self::shared_keyword( array_map( function( $id ) use ( $data ) { return $data[ $id ]['title']; }, $members ), array_map( function( $id ) use ( $data ) { return (string) ( $data[ $id ]['k'] ?? '' ); }, $members ) ), 'info' => $info );
		}
		// La misma keyword principal en varios contenidos que no están ya en un grupo de texto casi igual.
		$grouped = array();
		foreach ( $groups as $group ) foreach ( $group['ids'] as $id ) $grouped[ $id ] = true;
		$by_kw = array();
		foreach ( $data as $id => $meta ) if ( ! empty( $meta['k'] ) && empty( $meta['n'] ) ) $by_kw[ trim( implode( ' ', self::words( $meta['k'] ) ) ) ][] = $id;
		$same_kw = array();
		foreach ( $by_kw as $kw => $ids ) if ( '' !== $kw && count( $ids ) > 1 && count( array_diff_key( array_flip( $ids ), $grouped ) ) > 0 ) $same_kw[] = array( 'keyword' => $kw, 'ids' => array_slice( $ids, 0, 20 ) );

		// B1, C1 y B4 por contenido.
		$b1 = array(); $stale = array(); $canon = array();
		foreach ( $data as $id => $meta ) {
			if ( ! empty( $meta['x'] ) && count( $b1 ) < self::LIST_MAX ) $b1[ $id ] = $meta['x'];
			if ( ! empty( $meta['st'] ) && count( $stale ) < self::LIST_MAX ) $stale[ $id ] = $meta['st'];
			if ( ! empty( $meta['c'] ) && empty( $meta['cok'] ) && count( $canon ) < self::LIST_MAX ) $canon[ $id ] = array( 'url' => (string) $meta['c'], 'offsite' => ! empty( $meta['co'] ) );
		}
		$site = array();
		foreach ( self::site_fields() as $label => $field ) foreach ( self::unresolved_in( (string) $field[0], $field[1] ) as $token ) $site[] = array( $label, $token );
		$results = array(
			'at'        => time(),
			'posts'     => count( $data ),
			'b1_site'   => $site,
			'b1_posts'  => $b1,
			'b1_terms'  => self::term_issues(),
			'clusters'  => $groups,
			'same_kw'   => array_slice( $same_kw, 0, 50 ),
			'stale'     => $stale,
			'canonical' => $canon,
			'linked'    => self::linked_elsewhere(),
		);
		update_option( self::RESULTS, $results, false );
		$state = self::state();
		update_option( self::STATE, array_merge( $state, array( 'dirty' => 0, 'finished' => time() ) ), false );
		if ( $canon && ! wp_next_scheduled( self::CANON_CRON ) ) wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CANON_CRON );
		return $results;
	}

	/* ------------------------------------------------------------------ */
	/* B4: comprobación HTTP del canonical (cron).                          */
	/* ------------------------------------------------------------------ */

	public static function canonical_state() { $c = get_option( self::CANON, array() ); return is_array( $c ) ? $c : array(); }

	/** Una petición: HEAD y, si no lo admite, un GET corto; sin seguir redirecciones; con la protección SSRF de WordPress. */
	public static function probe( $url ) {
		$args = array( 'timeout' => 5, 'redirection' => 0, 'user-agent' => 'Mozilla/5.0 (compatible; DIGITALISIMO SEO canonical check; ' . home_url( '/' ) . ')' );
		$res  = wp_safe_remote_head( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		// Un servidor que no admite HEAD responde 403, 404, 405, 410 o 501, o corta: se repite con un GET corto (un 404 sólo
		// cuenta si también lo da el GET). Un dominio que no existe no se reintenta.
		$dns  = is_wp_error( $res ) && 'gone' === self::verdict( 0, $res->get_error_message() );
		if ( ! $dns && ( is_wp_error( $res ) || in_array( $code, array( 403, 404, 405, 410, 501 ), true ) ) ) {
			$res  = wp_safe_remote_get( $url, $args + array( 'limit_response_size' => 4096 ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		}
		$error    = is_wp_error( $res ) ? substr( wp_strip_all_tags( $res->get_error_message() ), 0, 160 ) : '';
		$host     = self::host( $url );
		$resolves = 0 !== $code || '' === $host || preg_match( '/^[0-9.]+$|:/', $host ) || ! function_exists( 'gethostbynamel' ) || (bool) @gethostbynamel( $host ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- un nombre que no existe sólo devuelve false.
		$location = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_header( $res, 'location' );
		return array( 'code' => $code, 'error' => $error, 'verdict' => self::verdict( $code, $error, $resolves ), 'location' => substr( $location, 0, 300 ) );
	}

	/**
	 * Comprueba los canonical pendientes (los no comprobados y los de hace más de una semana) dentro del presupuesto;
	 * un «gone» nuevo queda pendiente y se confirma en otra vuelta al menos media hora después.
	 */
	public static function run_canonical() {
		if ( ! self::active() ) return;
		$results = self::results();
		$state   = self::canonical_state();
		$urls    = array_values( array_unique( array_map( function( $c ) { return (string) $c['url']; }, (array) ( $results['canonical'] ?? array() ) ) ) );
		$started = microtime( true );
		$retry   = false;
		$keep    = array();
		foreach ( $urls as $url ) {
			$row = $state[ $url ] ?? array();
			$keep[ $url ] = $row;
			$due = empty( $row['at'] ) || (int) $row['at'] < time() - self::CANON_TTL || ( ! empty( $row['pending'] ) && (int) $row['at'] <= time() - self::CONFIRM_AFTER );
			if ( ! $due || microtime( true ) - $started > self::$budget ) { if ( $due ) $retry = true; continue; }
			$new = self::probe( $url );
			$new['at'] = time();
			if ( 'gone' === $new['verdict'] && empty( $row['pending'] ) && 'gone' !== ( $row['verdict'] ?? '' ) ) { $new['pending'] = 1; $retry = true; }
			$keep[ $url ] = $new;
		}
		update_option( self::CANON, $keep, false );
		wp_clear_scheduled_hook( self::CANON_CRON );
		if ( $retry ) wp_schedule_single_event( time() + ( microtime( true ) - $started > self::$budget ? MINUTE_IN_SECONDS : self::CONFIRM_AFTER + 60 ), self::CANON_CRON );
	}

	/* ------------------------------------------------------------------ */
	/* Lectura: resumen, avisos y MCP.                                      */
	/* ------------------------------------------------------------------ */

	/** Contenidos con shortcodes sin resolver en su texto (los marca A5). */
	public static function content_issues( $limit = 200 ) {
		if ( ! class_exists( 'Digitalisimo_Integrations_Index_Hygiene' ) ) return array();
		return get_posts( array( 'post_type' => Digitalisimo_Integrations_Index_Hygiene::types(), 'post_status' => 'publish', 'posts_per_page' => $limit, 'no_found_rows' => true, 'fields' => 'ids', 'meta_key' => Digitalisimo_Integrations_Index_Hygiene::META ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	/** Canonical con problema: cada uno con su veredicto (offsite, gone, pending, redirect, unchecked). */
	public static function canonical_issues() {
		$results = self::results();
		$state   = self::canonical_state();
		$out     = array();
		foreach ( (array) ( $results['canonical'] ?? array() ) as $id => $c ) {
			$row     = $state[ $c['url'] ] ?? array();
			$verdict = (string) ( $row['verdict'] ?? '' );
			$kind    = 'gone' === $verdict ? ( empty( $row['pending'] ) ? 'gone' : 'pending' ) : ( 'redirect' === $verdict ? 'redirect' : ( ! empty( $c['offsite'] ) ? 'offsite' : ( '' === $verdict ? 'unchecked' : '' ) ) );
			if ( '' === $kind ) continue;
			$out[ (int) $id ] = array( 'url' => $c['url'], 'kind' => $kind, 'offsite' => ! empty( $c['offsite'] ), 'code' => (int) ( $row['code'] ?? 0 ), 'error' => (string) ( $row['error'] ?? '' ), 'location' => (string) ( $row['location'] ?? '' ) );
		}
		return $out;
	}

	/** Recuentos y listas cortas para el aviso y el MCP (lee opciones; sin peticiones). */
	public static function summary( $with_orphans = true ) {
		$r       = self::results();
		$canon   = self::canonical_issues();
		$broken  = array_filter( $canon, function( $c ) { return 'gone' === $c['kind']; } );
		$content = count( self::content_issues() );
		$b1     = count( (array) ( $r['b1_site'] ?? array() ) ) + count( (array) ( $r['b1_posts'] ?? array() ) ) + count( (array) ( $r['b1_terms'] ?? array() ) );
		$state  = self::state();
		return array(
			'analyzed_at'       => (int) ( $r['at'] ?? 0 ),
			'running'           => empty( $state['done'] ) && ! empty( $state['version'] ),
			'errors'            => array(
				'unresolved_settings' => array_map( function( $row ) { return $row[0] . ': ' . $row[1]; }, (array) ( $r['b1_site'] ?? array() ) ),
				'unresolved_posts'    => count( (array) ( $r['b1_posts'] ?? array() ) ),
				'unresolved_terms'    => count( (array) ( $r['b1_terms'] ?? array() ) ),
				'unresolved_content'  => $content,
				'canonical_broken'    => array_values( array_map( function( $id, $c ) { return array( 'post_id' => $id, 'url' => get_permalink( $id ), 'canonical' => $c['url'], 'detail' => $c['error'] ?: 'responde ' . $c['code'] ); }, array_keys( $broken ), $broken ) ),
			),
			'warnings'          => array(
				'cannibalization'   => array_map( function( $g ) { return array( 'keyword' => $g['keyword'], 'similarity' => (int) round( $g['max'] * 100 ), 'keep' => get_permalink( $g['keeper'] ), 'urls' => array_map( 'get_permalink', $g['ids'] ) ); }, array_slice( (array) ( $r['clusters'] ?? array() ), 0, 20 ) ),
				'same_keyword'      => count( (array) ( $r['same_kw'] ?? array() ) ),
				'canonical_offsite' => count( array_filter( $canon, function( $c ) { return in_array( $c['kind'], array( 'offsite', 'redirect', 'pending' ), true ); } ) ),
				'stale_social_description' => array_values( array_map( function( $id ) { return get_permalink( $id ); }, array_slice( array_keys( (array) ( $r['stale'] ?? array() ) ), 0, 20 ) ) ),
				'orphans'           => $with_orphans ? count( self::orphans() ) : null,
			),
			'error_count'       => $b1 + $content + count( $broken ),
		);
	}

	/** Huérfanas que sí se pueden tratar: sin enlaces internos ni de menús o plantillas, y que no son páginas del sistema. */
	public static function orphans( $limit = 500 ) {
		if ( ! class_exists( 'Digitalisimo_Integrations_Links' ) || ! Digitalisimo_Integrations_Links::enabled() || ! Digitalisimo_Integrations_Links::installed() ) return array();
		$linked = array_flip( (array) ( self::results()['linked'] ?? array() ) );
		$out    = array();
		foreach ( Digitalisimo_Integrations_Links::orphans( $limit ) as $id ) if ( ! isset( $linked[ $id ] ) && ! Digitalisimo_Integrations_SEO_Cleanup::protected_reason( $id ) ) $out[] = (int) $id;
		return $out;
	}

	/** Aviso en el Escritorio y en las pantallas DIGITALÍSIMO: errores (B1, canonical roto) y avisos, con la X. */
	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) || ( function_exists( 'is_network_admin' ) && is_network_admin() ) || ! self::active() ) return;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se muestra.
		if ( ! ( $screen && 'dashboard' === $screen->id ) && 0 !== strpos( $page, 'digitalisimo' ) ) return;
		if ( 'digitalisimo-seo-audit' === $page ) return; // La propia pantalla ya lo muestra entero.
		$r = self::results();
		if ( empty( $r['at'] ) ) return;
		// Barato: cuenta lo ya reunido, sin armar direcciones. La huella son los TIPOS de problema, no los números: un
		// aviso cerrado no vuelve porque cambie una cifra (regla de Digitalisimo_Notices).
		$canon  = self::canonical_issues();
		$broken = count( array_filter( $canon, function( $c ) { return 'gone' === $c['kind']; } ) );
		$other  = count( $canon ) - $broken - count( array_filter( $canon, function( $c ) { return 'unchecked' === $c['kind']; } ) );
		$b1     = count( (array) ( $r['b1_site'] ?? array() ) ) + count( (array) ( $r['b1_posts'] ?? array() ) ) + count( (array) ( $r['b1_terms'] ?? array() ) ) + count( self::content_issues() );
		$kinds  = array_filter( array(
			'b1'       => $b1 ? sprintf( 'Shortcodes o variables sin resolver que se ven publicados: %d (títulos, descripciones, migas o contenido).', $b1 ) : '',
			'canon'    => $broken ? sprintf( 'Canonical hacia una URL que no responde: %d.', $broken ) : '',
			'cluster'  => ! empty( $r['clusters'] ) ? sprintf( 'Grupos de páginas casi iguales que compiten por la misma búsqueda: %d.', count( (array) $r['clusters'] ) ) : '',
			'offsite'  => $other ? sprintf( 'Canonical hacia otro dominio o que redirige: %d.', $other ) : '',
			'stale'    => ! empty( $r['stale'] ) ? sprintf( 'Descripciones para redes que parecen desactualizadas: %d.', count( (array) $r['stale'] ) ) : '',
		) );
		if ( ! $kinds ) return;
		$type = ( $b1 || $broken ) ? 'error' : 'warning';
		$html = '<p><strong>' . esc_html( 'DIGITALÍSIMO SEO: el análisis del sitio encontró cosas por corregir' ) . '</strong></p><ul style="list-style:disc;padding-left:1.5em"><li>' . implode( '</li><li>', array_map( 'esc_html', $kinds ) ) . '</li></ul><p><a href="' . esc_url( self::url() ) . '">' . esc_html( 'Abrir SEO → Auditoría → Análisis del sitio' ) . '</a></p>';
		$items = array_keys( $kinds );
		Digitalisimo_Notices::show( Digitalisimo_Notices::site( 'seo-site-analysis' ), $type, $html, array( 'hash' => $items, 'cap' => 'manage_options' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Pantalla: SEO → Auditoría → Análisis del sitio.                      */
	/* ------------------------------------------------------------------ */

	public static function link( $id ) {
		$path = (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH );
		return '<a href="' . esc_url( get_edit_post_link( $id ) ?: (string) get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ?: '(sin título)' ) . '</a><br><code>' . esc_html( $path ) . '</code>';
	}

	public static function render() {
		echo '<h2>Análisis del sitio</h2>';
		if ( ! self::active() ) { echo '<p>' . esc_html( Digitalisimo_Integrations_SEO_Resolver::foreign_seo() . ' está activo: sus títulos, descripciones y canonical no son los de DIGITALÍSIMO SEO, así que no se analizan.' ) . '</p>'; return; }
		Digitalisimo_Integrations_SEO_Cleanup::render_messages();
		Digitalisimo_Integrations_SEO_Cleanup::render_plan();
		$state = self::state();
		$r     = self::results();
		echo '<p>Revisa en segundo plano lo que el sitio publica: shortcodes y variables sin resolver, páginas que compiten entre sí, páginas sin enlaces, canonical a otro dominio o roto y descripciones para redes desactualizadas. Sólo informa; cada acción pide tu confirmación y guarda un respaldo para deshacerla.</p>';
		if ( empty( $state['done'] ) && ! empty( $state['version'] ) ) echo '<div class="notice notice-info inline"><p>Análisis en curso, por lotes en segundo plano. Los resultados de abajo son del repaso anterior.</p></div>';
		if ( empty( $r['at'] ) ) echo '<p class="description">Aún no hay resultados: el primer repaso empieza al abrir el escritorio y tarda unos minutos.</p>';
		else echo '<p class="description">' . esc_html( sprintf( 'Último análisis: %1$s · %2$d contenidos publicados.', wp_date( 'Y-m-d H:i', (int) $r['at'] ), (int) $r['posts'] ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_analysis_rescan">';
		wp_nonce_field( 'digitalisimo_seo_analysis_rescan' );
		submit_button( 'Analizar de nuevo', 'secondary', 'submit', false );
		echo '</form>';
		if ( empty( $r['at'] ) ) { Digitalisimo_Integrations_SEO_Cleanup::render_log(); return; }
		self::render_unresolved( $r );
		self::render_canonical();
		self::render_clusters( $r );
		self::render_orphans();
		self::render_stale( $r );
		Digitalisimo_Integrations_SEO_Cleanup::render_log();
	}

	private static function badge( $status, $label ) { return class_exists( 'Digitalisimo_Integrations_Quality_Audit' ) ? Digitalisimo_Integrations_Quality_Audit::badge( $status, $label ) : '<strong>' . esc_html( $label ) . '</strong>'; }

	private static function render_unresolved( array $r ) {
		$content = self::content_issues();
		$total   = count( (array) $r['b1_site'] ) + count( (array) $r['b1_posts'] ) + count( (array) $r['b1_terms'] ) + count( $content );
		echo '<h3>Shortcodes y variables sin resolver (' . (int) $total . ')</h3>';
		if ( ! $total ) { echo '<p>' . self::badge( 'OK', 'SIN ERRORES' ) . ' Ningún título, descripción, miga ni contenido muestra un shortcode o una variable sin resolver.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p>' . self::badge( 'ERROR', 'ERROR' ) . ' Se publican tal cual: los visitantes y Google ven el código en lugar del texto. Corrígelo donde dice cada fila.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<table class="widefat striped"><thead><tr><th>Dónde</th><th>Campo</th><th>Sin resolver</th></tr></thead><tbody>';
		foreach ( (array) $r['b1_site'] as $row ) echo '<tr><td>Ajustes del sitio</td><td>' . esc_html( $row[0] ) . '</td><td><code>' . esc_html( $row[1] ) . '</code></td></tr>';
		foreach ( (array) $r['b1_posts'] as $id => $issues ) foreach ( (array) $issues as $issue ) echo '<tr><td>' . self::link( (int) $id ) . '</td><td>' . esc_html( $issue[1] ) . '</td><td><code>' . esc_html( $issue[2] ) . '</code></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( (array) $r['b1_terms'] as $row ) { $term = get_term( (int) $row[0], $row[1] ); $name = $term && ! is_wp_error( $term ) ? $term->name : '#' . (int) $row[0]; echo '<tr><td><a href="' . esc_url( (string) get_edit_term_link( (int) $row[0], $row[1] ) ) . '">' . esc_html( $name ) . '</a> (' . esc_html( $row[1] ) . ')</td><td>' . esc_html( $row[2] ) . '</td><td><code>' . esc_html( $row[3] ) . '</code></td></tr>'; }
		foreach ( $content as $id ) echo '<tr><td>' . self::link( (int) $id ) . '</td><td>Contenido</td><td><code>' . esc_html( class_exists( 'Digitalisimo_Integrations_Index_Hygiene' ) ? Digitalisimo_Integrations_Index_Hygiene::editor_note( get_post( $id ) ) : '' ) . '</code></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</tbody></table><p class="description">Los del contenido son los mismos que lista SEO → Indexación (shortcodes de plugins que ya no están). En títulos y descripciones nunca se ejecutan shortcodes; las variables válidas están en SEO → Títulos.</p>';
	}

	private static function render_canonical() {
		$issues = self::canonical_issues();
		echo '<h3>Canonical hacia otro dominio o roto (' . count( $issues ) . ')</h3>';
		if ( ! $issues ) { echo '<p>' . self::badge( 'OK', 'SIN PROBLEMAS' ) . ' Ningún canonical escrito a mano apunta fuera del sitio ni a una URL que falle.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		$labels = array( 'gone' => array( 'ERROR', 'No responde' ), 'pending' => array( 'AVISO', 'Parece roto (se confirma en una hora)' ), 'redirect' => array( 'AVISO', 'Redirige' ), 'offsite' => array( 'AVISO', 'Otro dominio' ), 'unchecked' => array( 'INFO', 'Sin comprobar todavía' ) );
		echo '<table class="widefat striped"><thead><tr><th>Contenido</th><th>Canonical</th><th>Estado</th></tr></thead><tbody>';
		foreach ( $issues as $id => $c ) {
			$detail = 'gone' === $c['kind'] || 'pending' === $c['kind'] ? ( $c['error'] ?: 'responde ' . $c['code'] ) : ( 'redirect' === $c['kind'] ? 'responde ' . $c['code'] . ( $c['location'] ? ' hacia ' . $c['location'] : '' ) : ( $c['offsite'] ? 'Google lo trata como una copia de esa otra página' : '' ) );
			echo '<tr><td>' . self::link( $id ) . '</td><td><code>' . esc_html( $c['url'] ) . '</code></td><td>' . self::badge( $labels[ $c['kind'] ][0], $labels[ $c['kind'] ][1] ) . ' ' . esc_html( $detail ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table><p class="description">Corrígelo en el editor del contenido → SEO → Avanzado → URL canonical: vacío usa la propia URL de la página. Se comprueba en segundo plano y cada semana.</p>';
	}

	private static function render_clusters( array $r ) {
		$groups = (array) $r['clusters'];
		echo '<h3>Páginas que compiten entre sí (' . count( $groups ) . ')</h3>';
		if ( ! $groups ) echo '<p>' . self::badge( 'OK', 'SIN CANIBALIZACIÓN' ) . ' Ningún par de páginas comparte más del ' . (int) ( self::SIMILAR * 100 ) . ' % de su texto.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		else echo '<p>Comparten casi todo el texto y compiten por la misma búsqueda: Google elige una y las demás se reparten la fuerza. Se propone conservar la de más enlaces internos (o la de más texto); elige otra si prefieres y redirige las demás con 301.</p>';
		foreach ( $groups as $i => $group ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0;padding:10px;border:1px solid var(--digi-line,#dce4ff);border-radius:8px">';
			wp_nonce_field( 'digitalisimo_seo_cleanup_preview' );
			echo '<input type="hidden" name="action" value="digitalisimo_seo_cleanup_preview"><input type="hidden" name="kind" value="301"><input type="hidden" name="source" value="cluster">';
			echo '<p><strong>' . esc_html( '' !== $group['keyword'] ? 'Palabra clave en disputa: «' . $group['keyword'] . '»' : 'Texto casi igual' ) . '</strong> · ' . esc_html( sprintf( '%d %% de texto en común', (int) round( $group['max'] * 100 ) ) ) . '</p>';
			echo '<table class="widefat striped"><thead><tr><th>Se queda</th><th>Página</th><th>Enlaces entrantes</th><th>Palabras</th></tr></thead><tbody>';
			foreach ( $group['ids'] as $id ) {
				$protected = Digitalisimo_Integrations_SEO_Cleanup::protected_reason( $id );
				echo '<tr><td><input type="radio" name="target" value="' . (int) $id . '"' . checked( (int) $group['keeper'], (int) $id, false ) . '><input type="hidden" name="ids[]" value="' . (int) $id . '"></td><td>' . self::link( $id ) . ( $protected ? '<br><span class="description">' . esc_html( $protected . ': nunca se redirige.' ) . '</span>' : '' ) . '</td><td>' . (int) $group['info'][ $id ]['incoming'] . '</td><td>' . (int) $group['info'][ $id ]['words'] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</tbody></table><p>';
			submit_button( 'Revisar: conservar la elegida y redirigir las demás (301)', 'secondary', 'submit-' . (int) $i, false );
			echo '</p></form>';
		}
		$same = (array) ( $r['same_kw'] ?? array() );
		if ( $same ) {
			echo '<h4>Con la misma keyword principal (' . count( $same ) . ')</h4><p class="description">Su texto es distinto, pero apuntan a la misma búsqueda. Dale a cada una su propia keyword o únelas.</p><table class="widefat striped"><tbody>';
			foreach ( $same as $row ) echo '<tr><td>«' . esc_html( $row['keyword'] ) . '»</td><td>' . implode( '<br>', array_map( array( __CLASS__, 'link' ), $row['ids'] ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tbody></table>';
		}
	}

	private static function render_orphans() {
		echo '<h3>Páginas huérfanas y de relleno</h3>';
		if ( ! class_exists( 'Digitalisimo_Integrations_Links' ) || ! Digitalisimo_Integrations_Links::enabled() ) { echo '<p>' . self::badge( 'NO APLICABLE', 'SIN ACTIVAR' ) . ' Activa «Analizar los enlaces internos» en SEO → Indexación para encontrarlas.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		$orphans = self::orphans();
		echo '<p>Publicadas, indexables y en el sitemap, sin ningún enlace desde otro contenido, desde los menús ni desde las plantillas del constructor (cabecera, pie). Google las descubre mal y suelen ser relleno. Enlázalas desde un contenido relacionado o, si sobran, elige qué hacer con ellas: <strong>noindex</strong> (siguen visibles, salen de Google y del sitemap), <strong>410</strong> (ya no existen) o <strong>301</strong> a otra página.</p>';
		if ( ! $orphans ) { echo '<p>' . self::badge( 'OK', 'SIN HUÉRFANAS' ) . ' Todas las páginas publicadas reciben al menos un enlace.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'digitalisimo_seo_cleanup_preview' );
		echo '<input type="hidden" name="action" value="digitalisimo_seo_cleanup_preview"><input type="hidden" name="source" value="orphans">';
		echo '<p><label>Acción <select name="kind"><option value="noindex">noindex (sacar de Google y del sitemap)</option><option value="410">410 (ya no existe)</option><option value="301">301 a otra página</option></select></label> <label>Destino del 301 <input type="text" name="target" placeholder="/servicios/" class="regular-text"></label> ';
		submit_button( 'Revisar lo seleccionado', 'secondary', 'submit', false );
		echo '</p><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" onclick="var c=this.checked;this.closest(\'table\').querySelectorAll(\'tbody input[type=checkbox]\').forEach(function(x){x.checked=c})" aria-label="Seleccionar todas"></td><th>Contenido</th><th>Tipo</th><th>Publicado</th></tr></thead><tbody>';
		foreach ( $orphans as $id ) echo '<tr><th class="check-column"><input type="checkbox" name="ids[]" value="' . (int) $id . '"></th><td>' . self::link( $id ) . '</td><td>' . esc_html( (string) ( get_post_type_object( get_post_type( $id ) )->labels->singular_name ?? get_post_type( $id ) ) ) . '</td><td>' . esc_html( (string) get_the_date( 'Y-m-d', $id ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</tbody></table></form><p class="description">Nunca aparecen la portada, la página del blog, la de privacidad ni las de WooCommerce. Nada se aplica sin la pantalla de confirmación.</p>';
	}

	private static function render_stale( array $r ) {
		$stale = (array) ( $r['stale'] ?? array() );
		echo '<h3>Descripciones para redes desactualizadas (' . count( $stale ) . ')</h3>';
		if ( ! $stale ) { echo '<p>' . self::badge( 'OK', 'AL DÍA' ) . ' Ninguna descripción escrita a mano parece haberse quedado atrás de su página.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p>La descripción que se ve al compartir la página (og:description) se escribió a mano y la página cambió. Revísala en el editor → SEO → Social (o la descripción SEO, si no hay una para redes).</p><table class="widefat striped"><thead><tr><th>Contenido</th><th>Descripción</th><th>Por qué</th></tr></thead><tbody>';
		foreach ( $stale as $id => $reasons ) {
			$desc = (string) ( get_post_meta( (int) $id, 'digitalisimo_seo_social_description', true ) ?: get_post_meta( (int) $id, 'digitalisimo_seo_description', true ) );
			echo '<tr><td>' . self::link( (int) $id ) . '</td><td>' . esc_html( $desc ) . '</td><td>' . esc_html( implode( '; ', array_map( function( $r ) { return $r[1]; }, (array) $reasons ) ) ) . '.</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	}
}
