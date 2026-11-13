<?php
/**
 * Sólo para tests/playground/elements-installer.sh: nunca se instala en un sitio real. Lo copia setup.php en
 * mu-plugins del WordPress efímero de Playground y lee /digi-probe/mode.json.
 *
 *  - Origen de pruebas del instalador (salvo en el modo «github»): el índice y el ZIP de Elements de dist/ servidos por
 *    server.cjs en 127.0.0.1, para que el ensayo no dependa de GitHub. La lista blanca de producción no acepta esas
 *    URL: el instalador sólo las toma con este filtro (digitalisimo_elements_installer_test_source), y aun así sólo de
 *    una base de bucle local; las dos excepciones de WordPress (host interno y puerto) también son sólo de este ensayo.
 *  - Sin peticiones al propio sitio (un solo proceso de Playground se esperaría a sí mismo) y sin correo.
 *  - Sonda ?digi_ei=state&t=<token>: límites de subida en vigor, plugins instalados y activos (en la red, por sitio) y
 *    si existen las carpetas del instalador y de Elements.
 */
$digi_ei = json_decode( (string) @file_get_contents( '/digi-probe/mode.json' ), true );
if ( ! is_array( $digi_ei ) ) {
	return;
}
if ( ! empty( $digi_ei['test_source'] ) ) {
	add_filter( 'digitalisimo_elements_installer_test_source', static function () use ( $digi_ei ) {
		return 'http://127.0.0.1:' . (int) $digi_ei['srv_port'] . '/';
	} );
	add_filter( 'http_request_host_is_external', static function ( $external, $host ) {
		return '127.0.0.1' === $host ? true : $external;
	}, 10, 2 );
	add_filter( 'http_allowed_safe_ports', static function ( $ports ) use ( $digi_ei ) {
		$ports[] = (int) $digi_ei['srv_port'];
		return $ports;
	} );
}
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
	return $host === (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) ? new WP_Error( 'digi_no_loopback', 'Bucle hacia el propio sitio cortado en el ensayo.' ) : $pre;
}, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
add_action( 'init', static function () use ( $digi_ei ) {
	if ( 'state' !== ( $_GET['digi_ei'] ?? '' ) || ! hash_equals( (string) $digi_ei['token'], (string) ( $_GET['t'] ?? '' ) ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	wp_cache_delete( 'plugins', 'plugins' );
	$state = array(
		'ini' => array( 'upload_max_filesize' => ini_get( 'upload_max_filesize' ), 'post_max_size' => ini_get( 'post_max_size' ) ),
		'wp_max_upload_size' => wp_max_upload_size(),
		'versions' => array( 'wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION ),
		'plugins' => array_keys( get_plugins() ),
		'dirs' => array(
			'installer' => is_dir( WP_PLUGIN_DIR . '/digitalisimo-elements-installer' ),
			'elements' => is_file( WP_PLUGIN_DIR . '/digitalisimo-elements/pro-elements.php' ),
		),
		'sitewide' => is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array(),
		'blogs' => array(),
	);
	foreach ( is_multisite() ? get_sites( array( 'fields' => 'ids' ) ) : array( get_current_blog_id() ) as $id ) {
		if ( is_multisite() ) {
			switch_to_blog( $id );
		}
		$state['blogs'][ $id ] = array_values( (array) get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			restore_current_blog();
		}
	}
	wp_send_json( $state );
}, 0 );
