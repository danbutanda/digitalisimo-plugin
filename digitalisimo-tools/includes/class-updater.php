<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;
final class Updater {
	public static function init() { add_filter( 'site_transient_update_plugins', array( __CLASS__, 'check' ) ); add_filter( 'transient_update_plugins', array( __CLASS__, 'check' ) ); }
	public static function check( $t ) { if ( ! is_object( $t ) || empty( $t->checked ) ) return $t; $r = wp_remote_get( 'https://api.github.com/repos/danbutanda/digitalisimo-plugin/releases?per_page=50', array( 'timeout' => 10, 'headers' => array( 'User-Agent' => 'Digitalisimo-Tools' ) ) ); if ( is_wp_error( $r ) ) return $t; $best = array(); foreach ( (array) json_decode( wp_remote_retrieve_body( $r ), true ) as $release ) foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) if ( preg_match( '/^digitalisimo-tools-([0-9.]+)\\.zip$/', $asset['name'] ?? '', $m ) && ( empty( $best['version'] ) || version_compare( $m[1], $best['version'], '>' ) ) ) $best = array( 'version' => $m[1], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ) ); if ( ! empty( $best['version'] ) && version_compare( $best['version'], DIGITALISIMO_TOOLS_VERSION, '>' ) ) $t->response[ plugin_basename( DIGITALISIMO_TOOLS_FILE ) ] = (object) array( 'slug' => 'digitalisimo-tools', 'plugin' => plugin_basename( DIGITALISIMO_TOOLS_FILE ), 'new_version' => $best['version'], 'package' => $best['package'] ); return $t; }
}
