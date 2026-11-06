<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Existencias del producto (`digitalisimo-woo-product-stock`): sustituye a «Product Stock» de PRO
 * (`modules/woocommerce/widgets/product-stock.php`, mapa `maps/woocommerce-product-stock.php`). Mismos controles y
 * selectores; el aviso lo pinta WooCommerce (`wc_get_stock_html()`, con su filtro). Sin hoja ni script, como PRO.
 */
class Product_Stock extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-stock';
	}

	public function get_title() {
		return esc_html__( 'Existencias del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-stock';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'existencias', 'inventario', 'cantidad', 'producto', 'stock', 'product' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_product_stock_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_style_warning();

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .stock' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => '.woocommerce {{WRAPPER}} .stock',
			)
		);

		$this->end_controls_section();
	}

	protected function render_widget() {
		global $product;
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de WooCommerce.
	}
}
