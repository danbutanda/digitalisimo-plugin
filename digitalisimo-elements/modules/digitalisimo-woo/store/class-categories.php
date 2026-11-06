<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Categorías de productos (`digitalisimo-woo-categories`): sustituye a «Product Categories» de PRO
 * (`modules/woocommerce/widgets/categories.php`, GPL; mapa `maps/wc-categories.php`). Repite sus controles con el mismo
 * nombre, valor por defecto, opciones y condiciones (también las columnas de su base), y pinta lo mismo que PRO: el
 * atajo `[product_categories]` de WooCommerce con los mismos atributos, dentro de la rejilla del núcleo
 * (`ul.products elementor-grid` y `elementor-grid-<n>` en el envoltorio). Hoja propia sin JavaScript.
 *
 * Clases propias del envoltorio: `digi-woo-grid digi-woo-wc-products` (control oculto `products_class`, en PRO
 * `elementor-products-grid elementor-wc-products`) y `digi-woo-grid--align-<valor>` (en PRO
 * `elementor-product-loop-item--align-<valor>`).
 */
class Categories extends Base {

	public function get_name() {
		return 'digitalisimo-woo-categories';
	}

	public function get_title() {
		return esc_html__( 'Categorías de productos', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-categories';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'categorías', 'producto', 'shop', 'store', 'categories', 'product' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	/** Categorías de producto para las opciones (sólo con WooCommerce y fuera del front optimizado). */
	private function category_options(): array {
		$optimize = class_exists( '\Elementor\Core\Frontend\Performance' ) && \Elementor\Core\Frontend\Performance::should_optimize_controls();
		if ( $optimize || ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms   = get_terms( array( 'taxonomy' => 'product_cat' ) );
		$options = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$options[ $term->term_id ] = $term->name;
		}
		return $options;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Diseño', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_columns_responsive_control();

		$this->add_control(
			'number',
			array(
				'label'   => esc_html__( 'Número de categorías', 'pro-elements' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => '4',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_filter',
			array(
				'label' => esc_html__( 'Consulta', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'source',
			array(
				'label'       => esc_html__( 'Origen', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					''                      => esc_html__( 'Mostrar todas', 'pro-elements' ),
					'by_id'                 => esc_html__( 'Selección manual', 'pro-elements' ),
					'by_parent'             => esc_html__( 'Por categoría superior', 'pro-elements' ),
					'current_subcategories' => esc_html__( 'Subcategorías de la actual', 'pro-elements' ),
				),
				'label_block' => true,
			)
		);

		$options = $this->category_options();

		$this->add_control(
			'categories',
			array(
				'label'       => esc_html__( 'Categorías', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => $options,
				'default'     => array(),
				'label_block' => true,
				'multiple'    => true,
				'condition'   => array(
					'source' => 'by_id',
				),
			)
		);

		$this->add_control(
			'parent',
			array(
				'label'     => esc_html__( 'Categoría superior', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '0',
				'options'   => array( '0' => esc_html__( 'Sólo el primer nivel', 'pro-elements' ) ) + $options,
				'condition' => array(
					'source' => 'by_parent',
				),
			)
		);

		$this->add_control(
			'hide_empty',
			array(
				'label'     => esc_html__( 'Ocultar vacías', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => '',
				'label_on'  => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_off' => esc_html__( 'Mostrar', 'pro-elements' ),
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => esc_html__( 'Ordenar por', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'name',
				'options' => array(
					'name'        => esc_html__( 'Nombre', 'pro-elements' ),
					'slug'        => esc_html__( 'Slug', 'pro-elements' ),
					'description' => esc_html__( 'Descripción', 'pro-elements' ),
					'count'       => esc_html__( 'Número de productos', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => esc_html__( 'Orden', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'desc',
				'options' => array(
					'asc'  => esc_html__( 'Ascendente', 'pro-elements' ),
					'desc' => esc_html__( 'Descendente', 'pro-elements' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_products_style',
			array(
				'label' => esc_html__( 'Productos', 'pro-elements' ),
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
			'products_class',
			array(
				'type'         => Controls_Manager::HIDDEN,
				'default'      => 'wc-products',
				'prefix_class' => 'digi-woo-grid digi-woo-',
			)
		);

		$this->add_control(
			'column_gap',
			array(
				'label'      => esc_html__( 'Separación entre columnas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 20,
				),
				'range'      => array(
					'px' => array(
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.digi-woo-wc-products  ul.products' => 'grid-column-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'row_gap',
			array(
				'label'      => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 40,
				),
				'range'      => array(
					'px' => array(
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.digi-woo-wc-products  ul.products' => 'grid-row-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'        => esc_html__( 'Alineación', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'   => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'prefix_class' => 'digi-woo-grid--align-',
				'selectors'    => array(
					'{{WRAPPER}} .product' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'heading_image_style',
			array(
				'label'     => esc_html__( 'Imagen', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'image_border',
				'selector' => '{{WRAPPER}} a > img',
			)
		);

		$this->add_responsive_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} a > img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'image_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} a > img' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'heading_title_style',
			array(
				'label'     => esc_html__( 'Título', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .woocommerce .woocommerce-loop-category__title' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .woocommerce .woocommerce-loop-category__title',
			)
		);

		$this->add_control(
			'heading_count_style',
			array(
				'label'     => esc_html__( 'Número de productos', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'count_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .woocommerce-loop-category__title .count' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'count_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .woocommerce-loop-category__title .count',
			)
		);

		$this->end_controls_section();
	}

	/** El atajo `[product_categories]` con los mismos atributos que PRO. */
	private function get_shortcode(): string {
		$settings   = $this->get_settings();
		$attributes = array(
			'number'     => $settings['number'],
			'columns'    => $settings['columns'],
			'hide_empty' => ( 'yes' === $settings['hide_empty'] ) ? 1 : 0,
			'orderby'    => $settings['orderby'],
			'order'      => $settings['order'],
		);
		if ( 'by_id' === $settings['source'] ) {
			$attributes['ids'] = implode( ',', (array) $settings['categories'] );
		} elseif ( 'by_parent' === $settings['source'] ) {
			$attributes['parent'] = $settings['parent'];
		} elseif ( 'current_subcategories' === $settings['source'] ) {
			$attributes['parent'] = get_queried_object_id();
		}
		$this->add_render_attribute( 'shortcode', $attributes );
		return sprintf( '[product_categories %s]', $this->get_render_attribute_string( 'shortcode' ) );
	}

	protected function render_widget() {
		$html = do_shortcode( $this->get_shortcode() );
		if ( $html ) {
			// Plantilla de WooCommerce (los plugins pueden engancharse en ella): se imprime tal cual, como PRO.
			echo str_replace( '<ul class="products', '<ul class="products elementor-grid', $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function render_plain_content() {
		echo wp_kses_post( $this->get_shortcode() );
	}
}
