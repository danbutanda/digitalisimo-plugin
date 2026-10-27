<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registro de 404 del sitio en curso (RM3 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Una fila por URI con su contador (una sola consulta por 404), sin IP. Apagado por defecto;
 * el cron diario recorta por antigüedad y por número de filas.
 */
class Digitalisimo_Integrations_404_Monitor {
	const DB_VERSION = 1;
	const DB_OPTION  = 'digitalisimo_seo_404_db';
	const CRON       = 'digitalisimo_seo_404_cleanup';
	const PER_PAGE   = 50;

	public static function table() { global $wpdb; return $wpdb->prefix . 'digitalisimo_404_log'; }

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		add_action( 'admin_post_digitalisimo_seo_404_action', array( __CLASS__, 'handle_action' ) );
		// Después de las redirecciones (wp, 11) y de las de WordPress (template_redirect, 10): sólo lo que de verdad no existe.
		if ( ! is_admin() ) add_action( 'template_redirect', array( __CLASS__, 'record' ), 99 );
	}

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	public static function maybe_install() {
		if ( (int) get_option( self::DB_OPTION ) < self::DB_VERSION ) self::install();
		if ( self::o( 'seo_404_enabled', 0 ) && ! wp_next_scheduled( self::CRON ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		elseif ( ! self::o( 'seo_404_enabled', 0 ) && wp_next_scheduled( self::CRON ) ) wp_clear_scheduled_hook( self::CRON );
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
		global $wpdb;
		$now = current_time( 'mysql', true );
		$referer = substr( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ), 0, 255 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$agent   = substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table() . ' (uri_hash, uri, hits, first_seen, last_seen, referer, user_agent) VALUES (%s, %s, 1, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referer = IF(VALUES(referer) = \'\', referer, VALUES(referer)), user_agent = VALUES(user_agent)', md5( $uri ), $uri, $now, $now, $referer, $agent ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
		global $wpdb;
		$table = self::table();
		if ( ! self::o( 'seo_404_enabled', 0 ) ) echo '<div class="notice notice-info inline"><p>El registro está apagado. Actívalo arriba para empezar a anotar las URLs que responden 404 en este sitio.</p></div>';
		$paged = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d", self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		echo '<h2>URLs no encontradas <span class="count">(' . esc_html( number_format_i18n( $total ) ) . ')</span></h2>';
		if ( ! $rows ) { echo '<p class="description">Todavía no hay 404 registrados.</p>'; return; }
		echo '<p><a class="button" href="' . esc_url( self::action_url( 'clear' ) ) . '" onclick="return confirm(\'¿Vaciar todo el registro de este sitio?\')">Vaciar registro</a></p>';
		echo '<table class="widefat striped"><thead><tr><th>URL</th><th>Visitas</th><th>Última vez</th><th>Procedencia</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$create = admin_url( 'admin.php?page=digitalisimo-seo-redirections&edit=new&source=' . rawurlencode( explode( '?', $row['uri'] )[0] ) );
			echo '<tr><td><code>' . esc_html( $row['uri'] ) . '</code></td><td>' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . '</td><td>' . esc_html( get_date_from_gmt( $row['last_seen'], 'Y-m-d H:i' ) ) . '</td><td>' . ( $row['referer'] ? '<span class="description">' . esc_html( $row['referer'] ) . '</span>' : '—' ) . '</td><td><a href="' . esc_url( $create ) . '">Crear redirección</a> · <a href="' . esc_url( self::action_url( 'delete', (int) $row['id'] ) ) . '">Borrar</a></td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) echo '<p class="tablenav-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
