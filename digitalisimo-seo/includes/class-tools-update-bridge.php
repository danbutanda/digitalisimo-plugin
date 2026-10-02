<?php
defined( 'ABSPATH' ) || exit;

/** Permite actualizar Tools 1.0.15, cuyo verificador anterior puede ocultar su propia actualización. */
final class Digitalisimo_Tools_Update_Bridge {
	const FILE = 'digitalisimo-tools/digitalisimo-tools.php';
	const CACHE = 'digitalisimo_tools_bridge_release';
	const ACTION = 'digitalisimo_tools_bridge_check';
	private static $installed_version = '';

	public static function init() {
		$path = WP_PLUGIN_DIR . '/' . self::FILE;
		if ( ! file_exists( $path ) ) return;
		$header = get_file_data( $path, array( 'Version' => 'Version' ) );
		self::$installed_version = (string) ( $header['Version'] ?? '' );
		if ( ! self::$installed_version || version_compare( self::$installed_version, '1.0.17', '>=' ) ) return;
		foreach ( array( 'pre_set_site_transient_update_plugins', 'pre_set_transient_update_plugins', 'site_transient_update_plugins', 'transient_update_plugins' ) as $hook ) add_filter( $hook, array( __CLASS__, 'inject' ), 100 );
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'uri_update' ), 100, 4 );
		add_filter( 'plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'force_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
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
		delete_site_transient( self::CACHE );
		delete_site_transient( 'digitalisimo_tools_release' );
		delete_site_transient( 'digitalisimo_releases_index' );
		delete_site_transient( 'update_plugins' );
		delete_transient( 'update_plugins' );
		wp_update_plugins();
		$context = sanitize_key( wp_unslash( $_GET['digitalisimo_context'] ?? '' ) );
		$default = is_multisite() && 'network' === $context ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'digitalisimo-tools-bridge-checked', '1', $back ? $back : $default ) );
		exit;
	}

	public static function notice() {
		if ( empty( $_GET['digitalisimo-tools-bridge-checked'] ) || ! self::can_update() ) return;
		$release = self::release();
		$pending = ! empty( $release['version'] ) && version_compare( $release['version'], self::$installed_version, '>' );
		$message = $pending ? 'DIGITALÍSIMO Tools ' . $release['version'] . ' está disponible para actualizar.' : 'No se encontró una versión nueva de DIGITALÍSIMO Tools.';
		echo '<div class="notice notice-' . ( $pending ? 'warning' : 'info' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private static function release() {
		$cached = get_site_transient( self::CACHE );
		if ( false !== $cached ) return (array) $cached;
		$response = wp_remote_get( 'https://api.github.com/repos/danbutanda/digitalisimo-plugin/releases?per_page=50', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-Tools-Bridge' ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { set_site_transient( self::CACHE, array(), MINUTE_IN_SECONDS ); return array(); }
		$best = array();
		foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! preg_match( '/^digitalisimo-tools-([0-9.]+)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) continue;
				if ( ! empty( $best['version'] ) && ! version_compare( $match[1], $best['version'], '>' ) ) continue;
				$best = array( 'version' => $match[1], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ), 'url' => esc_url_raw( $release['html_url'] ?? '' ) );
			}
		}
		set_site_transient( self::CACHE, $best, 5 * MINUTE_IN_SECONDS );
		return $best;
	}

	public static function uri_update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( self::FILE !== $plugin_file ) return $update;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], self::$installed_version, '>' ) ) return $update;
		return array( 'slug' => 'digitalisimo-tools', 'version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'], 'requires_php' => '7.4' );
	}

	public static function inject( $transient ) {
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], self::$installed_version, '>' ) ) return $transient;
		if ( ! is_object( $transient ) ) $transient = new stdClass();
		if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) $transient->checked = array();
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = array();
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) $transient->no_update = array();
		$transient->checked[ self::FILE ] = self::$installed_version;
		unset( $transient->no_update[ self::FILE ] );
		$transient->response[ self::FILE ] = (object) array( 'id' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-tools', 'slug' => 'digitalisimo-tools', 'plugin' => self::FILE, 'new_version' => $release['version'], 'url' => $release['url'], 'package' => $release['package'], 'tested' => get_bloginfo( 'version' ), 'requires' => '6.0', 'requires_php' => '7.4' );
		return $transient;
	}
}
