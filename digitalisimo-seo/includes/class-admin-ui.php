<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lo propio de las pantallas de SEO (inspector del front, puntajes, recomendaciones). El diseño compartido de los
 * plugins Digitalisimo (tokens, componentes y la clase de body `digitalisimo-admin`) lo pone digitalisimo-ui/.
 */
class Digitalisimo_Admin_UI {
	public static function init() { add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 ); add_action( 'current_screen', array( __CLASS__, 'hidden_page_title' ) ); }

	/**
	 * Las pantallas de SEO sin entrada propia en el menú (pestañas registradas con add_submenu_page( null, … )) no dan
	 * título a WordPress: admin-header.php hacía strip_tags( null ), tres avisos «deprecated» de PHP 8.1+ en cada carga.
	 * Antes de pintar la cabecera se toma el título con el que se registró la pantalla.
	 */
	public static function hidden_page_title() {
		global $title, $plugin_page, $submenu;
		if ( ! empty( $title ) || ! is_string( $plugin_page ) || 0 !== strpos( $plugin_page, 'digitalisimo' ) ) return;
		foreach ( (array) ( $submenu[''] ?? array() ) as $item ) if ( isset( $item[2], $item[3] ) && $item[2] === $plugin_page ) { $title = $item[3]; return; } // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- es el título que WordPress buscaría.
	}
	public static function assets() {
		if ( ! function_exists( 'digitalisimo_ui_screen' ) || ! digitalisimo_ui_screen() ) return;
		wp_enqueue_style( 'digitalisimo-admin-ui', DIGITALISIMO_INTEGRATIONS_URL . 'assets/admin-ui.css', array( 'digitalisimo-ui' ), DIGITALISIMO_INTEGRATIONS_VERSION );
	}
}
