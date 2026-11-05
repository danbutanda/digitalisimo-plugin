<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Utils;
use ElementorPro\Modules\QueryControl\Module as Query_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Mapa del sitio (`digitalisimo-sitemap`): sustituye al widget «Sitemap» de PRO (`modules/theme-elements/widgets/sitemap.php`,
 * mapa `modules/digitalisimo-convert/maps/sitemap.php`). Repite sus controles con el mismo nombre, valor por defecto,
 * opciones y condiciones; el marcado y la hoja son propios, sin JavaScript.
 *
 * Cada fila del repetidor es una sección con su encabezado (`sitemap_title_tag`, `h2` por defecto) y una lista `<ul>`:
 * - tipo de contenido: `WP_Query` con los mismos argumentos que PRO (publicados, sin protegidos salvo que se pidan,
 *   excluidos, orden) y la lista de `Walker_Page`, en árbol con «Vista jerárquica»;
 * - taxonomía: `wp_list_categories()` con los mismos argumentos que PRO (vacíos ocultos, profundidad, orden).
 * Las clases propias (`digi-sitemap__item digi-sitemap__item--<tipo>`) se anteponen a las de WordPress (`page_item`,
 * `cat-item`, `children`), y «Añadir nofollow» pone `rel="nofollow"` en cada enlace, como PRO.
 *
 * Diferencias declaradas con PRO:
 * - Sin consultas sin límite: PRO pedía todas las entradas (`posts_per_page` -1); aquí el tope es
 *   `digitalisimo_elements_sitemap_limit` (1 000 por defecto, filtrable) y no se cuentan filas ni se precargan términos.
 * - «Excluir → Entrada actual» excluye la entrada en curso (PRO mostraba la opción pero la ignoraba).
 * - El texto de una lista vacía está en español («Ninguno»).
 */
class Sitemap extends \Elementor\Widget_Base {

	/** Tope de entradas por lista (filtro `digitalisimo_elements_sitemap_limit`). */
	const LIMIT = 1000;

	public function get_name() {
		return 'digitalisimo-sitemap';
	}

	public function get_title() {
		return esc_html__( 'Mapa del sitio', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-sitemap';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'mapa del sitio', 'sitemap', 'taxonomía', 'tipo de contenido', 'enlaces', 'árbol', 'site map', 'taxonomy', 'cpt' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'sitemap_section',
			array(
				'label' => esc_html__( 'Mapa del sitio', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->register_post_type_controls();

		$this->add_control(
			'sitemap_layout_divider',
			array(
				'type' => Controls_Manager::DIVIDER,
			)
		);

		$this->register_layout_controls();

		$this->end_controls_section();

		$this->start_controls_section(
			'sitemap_query_section',
			array(
				'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'sitemap_exclude',
			array(
				'label'       => esc_html__( 'Excluir', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => array(
					'current_post'     => esc_html__( 'Entrada actual', 'pro-elements' ),
					'manual_selection' => esc_html__( 'Selección manual', 'pro-elements' ),
				),
				'label_block' => true,
			)
		);

		$this->add_control(
			'sitemap_exclude_ids',
			array(
				'label'        => esc_html__( 'Buscar y elegir', 'pro-elements' ),
				'type'         => Query_Module::QUERY_CONTROL_ID,
				'options'      => array(),
				'label_block'  => true,
				'multiple'     => true,
				'autocomplete' => array(
					'object' => Query_Module::QUERY_OBJECT_POST,
				),
				'condition'    => array(
					'sitemap_exclude' => 'manual_selection',
				),
			)
		);

		$this->add_control(
			'sitemap_password_protected',
			array(
				'label'     => esc_html__( 'Entradas protegidas', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
			)
		);

		$this->end_controls_section();

		$this->register_style_controls();
	}

	private function register_layout_controls() {
		$this->add_responsive_control(
			'sitemap_columns',
			array(
				'label'          => esc_html__( 'Columnas', 'pro-elements' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '4',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'selectors'      => array(
					'{{WRAPPER}} .digi-sitemap__section' => 'flex-basis: calc( 1 / {{VALUE}} * 100% );',
				),
			)
		);

		$this->add_control(
			'sitemap_title_tag',
			array(
				'label'   => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				),
				'default' => 'h2',
			)
		);

		$this->add_control(
			'sitemap_add_nofollow',
			array(
				'label' => esc_html__( 'Añadir nofollow', 'pro-elements' ),
				'type'  => Controls_Manager::SWITCHER,
			)
		);
	}

	private function register_post_type_controls() {
		$supported_taxonomies = array();
		$public_types         = \ElementorPro\Core\Utils::get_public_post_types();

		foreach ( $public_types as $type => $title ) {
			foreach ( get_object_taxonomies( $type, 'objects' ) as $tax ) {
				if ( ! isset( $supported_taxonomies[ $tax->name ] ) ) {
					$supported_taxonomies[ $tax->name ] = $tax->label . ' (' . $tax->name . ')';
				}
			}
		}

		$repeater = new Repeater();

		$repeater->add_control(
			'sitemap_type_selector',
			array(
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'post_type',
				'options' => array(
					'post_type' => esc_html__( 'Tipo de contenido', 'pro-elements' ),
					'taxonomy'  => esc_html__( 'Taxonomía', 'pro-elements' ),
				),
			)
		);

		$repeater->add_control(
			'sitemap_source_post_type',
			array(
				'label'     => esc_html__( 'Origen', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'page',
				'options'   => $public_types,
				'condition' => array(
					'sitemap_type_selector' => 'post_type',
				),
			)
		);

		$repeater->add_control(
			'sitemap_source_taxonomy',
			array(
				'label'     => esc_html__( 'Origen', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'category',
				'options'   => $supported_taxonomies,
				'condition' => array(
					'sitemap_type_selector' => 'taxonomy',
				),
			)
		);

		$repeater->add_control(
			'sitemap_title',
			array(
				'label'   => esc_html__( 'Título', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'sitemap_orderby_post_type',
			array(
				'label'     => esc_html__( 'Ordenar por', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'post_date',
				'options'   => array(
					'post_date'  => esc_html__( 'Fecha', 'pro-elements' ),
					'post_title' => esc_html__( 'Título', 'pro-elements' ),
					'menu_order' => esc_html__( 'Orden del menú', 'pro-elements' ),
					'rand'       => esc_html__( 'Aleatorio', 'pro-elements' ),
				),
				'condition' => array(
					'sitemap_type_selector' => 'post_type',
				),
			)
		);

		$repeater->add_control(
			'sitemap_orderby_taxonomy',
			array(
				'label'     => esc_html__( 'Ordenar por', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'name',
				'options'   => array(
					'id'   => esc_html__( 'ID', 'pro-elements' ),
					'name' => esc_html__( 'Nombre', 'pro-elements' ),
				),
				'condition' => array(
					'sitemap_type_selector' => 'taxonomy',
				),
			)
		);

		$repeater->add_control(
			'sitemap_order',
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

		$repeater->add_control(
			'sitemap_hide_empty',
			array(
				'label'     => esc_html__( 'Ocultar vacíos', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array(
					'sitemap_type_selector' => 'taxonomy',
				),
			)
		);

		$repeater->add_control(
			'sitemap_hierarchical',
			array(
				'label'   => esc_html__( 'Vista jerárquica', 'pro-elements' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'no',
			)
		);

		$repeater->add_control(
			'sitemap_depth',
			array(
				'label'     => esc_html__( 'Profundidad', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '0',
				'options'   => array(
					'0' => esc_html__( 'Todas', 'pro-elements' ),
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'condition' => array(
					'sitemap_hierarchical' => 'yes',
				),
			)
		);

		$this->add_control(
			'sitemap_items',
			array(
				'label'       => '',
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'sitemap_type_selector'    => 'post_type',
						'sitemap_title'            => esc_html__( 'Páginas', 'pro-elements' ),
						'sitemap_source_post_type' => 'page',
					),
					array(
						'sitemap_type_selector'   => 'taxonomy',
						'sitemap_title'           => esc_html__( 'Categorías', 'pro-elements' ),
						'sitemap_source_taxonomy' => 'category',
					),
				),
				'title_field' => '{{{ sitemap_title }}}',
			)
		);
	}

	private function register_style_controls() {
		$this->start_controls_section(
			'section_sitemap_style',
			array(
				'label' => esc_html__( 'Lista', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'sitemap_list_indent',
			array(
				'label'      => esc_html__( 'Sangría', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-sitemap__section ul' => 'margin-inline-start: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'sitemap_section_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-sitemap__section' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'sitemap_title_style',
			array(
				'label'     => esc_html__( 'Título', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'sitemap_title_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-sitemap__title' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'sitemap_title_typography',
				'selector' => '{{WRAPPER}} .digi-sitemap__title',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
			)
		);

		$this->add_control(
			'sitemap_list_style',
			array(
				'label'     => esc_html__( 'Elemento de la lista', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'sitemap_list_item_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-sitemap__item, {{WRAPPER}} span.digi-sitemap__list, {{WRAPPER}} .digi-sitemap__item a' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'sitemap_list_item_typography',
				'selector' => '{{WRAPPER}} .digi-sitemap__item, {{WRAPPER}} span.digi-sitemap__list, {{WRAPPER}} .digi-sitemap__item a',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->add_control(
			'sitemap_bullet_style',
			array(
				'label'     => esc_html__( 'Viñeta', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'sitemap_bullet_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-sitemap__item' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_control(
			'sitemap_list_item_bullet_style',
			array(
				'label'     => esc_html__( 'Estilo', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'disc',
				'options'   => array(
					'disc'   => array(
						'title' => esc_html__( 'Disco', 'pro-elements' ),
						'icon'  => 'eicon-circle',
					),
					'circle' => array(
						'title' => esc_html__( 'Círculo', 'pro-elements' ),
						'icon'  => 'eicon-circle-o',
					),
					'square' => array(
						'title' => esc_html__( 'Cuadrado', 'pro-elements' ),
						'icon'  => 'eicon-square',
					),
					'none'   => array(
						'title' => esc_html__( 'Ninguna', 'pro-elements' ),
						'icon'  => 'eicon-ban',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-sitemap__list, {{WRAPPER}} .digi-sitemap__list .children' => 'list-style-type: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['sitemap_items'] ) || ! is_array( $settings['sitemap_items'] ) ) {
			return;
		}
		$exclude  = (array) ( $settings['sitemap_exclude'] ?? array() );
		$excluded = in_array( 'manual_selection', $exclude, true ) ? array_values( array_filter( array_map( 'absint', (array) ( $settings['sitemap_exclude_ids'] ?? array() ) ) ) ) : array();
		if ( in_array( 'current_post', $exclude, true ) && function_exists( 'is_singular' ) && is_singular() ) {
			$excluded[] = (int) get_queried_object_id();
		}
		$query_args = array(
			'post__not_in' => array_values( array_unique( array_filter( $excluded ) ) ),
			'has_password' => 'yes' === ( $settings['sitemap_password_protected'] ?? '' ) ? null : false,
		);
		$nofollow   = 'yes' === ( $settings['sitemap_add_nofollow'] ?? '' );
		$title_tag  = Utils::validate_html_tag( (string) ( $settings['sitemap_title_tag'] ?? 'h2' ) );

		echo '<div class="digi-sitemap">';
		foreach ( $settings['sitemap_items'] as $item ) {
			echo $this->render_section( is_array( $item ) ? $item : array(), $title_tag, $query_args, $nofollow ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado al construirlo.
		}
		echo '</div>';
	}

	/** Título de la lista: el escrito o el nombre de la taxonomía o del tipo de contenido, como PRO. */
	private static function list_title( string $title, string $type, bool $is_taxonomy ): string {
		if ( '' !== $title ) {
			return $title;
		}
		if ( $is_taxonomy ) {
			$object = get_taxonomy( $type );
			return $object ? (string) $object->label : '';
		}
		$object = get_post_type_object( $type );
		if ( ! $object ) {
			return '';
		}
		return '' === (string) $object->labels->name ? (string) $object->labels->singular_name : (string) $object->labels->name;
	}

	/** Una sección: encabezado y lista (o «Ninguno»). */
	private function render_section( array $item, string $title_tag, array $query_args, bool $nofollow ): string {
		$item        += array(
			'sitemap_type_selector'     => 'post_type',
			'sitemap_source_post_type'  => 'page',
			'sitemap_source_taxonomy'   => 'category',
			'sitemap_title'             => '',
			'sitemap_orderby_post_type' => 'post_date',
			'sitemap_orderby_taxonomy'  => 'name',
			'sitemap_order'             => 'desc',
			'sitemap_hide_empty'        => 'yes',
			'sitemap_hierarchical'      => 'no',
			'sitemap_depth'             => '0',
		);
		$is_taxonomy  = 'taxonomy' === $item['sitemap_type_selector'];
		$type         = sanitize_key( (string) ( $is_taxonomy ? $item['sitemap_source_taxonomy'] : $item['sitemap_source_post_type'] ) );
		$hierarchical = 'yes' === $item['sitemap_hierarchical'];
		$depth        = (int) $item['sitemap_depth'];
		$query_args['orderby'] = (string) $item['sitemap_orderby_post_type'];
		$query_args['order']   = (string) $item['sitemap_order'];
		$classes      = 'digi-sitemap__item digi-sitemap__item--' . $type . ' ';
		$items        = $is_taxonomy ? self::taxonomy_items( $type, $hierarchical, $depth, $item, $query_args ) : self::post_type_items( $type, $hierarchical, $depth, $query_args );
		if ( '' !== $items ) {
			// Como PRO: las clases propias delante de las de WordPress y, con «nofollow», en cada enlace.
			$items = str_replace( 'class="', 'class="' . esc_attr( $classes ), $items );
			if ( $nofollow ) {
				$items = str_replace( 'href=', 'rel="nofollow" href=', $items );
			}
		}
		$title = self::list_title( (string) $item['sitemap_title'], $type, $is_taxonomy );
		$html  = '<div class="digi-sitemap__section">';
		if ( '' !== $title ) {
			$html .= '<' . $title_tag . ' class="digi-sitemap__title digi-sitemap__title--' . esc_attr( $type ) . '">' . wp_kses_post( $title ) . '</' . $title_tag . '>';
		}
		$list_class = 'digi-sitemap__list digi-sitemap__list--' . esc_attr( $type );
		$html      .= '' === $items ? '<span class="' . $list_class . '">' . esc_html__( 'Ninguno', 'pro-elements' ) . '</span>' : '<ul class="' . $list_class . '">' . $items . '</ul>';
		return $html . '</div>';
	}

	/** Términos de una taxonomía con `wp_list_categories()` y los argumentos de PRO. */
	private static function taxonomy_items( string $taxonomy, bool $hierarchical, int $depth, array $item, array $query_args ): string {
		$query_args['hide_empty']       = 'yes' === $item['sitemap_hide_empty'];
		$query_args['show_option_none'] = '';
		$query_args['taxonomy']         = $taxonomy;
		$query_args['title_li']         = '';
		$query_args['echo']             = false;
		$query_args['depth']            = $depth;
		$query_args['hierarchical']     = $hierarchical;
		$query_args['orderby']          = (string) $item['sitemap_orderby_taxonomy'];
		return (string) wp_list_categories( $query_args );
	}

	/** Entradas de un tipo de contenido con los argumentos de PRO, con tope, en la lista de `Walker_Page`. */
	private static function post_type_items( string $post_type, bool $hierarchical, int $depth, array $query_args ): string {
		$limit = (int) apply_filters( 'digitalisimo_elements_sitemap_limit', self::LIMIT, $post_type );
		$query = new \WP_Query(
			array_merge(
				$query_args,
				array(
					'posts_per_page'         => $limit > 0 ? $limit : self::LIMIT,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'post_type'              => $post_type,
					'post_status'            => 'publish',
				)
			)
		);
		if ( ! $query->have_posts() ) {
			return '';
		}
		$walker            = new \Walker_Page();
		$walker->tree_type = $post_type;
		return (string) $walker->walk( $query->posts, $hierarchical ? $depth : -1 );
	}
}
