<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lo propio de las pantallas de SEO (inspector del front, puntajes, recomendaciones). El diseño compartido de los
 * plugins Digitalisimo (tokens, componentes y la clase de body `digitalisimo-admin`) lo pone digitalisimo-ui/.
 */
class Digitalisimo_Admin_UI {
	public static function init() { add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 ); }
	public static function assets() {
		if ( ! function_exists( 'digitalisimo_ui_screen' ) || ! digitalisimo_ui_screen() ) return;
		wp_enqueue_style( 'digitalisimo-admin-ui', DIGITALISIMO_INTEGRATIONS_URL . 'assets/admin-ui.css', array( 'digitalisimo-ui' ), DIGITALISIMO_INTEGRATIONS_VERSION );
	}
}
