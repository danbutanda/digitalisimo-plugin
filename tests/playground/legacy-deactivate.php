<?php
/**
 * Desactiva en la red la copia de Element Pack de la corrida (`ep.json`; por defecto Lite 10.0.1) o, por HTTP desde
 * legacy-ab-run.mjs con el token de la corrida, DIGITALÍSIMO Migración o la vuelve a activar
 * (`?token=…&plugin=ep|migration&activate=0|1`).
 */
$digi_ab_http = isset( $_SERVER['REQUEST_METHOD'], $_GET['plugin'] ); // php-wasm declara la SAPI «cli» también en las peticiones HTTP.
if ( $digi_ab_http ) {
	$digi_ab_token = trim( (string) @file_get_contents( '/wordpress/wp-content/digi-ab/token.txt' ) );
	if ( '' === $digi_ab_token || ! hash_equals( $digi_ab_token, (string) ( $_GET['token'] ?? '' ) ) ) {
		http_response_code( 403 );
		exit;
	}
}
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$digi_ab_ep     = json_decode( (string) @file_get_contents( '/wordpress/wp-content/digi-ab/ep.json' ), true );
$digi_ab_plugin = is_array( $digi_ab_ep ) && ! empty( $digi_ab_ep['plugin'] ) ? $digi_ab_ep['plugin'] : 'bdthemes-element-pack/bdthemes-element-pack.php';
if ( $digi_ab_http && 'migration' === ( $_GET['plugin'] ?? '' ) ) {
	$digi_ab_plugin = 'digitalisimo-migration/digitalisimo-migration.php';
}
if ( $digi_ab_http && ! empty( $_GET['activate'] ) ) {
	$digi_ab_result = activate_plugin( $digi_ab_plugin, '', true );
} else {
	deactivate_plugins( $digi_ab_plugin, false, true );
	$digi_ab_result = true;
}
if ( $digi_ab_http ) {
	header( 'Content-Type: application/json' );
	echo wp_json_encode( array( 'plugin' => $digi_ab_plugin, 'ok' => ! is_wp_error( $digi_ab_result ), 'network' => array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) );
}
