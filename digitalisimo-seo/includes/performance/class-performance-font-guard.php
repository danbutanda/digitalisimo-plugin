<?php
defined( 'ABSPATH' ) || exit;

/**
 * Blindaje de fuentes por sitio.
 *
 * En administración o cron genera copias del CSS local de Elementor que sólo
 * conservan las variantes autorizadas por la whitelist efectiva del sitio
 * (familia + estilo + peso). Los bloques conservados no se reescriben, así que
 * mantienen su font-display, unicode-range y URLs relativas a los WOFF/WOFF2.
 * El frontend sustituye una hoja por su copia sólo si la copia está vigente y
 * superó la validación; en cualquier otro caso sirve el original.
 *
 * No hay familias fijas en el código: cada sitio usa lo que detecta.
 *
 * Modos:
 * - off: no filtra.
 * - auto (seguro): detecta + permite + registra. Recorta las variantes que no
 *   usan las familias detectadas, pero conserva y registra las no detectadas,
 *   porque el Kit no tiene por qué contener todas las fuentes del sitio.
 * - strict: elimina cualquier @font-face fuera de lo detectado + excepciones.
 * - manual: sólo la lista escrita por el administrador.
 *
 * Los icon fonts siguen una ruta de exclusión aparte y nunca se filtran.
 */
class Digitalisimo_Integrations_Performance_Font_Guard {
	const OPTION   = 'digitalisimo_performance_font_guard_manifest';
	const APPROVAL = 'digitalisimo_performance_font_guard_approval';
	/** Versión de la semántica de filtrado: cambiarla invalida las copias anteriores. */
	const SCHEMA = 3;

	/** Icon fonts reconocidos sin configuración. Una coincidencia sólo protege: nunca filtra. */
	const ICON_PATTERN = '/(?:^|[\s_-])(?:eicons|font[\s_-]?awesome|line[\s_-]?awesome|dashicons|material[\s_-]?(?:icons|symbols)|icomoon|fontello|themify|ionicons|bootstrap[\s_-]?icons|remixicon|boxicons|[a-z0-9_-]*icons?)(?:$|[\s_-])/i';
	const ICON_EXACT   = array( 'star', 'woocommerce', 'feather', 'phosphor' );

	/** Las copias sin referencia se conservan este tiempo por si una caché de página aún las enlaza. */
	const STALE_COPY_TTL = 7 * 86400;

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_optimize_fonts', array( __CLASS__, 'optimize_action' ) );
		add_action( 'admin_post_digitalisimo_performance_recalculate_fonts', array( __CLASS__, 'recalculate_action' ) );
		add_action( 'admin_post_digitalisimo_performance_recalculate_network_fonts', array( __CLASS__, 'recalculate_network_action' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'invalidate_on_meta' ), 10, 4 );
		add_action( 'save_post_elementor_library', array( __CLASS__, 'invalidate_on_library_save' ) );
		add_action( 'init', array( __CLASS__, 'schedule_missing' ), 31 );
		add_filter( 'style_loader_src', array( __CLASS__, 'style_src' ), 20, 2 );
	}

	/* ------------------------------------------------------------------ *
	 * Ajustes y política efectiva
	 * ------------------------------------------------------------------ */

	public static function sanitize_mode( $value ) {
		return in_array( $value, array( 'off', 'auto', 'strict', 'manual' ), true ) ? $value : 'off';
	}

	/** «auto-prune» era el nombre interno del recorte con inventario completo: hoy es «strict». */
	private static function normalize_mode( $mode ) {
		return 'auto-prune' === $mode ? 'strict' : self::sanitize_mode( $mode );
	}

	public static function sanitize_allowlist( $value ) {
		$lines = array();
		foreach ( self::parse_allowlist( $value ) as $key => $rule ) {
			$lines[ $key ] = $rule['family'] . '|' . implode( ',', $rule['weights'] ) . '|' . implode( ',', $rule['styles'] );
		}
		return implode( "\n", array_values( $lines ) );
	}

	/** Una familia por línea; sólo nombres de fuente, nunca rutas ni URLs. */
	public static function sanitize_icon_families( $value ) {
		$families = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$family = trim( $line, " \t'\"" );
			if ( ! preg_match( '/^[\p{L}\p{N} ._-]{1,80}$/u', $family ) ) continue;
			$families[ strtolower( $family ) ] = $family;
			if ( count( $families ) >= 40 ) break;
		}
		return implode( "\n", array_values( $families ) );
	}

	/** Familia|pesos|estilos → reglas indexadas por familia en minúsculas. */
	private static function parse_allowlist( $value ) {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( 3 !== count( $parts ) || ! preg_match( '/^[\p{L}\p{N} ._-]{1,80}$/u', $parts[0] ) ) continue;
			$weights = array();
			foreach ( explode( ',', $parts[1] ) as $weight ) {
				$weight = trim( $weight );
				if ( preg_match( '/^[1-9]00$/', $weight ) ) $weights[ $weight ] = true;
			}
			$styles = array();
			foreach ( explode( ',', $parts[2] ) as $style ) {
				$style = strtolower( trim( $style ) );
				if ( in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) ) $styles[ $style ] = true;
			}
			if ( ! $weights || ! $styles ) continue;
			$rules[ strtolower( $parts[0] ) ] = array( 'family' => $parts[0], 'weights' => array_map( 'strval', array_keys( $weights ) ), 'styles' => array_keys( $styles ) );
			if ( count( $rules ) >= 40 ) break;
		}
		return $rules;
	}

	private static function setting( $key ) {
		return class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) ? Digitalisimo_Integrations_SEO_Resolver::option( $key ) : '';
	}

	/** @return array{0:string,1:string,2:string,3:string} modo, lista, huella e icon fonts. */
	private static function policy() {
		$mode      = self::sanitize_mode( self::setting( 'perf_font_guard_mode' ) );
		$allowlist = self::sanitize_allowlist( self::setting( 'perf_font_guard_allowlist' ) );
		$icons     = self::sanitize_icon_families( self::setting( 'perf_font_icon_families' ) );
		return array( $mode, $allowlist, self::fingerprint( $mode, $allowlist, $icons ), $icons );
	}

	public static function fingerprint( $mode, $allowlist, $icons = '' ) {
		return hash( 'sha256', 'v' . self::SCHEMA . "\n" . $mode . "\n" . $allowlist . "\n" . $icons );
	}

	/**
	 * Whitelist efectiva del sitio.
	 *
	 * En auto/strict parte de lo detectado (Kit + contenido) y le suma las
	 * excepciones manuales; en manual es sólo la lista. Una lista de pesos o
	 * estilos vacía significa «todos»: así se representa una variante que la
	 * detección no pudo precisar, y una excepción nunca la estrecha.
	 */
	private static function rules( $mode, $allowlist, $report ) {
		$mode   = self::normalize_mode( $mode );
		$manual = self::parse_allowlist( $allowlist );
		if ( 'manual' === $mode ) {
			foreach ( $manual as $key => $rule ) $manual[ $key ]['source'] = 'manual';
			return $manual;
		}
		if ( 'off' === $mode ) return array();

		$kit = array();
		foreach ( (array) ( $report['kit_families'] ?? array() ) as $row ) $kit[ strtolower( (string) ( $row['family'] ?? '' ) ) ] = true;

		$rules = array();
		foreach ( (array) ( $report['families'] ?? array() ) as $row ) {
			$name = trim( (string) ( $row['family'] ?? '' ) );
			$key  = strtolower( $name );
			if ( '' === $key ) continue;
			$weights = array();
			foreach ( (array) ( $row['weights'] ?? array() ) as $weight ) {
				$weight = self::normalize_weight( $weight );
				if ( null !== $weight ) $weights[ $weight ] = true;
			}
			$styles = array();
			foreach ( (array) ( $row['styles'] ?? array() ) as $style ) {
				$style = self::normalize_style( $style );
				if ( null !== $style ) $styles[ $style ] = true;
			}
			// Un valor que no se pudo normalizar no debe estrechar la regla.
			if ( count( $weights ) !== count( (array) ( $row['weights'] ?? array() ) ) ) $weights = array();
			if ( count( $styles ) !== count( (array) ( $row['styles'] ?? array() ) ) ) $styles = array();
			$rules[ $key ] = array( 'family' => $name, 'weights' => array_map( 'strval', array_keys( $weights ) ), 'styles' => array_keys( $styles ), 'source' => isset( $kit[ $key ] ) ? 'kit' : 'content' );
			$rules[ $key ]['detected'] = array( 'weights' => $rules[ $key ]['weights'], 'styles' => $rules[ $key ]['styles'] );
		}

		foreach ( $manual as $key => $exception ) {
			if ( ! isset( $rules[ $key ] ) ) {
				$rules[ $key ] = $exception + array( 'source' => 'exception', 'exception' => $exception );
				continue;
			}
			foreach ( array( 'weights', 'styles' ) as $field ) {
				if ( $rules[ $key ][ $field ] ) $rules[ $key ][ $field ] = array_values( array_unique( array_merge( $rules[ $key ][ $field ], $exception[ $field ] ) ) );
			}
			$rules[ $key ]['exception'] = $exception;
		}
		return $rules;
	}

	/* ------------------------------------------------------------------ *
	 * Clasificación de variantes
	 * ------------------------------------------------------------------ */

	/** CSS: «normal» es 400 y «bold» 700. Rangos variables («100 900») quedan ambiguos. */
	public static function normalize_weight( $weight ) {
		$weight = strtolower( trim( (string) $weight ) );
		if ( '' === $weight || 'normal' === $weight ) return '400';
		if ( 'bold' === $weight ) return '700';
		return preg_match( '/^[1-9]00$/', $weight ) ? $weight : null;
	}

	public static function normalize_style( $style ) {
		$style = strtolower( trim( (string) $style ) );
		if ( '' === $style ) return 'normal';
		return in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) ? $style : null;
	}

	private static function configured_icons() {
		return self::sanitize_icon_families( self::setting( 'perf_font_icon_families' ) );
	}

	/**
	 * Ruta de exclusión de icon fonts: patrón incorporado + lista configurable.
	 *
	 * @param string      $family Familia declarada en @font-face.
	 * @param string|null $icons  Lista saneada; null lee la configuración vigente.
	 */
	public static function icon_family( $family, $icons = null ) {
		$family = trim( (string) $family, " \t'\"" );
		if ( '' === $family ) return false;
		$key = strtolower( $family );
		if ( in_array( $key, self::ICON_EXACT, true ) || preg_match( self::ICON_PATTERN, $family ) ) return true;
		$icons = null === $icons ? self::configured_icons() : (string) $icons;
		return '' !== $icons && in_array( $key, array_map( 'strtolower', explode( "\n", $icons ) ), true );
	}

	/**
	 * Veredicto de una variante: icon, allow, undetected, ambiguous o block.
	 * Todo salvo «block» se conserva en el CSS.
	 */
	private static function verdict( $family, $weight, $style, $rules, $mode, $icons ) {
		if ( self::icon_family( $family, $icons ) ) return 'icon';
		$key = strtolower( trim( (string) $family, " \t'\"" ) );
		if ( ! isset( $rules[ $key ] ) ) return 'auto' === self::normalize_mode( $mode ) ? 'undetected' : 'block';
		$weight = self::normalize_weight( $weight );
		$style  = self::normalize_style( $style );
		if ( null === $weight || null === $style ) return 'ambiguous';
		$rule = $rules[ $key ];
		$ok   = ( empty( $rule['weights'] ) || in_array( $weight, $rule['weights'], true ) ) && ( empty( $rule['styles'] ) || in_array( $style, $rule['styles'], true ) );
		return $ok ? 'allow' : 'block';
	}

	/** Archivo WOFF/WOFF2 de un bloque CSS o de una fila del inventario. */
	private static function file_key( $face ) {
		$source = (string) ( $face['url'] ?? '' );
		if ( '' === $source && preg_match( '/url\(\s*[\'"]?([^\'")]+)/i', (string) ( $face['src'] ?? '' ), $m ) ) $source = $m[1];
		$source = strtok( $source, '?#' );
		return false === $source || '' === $source ? '' : strtolower( basename( $source ) );
	}

	/**
	 * Archivos que el navegador descargará de todos modos porque una variante
	 * permitida los usa. En una fuente variable todos los pesos de un estilo
	 * apuntan al mismo archivo: retirar sus otras reglas no ahorra bytes y sólo
	 * provoca negritas sintéticas, así que esas reglas se conservan.
	 */
	private static function kept_files( $faces, $rules, $mode, $icons ) {
		$files = array();
		foreach ( (array) $faces as $face ) {
			$file = self::file_key( $face );
			if ( '' !== $file && 'block' !== self::verdict( (string) ( $face['family'] ?? '' ), (string) ( $face['weight'] ?? '' ), (string) ( $face['style'] ?? '' ), $rules, $mode, $icons ) ) $files[ $file ] = true;
		}
		return $files;
	}

	/** Veredicto final: una variante bloqueada cuyo archivo ya se descarga pasa a «shared». */
	private static function decide( $face, $rules, $mode, $icons, $kept_files ) {
		$verdict = self::verdict( (string) ( $face['family'] ?? '' ), (string) ( $face['weight'] ?? '' ), (string) ( $face['style'] ?? '' ), $rules, $mode, $icons );
		return 'block' === $verdict && isset( $kept_files[ self::file_key( $face ) ] ) ? 'shared' : $verdict;
	}

	/** Bloques @font-face con sus descriptores; el texto original se conserva aparte. */
	public static function faces_in( $css ) {
		$faces = array();
		if ( ! preg_match_all( '/@font-face\s*\{([^{}]*)\}/i', (string) $css, $blocks ) ) return $faces;
		foreach ( $blocks[1] as $block ) {
			$read = function( $property ) use ( $block ) { return preg_match( '/' . $property . '\s*:\s*([^;]+)/i', $block, $m ) ? trim( $m[1] ) : ''; };
			$faces[] = array(
				'family' => trim( $read( 'font-family' ), " \t\n\r\0\x0B'\"" ),
				'weight' => $read( 'font-weight' ),
				'style'  => $read( 'font-style' ),
				'range'  => $read( 'unicode-range' ),
				'src'    => $read( 'src' ),
			);
		}
		return $faces;
	}

	private static function signature( $face ) {
		return strtolower( $face['family'] ) . '|' . strtolower( trim( $face['weight'] ) ) . '|' . strtolower( trim( $face['style'] ) ) . '|' . strtoupper( preg_replace( '/\s+/', '', $face['range'] ) ) . '|' . preg_replace( '/\s+/', '', $face['src'] );
	}

	private static function describe( $face ) {
		return trim( $face['family'] . ' ' . ( '' !== $face['style'] ? $face['style'] : 'normal' ) . ' ' . ( '' !== $face['weight'] ? $face['weight'] : '400' ) . ( '' !== $face['range'] ? ' [' . $face['range'] . ']' : '' ) );
	}

	/**
	 * Devuelve el CSS sin las variantes bloqueadas y sin bloques idénticos repetidos.
	 * Un bloque que no se puede interpretar con seguridad se conserva.
	 *
	 * @return array{0:string,1:int,2:array} CSS, reglas retiradas y familias no detectadas conservadas.
	 */
	public static function filter_css( $css, $rules, $mode, $icons = null ) {
		$mode       = self::normalize_mode( $mode );
		$icons      = null === $icons ? self::configured_icons() : (string) $icons;
		$removed    = 0;
		$undetected = array();
		$seen       = array();
		$kept_files = self::kept_files( self::faces_in( $css ), $rules, $mode, $icons );
		$filtered   = preg_replace_callback( '/@font-face\s*\{([^{}]*)\}/i', function( $match ) use ( $rules, $mode, $icons, $kept_files, &$removed, &$undetected, &$seen ) {
			$face = self::faces_in( $match[0] )[0] ?? null;
			if ( ! $face || '' === $face['family'] ) return $match[0];
			$verdict = self::decide( $face, $rules, $mode, $icons, $kept_files );
			if ( 'block' === $verdict ) { ++$removed; return ''; }
			// Dos declaraciones idénticas sólo duplican trabajo del navegador.
			$text = preg_replace( '/\s+/', '', $match[0] );
			if ( isset( $seen[ $text ] ) ) { ++$removed; return ''; }
			$seen[ $text ] = true;
			if ( 'undetected' === $verdict ) $undetected[ strtolower( $face['family'] ) ] = $face['family'];
			return $match[0];
		}, (string) $css );
		return array( is_string( $filtered ) ? $filtered : $css, $removed, array_values( $undetected ) );
	}

	/**
	 * Contrasta el CSS generado con la whitelist efectiva.
	 *
	 * Detecta variantes no autorizadas que sobrevivieron, variantes autorizadas
	 * que se perdieron y duplicados introducidos por la generación. Una copia con
	 * errores no se sirve: el frontend conserva el original.
	 */
	public static function validate_css( $original, $generated, $rules, $mode, $icons = null ) {
		$mode   = self::normalize_mode( $mode );
		$icons  = null === $icons ? self::configured_icons() : (string) $icons;
		$errors     = array();
		$kept       = array();
		$source     = array();
		$kept_files = self::kept_files( self::faces_in( $original ), $rules, $mode, $icons );
		foreach ( self::faces_in( $generated ) as $face ) {
			if ( '' === $face['family'] ) continue;
			$signature = self::signature( $face );
			$kept[ $signature ] = ( $kept[ $signature ] ?? 0 ) + 1;
			if ( 'block' === self::decide( $face, $rules, $mode, $icons, $kept_files ) ) $errors[] = 'Variante no autorizada en el CSS generado: ' . self::describe( $face );
		}
		foreach ( self::faces_in( $original ) as $face ) {
			if ( '' === $face['family'] ) continue;
			$signature = self::signature( $face );
			$source[ $signature ] = ( $source[ $signature ] ?? 0 ) + 1;
			if ( 'block' !== self::decide( $face, $rules, $mode, $icons, $kept_files ) && empty( $kept[ $signature ] ) ) $errors[] = 'Falta una variante autorizada: ' . self::describe( $face );
		}
		foreach ( $kept as $signature => $count ) {
			if ( $count > 1 && $count > ( $source[ $signature ] ?? 0 ) ) $errors[] = 'Regla @font-face duplicada: ' . $signature;
		}
		return array_values( array_unique( $errors ) );
	}

	/* ------------------------------------------------------------------ *
	 * Google Fonts remoto
	 * ------------------------------------------------------------------ */

	/** Whitelist compacta: la guarda el manifest para no leer el inventario en cada visita. */
	private static function compact_rules( $rules ) {
		$compact = array();
		foreach ( $rules as $key => $rule ) $compact[ $key ] = array( 'weights' => array_values( (array) ( $rule['weights'] ?? array() ) ), 'styles' => array_values( (array) ( $rule['styles'] ?? array() ) ) );
		return $compact;
	}

	/** Variante de la API v1: 400, 700italic, regular, italic, b, bi… null si no se reconoce. */
	private static function google_v1_variant( $token ) {
		$token = strtolower( trim( $token ) );
		$named = array( 'regular' => array( '400', 'normal' ), 'italic' => array( '400', 'italic' ), 'i' => array( '400', 'italic' ), 'bold' => array( '700', 'normal' ), 'b' => array( '700', 'normal' ), 'bolditalic' => array( '700', 'italic' ), 'bi' => array( '700', 'italic' ) );
		if ( isset( $named[ $token ] ) ) return $named[ $token ];
		return preg_match( '/^([1-9]00)(italic|i)?$/', $token, $m ) ? array( $m[1], empty( $m[2] ) ? 'normal' : 'italic' ) : null;
	}

	/**
	 * Reescribe una hoja de fonts.googleapis.com para pedir sólo las variantes
	 * autorizadas por la whitelist del sitio. Cubre la API v1 (la que usa
	 * Elementor cuando no carga las fuentes en local) y css2.
	 *
	 * Lo que no se puede interpretar con seguridad se deja tal cual: un token
	 * desconocido, un rango variable («100..900») o un eje distinto de ital/wght.
	 * Devuelve false si no queda ninguna familia: la hoja no se imprime.
	 */
	public static function google_fonts_url( $url, $rules, $mode, $icons ) {
		$parts = parse_url( (string) $url );
		if ( ! is_array( $parts ) || 'fonts.googleapis.com' !== strtolower( (string) ( $parts['host'] ?? '' ) ) ) return $url;
		$path = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
		if ( '/css' !== $path && '/css2' !== $path ) return $url;

		$families = array();
		$others   = array();
		foreach ( explode( '&', (string) ( $parts['query'] ?? '' ) ) as $pair ) {
			if ( '' === $pair ) continue;
			list( $key, $value ) = array_pad( explode( '=', $pair, 2 ), 2, '' );
			if ( 'family' === strtolower( $key ) ) $families[] = urldecode( $value );
			else $others[] = $pair;
		}
		if ( ! $families ) return $url;

		$kept = array();
		$keep = function( $name, $weight, $style ) use ( $rules, $mode, $icons ) {
			return 'block' !== self::verdict( $name, $weight, $style, $rules, $mode, $icons );
		};
		if ( '/css' === $path ) {
			foreach ( $families as $value ) {
				foreach ( explode( '|', $value ) as $spec ) {
					$segments = explode( ':', $spec, 3 );
					$name     = trim( $segments[0] );
					if ( '' === $name ) continue;
					if ( self::icon_family( $name, $icons ) || ! isset( $segments[1] ) || '' === trim( $segments[1] ) ) { $kept[] = $spec; continue; }
					$tokens = array();
					foreach ( explode( ',', $segments[1] ) as $token ) {
						$variant = self::google_v1_variant( $token );
						if ( null === $variant || $keep( $name, $variant[0], $variant[1] ) ) $tokens[] = trim( $token );
					}
					if ( $tokens ) $kept[] = $name . ':' . implode( ',', $tokens ) . ( isset( $segments[2] ) ? ':' . $segments[2] : '' );
				}
			}
			if ( ! $kept ) return false;
			$query = 'family=' . implode( '%7C', array_map( function( $spec ) { return str_replace( array( ' ', '|' ), array( '+', '%7C' ), $spec ); }, $kept ) );
		} else {
			foreach ( $families as $value ) {
				if ( ! preg_match( '/^([^:]+)(?::([a-z,]+)@(.+))?$/i', $value, $m ) ) { $kept[] = $value; continue; }
				$name = trim( $m[1] );
				if ( self::icon_family( $name, $icons ) || empty( $m[2] ) ) { $kept[] = $value; continue; }
				$axes = explode( ',', strtolower( $m[2] ) );
				if ( array_diff( $axes, array( 'ital', 'wght' ) ) ) { $kept[] = $value; continue; }
				$tuples = array();
				foreach ( explode( ';', $m[3] ) as $tuple ) {
					$values = explode( ',', $tuple );
					if ( count( $values ) !== count( $axes ) || false !== strpos( $tuple, '..' ) ) { $tuples[] = $tuple; continue; }
					$axis   = array_combine( $axes, $values );
					$style  = '1' === trim( $axis['ital'] ?? '0' ) ? 'italic' : 'normal';
					if ( $keep( $name, trim( $axis['wght'] ?? '400' ), $style ) ) $tuples[] = $tuple;
				}
				if ( $tuples ) $kept[] = $name . ':' . $m[2] . '@' . implode( ';', $tuples );
			}
			if ( ! $kept ) return false;
			$query = implode( '&', array_map( function( $spec ) { return 'family=' . str_replace( ' ', '+', $spec ); }, $kept ) );
		}
		if ( $others ) $query .= '&' . implode( '&', $others );
		return ( $parts['scheme'] ?? 'https' ) . '://fonts.googleapis.com' . $path . '?' . $query;
	}

	/** Vista previa para el debug: la URL que recibiría un visitante con la política vigente. */
	public static function preview_google_url( $url ) {
		list( $mode, , $fingerprint, $icons ) = self::policy();
		$manifest = self::manifest();
		if ( 'off' === $mode || $fingerprint !== ( $manifest['fingerprint'] ?? '' ) || ! isset( $manifest['whitelist'] ) ) return null;
		return self::google_fonts_url( $url, $manifest['whitelist'], $mode, $icons );
	}

	/* ------------------------------------------------------------------ *
	 * Estado: aprobación, manifest y caché
	 * ------------------------------------------------------------------ */

	/** Manifest persistente; la caché sólo evita lecturas repetidas. Un MISS lee la opción. */
	public static function manifest() {
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) {
			$found  = false;
			$cached = Digitalisimo_Integrations_Performance_Cache::get( 'font_guard', $found );
			if ( $found && is_array( $cached ) ) return $cached;
		}
		$manifest = get_option( self::OPTION, array() );
		$manifest = is_array( $manifest ) ? $manifest : array();
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::set( 'font_guard', $manifest, 3600 );
		return $manifest;
	}

	private static function save_manifest( $manifest ) {
		update_option( self::OPTION, $manifest, false );
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::set( 'font_guard', $manifest, 3600 );
	}

	/** Olvida las copias del sitio: el frontend vuelve al CSS original de inmediato. */
	public static function forget() {
		delete_option( self::OPTION );
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::delete( 'font_guard' );
	}

	/**
	 * La aprobación se guarda aparte del manifest: así un recálculo automático
	 * tras editar en Elementor no apaga una optimización que el administrador
	 * aprobó. Queda ligada al modo; si el modo cambia, hay que aprobar otra vez.
	 */
	private static function approve( $mode ) {
		update_option( self::APPROVAL, array( 'mode' => $mode, 'at' => time() ), true );
	}

	public static function approved() {
		list( $mode ) = self::policy();
		if ( 'off' === $mode ) return false;
		$approval = get_option( self::APPROVAL, array() );
		if ( is_array( $approval ) && ( $approval['mode'] ?? '' ) === $mode ) return true;
		// Copias aprobadas antes de existir la aprobación separada.
		$manifest = self::manifest();
		if ( ! $approval && ! empty( $manifest['approved'] ) && empty( $manifest['schema'] ) ) { self::approve( $mode ); return true; }
		return false;
	}

	/** Sin política aplicable, el blindaje no limita nada. */
	private static function inactive() {
		list( $mode ) = self::policy();
		return 'off' === $mode || ( self::setting( 'perf_safe_mode' ) && ! self::approved() );
	}

	private static function row_usable( $row ) {
		return is_array( $row ) && $row && false !== ( $row['valid'] ?? true )
			&& is_file( $row['original'] ?? '' ) && is_file( $row['generated'] ?? '' )
			&& filemtime( $row['original'] ) === ( $row['mtime'] ?? null );
	}

	/* ------------------------------------------------------------------ *
	 * Consultas para precargas y debug
	 * ------------------------------------------------------------------ */

	/** La precarga debe respetar la misma política que los @font-face filtrados. */
	public static function allows_face( $face, $report ) {
		if ( self::inactive() ) return true;
		list( $mode, $allowlist, , $icons ) = self::policy();
		$rules = self::rules( $mode, $allowlist, $report );
		return 'block' !== self::decide( $face, $rules, $mode, $icons, self::kept_files( (array) ( $report['faces'] ?? array() ), $rules, $mode, $icons ) );
	}

	/** La variante comparte archivo con otros pesos: es una fuente variable. */
	public static function is_variable( $face, $report ) {
		if ( false !== strpos( trim( (string) ( $face['weight'] ?? '' ) ), ' ' ) ) return true;
		$file = self::file_key( $face );
		if ( '' === $file ) return false;
		$weights = array();
		foreach ( (array) ( $report['faces'] ?? array() ) as $other ) {
			if ( self::file_key( $other ) === $file ) $weights[ strtolower( trim( (string) ( $other['weight'] ?? '' ) ) ) ] = true;
		}
		return count( $weights ) > 1;
	}

	public static function permitted_urls( $report ) {
		$inactive = self::inactive();
		list( $mode, $allowlist, , $icons ) = self::policy();
		$rules = $inactive ? array() : self::rules( $mode, $allowlist, $report );
		$urls  = array();
		foreach ( (array) ( $report['faces'] ?? array() ) as $face ) {
			if ( empty( $face['url'] ) ) continue;
			if ( $inactive || 'block' !== self::verdict( (string) ( $face['family'] ?? '' ), (string) ( $face['weight'] ?? '' ), (string) ( $face['style'] ?? '' ), $rules, $mode, $icons ) ) $urls[ $face['url'] ] = true;
		}
		return $urls;
	}

	/** Estado administrativo: una política sin copia vigente y válida no bloquea el CSS original. */
	public static function ready_for_face( $face, $report ) {
		if ( self::inactive() || empty( $face['css'] ) || empty( $report['uploads_baseurl'] ) ) return false;
		list( , , $fingerprint ) = self::policy();
		$manifest = self::manifest();
		if ( $fingerprint !== ( $manifest['fingerprint'] ?? '' ) ) return false;
		$key = $report['uploads_baseurl'] . 'elementor/google-fonts/css/' . basename( $face['css'] );
		return self::row_usable( $manifest['rows'][ $key ] ?? array() );
	}

	/** Por qué una familia está en la whitelist, para el debug. */
	public static function origin( $face, $report ) {
		list( $mode, $allowlist, , $icons ) = self::policy();
		$family = (string) ( $face['family'] ?? '' );
		if ( self::icon_family( $family, $icons ) ) return 'Icon font · excluido del filtrado';
		// Apagado también muestra de dónde vendría cada familia si se activara.
		$rules = self::rules( 'off' === $mode ? 'auto' : $mode, $allowlist, $report );
		$rule  = $rules[ strtolower( $family ) ] ?? null;
		if ( ! $rule ) return 'strict' === $mode || 'manual' === $mode ? 'No detectado' : 'No detectado · conservado por modo seguro';
		$labels = array( 'kit' => 'Kit Elementor', 'content' => 'Contenido Elementor', 'exception' => 'Excepción manual', 'manual' => 'Lista manual' );
		return $labels[ $rule['source'] ] ?? '';
	}

	/** La variante está permitida sólo por una excepción manual, no por la detección. */
	public static function is_exception( $face, $report ) {
		list( $mode, $allowlist ) = self::policy();
		if ( ! in_array( $mode, array( 'auto', 'strict' ), true ) ) return false;
		$rule = self::rules( $mode, $allowlist, $report )[ strtolower( (string) ( $face['family'] ?? '' ) ) ] ?? null;
		if ( ! $rule || empty( $rule['exception'] ) ) return false;
		$weight = self::normalize_weight( $face['weight'] ?? '' );
		$style  = self::normalize_style( $face['style'] ?? '' );
		if ( null === $weight || null === $style ) return false;
		$matches = function( $set ) use ( $weight, $style ) {
			return ( empty( $set['weights'] ) || in_array( $weight, $set['weights'], true ) ) && ( empty( $set['styles'] ) || in_array( $style, $set['styles'], true ) );
		};
		if ( ! $matches( $rule['exception'] ) ) return false;
		return 'exception' === $rule['source'] || ! $matches( $rule['detected'] ?? array() );
	}

	/** Variante tipográfica crítica declarada por el Kit (cuerpo, texto global, H1, principal). */
	public static function is_critical( $face, $report ) {
		$family = strtolower( (string) ( $face['family'] ?? '' ) );
		$weight = self::normalize_weight( $face['weight'] ?? '' );
		$style  = self::normalize_style( $face['style'] ?? '' );
		foreach ( (array) ( $report['critical'] ?? array() ) as $row ) {
			if ( strtolower( (string) ( $row['family'] ?? '' ) ) === $family && (string) ( $row['weight'] ?? '' ) === $weight && (string) ( $row['style'] ?? '' ) === $style ) return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ *
	 * Generación de copias
	 * ------------------------------------------------------------------ */

	/**
	 * Escribe junto al CSS de Elementor para preservar las URLs relativas.
	 * Sólo se ejecuta en administración o cron, nunca durante una visita.
	 *
	 * @param bool       $approved Registrar la aprobación del administrador.
	 * @param array|null $report   Inventario recién calculado; null lee el guardado.
	 */
	public static function build( $approved = false, $report = null ) {
		list( $mode, $allowlist, $fingerprint, $icons ) = self::policy();
		if ( 'off' === $mode ) { self::forget(); return array( 'built' => 0, 'removed' => 0 ); }
		if ( $approved ) self::approve( $mode );
		if ( null === $report ) $report = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
		$now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

		// Un fallo también se registra con la huella vigente: evita reintentos en bucle
		// y deja el sitio con su CSS original.
		$fail = function( $message ) use ( $fingerprint, $mode, $now ) {
			self::save_manifest( array( 'schema' => self::SCHEMA, 'fingerprint' => $fingerprint, 'mode' => $mode, 'rows' => array(), 'error' => $message, 'built_at' => $now ) );
			return array( 'error' => $message );
		};
		if ( empty( $report['scanned_at'] ) ) return $fail( 'Analiza primero las fuentes.' );
		$rules = self::rules( $mode, $allowlist, $report );
		if ( ! $rules ) return $fail( 'manual' === $mode ? 'Escribe al menos una familia válida en la lista manual.' : 'No se detectaron familias en el Kit ni en el contenido Elementor de este sitio.' );

		$manifest = array( 'schema' => self::SCHEMA, 'fingerprint' => $fingerprint, 'mode' => $mode, 'whitelist' => self::compact_rules( $rules ), 'rows' => array(), 'undetected' => array(), 'invalid' => 0, 'built_at' => $now );
		$uploads   = wp_upload_dir();
		$root      = realpath( $uploads['basedir'] ?? '' );
		$directory = realpath( trailingslashit( $uploads['basedir'] ?? '' ) . 'elementor/google-fonts/css' );
		// Sin CSS local, el sitio carga Google Fonts en remoto: basta la whitelist
		// para reescribir la URL de fonts.googleapis.com.
		if ( ! $root || ! $directory ) {
			$manifest['remote_only'] = true;
			self::save_manifest( $manifest );
			return array( 'built' => 0, 'removed' => 0, 'invalid' => 0, 'remote_only' => true, 'complete' => ! empty( $report['complete'] ), 'families' => count( (array) ( $report['families'] ?? array() ) ) );
		}
		if ( 0 !== strpos( $directory, trailingslashit( $root ) ) || ! is_writable( $directory ) ) {
			$manifest['error'] = 'No se puede escribir en el CSS local de este sitio.';
			self::save_manifest( $manifest );
			return array( 'error' => $manifest['error'] );
		}

		$removed  = 0;
		$files    = glob( $directory . '/*.css' );
		$files    = is_array( $files ) ? array_values( array_filter( $files, function( $path ) { return 0 !== strpos( basename( $path ), 'digitalisimo-' ); } ) ) : array();
		foreach ( array_slice( $files, 0, 50 ) as $path ) {
			$real = realpath( $path );
			if ( ! $real || 0 !== strpos( $real, trailingslashit( $directory ) ) || ! is_readable( $real ) || filesize( $real ) > 512 * KB_IN_BYTES ) continue;
			$css = file_get_contents( $real );
			if ( ! is_string( $css ) || false === stripos( $css, '@font-face' ) ) continue;
			list( $filtered, $count, $undetected ) = self::filter_css( $css, $rules, $mode, $icons );
			foreach ( $undetected as $family ) $manifest['undetected'][ strtolower( $family ) ] = $family;
			if ( ! $count ) continue;

			$source_url = trailingslashit( $uploads['baseurl'] ) . 'elementor/google-fonts/css/' . basename( $real );
			$errors     = self::validate_css( $css, $filtered, $rules, $mode, $icons );
			if ( $errors ) {
				// Se registra para el debug, pero no se escribe ni se sirve.
				$manifest['rows'][ $source_url ] = array( 'original' => $real, 'mtime' => filemtime( $real ), 'generated' => '', 'url' => '', 'removed' => $count, 'valid' => false, 'errors' => $errors );
				++$manifest['invalid'];
				continue;
			}
			$name   = 'digitalisimo-' . substr( hash( 'sha256', $fingerprint . $css ), 0, 16 ) . '-' . basename( $real );
			$target = $directory . '/' . $name;
			if ( false === file_put_contents( $target, $filtered, LOCK_EX ) ) continue;
			$manifest['rows'][ $source_url ] = array( 'original' => $real, 'mtime' => filemtime( $real ), 'generated' => $target, 'url' => trailingslashit( $uploads['baseurl'] ) . 'elementor/google-fonts/css/' . $name, 'removed' => $count, 'valid' => true, 'errors' => array() );
			$removed += $count;
		}
		$manifest['undetected'] = array_values( $manifest['undetected'] );
		$manifest['removed']    = $removed;
		$manifest['complete']   = ! empty( $report['complete'] );
		self::save_manifest( $manifest );
		self::prune_copies( $directory, $manifest );
		return array( 'built' => count( array_filter( $manifest['rows'], function( $row ) { return ! empty( $row['valid'] ); } ) ), 'removed' => $removed, 'invalid' => $manifest['invalid'], 'complete' => $manifest['complete'], 'families' => count( (array) ( $report['families'] ?? array() ) ) );
	}

	/** Borra copias propias sin referencia y antiguas; nunca toca archivos de Elementor. */
	private static function prune_copies( $directory, $manifest ) {
		$keep = array();
		foreach ( (array) $manifest['rows'] as $row ) if ( ! empty( $row['generated'] ) ) $keep[ $row['generated'] ] = true;
		$copies = glob( $directory . '/digitalisimo-*.css' );
		foreach ( is_array( $copies ) ? $copies : array() as $path ) {
			if ( isset( $keep[ $path ] ) || ! is_file( $path ) || time() - (int) filemtime( $path ) < self::STALE_COPY_TTL ) continue;
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- un archivo ya retirado no es un error.
		}
	}

	/**
	 * Recalcula todo para el sitio actual: inventario, copias y precargas, en ese
	 * orden, porque las precargas dependen de la política ya aplicada.
	 */
	public static function recalculate( $approve = false ) {
		$report   = Digitalisimo_Integrations_Performance_Fonts::scan();
		$guard    = self::build( $approve, $report );
		$preloads = class_exists( 'Digitalisimo_Integrations_Performance_Preloads' ) ? Digitalisimo_Integrations_Performance_Preloads::rebuild( $report ) : false;
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Font_Inline' ) ) Digitalisimo_Integrations_Performance_Font_Inline::rebuild();
		return array( 'report' => $report, 'guard' => $guard, 'preloads' => $preloads );
	}

	public static function status() {
		list( $mode, , $fingerprint ) = self::policy();
		$manifest = self::manifest();
		$valid    = 0;
		$removed  = 0;
		$allowed  = ! self::setting( 'perf_safe_mode' ) || self::approved();
		if ( 'off' !== $mode && $allowed && $fingerprint === ( $manifest['fingerprint'] ?? '' ) ) {
			foreach ( (array) ( $manifest['rows'] ?? array() ) as $row ) {
				if ( self::row_usable( $row ) ) { ++$valid; $removed += (int) ( $row['removed'] ?? 0 ); }
			}
		}
		return array(
			'mode'       => $mode,
			'copies'     => $valid,
			'removed'    => $removed,
			'complete'   => ! empty( $manifest['complete'] ),
			'invalid'    => (int) ( $manifest['invalid'] ?? 0 ),
			'undetected' => (array) ( $manifest['undetected'] ?? array() ),
			'error'      => (string) ( $manifest['error'] ?? '' ),
			'built_at'   => (string) ( $manifest['built_at'] ?? '' ),
			'stale'      => 'off' !== $mode && $fingerprint !== ( $manifest['fingerprint'] ?? '' ),
			'remote'     => ! empty( $manifest['remote_only'] ) && $fingerprint === ( $manifest['fingerprint'] ?? '' ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Frontend
	 * ------------------------------------------------------------------ */

	public static function style_src( $src, $handle ) {
		list( $mode, , $fingerprint ) = self::policy();
		if ( 'off' === $mode ) return $src;
		if ( ! Digitalisimo_Integrations_Performance_Manager::advanced_allowed() && ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! self::approved() ) ) return $src;
		$manifest = self::manifest();
		if ( $fingerprint !== ( $manifest['fingerprint'] ?? '' ) ) return $src;
		if ( false !== stripos( (string) $src, 'fonts.googleapis.com/css' ) ) {
			if ( ! isset( $manifest['whitelist'] ) ) return $src;
			list( , , , $icons ) = self::policy();
			return self::google_fonts_url( $src, $manifest['whitelist'], $mode, $icons );
		}
		$row = $manifest['rows'][ strtok( (string) $src, '?#' ) ] ?? array();
		return self::row_usable( $row ) ? $row['url'] : $src;
	}

	/* ------------------------------------------------------------------ *
	 * Regeneración
	 * ------------------------------------------------------------------ */

	/** Una edición de Elementor puede añadir familias: se vuelve al original y se recalcula. */
	public static function invalidate_on_meta( $meta_id, $post_id, $key, $value ) {
		if ( ! in_array( $key, array( '_elementor_data', '_elementor_page_settings', '_elementor_edit_mode' ), true ) ) return;
		self::forget();
		self::schedule();
	}

	public static function invalidate_on_library_save() {
		self::forget();
		self::schedule();
	}

	/** Comparte el evento de cron con las precargas: un solo recálculo por sitio. */
	private static function schedule() {
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Preloads' ) ) Digitalisimo_Integrations_Performance_Preloads::schedule();
	}

	/**
	 * Tras actualizar el plugin o cambiar la política no hay visita de
	 * administrador garantizada: sólo agenda, nunca genera durante la visita.
	 */
	public static function schedule_missing() {
		list( $mode, , $fingerprint ) = self::policy();
		if ( 'off' === $mode ) return;
		if ( self::setting( 'perf_safe_mode' ) && ! self::approved() ) return;
		if ( $fingerprint !== ( self::manifest()['fingerprint'] ?? '' ) ) self::schedule();
	}

	/* ------------------------------------------------------------------ *
	 * Acciones administrativas
	 * ------------------------------------------------------------------ */

	/** Valida el contexto, entra al sitio elegido y devuelve la URL de retorno. */
	private static function run_for_site( $nonce_prefix, $callback ) {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		check_admin_referer( $nonce_prefix . $site_id );
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try { $result = $callback(); }
		finally { if ( $switched ) restore_current_blog(); }
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=fonts&site_id=' . $site_id ) : admin_url( 'admin.php?page=digitalisimo-performance&section=fonts' );
		return array( $result, $target );
	}

	/** Un clic: inventaría el sitio elegido, activa sólo su modo seguro y prepara copias reversibles. */
	public static function optimize_action() {
		list( $result, $target ) = self::run_for_site( 'digitalisimo_performance_optimize_fonts_', function() {
			$report = Digitalisimo_Integrations_Performance_Fonts::scan();
			if ( empty( $report['families'] ) ) return array( 'error' => 'No se detectaron familias Elementor. No se modificó la política.' );
			self::forget();
			$settings = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
			$settings['perf_font_guard_mode'] = 'auto';
			update_option( Digitalisimo_Integrations_Settings::OPTION, $settings, false );
			if ( is_multisite() ) {
				$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
				$inherit['perf_font_guard_mode'] = 0;
				update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
			}
			$result = self::build( true, $report );
			if ( class_exists( 'Digitalisimo_Integrations_Performance_Preloads' ) ) Digitalisimo_Integrations_Performance_Preloads::rebuild( $report );
			if ( class_exists( 'Digitalisimo_Integrations_Performance_Font_Inline' ) ) Digitalisimo_Integrations_Performance_Font_Inline::rebuild();
			return $result;
		} );
		wp_safe_redirect( add_query_arg( isset( $result['error'] ) ? 'font_guard_error' : 'font_guard_optimized', isset( $result['error'] ) ? $result['error'] : $result['removed'], $target ) );
		exit;
	}

	/** «Recalcular fuentes»: respeta el modo elegido y lo aprueba para este sitio. */
	public static function recalculate_action() {
		list( $result, $target ) = self::run_for_site( 'digitalisimo_performance_recalculate_fonts_', function() {
			list( $mode ) = self::policy();
			return self::recalculate( 'off' !== $mode )['guard'];
		} );
		wp_safe_redirect( add_query_arg( isset( $result['error'] ) ? 'font_guard_error' : 'font_guard_recalculated', isset( $result['error'] ) ? $result['error'] : (int) ( $result['removed'] ?? 0 ), $target ) );
		exit;
	}

	/**
	 * Toda la red de una vez: cada sitio se recalcula con su propio modo efectivo
	 * y sus propias fuentes, en su propio cron. Los sitios con el blindaje
	 * apagado se omiten; nunca se copia la whitelist de un sitio a otro.
	 */
	public static function recalculate_network_action() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_performance_recalculate_network_fonts' );
		$scheduled = 0;
		$skipped   = 0;
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				list( $mode ) = self::policy();
				if ( 'off' === $mode ) { ++$skipped; continue; }
				// La acción del superadministrador es la aprobación explícita de cada sitio.
				self::approve( $mode );
				self::schedule();
				++$scheduled;
			} finally { restore_current_blog(); }
		}
		wp_safe_redirect( add_query_arg( array( 'font_network_scheduled' => $scheduled, 'font_network_skipped' => $skipped ), network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=fonts' ) ) );
		exit;
	}

	public static function render_network_recalculate() {
		if ( isset( $_GET['font_network_scheduled'] ) ) echo '<div class="notice notice-success"><p>Recálculo programado en ' . esc_html( absint( $_GET['font_network_scheduled'] ) ) . ' sitios, cada uno con sus propias fuentes. Sitios omitidos por tener el blindaje apagado: ' . esc_html( absint( $_GET['font_network_skipped'] ?? 0 ) ) . '. Cada sitio se procesa en su próximo cron.</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0 18px"><input type="hidden" name="action" value="digitalisimo_performance_recalculate_network_fonts">';
		wp_nonce_field( 'digitalisimo_performance_recalculate_network_fonts' );
		submit_button( 'Recalcular fuentes en todos los sitios', 'primary', 'submit', false );
		echo '<p class="description">Cada sitio detecta sus propias familias, estilos y pesos y genera su CSS y sus preloads con su modo efectivo (el suyo o el heredado de la red). Los sitios con el blindaje apagado se omiten.</p></form>';
	}

	public static function render_recalculate( $network, $site_id, $label = 'Recalcular fuentes', $type = 'secondary' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px"><input type="hidden" name="action" value="digitalisimo_performance_recalculate_fonts"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '">';
		wp_nonce_field( 'digitalisimo_performance_recalculate_fonts_' . $site_id );
		submit_button( $label, $type, 'submit', false );
		echo '</form>';
	}
}
