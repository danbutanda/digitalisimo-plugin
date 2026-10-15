<?php
defined( 'ABSPATH' ) || exit;

/** Permite actualizar Tools 1.0.15, cuyo verificador anterior puede ocultar su propia actualización. */
final class Digitalisimo_Tools_Update_Bridge {
	const FILE = 'digitalisimo-tools/digitalisimo-tools.php';
	const CACHE = 'digitalisimo_tools_bridge_release';
	const ACTION = 'digitalisimo_tools_bridge_check';
	const SLUG = 'digitalisimo-tools';
	const REPOSITORY = 'danbutanda/digitalisimo-plugin';
	/**
	 * Índice de versiones adjunto a cada Release (el ZIP vigente de los siete plugins). Se descarga como un archivo
	 * de la última Release, no por la API de GitHub, que limita a 60 consultas por hora a cada servidor sin
	 * autenticar y que agotan entre todos los plugins Digitalisimo.
	 */
	const MANIFEST_URL = 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json';
	/** Caché compartida del índice: un solo archivo sirve a todos los plugins que lo lean. */
	const MANIFEST_CACHE = 'digitalisimo_plugins_manifest';
	/** Última versión encontrada: si GitHub no responde, la actualización sigue a la vista en lugar de desaparecer. */
	const LAST_KNOWN = 'digitalisimo_tools_bridge_last_release';
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
		delete_site_transient( self::MANIFEST_CACHE );
		delete_site_transient( 'digitalisimo_tools_release' );
		delete_site_transient( 'digitalisimo_releases_index' );
		$updates = get_site_transient( 'update_plugins' );
		if ( is_object( $updates ) && isset( $updates->checked ) && is_array( $updates->checked ) ) set_site_transient( 'update_plugins', self::inject( $updates ) );
		else wp_update_plugins();
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
		$best = self::from_manifest();
		if ( ! $best ) $best = self::from_api();
		if ( $best ) {
			update_site_option( self::LAST_KNOWN, $best );
			set_site_transient( self::CACHE, $best, 10 * MINUTE_IN_SECONDS );
			return $best;
		}
		// GitHub no respondió o limitó las consultas: se conserva lo último encontrado y se reintenta pronto.
		$known = get_site_option( self::LAST_KNOWN, array() );
		$known = is_array( $known ) ? $known : array();
		set_site_transient( self::CACHE, $known, 2 * MINUTE_IN_SECONDS );
		return $known;
	}

	/** Versión de Tools en el índice de la última Release; sólo acepta un ZIP suyo de este repositorio. */
	private static function from_manifest() {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) {
			$response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 10, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Tools-Bridge' ) ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array();
			$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $manifest ) || empty( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) return array();
			set_site_transient( self::MANIFEST_CACHE, $manifest, 10 * MINUTE_IN_SECONDS );
		}
		$entry   = $manifest['plugins'][ self::SLUG ] ?? null;
		$version = is_array( $entry ) ? (string) ( $entry['version'] ?? '' ) : '';
		$package = is_array( $entry ) ? (string) ( $entry['url'] ?? '' ) : '';
		if ( ! preg_match( '/^\d+(?:\.\d+)+$/', $version ) || ! preg_match( '#^https://github\.com/' . preg_quote( self::REPOSITORY, '#' ) . '/releases/download/[A-Za-z0-9._-]+/' . self::SLUG . '-' . preg_quote( $version, '#' ) . '\.zip$#', $package ) ) return array();
		$release_url = (string) ( $manifest['release_url'] ?? '' );
		if ( 0 !== strpos( $release_url, 'https://github.com/' . self::REPOSITORY . '/releases' ) ) $release_url = 'https://github.com/' . self::REPOSITORY . '/releases';
		return array( 'version' => $version, 'package' => esc_url_raw( $package ), 'url' => esc_url_raw( $release_url ) );
	}

	/** Respaldo por la API de GitHub (sujeta a su límite de consultas): el asset más reciente de Tools. */
	private static function from_api() {
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-Tools-Bridge' ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array();
		$best = array();
		foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! preg_match( '/^digitalisimo-tools-([0-9.]+)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) continue;
				if ( ! empty( $best['version'] ) && ! version_compare( $match[1], $best['version'], '>' ) ) continue;
				$best = array( 'version' => $match[1], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ), 'url' => esc_url_raw( $release['html_url'] ?? '' ) );
			}
		}
		return $best;
	}

	public static function uri_update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( self::FILE !== $plugin_file ) return $update;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], self::$installed_version, '>' ) ) return $update;
		return array( 'slug' => 'digitalisimo-tools', 'version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'], 'requires_php' => '7.4' );
	}

	public static function inject( $transient ) {
		if ( ! is_object( $transient ) || ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) return $transient;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], self::$installed_version, '>' ) ) return $transient;
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = array();
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) $transient->no_update = array();
		$transient->checked[ self::FILE ] = self::$installed_version;
		unset( $transient->no_update[ self::FILE ] );
		$transient->response[ self::FILE ] = (object) array( 'id' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-tools', 'slug' => 'digitalisimo-tools', 'plugin' => self::FILE, 'new_version' => $release['version'], 'url' => $release['url'], 'package' => $release['package'], 'tested' => get_bloginfo( 'version' ), 'requires' => '6.0', 'requires_php' => '7.4' );
		return $transient;
	}
}
