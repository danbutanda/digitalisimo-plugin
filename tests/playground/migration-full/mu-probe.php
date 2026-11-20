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
	// El WP-Cron lanzado por petición (spawn_cron(), que la cola de Backups llama aunque DISABLE_WP_CRON esté puesto)
	// correría el mismo respaldo en otro worker de Playground, cuyo bloqueo de archivos entre workers se traba. Como en
	// un hosting sin loopback para el cron: no sale, y el conductor hace avanzar la tarea.
	if ( false !== strpos( (string) $url, 'wp-cron.php' ) ) {
		file_put_contents( WP_CONTENT_DIR . '/probe/cron-spawn.log', $url . "\n", FILE_APPEND );
		return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	}
	// Red de la prueba en http://playground.test (multisite no admite puerto): como un archivo hosts, la petición va al
	// servidor local con su cabecera Host. Sólo GET, que es lo que pide la verificación de la portada.
	$port = (int) @file_get_contents( WP_CONTENT_DIR . '/probe/port' );
	if ( 'playground.test' === $host && $port ) {
		// Playground contesta la primera visita con una redirección que pone una cookie: se sigue con sus cookies.
		$cookies = array();
		for ( $hop = 0; $hop < 5; $hop++ ) {
			$local   = preg_replace( '#^https?://playground\.test#', 'http://127.0.0.1:' . $port, $url );
			$header  = "Host: playground.test\r\n" . ( $cookies ? 'Cookie: ' . implode( '; ', $cookies ) . "\r\n" : '' );
			$context = stream_context_create( array( 'http' => array( 'method' => 'GET', 'header' => $header, 'ignore_errors' => true, 'timeout' => (float) ( $args['timeout'] ?? 20 ), 'follow_location' => 0 ) ) );
			$body    = @file_get_contents( $local, false, $context );
			if ( false === $body ) {
				return new WP_Error( 'digi_probe_loopback', 'La petición local falló.' );
			}
			$code     = 0;
			$location = '';
			foreach ( (array) ( $http_response_header ?? array() ) as $line ) {
				if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $m ) ) {
					$code = (int) $m[1];
				} elseif ( preg_match( '#^Set-Cookie:\s*([^;]+)#i', $line, $m ) ) {
					$cookies[] = trim( $m[1] );
				} elseif ( preg_match( '#^Location:\s*(\S+)#i', $line, $m ) ) {
					$location = trim( $m[1] );
				}
			}
			if ( $code < 300 || $code >= 400 || '' === $location ) {
				break;
			}
			$url = 0 === strpos( $location, '/' ) ? 'http://playground.test' . $location : $location;
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
	$local = '/digi-zips/' . basename( (string) $package );
	file_put_contents( WP_CONTENT_DIR . '/probe/downloads.log', basename( (string) $package ) . "\n", FILE_APPEND );
	if ( ! is_file( $local ) ) {
		return new WP_Error( 'digi_probe_missing', 'ZIP ausente en la prueba: ' . basename( (string) $package ) );
	}
	$tmp = wp_tempnam( basename( (string) $package ) );
	copy( $local, $tmp );
	return $tmp;
}, 10, 2 );

// La redirección de activación de Elements (caso real del 2026-11-19): lo que deja el gancho de activación, leído justo
// después de activar Elements (antes de que «Migrar» lo borre), y lo que queda al terminar esa misma petición.
add_action( 'activated_plugin', static function ( $plugin, $network_wide ) {
	if ( 'digitalisimo-elements/pro-elements.php' !== $plugin ) {
		return;
	}
	$file = WP_CONTENT_DIR . '/probe/activation-redirect.json';
	file_put_contents( $file, wp_json_encode( array( 'network_wide' => (bool) $network_wide, 'at_activation' => get_transient( 'elementor_activation_redirect' ) ) ) );
	add_action( 'shutdown', static function () use ( $file ) {
		$seen          = (array) json_decode( (string) file_get_contents( $file ), true );
		$seen['at_end'] = get_transient( 'elementor_activation_redirect' );
		file_put_contents( $file, wp_json_encode( $seen ) );
	}, 1 );
}, 10, 2 );
