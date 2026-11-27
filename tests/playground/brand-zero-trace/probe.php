<?php
/**
 * Sonda de la auditoría de cero rastro (mu-plugin): sin red hacia fuera ni correo, contraseñas de aplicación
 * disponibles y los errores fatales en /digi-probe/fatal.log.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_filter( 'wp_is_application_passwords_available', '__return_true' );
add_filter( 'pre_wp_mail', '__return_true' );
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		return $host && ! in_array( $host, array( '127.0.0.1', 'localhost', wp_parse_url( home_url(), PHP_URL_HOST ) ), true ) ? new WP_Error( 'digi_zt_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( '/digi-probe/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);
