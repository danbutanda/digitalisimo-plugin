<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Migas de la tienda (`digitalisimo-woo-breadcrumb`): sustituye a «WooCommerce Breadcrumbs» de PRO
 * (`modules/woocommerce/widgets/breadcrumb.php`, GPL; mapa `maps/woocommerce-breadcrumb.php`). Mismos controles y la
 * misma salida: `woocommerce_breadcrumb()` con sus argumentos por defecto y sus filtros (`woocommerce_breadcrumb_defaults`,
 * `woocommerce_get_breadcrumb`…). Ningún JSON-LD propio: los datos estructurados son de WooCommerce o de DIGITALÍSIMO
 * SEO. La hoja propia sólo añade el foco visible de los enlaces.
 */
class Breadcrumb extends Base {

	public function get_name() {
		return 'digitalisimo-woo-breadcrumb';
	}

	public function get_title() {
		return esc_html__( 'Migas de la tienda', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-breadcrumbs';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'migas', 'ruta', 'enlaces internos', 'producto', 'breadcrumbs', 'shop' );
	}

	public function get_categories() {
		return array( 'woocommerce-elements', 'woocommerce-elements-single' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_product_rating_style',
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

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .woocommerce-breadcrumb' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => esc_html__( 'Color de los enlaces', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .woocommerce-breadcrumb > a' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => '{{WRAPPER}} .woocommerce-breadcrumb',
			)
		);

		$this->add_responsive_control(
			'alignment',
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
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array(
					'{{WRAPPER}} .woocommerce-breadcrumb' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render_widget() {
		woocommerce_breadcrumb();
	}
}
