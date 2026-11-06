<?php
namespace Digitalisimo\Elements\Woo\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Peticiones de WooCommerce del Carrito propio (`digitalisimo-woo-cart`), lo que PRO hace para el suyo desde su módulo
 * (`load_widget_before_wc_ajax()` y `empty_cart_fragments()` de `modules/woocommerce/module.php`, que sólo buscan
 * `woocommerce-cart`):
 *
 * - `?wc-ajax=update_shipping_method` (cambiar el método de envío): antes de recalcular, busca el primer Carrito propio de
 *   la página de origen y engancha sus ganchos de pintado, así los totales que devuelve WooCommerce llevan los textos
 *   del widget (botón de pago, actualizar envío). Comprueba el nonce de WooCommerce y no hace nada más.
 * - Fragmentos del carrito vacío: con «Personalizar el carrito vacío» y una plantilla elegida, al vaciarse el carrito
 *   por AJAX el contenedor del widget se sustituye por la plantilla, sin recargar.
 *
 * Arranca con `Woo\Module` (`BOOT`), sólo con WooCommerce. No escribe nada.
 */
final class Cart_Ajax {
	const WIDGET = 'digitalisimo-woo-cart';

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $hooked = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'before_shipping_update' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'empty_cart_fragments' ) );
	}

	/** Elementos del Carrito propio de un documento, en orden. */
	private static function widgets( int $page_id ): array {
		if ( $page_id <= 0 || ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return array();
		}
		$document = \Elementor\Plugin::$instance->documents->get_doc_for_frontend( $page_id );
		if ( ! $document ) {
			return array();
		}
		$found = array();
		\Elementor\Plugin::$instance->db->iterate_data(
			$document->get_elements_data(),
			static function ( $element ) use ( &$found ) {
				if ( isset( $element['widgetType'] ) && self::WIDGET === $element['widgetType'] ) {
					$found[] = $element;
				}
				return $element;
			}
		);
		return $found;
	}

	/** `woocommerce_before_calculate_totals` en `?wc-ajax=update_shipping_method`. */
	public static function before_shipping_update(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo se lee el nombre de la petición.
		$action = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : '';
		if ( self::$hooked || 'update_shipping_method' !== $action ) {
			return;
		}
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'update-shipping-method' ) ) {
			return;
		}
		$page_id = (int) url_to_postid( (string) wp_get_referer() );
		$widgets = self::widgets( $page_id );
		if ( ! $widgets ) {
			return;
		}
		self::$hooked = true;
		$post         = get_post( $page_id );
		if ( $post ) {
			setup_postdata( $post );
		}
		$instance = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $widgets[0] );
		if ( $instance && method_exists( $instance, 'add_render_hooks' ) ) {
			$instance->add_render_hooks();
		}
	}

	/**
	 * Con el carrito vacío, el contenedor de cada Carrito propio con plantilla de carrito vacío se sustituye por la
	 * plantilla (como `empty_cart_fragments()` de PRO).
	 *
	 * @param array $fragments Fragmentos de WooCommerce.
	 */
	public static function empty_cart_fragments( $fragments ) {
		if ( ! is_array( $fragments ) || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->get_cart_contents_count() !== 0 ) {
			return $fragments;
		}
		foreach ( self::widgets( (int) url_to_postid( (string) wp_get_referer() ) ) as $element ) {
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			$template = isset( $settings['additional_template_select'] ) ? (int) $settings['additional_template_select'] : 0;
			if ( 'active' !== ( $settings['additional_template_switch'] ?? '' ) || $template <= 0 ) {
				continue;
			}
			$fragments[ 'div.elementor-element-' . sanitize_key( (string) $element['id'] ) . ' .digi-cart' ] = '<div class="digi-cart">' . do_shortcode( '[elementor-template id="' . $template . '"]' ) . '</div>';
		}
		return $fragments;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$hooked      = false;
	}
}
