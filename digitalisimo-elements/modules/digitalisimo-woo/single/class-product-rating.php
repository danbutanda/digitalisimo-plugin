<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Valoración del producto (`digitalisimo-woo-product-rating`): sustituye a «Product Rating» de PRO
 * (`modules/woocommerce/widgets/product-rating.php`, mapa `maps/woocommerce-product-rating.php`). Mismos controles,
 * valores por defecto y selectores; las estrellas y el enlace a las reseñas los pinta la plantilla de WooCommerce
 * (`single-product/rating.php`). La alineación usa la clase propia `digi-product-rating--align-*`; la hoja repite la de
 * PRO con la misma especificidad y añade el foco visible del enlace.
 */
class Product_Rating extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-rating';
	}

	public function get_title() {
		return esc_html__( 'Valoración del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-rating';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'valoración', 'reseñas', 'estrellas', 'producto', 'rating', 'review', 'product' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_product_rating_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_style_warning();

		$this->add_control(
			'star_color',
			array(
				'label'     => esc_html__( 'Color de las estrellas', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .star-rating' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'empty_star_color',
			array(
				'label'     => esc_html__( 'Color de las estrellas vacías', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .star-rating::before' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'.woocommerce {{WRAPPER}} .woocommerce-review-link' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => '.woocommerce {{WRAPPER}} .woocommerce-review-link',
			)
		);

		$this->add_control(
			'star_size',
			array(
				'label'      => esc_html__( 'Tamaño de las estrellas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'unit' => 'em',
				),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors'  => array(
					'.woocommerce {{WRAPPER}} .star-rating' => 'font-size: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'space_between',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'unit' => 'em',
				),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors'  => array(
					'.woocommerce:not(.rtl) {{WRAPPER}} .star-rating' => 'margin-right: {{SIZE}}{{UNIT}}',
					'.woocommerce.rtl {{WRAPPER}} .star-rating' => 'margin-left: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label'        => esc_html__( 'Alineación', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'    => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'   => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'prefix_class' => 'digi-product-rating--align-',
			)
		);

		$this->end_controls_section();
	}

	protected function render_widget() {
		if ( ! post_type_supports( 'product', 'comments' ) ) {
			return;
		}

		global $product;
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		wc_get_template( 'single-product/rating.php' );
	}
}
