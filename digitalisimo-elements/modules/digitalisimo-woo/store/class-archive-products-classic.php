<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Productos del archivo (clásico) (`digitalisimo-woo-archive-products-classic`): sustituye a «Archive Products
 * (deprecated)» de PRO (`modules/woocommerce/widgets/archive-products-deprecated.php`, mapa
 * `maps/woocommerce-archive-products.php`). Oculto del panel como el de PRO: sólo existe para lo ya guardado. Hereda los
 * Productos propios y repite sus cambios (consulta actual, paginación, 4 filas, mensaje sin resultados).
 */
class Archive_Products_Classic extends Products {

	public function get_name() {
		return 'digitalisimo-woo-archive-products-classic';
	}

	public function get_title() {
		return esc_html__( 'Productos del archivo (clásico)', 'pro-elements' );
	}

	public function get_categories() {
		return [ 'woocommerce-elements-archive' ];
	}

	public function show_in_panel() {
		return false;
	}

	/**
	 * La hoja de los Productos. PRO declaraba también la del archivo, cuyas reglas sólo nombran su widget de archivo nuevo
	 * y no se aplicaban a éste: no se carga.
	 */
	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( \Digitalisimo\Elements\Woo\Registry::style_depends( 'digitalisimo-woo-products' ), parent::get_style_depends() ) ) );
	}

	protected function register_controls() {
		$this->legacy_notice( esc_html__( 'Productos del archivo', 'pro-elements' ) );

		parent::register_controls();

		$this->start_injection( [
			'at' => 'before',
			'of' => 'columns',
		] );

		$this->add_control(
			'wc_notice_do_not_use_customizer',
			[
				'type' => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content' => esc_html__( 'Estos ajustes de diseño sustituyen a los de Apariencia > Personalizar.', 'pro-elements' ),
			]
		);

		$this->end_injection();

		$this->update_control(
			'rows',
			[
				'default' => 4,
			],
			[
				'recursive' => true,
			]
		);

		$this->update_control(
			'paginate',
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
			'query_post_type',
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

	public function render_no_results() {
		echo '<div class="digi-products__nothing-found">' . esc_html( $this->get_settings( 'nothing_found_message' ) ) . '</div>';
	}

	protected function render_widget() {
		add_action( 'woocommerce_shortcode_products_loop_no_results', [ $this, 'render_no_results' ] );

		parent::render_widget();
	}

}
