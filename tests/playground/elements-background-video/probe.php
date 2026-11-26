<?php
/**
 * Sonda del ensayo de los vídeos de fondo (mu-plugin): `?digi_bv=<modo>` (element, never o always) cambia la política
 * del sitio como la pantalla «Rendimiento» (`Background_Video::save_site()` y, si cambió, vaciado del constructor) y
 * devuelve lo resuelto. Sólo para la administradora con sesión. Sin red hacia fuera; los fatales van a fatal.log.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( '/digi-bv/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		return $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) && '127.0.0.1' !== $host ? new WP_Error( 'digi_bv_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
add_action(
	'wp_loaded',
	static function () {
		if ( ! isset( $_GET['digi_bv'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( array( 'error' => 'sin sesión' ), 403 );
		}
		$class = '\Digitalisimo\Elements\Front\Background_Video';
		if ( ! class_exists( $class ) ) {
			wp_send_json( array( 'fix' => false ) );
		}
		$changed = $class::save_site( array( 'mobile' => sanitize_key( wp_unslash( $_GET['digi_bv'] ) ), 'max_mb' => '2' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $changed ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		wp_send_json( array( 'fix' => true, 'mobile' => $class::get( 'mobile' ), 'max_mb' => $class::get( 'max_mb' ), 'changed' => $changed ) );
	}
);
