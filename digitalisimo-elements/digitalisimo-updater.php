<?php

defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Updater {
	const CACHE_KEY = 'digitalisimo_elements_release';
	const ACTION = 'digitalisimo_elements_check_updates';
	const FILE = 'digitalisimo-elements/pro-elements.php';
	const SLUG = 'digitalisimo-elements';
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
	const LAST_KNOWN = 'digitalisimo_elements_last_release';

	public static function init() {
		foreach ( array( 'pre_set_site_transient_update_plugins', 'pre_set_transient_update_plugins', 'site_transient_update_plugins', 'transient_update_plugins' ) as $hook ) add_filter( $hook, array( __CLASS__, 'check' ), 30 );
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'uri_update' ), 30, 4 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'force_check' ) );
		// «Buscar actualizaciones de nuevo» de WordPress (update-core.php?force-check=1): vaciar las cachés propias antes de que WordPress vuelva a consultar.
		add_action( 'load-update-core.php', array( __CLASS__, 'native_force_check' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_after_upgrade' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_inline_update' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedule_interval' ) );
		add_action( 'digitalisimo_elements_refresh_updates', array( __CLASS__, 'refresh' ) );
		if ( ( ! is_multisite() || is_main_site() ) && ! wp_next_scheduled( 'digitalisimo_elements_refresh_updates' ) ) wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'digitalisimo_elements_five_minutes', 'digitalisimo_elements_refresh_updates' );
	}

	public static function schedule_interval( $schedules ) {
		$schedules['digitalisimo_elements_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Cada cinco minutos · DIGITALÍSIMO Elements' );
		return $schedules;
	}

	/** Usa la actualización AJAX de WordPress dentro de la lista de Plugins. */
	public static function enqueue_inline_update( $hook ) {
		if ( 'plugins.php' !== $hook || ! self::can_update() ) return;
		wp_enqueue_script( 'digitalisimo-inline-plugin-update', plugins_url( 'assets/js/update-in-place.js', WP_PLUGIN_DIR . '/' . self::FILE ), array( 'updates' ), DIGITALISIMO_ELEMENTS_VERSION, true );
		wp_add_inline_script( 'digitalisimo-inline-plugin-update', 'window.digitalisimoPluginUpdateFiles=window.digitalisimoPluginUpdateFiles||{};window.digitalisimoPluginUpdateFiles[' . wp_json_encode( self::FILE ) . ']=true;', 'before' );
	}

	private static function can_update() { return is_multisite() ? current_user_can( 'manage_network_plugins' ) : current_user_can( 'update_plugins' ); }

	public static function action_link( $links ) {
		if ( ! self::can_update() ) return $links;
		$url = wp_nonce_url( add_query_arg( array( 'action' => self::ACTION, 'digitalisimo_context' => is_network_admin() ? 'network' : 'site' ), admin_url( 'admin-post.php' ) ), self::ACTION );
		$links[] = '<a href="' . esc_url( $url ) . '">Buscar actualizaciones</a>';
		return $links;
	}

	/** El botón nativo de WordPress consulta de inmediato: se olvidan la versión conocida y el índice compartido. */
	public static function native_force_check() {
		if ( empty( $_GET['force-check'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo vacía cachés propias.
		self::clear();
	}
	public static function force_check() {
		if ( ! self::can_update() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION );
		self::refresh();
		$context = sanitize_key( wp_unslash( $_GET['digitalisimo_context'] ?? '' ) );
		$default = is_multisite() && 'network' === $context ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'digitalisimo-elements-checked', '1', $back ? $back : $default ) );
		exit;
	}

	public static function notice() {
		if ( empty( $_GET['digitalisimo-elements-checked'] ) || ! self::can_update() ) return;
		$release = self::release();
		$pending = ! empty( $release['version'] ) && version_compare( $release['version'], DIGITALISIMO_ELEMENTS_VERSION, '>' );
		$message = $pending ? 'DIGITALÍSIMO Elements ' . $release['version'] . ' está disponible para actualizar.' : 'DIGITALÍSIMO Elements está al día.';
		echo '<div class="notice notice-' . ( $pending ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	public static function clear() {
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( self::MANIFEST_CACHE );
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
		$best = self::from_manifest();
		if ( ! $best ) $best = self::from_api();
		if ( $best ) {
			update_site_option( self::LAST_KNOWN, $best );
			set_site_transient( self::CACHE_KEY, $best, 10 * MINUTE_IN_SECONDS );
			return $best;
		}
		// GitHub no respondió o limitó las consultas: se conserva lo último encontrado y se reintenta pronto.
		$known = get_site_option( self::LAST_KNOWN, array() );
		$known = is_array( $known ) ? $known : array();
		set_site_transient( self::CACHE_KEY, $known, 2 * MINUTE_IN_SECONDS );
		return $known;
	}

	/** Versión de este plugin en el índice de la última Release; sólo acepta un ZIP suyo de este repositorio. */
	private static function from_manifest() {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) {
			$response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 10, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Elements' ) ) );
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

	/** Respaldo por la API de GitHub (sujeta a su límite de consultas): el asset más reciente de este plugin. */
	private static function from_api() {
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-Elements' ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array();
		$best = array();
		foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! preg_match( '/^digitalisimo-elements-([0-9.]+)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) continue;
				if ( ! empty( $best['version'] ) && ! version_compare( $match[1], $best['version'], '>' ) ) continue;
				$best = array( 'version' => $match[1], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ), 'url' => esc_url_raw( $release['html_url'] ?? '' ) );
			}
		}
		return $best;
	}

	public static function uri_update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( self::FILE !== $plugin_file ) return $update;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], DIGITALISIMO_ELEMENTS_VERSION, '>' ) ) return false;
		return array( 'slug' => 'digitalisimo-elements', 'version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'], 'requires_php' => '7.4', 'autoupdate' => true );
	}

	public static function auto_update( $update, $item ) { return isset( $item->plugin ) && self::FILE === $item->plugin ? true : $update; }

	public static function check( $transient ) {
		// Una caché ausente o aún incompleta debe quedar en manos de WordPress.
		if ( ! is_object( $transient ) || ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) return $transient;
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = array();
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) $transient->no_update = array();
		$transient->checked[ self::FILE ] = DIGITALISIMO_ELEMENTS_VERSION;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) ) return $transient;
		$pending = version_compare( $release['version'], DIGITALISIMO_ELEMENTS_VERSION, '>' );
		$item = (object) array( 'id' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-elements', 'slug' => 'digitalisimo-elements', 'plugin' => self::FILE, 'new_version' => $pending ? $release['version'] : DIGITALISIMO_ELEMENTS_VERSION, 'url' => $release['url'], 'package' => $pending ? $release['package'] : '', 'tested' => get_bloginfo( 'version' ), 'requires' => '6.8', 'requires_php' => '7.4', 'autoupdate' => true );
		if ( $pending ) { unset( $transient->no_update[ self::FILE ] ); $transient->response[ self::FILE ] = $item; }
		else { unset( $transient->response[ self::FILE ] ); $transient->no_update[ self::FILE ] = $item; }
		return $transient;
	}
}
