<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Elementos del producto (`digitalisimo-woo-single-elements`): sustituye a «Woo - Single Elements» de PRO
 * (`modules/woocommerce/widgets/single-elements.php`, obsoleto y oculto del panel; mapa `maps/wc-single-elements.php`).
 * Un único control, «Elemento», con la misma lista cerrada de funciones de WooCommerce que PRO; sólo se llama a una de
 * esa lista. Oculto del panel como el de PRO: existe para que el contenido que lo usa se siga pintando y se convierta.
 */
class Single_Elements extends Base {

	public function get_name() {
		return 'digitalisimo-woo-single-elements';
	}

	public function get_title() {
		return esc_html__( 'Elementos del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-woocommerce';
	}

	/** Obsoleto, como el de PRO. */
	public function show_in_panel() {
		return false;
	}

	/** Funciones de WooCommerce que puede pintar, con su etiqueta (la lista de PRO). */
	private function allowed_functions(): array {
		return array(
			''                                               => '— ' . esc_html__( 'Elegir', 'pro-elements' ) . ' —',
			'woocommerce_output_product_data_tabs'           => esc_html__( 'Pestañas de datos', 'pro-elements' ),
			'woocommerce_template_single_title'              => esc_html__( 'Título', 'pro-elements' ),
			'woocommerce_template_single_rating'             => esc_html__( 'Valoración', 'pro-elements' ),
			'woocommerce_template_single_price'              => esc_html__( 'Precio', 'pro-elements' ),
			'woocommerce_template_single_excerpt'            => esc_html__( 'Extracto', 'pro-elements' ),
			'woocommerce_template_single_meta'               => esc_html__( 'Metadatos', 'pro-elements' ),
			'woocommerce_template_single_sharing'            => esc_html__( 'Compartir', 'pro-elements' ),
			'woocommerce_show_product_sale_flash'            => esc_html__( 'Distintivo de oferta', 'pro-elements' ),
			'woocommerce_product_additional_information_tab' => esc_html__( 'Pestaña de información adicional', 'pro-elements' ),
			'woocommerce_upsell_display'                     => esc_html__( 'Ventas dirigidas', 'pro-elements' ),
			'wc_get_stock_html'                              => esc_html__( 'Estado de existencias', 'pro-elements' ),
		);
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
				'label'   => esc_html__( 'Elemento', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->allowed_functions(),
			)
		);

		$this->end_controls_section();
	}

	/** `woocommerce_product_tabs` (11): las pestañas sin la descripción, como PRO. */
	public function remove_description_tab( $tabs ) {
		unset( $tabs['description'] );
		return $tabs;
	}

	protected function render_widget() {
		global $product;
		$product = $this->get_product();

		$element = (string) $this->get_settings( 'element' );
		$html    = '';

		switch ( $element ) {
			case '':
				break;

			case 'wc_get_stock_html':
				$html = $product ? wc_get_stock_html( $product ) : '';
				break;

			case 'woocommerce_output_product_data_tabs':
				add_filter( 'woocommerce_product_tabs', array( $this, 'remove_description_tab' ), 11 );
				ob_start();
				woocommerce_output_product_data_tabs();
				// Con la clase `product` de WooCommerce alrededor, como PRO.
				$html = '<div class="product">' . ob_get_clean() . '</div>';
				remove_filter( 'woocommerce_product_tabs', array( $this, 'remove_description_tab' ), 11 );
				break;

			case 'woocommerce_template_single_rating':
				$is_edit_mode = self::is_edit_mode();

				if ( 'no' === get_option( 'woocommerce_enable_review_rating' ) ) {
					if ( $is_edit_mode ) {
						$html = esc_html__( 'Aviso para administradores:', 'pro-elements' ) . ' ' . esc_html__( 'Activa las valoraciones de las reseñas', 'pro-elements' );
					}
					break;
				}

				ob_start();
				woocommerce_template_single_rating();
				$html = ob_get_clean();
				if ( '' === $html && $is_edit_mode ) {
					$html = esc_html__( 'Aviso para administradores:', 'pro-elements' ) . ' ' . esc_html__( 'Sin reseñas con valoración', 'pro-elements' );
				}
				break;

			default:
				// Sólo una función de la lista cerrada.
				if ( array_key_exists( $element, $this->allowed_functions() ) && is_callable( $element ) ) {
					ob_start();
					$returned = call_user_func( $element );
					$html     = ob_get_clean() . ( is_string( $returned ) ? $returned : '' );
				}
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WooCommerce o texto propio escapado.
	}
}
