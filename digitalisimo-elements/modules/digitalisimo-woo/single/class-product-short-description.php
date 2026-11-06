<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Descripción corta del producto (`digitalisimo-woo-product-short-description`): sustituye a «Short Description» de PRO
 * (`modules/woocommerce/widgets/product-short-description.php`, mapa `maps/woocommerce-product-short-description.php`).
 * Mismos controles, valores por defecto y selectores; el texto lo pinta la plantilla de WooCommerce
 * (`single-product/short-description.php`, con `woocommerce_short_description`). Hoja propia: sólo el foco visible de
 * los enlaces.
 */
class Product_Short_Description extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-short-description';
	}

	public function get_title() {
		return esc_html__( 'Descripción corta del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-description';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'texto', 'descripción', 'extracto', 'producto', 'description', 'product' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_product_description_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_style_warning();

		$this->add_responsive_control(
			'text_align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start'   => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'     => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .woocommerce-product-details__short-description' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => '.woocommerce {{WRAPPER}} .woocommerce-product-details__short-description',
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

		wc_get_template( 'single-product/short-description.php' );
	}
}
