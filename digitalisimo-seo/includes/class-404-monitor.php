<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registro de 404 del sitio en curso (RM3 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Una fila por URI con su contador, sin IP. Apagado por defecto. Una visita a un 404 nunca escribe en la base: añade
 * una línea a un búfer en disco con tope de tamaño (lo que llega con el búfer lleno se descarta, así un bot que barre
 * miles de URLs no satura nada). Cada hora el cron vuelca el búfer agregado por URI (una sola consulta por lote) y el
 * cron diario recorta por antigüedad y por número de filas. El registro propone un destino para cada URL.
 */
class Digitalisimo_Integrations_404_Monitor {
	const DB_VERSION = 1;
	const DB_OPTION  = 'digitalisimo_seo_404_db';
	const CRON       = 'digitalisimo_seo_404_cleanup';
	const FLUSH      = 'digitalisimo_seo_404_flush';
	/** Tamaño máximo del búfer entre dos volcados; lo que llega después se descarta. */
	const BUFFER_MAX = 262144;
	/** URIs distintas que entran en la tabla por volcado (las más visitadas). */
	const FLUSH_MAX  = 500;
	/** Estado del búfer: cuándo se llenó por última vez. */
	const STATE      = 'digitalisimo_seo_404_state';
	const PER_PAGE   = 50;

	public static function table() { global $wpdb; return $wpdb->prefix . 'digitalisimo_404_log'; }

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		add_action( self::FLUSH, array( __CLASS__, 'flush' ) );
		add_action( 'admin_post_digitalisimo_seo_404_action', array( __CLASS__, 'handle_action' ) );
		// Después de las redirecciones (wp, 11) y de las de WordPress (template_redirect, 10): sólo lo que de verdad no existe.
		if ( ! is_admin() ) add_action( 'template_redirect', array( __CLASS__, 'record' ), 99 );
	}

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	public static function maybe_install() {
		if ( (int) get_option( self::DB_OPTION ) < self::DB_VERSION ) self::install();
		$on = (bool) self::o( 'seo_404_enabled', 0 );
		$off_now = false;
		foreach ( array( self::CRON => 'daily', self::FLUSH => 'hourly' ) as $hook => $recurrence ) {
			if ( $on && ! wp_next_scheduled( $hook ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, $hook );
			elseif ( ! $on && wp_next_scheduled( $hook ) ) { wp_clear_scheduled_hook( $hook ); $off_now = true; }
		}
		if ( $on ) self::prepare_buffer();
		// Al apagarlo, lo que quedaba en el búfer se descarta.
		elseif ( $off_now && ( $file = self::buffer_file() ) && file_exists( $file ) ) @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/** Búfer de este sitio: un .php que no se puede leer desde la web (su primera línea termina la ejecución). */
	public static function buffer_file() {
		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['basedir'] ) ) return '';
		return trailingslashit( $uploads['basedir'] ) . 'digitalisimo-seo/404-' . substr( md5( wp_salt( 'auth' ) . '|' . get_current_blog_id() ), 0, 12 ) . '.php';
	}

	/** Carpeta del búfer, desde el escritorio: una visita nunca crea carpetas. */
	private static function prepare_buffer() {
		$file = self::buffer_file();
		if ( ! $file ) return;
		$dir = dirname( $file );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) return;
		if ( ! file_exists( $dir . '/index.php' ) ) @file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore
		if ( ! file_exists( $dir . '/.htaccess' ) ) @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
	}

	/** Línea del búfer para una visita (pura). */
	public static function buffer_line( $uri, $referer, $agent, $time ) {
		return wp_json_encode( array( 'u' => (string) $uri, 'r' => (string) $referer, 'a' => (string) $agent, 't' => (int) $time ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	}

	/**
	 * Agrega las líneas de un búfer por URI (pura): visitas, primera y última vez, y la última procedencia y el último
	 * navegador no vacíos. Devuelve las $max URIs más visitadas.
	 *
	 * @return array<string,array{uri:string,hits:int,first:int,last:int,referer:string,agent:string}>
	 */
	public static function aggregate( $text, $max = self::FLUSH_MAX ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\n/', (string) $text ) as $line ) {
			$row = json_decode( $line, true );
			if ( ! is_array( $row ) || ! isset( $row['u'] ) || '' === (string) $row['u'] ) continue;
			$uri  = substr( (string) $row['u'], 0, 255 );
			$key  = md5( $uri );
			$time = (int) ( $row['t'] ?? 0 );
			if ( ! isset( $out[ $key ] ) ) $out[ $key ] = array( 'uri' => $uri, 'hits' => 0, 'first' => $time, 'last' => $time, 'referer' => '', 'agent' => '' );
			$item =& $out[ $key ];
			$item['hits']++;
			$item['first'] = min( $item['first'], $time );
			$item['last']  = max( $item['last'], $time );
			if ( '' !== (string) ( $row['r'] ?? '' ) ) $item['referer'] = substr( (string) $row['r'], 0, 255 );
			if ( '' !== (string) ( $row['a'] ?? '' ) ) $item['agent'] = substr( (string) $row['a'], 0, 255 );
			unset( $item );
		}
		uasort( $out, function( $a, $b ) { return $b['hits'] - $a['hits'] ?: $b['last'] - $a['last']; } );
		return array_slice( $out, 0, max( 1, (int) $max ), true );
	}

	/**
	 * Vuelca el búfer a la tabla: lo aparta con rename() (atómico; las visitas siguientes empiezan otro), lo agrega por
	 * URI y escribe en lotes de 100 filas con INSERT … ON DUPLICATE KEY UPDATE.
	 */
	public static function flush() {
		if ( (int) get_option( self::DB_OPTION ) < self::DB_VERSION ) return 0;
		$file = self::buffer_file();
		if ( ! $file || ! file_exists( $file ) ) return 0;
		$work = $file . '.' . wp_generate_password( 6, false, false ) . '.work';
		if ( ! @rename( $file, $work ) ) return 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$size = (int) @filesize( $work ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$text = (string) @file_get_contents( $work ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@unlink( $work ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size >= self::BUFFER_MAX ) update_option( self::STATE, array( 'full' => time() ), false );
		$rows = self::aggregate( $text );
		if ( ! $rows ) return 0;
		global $wpdb;
		foreach ( array_chunk( $rows, 100, true ) as $chunk ) {
			$values = array();
			$args   = array();
			foreach ( $chunk as $key => $row ) {
				$values[] = '(%s, %s, %d, %s, %s, %s, %s)';
				array_push( $args, $key, $row['uri'], $row['hits'], gmdate( 'Y-m-d H:i:s', $row['first'] ), gmdate( 'Y-m-d H:i:s', $row['last'] ), $row['referer'], $row['agent'] );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table() . ' (uri_hash, uri, hits, first_seen, last_seen, referer, user_agent) VALUES ' . implode( ', ', $values ) . ' ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits), last_seen = GREATEST(last_seen, VALUES(last_seen)), referer = IF(VALUES(referer) = \'\', referer, VALUES(referer)), user_agent = IF(VALUES(user_agent) = \'\', user_agent, VALUES(user_agent))', $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return count( $rows );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL auto_increment,
			uri_hash char(32) NOT NULL,
			uri varchar(255) NOT NULL,
			hits bigint(20) unsigned NOT NULL default 1,
			first_seen datetime NOT NULL default '0000-00-00 00:00:00',
			last_seen datetime NOT NULL default '0000-00-00 00:00:00',
			referer varchar(255) NOT NULL default '',
			user_agent varchar(255) NOT NULL default '',
			PRIMARY KEY  (id),
			UNIQUE KEY uri_hash (uri_hash),
			KEY last_seen (last_seen)
		) " . $wpdb->get_charset_collate() . ';' );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/** Exclusiones: una por línea, coincidencia parcial sin distinguir mayúsculas. */
	public static function sanitize_exclusions( $value ) {
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', is_scalar( $value ) ? (string) $value : '' ) as $line ) { $line = trim( wp_strip_all_tags( $line ) ); if ( '' !== $line ) $lines[ $line ] = true; }
		return implode( "\n", array_slice( array_keys( $lines ), 0, 100 ) );
	}
	public static function sanitize_limit( $value ) { return min( 20000, max( 50, absint( $value ) ) ); }
	public static function sanitize_retention( $value ) { return min( 365, max( 1, absint( $value ) ) ); }

	/** URI que se registra, o '' si no se registra. Pura: la usan la petición y la prueba. */
	public static function loggable( $request_uri, $exclusions, $ignore_query ) {
		$uri = substr( preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $request_uri ), 0, 255 );
		if ( $ignore_query ) $uri = explode( '?', $uri )[0];
		if ( '' === $uri || false !== stripos( $uri, '/wp-admin' ) || false !== stripos( $uri, 'wp-login.php' ) ) return '';
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $exclusions ) as $line ) { $line = trim( $line ); if ( '' !== $line && false !== stripos( $uri, $line ) ) return ''; }
		return $uri;
	}

	public static function record() {
		if ( ! is_404() || ! self::o( 'seo_404_enabled', 0 ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) return;
		if ( (int) get_option( self::DB_OPTION ) < self::DB_VERSION ) return; // La tabla se crea desde el escritorio, nunca en una visita.
		$uri = self::loggable( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), self::o( 'seo_404_exclude', '' ), self::o( 'seo_404_ignore_query', 1 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $uri ) return;
		$file = self::buffer_file();
		// Sin carpeta (aún no se abrió el escritorio) o con el búfer lleno, la visita no se anota: nunca va a la base.
		if ( ! $file || ! is_dir( dirname( $file ) ) ) return;
		$size = file_exists( $file ) ? (int) filesize( $file ) : 0;
		if ( $size >= self::BUFFER_MAX ) return;
		$referer = substr( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ), 0, 255 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$agent   = substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$line    = self::buffer_line( $uri, $referer, $agent, time() );
		@file_put_contents( $file, ( 0 === $size ? "<?php exit; ?>\n" : '' ) . $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/** Último tramo de una ruta, como slug (pura). '' en la portada. */
	public static function last_slug( $uri ) {
		$path = trim( (string) explode( '?', (string) $uri )[0], '/' );
		if ( '' === $path ) return '';
		$parts = explode( '/', $path );
		$last  = rawurldecode( (string) end( $parts ) );
		$last  = preg_replace( '/\.(html?|php)$/i', '', $last );
		return sanitize_title( $last );
	}

	/**
	 * Destino propuesto para una URL que responde 404, o null: el contenido publicado con ese mismo slug en otra
	 * dirección (cambió el padre o la base), el que tuvo ese slug antes (_wp_old_slug del núcleo) y, si no, el más
	 * parecido según la búsqueda de WordPress con las palabras del slug. Sólo propone: nunca crea la regla sola.
	 *
	 * @return array{url:string,why:string}|null
	 */
	public static function suggest( $uri ) {
		$slug = self::last_slug( $uri );
		if ( '' === $slug ) return null;
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'elementor_library' ) ) );
		$found = get_posts( array( 'name' => $slug, 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'suppress_filters' => false ) );
		$why   = 'Mismo slug en otra dirección';
		if ( ! $found ) { $found = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'meta_key' => '_wp_old_slug', 'meta_value' => $slug ) ); $why = 'Su slug anterior'; } // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $found ) {
			$words = trim( str_replace( '-', ' ', $slug ) );
			if ( strlen( $words ) >= 3 ) { $found = get_posts( array( 's' => $words, 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'orderby' => 'relevance' ) ); $why = 'Parecido por sus palabras'; }
		}
		if ( ! $found ) return null;
		$url = get_permalink( $found[0] );
		$own = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $own || trim( (string) $own, '/' ) === trim( (string) explode( '?', (string) $uri )[0], '/' ) ) return null;
		return array( 'url' => (string) $own, 'why' => $why );
	}

	/** Borra lo más antiguo que la retención y deja como mucho el límite de filas (las más visitadas). */
	public static function cleanup() {
		if ( (int) get_option( self::DB_OPTION ) < self::DB_VERSION ) return;
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * self::sanitize_retention( self::o( 'seo_404_retention', 30 ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$limit = self::sanitize_limit( self::o( 'seo_404_limit', 1000 ) );
		$cut   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT 1 OFFSET %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $cut ) {
			$keep = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $keep ) $wpdb->query( "DELETE FROM {$table} WHERE id NOT IN (" . implode( ',', array_map( 'absint', $keep ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	public static function handle_action() {
		$task = sanitize_key( $_GET['task'] ?? '' );
		$id   = absint( $_GET['id'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_404_' . $task . '_' . $id ) ) wp_die( 'No autorizado.' );
		global $wpdb;
		if ( 'delete' === $task && $id ) $wpdb->delete( self::table(), array( 'id' => $id ) );
		elseif ( 'clear' === $task ) $wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-seo-monitor404&log_done=' . $task ) );
		exit;
	}

	private static function action_url( $task, $id = 0 ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'digitalisimo_seo_404_action', 'task' => $task, 'id' => $id ), admin_url( 'admin-post.php' ) ), 'digitalisimo_seo_404_' . $task . '_' . $id );
	}

	public static function render_log() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		self::maybe_install();
		self::flush(); // Lo que esperaba en el búfer se ve ya, sin esperar al cron.
		global $wpdb;
		$table = self::table();
		$state = get_option( self::STATE, array() );
		if ( is_array( $state ) && ! empty( $state['full'] ) && $state['full'] > time() - DAY_IN_SECONDS ) echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( 'El búfer de 404 se llenó (%s): llegaron más URLs inexistentes de las que se anotan entre dos volcados, normalmente un rastreador. Las que siguieron se descartaron sin tocar la base de datos.', get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $state['full'] ), 'Y-m-d H:i' ) ) ) . '</p></div>';
		if ( ! self::o( 'seo_404_enabled', 0 ) ) echo '<div class="notice notice-info inline"><p>El registro está apagado. Actívalo arriba para empezar a anotar las URLs que responden 404 en este sitio.</p></div>';
		$paged = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d", self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		echo '<h2>URLs no encontradas <span class="count">(' . esc_html( number_format_i18n( $total ) ) . ')</span></h2>';
		if ( ! $rows ) { echo '<p class="description">Todavía no hay 404 registrados.</p>'; return; }
		echo '<p><a class="button" href="' . esc_url( self::action_url( 'clear' ) ) . '" onclick="return confirm(\'¿Vaciar todo el registro de este sitio?\')">Vaciar registro</a></p>';
		echo '<table class="widefat striped"><thead><tr><th>URL</th><th>Visitas</th><th>Última vez</th><th>Procedencia</th><th>Destino sugerido</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$source  = explode( '?', $row['uri'] )[0];
			$suggest = self::suggest( $row['uri'] );
			$create  = admin_url( 'admin.php?page=digitalisimo-seo-redirections&edit=new&source=' . rawurlencode( $source ) . ( $suggest ? '&target=' . rawurlencode( $suggest['url'] ) : '' ) );
			echo '<tr><td><code>' . esc_html( $row['uri'] ) . '</code></td><td>' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . '</td><td>' . esc_html( get_date_from_gmt( $row['last_seen'], 'Y-m-d H:i' ) ) . '</td><td>' . ( $row['referer'] ? '<span class="description">' . esc_html( $row['referer'] ) . '</span>' : '—' ) . '</td><td>' . ( $suggest ? '<code>' . esc_html( $suggest['url'] ) . '</code><br><span class="description">' . esc_html( $suggest['why'] ) . '</span>' : '—' ) . '</td><td><a href="' . esc_url( $create ) . '">' . ( $suggest ? 'Crear 301 a la sugerencia' : 'Crear redirección' ) . '</a> · <a href="' . esc_url( self::action_url( 'delete', (int) $row['id'] ) ) . '">Borrar</a></td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) echo '<p class="tablenav-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
