<?php
/**
 * Simulador de red de kit-roundtrip.sh (mu-plugin; CV-4, CV-5).
 *
 * Va en `pre_http_request` con prioridad PHP_INT_MAX - 1, después del proxy de medios de K (prioridad 1), y sólo
 * responde si nadie respondió antes (`$pre === false`). Así, lo que llega aquí es exactamente lo que el producto dejó
 * salir: cada petición se anota en probe/net.jsonl con su host, si el contexto de K estaba activo, la ruta REST y el
 * llamador. Responde:
 * - los hosts de demostración de los fixtures (`*.test` salvo los sitios del arnés) desde digi-shared/remote/<host>/,
 *   con retraso si SLOW=1 (A13), y escribe el archivo si la petición es en flujo (`stream` + `filename`);
 * - todo lo demás, incluidos los sitios A y B y el proveedor, con un WP_Error: nada sale a internet.
 */

if ( ! defined( 'DIGI_K_PROBE' ) ) {
	define( 'DIGI_K_PROBE', '/wordpress/wp-content/probe' );
	define( 'DIGI_K_SHARED', '/wordpress/wp-content/digi-shared' );
}

/** ¿Está activo el contexto de importación o exportación de K? null si K aún no expone cómo saberlo. */
function digi_k_context_active() {
	$class = '\Digitalisimo\Elements\Import\Kits\Context';
	if ( ! class_exists( $class, false ) ) {
		return class_exists( '\Digitalisimo\Elements\Import\Module', false ) ? false : null;
	}
	foreach ( array( 'is_active', 'active', 'current' ) as $method ) {
		if ( method_exists( $class, $method ) ) {
			return (bool) call_user_func( array( $class, $method ) );
		}
	}
	return null;
}

/** Resumen corto de la pila: el primer archivo de un plugin o del núcleo de WordPress que pidió la URL. */
function digi_k_caller() {
	$frames = array();
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ) as $frame ) {
		$file = (string) ( $frame['file'] ?? '' );
		if ( '' === $file || false !== strpos( $file, 'net-sim.php' ) || false !== strpos( $file, 'class-wp-hook.php' ) || false !== strpos( $file, 'plugin.php' ) && false !== strpos( $file, 'wp-includes' ) ) {
			continue;
		}
		$frames[] = preg_replace( '#^/wordpress/#', '', $file ) . ':' . ( $frame['line'] ?? 0 ) . ( isset( $frame['class'] ) ? ' ' . $frame['class'] . '::' . ( $frame['function'] ?? '' ) : '' );
		if ( count( $frames ) >= 4 ) {
			break;
		}
	}
	return implode( ' ← ', $frames );
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( false !== $pre ) {
			return $pre;
		}
		$opts  = json_decode( (string) @file_get_contents( DIGI_K_PROBE . '/options.json' ), true ) ?: array();
		$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$sites = (array) ( $opts['hosts'] ?? array( 'a.test', 'b.test', 'playground.test' ) );
		$entry = array(
			't'       => round( microtime( true ), 3 ),
			'url'     => substr( (string) $url, 0, 400 ),
			'host'    => $host,
			'method'  => (string) ( $args['method'] ?? 'GET' ),
			'stream'  => ! empty( $args['stream'] ),
			'context' => digi_k_context_active(),
			'route'   => (string) ( $GLOBALS['digi_k_rest_route'] ?? '' ),
			'cli'     => defined( 'WP_CLI' ) && WP_CLI,
			'caller'  => digi_k_caller(),
			'served'  => false,
		);
		$response = new WP_Error( 'digi_k_net_sim', 'Sin red en kit-roundtrip: ' . $host );
		$is_demo  = '' !== $host && preg_match( '/\.test$/', $host ) && ! in_array( $host, $sites, true );
		if ( $is_demo ) {
			$file = DIGI_K_SHARED . '/remote/' . $host . '/' . ltrim( rawurldecode( $path ), '/' );
			$real = realpath( $file );
			$root = realpath( DIGI_K_SHARED . '/remote/' . $host );
			if ( ! empty( $opts['slow'] ) ) {
				usleep( (int) ( $opts['slow_ms'] ?? 1500 ) * 1000 );
			}
			if ( $real && $root && 0 === strpos( $real, $root . '/' ) && is_file( $real ) ) {
				$body = (string) file_get_contents( $real );
				$type = function_exists( 'mime_content_type' ) ? (string) mime_content_type( $real ) : 'application/octet-stream';
				if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
					file_put_contents( $args['filename'], $body );
					$body = '';
				}
				$response = array(
					'headers'  => array( 'content-type' => $type, 'content-length' => (string) filesize( $real ) ),
					'body'     => $body,
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => ! empty( $args['stream'] ) ? (string) ( $args['filename'] ?? '' ) : null,
				);
				$entry['served'] = true;
			} else {
				$response = array(
					'headers'  => array( 'content-type' => 'text/html' ),
					'body'     => '',
					'response' => array( 'code' => 404, 'message' => 'Not Found' ),
					'cookies'  => array(),
					'filename' => null,
				);
				$entry['served'] = 404;
			}
		}
		file_put_contents( DIGI_K_PROBE . '/net.jsonl', wp_json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND );
		return $response;
	},
	PHP_INT_MAX - 1,
	3
);
