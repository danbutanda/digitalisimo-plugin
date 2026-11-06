<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Descripción del archivo de la tienda (`digitalisimo-woo-archive-description`): sustituye a «Archive Description» de
 * PRO (`modules/woocommerce/widgets/archive-description.php`, GPL; mapa `maps/woocommerce-archive-description.php`).
 * Mismos controles y la misma salida: la acción `woocommerce_archive_description` de WooCommerce (descripción del término
 * en un archivo de categoría o etiqueta, contenido de la página de la tienda), con sus ganchos y filtros. La hoja propia
 * sólo añade el foco visible de los enlaces.
 */
class Archive_Description extends Base {

	public function get_name() {
		return 'digitalisimo-woo-archive-description';
	}

	public function get_title() {
		return esc_html__( 'Descripción del archivo de la tienda', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-description';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'texto', 'descripción', 'categoría', 'producto', 'archivo', 'shop', 'description', 'archive' );
	}

	public function get_categories() {
		return array( 'woocommerce-elements-archive' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_product_description_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'wc_style_warning',
			array(
				'type'       => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => esc_html__( 'El tema y otros plugins suelen afectar al estilo de este widget. Si ves algún problema, prueba con un tema básico y desactiva los plugins relacionados.', 'pro-elements' ),
			)
		);

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
						'title' => esc_html__( 'Final', 'pro-elements' ),
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
					'.woocommerce {{WRAPPER}} .term-description' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => '.woocommerce {{WRAPPER}} .term-description',
			)
		);

		$this->end_controls_section();
	}

	protected function render_widget() {
		do_action( 'woocommerce_archive_description' );
	}
}
