<?php
namespace Digitalisimo\Elements\Woo\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Páginas de la tienda (carrito, pago, Mi cuenta y resumen de la compra) con los widgets propios, lo que el módulo de
 * WooCommerce del constructor hace sólo para los de PRO (`WC_STATUS_PAGES_MAPPED_TO_WIDGETS` y `pageSettingsWidgets`):
 *
 * - **Estado del sistema** (`woocommerce_rest_prepare_system_status`, 11, después del de PRO): una página de
 *   WooCommerce hecha con el Carrito, Finalizar compra o Mi cuenta propios cuenta como página con su shortcode, igual que
 *   con el widget de PRO. Si no, tras «Migrar», WooCommerce → Estado avisaba de que faltaba el shortcode. Sólo mira las
 *   páginas que WooCommerce marca sin shortcode ni bloque y no escribe nada.
 * - **Editor** (`elementor/editor/after_enqueue_scripts`, `store/assets/shop-pages-editor.js`): el aviso «¿Guardar esto
 *   como página del carrito…?» al soltar uno de los cuatro widgets propios en una página, y el botón «Editar plantilla»
 *   del Carrito (carrito vacío), como con los de PRO. Mi cuenta ya lo atiende `my-account.js`.
 *
 * Arranca con `Woo\Module` (`BOOT`), sólo con WooCommerce.
 */
final class Shop_Pages {
	/**
	 * Página de WooCommerce (opción) → widget propio que la hace. Las claves de PRO son los nombres en inglés que
	 * devuelve WooCommerce (`page_name`, traducido con `_x()`): aquí se reconocen por la opción, en cualquier idioma.
	 */
	const STATUS_PAGES = array(
		'woocommerce_cart_page_id'      => 'digitalisimo-woo-cart',
		'woocommerce_checkout_page_id'  => 'digitalisimo-woo-checkout',
		'woocommerce_myaccount_page_id' => 'digitalisimo-woo-my-account',
	);

	const HANDLE = 'digitalisimo-woo-shop-pages-editor';

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'woocommerce_rest_prepare_system_status', array( __CLASS__, 'system_status' ), 11 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_scripts' ) );
	}

	/**
	 * `woocommerce_rest_prepare_system_status`: marca `shortcode_present` en las páginas del carrito, del pago y de Mi
	 * cuenta cuyo documento del constructor contiene el widget propio que las hace.
	 *
	 * @param mixed $response `WP_REST_Response` con `data['pages']`.
	 * @return mixed
	 */
	public static function system_status( $response ) {
		if ( ! is_object( $response ) || ! isset( $response->data['pages'] ) || ! is_array( $response->data['pages'] ) ) {
			return $response;
		}
		$by_page = array();
		foreach ( self::STATUS_PAGES as $option => $widget ) {
			$id = (int) get_option( $option );
			if ( $id > 0 ) {
				$by_page[ $id ] = $widget;
			}
		}
		foreach ( $response->data['pages'] as $index => $page ) {
			$id = is_array( $page ) && isset( $page['page_id'] ) ? (int) $page['page_id'] : 0;
			if ( ! isset( $by_page[ $id ] ) || ! empty( $page['shortcode_present'] ) || ! empty( $page['block_present'] ) ) {
				continue;
			}
			if ( self::has_widget( $id, $by_page[ $id ] ) ) {
				$response->data['pages'][ $index ]['shortcode_present'] = true;
			}
		}
		return $response;
	}

	/** ¿El documento del constructor de la página contiene ese widget? (como PRO: búsqueda en `_elementor_data`) */
	public static function has_widget( int $page_id, string $widget ): bool {
		if ( 'builder' !== get_post_meta( $page_id, '_elementor_edit_mode', true ) ) {
			return false;
		}
		$data = get_post_meta( $page_id, '_elementor_data', true );
		if ( is_array( $data ) ) {
			$data = wp_json_encode( $data );
		}
		return is_string( $data ) && false !== strpos( $data, '"' . $widget . '"' );
	}

	/** `elementor/editor/after_enqueue_scripts`: el script del editor, tras el del módulo de WooCommerce del constructor. */
	public static function editor_scripts(): void {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$file = 'modules/digitalisimo-woo/store/assets/shop-pages-editor.js';
		if ( ! is_file( \Digitalisimo\Elements\Woo\Assets::path( $file ) ) ) {
			return;
		}
		wp_enqueue_script( self::HANDLE, \Digitalisimo\Elements\Woo\Assets::url( $file ), array(), defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false, true );
	}
}
