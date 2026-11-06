<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use ElementorPro\Modules\QueryControl\Controls\Group_Control_Posts;
use ElementorPro\Modules\QueryControl\Module as Query_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Productos (clásico) (`digitalisimo-woo-products-classic`): sustituye a «Woo - Products-» de PRO
 * (`modules/woocommerce/widgets/products-deprecated.php`, mapa `maps/wc-products.php`). Oculto del panel como el de
 * PRO: sólo existe para lo ya guardado. Repite sus controles (número de productos, grupo de consulta `posts` del
 * control de consulta del constructor, filtrar por, orden, exclusiones, evitar duplicados), los de su base
 * (`Products_Base`) y la presentación «Clásico» (`Skin_Classic`, columnas), con la misma consulta y el mismo bucle de
 * WooCommerce (`content-product`).
 */
class Products_Classic extends Products_Base {

	/** @var \WP_Query|null */
	private $query = null;

	protected $_has_template_content = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- nombre del núcleo.

	public function get_name() {
		return 'digitalisimo-woo-products-classic';
	}

	public function get_title() {
		return esc_html__( 'Productos (clásico)', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-products';
	}

	public function get_keywords() {
		return [ 'woocommerce', 'tienda', 'productos', 'bucle', 'consulta', 'shop', 'store', 'loop', 'query', 'product' ];
	}

	public function get_categories() {
		return [ 'woocommerce-elements' ];
	}

	public function show_in_panel() {
		return false;
	}

	/** La hoja de los Productos, como PRO (`widget-woocommerce-products`). */
	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( \Digitalisimo\Elements\Woo\Registry::style_depends( 'digitalisimo-woo-products' ), parent::get_style_depends() ) ) );
	}

	public function on_export( $element ) {
		$element = Group_Control_Posts::on_export_remove_setting_from_element( $element, 'posts' );

		return $element;
	}

	public function get_query() {
		return $this->query;
	}

	protected function register_skins() {
		$this->add_skin( new Skin_Classic( $this ) );
	}

	protected function register_controls() {
		$this->legacy_notice( esc_html__( 'Productos', 'pro-elements' ) );

		$this->start_controls_section(
			'section_layout',
			[
				'label' => esc_html__( 'Diseño', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'posts_per_page',
			[
				'label' => esc_html__( 'Número de productos', 'pro-elements' ),
				'type' => Controls_Manager::NUMBER,
				'default' => '4',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_filter',
			[
				'label' => esc_html__( 'Consulta', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_group_control(
			Group_Control_Posts::get_type(),
			[
				'name' => 'posts',
				'post_type' => 'product',
			]
		);

		$this->add_control(
			'advanced',
			[
				'label' => esc_html__( 'Avanzado', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'filter_by',
			[
				'label' => esc_html__( 'Filtrar por', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Ninguno', 'pro-elements' ),
					'featured' => esc_html__( 'Destacados', 'pro-elements' ),
					'sale' => esc_html__( 'Oferta', 'pro-elements' ),
				],
			]
		);

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

		$this->add_control(
			'exclude',
			[
				'label' => esc_html__( 'Excluir', 'pro-elements' ),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => [
					'current_post' => esc_html__( 'Entrada actual', 'pro-elements' ),
					'manual_selection' => esc_html__( 'Selección manual', 'pro-elements' ),
				],
				'label_block' => true,
			]
		);

		$this->add_control(
			'exclude_ids',
			[
				'label' => esc_html__( 'Buscar y elegir', 'pro-elements' ),
				'type' => Query_Module::QUERY_CONTROL_ID,
				'autocomplete' => [
					'object' => Query_Module::QUERY_OBJECT_POST,
				],
				'options' => [],
				'label_block' => true,
				'multiple' => true,
				'condition' => [
					'exclude' => 'manual_selection',
				],
			]
		);

		$this->add_control(
			'avoid_duplicates',
			[
				'label' => esc_html__( 'Evitar duplicados', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'description' => esc_html__( 'Actívalo para que no se repitan entradas en la página. Sólo afecta al sitio publicado.', 'pro-elements' ),
			]
		);

		$this->end_controls_section();

		parent::register_controls();
	}

	public function query_posts() {
		$settings = $this->get_settings();
		/** @var Module $query_module */
		$query_module = Query_Module::instance();
		$query_args = $query_module->get_query_args( 'posts', $settings );

		// Default ordering args
		$ordering_args = WC()->query->get_catalog_ordering_args( $settings['orderby'], $settings['order'] );

		$query_args['orderby'] = $ordering_args['orderby'];
		$query_args['order'] = $ordering_args['order'];

		if ( ! empty( $ordering_args['meta_key'] ) ) {
			$query_args['meta_key'] = $ordering_args['meta_key'];
		}

		if ( 'sale' === $settings['filter_by'] ) {
			// From WooCommerce `sale_products` shortcode
			$query_args['post__in'] = array_merge( [ 0 ], wc_get_product_ids_on_sale() );
		}

		if ( version_compare( WC()->version, '3.0.0', '>=' ) ) {
			$query_args = $this->get_wc_visibility_parse_query( $query_args );
		} else {
			$query_args = $this->get_wc_legacy_visibility_parse_query( $query_args );
		}

		$this->query = new \WP_Query( $query_args );
	}

	private function get_wc_visibility_parse_query( $query_args ) {
		$settings = $this->get_settings();
		$product_visibility_term_ids = wc_get_product_visibility_term_ids();

		if ( 'featured' === $settings['filter_by'] ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'product_visibility',
				'field' => 'term_taxonomy_id',
				'terms' => $product_visibility_term_ids['featured'],
			];
		}

		return $query_args;
	}

	private function get_wc_legacy_visibility_parse_query( $query_args ) {
		$settings = $this->get_settings();

		$query_args['meta_query'] = [
			[
				'key' => '_visibility',
				'value' => [ 'catalog', 'visible' ],
				'compare' => 'IN',
			],
		];

		if ( 'featured' === $settings['filter_by'] ) {
			// From WooCommerce `featured_products` shortcode
			$query_args['meta_query'][] = [
				'key' => '_featured',
				'value' => 'yes',
			];
		}

		return $query_args;
	}

}
