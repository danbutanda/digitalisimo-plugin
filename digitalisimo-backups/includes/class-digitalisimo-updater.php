<?php
defined( 'ABSPATH' ) || exit;
/** Actualizador autónomo del módulo Backups. */
final class Digitalisimo_Backups_Updater {
	const REPO = 'danbutanda/digitalisimo-plugin';
	const KEY = 'digitalisimo_backups_release';
	public static function register() { add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject' ) ); add_filter( 'transient_update_plugins', array( __CLASS__, 'inject' ) ); }
	private static function release() {
		$cached = get_site_transient( self::KEY ); if ( false !== $cached ) return $cached;
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases?per_page=20', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-WordPress-Updater' ) ) );
		$found = array();
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) if ( preg_match( '/^digitalisimo-backups-([0-9.]+)\\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) && ( empty( $found['version'] ) || version_compare( $match[1], $found['version'], '>' ) ) ) $found = array( 'version' => $match[1], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ), 'url' => esc_url_raw( $release['html_url'] ?? '' ) );
		set_site_transient( self::KEY, $found, HOUR_IN_SECONDS ); return $found;
	}
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) return $transient; $file = plugin_basename( DIGITALISIMO_BACKUPS_FILE ); $release = self::release();
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = array(); if ( ! empty( $release['version'] ) && ! empty( $release['package'] ) && version_compare( $release['version'], DIGITALISIMO_BACKUPS_VERSION, '>' ) ) $transient->response[ $file ] = (object) array( 'slug' => 'digitalisimo-backups', 'plugin' => $file, 'new_version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'] );
		return $transient;
	}
}
