<?php
/**
 * Sonda de la auditoría de cero rastro (mu-plugin): sin red hacia fuera ni correo, contraseñas de aplicación
 * disponibles y los errores fatales en /digi-probe/fatal.log.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_filter( 'wp_is_application_passwords_available', '__return_true' );
// PAQUETE=1 con MARCA=on: la marca propia con el paquete transformado (Toggle la deja «no disponible» hasta validar el
// editor, D-12); esta prueba es esa validación.
$digi_zt_opts = json_decode( (string) @file_get_contents( '/digi-probe/options.json' ), true ) ?: array();
if ( ! empty( $digi_zt_opts['paquete'] ) && 'on' === ( $digi_zt_opts['marca'] ?? '' ) ) {
	add_filter( 'digitalisimo_elements_marca_editor_ready', '__return_true' );
}
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
// Lo que el editor dejó guardado (para la prueba del editor de run.cjs): datos, estado y plantilla, con la contraseña
// de aplicación del administrador.
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'digi-zt/v1',
			'/meta/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function ( $r ) {
					$id = (int) $r['id'];
					return array( 'data' => (string) get_post_meta( $id, '_elementor_data', true ), 'status' => get_post_status( $id ), 'template' => (string) get_post_meta( $id, '_wp_page_template', true ) );
				},
			)
		);
	}
);
// Diagnóstico del nonce del AJAX del editor (run.cjs): qué recibe el servidor y qué nonce espera para esta sesión.
add_action(
	'wp_ajax_digi_zt_nonce',
	static function () {
		$n = isset( $_POST['n'] ) ? (string) wp_unslash( $_POST['n'] ) : '';
		wp_send_json( array( 'user' => get_current_user_id(), 'recibido' => $n, 'valido' => wp_verify_nonce( $n, 'elementor_ajax' ), 'esperado' => wp_create_nonce( 'elementor_ajax' ) ) );
	}
);
