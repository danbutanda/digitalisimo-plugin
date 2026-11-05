<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Pestañas de datos del producto (`digitalisimo-woo-product-data-tabs`): sustituye a «Product Data Tabs» de PRO
 * (`modules/woocommerce/widgets/product-data-tabs.php`, mapa `maps/woocommerce-product-data-tabs.php`). Mismos
 * controles, valores por defecto y selectores. WooCommerce pinta lo suyo: la plantilla `single-product/tabs/tabs.php`
 * con el filtro `woocommerce_product_tabs`, y su script de la ficha (`wc-single-product`) las hace funcionar, con los
 * roles de pestañas que pone WooCommerce. En el editor, al volver a pintar el widget, `Single\Module` vuelve a iniciar
 * las pestañas recién pintadas (PRO lo hacía con un script en línea dentro del marcado). La hoja repite la de PRO sólo dentro del
 * widget y añade el foco visible.
 */
class Product_Data_Tabs extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-data-tabs';
	}

	public function get_title() {
		return esc_html__( 'Pestañas de datos del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-tabs';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'pestañas', 'datos', 'descripción', 'reseñas', 'producto', 'tabs', 'product' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_product_tabs_style',
			[
				'label' => esc_html__( 'Pestañas', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_style_warning();

		$this->start_controls_tabs( 'tabs_style' );

		$this->start_controls_tab( 'normal_tabs_style',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			]
		);

		$this->add_control(
			'tab_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li a' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'tab_bg_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'alpha' => false,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'tabs_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel' => 'border-color: {{VALUE}}',
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'active_tabs_style',
			[
				'label' => esc_html__( 'Activa', 'pro-elements' ),
			]
		);

		$this->add_control(
			'active_tab_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li.active a' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'active_tab_bg_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'alpha' => false,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel, .woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li.active' => 'background-color: {{VALUE}}',
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li.active' => 'border-bottom-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'active_tabs_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel' => 'border-color: {{VALUE}}',
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li.active' => 'border-color: {{VALUE}} {{VALUE}} {{active_tab_bg_color.VALUE}} {{VALUE}}',
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li:not(.active)' => 'border-bottom-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'tab_typography',
				'selector' => '.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li a',
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tab_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs li' => 'border-radius: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_product_panel_style',
			[
				'label' => esc_html__( 'Panel', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-Tabs-panel' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'content_typography',
				'selector' => '.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel',
			]
		);

		$this->add_control(
			'heading_panel_heading_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Encabezado', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'heading_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-Tabs-panel h2' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'content_heading_typography',
				'selector' => '.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel h2',
			]
		);

		$this->add_control(
			'panel_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; margin-top: -{{TOP}}{{UNIT}}',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'panel_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
					'.woocommerce {{WRAPPER}} .woocommerce-tabs ul.wc-tabs' => 'margin-left: {{TOP}}{{UNIT}}; margin-right: {{RIGHT}}{{UNIT}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'panel_box_shadow',
				'selector' => '.woocommerce {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel',
			]
		);

		$this->end_controls_section();	}

	protected function render_widget() {
		global $product;
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		setup_postdata( $product->get_id() );

		wc_get_template( 'single-product/tabs/tabs.php' );
	}
}
