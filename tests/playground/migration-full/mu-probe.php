<?php
/**
 * Mu-plugin de migration-full.sh (sólo Playground): el índice de la Release apunta a ZIP locales de esta copia del
 * repositorio (las mismas URL oficiales que valida Migración; `upgrader_pre_download` entrega el ZIP local) y ninguna
 * petición sale del sitio: GitHub nunca se consulta.
 */
defined( 'ABSPATH' ) || exit;

$GLOBALS['digi_probe_manifest'] = json_decode( (string) @file_get_contents( WP_CONTENT_DIR . '/probe/manifest.json' ), true );

add_action( 'muplugins_loaded', static function () {
	if ( is_array( $GLOBALS['digi_probe_manifest'] ) && ! is_array( get_site_transient( 'digitalisimo_plugins_manifest' ) ) ) {
		set_site_transient( 'digitalisimo_plugins_manifest', $GLOBALS['digi_probe_manifest'], 0 );
	}
} );

add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	// Red de la prueba en http://playground.test (multisite no admite puerto): como un archivo hosts, la petición va al
	// servidor local con su cabecera Host. Sólo GET, que es lo que pide la verificación de la portada.
	$port = (int) @file_get_contents( WP_CONTENT_DIR . '/probe/port' );
	if ( 'playground.test' === $host && $port ) {
		$local   = preg_replace( '#^https?://playground\.test#', 'http://127.0.0.1:' . $port, $url );
		$context = stream_context_create( array( 'http' => array( 'method' => 'GET', 'header' => "Host: playground.test\r\n", 'ignore_errors' => true, 'timeout' => (float) ( $args['timeout'] ?? 20 ), 'follow_location' => 1, 'max_redirects' => 3 ) ) );
		$body    = @file_get_contents( $local, false, $context );
		if ( false === $body ) {
			return new WP_Error( 'digi_probe_loopback', 'La petición local falló.' );
		}
		$code = 0;
		foreach ( (array) ( $http_response_header ?? array() ) as $line ) {
			if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $m ) ) {
				$code = (int) $m[1];
			}
		}
		return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}
	if ( in_array( $host, array( '127.0.0.1', 'localhost', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), true ) ) {
		return $pre;
	}
	file_put_contents( WP_CONTENT_DIR . '/probe/http-blocked.log', $url . "\n", FILE_APPEND );
	return new WP_Error( 'digi_probe_offline', 'Sin red en la prueba.' );
}, 1, 3 );

add_filter( 'upgrader_pre_download', static function ( $reply, $package ) {
	$local = WP_CONTENT_DIR . '/probe/zips/' . basename( (string) $package );
	file_put_contents( WP_CONTENT_DIR . '/probe/downloads.log', basename( (string) $package ) . "\n", FILE_APPEND );
	if ( ! is_file( $local ) ) {
		return new WP_Error( 'digi_probe_missing', 'ZIP ausente en la prueba: ' . basename( (string) $package ) );
	}
	$tmp = wp_tempnam( basename( (string) $package ) );
	copy( $local, $tmp );
	return $tmp;
}, 10, 2 );
