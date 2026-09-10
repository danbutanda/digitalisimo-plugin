<?php
defined( 'ABSPATH' ) || exit;

/** Actualizador nativo de WordPress para las Releases públicas de Digitalisimo. */
class Digitalisimo_GitHub_Updater {
	const REPOSITORY = 'danbutanda/digitalisimo-plugin';
	const CACHE_KEY  = 'digitalisimo_github_release';
	const CACHE_TTL  = 21600;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ), 10, 2 );
	}

	private static function plugin_file() { return plugin_basename( DIGITALISIMO_INTEGRATIONS_FILE ); }
	private static function release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) return $cached;
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50', array( 'timeout' => 8, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-WordPress-Updater/' . DIGITALISIMO_INTEGRATIONS_VERSION ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { set_site_transient( self::CACHE_KEY, array(), HOUR_IN_SECONDS ); return array(); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true ); $release = array();
		foreach ( (array) $data as $item ) foreach ( (array) ( $item['assets'] ?? array() ) as $asset ) if ( preg_match( '/^digitalisimo-seo-([0-9.]+)\\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) && ( empty( $release['version'] ) || version_compare( $match[1], $release['version'], '>' ) ) ) $release = array( 'version' => $match[1], 'download_url' => esc_url_raw( $asset['browser_download_url'] ?? '' ), 'details_url' => esc_url_raw( $item['html_url'] ?? 'https://github.com/' . self::REPOSITORY . '/releases' ), 'published' => sanitize_text_field( $item['published_at'] ?? '' ), 'body' => wp_kses_post( $item['body'] ?? '' ) );
		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL ); return $release;
	}

	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) || ! isset( $transient->checked[ self::plugin_file() ] ) ) return $transient;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['download_url'] ) || ! version_compare( $release['version'], DIGITALISIMO_INTEGRATIONS_VERSION, '>' ) ) return $transient;
		$update = (object) array( 'slug' => 'digitalisimo', 'plugin' => self::plugin_file(), 'new_version' => $release['version'], 'url' => $release['details_url'], 'package' => $release['download_url'], 'tested' => get_bloginfo( 'version' ), 'requires' => '6.0', 'requires_php' => '7.4' );
		$transient->response[ self::plugin_file() ] = $update; return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'digitalisimo' !== $args->slug ) return $result;
		$release = self::release(); return (object) array( 'name' => 'Digitalisimo', 'slug' => 'digitalisimo', 'version' => $release['version'] ?? DIGITALISIMO_INTEGRATIONS_VERSION, 'author' => 'Digitalísimo', 'homepage' => 'https://github.com/' . self::REPOSITORY, 'download_link' => $release['download_url'] ?? '', 'sections' => array( 'description' => 'SEO, estrategia de contenidos, comercio electrónico e integraciones de Digitalisimo.', 'changelog' => $release['body'] ?? 'Sin notas de versión.' ) );
	}

	public static function clear_cache( $upgrader, $options ) { if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) || 'plugin' !== $options['type'] ) return; delete_site_transient( self::CACHE_KEY ); }
}
