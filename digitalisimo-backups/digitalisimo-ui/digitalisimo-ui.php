<?php
/**
 * Diseño compartido de los plugins Digitalisimo (tokens y componentes de digitalisimo-ui.css).
 *
 * Cada uno de los siete plugins lleva una copia idéntica de esta carpeta (tests/digitalisimo-ui.mjs lo comprueba) y la
 * incluye al cargarse, así que cualquiera funciona solo. Con varios activos, cada copia se anota con su versión y se
 * sirve la más alta, aunque la función que la registra sea la de otra copia: un plugin sin actualizar nunca impone un
 * estilo viejo. La hoja (handle `digitalisimo-ui`) queda registrada en toda la administración para que otras hojas
 * dependan de sus tokens, y se encola, junto con la clase de body `digitalisimo-admin`, en las pantallas
 * ?page=digitalisimo*. Al cambiar la hoja o este archivo se cambian en las siete copias y se sube la versión.
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['digitalisimo_ui_copies']['1.0.0'] = __DIR__;

if ( ! function_exists( 'digitalisimo_ui_copy' ) ) {
	/**
	 * La copia de versión más alta entre las incluidas.
	 *
	 * @return array{0:string,1:string} versión y carpeta.
	 */
	function digitalisimo_ui_copy() {
		$copies = isset( $GLOBALS['digitalisimo_ui_copies'] ) && is_array( $GLOBALS['digitalisimo_ui_copies'] ) ? $GLOBALS['digitalisimo_ui_copies'] : array();
		uksort( $copies, 'version_compare' );
		$version = (string) array_key_last( $copies );
		return array( $version, '' === $version ? '' : (string) $copies[ $version ] );
	}

	/** ¿Es una pantalla de un plugin Digitalisimo (?page=digitalisimo*)? */
	function digitalisimo_ui_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide el estilo.
		return 0 === strpos( $page, 'digitalisimo' );
	}

	/** Registra la hoja compartida (una sola vez, con la copia más nueva). */
	function digitalisimo_ui_register() {
		list( $version, $dir ) = digitalisimo_ui_copy();
		if ( '' !== $dir && ! wp_style_is( 'digitalisimo-ui', 'registered' ) ) {
			wp_register_style( 'digitalisimo-ui', plugins_url( 'digitalisimo-ui.css', $dir . '/digitalisimo-ui.php' ), array(), $version );
		}
	}

	/**
	 * Dependencia para una hoja propia que use los tokens: `array( 'digitalisimo-ui' )` si la hoja compartida está
	 * registrada, o ninguna (WordPress descarta en silencio una hoja cuya dependencia no existe).
	 */
	function digitalisimo_ui_dependency() {
		if ( function_exists( 'wp_register_style' ) && did_action( 'init' ) ) {
			digitalisimo_ui_register();
		}
		return function_exists( 'wp_style_is' ) && wp_style_is( 'digitalisimo-ui', 'registered' ) ? array( 'digitalisimo-ui' ) : array();
	}

	/** En toda la administración queda registrada; en las pantallas propias, encolada. */
	function digitalisimo_ui_enqueue() {
		digitalisimo_ui_register();
		if ( digitalisimo_ui_screen() ) {
			wp_enqueue_style( 'digitalisimo-ui' );
		}
	}

	function digitalisimo_ui_body_class( $classes ) {
		return digitalisimo_ui_screen() && false === strpos( ' ' . $classes . ' ', ' digitalisimo-admin ' ) ? $classes . ' digitalisimo-admin' : $classes;
	}

	add_action( 'admin_enqueue_scripts', 'digitalisimo_ui_enqueue', 5 );
	add_filter( 'admin_body_class', 'digitalisimo_ui_body_class' );
}
