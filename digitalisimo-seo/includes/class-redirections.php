<?php
defined( 'ABSPATH' ) || exit;

/**
 * Redirecciones del sitio en curso (RM2 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Las reglas viven en una tabla por sitio. El front sólo mira el indicador con autoload
 * `digitalisimo_seo_redirect_on`: sin reglas activas no hace ninguna consulta, y con reglas lee
 * un índice precalculado (una opción sin autoload) en lugar de recorrer la tabla.
 *
 * Una regla puede llevar categoría y programación (desde/hasta, en GMT). El índice sólo contiene
 * las reglas vigentes y anota la próxima fecha en que eso cambia: un evento de WP-cron lo
 * reconstruye entonces y, si el cron se retrasa, lo hace la primera visita que lo note.
 */
class Digitalisimo_Integrations_Redirections {
	const DB_VERSION = 2;
	const DB_OPTION  = 'digitalisimo_seo_redirections_db';
	const FLAG       = 'digitalisimo_seo_redirect_on';
	const INDEX      = 'digitalisimo_seo_redirect_index';
	const CODES      = array( 301, 302, 307, 410, 451 );
	const PER_PAGE   = 50;
	const TICK       = 'digitalisimo_seo_redirect_tick';
	/** Columnas del CSV: las de Rank Math más la programación. */
	const CSV_COLUMNS = array( 'id', 'source', 'matching', 'destination', 'type', 'category', 'status', 'ignore', 'starts', 'ends' );
	const CSV_MAX_ROWS = 5000;

	/** @var array<int,string> Enlaces anteriores de los contenidos que se están guardando. */
	private static $before = array();

	public static function comparisons() { return array( 'exact' => 'Exacta', 'start' => 'Empieza por', 'end' => 'Termina en', 'contains' => 'Contiene', 'regex' => 'Expresión regular' ); }
	public static function code_labels() { return array( 301 => '301 · permanente', 302 => '302 · temporal', 307 => '307 · temporal (conserva el método)', 410 => '410 · contenido eliminado', 451 => '451 · no disponible por motivos legales' ); }

	public static function table() { global $wpdb; return $wpdb->prefix . 'digitalisimo_redirections'; }

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'admin_post_digitalisimo_seo_redirect_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_digitalisimo_seo_redirect_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_post_digitalisimo_seo_redirect_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_digitalisimo_seo_redirect_import', array( __CLASS__, 'handle_import' ) );
		add_action( self::TICK, array( __CLASS__, 'refresh_index' ) );
		add_action( 'pre_post_update', array( __CLASS__, 'remember_post' ), 10, 1 );
		add_action( 'post_updated', array( __CLASS__, 'watch_post' ), 10, 3 );
		add_action( 'edit_terms', array( __CLASS__, 'remember_term' ), 10, 2 );
		add_action( 'edited_term', array( __CLASS__, 'watch_term' ), 10, 3 );
		if ( ! is_admin() ) add_action( 'wp', array( __CLASS__, 'maybe_redirect' ), 11 );
	}

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	/** Crea o actualiza la tabla del sitio en curso. */
	public static function maybe_install() {
		if ( (int) get_option( self::DB_OPTION ) >= self::DB_VERSION ) return;
		self::install();
	}
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL auto_increment,
			sources longtext NOT NULL,
			url_to text NOT NULL,
			header_code smallint(4) unsigned NOT NULL default 301,
			hits bigint(20) unsigned NOT NULL default 0,
			status varchar(20) NOT NULL default 'active',
			origin varchar(20) NOT NULL default 'manual',
			import_key varchar(64) NOT NULL default '',
			category varchar(100) NOT NULL default '',
			starts datetime NULL default NULL,
			ends datetime NULL default NULL,
			created datetime NOT NULL default '0000-00-00 00:00:00',
			updated datetime NOT NULL default '0000-00-00 00:00:00',
			last_accessed datetime NULL default NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY import_key (import_key),
			KEY category (category)
		) $charset;" );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/* ------------------------------------------------------------------ */
	/* Reglas puras: normalización, validación y coincidencia (sin BD).     */
	/* ------------------------------------------------------------------ */

	/**
	 * Separa una URI en ruta relativa a la portada (sin barras de los extremos) y query string.
	 * Admite URLs completas: el dominio se descarta porque cada sitio sólo redirige sus rutas.
	 */
	public static function relative( $uri, $home_path = '/' ) {
		$uri   = trim( (string) $uri );
		$uri   = (string) preg_replace( '~^[a-z][a-z0-9+.-]*://[^/?#]*~i', '', $uri );
		$uri   = explode( '#', $uri )[0];
		$query = '';
		if ( false !== ( $q = strpos( $uri, '?' ) ) ) { $query = substr( $uri, $q + 1 ); $uri = substr( $uri, 0, $q ); }
		$path = '/' . ltrim( rawurldecode( $uri ), '/' );
		$base = '/' . trim( (string) $home_path, '/' );
		if ( '/' !== $base && ( $path === $base || 0 === strpos( $path, $base . '/' ) ) ) $path = substr( $path, strlen( $base ) );
		return array( trim( $path, '/' ), $query );
	}

	/** Patrón regex listo para preg_*: delimitador escapado y bandera de mayúsculas. */
	public static function regex( $pattern, $ignore_case = false ) {
		return '@' . str_replace( '@', '\\@', (string) $pattern ) . '@' . ( $ignore_case ? 'i' : '' );
	}

	public static function valid_regex( $pattern ) {
		if ( '' === (string) $pattern || strlen( $pattern ) > 500 ) return false;
		return false !== @preg_match( self::regex( $pattern ), '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Limpia un origen. Devuelve null si no es válido: una regla inválida nunca se activa.
	 *
	 * @return array{pattern:string,comparison:string,ignore:string}|null
	 */
	public static function sanitize_source( $source, $home_path = '/' ) {
		if ( ! is_array( $source ) ) return null;
		$comparison = (string) ( $source['comparison'] ?? 'exact' );
		if ( ! array_key_exists( $comparison, self::comparisons() ) ) return null;
		$pattern = trim( wp_strip_all_tags( (string) ( $source['pattern'] ?? '' ) ) );
		if ( 'regex' === $comparison ) {
			if ( ! self::valid_regex( $pattern ) ) return null;
		} else {
			list( $path, $query ) = self::relative( $pattern, $home_path );
			$pattern = '' !== $query ? $path . '?' . $query : $path;
		}
		if ( '' === $pattern ) return null; // La portada nunca es un origen: dejaría el sitio sin inicio.
		$ignore = in_array( $source['ignore'] ?? '', array( 'case', '1', 1, true ), true ) ? 'case' : '';
		return array( 'pattern' => $pattern, 'comparison' => $comparison, 'ignore' => $ignore );
	}

	/** Destino: ruta del sitio, URL http(s) o vacío para 410/451. Cualquier otro esquema se rechaza. */
	public static function sanitize_target( $target ) {
		$target = trim( (string) $target );
		if ( '' === $target ) return '';
		if ( preg_match( '#^https?://#i', $target ) ) return esc_url_raw( $target, array( 'http', 'https' ) );
		if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $target ) || 0 === strpos( $target, '//' ) ) return '';
		return '/' . ltrim( preg_replace( '/[\s<>"]/', '', $target ), '/' );
	}

	public static function compare( $source, $uri ) {
		$pattern = (string) $source['pattern'];
		$ci      = 'case' === ( $source['ignore'] ?? '' );
		switch ( $source['comparison'] ) {
			case 'regex':
				return 1 === @preg_match( self::regex( $pattern, $ci ), $uri ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			case 'exact':
				return $ci ? strtolower( trim( $pattern, '/' ) ) === strtolower( $uri ) : trim( $pattern, '/' ) === $uri;
		}
		$pattern = trim( $pattern, '/' );
		if ( '' === $pattern ) return false;
		$haystack = $ci ? strtolower( $uri ) : $uri;
		$needle   = $ci ? strtolower( $pattern ) : $pattern;
		if ( 'start' === $source['comparison'] ) return 0 === strpos( $haystack, $needle );
		if ( 'end' === $source['comparison'] ) return substr( $haystack, -strlen( $needle ) ) === $needle;
		return false !== strpos( $haystack, $needle );
	}

	/**
	 * Busca la primera regla del índice que coincide: primero los orígenes exactos (por clave), luego
	 * el resto en el orden del índice (las más recientes primero). Prueba la ruta sin query y, si no
	 * coincide, con la query, como hacía el origen de las reglas importadas.
	 *
	 * @return array{rule:array,source:array,uri:string}|null
	 */
	public static function find( array $index, $path, $query = '' ) {
		$candidates = array( $path );
		if ( '' !== $query ) $candidates[] = $path . '?' . $query;
		foreach ( $candidates as $uri ) {
			foreach ( array( 'exact' => $uri, 'exact_ci' => strtolower( $uri ) ) as $map => $key ) {
				if ( isset( $index[ $map ][ $key ], $index['rules'][ $index[ $map ][ $key ] ] ) ) {
					$rule = $index['rules'][ $index[ $map ][ $key ] ];
					foreach ( $rule['s'] as $source ) if ( 'exact' === $source['comparison'] && self::compare( $source, $uri ) ) return array( 'rule' => $rule, 'source' => $source, 'uri' => $uri );
				}
			}
			foreach ( $index['scan'] ?? array() as $id ) {
				$rule = $index['rules'][ $id ] ?? null;
				if ( ! $rule ) continue;
				foreach ( $rule['s'] as $source ) {
					if ( 'exact' !== $source['comparison'] && self::compare( $source, $uri ) ) return array( 'rule' => $rule, 'source' => $source, 'uri' => $uri );
				}
			}
		}
		return null;
	}

	/** Destino final de una coincidencia; con regex admite referencias $1…$9 al origen. */
	public static function destination( array $match ) {
		$to = (string) $match['rule']['u'];
		if ( 'regex' === $match['source']['comparison'] && preg_match( '/\$\d/', $to ) ) {
			$to = (string) @preg_replace( self::regex( $match['source']['pattern'], 'case' === $match['source']['ignore'] ), $to, $match['uri'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( '' !== $to && ! preg_match( '#^(https?:)?/#i', $to ) ) $to = '/' . $to;
		}
		return $to;
	}

	/**
	 * Índice compacto de las reglas activas y vigentes, de la más reciente a la más antigua. `next` es el
	 * próximo instante (timestamp) en que una regla programada entra o sale; 0 si ninguna cambiará.
	 */
	public static function build_index( array $rows, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		// Los orígenes exactos se encuentran por clave; sólo las reglas con patrones se recorren (`scan`).
		$index = array( 'exact' => array(), 'exact_ci' => array(), 'rules' => array(), 'scan' => array(), 'next' => 0 );
		foreach ( $rows as $row ) {
			$starts = self::timestamp( $row['starts'] ?? null );
			$ends   = self::timestamp( $row['ends'] ?? null );
			foreach ( array( $starts, $ends ) as $edge ) if ( $edge > $now && ( ! $index['next'] || $edge < $index['next'] ) ) $index['next'] = $edge;
			if ( ( $starts && $starts > $now ) || ( $ends && $ends <= $now ) ) continue;
			$sources = json_decode( (string) $row['sources'], true );
			if ( ! is_array( $sources ) || ! $sources ) continue;
			$id      = (int) $row['id'];
			$pattern = false;
			$index['rules'][ $id ] = array( 'i' => $id, 's' => $sources, 'u' => (string) $row['url_to'], 'c' => (int) $row['header_code'] );
			foreach ( $sources as $source ) {
				if ( 'exact' !== $source['comparison'] ) { $pattern = true; continue; }
				$key = trim( (string) $source['pattern'], '/' );
				if ( 'case' === $source['ignore'] ) { if ( ! isset( $index['exact_ci'][ strtolower( $key ) ] ) ) $index['exact_ci'][ strtolower( $key ) ] = $id; }
				elseif ( ! isset( $index['exact'][ $key ] ) ) $index['exact'][ $key ] = $id;
			}
			if ( $pattern ) $index['scan'][] = $id;
		}
		return $index;
	}

	/** Fecha GMT de la tabla ('Y-m-d H:i:s') a timestamp; 0 si no hay. */
	public static function timestamp( $value ) {
		$value = (string) $value;
		if ( '' === $value || 0 === strpos( $value, '0000' ) ) return 0;
		$time = strtotime( $value . ' UTC' );
		return false === $time ? 0 : $time;
	}

	/** Fecha libre (CSV, formulario ya en GMT) a 'Y-m-d H:i:s' GMT, o null. */
	public static function sanitize_datetime( $value ) {
		$value = trim( str_replace( 'T', ' ', (string) $value ) );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2})(?::(\d{2}))?)?$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) return null;
		$h = (int) ( $m[4] ?? 0 ); $i = (int) ( $m[5] ?? 0 ); $sec = (int) ( $m[6] ?? 0 );
		if ( $h > 23 || $i > 59 || $sec > 59 ) return null;
		return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $m[1], $m[2], $m[3], $h, $i, $sec );
	}

	public static function sanitize_category( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 100 ) : substr( $value, 0, 100 );
	}

	/* ------------------------------------------------------------------ */
	/* Front.                                                               */
	/* ------------------------------------------------------------------ */

	private static function skip_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) return true;
		if ( is_customize_preview() || is_preview() || isset( $_GET['elementor-preview'] ) || isset( $_GET['preview_id'] ) ) return true; // phpcs:ignore WordPress.Security.NonceVerification
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		if ( '' === $uri || false !== strpos( $uri, 'wp-login.php' ) || false !== strpos( $uri, '/wp-admin' ) ) return true;
		return false;
	}

	/** Ruta privada de acceso (SEO o Tools): nunca se redirige. */
	private static function private_login_path() {
		if ( class_exists( 'Digitalisimo\\Tools\\Hide_Login' ) && is_callable( array( 'Digitalisimo\\Tools\\Hide_Login', 'slug' ) ) ) return (string) call_user_func( array( 'Digitalisimo\\Tools\\Hide_Login', 'slug' ) );
		if ( class_exists( 'Digitalisimo_Integrations_Hide_Login' ) ) return (string) Digitalisimo_Integrations_Hide_Login::slug();
		return '';
	}

	public static function maybe_redirect() {
		if ( ! self::o( 'seo_redirect_enabled', 1 ) || self::skip_request() ) return;
		$active = self::active_count();
		$fallback = $active ? '' : (string) self::o( 'seo_redirect_fallback_url', '' );
		if ( ! $active && ( '' === $fallback || ! is_404() ) ) return;
		list( $path, $query ) = self::relative( (string) $_SERVER['REQUEST_URI'], (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$private = trim( self::private_login_path(), '/' );
		if ( '' !== $private && ( $path === $private || 0 === strpos( $path, $private . '/' ) ) ) return;
		$match = null;
		if ( $active ) {
			$index = get_option( self::INDEX, array() );
			// Una regla programada entró o salió y el cron aún no pasó: el índice se rehace ahora.
			if ( is_array( $index ) && ! empty( $index['next'] ) && time() >= (int) $index['next'] ) $index = self::refresh_index();
			$match = is_array( $index ) ? self::find( $index, $path, $query ) : null;
		}
		if ( ! $match ) {
			$fallback = $fallback ?: (string) self::o( 'seo_redirect_fallback_url', '' );
			if ( '' === $fallback || ! is_404() || '' === $path ) return;
			self::send( self::sanitize_target( $fallback ), self::fallback_code(), $path, '' );
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET hits = hits + 1, last_accessed = %s WHERE id = %d', current_time( 'mysql', true ), (int) $match['rule']['i'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$code = (int) $match['rule']['c'];
		if ( in_array( $code, array( 410, 451 ), true ) ) {
			global $wp_query;
			if ( $wp_query ) $wp_query->set_404();
			status_header( $code );
			nocache_headers();
			return;
		}
		self::send( self::destination( $match ), $code, $path, $query );
	}

	/**
	 * Reglas activas según el indicador con autoload. Se lee de las opciones ya cargadas: un get_option()
	 * de una opción que aún no existe consultaría la base de datos en cada visita de un sitio sin reglas.
	 */
	private static function active_count() {
		$all = wp_load_alloptions();
		return (int) ( $all[ self::FLAG ] ?? 0 );
	}

	private static function fallback_code() { $code = (int) self::o( 'seo_redirect_default_code', 301 ); return in_array( $code, array( 301, 302, 307 ), true ) ? $code : 301; }

	private static function send( $to, $code, $path, $query ) {
		if ( '' === (string) $to ) return;
		$absolute = preg_match( '#^https?://#i', $to ) ? $to : home_url( '/' . ltrim( $to, '/' ) );
		// Nunca hacia sí misma: un bucle dejaría la URL inaccesible.
		if ( wp_parse_url( $absolute, PHP_URL_HOST ) === wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
			list( $target_path ) = self::relative( $absolute, (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
			if ( $target_path === $path ) return;
		}
		if ( '' !== $query && false === strpos( $absolute, '?' ) ) $absolute .= '?' . $query;
		if ( wp_redirect( esc_url_raw( $absolute ), $code, 'DIGITALISIMO' ) ) exit;
	}

	/* ------------------------------------------------------------------ */
	/* Escritura.                                                           */
	/* ------------------------------------------------------------------ */

	/** Reconstruye el índice y el indicador del front tras cualquier cambio. */
	public static function refresh_index() {
		global $wpdb;
		self::maybe_install();
		$rows  = $wpdb->get_results( 'SELECT id, sources, url_to, header_code, starts, ends FROM ' . self::table() . " WHERE status = 'active' ORDER BY updated DESC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$index = self::build_index( (array) $rows );
		update_option( self::INDEX, $index, false );
		// Con sólo reglas futuras el front también debe leer el índice para notar cuándo empiezan.
		update_option( self::FLAG, count( $index['rules'] ) ?: ( $index['next'] ? 1 : 0 ), true );
		wp_clear_scheduled_hook( self::TICK );
		if ( $index['next'] ) wp_schedule_single_event( (int) $index['next'], self::TICK );
		return $index;
	}

	/**
	 * Guarda una regla. Devuelve el ID o un WP_Error con el motivo; la regla inválida no se escribe.
	 *
	 * @param array $data sources (lista de orígenes), url_to, header_code, status, origin, import_key, hits, created, updated, last_accessed.
	 */
	public static function save( array $data, $id = 0, $refresh = true ) {
		global $wpdb;
		self::maybe_install();
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$sources   = array();
		foreach ( (array) ( $data['sources'] ?? array() ) as $source ) { $clean = self::sanitize_source( $source, $home_path ); if ( $clean ) $sources[] = $clean; }
		if ( ! $sources ) return new WP_Error( 'source', 'El origen está vacío o no es válido.' );
		$code = (int) ( $data['header_code'] ?? 301 );
		if ( ! in_array( $code, self::CODES, true ) ) return new WP_Error( 'code', 'Código de respuesta no admitido.' );
		$to = in_array( $code, array( 410, 451 ), true ) ? '' : self::sanitize_target( $data['url_to'] ?? '' );
		if ( '' === $to && ! in_array( $code, array( 410, 451 ), true ) ) return new WP_Error( 'target', 'El destino debe ser una ruta del sitio o una URL http(s).' );
		if ( '' !== $to ) {
			list( $target_path ) = self::relative( $to, $home_path );
			$own = 0 === strpos( $to, '/' ) || wp_parse_url( $to, PHP_URL_HOST ) === wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			foreach ( $sources as $source ) if ( $own && 'exact' === $source['comparison'] && trim( $source['pattern'], '/' ) === $target_path ) return new WP_Error( 'loop', 'El destino es el mismo que el origen.' );
		}
		$starts = self::sanitize_datetime( $data['starts'] ?? '' );
		$ends   = self::sanitize_datetime( $data['ends'] ?? '' );
		if ( $starts && $ends && $ends <= $starts ) return new WP_Error( 'window', 'La fecha de fin debe ser posterior a la de inicio.' );
		$now  = current_time( 'mysql', true );
		$row  = array(
			'category'    => self::sanitize_category( $data['category'] ?? '' ),
			'starts'      => $starts,
			'ends'        => $ends,
			'sources'     => wp_json_encode( $sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'url_to'      => $to,
			'header_code' => $code,
			'status'      => 'inactive' === ( $data['status'] ?? 'active' ) ? 'inactive' : 'active',
			'updated'     => $data['updated'] ?? $now,
		);
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
		} else {
			$row += array(
				'origin'        => sanitize_key( $data['origin'] ?? 'manual' ),
				'import_key'    => substr( sanitize_text_field( $data['import_key'] ?? '' ), 0, 64 ),
				'hits'          => absint( $data['hits'] ?? 0 ),
				'created'       => $data['created'] ?? $now,
				'last_accessed' => $data['last_accessed'] ?? null,
			);
			$wpdb->insert( self::table(), $row );
			$id = (int) $wpdb->insert_id;
		}
		if ( $refresh ) self::refresh_index();
		return (int) $id;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row ) $row['sources'] = json_decode( (string) $row['sources'], true ) ?: array();
		return $row ?: null;
	}

	/** ¿Ya hay una regla con este origen exacto? Evita crear dos veces la misma redirección automática. */
	private static function exact_source_exists( $path ) {
		$index = get_option( self::INDEX, array() );
		return is_array( $index ) && ( isset( $index['exact'][ $path ] ) || isset( $index['exact_ci'][ strtolower( $path ) ] ) );
	}

	/* ------------------------------------------------------------------ */
	/* Vigilante de enlaces permanentes.                                   */
	/* ------------------------------------------------------------------ */

	public static function remember_post( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'publish' === $post->post_status && is_post_type_viewable( $post->post_type ) ) self::$before[ 'p' . $post_id ] = get_permalink( $post );
	}

	public static function watch_post( $post_id, $after, $before ) {
		$old = self::$before[ 'p' . $post_id ] ?? '';
		unset( self::$before[ 'p' . $post_id ] );
		if ( ! $old || ! $after || 'publish' !== $after->post_status || 'publish' !== $before->post_status || wp_is_post_revision( $post_id ) ) return;
		self::watch( $old, get_permalink( $after ) );
	}

	public static function remember_term( $term_id, $taxonomy ) {
		$link = get_term_link( (int) $term_id, $taxonomy );
		if ( ! is_wp_error( $link ) ) self::$before[ 't' . $term_id ] = $link;
	}

	public static function watch_term( $term_id, $tt_id, $taxonomy ) {
		$old = self::$before[ 't' . $term_id ] ?? '';
		unset( self::$before[ 't' . $term_id ] );
		$new = get_term_link( (int) $term_id, $taxonomy );
		if ( $old && ! is_wp_error( $new ) ) self::watch( $old, $new );
	}

	/** Un cambio de enlace crea una 301 del anterior al nuevo, salvo que ya haya una regla para ese origen. */
	private static function watch( $old, $new ) {
		if ( ! self::o( 'seo_redirect_enabled', 1 ) || ! self::o( 'seo_redirect_slug_watch', 1 ) ) return;
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		list( $from ) = self::relative( $old, $home_path );
		list( $to )   = self::relative( $new, $home_path );
		if ( '' === $from || $from === $to ) return;
		self::maybe_install();
		// La ruta nueva vuelve a estar viva: una regla que la redirigía crearía un bucle con la nueva.
		$index = (array) get_option( self::INDEX, array() );
		foreach ( array( $index['exact'][ $to ] ?? 0, $index['exact_ci'][ strtolower( $to ) ] ?? 0 ) as $stale ) if ( $stale ) self::set_status( (int) $stale, 'inactive', false );
		if ( (int) get_option( self::FLAG, 0 ) && self::exact_source_exists( $from ) ) { self::refresh_index(); return; }
		$slash = '' !== $to && '/' === substr( (string) $new, -1 ) ? '/' : '';
		self::save( array( 'sources' => array( array( 'pattern' => $from, 'comparison' => 'exact' ) ), 'url_to' => '/' . $to . $slash, 'header_code' => 301, 'origin' => 'slug' ) );
	}

	private static function set_status( $id, $status, $refresh = true ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => $status, 'updated' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
		if ( $refresh ) self::refresh_index();
	}

	/* ------------------------------------------------------------------ */
	/* Administración.                                                      */
	/* ------------------------------------------------------------------ */

	private static function page_url( $args = array() ) { return add_query_arg( $args, admin_url( 'admin.php?page=digitalisimo-seo-redirections' ) ); }

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_redirect_save' ) ) wp_die( 'No autorizado.' );
		$id         = absint( $_POST['redirect_id'] ?? 0 );
		$comparison = sanitize_key( wp_unslash( $_POST['comparison'] ?? 'exact' ) );
		$ignore     = empty( $_POST['ignore_case'] ) ? '' : 'case';
		$sources    = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['sources'] ?? '' ) ) as $line ) if ( '' !== trim( $line ) ) $sources[] = array( 'pattern' => trim( $line ), 'comparison' => $comparison, 'ignore' => $ignore );
		// Las fechas del formulario están en la zona horaria del sitio; la tabla guarda GMT.
		$when = array();
		foreach ( array( 'starts', 'ends' ) as $key ) { $local = self::sanitize_datetime( wp_unslash( $_POST[ $key ] ?? '' ) ); $when[ $key ] = $local ? get_gmt_from_date( $local ) : ''; }
		$result = self::save( array( 'sources' => $sources, 'url_to' => wp_unslash( $_POST['url_to'] ?? '' ), 'header_code' => absint( $_POST['header_code'] ?? 301 ), 'status' => sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ), 'category' => wp_unslash( $_POST['category'] ?? '' ), 'starts' => $when['starts'], 'ends' => $when['ends'] ), $id );
		if ( is_wp_error( $result ) ) { wp_safe_redirect( self::page_url( array( 'redirect_error' => $result->get_error_code(), 'edit' => $id ?: 'new' ) ) ); exit; }
		wp_safe_redirect( self::page_url( array( 'redirect_saved' => 1 ) ) );
		exit;
	}

	public static function handle_action() {
		$id   = absint( $_GET['id'] ?? 0 );
		$task = sanitize_key( $_GET['task'] ?? '' );
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_redirect_' . $task . '_' . $id ) ) wp_die( 'No autorizado.' );
		global $wpdb;
		if ( 'delete' === $task ) { $wpdb->delete( self::table(), array( 'id' => $id ) ); self::refresh_index(); }
		elseif ( in_array( $task, array( 'activate', 'deactivate' ), true ) ) self::set_status( $id, 'activate' === $task ? 'active' : 'inactive' );
		wp_safe_redirect( self::page_url( array( 'redirect_done' => $task ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* CSV.                                                                 */
	/* ------------------------------------------------------------------ */

	/** Filas CSV de una regla: una por origen, todas con su ID para volver a unirlas al importar. */
	public static function csv_rows( array $rule ) {
		$rows = array();
		foreach ( (array) $rule['sources'] as $source ) {
			$cells = array( $rule['id'], 'regex' === $source['comparison'] ? $source['pattern'] : '/' . $source['pattern'], $source['comparison'], $rule['url_to'], $rule['header_code'], $rule['category'] ?? '', $rule['status'], $source['ignore'], (string) ( $rule['starts'] ?? '' ), (string) ( $rule['ends'] ?? '' ) );
			$rows[] = array_map( array( 'Digitalisimo_Integrations_SEO_Bulk', 'csv_cell' ), $cells );
		}
		return $rows;
	}

	/**
	 * Reglas a partir de las filas de un CSV (cabecera => celda). Las filas seguidas con el mismo id
	 * no vacío son orígenes de una misma regla. Una fila sin origen se descarta.
	 *
	 * @return array<int,array> Datos para save().
	 */
	public static function csv_rules( array $rows ) {
		$rules = array();
		$last  = null;
		$v     = function( $row, $key ) { return trim( Digitalisimo_Integrations_SEO_Bulk::csv_value( (string) ( $row[ $key ] ?? '' ) ) ); };
		foreach ( $rows as $row ) {
			$pattern = $v( $row, 'source' );
			if ( '' === $pattern ) { $last = null; continue; }
			$matching = strtolower( $v( $row, 'matching' ) ) ?: 'exact';
			$source   = array( 'pattern' => $pattern, 'comparison' => $matching, 'ignore' => 'case' === strtolower( $v( $row, 'ignore' ) ) ? 'case' : '' );
			$id       = $v( $row, 'id' );
			if ( null !== $last && '' !== $id && $id === $rules[ $last ]['_id'] ) { $rules[ $last ]['sources'][] = $source; continue; }
			$category = $v( $row, 'category' );
			$rules[]  = array(
				'_id'         => $id,
				'sources'     => array( $source ),
				'url_to'      => $v( $row, 'destination' ),
				'header_code' => (int) ( $v( $row, 'type' ) ?: 301 ),
				'status'      => 'inactive' === strtolower( $v( $row, 'status' ) ) ? 'inactive' : 'active',
				// Rank Math separa varias categorías con comas: aquí cada regla tiene una.
				'category'    => trim( explode( ',', $category )[0] ),
				'starts'      => $v( $row, 'starts' ),
				'ends'        => $v( $row, 'ends' ),
				'origin'      => 'csv',
			);
			$last = count( $rules ) - 1;
		}
		return array_map( function( $rule ) { unset( $rule['_id'] ); return $rule; }, $rules );
	}

	public static function write_csv( $out ) {
		global $wpdb;
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM: las hojas de cálculo leen bien las tildes.
		fputcsv( $out, self::CSV_COLUMNS );
		for ( $after = 0; ; ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE id > %d AND status IN ('active','inactive') ORDER BY id ASC LIMIT 500", $after ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $rows ) break;
			foreach ( $rows as $row ) {
				$after = (int) $row['id'];
				$row['sources'] = json_decode( (string) $row['sources'], true ) ?: array();
				foreach ( self::csv_rows( $row ) as $cells ) fputcsv( $out, $cells );
			}
		}
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_redirect_export' ) ) wp_die( 'No autorizado.' );
		self::maybe_install();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="redirecciones-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		self::write_csv( $out );
		fclose( $out );
		exit;
	}

	/**
	 * Importa reglas de un CSV ya abierto. Nunca duplica una regla: la que sólo trae orígenes que ya
	 * existen (activos o no, con la misma comparación) se omite.
	 *
	 * @return array{added:int,skipped:int,invalid:int}
	 */
	public static function import_csv( $handle ) {
		global $wpdb;
		self::maybe_install();
		$header = fgetcsv( $handle );
		$header = is_array( $header ) ? array_map( function( $h ) { return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ); }, $header ) : array();
		$result = array( 'added' => 0, 'skipped' => 0, 'invalid' => 0 );
		if ( ! in_array( 'source', $header, true ) ) { $result['invalid'] = 1; return $result; }
		$rows = array();
		while ( count( $rows ) < self::CSV_MAX_ROWS && false !== ( $cells = fgetcsv( $handle ) ) ) {
			if ( array( null ) === $cells ) continue;
			$rows[] = array_combine( $header, array_slice( array_pad( $cells, count( $header ), '' ), 0, count( $header ) ) );
		}
		$known = array();
		foreach ( (array) $wpdb->get_col( 'SELECT sources FROM ' . self::table() . " WHERE status IN ('active','inactive')" ) as $json ) foreach ( (array) json_decode( (string) $json, true ) as $source ) if ( is_array( $source ) && isset( $source['pattern'], $source['comparison'] ) ) $known[ self::signature( $source ) ] = true; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		foreach ( self::csv_rules( $rows ) as $rule ) {
			$keys = array();
			foreach ( $rule['sources'] as $source ) { $clean = self::sanitize_source( $source, $home_path ); if ( $clean ) $keys[] = self::signature( $clean ); }
			if ( $keys && count( $keys ) === count( $rule['sources'] ) && ! array_diff( $keys, array_keys( $known ) ) ) { $result['skipped']++; continue; }
			$saved = self::save( $rule, 0, false );
			if ( is_wp_error( $saved ) ) { $result['invalid']++; continue; }
			$result['added']++;
			foreach ( $keys as $key ) $known[ $key ] = true;
		}
		self::refresh_index();
		return $result;
	}

	/** Identidad de un origen para no duplicarlo: las rutas sin distinguir mayúsculas, las regex tal cual. */
	public static function signature( array $source ) {
		$pattern = (string) $source['pattern'];
		return $source['comparison'] . ':' . ( 'regex' === $source['comparison'] ? $pattern : strtolower( trim( $pattern, '/' ) ) );
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_redirect_import' ) ) wp_die( 'No autorizado.' );
		$file = $_FILES['digitalisimo_redirect_csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $file || UPLOAD_ERR_OK !== ( $file['error'] ?? 1 ) || (int) $file['size'] > 2 * MB_IN_BYTES || ! preg_match( '/\.csv$/i', (string) $file['name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { wp_safe_redirect( self::page_url( array( 'redirect_csv' => 'invalid' ) ) ); exit; }
		$handle = fopen( $file['tmp_name'], 'r' );
		$result = $handle ? self::import_csv( $handle ) : array( 'added' => 0, 'skipped' => 0, 'invalid' => 1 );
		if ( $handle ) fclose( $handle );
		wp_safe_redirect( self::page_url( array( 'redirect_csv' => 'done', 'added' => $result['added'], 'skipped' => $result['skipped'], 'invalid' => $result['invalid'] ) ) );
		exit;
	}

	private static function action_url( $id, $task ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'digitalisimo_seo_redirect_action', 'id' => $id, 'task' => $task ), admin_url( 'admin-post.php' ) ), 'digitalisimo_seo_redirect_' . $task . '_' . $id );
	}

	/** Categorías en uso, para sugerirlas y filtrar. */
	public static function categories() {
		global $wpdb;
		return array_values( array_filter( array_map( 'strval', (array) $wpdb->get_col( 'SELECT DISTINCT category FROM ' . self::table() . " WHERE category <> '' AND status IN ('active','inactive') ORDER BY category ASC LIMIT 200" ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function describe_sources( array $sources ) {
		$labels = self::comparisons();
		return implode( '<br>', array_map( function( $s ) use ( $labels ) { return '<code>' . esc_html( 'regex' === $s['comparison'] ? $s['pattern'] : '/' . $s['pattern'] ) . '</code> <span class="description">' . esc_html( $labels[ $s['comparison'] ] ?? '' ) . ( 'case' === $s['ignore'] ? ' · sin distinguir mayúsculas' : '' ) . '</span>'; }, $sources ) );
	}

	/** Gestor bajo los ajustes de la pestaña Redirecciones (fuera de su formulario). */
	public static function render_manager() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		self::maybe_install();
		global $wpdb;
		$errors = array( 'source' => 'El origen está vacío o no es válido (revisa la expresión regular).', 'code' => 'Código de respuesta no admitido.', 'target' => 'El destino debe ser una ruta del sitio o una URL http(s).', 'loop' => 'El destino es el mismo que el origen.', 'window' => 'La fecha de fin debe ser posterior a la de inicio.' );
		$error  = sanitize_key( $_GET['redirect_error'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $errors[ $error ] ) ) echo '<div class="notice notice-error inline"><p>' . esc_html( $errors[ $error ] ) . '</p></div>';
		$csv = sanitize_key( $_GET['redirect_csv'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'invalid' === $csv ) echo '<div class="notice notice-error inline"><p>El archivo debe ser un CSV de hasta 2 MB.</p></div>';
		if ( 'done' === $csv ) echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( 'CSV importado: %1$d añadidas, %2$d ya existían, %3$d no válidas.', absint( $_GET['added'] ?? 0 ), absint( $_GET['skipped'] ?? 0 ), absint( $_GET['invalid'] ?? 0 ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['redirect_saved'] ) ) echo '<div class="notice notice-success inline"><p>Redirección guardada.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		$edit = sanitize_key( $_GET['edit'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$rule = ctype_digit( $edit ) ? self::get( (int) $edit ) : null;
		$prefill = sanitize_text_field( wp_unslash( $_GET['source'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<h2>' . ( $rule ? 'Editar redirección' : 'Nueva redirección' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-redirect-form"><input type="hidden" name="action" value="digitalisimo_seo_redirect_save"><input type="hidden" name="redirect_id" value="' . esc_attr( $rule['id'] ?? 0 ) . '">';
		wp_nonce_field( 'digitalisimo_seo_redirect_save' );
		$first   = $rule['sources'][0] ?? array( 'comparison' => 'exact', 'ignore' => '' );
		$sources = $rule ? implode( "\n", array_map( function( $s ) { return 'regex' === $s['comparison'] ? $s['pattern'] : '/' . $s['pattern']; }, $rule['sources'] ) ) : ( $prefill ? '/' . ltrim( $prefill, '/' ) : '' );
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="digitalisimo_redirect_sources">Origen</label></th><td><textarea class="large-text" rows="3" id="digitalisimo_redirect_sources" name="sources" required placeholder="/pagina-anterior/">' . esc_textarea( $sources ) . '</textarea><p class="description">Una ruta por línea, relativa a este sitio. Puedes pegar la URL completa: el dominio se descarta.</p></td></tr>';
		echo '<tr><th scope="row"><label for="digitalisimo_redirect_comparison">Coincidencia</label></th><td><select id="digitalisimo_redirect_comparison" name="comparison">';
		foreach ( self::comparisons() as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $first['comparison'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select> <label><input type="checkbox" name="ignore_case" value="1" ' . checked( 'case', $first['ignore'], false ) . '> Sin distinguir mayúsculas</label><p class="description">Con expresión regular, ancla el patrón con ^ y $ y usa $1, $2… en el destino para reutilizar lo capturado.</p></td></tr>';
		echo '<tr><th scope="row"><label for="digitalisimo_redirect_to">Destino</label></th><td><input class="regular-text" id="digitalisimo_redirect_to" name="url_to" value="' . esc_attr( $rule['url_to'] ?? '' ) . '" placeholder="/pagina-nueva/"><p class="description">Ruta de este sitio o URL completa. Vacío sólo con 410 o 451.</p></td></tr>';
		echo '<tr><th scope="row"><label for="digitalisimo_redirect_code">Respuesta</label></th><td><select id="digitalisimo_redirect_code" name="header_code">';
		foreach ( self::code_labels() as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( (int) ( $rule['header_code'] ?? self::o( 'seo_redirect_default_code', 301 ) ), $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select> <select name="status" aria-label="Estado"><option value="active" ' . selected( $rule['status'] ?? 'active', 'active', false ) . '>Activa</option><option value="inactive" ' . selected( $rule['status'] ?? 'active', 'inactive', false ) . '>Inactiva</option></select></td></tr>';
		$categories = self::categories();
		echo '<tr><th scope="row"><label for="digitalisimo_redirect_category">Categoría</label></th><td><input class="regular-text" id="digitalisimo_redirect_category" name="category" maxlength="100" list="digitalisimo_redirect_categories" value="' . esc_attr( $rule['category'] ?? '' ) . '"><datalist id="digitalisimo_redirect_categories">';
		foreach ( $categories as $category ) echo '<option value="' . esc_attr( $category ) . '">';
		echo '</datalist><p class="description">Opcional, para ordenar y filtrar las reglas.</p></td></tr>';
		$local = function( $gmt ) { return $gmt ? get_date_from_gmt( $gmt, 'Y-m-d\\TH:i' ) : ''; };
		echo '<tr><th scope="row">Programación</th><td><label>Desde <input type="datetime-local" name="starts" value="' . esc_attr( $local( $rule['starts'] ?? '' ) ) . '"></label> <label>Hasta <input type="datetime-local" name="ends" value="' . esc_attr( $local( $rule['ends'] ?? '' ) ) . '"></label><p class="description">Opcional, en la hora del sitio. Fuera de ese periodo la regla no se aplica aunque esté activa.</p></td></tr></table>';
		submit_button( $rule ? 'Guardar redirección' : 'Añadir redirección', 'primary', 'submit', false );
		if ( $rule ) echo ' <a class="button" href="' . esc_url( self::page_url() ) . '">Cancelar</a>';
		echo '</form>';

		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$where  = "status IN ('active','inactive')";
		$args   = array();
		if ( '' !== $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where .= ' AND ( sources LIKE %s OR url_to LIKE %s )'; $args = array( $like, $like ); }
		$filter = self::sanitize_category( wp_unslash( $_GET['category'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $filter ) { $where .= ' AND category = %s'; $args[] = $filter; }
		$count_sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . $where;
		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$list_sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . $where . ' ORDER BY updated DESC, id DESC LIMIT %d OFFSET %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $args, array( self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<h2>Redirecciones del sitio <span class="count">(' . esc_html( number_format_i18n( $total ) ) . ')</span></h2>';
		echo '<form method="get" class="search-box"><input type="hidden" name="page" value="digitalisimo-seo-redirections"><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Buscar origen o destino"> ';
		if ( $categories ) { echo '<select name="category" aria-label="Categoría"><option value="">Todas las categorías</option>'; foreach ( $categories as $category ) echo '<option value="' . esc_attr( $category ) . '" ' . selected( $filter, $category, false ) . '>' . esc_html( $category ) . '</option>'; echo '</select> '; }
		echo '<button class="button">Buscar</button></form>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_seo_redirect_export' ), 'digitalisimo_seo_redirect_export' ) ) . '">Exportar CSV</a></p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_redirect_import">';
		wp_nonce_field( 'digitalisimo_seo_redirect_import' );
		echo '<label>Importar CSV <input type="file" name="digitalisimo_redirect_csv" accept=".csv,text/csv" required></label> <button class="button">Importar</button><p class="description">Columnas: id, source, matching, destination, type, category, status, ignore, starts, ends (fechas en GMT). Acepta el CSV exportado por Rank Math. Un origen exacto que ya existe no se duplica.</p></form>';
		if ( ! $rows ) { echo '<p class="description">' . ( '' !== $search ? 'Ninguna redirección coincide con la búsqueda.' : 'Aún no hay redirecciones en este sitio.' ) . '</p>'; return; }
		$origins = array( 'manual' => 'Manual', 'slug' => 'Cambio de enlace', 'rankmath' => 'Importada', '404' => 'Desde 404', 'csv' => 'Desde CSV' );
		echo '<table class="widefat striped"><thead><tr><th>Origen</th><th>Destino</th><th>Respuesta</th><th>Estado</th><th>Visitas</th><th>Último acceso</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$sources = json_decode( (string) $row['sources'], true ) ?: array();
			$active  = 'active' === $row['status'];
			$label   = $active ? 'Activa' : 'Inactiva';
			$starts  = self::timestamp( $row['starts'] ); $ends = self::timestamp( $row['ends'] );
			if ( $active && $starts > time() ) $label = 'Programada';
			elseif ( $active && $ends && $ends <= time() ) $label = 'Vencida';
			$when = ( $starts ? 'desde ' . get_date_from_gmt( $row['starts'], 'Y-m-d H:i' ) . ' ' : '' ) . ( $ends ? 'hasta ' . get_date_from_gmt( $row['ends'], 'Y-m-d H:i' ) : '' );
			echo '<tr><td>' . self::describe_sources( $sources ) . '<br><span class="description">' . esc_html( $origins[ $row['origin'] ] ?? $row['origin'] ) . ( '' !== $row['category'] ? ' · ' . esc_html( $row['category'] ) : '' ) . '</span></td><td>' . ( '' !== $row['url_to'] ? '<code>' . esc_html( $row['url_to'] ) . '</code>' : '—' ) . '</td><td>' . esc_html( $row['header_code'] ) . '</td><td><span class="digitalisimo-effective-state ' . ( 'Activa' === $label ? 'is-active' : 'is-inactive' ) . '">' . esc_html( $label ) . '</span>' . ( '' !== $when ? '<br><span class="description">' . esc_html( trim( $when ) ) . '</span>' : '' ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . '</td><td>' . esc_html( $row['last_accessed'] ? get_date_from_gmt( $row['last_accessed'], 'Y-m-d H:i' ) : '—' ) . '</td><td>';
			echo '<a href="' . esc_url( self::page_url( array( 'edit' => $row['id'] ) ) ) . '">Editar</a> · <a href="' . esc_url( self::action_url( (int) $row['id'], $active ? 'deactivate' : 'activate' ) ) . '">' . ( $active ? 'Desactivar' : 'Activar' ) . '</a> · <a href="' . esc_url( self::action_url( (int) $row['id'], 'delete' ) ) . '" onclick="return confirm(\'¿Borrar esta redirección?\')">Borrar</a></td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) echo '<p class="tablenav-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function sanitize_code( $value ) { $value = (int) $value; return in_array( $value, array( 301, 302, 307 ), true ) ? $value : 301; }
}
