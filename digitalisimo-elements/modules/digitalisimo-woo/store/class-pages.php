<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use ElementorPro\Modules\QueryControl\Module as Query_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Páginas de la tienda (`digitalisimo-woo-pages`): sustituye a «WooCommerce Pages» de PRO
 * (`modules/woocommerce/widgets/elements.php`, mapa `modules/digitalisimo-convert/maps/wc-elements.php`). Mismos
 * controles (`element`, `product_id`) y misma salida: el shortcode de WooCommerce de la página elegida (carrito, pago,
 * seguimiento del pedido, «Mi cuenta» o la ficha de un producto), que pinta WooCommerce con sus plantillas, filtros y
 * scripts. El control de búsqueda de productos es el del constructor (control de consulta). Sin hoja ni script propios
 * salvo el foco visible.
 */
class Pages extends Base {

	public function get_name() {
		return 'digitalisimo-woo-pages';
	}

	public function get_title() {
		return esc_html__( 'Páginas de la tienda', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-pages';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'carrito', 'pago', 'cuenta', 'seguimiento', 'shortcode', 'producto', 'shop', 'cart', 'checkout', 'account', 'order tracking' );
	}

	/** Como PRO: el producto elegido no viaja en una exportación (su ID no existe en otro sitio). */
	public function on_export( $element ) {
		unset( $element['settings']['product_id'] );
		return $element;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_product',
			array(
				'label' => esc_html__( 'Elemento', 'pro-elements' ),
			)
		);

		$this->add_control(
			'element',
			array(
				'label'   => esc_html__( 'Página', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''                           => '— ' . esc_html__( 'Elegir', 'pro-elements' ) . ' —',
					'woocommerce_cart'           => esc_html__( 'Página del carrito', 'pro-elements' ),
					'product_page'               => esc_html__( 'Página de un producto', 'pro-elements' ),
					'woocommerce_checkout'       => esc_html__( 'Página de pago', 'pro-elements' ),
					'woocommerce_order_tracking' => esc_html__( 'Formulario de seguimiento del pedido', 'pro-elements' ),
					'woocommerce_my_account'     => esc_html__( 'Mi cuenta', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'product_id',
			array(
				'label'        => esc_html__( 'Producto', 'pro-elements' ),
				'type'         => Query_Module::QUERY_CONTROL_ID,
				'options'      => array(),
				'label_block'  => true,
				'autocomplete' => array(
					'object' => Query_Module::QUERY_OBJECT_POST,
					'query'  => array(
						'post_type' => array( 'product' ),
					),
				),
				'condition'    => array(
					'element' => array( 'product_page' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/** El shortcode de la página elegida, como `get_shortcode()` de PRO. */
	private function get_shortcode() {
		$settings = $this->get_settings();
		$element  = (string) ( $settings['element'] ?? '' );
		$allowed  = array( 'woocommerce_cart', 'product_page', 'woocommerce_checkout', 'woocommerce_order_tracking', 'woocommerce_my_account' );
		if ( ! in_array( $element, $allowed, true ) ) {
			return '';
		}
		if ( 'product_page' === $element ) {
			$product = false;
			if ( ! empty( $settings['product_id'] ) ) {
				$post    = get_post( (int) $settings['product_id'] );
				$product = ! empty( $post ) && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ? wc_setup_product_data( $post ) : false;
			}
			if ( empty( $product ) && current_user_can( 'manage_options' ) ) {
				return esc_html__( 'Elige un producto válido', 'pro-elements' );
			}
			$this->add_render_attribute( 'shortcode', 'id', (string) ( $settings['product_id'] ?? '' ) );
		}
		return sprintf( '[%s %s]', esc_html( $element ), $this->get_render_attribute_string( 'shortcode' ) );
	}

	/** Clase `product` en las entradas que pinta el shortcode, como `add_products_post_class_filter()` de PRO. */
	public static function product_post_class( $classes ) {
		$classes[] = 'product';
		return $classes;
	}

	protected function render_widget() {
		$shortcode = $this->get_shortcode();
		if ( empty( $shortcode ) ) {
			return;
		}
		add_filter( 'post_class', array( __CLASS__, 'product_post_class' ) );
		$html = do_shortcode( $shortcode );
		if ( 'woocommerce_checkout' === $this->get_settings( 'element' ) && '<div class="woocommerce"></div>' === $html ) {
			$html = '<div class="woocommerce">' . esc_html__( 'Your cart is currently empty.', 'woocommerce' ) . '</div>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- texto de WooCommerce.
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salida de WooCommerce.
		remove_filter( 'post_class', array( __CLASS__, 'product_post_class' ) );
	}

	public function render_plain_content() {
		echo $this->get_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en get_shortcode().
	}
}
