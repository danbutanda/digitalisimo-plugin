<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Productos (`digitalisimo-woo-products`): sustituye a «Products» de PRO (`modules/woocommerce/widgets/products.php`,
 * mapa `modules/digitalisimo-convert/maps/woocommerce-products.php`). Repite sus controles, los de su base
 * (`Products_Base`, `products-base.php`) y los de consulta (`Products_Trait`) con el mismo nombre, valor por defecto,
 * opciones y condiciones. WooCommerce pinta la lista como en PRO: el atajo `[products]` (`Products_Renderer`) o la
 * consulta actual (`Current_Query_Renderer`), y para relacionados, ventas dirigidas y cruzadas las plantillas de
 * WooCommerce (`woocommerce_related_products()`, `woocommerce_upsell_display()`, `woocommerce_cross_sell_display()`),
 * con los mismos filtros. La lista lleva la rejilla del núcleo (`elementor-grid`); la envoltura, las clases propias de
 * `Products_Base` y `digi-products--heading-show`. Sin script; la hoja reproduce la de PRO.
 *
 * Diferencia con PRO: las secciones de relacionados y ventas no se refrescan solas al cambiar el carrito si
 * `Products_Fragments` no está arrancado (lo arranca `Woo\Module::BOOT`).
 */
class Products extends Products_Base {

	use Products_Trait;

	public function get_name() {
		return 'digitalisimo-woo-products';
	}

	public function get_title() {
		return esc_html__( 'Productos', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-products';
	}

	public function get_keywords() {
		return [ 'woocommerce', 'tienda', 'productos', 'archivo', 'ventas dirigidas', 'ventas cruzadas', 'relacionados', 'shop', 'store', 'product', 'upsells', 'cross-sells', 'related' ];
	}

	public function get_categories() {
		return [ 'woocommerce-elements' ];
	}

	/**
	 * @throws \Exception
	 */
	protected function register_query_section() {
		$this->start_controls_section(
			'section_query',
			[
				'label' => esc_html__( 'Consulta', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_query_controls( self::QUERY_CONTROL_NAME );

		$this->end_controls_section();
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
			]
		);

		$this->add_columns_responsive_control();

		$this->add_control(
			'rows',
			[
				'label' => esc_html__( 'Filas', 'pro-elements' ),
				'type' => Controls_Manager::NUMBER,
				'default' => self::DEFAULT_COLUMNS_AND_ROWS,
				'render_type' => 'template',
				'range' => [
					'px' => [
						'max' => 20,
					],
				],
			]
		);

		$this->add_control(
			'paginate',
			[
				'label' => esc_html__( 'Paginación', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'condition' => [
					self::QUERY_CONTROL_NAME . '_post_type!' => [
						'related_products',
						'upsells',
						'cross_sells',
					],
				],
			]
		);

		$this->add_control(
			'allow_order',
			[
				'label' => esc_html__( 'Permitir ordenar', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'condition' => [
					'paginate' => 'yes',
				],
			]
		);

		$this->add_control(
			'wc_notice_frontpage',
			[
				'type' => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content' => esc_html__( 'Ordenar no está disponible si este widget está en la portada. Sólo se ve en el sitio publicado.', 'pro-elements' ),
				'condition' => [
					'paginate' => 'yes',
					'allow_order' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_result_count',
			[
				'label' => esc_html__( 'Mostrar el número de resultados', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'condition' => [
					'paginate' => 'yes',
				],
			]
		);

		$this->end_controls_section();

		$this->register_query_section();

		$this->start_controls_section(
			'section_products_title',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => self::QUERY_CONTROL_NAME . '_post_type',
							'operator' => '=',
							'value' => 'related_products',
						],
						[
							'name' => self::QUERY_CONTROL_NAME . '_post_type',
							'operator' => '=',
							'value' => 'upsells',
						],
						[
							'name' => self::QUERY_CONTROL_NAME . '_post_type',
							'operator' => '=',
							'value' => 'cross_sells',
						],
					],
				],
			]
		);

		$this->add_control(
			'products_title_show',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default' => '',
				'return_value' => 'show',
				'prefix_class' => 'digi-products--heading-',
			]
		);

		$query_type_strings = [
			'related_products' => esc_html__( 'Productos relacionados', 'pro-elements' ),
			'upsells' => esc_html__( 'También te puede interesar…', 'pro-elements' ),
			'cross_sells' => esc_html__( 'Quizá te interese…', 'pro-elements' ),
		];

		foreach ( $query_type_strings as $query_type => $string ) {
			$this->add_control(
				'products_' . $query_type . '_title_text',
				[
					'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
					'type' => Controls_Manager::TEXT,
					'label_block' => true,
					'placeholder' => $string,
					'default' => $string,
					'dynamic' => [
						'active' => true,
					],
					'condition' => [
						'products_title_show!' => '',
						self::QUERY_CONTROL_NAME . '_post_type' => $query_type,
					],
				]
			);
		}

		$this->add_responsive_control(
			'products_title_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
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
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--products-title-alignment: {{VALUE}};',
				],
				'condition' => [
					'products_title_show!' => '',
				],
			]
		);

		$this->end_controls_section();

		parent::register_controls();

		$this->start_injection( [
			'type' => 'section',
			'at' => 'start',
			'of' => 'section_design_box',
		] );

		$this->start_controls_section(
			'products_title_style',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'products_title_show!' => '',
				],
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => self::QUERY_CONTROL_NAME . '_post_type',
							'operator' => '=',
							'value' => 'related_products',
						],
						[
							'name' => self::QUERY_CONTROL_NAME . '_post_type',
							'operator' => '=',
							'value' => 'upsells',
						],
						[
							'name' => self::QUERY_CONTROL_NAME . '_post_type',
							'operator' => '=',
							'value' => 'cross_sells',
						],
					],
				],
			]
		);

		$this->add_control(
			'products_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--products-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'products_title_typography',
				'selector' => '{{WRAPPER}}.digi-products--heading-show .related-products > h2, {{WRAPPER}}.digi-products--heading-show .upsells > h2, {{WRAPPER}}.digi-products--heading-show .cross-sells > h2',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
			]
		);

		$this->add_responsive_control(
			'products_title_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--products-title-spacing: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->end_injection();
	}

	/** Renderizador de la consulta del widget: la consulta actual o el atajo `[products]` (como PRO). */
	public static function get_shortcode_object( $settings ) {
		if ( 'current_query' === $settings[ self::QUERY_CONTROL_NAME . '_post_type' ] ) {
			return new Current_Query_Renderer( $settings, 'current_query' );
		}
		return new Products_Renderer( $settings, 'products' );
	}

	protected function render_widget() {
		if ( WC()->session ) {
			wc_print_notices();
		}

		$settings          = $this->get_settings_for_display();
		$post_type_setting = $settings[ self::QUERY_CONTROL_NAME . '_post_type' ];

		// Envoltura del botón de compra y de «Ver carrito» con «Alinear los botones automáticamente».
		if ( 'yes' === $settings['automatically_align_buttons'] ) {
			add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'add_to_cart_wrapper' ], 10, 1 );
		}

		if ( 'related_products' === $post_type_setting ) {
			$content = self::get_products_related_content( $settings );
		} elseif ( 'upsells' === $post_type_setting ) {
			$content = self::get_upsells_content( $settings );
		} elseif ( 'cross_sells' === $post_type_setting ) {
			$content = self::get_cross_sells_content( $settings );
		} else {
			// Para `Products_Renderer`, como PRO.
			if ( ! isset( $GLOBALS['post'] ) ) {
				$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
			$shortcode = static::get_shortcode_object( $settings );
			$content   = $shortcode->get_content();
		}

		if ( $content ) {
			$content = str_replace( '<ul class="products', '<ul class="products elementor-grid', $content );
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salida de WooCommerce.
		} elseif ( $this->get_settings_for_display( 'nothing_found_message' ) ) {
			echo '<div class="digi-products__nothing-found">' . esc_html( $this->get_settings_for_display( 'nothing_found_message' ) ) . '</div>';
		}

		if ( 'yes' === $settings['automatically_align_buttons'] ) {
			remove_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'add_to_cart_wrapper' ] );
		}
	}

	/**
	 * Relacionados del producto en curso (`get_products_related_content()` del módulo de WooCommerce de PRO). Como en
	 * PRO, el título de la sección lo da la clave `products_related_title_text`, que el control no guarda
	 * (`products_related_products_title_text`): los relacionados muestran el título de WooCommerce.
	 */
	public static function get_products_related_content( $settings ) {
		global $product;

		$product = wc_get_product();

		if ( ! $product ) {
			return;
		}

		return self::get_product_widget_content( $settings, 'related_products', 'woocommerce_product_related_products_heading', 'products_related_title_text' );
	}

	/** Ventas dirigidas del producto en curso (`get_upsells_content()` de PRO). */
	public static function get_upsells_content( $settings ) {
		return self::get_product_widget_content( $settings, 'upsells', 'woocommerce_product_upsells_products_heading', 'products_upsells_title_text' );
	}

	/** Ventas cruzadas del carrito (`get_cross_sells_content()` de PRO). */
	public static function get_cross_sells_content( $settings ) {
		return self::get_product_widget_content( $settings, 'cross_sells', 'woocommerce_product_cross_sells_products_heading', 'products_cross_sells_title_text' );
	}

	/** Contenido de relacionados y ventas con las plantillas de WooCommerce (`get_product_widget_content()` de PRO). */
	private static function get_product_widget_content( $settings, $type, $title_hook, $title_key = '' ) {
		add_filter( $title_hook, function ( $heading ) use ( $settings, $title_key ) {
			$title_text = isset( $settings[ $title_key ] ) ? $settings[ $title_key ] : '';

			if ( ! empty( $title_text ) ) {
				return $title_text;
			}

			return $heading;
		}, 10, 1 );

		ob_start();

		$args = self::parse_product_widget_args( $settings, $type );

		if ( 'related_products' === $type ) {
			woocommerce_related_products( $args );
		} elseif ( 'upsells' === $type ) {
			woocommerce_upsell_display( $args['limit'], $args['columns'], $args['orderby'], $args['order'] );
		} else {
			// Envoltura `woocommerce` para que la lista tenga su diseño fuera del carrito, como en PRO.
			echo '<div class="woocommerce">';
			woocommerce_cross_sell_display( $args['limit'], $args['columns'], $args['orderby'], $args['order'] );
			echo '</div>';
		}

		$products_html = ob_get_clean();

		if ( $products_html ) {
			$products_html = str_replace( '<ul class="products', '<ul class="products elementor-grid', $products_html );

			return wp_kses_post( $products_html );
		}

		return false;
	}

	/** Argumentos de las plantillas de relacionados y ventas (`parse_product_widget_args()` de PRO). */
	private static function parse_product_widget_args( $settings, $type = 'related_products' ) {
		$limit_key  = 'related_products' === $type ? 'posts_per_page' : 'limit';
		$query_name = self::QUERY_CONTROL_NAME;

		$args = [
			$limit_key => '-1',
			'columns'  => ! empty( $settings['columns'] ) ? $settings['columns'] : 4,
			'orderby'  => ! empty( $settings[ "{$query_name}_orderby" ] ) ? $settings[ "{$query_name}_orderby" ] : 'rand',
			'order'    => ! empty( $settings[ "{$query_name}_order" ] ) ? $settings[ "{$query_name}_order" ] : 'desc',
		];

		if ( ! empty( $settings['rows'] ) ) {
			$args[ $limit_key ] = $args['columns'] * $settings['rows'];
		}

		return $args;
	}
}
