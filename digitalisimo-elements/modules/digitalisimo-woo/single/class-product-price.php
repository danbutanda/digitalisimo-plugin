<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Precio del producto (`digitalisimo-woo-product-price`): sustituye a «Product Price» de PRO
 * (`modules/woocommerce/widgets/product-price.php`, mapa `modules/digitalisimo-convert/maps/woocommerce-product-price.php`).
 * Mismos controles, valores por defecto y selectores; el precio lo pinta la plantilla de WooCommerce
 * (`single-product/price.php`). «Apilado» usa la clase propia `digi-product-price--block-yes`.
 */
class Product_Price extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-price';
	}

	public function get_title() {
		return esc_html__( 'Precio del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-price';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'precio', 'oferta', 'producto', 'price', 'product' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_price_style',
			array(
				'label' => esc_html__( 'Precio', 'pro-elements' ),
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
					'start'  => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'    => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
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
			'price_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .price' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '.woocommerce {{WRAPPER}} .price',
			)
		);

		$this->add_control(
			'sale_heading',
			array(
				'label'     => esc_html__( 'Precio rebajado', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'sale_price_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .price ins' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'sale_price_typography',
				'selector' => '.woocommerce {{WRAPPER}} .price ins',
			)
		);

		$this->add_control(
			'price_block',
			array(
				'label'        => esc_html__( 'Apilado', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'prefix_class' => 'digi-product-price--block-',
			)
		);

		$this->add_responsive_control(
			'sale_price_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors'  => array(
					'body:not(.rtl) {{WRAPPER}}:not(.digi-product-price--block-yes) del' => 'margin-right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}}:not(.digi-product-price--block-yes) del' => 'margin-left: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-product-price--block-yes del' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
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

		wc_get_template( '/single-product/price.php' );
	}
}
