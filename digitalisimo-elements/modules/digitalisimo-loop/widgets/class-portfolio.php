<?php
namespace Digitalisimo\Elements\Loop\Widgets;

use Digitalisimo\Elements\Loop\Module;
use Digitalisimo\Elements\Loop\Posts_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Portafolio (`digitalisimo-portfolio`): sustituye al widget «Portfolio» de PRO (`modules/posts/widgets/portfolio.php`,
 * mapa `modules/digitalisimo-convert/maps/portfolio.php`). Repite sus controles con el mismo nombre, valor por defecto
 * y condiciones; la consulta es la del constructor (grupo `posts_`, como PRO). Marcado propio:
 *
 *     <ul class="digi-portfolio__filters" aria-label="Filtrar">                      ← con «Mostrar la barra de filtro»
 *       <li><button type="button" class="digi-portfolio__filter" data-filter="__all" aria-pressed="true">Todos</button></li> …
 *     </ul>
 *     <div class="digi-portfolio elementor-grid" role="list" data-digi-items [data-digi-masonry]>
 *       <article class="digi-portfolio__item {post_class()}" role="listitem" data-digi-terms="12 15">
 *         <a class="digi-portfolio__link" href><div class="digi-portfolio__img"><img></div>
 *           <div class="digi-portfolio__overlay"><h3 class="digi-portfolio__title">…</h3></div></a>
 *       </article>
 *     </div>
 *
 * El filtro son botones con `aria-pressed` (PRO: elementos de lista con `tabindex` que el teclado no activaba) y su
 * script (`portfolio.js`, sin jQuery ni la librería de PRO) oculta los elementos de otros términos; la mampostería la
 * hace `loop.js`. Los botones se ven como los elementos de lista de PRO también con los estilos de botón del tema
 * (el `reset.css` de Hello) o del kit: `portfolio.css` los anula con más especificidad. El primer botón dice «Todos»;
 * en lo convertido de PRO (marca `_digitalisimo_pro` del traductor), el «All» de PRO con su msgid y su dominio, así que
 * un sitio migrado conserva el texto (o la traducción del paquete de idioma de PRO). La proporción de la imagen se
 * aplica con CSS.
 *
 * Derivado de PRO Elements (GPL): `modules/posts/widgets/portfolio.php`.
 */
class Portfolio extends \Elementor\Widget_Base {

	/** @var \WP_Query|null */
	private $query = null;

	/** @var array<int, \WP_Term[]> Términos de cada entrada de la consulta (taxonomía del filtro). */
	private $terms = array();

	protected $_has_template_content = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- nombre del núcleo.

	public function get_name() {
		return 'digitalisimo-portfolio';
	}

	public function get_title() {
		return esc_html__( 'Portafolio', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'portafolio', 'portfolio', 'proyectos', 'entradas', 'filtro', 'rejilla', 'consulta', 'tipo de contenido' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	/** `portfolio.js` con la barra de filtro; `loop.js` con la mampostería (en el editor, los dos). */
	public function get_script_depends(): array {
		$editor = Posts_Base::is_editor();
		$out    = array();
		if ( $editor || 'yes' === $this->get_settings( 'masonry' ) ) {
			$out[] = Posts_Base::SHARED;
		}
		if ( $editor || 'yes' === $this->get_settings( 'show_filter_bar' ) ) {
			$out = array_merge( $out, Module::script_depends( $this->get_name() ) );
		}
		return $out;
	}

	public function on_import( $element ) {
		if ( isset( $element['settings']['posts_post_type'] ) && function_exists( 'get_post_type_object' ) && ! get_post_type_object( $element['settings']['posts_post_type'] ) ) {
			$element['settings']['posts_post_type'] = 'post';
		}

		return $element;
	}

	public function get_query() {
		return $this->query;
	}

	public function get_group_name() {
		return 'posts';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'              => esc_html__( 'Columnas', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '3',
				'tablet_default'     => '2',
				'mobile_default'     => '1',
				'options'            => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'prefix_class'       => 'elementor-grid%s-',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'   => esc_html__( 'Entradas por página', 'pro-elements' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 6,
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'         => 'thumbnail_size',
				'exclude'      => array( 'custom' ),
				'default'      => 'medium',
				'prefix_class' => 'digi-portfolio--thumbnail-size-',
			)
		);

		$this->add_control(
			'masonry',
			array(
				'label'              => esc_html__( 'Mampostería', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'condition'          => array( 'columns!' => '1' ),
				'render_type'        => 'ui',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'item_ratio',
			array(
				'label'              => esc_html__( 'Proporción del elemento', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'default'            => array( 'size' => 0.66 ),
				'range'              => array(
					'px' => array(
						'min'  => 0.1,
						'max'  => 2,
						'step' => 0.01,
					),
				),
				'selectors'          => array(
					'{{WRAPPER}} .digi-portfolio__link'     => 'padding-bottom: calc( {{SIZE}} * 100% )',
					'{{WRAPPER}} .digi-portfolio__img'      => 'position: absolute; inset: 0; background-color: rgba(0,0,0,.1);',
					'{{WRAPPER}} .digi-portfolio__img img' => 'position: absolute; top: calc(50% + 1px); left: calc(50% + 1px); height: 100%; object-fit: cover; transform: scale(1.01) translate(-50%, -50%);',
				),
				'condition'          => array( 'masonry' => '' ),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'     => esc_html__( 'Mostrar el título', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
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
				'default'   => 'h3',
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_query',
			array(
				'label' => esc_html__( 'Consulta', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		if ( class_exists( '\ElementorPro\Modules\QueryControl\Controls\Group_Control_Related' ) ) {
			$this->add_group_control(
				\ElementorPro\Modules\QueryControl\Controls\Group_Control_Related::get_type(),
				array(
					'name'    => 'posts',
					'presets' => array( 'full' ),
					'exclude' => array( 'posts_per_page' ),
				)
			);
		}

		$this->end_controls_section();

		$this->start_controls_section(
			'filter_bar',
			array(
				'label' => esc_html__( 'Barra de filtro', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_filter_bar',
			array(
				'label'     => esc_html__( 'Mostrar', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
			)
		);

		$this->add_control(
			'taxonomy',
			array(
				'label'       => esc_html__( 'Taxonomía', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'default'     => array(),
				'options'     => $this->get_taxonomies(),
				'condition'   => array(
					'show_filter_bar'  => 'yes',
					'posts_post_type!' => 'by_id',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_design_layout',
			array(
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// `item_gap` lo sustituyeron `column_gap` y `row_gap`; queda oculto, como en PRO, para los sitios antiguos.
		$this->add_control(
			'item_gap',
			array(
				'label'              => esc_html__( 'Separación', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'          => array( '{{WRAPPER}}' => '--grid-row-gap: {{SIZE}}{{UNIT}}; --grid-column-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px;' ),
				'frontend_available' => true,
				'classes'            => 'elementor-hidden',
			)
		);

		$this->add_control(
			'column_gap',
			array(
				'label'      => esc_html__( 'Separación entre columnas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}}' => ' --grid-column-gap: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_control(
			'row_gap',
			array(
				'label'              => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', 'em', 'rem', 'custom' ),
				'range'              => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'frontend_available' => true,
				// `--digi-masonry-gap` (mampostería, en px como en PRO): `row_gap` se registra después de `item_gap` y gana, como
				// en PRO, donde `item_gap` sólo cuenta si `row_gap` está vacío.
				'selectors'          => array( '{{WRAPPER}}' => '--grid-row-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px' ),
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-portfolio__img, {{WRAPPER}} .digi-portfolio__overlay' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_design_overlay',
			array(
				'label' => esc_html__( 'Capa del elemento', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'color_background',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=accent' ),
				'selectors' => array( '{{WRAPPER}} a .digi-portfolio__overlay' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'color_title',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'separator' => 'before',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} a .digi-portfolio__title' => 'color: {{VALUE}};' ),
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'typography_title',
				'global'    => array( 'default' => 'globals/typography?id=primary' ),
				'selector'  => '{{WRAPPER}} .digi-portfolio__title',
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_design_filter',
			array(
				'label'     => esc_html__( 'Barra de filtro', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_filter_bar' => 'yes' ),
			)
		);

		$this->add_control(
			'color_filter',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=text' ),
				'selectors' => array( '{{WRAPPER}} .digi-portfolio__filter' => 'color: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'color_filter_active',
			array(
				'label'     => esc_html__( 'Color activo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=primary' ),
				'selectors' => array( '{{WRAPPER}} .digi-portfolio__filter[aria-pressed="true"]' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography_filter',
				'global'   => array( 'default' => 'globals/typography?id=primary' ),
				'selector' => '{{WRAPPER}} .digi-portfolio__filter',
			)
		);

		$this->add_control(
			'filter_item_spacing',
			array(
				'label'      => esc_html__( 'Separación entre filtros', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 10 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-portfolio__filters > li:not(:last-child)'  => 'margin-right: calc({{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .digi-portfolio__filters > li:not(:first-child)' => 'margin-left: calc({{SIZE}}{{UNIT}}/2)',
				),
			)
		);

		$this->add_control(
			'filter_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 10 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-portfolio__filters' => 'margin-bottom: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->end_controls_section();
	}

	protected function get_taxonomies() {
		$options = array( '' => '' );
		if ( function_exists( 'get_taxonomies' ) ) {
			foreach ( get_taxonomies( array( 'show_in_nav_menus' => true ), 'objects' ) as $taxonomy ) {
				$options[ $taxonomy->name ] = $taxonomy->label;
			}
		}
		return $options;
	}

	public function query_posts() {
		$query_args = array( 'posts_per_page' => $this->get_settings( 'posts_per_page' ) );

		if ( ! class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
			$this->query = new \WP_Query( $query_args );
			return;
		}

		$this->query = \ElementorPro\Modules\QueryControl\Module::instance()->get_query( $this, 'posts', $query_args, array() );
	}

	/** Términos de cada entrada en la taxonomía del filtro (como `get_posts_tags()` de PRO). */
	protected function collect_terms() {
		$taxonomy    = $this->get_settings( 'taxonomy' );
		$this->terms = array();
		foreach ( $this->query->posts as $post ) {
			$terms = array();
			if ( $taxonomy ) {
				foreach ( (array) wp_get_post_terms( $post->ID, $taxonomy ) as $term ) {
					if ( is_object( $term ) ) {
						$terms[ $term->term_id ] = $term;
					}
				}
			}
			$this->terms[ $post->ID ] = $terms;
		}
	}

	public function render() {
		$this->query_posts();

		$query = $this->get_query();

		if ( ! $query || ! $query->found_posts ) {
			return;
		}

		$this->collect_terms();

		if ( $this->get_settings( 'show_filter_bar' ) ) {
			$this->render_filter_menu();
		}

		$this->add_render_attribute(
			'container',
			array(
				'class'           => array( 'digi-portfolio', 'elementor-grid' ),
				'role'            => 'list',
				'data-digi-items' => '',
			)
		);
		if ( 'yes' === $this->get_settings( 'masonry' ) ) {
			$this->add_render_attribute( 'container', 'data-digi-masonry', '' );
		}

		echo '<div ' . $this->get_render_attribute_string( 'container' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados por el núcleo.

		while ( $query->have_posts() ) {
			$query->the_post();
			$this->render_post();
		}

		echo '</div>';

		wp_reset_postdata();
	}

	/** Barra de filtro: «Todos» (en lo convertido, el «All» de PRO) y los términos de las entradas listadas, por nombre (como PRO). */
	protected function render_filter_menu() {
		if ( ! $this->get_settings( 'taxonomy' ) ) {
			return;
		}

		$terms = array();
		foreach ( $this->terms as $post_terms ) {
			$terms += $post_terms;
		}

		if ( empty( $terms ) ) {
			return;
		}

		usort(
			$terms,
			static function ( $a, $b ) {
				return strcmp( $a->name, $b->name );
			}
		);

		// El primer botón no es un ajuste: en lo convertido de PRO (la marca del traductor, `Translator::MARKER`, que dejan
		// «Migrar» y los kits) dice el «All» de PRO con su msgid y su dominio (o la traducción del paquete de idioma de
		// PRO); en lo nuevo, «Todos», como desde C5.
		$all = '' !== (string) $this->get_settings( '_digitalisimo_pro' )
			? esc_html__( 'All', 'elementor-pro' ) // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- mismo texto y dominio que PRO en lo convertido.
			: esc_html__( 'Todos', 'pro-elements' );

		printf( '<ul class="digi-portfolio__filters" aria-label="%s">', esc_attr__( 'Filtrar', 'pro-elements' ) );
		printf( '<li><button type="button" class="digi-portfolio__filter" data-filter="__all" aria-pressed="true">%s</button></li>', $all ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
		foreach ( $terms as $term ) {
			printf( '<li><button type="button" class="digi-portfolio__filter" data-filter="%1$s" aria-pressed="false">%2$s</button></li>', esc_attr( (string) $term->term_id ), esc_html( $term->name ) );
		}
		echo '</ul>';
	}

	/** Etiqueta `<img>` de la imagen destacada con `wp_get_attachment_image()` y el tamaño elegido. */
	protected function get_thumbnail_html(): string {
		$id = (int) get_post_thumbnail_id();
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			return '';
		}
		$size  = (string) $this->get_settings( 'thumbnail_size_size' );
		$sizes = array_merge( get_intermediate_image_sizes(), array( 'full' ) );
		if ( '' === $size || ! in_array( $size, $sizes, true ) ) {
			$size = 'full';
		}
		return (string) wp_get_attachment_image( $id, $size, false, array( 'class' => "attachment-$size size-$size wp-image-$id" ) );
	}

	protected function render_post() {
		$id    = (int) get_the_ID();
		$terms = array_keys( $this->terms[ $id ] ?? array() );

		printf(
			'<article class="%1$s" role="listitem" data-digi-terms="%2$s"><a class="digi-portfolio__link" href="%3$s"><div class="digi-portfolio__img">%4$s</div><div class="digi-portfolio__overlay">',
			esc_attr( implode( ' ', get_post_class( array( 'digi-portfolio__item' ) ) ) ),
			esc_attr( implode( ' ', $terms ) ),
			esc_url( get_permalink() ),
			$this->get_thumbnail_html() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- `wp_get_attachment_image()`.
		);

		if ( $this->get_settings( 'show_title' ) ) {
			$tag = Utils::validate_html_tag( (string) $this->get_settings( 'title_tag' ) );
			printf( '<%1$s class="digi-portfolio__title">%2$s</%1$s>', $tag, get_the_title() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta validada; título como `the_title()`.
		}

		echo '</div></a></article>';
	}

	public function render_plain_content() {}

	/** Versión en Markdown: una línea por entrada (título enlazado y extracto), como PRO. */
	public function render_markdown(): string {
		$this->query_posts();
		$query = $this->get_query();
		if ( ! $query || ! $query->have_posts() ) {
			return '';
		}
		$lines = array();
		foreach ( $query->posts as $post ) {
			$title = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) get_the_title( $post ) ) ) );
			if ( '' === $title ) {
				continue;
			}
			$title   = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $title );
			$excerpt = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) get_the_excerpt( $post ) ) ) );
			$line    = '- [' . $title . '](' . esc_url( (string) get_permalink( $post ) ) . ')';
			$lines[] = '' !== $excerpt ? $line . ' — ' . str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $excerpt ) : $line;
		}
		wp_reset_postdata();
		return implode( "\n", $lines );
	}
}
