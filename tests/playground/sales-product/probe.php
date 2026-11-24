<?php
/**
 * Sonda del ensayo de la landing de producto (mu-plugin que copia setup.php).
 *
 * - Sin red (toda petición HTTP de PHP devuelve un error: Playground corre con un solo proceso y una petición a sí mismo
 *   se queda esperando) y sin correo.
 * - Avisos de PHP del kit de ventas → /digi-probe/php-errors.log (el arnés falla con cualquier línea); los fatales →
 *   fatal.log.
 * - `?digi_sp=<paso>&token=<token>`: `login` (inicia sesión en el navegador con un usuario del ensayo) y `product`
 *   (el precio que cobra hoy la tienda, para comparar con la landing).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_SP_DIR = '/digi-probe';

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_SP_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);
$digi_sp_handler = static function ( $severity, $message, $file, $line ) {
	if ( 1 === preg_match( '#modules/digitalisimo-sales/#', (string) $file ) ) {
		file_put_contents( DIGI_SP_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file:$line\n", FILE_APPEND );
	}
	return false;
};
set_error_handler( $digi_sp_handler );
add_action(
	'rest_api_init',
	static function () use ( $digi_sp_handler ) {
		$prev = null;
		$prev = set_error_handler(
			static function ( ...$args ) use ( $digi_sp_handler, &$prev ) {
				$digi_sp_handler( ...$args );
				return $prev ? call_user_func_array( $prev, $args ) : false;
			}
		);
	},
	PHP_INT_MAX
);
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'digi_sp_offline', 'Sin red en el ensayo.' );
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

function digi_sp_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Cache-Control: no-store' );
	echo wp_json_encode( $data );
	exit;
}

add_action(
	'wp_loaded',
	static function () {
		if ( ! isset( $_GET['digi_sp'] ) ) {
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_SP_DIR . '/token.txt' ) );
		if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) {
			digi_sp_out( array( 'error' => 'token' ), 403 );
		}
		$value = sanitize_text_field( wp_unslash( (string) ( $_GET['value'] ?? '' ) ) );
		switch ( sanitize_key( $_GET['digi_sp'] ) ) {
			case 'ping':
				digi_sp_out(
					array(
						'ready' => true,
						'wp'    => $GLOBALS['wp_version'],
						'php'   => PHP_VERSION,
						'woo'   => defined( 'WC_VERSION' ) ? WC_VERSION : null,
						'sales' => class_exists( '\Digitalisimo\Elements\Sales\Product' ),
					)
				);
				break;
			case 'login':
				$user = get_user_by( 'login', $value );
				if ( ! $user ) {
					digi_sp_out( array( 'ok' => false ), 404 );
				}
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID, true );
				digi_sp_out( array( 'ok' => true ) );
				break;
			case 'product':
				$product = wc_get_product( (int) $value );
				digi_sp_out( array( 'price' => $product ? $product->get_price() : null ) );
				break;
		}
		digi_sp_out( array( 'error' => 'paso' ), 400 );
	},
	1
);
