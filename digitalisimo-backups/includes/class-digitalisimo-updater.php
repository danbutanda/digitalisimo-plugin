<?php
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Backups_Updater {
	const CACHE_KEY = 'digitalisimo_backups_release';
	const ACTION = 'digitalisimo_backups_check_updates';
	const FILE = 'digitalisimo-backups/digitalisimo-backups.php';

	public static function register() {
		foreach ( array( 'pre_set_site_transient_update_plugins', 'pre_set_transient_update_plugins', 'site_transient_update_plugins', 'transient_update_plugins' ) as $hook ) add_filter( $hook, array( __CLASS__, 'check' ), 30 );
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'uri_update' ), 30, 4 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'force_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_after_upgrade' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_inline_update' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedule_interval' ) );
		add_action( 'digitalisimo_backups_refresh_updates', array( __CLASS__, 'refresh' ) );
		if ( ( ! is_multisite() || is_main_site() ) && ! wp_next_scheduled( 'digitalisimo_backups_refresh_updates' ) ) wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'digitalisimo_backups_five_minutes', 'digitalisimo_backups_refresh_updates' );
	}

	public static function schedule_interval( $schedules ) {
		$schedules['digitalisimo_backups_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Cada cinco minutos · Digitalisimo Backups' );
		return $schedules;
	}

	/** Usa la actualización AJAX de WordPress dentro de la lista de Plugins. */
	public static function enqueue_inline_update( $hook ) {
		if ( 'plugins.php' !== $hook || ! self::can_update() ) return;
		wp_enqueue_script( 'digitalisimo-inline-plugin-update', plugins_url( 'assets/js/update-in-place.js', WP_PLUGIN_DIR . '/' . self::FILE ), array( 'updates' ), DIGITALISIMO_BACKUPS_VERSION, true );
	}

	private static function can_update() { return is_multisite() ? current_user_can( 'manage_network_plugins' ) : current_user_can( 'update_plugins' ); }

	public static function action_link( $links ) {
		if ( ! self::can_update() ) return $links;
		$url = wp_nonce_url( add_query_arg( array( 'action' => self::ACTION, 'digitalisimo_context' => is_network_admin() ? 'network' : 'site' ), admin_url( 'admin-post.php' ) ), self::ACTION );
		$links[] = '<a href="' . esc_url( $url ) . '">Buscar actualizaciones</a>';
		return $links;
	}

	public static function force_check() {
		if ( ! self::can_update() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION );
		self::refresh();
		$context = sanitize_key( wp_unslash( $_GET['digitalisimo_context'] ?? '' ) );
		$default = is_multisite() && 'network' === $context ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'digitalisimo-backups-checked', '1', $back ? $back : $default ) );
		exit;
	}

	public static function notice() {
		if ( empty( $_GET['digitalisimo-backups-checked'] ) || ! self::can_update() ) return;
		$release = self::release();
		$pending = ! empty( $release['version'] ) && version_compare( $release['version'], DIGITALISIMO_BACKUPS_VERSION, '>' );
		$message = $pending ? 'Digitalisimo Backups ' . $release['version'] . ' está disponible para actualizar.' : 'Digitalisimo Backups está al día.';
		echo '<div class="notice notice-' . ( $pending ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	public static function clear() {
		delete_site_transient( self::CACHE_KEY );
	}

	public static function refresh() {
		self::clear();
		$updates = get_site_transient( 'update_plugins' );
		if ( is_object( $updates ) && isset( $updates->checked ) && is_array( $updates->checked ) ) {
			set_site_transient( 'update_plugins', self::check( $updates ) );
		} else {
			wp_update_plugins();
		}
	}

	public static function clear_after_upgrade( $upgrader, $options ) {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) && in_array( self::FILE, (array) ( $options['plugins'] ?? array() ), true ) ) self::clear();
	}

	private static function release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) return (array) $cached;
		$response = wp_remote_get( 'https://api.github.com/repos/danbutanda/digitalisimo-plugin/releases?per_page=100', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-Backups' ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, array(), MINUTE_IN_SECONDS );
			return array();
		}
		$best = array();
		foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! preg_match( '/^digitalisimo-backups-([0-9.]+)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) continue;
				if ( ! empty( $best['version'] ) && ! version_compare( $match[1], $best['version'], '>' ) ) continue;
				if ( empty( $asset['browser_download_url'] ) ) continue;
				$best = array( 'version' => $match[1], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ), 'url' => esc_url_raw( $release['html_url'] ?? '' ) );
			}
		}
		set_site_transient( self::CACHE_KEY, $best, 5 * MINUTE_IN_SECONDS );
		return $best;
	}

	public static function uri_update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( self::FILE !== $plugin_file ) return $update;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], DIGITALISIMO_BACKUPS_VERSION, '>' ) ) return false;
		return array( 'slug' => 'digitalisimo-backups', 'version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'], 'requires_php' => '7.4' );
	}

	public static function auto_update( $update, $item ) { return isset( $item->plugin ) && self::FILE === $item->plugin ? true : $update; }

	public static function check( $transient ) {
		// Una caché ausente o aún incompleta debe quedar en manos de WordPress.
		if ( ! is_object( $transient ) || ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) return $transient;
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = array();
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) $transient->no_update = array();
		$transient->checked[ self::FILE ] = DIGITALISIMO_BACKUPS_VERSION;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) ) return $transient;
		$pending = version_compare( $release['version'], DIGITALISIMO_BACKUPS_VERSION, '>' );
		$item = (object) array( 'id' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-backups', 'slug' => 'digitalisimo-backups', 'plugin' => self::FILE, 'new_version' => $pending ? $release['version'] : DIGITALISIMO_BACKUPS_VERSION, 'url' => $release['url'], 'package' => $pending ? $release['package'] : '', 'tested' => get_bloginfo( 'version' ), 'requires' => '6.0', 'requires_php' => '7.4' );
		if ( $pending ) { unset( $transient->no_update[ self::FILE ] ); $transient->response[ self::FILE ] = $item; }
		else { unset( $transient->response[ self::FILE ] ); $transient->no_update[ self::FILE ] = $item; }
		return $transient;
	}
}
