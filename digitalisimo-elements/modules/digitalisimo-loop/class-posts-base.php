<?php
namespace Digitalisimo\Elements\Loop;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Base común de los listados propios de C5 (`docs/pro/c5.md` §1): Entradas, Entradas del archivo y los bucles. Repite
 * `modules/posts/widgets/posts-base.php` de PRO con lo que hereda de `Button_Widget_Trait` y `Pagination_Trait`: las
 * mismas secciones y los mismos controles (nombre, valor por defecto, opciones y condiciones), así que los mapas son
 * `keep`; los selectores, el marcado y los recursos son propios.
 *
 * Para las subclases (Entradas, Entradas del archivo, Cuadrícula y Carrusel de bucle):
 * - `query_posts()` (abstracto) deja la consulta en `$this->query`; `get_query()`, `get_query_name()` (`posts` por
 *   defecto: el grupo de consulta conserva el prefijo `posts_` de PRO) y `get_posts_per_page_value()`.
 * - `register_pagination_section_controls()`: las tres secciones de paginación de PRO (contenido, estilo de números y
 *   estilo de «Cargar más»), con los controles del botón de `Button_Widget_Trait` (`register_button_content_controls()`,
 *   `register_button_style_controls()`) y los mismos `remove_control()` que PRO.
 * - Paginación como PRO: `get_current_page()` (`paged`, `page` o `e-page-<id>`), `get_wp_link_page( $i )`,
 *   `get_posts_nav_link( $limit )`, `is_rest_request()`, `is_allow_to_use_custom_page_option()`,
 *   `current_url_contains_taxonomy_filter()`, `get_base_url()` y `has_multiple_posts_widgets_on_page()` (cuenta los
 *   widgets de PRO y los propios).
 * - `render_pagination()`: el pie (cargador, ancla, botón y mensaje de «Cargar más», o la navegación de números y
 *   anterior/siguiente con enlaces reales); `render_button()` pinta el botón «Cargar más».
 *
 * Marcado del pie (clases propias, compartidas por todos los listados; hoja y script `digitalisimo-loop`):
 *
 *     <span class="digi-loop__spinner">{icono}</span>                       ← sólo con «Cargar más» o infinita y cargador
 *     <div class="digi-loop__anchor" data-page="1" data-max-page="3" data-next-page="URL" data-digi-more="click|infinite"></div>
 *     <div class="digi-loop__more elementor-button-wrapper"><a class="elementor-button elementor-size-sm">
 *       <span class="elementor-button-content-wrapper" role="button" tabindex="0">…</span></a></div>
 *     <div class="digi-loop__message">…</div><span class="digi-loop__status elementor-screen-only" aria-live="polite"></span>
 *     <nav class="digi-loop__pagination" aria-label="Paginación">… .page-numbers …</nav>   ← números y anterior/siguiente
 *
 * El contenedor de los elementos lleva `data-digi-items` (sus hijos directos son los elementos que «Cargar más» añade) y,
 * con mampostería, `data-digi-masonry`. El script (`assets/js/loop.js`) pide la página siguiente (la URL de
 * `data-next-page`, del mismo sitio, como PRO), añade los elementos del mismo widget (`[data-id]`) y lo anuncia en la
 * región viva; con mampostería recoloca los elementos según las columnas calculadas de la rejilla.
 *
 * Derivado de PRO Elements (GPL): `modules/posts/widgets/posts-base.php`, `modules/posts/traits/button-widget-trait.php`,
 * `modules/posts/traits/pagination-trait.php`, `modules/posts/module.php` (`pre_handle_404`) y el pie de
 * `modules/posts/skins/skin-base.php` (`render_loop_footer()`).
 */
abstract class Posts_Base extends \Elementor\Widget_Base {
	const LOAD_MORE_ON_CLICK        = 'load_more_on_click';
	const LOAD_MORE_INFINITE_SCROLL = 'load_more_infinite_scroll';

	/** Hoja y script compartidos (paginación, «Cargar más», infinita y mampostería). */
	const SHARED = 'digitalisimo-loop';

	/** Hoja de la piel «Tarjetas» (`assets/css/posts-cards.css`), sólo con esa piel. */
	const CARDS = 'digitalisimo-posts-cards';

	/** Widgets de PRO que paginan (además de los propios que heredan de esta clase). */
	const PRO_PAGINATED = array( 'posts', 'archive-posts', 'loop-grid', 'loop-carousel', 'woocommerce-products', 'wc-archive-products', 'global' );

	/** @var \WP_Query|null */
	protected $query = null;

	protected $_has_template_content = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- nombre del núcleo.

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * La hoja del widget, la de las tarjetas con una piel `*cards` y, si pagina, la compartida (en el editor, todas).
	 */
	public function get_style_depends(): array {
		$editor = self::is_editor();
		$own    = Module::style_depends( $this->own_assets_name() );
		if ( $editor || '' !== (string) $this->get_settings( 'pagination_type' ) ) {
			array_unshift( $own, self::SHARED );
		}
		if ( $editor || 'cards' === substr( (string) $this->get_settings( '_skin' ), -5 ) ) {
			$own[] = self::CARDS;
		}
		return $own;
	}

	/** El script compartido sólo si hace falta: mampostería, «Cargar más» o infinita (en el editor, siempre). */
	public function get_script_depends(): array {
		return self::is_editor() || $this->needs_script() ? array( self::SHARED ) : array();
	}

	/** Nombre del widget cuyos recursos propios usa (las Entradas del archivo usan los de Entradas). */
	protected function own_assets_name(): string {
		return $this->get_name();
	}

	/** ¿Necesita el script compartido con estos ajustes? */
	protected function needs_script(): bool {
		return in_array( (string) $this->get_settings( 'pagination_type' ), array( self::LOAD_MORE_ON_CLICK, self::LOAD_MORE_INFINITE_SCROLL ), true );
	}

	/** Editor o vista previa del editor (ahí se cargan todos los recursos). */
	public static function is_editor(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && is_object( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && is_object( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	public function get_query() {
		return $this->query;
	}

	/** Nombre del grupo de consulta: `posts`, como PRO (los ajustes guardados son `posts_*`). */
	public function get_query_name() {
		return 'posts';
	}

	/** Entradas por página: el control de la piel actual (`<piel>_posts_per_page`), como PRO. */
	protected function get_posts_per_page_value() {
		$skin = $this->get_current_skin();
		return $skin ? $skin->get_instance_value( 'posts_per_page' ) : $this->get_settings( 'posts_per_page' );
	}

	abstract public function query_posts();

	public function render() {}

	public function render_plain_content() {}

	protected function register_controls() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->end_controls_section();
	}

	protected function get_pagination_type_options() {
		return array(
			''                              => esc_html__( 'Ninguna', 'pro-elements' ),
			'numbers'                       => esc_html__( 'Números', 'pro-elements' ),
			'prev_next'                     => esc_html__( 'Anterior/Siguiente', 'pro-elements' ),
			'numbers_and_prev_next'         => esc_html__( 'Números', 'pro-elements' ) . ' + ' . esc_html__( 'Anterior/Siguiente', 'pro-elements' ),
			self::LOAD_MORE_ON_CLICK        => esc_html__( 'Cargar al pulsar', 'pro-elements' ),
			self::LOAD_MORE_INFINITE_SCROLL => esc_html__( 'Desplazamiento infinito', 'pro-elements' ),
		);
	}

	/**
	 * Versión en Markdown del listado (una línea por entrada: título enlazado y extracto), como
	 * `Render_Posts_Markdown_Trait` de PRO: la consulta no cuenta para «Evitar duplicados».
	 */
	public function render_markdown(): string {
		$module = '\ElementorPro\Modules\QueryControl\Module';
		$before = class_exists( $module ) && property_exists( $module, 'displayed_ids' ) ? $module::$displayed_ids : null;

		$this->query_posts();
		$query = $this->get_query();

		if ( null !== $before ) {
			$module::$displayed_ids = $before;
		}

		if ( ! $query || ! $query->have_posts() ) {
			return '';
		}

		$clean = static function ( $value ) {
			$text = trim( wp_strip_all_tags( (string) $value ) );
			return '' === $text ? '' : str_replace( array( '[', ']' ), array( '\\[', '\\]' ), (string) preg_replace( '/\s+/', ' ', $text ) );
		};
		$lines = array();
		foreach ( $query->posts as $post ) {
			$title = $clean( get_the_title( $post ) );
			if ( '' === $title ) {
				continue;
			}
			$permalink = (string) get_permalink( $post );
			$excerpt   = $clean( get_the_excerpt( $post ) );
			$line      = '' !== $permalink ? '- [' . $title . '](' . esc_url( $permalink ) . ')' : '- ' . $title;
			$lines[]   = '' !== $excerpt ? $line . ' — ' . $excerpt : $line;
		}

		wp_reset_postdata();

		return implode( "\n", $lines );
	}

	/* ------------------------------------------------------------------ Controles del botón (Button_Widget_Trait) */

	public static function get_button_sizes() {
		return array(
			'xs' => esc_html__( 'Muy pequeño', 'pro-elements' ),
			'sm' => esc_html__( 'Pequeño', 'pro-elements' ),
			'md' => esc_html__( 'Mediano', 'pro-elements' ),
			'lg' => esc_html__( 'Grande', 'pro-elements' ),
			'xl' => esc_html__( 'Muy grande', 'pro-elements' ),
		);
	}

	/** Selector del botón «Cargar más» (dentro de su envoltorio: no alcanza a los botones de un elemento de bucle). */
	protected function button_selector(): string {
		return '{{WRAPPER}} .digi-loop__more .elementor-button';
	}

	protected function register_button_content_controls( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'section_condition'      => array(),
				'button_text'            => esc_html__( 'Pulsa aquí', 'pro-elements' ),
				'control_label_name'     => esc_html__( 'Texto', 'pro-elements' ),
				'exclude_inline_options' => array(),
			)
		);
		$button = $this->button_selector();

		$this->add_control(
			'button_type',
			array(
				'label'        => esc_html__( 'Tipo', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => '',
				'options'      => array(
					''        => esc_html__( 'Predeterminado', 'pro-elements' ),
					'info'    => esc_html__( 'Información', 'pro-elements' ),
					'success' => esc_html__( 'Éxito', 'pro-elements' ),
					'warning' => esc_html__( 'Advertencia', 'pro-elements' ),
					'danger'  => esc_html__( 'Peligro', 'pro-elements' ),
				),
				'prefix_class' => 'elementor-button-',
				'condition'    => $args['section_condition'],
			)
		);

		$this->add_control(
			'text',
			array(
				'label'       => $args['control_label_name'],
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => $args['button_text'],
				'placeholder' => $args['button_text'],
				'condition'   => $args['section_condition'],
			)
		);

		$this->add_control(
			'link',
			array(
				'label'     => esc_html__( 'Enlace', 'pro-elements' ),
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'default'   => array( 'url' => '#' ),
				'condition' => $args['section_condition'],
			)
		);

		$this->add_control(
			'size',
			array(
				'label'          => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => 'sm',
				'options'        => self::get_button_sizes(),
				'style_transfer' => true,
				'condition'      => array_merge( $args['section_condition'], array( 'size[value]!' => 'sm' ) ),
			)
		);

		$this->add_control(
			'selected_icon',
			array(
				'label'                  => esc_html__( 'Icono', 'pro-elements' ),
				'type'                   => Controls_Manager::ICONS,
				'fa4compatibility'       => 'icon',
				'skin'                   => 'inline',
				'label_block'            => false,
				'condition'              => $args['section_condition'],
				'exclude_inline_options' => $args['exclude_inline_options'],
			)
		);

		$start = is_rtl() ? 'right' : 'left';
		$end   = is_rtl() ? 'left' : 'right';

		$this->add_control(
			'icon_align',
			array(
				'label'                => esc_html__( 'Posición del icono', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'default'              => is_rtl() ? 'row-reverse' : 'row',
				'options'              => array(
					'row'         => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => "eicon-h-align-{$start}",
					),
					'row-reverse' => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => "eicon-h-align-{$end}",
					),
				),
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'row-reverse' : 'row',
					'right' => is_rtl() ? 'row' : 'row-reverse',
				),
				'selectors'            => array(
					$button . ' .elementor-button-content-wrapper' => 'flex-direction: {{VALUE}};',
				),
				'condition'            => array_merge(
					$args['section_condition'],
					array(
						'text!'                 => '',
						'selected_icon[value]!' => '',
					)
				),
			)
		);

		$this->add_control(
			'icon_indent',
			array(
				'label'      => esc_html__( 'Separación del icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array(
					$button . ' .elementor-button-content-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array_merge(
					$args['section_condition'],
					array(
						'text!'                 => '',
						'selected_icon[value]!' => '',
					)
				),
			)
		);

		$this->add_control(
			'button_css_id',
			array(
				'label'       => esc_html__( 'ID del botón', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
				'default'     => '',
				'title'       => esc_html__( 'Escribe el ID sin la almohadilla, p. ej.: mi-id', 'pro-elements' ),
				'description' => sprintf(
					/* translators: 1: `<code>` de apertura, 2: `</code>` de cierre. */
					esc_html__( 'El ID debe ser único en la página. Admite %1$sA-z 0-9%2$s y guion bajo, sin espacios.', 'pro-elements' ),
					'<code>',
					'</code>'
				),
				'separator'   => 'before',
				'condition'   => $args['section_condition'],
			)
		);
	}

	protected function register_button_style_controls( $args = array() ) {
		$args   = wp_parse_args(
			$args,
			array(
				'section_condition'         => array(),
				'prefix_class'              => 'elementor%s-align-',
				'alignment_default'         => '',
				'content_alignment_default' => '',
			)
		);
		$button = $this->button_selector();
		$hover  = $button . ':hover, ' . $button . ':focus';

		$this->add_responsive_control(
			'align',
			array(
				'label'        => esc_html__( 'Posición', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'    => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'   => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Estirar', 'pro-elements' ),
						'icon'  => 'eicon-h-align-stretch',
					),
				),
				'prefix_class' => $args['prefix_class'],
				'default'      => $args['alignment_default'],
				'condition'    => $args['section_condition'],
			)
		);

		$start = is_rtl() ? 'right' : 'left';
		$end   = is_rtl() ? 'left' : 'right';

		$this->add_responsive_control(
			'content_align',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'start'         => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => "eicon-text-align-{$start}",
					),
					'center'        => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'           => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => "eicon-text-align-{$end}",
					),
					'space-between' => array(
						'title' => esc_html__( 'Repartido', 'pro-elements' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default'   => $args['content_alignment_default'],
				'selectors' => array(
					$button . ' .elementor-button-content-wrapper' => 'justify-content: {{VALUE}};',
				),
				'condition' => array_merge( $args['section_condition'], array( 'align' => 'justify' ) ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'typography',
				'global'    => array( 'default' => 'globals/typography?id=accent' ),
				'selector'  => $button,
				'condition' => $args['section_condition'],
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'      => 'text_shadow',
				'selector'  => $button,
				'condition' => $args['section_condition'],
			)
		);

		$this->start_controls_tabs( 'tabs_button_style', array( 'condition' => $args['section_condition'] ) );

		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label'     => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => $args['section_condition'],
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					$button => 'fill: {{VALUE}}; color: {{VALUE}};',
				),
				'condition' => $args['section_condition'],
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'background',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => $button,
				'fields_options' => array(
					'background' => array( 'default' => 'classic' ),
					'color'      => array( 'global' => array( 'default' => 'globals/colors?id=accent' ) ),
				),
				'condition'      => $args['section_condition'],
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'button_box_shadow',
				'selector'  => $button,
				'condition' => $args['section_condition'],
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label'     => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
				'condition' => $args['section_condition'],
			)
		);

		$this->add_control(
			'hover_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$hover                                              => 'color: {{VALUE}};',
					$button . ':hover svg, ' . $button . ':focus svg' => 'fill: {{VALUE}};',
				),
				'condition' => $args['section_condition'],
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'button_background_hover',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => $hover,
				'fields_options' => array(
					'background' => array( 'default' => 'classic' ),
				),
				'condition'      => $args['section_condition'],
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$hover => 'border-color: {{VALUE}};',
				),
				'condition' => $args['section_condition'],
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'button_hover_box_shadow',
				'selector'  => $hover,
				'condition' => $args['section_condition'],
			)
		);

		$this->add_control(
			'button_hover_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 's' ),
				'selectors'  => array(
					$button => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'hover_animation',
			array(
				'label'     => esc_html__( 'Animación al pasar el cursor', 'pro-elements' ),
				'type'      => Controls_Manager::HOVER_ANIMATION,
				'condition' => $args['section_condition'],
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'border',
				'selector'  => $button,
				'separator' => 'before',
				'condition' => $args['section_condition'],
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					$button => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => $args['section_condition'],
			)
		);

		$this->add_responsive_control(
			'text_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					$button => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
				'condition'  => $args['section_condition'],
			)
		);
	}

	/* ------------------------------------------------------------------ Paginación (controles de Posts_Base) */

	public function register_load_more_button_style_controls() {
		$this->add_control(
			'heading_load_more_style_button',
			array(
				'label'     => esc_html__( 'Botón', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array( 'pagination_type' => 'load_more_on_click' ),
			)
		);

		$this->register_button_style_controls(
			array(
				'section_condition' => array( 'pagination_type' => 'load_more_on_click' ),
				'prefix_class'      => 'digi-loop--more-align-',
				'alignment_default' => 'center',
			)
		);
	}

	public function register_load_more_message_style_controls() {
		$this->add_control(
			'heading_load_more_on_click_no_posts_message',
			array(
				'label'     => esc_html__( 'Mensaje de fin', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'pagination_type' => 'load_more_on_click' ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_control(
			'heading_load_more_on_click_infinity_scroll_no_posts_message',
			array(
				'label'     => esc_html__( 'Mensaje de fin', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array( 'pagination_type' => 'load_more_infinite_scroll' ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'load_more_no_posts_message',
				'selector' => '{{WRAPPER}} .digi-loop__message',
				'global'   => array( 'default' => 'globals/typography?id=secondary' ),
			)
		);

		$this->add_control(
			'load_more_no_posts_message_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}}' => '--digi-loop-message-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'load_more_spinner_color',
			array(
				'label'     => esc_html__( 'Color del cargador', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}}' => '--digi-loop-spinner-color: {{VALUE}};' ),
				'separator' => 'before',
				'condition' => array( 'load_more_spinner[value]!' => '' ),
			)
		);

		$this->add_responsive_control(
			'load_more_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-loop-more-spacing: {{SIZE}}{{UNIT}};' ),
				'separator'  => 'before',
			)
		);
	}

	public function register_pagination_section_controls() {
		$this->start_controls_section(
			'section_pagination',
			array(
				'label'     => esc_html__( 'Paginación', 'pro-elements' ),
				'condition' => array( '_skin!' => array( 'post_taxonomy', 'product_taxonomy' ) ),
			)
		);

		$this->add_control(
			'pagination_type',
			array(
				'label'              => esc_html__( 'Paginación', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '',
				'options'            => $this->get_pagination_type_options(),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pagination_page_limit',
			array(
				'label'     => esc_html__( 'Límite de páginas', 'pro-elements' ),
				'default'   => '5',
				'condition' => array( 'pagination_type!' => array( 'load_more_on_click', 'load_more_infinite_scroll', '' ) ),
			)
		);

		$this->add_control(
			'pagination_numbers_shorten',
			array(
				'label'     => esc_html__( 'Acortar', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => '',
				'condition' => array( 'pagination_type' => array( 'numbers', 'numbers_and_prev_next' ) ),
			)
		);

		$this->add_control(
			'pagination_prev_label',
			array(
				'label'     => esc_html__( 'Texto de «Anterior»', 'pro-elements' ),
				'dynamic'   => array( 'active' => true ),
				'default'   => esc_html__( '&laquo; Anterior', 'pro-elements' ),
				'condition' => array( 'pagination_type' => array( 'prev_next', 'numbers_and_prev_next' ) ),
			)
		);

		$this->add_control(
			'pagination_next_label',
			array(
				'label'     => esc_html__( 'Texto de «Siguiente»', 'pro-elements' ),
				'default'   => esc_html__( 'Siguiente &raquo;', 'pro-elements' ),
				'condition' => array( 'pagination_type' => array( 'prev_next', 'numbers_and_prev_next' ) ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_control(
			'pagination_align',
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
				'default'              => 'center',
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array( '{{WRAPPER}} .digi-loop__pagination' => 'text-align: {{VALUE}};' ),
				'condition'            => array( 'pagination_type!' => array( 'load_more_on_click', 'load_more_infinite_scroll', '' ) ),
			)
		);

		$this->add_control(
			'pagination_individual_divider',
			array(
				'type'      => Controls_Manager::DIVIDER,
				'condition' => array( 'pagination_type' => array( 'numbers', 'numbers_and_prev_next', 'prev_next' ) ),
			)
		);

		$this->add_control(
			'pagination_individual_handle',
			array(
				'label'     => esc_html__( 'Paginación individual', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'default'   => '',
				'condition' => array( 'pagination_type' => array( 'numbers', 'numbers_and_prev_next', 'prev_next' ) ),
			)
		);

		$this->add_control(
			'pagination_individual_handle_message',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Con varios listados en la misma página, actívalo para paginar cada uno por separado. Cambia la estructura de las URL de la página.', 'pro-elements' ),
				'content_classes' => 'elementor-control-field-description',
				'condition'       => array( 'pagination_type' => array( 'numbers', 'numbers_and_prev_next', 'prev_next' ) ),
			)
		);

		$this->add_control(
			'load_more_spinner',
			array(
				'label'                  => esc_html__( 'Cargador', 'pro-elements' ),
				'type'                   => Controls_Manager::ICONS,
				'fa4compatibility'       => 'icon',
				'default'                => array(
					'value'   => 'fas fa-spinner',
					'library' => 'fa-solid',
				),
				'exclude_inline_options' => array( 'svg' ),
				'recommended'            => array( 'fa-solid' => array( 'spinner', 'cog', 'sync', 'sync-alt', 'asterisk', 'circle-notch' ) ),
				'skin'                   => 'inline',
				'label_block'            => false,
				'condition'              => array( 'pagination_type' => array( 'load_more_on_click', 'load_more_infinite_scroll' ) ),
				'frontend_available'     => true,
			)
		);

		$this->add_control(
			'heading_load_more_button',
			array(
				'label'     => esc_html__( 'Botón', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'pagination_type' => 'load_more_on_click' ),
			)
		);

		$this->register_button_content_controls(
			array(
				'button_text'            => esc_html__( 'Cargar más', 'pro-elements' ),
				'control_label_name'     => esc_html__( 'Texto del botón', 'pro-elements' ),
				'section_condition'      => array( 'pagination_type' => 'load_more_on_click' ),
				'exclude_inline_options' => array( 'svg' ),
			)
		);

		$this->remove_control( 'button_type' );
		$this->remove_control( 'link' );
		$this->remove_control( 'size' );

		$this->add_control(
			'heading_load_more_no_posts_message',
			array(
				'label'     => esc_html__( 'Mensaje de fin', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'pagination_type' => array( 'load_more_on_click', 'load_more_infinite_scroll' ) ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_responsive_control(
			'load_more_no_posts_message_align',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
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
				'selectors' => array( '{{WRAPPER}}' => '--digi-loop-message-align: {{VALUE}};' ),
				'condition' => array( 'pagination_type' => array( 'load_more_on_click', 'load_more_infinite_scroll' ) ),
			)
		);

		$this->add_control(
			'load_more_no_posts_message_switcher',
			array(
				'label'     => esc_html__( 'Mensajes personalizados', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => '',
				'condition' => array( 'pagination_type' => array( 'load_more_on_click', 'load_more_infinite_scroll' ) ),
			)
		);

		$this->add_control(
			'load_more_no_posts_custom_message',
			array(
				'label'       => esc_html__( 'Mensaje cuando no hay más', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'No hay más entradas que mostrar', 'pro-elements' ),
				'condition'   => array(
					'pagination_type'                     => array( 'load_more_on_click', 'load_more_infinite_scroll' ),
					'load_more_no_posts_message_switcher' => 'yes',
				),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->end_controls_section();

		// Estilo de los números y de anterior/siguiente.
		$this->start_controls_section(
			'section_pagination_style',
			array(
				'label'     => esc_html__( 'Paginación', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'pagination_type!' => array( 'load_more_on_click', 'load_more_infinite_scroll', '' ) ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'pagination_typography',
				'selector' => '{{WRAPPER}} .digi-loop__pagination',
				'global'   => array( 'default' => 'globals/typography?id=secondary' ),
			)
		);

		$this->add_control(
			'pagination_color_heading',
			array(
				'label'     => esc_html__( 'Colores', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->start_controls_tabs( 'pagination_colors' );

		$this->start_controls_tab( 'pagination_color_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );

		$this->add_control(
			'pagination_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-loop__pagination .page-numbers:not(.dots)' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'pagination_color_hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );

		$this->add_control(
			'pagination_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-loop__pagination a.page-numbers:hover' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'pagination_color_active', array( 'label' => esc_html__( 'Activa', 'pro-elements' ) ) );

		$this->add_control(
			'pagination_active_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-loop__pagination .page-numbers.current' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'pagination_spacing',
			array(
				'label'      => esc_html__( 'Separación entre números', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'separator'  => 'before',
				'default'    => array( 'size' => 10 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-loop__pagination .page-numbers:not(:first-child)' => 'margin-inline-start: calc( {{SIZE}}{{UNIT}}/2 );',
					'{{WRAPPER}} .digi-loop__pagination .page-numbers:not(:last-child)' => 'margin-inline-end: calc( {{SIZE}}{{UNIT}}/2 );',
				),
			)
		);

		$this->add_responsive_control(
			'pagination_spacing_top',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-loop__pagination' => 'margin-top: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->end_controls_section();

		// Estilo de «Cargar al pulsar» y del desplazamiento infinito.
		$this->start_controls_section(
			'section_style',
			array(
				'label'     => esc_html__( 'Paginación', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'pagination_type' => array( 'load_more_on_click', 'load_more_infinite_scroll' ) ),
			)
		);

		$this->register_load_more_button_style_controls();

		$this->register_load_more_message_style_controls();

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ Página actual y enlaces (como PRO) */

	/** Valor de una superglobal, limpio como el de PRO (`Utils::_unstable_get_super_global_value()`). */
	protected static function request_value( array $source, string $key ) {
		if ( ! isset( $source[ $key ] ) ) {
			return null;
		}
		return function_exists( 'wp_kses_post_deep' ) ? wp_kses_post_deep( wp_unslash( $source[ $key ] ) ) : $source[ $key ];
	}

	public function get_current_page() {
		if ( '' === $this->get_settings_for_display( 'pagination_type' ) ) {
			return 1;
		}

		return max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ), (int) self::request_value( $_GET, 'e-page-' . $this->get_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo lectura del número de página.
	}

	public function is_rest_request() {
		$request_uri = (string) self::request_value( $_SERVER, 'REQUEST_URI' );

		return false !== wp_get_referer() && isset( $_SERVER['REQUEST_URI'] ) && ( false !== strpos( $request_uri, 'wp-json' ) || false !== strpos( $request_uri, 'rest_route' ) );
	}

	public function is_allow_to_use_custom_page_option() {
		if ( 'ajax' === $this->get_settings_for_display( 'pagination_load_type' ) || 'yes' === $this->get_settings_for_display( 'pagination_individual_handle' ) ) {
			return true;
		}

		return $this->has_multiple_posts_widgets_on_page();
	}

	public function current_url_contains_taxonomy_filter() {
		return false !== strpos( (string) self::request_value( $_SERVER, 'QUERY_STRING' ), 'e-filter-' );
	}

	public function referer_contains_taxonomy_filter() {
		return false !== strpos( (string) self::request_value( $_SERVER, 'HTTP_REFERER' ), 'e-filter-' );
	}

	protected function format_query_string_concatenation( $input ) {
		if ( false === strpos( $input, '?' ) ) {
			$input = preg_replace( '/&/', '?', $input, 1 );
		}

		return $input;
	}

	/** URL de la página `$i` del listado: la misma que PRO (archivo, singular, paginación individual o REST). */
	public function get_wp_link_page( $i ) {
		if ( ( ! is_singular() || is_front_page() ) && ! $this->is_rest_request() && ! $this->is_allow_to_use_custom_page_option() ) {
			return get_pagenum_link( $i );
		}

		// Como `_wp_link_page()` de wp-includes/post-template.php.
		global $wp_rewrite;
		$post           = get_post();
		$query_args     = array();
		$url            = get_permalink();
		$post_id        = 0;
		$link_unescaped = '';

		if ( $this->is_rest_request() ) {
			$link_unescaped = wp_get_referer();
			$post_id        = url_to_postid( $link_unescaped );

			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
			}

			$url = $this->get_base_url_for_rest_request( $post_id, $url );
		}

		if ( $i > 1 && $post ) {
			if ( '' === get_option( 'permalink_structure' ) || in_array( $post->post_status, array( 'draft', 'pending' ), true ) ) {
				$url = add_query_arg( $this->get_wp_pagination_query_var(), $i, $url );
			} elseif ( get_option( 'show_on_front' ) === 'page' && (int) get_option( 'page_on_front' ) === $post->ID ) {
				$url = trailingslashit( $url ) . user_trailingslashit( "$wp_rewrite->pagination_base/" . $i, 'single_paged' );
			} else {
				$url = trailingslashit( $url ) . user_trailingslashit( $i, 'single_paged' );
			}
		}

		if ( $i > 1 && $this->is_allow_to_use_custom_page_option() ) {
			$url = $this->get_wp_link_page_url_for_custom_page_option( $url, $i, $post_id );
		}

		if ( 1 === (int) $i && $this->is_allow_to_use_custom_page_option() ) {
			$url = $this->get_base_url();
		}

		if ( is_preview() && $post ) {
			$url = $this->get_wp_link_page_url_for_preview( $post, $query_args, $url );
		}

		if ( $this->is_rest_request() ) {
			$url = $this->get_wp_link_page_url_for_rest_request( $url, $link_unescaped );
		}

		if ( ! $this->is_rest_request() && $this->current_url_contains_taxonomy_filter() && ! is_preview() ) {
			$url = $this->get_wp_link_page_url_for_normal_page_load( $url );
		}

		return esc_url( apply_filters( 'elementor/posts/wp_link_page', $url ) );
	}

	protected function get_base_url_for_rest_request( $post_id, $url ) {
		if ( $post_id > 0 ) {
			return get_permalink( $post_id );
		}

		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() && ( $this->current_url_contains_taxonomy_filter() || $this->referer_contains_taxonomy_filter() ) ) {
			$url = $this->is_allow_to_use_custom_page_option() ? get_query_var( 'pagination_base_url' ) : get_query_var( 'pagination_base_url' ) . user_trailingslashit( "$wp_rewrite->pagination_base/", 'single_paged' );
		} else {
			$url = remove_query_arg( 'p', $url );
		}

		return $url;
	}

	protected function get_wp_link_page_url_for_preview( $post, $query_args, $url ) {
		if ( 'draft' === $post->post_status || ! isset( $_GET['preview_id'], $_GET['preview_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $url;
		}

		$query_args['preview_id']    = self::request_value( $_GET, 'preview_id' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query_args['preview_nonce'] = self::request_value( $_GET, 'preview_nonce' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $this->is_rest_request() || ! $this->current_url_contains_taxonomy_filter() ) {
			return get_preview_post_link( $post, $query_args, $url );
		}

		wp_parse_str( htmlspecialchars_decode( (string) self::request_value( $_SERVER, 'QUERY_STRING' ) ), $query_params );

		foreach ( $query_params as $param_key => $param_value ) {
			if ( false !== strpos( $param_key, 'e-filter-' ) ) {
				$query_args[ $param_key ] = $param_value;
			}
		}

		return get_preview_post_link( $post, $query_args, $url );
	}

	protected function get_wp_link_page_url_for_rest_request( $url, $link_unescaped ) {
		$url_components = wp_parse_url( $link_unescaped );
		$query_args     = array();

		if ( isset( $url_components['query'] ) ) {
			wp_parse_str( $url_components['query'], $query_args );
		}

		$url = ! empty( $query_args ) ? $url . '&' . http_build_query( $query_args ) : $url;

		return $this->format_query_string_concatenation( $url );
	}

	protected function get_wp_link_page_url_for_normal_page_load( $url ) {
		wp_parse_str( htmlspecialchars_decode( (string) self::request_value( $_SERVER, 'QUERY_STRING' ) ), $query_params );

		$e_filters = '';

		foreach ( $query_params as $param_key => $param_value ) {
			if ( false !== strpos( $param_key, 'e-filter' ) ) {
				$e_filters .= '&' . $param_key . '=' . $param_value;
			}
		}

		return $this->format_query_string_concatenation( $url . $e_filters );
	}

	private function get_wp_link_page_url_for_custom_page_option( $url, $i, $post_id ) {
		$base_raw_url   = $this->is_rest_request() ? $this->get_base_url_for_rest_request( $post_id, $url ) : $this->get_base_url();
		$pagination_key = 'e-page-' . $this->get_id();

		if ( 'yes' === $this->get_settings_for_display( 'pagination_individual_handle' ) ) {
			$base_raw_url .= $this->get_pagination_query_vars_for_others_individually_paginated_widgets( $pagination_key );
		}

		return $this->format_query_string_concatenation( $base_raw_url . '&' . $pagination_key . '=' . $i );
	}

	private function get_pagination_query_vars_for_others_individually_paginated_widgets( string $pagination_key ): string {
		wp_parse_str( htmlspecialchars_decode( (string) self::request_value( $_SERVER, 'QUERY_STRING' ) ), $query_params );

		$e_page = '';

		foreach ( $query_params as $param_key => $param_value ) {
			if ( false !== strpos( $param_key, 'e-page' ) && $pagination_key !== $param_key ) {
				$e_page .= '&' . $param_key . '=' . $param_value;
			}
		}

		return $e_page;
	}

	private function get_wp_pagination_query_var() {
		if ( '' === get_option( 'permalink_structure' ) && $this->is_posts_page( $this->is_allow_to_use_custom_page_option() ) ) {
			return 'paged';
		}

		return 'page';
	}

	/** ¿Es la página de entradas del blog? (Como `Pagination_Trait::is_posts_page()` de PRO.) */
	private function is_posts_page( $custom_page_option = true ) {
		if ( $custom_page_option ) {
			return ! is_front_page() && is_home();
		}

		$posts_page_id = (int) get_option( 'page_for_posts' );
		$base_url      = get_query_var( 'pagination_base_url' );

		if ( ! empty( $base_url ) ) {
			return url_to_postid( $base_url ) === $posts_page_id;
		}

		return false;
	}

	/** URL base de la vista actual (como `Pagination_Trait::get_base_url()` de PRO). */
	public function get_base_url() {
		if ( is_page() || is_single() ) {
			return get_permalink();
		} elseif ( is_year() ) {
			return get_year_link( get_query_var( 'year' ) );
		} elseif ( is_month() ) {
			return get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
		} elseif ( is_day() ) {
			return get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$queried_object = get_queried_object();
			return get_term_link( $queried_object->term_id, $queried_object->taxonomy );
		} elseif ( is_author() ) {
			return get_author_posts_url( get_the_author_meta( 'ID' ) );
		} elseif ( is_search() ) {
			return get_search_link();
		} elseif ( is_archive() ) {
			return get_post_type_archive_link( get_post_type() );
		} elseif ( is_singular() && 'post' !== get_post_type() && 'page' !== get_post_type() ) {
			$post_type = get_post_type_object( get_post_type() );
			return $post_type && $post_type->has_archive ? get_post_type_archive_link( get_post_type() ) : get_permalink();
		} elseif ( $this->is_posts_page() ) {
			return get_permalink( get_option( 'page_for_posts' ) );
		}

		return home_url( '/' );
	}

	/** Enlaces de anterior y siguiente (desactivados, `span`, en los extremos), como PRO. */
	public function get_posts_nav_link( $page_limit = null ) {
		if ( ! $page_limit ) {
			$page_limit = $this->query->max_num_pages;
		}

		$return            = array();
		$paged             = $this->get_current_page();
		$link_template     = '<a class="page-numbers %s" href="%s">%s</a>';
		$disabled_template = '<span class="page-numbers %s">%s</span>';
		$prev              = wp_kses_post( (string) $this->get_settings_for_display( 'pagination_prev_label' ) );
		$next              = wp_kses_post( (string) $this->get_settings_for_display( 'pagination_next_label' ) );

		if ( $paged > 1 ) {
			$return['prev'] = sprintf( $link_template, 'prev', $this->get_wp_link_page( max( 1, (int) $paged - 1 ) ), $prev );
		} else {
			$return['prev'] = sprintf( $disabled_template, 'prev', $prev );
		}

		$next_page = (int) $paged + 1;

		if ( $next_page <= $page_limit ) {
			$return['next'] = sprintf( $link_template, 'next', $this->get_wp_link_page( $next_page ), $next );
		} else {
			$return['next'] = sprintf( $disabled_template, 'next', $next );
		}

		return $return;
	}

	/* ------------------------------------------------------------------ Varios listados y páginas /2/ */

	/** Nombres de los widgets que paginan: los de PRO y los propios que heredan de esta clase. */
	public static function paginated_widgets(): array {
		$names = self::PRO_PAGINATED;
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			foreach ( (array) \Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $widget ) {
				if ( $widget instanceof self || ( class_exists( '\ElementorPro\Modules\Posts\Widgets\Posts_Base' ) && $widget instanceof \ElementorPro\Modules\Posts\Widgets\Posts_Base ) ) {
					$names[] = $widget->get_name();
				}
			}
		}
		/**
		 * Widgets que cuentan como listados paginados (varios en una página y páginas `/2/` de un singular).
		 *
		 * @param string[] $names Nombres de widget.
		 */
		return array_values( array_unique( (array) apply_filters( 'digitalisimo_elements_loop_paginated_widgets', $names ) ) );
	}

	/** Recorre los elementos de un documento (cualquier profundidad). */
	private static function walk( array $elements, callable $callback ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$callback( $element );
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk( $element['elements'], $callback );
			}
		}
	}

	/** Elementos guardados del documento de la vista actual. */
	private static function document_elements(): array {
		$post_id = get_queried_object_id() ?: get_the_ID();
		if ( ! $post_id || ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return array();
		}
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		return $document ? (array) $document->get_elements_data() : array();
	}

	/** ¿Hay dos o más listados paginables (de PRO o propios) en la página? */
	public function has_multiple_posts_widgets_on_page(): bool {
		$names = self::paginated_widgets();
		$count = 0;
		self::walk(
			self::document_elements(),
			static function ( $element ) use ( &$count, $names ) {
				if ( isset( $element['widgetType'] ) && in_array( $element['widgetType'], $names, true ) ) {
					++$count;
				}
			}
		);
		return $count >= 2;
	}

	/**
	 * `pre_handle_404`: una página `/N/` de un singular cuyo documento tiene un listado propio paginado hasta N no es un
	 * 404 (como `Module::allow_posts_widget_pagination()` de PRO, que sólo reconoce sus widgets).
	 */
	public static function allow_pagination( $handled, $wp_query ) {
		if ( $handled || ! is_object( $wp_query ) || empty( $wp_query->query_vars['page'] ) || ! is_singular() || empty( $wp_query->post ) ) {
			return $handled;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return $handled;
		}
		$document = \Elementor\Plugin::$instance->documents->get( $wp_query->post->ID );
		if ( ! $document ) {
			return $handled;
		}
		$own   = array();
		$valid = false;
		$page  = (int) $wp_query->query_vars['page'];
		if ( isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			foreach ( (array) \Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $widget ) {
				if ( $widget instanceof self ) {
					$own[] = $widget->get_name();
				}
			}
		}
		self::walk(
			(array) $document->get_elements_data(),
			static function ( $element ) use ( &$valid, $own, $page ) {
				if ( ! isset( $element['widgetType'] ) || ! in_array( $element['widgetType'], $own, true ) ) {
					return;
				}
				$type = (string) ( $element['settings']['pagination_type'] ?? '' );
				if ( '' === $type ) {
					$valid = false;
					return;
				}
				$limit = (string) ( $element['settings']['pagination_page_limit'] ?? '' );
				$valid = '' === $limit || in_array( $type, array( self::LOAD_MORE_ON_CLICK, self::LOAD_MORE_INFINITE_SCROLL ), true ) || $page <= (int) $limit;
			}
		);
		return $valid ? true : $handled;
	}

	/* ------------------------------------------------------------------ Pie: paginación y «Cargar más» */

	/**
	 * Pie del listado (después de cerrar el contenedor de los elementos), como el de PRO: cargador, ancla y botón de
	 * «Cargar más» o mensaje de la infinita; o la navegación de números y anterior/siguiente con enlaces reales.
	 */
	public function render_pagination() {
		$settings = $this->get_settings_for_display();

		if ( ! isset( $settings['pagination_type'] ) ) {
			return;
		}

		$type = (string) $settings['pagination_type'];
		$ajax = in_array( $type, array( self::LOAD_MORE_ON_CLICK, self::LOAD_MORE_INFINITE_SCROLL ), true );

		if ( $ajax && ! empty( $settings['load_more_spinner']['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
			echo '<span class="digi-loop__spinner">';
			Icons_Manager::render_icon( $settings['load_more_spinner'], array( 'aria-hidden' => 'true' ) );
			echo '</span>';
		}

		if ( '' === $type || ! $this->query ) {
			return;
		}

		$page_limit = (int) $this->query->max_num_pages;

		if ( '' !== (string) ( $settings['pagination_page_limit'] ?? '' ) && ! $ajax ) {
			$page_limit = min( (int) $settings['pagination_page_limit'], $page_limit );
		}

		if ( 2 > $page_limit ) {
			return;
		}

		$current = (int) $this->get_current_page();

		if ( $ajax ) {
			printf(
				'<div class="digi-loop__anchor" data-page="%1$d" data-max-page="%2$d" data-next-page="%3$s" data-digi-more="%4$s"></div>',
				$current, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- entero.
				(int) $this->query->max_num_pages, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- entero.
				esc_attr( $this->get_wp_link_page( $current + 1 ) ),
				self::LOAD_MORE_ON_CLICK === $type ? 'click' : 'infinite'
			);
			if ( self::LOAD_MORE_ON_CLICK === $type ) {
				$this->render_button();
			}
			$message = (string) ( $settings['load_more_no_posts_custom_message'] ?? '' );
			echo '<div class="digi-loop__message">' . esc_html( $message ) . '</div>';
			printf( '<span class="digi-loop__status elementor-screen-only" aria-live="polite" data-digi-loaded="%s"></span>', esc_attr__( 'Se cargaron %d elementos más.', 'pro-elements' ) );
			return;
		}

		$links       = array();
		$has_numbers = in_array( $type, array( 'numbers', 'numbers_and_prev_next' ), true );

		if ( $has_numbers ) {
			$paginate_args = array(
				'type'               => 'array',
				'current'            => $current,
				'total'              => $page_limit,
				'prev_next'          => false,
				'show_all'           => 'yes' !== ( $settings['pagination_numbers_shorten'] ?? '' ),
				'before_page_number' => '<span class="elementor-screen-only">' . esc_html__( 'Página', 'pro-elements' ) . '</span>',
			);

			if ( is_singular() && ! is_front_page() && ! $this->is_rest_request() ) {
				$paginate_args = $this->get_paginate_args_for_singular_post( $paginate_args );
			}

			if ( is_archive() && $this->current_url_contains_taxonomy_filter() ) {
				$paginate_args = $this->get_paginate_args_for_archive_with_filters( $paginate_args );
			}

			if ( $this->is_rest_request() ) {
				$paginate_args = $this->get_paginate_args_for_rest_request( $paginate_args );
			}

			if ( $this->is_allow_to_use_custom_page_option() ) {
				$paginate_args['format'] = $this->get_pagination_format( $paginate_args );
			}

			$links = (array) paginate_links( $paginate_args );
		}

		if ( in_array( $type, array( 'prev_next', 'numbers_and_prev_next' ), true ) ) {
			$prev_next = $this->get_posts_nav_link( $page_limit );
			array_unshift( $links, $prev_next['prev'] );
			$links[] = $prev_next['next'];
		}

		printf(
			'<nav class="digi-loop__pagination" aria-label="%1$s">%2$s</nav>',
			esc_attr__( 'Paginación', 'pro-elements' ),
			implode( PHP_EOL, $links ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- `paginate_links()` y las etiquetas, ya escapadas.
		);
	}

	protected function get_pagination_format( $paginate_args ) {
		$connector = ! empty( $paginate_args['base'] ) && strpos( $paginate_args['base'], '?' ) ? '&' : '?';
		return $connector . 'e-page-' . $this->get_id() . '=%#%';
	}

	protected function get_paginate_args_for_singular_post( $paginate_args ) {
		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() ) {
			$paginate_args['base']   = trailingslashit( get_permalink() ) . '%_%';
			$paginate_args['format'] = user_trailingslashit( '%#%', 'single_paged' );
		} else {
			$paginate_args['format'] = '?page=%#%';
		}

		return $paginate_args;
	}

	protected function get_paginate_args_for_archive_with_filters( $paginate_args ) {
		global $wp_rewrite;

		if ( ! $wp_rewrite->using_permalinks() ) {
			$paginate_args['format'] = '?page=%#%';
		}

		return $paginate_args;
	}

	protected function get_paginate_args_for_rest_request( $paginate_args ) {
		global $wp_rewrite;

		$link_unescaped = wp_get_referer();
		$url_components = wp_parse_url( $link_unescaped );
		$add_args       = array();

		if ( isset( $url_components['query'] ) ) {
			wp_parse_str( $url_components['query'], $add_args );
		}

		$url_to_post_id      = url_to_postid( $link_unescaped );
		$pagination_base_url = 0 !== $url_to_post_id ? get_permalink( $url_to_post_id ) : get_query_var( 'pagination_base_url' );

		if ( $wp_rewrite->using_permalinks() ) {
			$paginate_args['base']     = trailingslashit( $pagination_base_url ) . '%_%';
			$paginate_args['format']   = user_trailingslashit( '%#%', 'single_paged' );
			$paginate_args['add_args'] = $add_args;

			if ( 0 === $url_to_post_id ) {
				unset( $paginate_args['format'] );
			}
		} else {
			$paginate_args['base']     = $this->is_allow_to_use_custom_page_option() ? $pagination_base_url . '&%_%' : trailingslashit( $pagination_base_url ) . '%_%';
			$paginate_args['format']   = '&page=%#%';
			$paginate_args['add_args'] = $add_args;
		}

		return $paginate_args;
	}

	/**
	 * Botón «Cargar más», dentro de `.digi-loop__more`: el mismo elemento que PRO (`<a class="elementor-button …">` sin
	 * `href` ni `tabindex`, con las clases del botón del núcleo, su icono y su texto), para que los estilos del tema y del
	 * kit le lleguen igual (con Hello, `a:not([href]):not([tabindex])` hereda el color, como en PRO; un `<button>` recibía
	 * en cambio los estilos de botón del tema y del kit). El control para el teclado y los lectores de pantalla es su
	 * contenido (`role="button"`, `tabindex="0"`), que `loop.js` activa con Intro y Espacio: PRO no lo ofrecía y no
	 * cambia lo que se ve.
	 */
	public function render_button() {
		$settings = $this->get_settings_for_display();
		$text     = (string) ( $settings['text'] ?? '' );
		$icon     = $settings['selected_icon'] ?? array();
		$legacy   = (string) ( $this->get_settings( 'icon' ) ?? '' );

		if ( '' === $text && empty( $icon['value'] ) && '' === $legacy ) {
			return;
		}

		$classes = array( 'elementor-button', 'elementor-size-' . ( ! empty( $settings['size'] ) ? $settings['size'] : 'sm' ) );
		if ( ! empty( $settings['hover_animation'] ) ) {
			$classes[] = 'elementor-animation-' . $settings['hover_animation'];
		}
		$id = ! empty( $settings['button_css_id'] ) ? ' id="' . esc_attr( (string) $settings['button_css_id'] ) . '"' : '';

		echo '<div class="digi-loop__more elementor-button-wrapper"><a class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $id . '><span class="elementor-button-content-wrapper" role="button" tabindex="0">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $id escapado.
		if ( ! empty( $icon['value'] ) || '' !== $legacy ) {
			echo '<span class="elementor-button-icon">';
			if ( ! empty( $icon['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
				Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			} else {
				echo '<i class="' . esc_attr( $legacy ) . '" aria-hidden="true"></i>';
			}
			echo '</span>';
		}
		if ( '' !== $text ) {
			echo '<span class="elementor-button-text">' . wp_kses_post( $text ) . '</span>';
		}
		echo '</span></a></div>';
	}
}
