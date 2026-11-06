<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Productos del archivo (`digitalisimo-woo-archive-products`): sustituye a «Archive Products» de PRO
 * (`modules/woocommerce/widgets/archive-products.php`, mapa `maps/wc-archive-products.php`). Hereda los Productos
 * propios como el de PRO hereda los suyos y repite sus cambios: la consulta actual por defecto (sección de consulta
 * oculta), paginación siempre activa, ordenar y número de resultados activos, sin columnas ni filas propias (las del
 * Personalizador de WooCommerce, con la hoja `archive-products.css`), y el mensaje sin resultados con sus estilos.
 */
class Archive_Products extends Products {

	public function get_name() {
		return 'digitalisimo-woo-archive-products';
	}

	public function get_title() {
		return esc_html__( 'Productos del archivo', 'pro-elements' );
	}

	public function get_keywords() {
		return [ 'woocommerce', 'tienda', 'productos', 'archivo', 'shop', 'store', 'product', 'archive' ];
	}

	public function get_categories() {
		return [ 'woocommerce-elements-archive' ];
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	/** La hoja de los Productos y la del archivo, como PRO (`widget-woocommerce-products` y `-products-archive`). */
	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( \Digitalisimo\Elements\Woo\Registry::style_depends( 'digitalisimo-woo-products' ), parent::get_style_depends() ) ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->remove_responsive_control( 'columns' );
		$this->remove_responsive_control( 'rows' );
		$this->remove_control( 'orderby' );
		$this->remove_control( 'order' );

		$this->update_control(
			'products_class',
			[
				'prefix_class' => 'digi-products-grid digi-',
			]
		);

		// Should be kept as hidden since required for "allow_order"
		$this->update_control(
			'paginate',
			[
				'type' => 'hidden',
				'default' => 'yes',
			]
		);

		$this->update_control(
			'allow_order',
			[
				'default' => 'yes',
			]
		);

		$this->start_injection( [
			'at' => 'before',
			'of' => 'allow_order',
		] );

		if ( ! get_theme_support( 'woocommerce' ) ) {
			$this->add_control(
				'wc_notice_wc_not_supported',
				[
					'type' => Controls_Manager::ALERT,
					'alert_type' => 'warning',
					'content' => esc_html__( 'Usas WooCommerce, pero tu tema no lo admite. Considera cambiar de tema.', 'pro-elements' ),
				]
			);
		}

		$this->add_control(
			'wc_notice_use_customizer',
			[
				'type' => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content' => esc_html__( 'Para cambiar el diseño del archivo de productos, ve a Apariencia > Personalizar.', 'pro-elements' ),
			]
		);

		$this->add_control(
			'wc_notice_wrong_data',
			[
				'type' => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content' => esc_html__( 'La vista previa del editor puede verse distinta del sitio publicado. Revisa el sitio publicado.', 'pro-elements' ),
			]
		);

		$this->end_injection();

		$this->update_control(
			'show_result_count',
			[
				'default' => 'yes',
			]
		);

		$this->update_control(
			'section_query',
			[
				'type' => 'hidden',
			]
		);

		$this->update_control(
			self::QUERY_CONTROL_NAME . '_post_type',
			[
				'default' => 'current_query',
			]
		);

		$this->start_controls_section(
			'section_advanced',
			[
				'label' => esc_html__( 'Avanzado', 'pro-elements' ),
			]
		);

		$this->add_control(
			'nothing_found_message',
			[
				'label' => esc_html__( 'Mensaje sin resultados', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'No encontramos lo que buscas.', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_nothing_found_style',
			[
				'tab' => Controls_Manager::TAB_STYLE,
				'label' => esc_html__( 'Mensaje sin resultados', 'pro-elements' ),
				'condition' => [
					'nothing_found_message!' => '',
				],
			]
		);

		$this->add_control(
			'nothing_found_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'selectors' => [
					'{{WRAPPER}} .digi-products__nothing-found' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'nothing_found_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'selector' => '{{WRAPPER}} .digi-products__nothing-found',
			]
		);

		$this->end_controls_section();
	}

}
