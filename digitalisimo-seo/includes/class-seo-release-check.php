<?php
defined( 'ABSPATH' ) || exit;

/** Consulta independiente de SEO para no depender de la caché horaria del actualizador compartido. */
final class Digitalisimo_SEO_Release_Check {
	const CACHE_KEY = 'digitalisimo_seo_release_check';
	const HOOK = 'digitalisimo_seo_refresh_release';

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'uri_update' ), 30, 4 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject' ), 30 );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ), 30 );
		add_action( self::HOOK, array( __CLASS__, 'refresh' ) );
		add_action( 'admin_post_digitalisimo_check_updates', array( __CLASS__, 'clear_for_manual' ), 1 );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedule' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) wp_schedule_event( time() + 300, 'digitalisimo_five_minutes', self::HOOK );
	}

	public static function schedule( $schedules ) {
		$schedules['digitalisimo_five_minutes'] = array( 'interval' => 300, 'display' => 'Digitalisimo: cada cinco minutos' );
		return $schedules;
	}

	public static function clear_for_manual() {
		if ( ! current_user_can( is_multisite() ? 'manage_network_plugins' : 'update_plugins' ) ) return;
		check_admin_referer( 'digitalisimo_check_updates' );
		delete_site_transient( self::CACHE_KEY );
	}

	/** El cron actualiza únicamente el índice de SEO y el aviso; no consulta WordPress.org. */
	public static function refresh() {
		$previous = get_site_transient( self::CACHE_KEY );
		delete_site_transient( self::CACHE_KEY );
		$release = self::release();
		if ( ! $release && is_array( $previous ) && ! empty( $previous['version'] ) ) set_site_transient( self::CACHE_KEY, $previous, MINUTE_IN_SECONDS );
		$updates = get_site_transient( 'update_plugins' );
		$updates = self::inject( $updates );
		if ( is_object( $updates ) ) set_site_transient( 'update_plugins', $updates );
	}

	private static function release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) return $cached;
		$response = wp_remote_get( 'https://api.github.com/repos/danbutanda/digitalisimo-plugin/releases?per_page=50', array(
			'timeout' => function_exists( 'wp_doing_cron' ) && wp_doing_cron() ? 10 : 3,
			'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-SEO-Updater' ),
		) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, array(), MINUTE_IN_SECONDS );
			return array();
		}
		$latest = array();
		foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! preg_match( '/^digitalisimo-seo-([0-9][0-9.]*)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) continue;
				if ( $latest && ! version_compare( $match[1], $latest['version'], '>' ) ) continue;
				$package = esc_url_raw( $asset['browser_download_url'] ?? '' );
				if ( ! $package ) continue;
				$latest = array( 'version' => $match[1], 'package' => $package, 'url' => esc_url_raw( $release['html_url'] ?? '' ) );
			}
		}
		set_site_transient( self::CACHE_KEY, $latest, 5 * MINUTE_IN_SECONDS );
		return $latest;
	}

	private static function pending() {
		$release = self::release();
		return ! empty( $release['version'] ) && version_compare( $release['version'], DIGITALISIMO_INTEGRATIONS_VERSION, '>' ) ? $release : array();
	}

	public static function uri_update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( plugin_basename( DIGITALISIMO_INTEGRATIONS_FILE ) !== $plugin_file ) return $update;
		$release = self::pending();
		if ( ! $release ) return $update;
		return array( 'slug' => 'digitalisimo-seo', 'version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'], 'requires_php' => '7.4' );
	}

	public static function inject( $transient ) {
		$release = self::pending();
		if ( ! $release ) return $transient;
		if ( ! is_object( $transient ) ) $transient = new stdClass();
		$file = plugin_basename( DIGITALISIMO_INTEGRATIONS_FILE );
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = array();
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) $transient->no_update = array();
		if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) $transient->checked = array();
		$transient->checked[ $file ] = DIGITALISIMO_INTEGRATIONS_VERSION;
		unset( $transient->no_update[ $file ] );
		$transient->response[ $file ] = (object) array(
			'id' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-seo',
			'slug' => 'digitalisimo-seo', 'plugin' => $file,
			'new_version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'],
			'requires' => '6.0', 'requires_php' => '7.4',
		);
		return $transient;
	}
}
