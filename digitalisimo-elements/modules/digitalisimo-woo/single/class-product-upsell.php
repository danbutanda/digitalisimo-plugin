<?php
namespace Digitalisimo\Elements\Woo\Single;

use Digitalisimo\Elements\Woo\Registry;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Ventas dirigidas (`digitalisimo-woo-product-upsell`): sustituye a «Upsells» de PRO
 * (`modules/woocommerce/widgets/product-upsell.php`, mapa `maps/woocommerce-product-upsell.php`).
 * Como el de PRO, hereda la cuadrícula de productos (`Store\Products_Base`, la base propia de C7 que repite
 * `products-base.php` de PRO): mismos controles, valores por defecto, condiciones y selectores, con las clases propias
 * de la cuadrícula (`digi-products-grid digi-wc-products`) y su hoja (la de Productos). WooCommerce pinta lo suyo: la
 * plantilla y los ganchos de WooCommerce con su lista de productos; el widget sólo añade la rejilla del núcleo
 * (`elementor-grid`) a la lista y, con «Alinear los botones automáticamente», la envoltura de los botones.
 */
class Product_Upsell extends \Digitalisimo\Elements\Woo\Store\Products_Base {
	use Product_Lookup;

	public function get_name() {
		return 'digitalisimo-woo-product-upsell';
	}

	public function get_title() {
		return esc_html__( 'Ventas dirigidas', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-upsell';
	}

	public function get_categories() {
		return array( 'woocommerce-elements-single' );
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'ventas dirigidas', 'recomendados', 'productos', 'upsell', 'product' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	/** La hoja de la cuadrícula de Productos (la de PRO era la misma, `widget-woocommerce-products`) y la propia, si existe. */
	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( Registry::style_depends( 'digitalisimo-woo-products' ), Registry::style_depends( $this->get_name() ) ) ) );
	}

	public function get_script_depends(): array {
		return array();
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_upsell_content',
			[
				'label' => esc_html__( 'Ventas dirigidas', 'pro-elements' ),
			]
		);

		$this->add_columns_responsive_control();

		$this->add_control(
			'orderby',
			[
				'label' => esc_html__( 'Ordenar por', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'date' => esc_html__( 'Fecha', 'pro-elements' ),
					'title' => esc_html__( 'Título', 'pro-elements' ),
					'price' => esc_html__( 'Precio', 'pro-elements' ),
					'popularity' => esc_html__( 'Popularidad', 'pro-elements' ),
					'rating' => esc_html__( 'Valoración', 'pro-elements' ),
					'rand' => esc_html__( 'Aleatorio', 'pro-elements' ),
					'menu_order' => esc_html__( 'Orden del menú', 'pro-elements' ),
				],
			]
		);

		$this->add_control(
			'order',
			[
				'label' => esc_html__( 'Orden', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'desc',
				'options' => [
					'asc' => esc_html__( 'Ascendente', 'pro-elements' ),
					'desc' => esc_html__( 'Descendente', 'pro-elements' ),
				],
			]
		);

		$this->end_controls_section();

		parent::register_controls();

		$this->start_injection( [
			'at' => 'before',
			'of' => 'section_design_box',
		] );

		$this->start_controls_section(
			'section_heading_style',
			[
				'label' => esc_html__( 'Encabezado', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'show_heading',
			[
				'label' => esc_html__( 'Encabezado', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'default' => 'yes',
				'return_value' => 'yes',
				'prefix_class' => 'show-heading-',
			]
		);

		$this->add_control(
			'heading_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}}.digi-wc-products .products > h2' => 'color: {{VALUE}}',
				],
				'condition' => [
					'show_heading!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'heading_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}}.digi-wc-products .products > h2',
				'condition' => [
					'show_heading!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'heading_text_align',
			[
				'label' => esc_html__( 'Alineación del texto', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'classes' => 'elementor-control-start-end',
				'selectors_dictionary' => [
					'left' => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				],
				'selectors' => [
					'{{WRAPPER}}.digi-wc-products .products > h2' => 'text-align: {{VALUE}}',
				],
				'condition' => [
					'show_heading!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'heading_spacing',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}.digi-wc-products .products > h2' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				],
				'condition' => [
					'show_heading!' => '',
				],
			]
		);

		$this->end_controls_section();

		$this->end_injection();	}

	protected function render_widget() {
		$settings = $this->get_settings_for_display();

		// Envoltura de «Añadir al carrito» y «Ver carrito» con «Alinear los botones automáticamente».
		if ( 'yes' === $settings['automatically_align_buttons'] ) {
			add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'add_to_cart_wrapper' ), 10, 1 );
		}

		$limit   = '-1';
		$columns = 4;
		$orderby = 'rand';
		$order   = 'desc';

		if ( ! empty( $settings['columns'] ) ) {
			$columns = $settings['columns'];
		}

		if ( ! empty( $settings['orderby'] ) ) {
			$orderby = $settings['orderby'];
		}

		if ( ! empty( $settings['order'] ) ) {
			$order = $settings['order'];
		}

		ob_start();
		woocommerce_upsell_display( sanitize_text_field( $limit ), sanitize_text_field( $columns ), sanitize_text_field( $orderby ), sanitize_text_field( $order ) );
		$html = (string) ob_get_clean();

		if ( $html ) {
			echo str_replace( '<ul class="products', '<ul class="products elementor-grid', $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla de WooCommerce.
		}

		if ( 'yes' === $settings['automatically_align_buttons'] ) {
			remove_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'add_to_cart_wrapper' ) );
		}
	}
}
