<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Información adicional del producto (`digitalisimo-woo-product-additional-information`): sustituye a «Additional
 * Information» de PRO (`modules/woocommerce/widgets/product-additional-information.php`, mapa
 * `maps/woocommerce-product-additional-information.php`). Mismos controles, valores por defecto y selectores; la tabla
 * de atributos la pinta la plantilla de WooCommerce (`single-product/tabs/additional-information.php`). «Encabezado» usa
 * la clase propia `digi-product-additional-information--heading-yes`.
 */
class Product_Additional_Information extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-additional-information';
	}

	public function get_title() {
		return esc_html__( 'Información adicional del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-info';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'información', 'atributos', 'datos', 'producto', 'information', 'product' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_additional_info_style',
			array(
				'label' => esc_html__( 'General', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'show_heading',
			array(
				'label'        => esc_html__( 'Encabezado', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'    => esc_html__( 'Ocultar', 'pro-elements' ),
				'render_type'  => 'ui',
				'return_value' => 'yes',
				'default'      => 'yes',
				'prefix_class' => 'digi-product-additional-information--heading-',
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} h2' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'show_heading!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'heading_typography',
				'selector'  => '.woocommerce {{WRAPPER}} h2',
				'condition' => array(
					'show_heading!' => '',
				),
			)
		);

		$this->add_control(
			'content_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .shop_attributes' => 'color: {{VALUE}}',
				),
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'content_typography',
				'selector' => '.woocommerce {{WRAPPER}} .shop_attributes',
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

		wc_get_template( 'single-product/tabs/additional-information.php' );
	}
}
