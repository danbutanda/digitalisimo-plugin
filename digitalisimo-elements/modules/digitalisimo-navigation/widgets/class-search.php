<?php
namespace Digitalisimo\Elements\Navigation\Widgets;

use Digitalisimo\Elements\Navigation\Module;
use Digitalisimo\Elements\Navigation\Search_Rest;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Utils;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Búsqueda (`digitalisimo-search`, C3 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c3.md`, frente F): sustituye
 * a la «Search» de PRO (`modules/search/widgets/search.php`, mapa `modules/digitalisimo-convert/maps/search.php`).
 *
 * - **Controles**: los del widget de PRO con el mismo nombre, tipo, valor por defecto, condiciones y sección, también el
 *   grupo de consulta (`search_query`, el `Group_Control_Query` de las funciones avanzadas) y la plantilla de bucle
 *   (`template_id`, `Template_Query`). Estructura derivada de PRO Elements (GPL); textos, selectores, marcado y recursos
 *   propios (variables `--dgs-*`).
 * - **Marcado**: `<search role="search">` con un formulario `GET` a la portada (`s`), etiqueta asociada al campo, botón
 *   «Borrar» de verdad (`button`, 24 × 24 px, sólo visible con texto) y botón de envío. El campo oculto `e_search_props`
 *   (`<id del widget>-<documento>`) se conserva: el módulo de búsqueda de las funciones avanzadas aplica con él la
 *   consulta del widget a la página de resultados (`pre_get_posts`), como con el de PRO.
 * - **Resultados en vivo** (con «Resultados en vivo» y una plantilla de bucle): `assets/js/search.js` los pide a la REST
 *   del propio sitio (`Search_Rest`, `digitalisimo-elements/v1/search`), que pinta cada resultado con la plantilla de
 *   bucle, la misma consulta y la paginación de PRO; región con nombre, estado anunciado (`role="status"`), flechas
 *   arriba y abajo entre resultados, Escape cierra y devuelve el foco al campo, clic fuera cierra. Ninguna petición a
 *   terceros.
 * - **Recursos**: hoja `assets/css/search.css` y el script sólo si hay resultados en vivo o icono de borrar.
 */
class Search extends Widget_Base {
	const NAME = 'digitalisimo-search';

	/** Grupo de consulta (el mismo prefijo que PRO: `search_query_*`). */
	const QUERY_GROUP = 'ElementorPro\\Modules\\QueryControl\\Controls\\Group_Control_Query';

	const TEMPLATE_QUERY = 'ElementorPro\\Modules\\QueryControl\\Controls\\Template_Query';

	/** Indicador de carga (el dibujo de PRO, oculto a los lectores de pantalla: el estado se anuncia aparte). */
	const LOADER = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28" aria-hidden="true"><path fill-rule="evenodd" d="M14 .188c.587 0 1.063.475 1.063 1.062V5.5a1.063 1.063 0 0 1-2.126 0V1.25c0-.587.476-1.063 1.063-1.063ZM4.182 4.181a1.063 1.063 0 0 1 1.503 0L8.73 7.228A1.062 1.062 0 1 1 7.228 8.73L4.182 5.685a1.063 1.063 0 0 1 0-1.503Zm19.636 0a1.063 1.063 0 0 1 0 1.503L20.772 8.73a1.062 1.062 0 1 1-1.502-1.502l3.045-3.046a1.063 1.063 0 0 1 1.503 0ZM.188 14c0-.587.475-1.063 1.062-1.063H5.5a1.063 1.063 0 0 1 0 2.126H1.25A1.063 1.063 0 0 1 .187 14Zm21.25 0c0-.587.475-1.063 1.062-1.063h4.25a1.063 1.063 0 0 1 0 2.126H22.5A1.063 1.063 0 0 1 21.437 14ZM8.73 19.27a1.062 1.062 0 0 1 0 1.502l-3.045 3.046a1.063 1.063 0 0 1-1.503-1.503l3.046-3.046a1.063 1.063 0 0 1 1.502 0Zm10.54 0a1.063 1.063 0 0 1 1.502 0l3.046 3.045a1.063 1.063 0 0 1-1.503 1.503l-3.046-3.046a1.063 1.063 0 0 1 0-1.502ZM14 21.438c.587 0 1.063.475 1.063 1.062v4.25a1.063 1.063 0 0 1-2.126 0V22.5c0-.587.476-1.063 1.063-1.063Z"/></svg>';

	/** Selector de la caja del campo con el foco (PRO: `.e-focus` en el envoltorio). */
	const FOCUS = '{{WRAPPER}}:has(.digi-search__input:focus)';

	protected $query = null;

	protected $search_term = '';

	protected $page_number = 1;

	private $breakpoint = null;

	public function set_search_term( string $search_term ) {
		$this->search_term = $search_term;
	}

	public function set_page_number( int $page_number ) {
		$this->page_number = max( 1, $page_number );
	}

	public function set_breakpoint( ?string $breakpoint ) {
		$this->breakpoint = empty( $breakpoint ) ? null : sanitize_key( $breakpoint );
	}

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Búsqueda', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-site-search';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'búsqueda', 'buscar', 'buscador', 'search', 'resultados', 'en vivo', 'digitalisimo' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( self::NAME );
	}

	public function get_script_depends(): array {
		return Module::script_depends( self::NAME );
	}

	/** Nombre de la consulta, como PRO: el prefijo de sus ajustes es `search_query`. */
	public function get_query_name() {
		return 'search';
	}

	public function get_query() {
		if ( null === $this->query ) {
			$this->query_posts();
		}
		return $this->query;
	}

	public function query_posts() {
		$this->query = $this->get_query_args();
	}

	/** La consulta del widget (`WP_Query`), con el término y la página, por el módulo de consultas de PRO. */
	public function get_query_args() {
		$settings = $this->get_settings_for_display();
		$args     = array(
			'posts_per_page' => $this->get_posts_per_page_for_breakpoint( (array) $settings ),
			'paged'          => $this->page_number,
		);
		if ( '' !== $this->search_term ) {
			$args['s'] = $this->search_term;
		}
		$module = 'ElementorPro\\Modules\\QueryControl\\Module';
		if ( class_exists( $module ) && method_exists( $module, 'instance' ) ) {
			return $module::instance()->get_query( $this, $this->get_query_name() . '_query', $args, array() );
		}
		return new \WP_Query( $args + array( 'post_type' => 'any', 'post_status' => 'publish' ) );
	}

	protected function register_controls() {
		$this->register_content_section_search_field();
		$this->register_content_section_results();
		$this->register_content_section_query();
		$this->register_content_section_additional_settings();
		$this->register_style_section_search_field();
		$this->register_style_section_clear();
		$this->register_style_section_submit();
		$this->register_style_section_results();
		$this->register_style_section_additional_settings();
		$this->register_style_section_nothing_found_message();
	}

	protected function register_content_section_search_field() {
		$this->start_controls_section( 'content_section_search_field', array( 'label' => esc_html__( 'Campo de búsqueda', 'pro-elements' ) ) );

		$this->add_control(
			'heading_input',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => esc_html__( 'Campo', 'pro-elements' ),
			)
		);

		$this->add_control(
			'search_input_placeholder_text',
			array(
				'label'       => esc_html__( 'Texto de ejemplo', 'pro-elements' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Escribe para buscar…', 'pro-elements' ),
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
			)
		);

		$this->add_control(
			'icon_search',
			array(
				'label'            => esc_html__( 'Icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'label_block'      => false,
			)
		);

		$this->add_control(
			'autocomplete',
			array(
				'type'         => Controls_Manager::SWITCHER,
				'label'        => esc_html__( 'Autocompletar del navegador', 'pro-elements' ),
				'label_on'     => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'    => esc_html__( 'No', 'pro-elements' ),
				'separator'    => 'before',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'heading_clear',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Borrar', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'icon_clear',
			array(
				'label'            => esc_html__( 'Icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'skin_settings'    => array(
					'inline' => array(
						'icon' => array( 'icon' => 'eicon-close' ),
					),
				),
				'label_block'      => false,
				'default'          => array(
					'value'   => 'fas fa-times',
					'library' => 'fa-solid',
				),
			)
		);

		$this->add_control(
			'heading_submit',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Enviar', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'submit_trigger',
			array(
				'label'              => esc_html__( 'Cómo se envía', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'click_submit' => esc_html__( 'Botón de envío', 'pro-elements' ),
					'key_enter'    => esc_html__( 'Tecla Intro', 'pro-elements' ),
					'both'         => esc_html__( 'Las dos', 'pro-elements' ),
				),
				'default'            => 'click_submit',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'submit_button_text',
			array(
				'label'       => esc_html__( 'Texto', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Buscar', 'pro-elements' ),
				'placeholder' => esc_html__( 'Buscar', 'pro-elements' ),
				'condition'   => array( 'submit_trigger!' => 'key_enter' ),
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
			)
		);

		$this->add_control(
			'icon_submit',
			array(
				'label'            => esc_html__( 'Icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'label_block'      => false,
				'condition'        => array( 'submit_trigger!' => 'key_enter' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_content_section_results() {
		$this->start_controls_section( 'content_section_results', array( 'label' => esc_html__( 'Resultados', 'pro-elements' ) ) );

		$this->add_control(
			'live_results',
			array(
				'label'              => esc_html__( 'Resultados en vivo', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_off'          => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'           => esc_html__( 'Mostrar', 'pro-elements' ),
				'default'            => '',
				'frontend_available' => true,
			)
		);

		$template = array(
			'label'              => esc_html__( 'Elige una plantilla de bucle', 'pro-elements' ),
			'type'               => class_exists( self::TEMPLATE_QUERY ) ? constant( self::TEMPLATE_QUERY . '::CONTROL_ID' ) : Controls_Manager::NUMBER,
			'label_block'        => true,
			'condition'          => array( 'live_results' => 'yes' ),
			'frontend_available' => true,
		);
		if ( class_exists( self::TEMPLATE_QUERY ) ) {
			$template['autocomplete'] = array(
				'object' => 'library_template',
				'query'  => array(
					'post_status' => 'publish',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- consulta del panel, como PRO.
						array(
							'key'     => '_elementor_template_type',
							'value'   => 'loop-item',
							'compare' => 'IN',
						),
					),
				),
			);
			$template['actions']      = array(
				'new'  => array(
					'visible'         => true,
					'document_config' => array( 'type' => 'loop-item' ),
					'after_action'    => 'redirect',
				),
				'edit' => array(
					'visible'      => true,
					'after_action' => 'redirect',
				),
			);
		}
		$this->add_control( 'template_id', $template );

		$this->add_control(
			'minimum_search_characters',
			array(
				'label'              => esc_html__( 'Caracteres mínimos para buscar', 'pro-elements' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 3,
				'min'                => 1,
				'condition'          => array(
					'live_results'  => 'yes',
					'template_id!' => '',
				),
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'number_of_columns',
			array(
				'label'     => esc_html__( 'Columnas', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 1,
				'min'       => 1,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-cols: {{VALUE}}',
				),
				'condition' => array(
					'live_results'  => 'yes',
					'template_id!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'number_of_items',
			array(
				'label'     => esc_html__( 'Resultados por página', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 6,
				'min'       => 1,
				'condition' => array(
					'live_results'  => 'yes',
					'template_id!' => '',
				),
			)
		);

		$this->add_control(
			'equal_height',
			array(
				'label'     => esc_html__( 'Misma altura', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'default'   => '',
				'condition' => array(
					'live_results'  => 'yes',
					'template_id!' => '',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-rows: 1fr; --dgs-eqh: 100%',
				),
			)
		);

		$this->add_control(
			'enable_loader',
			array(
				'label'     => esc_html__( 'Indicador de carga', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'condition' => array(
					'live_results'  => 'yes',
					'template_id!' => '',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'enable_nothing_found_message',
			array(
				'label'     => esc_html__( 'Mensaje sin resultados', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'default'   => 'yes',
				'condition' => array(
					'live_results'  => 'yes',
					'template_id!' => '',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'nothing_found_message_description',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Se muestra cuando la búsqueda no encuentra nada.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => $this->get_nothing_found_conditions(),
			)
		);

		$this->add_control(
			'nothing_found_message_text',
			array(
				'type'      => Controls_Manager::TEXTAREA,
				'dynamic'   => array( 'active' => true ),
				'condition' => $this->get_nothing_found_conditions(),
				'default'   => esc_html__( 'No encontramos lo que buscas.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'nothing_found_message_html_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
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
				),
				'default'   => 'div',
				'condition' => $this->get_nothing_found_conditions(),
			)
		);

		$this->end_controls_section();
	}

	protected function register_content_section_query() {
		$this->start_controls_section( 'content_section_query', array( 'label' => esc_html__( 'Consulta', 'pro-elements' ) ) );

		if ( class_exists( self::QUERY_GROUP ) ) {
			$this->add_group_control(
				call_user_func( array( self::QUERY_GROUP, 'get_type' ) ),
				array(
					'name'           => 'search_query',
					'presets'        => array( 'full' ),
					'fields_options' => array(
						'post_type' => array(
							'default' => 'any',
							'options' => $this->get_supported_post_types(),
						),
						'exclude'   => array(
							'options' => array(
								'terms'            => esc_html__( 'Término', 'pro-elements' ),
								'authors'          => esc_html__( 'Autor', 'pro-elements' ),
								'manual_selection' => esc_html__( 'Selección manual', 'pro-elements' ),
							),
						),
					),
					'exclude'        => array(
						'avoid_duplicates',
						'offset',
						'posts_per_page',
					),
				)
			);
		}

		$this->end_controls_section();
	}

	protected function register_content_section_additional_settings() {
		$this->start_controls_section( 'content_section_additional_settings', array( 'label' => esc_html__( 'Ajustes adicionales', 'pro-elements' ) ) );

		$this->add_control(
			'heading_pagination',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => esc_html__( 'Paginación', 'pro-elements' ),
			)
		);

		$this->add_control(
			'pagination_type_options',
			array(
				'label'              => esc_html__( 'Tipo', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'none'                  => esc_html__( 'Ninguna', 'pro-elements' ),
					'numbers'               => esc_html__( 'Números', 'pro-elements' ),
					'previous_next'         => esc_html__( 'Anterior y siguiente', 'pro-elements' ),
					'numbers_previous_next' => esc_html__( 'Números, anterior y siguiente', 'pro-elements' ),
				),
				'default'            => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pagination_prev_label',
			array(
				'label'     => esc_html__( 'Texto de «anterior»', 'pro-elements' ),
				'dynamic'   => array( 'active' => true ),
				'default'   => esc_html__( 'Anterior', 'pro-elements' ),
				'condition' => array(
					'pagination_type_options' => array( 'previous_next', 'numbers_previous_next' ),
				),
			)
		);

		$this->add_control(
			'pagination_next_label',
			array(
				'label'     => esc_html__( 'Texto de «siguiente»', 'pro-elements' ),
				'default'   => esc_html__( 'Siguiente', 'pro-elements' ),
				'condition' => array(
					'pagination_type_options' => array( 'previous_next', 'numbers_previous_next' ),
				),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_control(
			'page_limit_settings',
			array(
				'label'              => esc_html__( 'Límite de páginas', 'pro-elements' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 5,
				'frontend_available' => true,
				'condition'          => array(
					'pagination_type_options' => array( 'numbers_previous_next', 'numbers', 'previous_next' ),
				),
			)
		);

		$this->add_control(
			'pagination_shorten_settings',
			array(
				'type'         => Controls_Manager::SWITCHER,
				'label'        => esc_html__( 'Acortar', 'pro-elements' ),
				'label_on'     => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'    => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'yes',
				'condition'    => array(
					'pagination_type_options' => array( 'numbers_previous_next', 'numbers' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function get_nothing_found_conditions() {
		return array(
			'enable_nothing_found_message' => 'yes',
			'live_results'                 => 'yes',
			'template_id!'                 => '',
		);
	}

	protected function register_style_section_search_field() {
		$this->start_controls_section(
			'section_search_field_style',
			array(
				'label' => esc_html__( 'Campo de búsqueda', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'search_field_typography',
				'selector' => '{{WRAPPER}} .digi-search__input',
			)
		);

		$this->add_control(
			'placeholder_color',
			array(
				'label'     => esc_html__( 'Color del texto de ejemplo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-ph: {{VALUE}};',
				),
			)
		);

		$this->start_controls_tabs( 'search_field_tabs' );

		$this->start_controls_tab( 'search_field_tab_input_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->register_search_field_style_tabs( 'normal' );
		$this->end_controls_tab();

		$this->start_controls_tab( 'search_field_tab_input_focus', array( 'label' => esc_html__( 'Con el foco', 'pro-elements' ) ) );
		$this->register_search_field_style_tabs( 'focus' );

		$this->add_control(
			'search_field_search_input_transition',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 's' ),
				'range'      => array(
					's'  => array( 'max' => 5 ),
					'ms' => array( 'max' => 5000 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-tr: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'search_field_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-search__input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'search_field_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-pt: {{TOP}}{{UNIT}}; --dgs-ps: {{LEFT}}{{UNIT}}; --dgs-pb: {{BOTTOM}}{{UNIT}}; --dgs-pe: {{RIGHT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'search_field_icon_label_size',
			array(
				'label'      => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-is: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
				'condition'  => array( 'icon_search[value]!' => '' ),
			)
		);

		$this->add_responsive_control(
			'search_field_icon_gap',
			array(
				'label'      => esc_html__( 'Separación entre el texto y el icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw' ),
				'range'      => self::range_50(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-gap: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array( 'icon_search[value]!' => '' ),
			)
		);

		$this->add_responsive_control(
			'search_field_submit_gap',
			array(
				'label'      => esc_html__( 'Separación entre el campo y el botón', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw' ),
				'range'      => self::range_50(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-sms: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	/** Rango de 0–50 px / 0–5 em / 0–5 rem / 0–50 vw de PRO. */
	private static function range_50(): array {
		return array(
			'px'  => array( 'max' => 50 ),
			'em'  => array( 'max' => 5 ),
			'rem' => array( 'max' => 5 ),
			'vw'  => array( 'max' => 50 ),
		);
	}

	/** Rango de 0–200 px / 0–20 em / 0–20 rem de PRO. */
	private static function range_200(): array {
		return array(
			'px'  => array( 'max' => 200 ),
			'em'  => array( 'max' => 20 ),
			'rem' => array( 'max' => 20 ),
		);
	}

	/** Rango de 0–5 s / 0–5000 ms de PRO. */
	private static function range_time(): array {
		return array(
			's'  => array( 'max' => 5 ),
			'ms' => array( 'max' => 5000 ),
		);
	}

	protected function register_search_field_style_tabs( $tab_id ) {
		$is_normal = 'normal' === $tab_id;
		$wrapper   = $is_normal ? '{{WRAPPER}}' : self::FOCUS;
		$input     = $is_normal ? '{{WRAPPER}} .digi-search__input' : '{{WRAPPER}} .digi-search__input:focus';

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => "search_field_background_$tab_id",
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => $input,
				'fields_options' => array(
					'color' => array( 'label' => esc_html__( 'Fondo', 'pro-elements' ) ),
				),
			)
		);

		$this->add_control(
			"search_field_input_text_color_$tab_id",
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_TEXT ),
				'selectors' => array(
					$wrapper => '--dgs-c: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			"search_field_icon_search_normal_color_$tab_id",
			array(
				'label'     => esc_html__( 'Color del icono', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$wrapper => '--dgs-ic: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => "search_field_border_$tab_id",
				'selector' => $input,
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => "search_field_box_shadow__$tab_id",
				'selector' => $input,
			)
		);
	}

	protected function register_style_section_clear() {
		$this->start_controls_section(
			'style_section_clear',
			array(
				'label'     => esc_html__( 'Borrar', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'icon_clear[value]!' => '' ),
			)
		);

		$this->add_responsive_control(
			'icon_clear_size',
			array(
				'label'      => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => self::range_50(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-xs: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'icon_clear_color_tabs' );

		$this->start_controls_tab( 'icon_clear_normal_tab', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );

		$this->add_control(
			'icon_clear_normal_color',
			array(
				'label'     => esc_html__( 'Color del icono', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-xc: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'icon_clear_hover_tab', array( 'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ) ) );

		$this->add_control(
			'icon_clear_hover_color',
			array(
				'label'     => esc_html__( 'Color del icono', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search__clear:hover' => '--dgs-xc: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_clear_hover_transition',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 's' ),
				'range'      => self::range_time(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-xt: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	protected function register_style_section_additional_settings() {
		$this->start_controls_section(
			'style_additional_settings',
			array(
				'label'     => esc_html__( 'Ajustes adicionales', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'pagination_type_options' => array( 'numbers_previous_next', 'previous_next', 'numbers' ),
				),
			)
		);

		$this->add_control(
			'heading_style_additional_settings',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => esc_html__( 'Paginación', 'pro-elements' ),
			)
		);

		$this->add_control(
			'style_additional_settings_alignment',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'     => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'toggle'    => true,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-pj: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'style_additional_settings_vertical_position',
			array(
				'label'                => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'top'    => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon'  => 'eicon-v-align-top',
					),
					'bottom' => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => array(
					'top'    => '--dgs-pd: column-reverse',
					'bottom' => '--dgs-pd: column',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography_title',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_PRIMARY ),
				'selector' => '{{WRAPPER}} .digi-search__pages',
			)
		);

		$this->add_control(
			'heading_style_additional_settings_colors',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Colores', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->start_controls_tabs( 'style_additional_settings_color_tabs' );

		$this->start_controls_tab( 'style_additional_settings_normal_tab', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control(
			'style_additional_settings_normal_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-pc: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'style_additional_settings_hover_tab', array( 'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ) ) );
		$this->add_control(
			'style_additional_settings_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-ph2: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'style_button_color_tabs_active', array( 'label' => esc_html__( 'Actual', 'pro-elements' ) ) );
		$this->add_control(
			'style_button_color_icon_active',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-pa: {{VALUE}}',
				),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'page_numbers_space_between',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-pg: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'top_spacing',
			array(
				'label'      => esc_html__( 'Espacio superior', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => self::range_200(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-pt2: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'bottom_spacing',
			array(
				'label'      => esc_html__( 'Espacio inferior', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => self::range_200(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-pb2: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_section_submit() {
		$this->start_controls_section(
			'style_section_submit',
			array(
				'label'     => esc_html__( 'Botón de envío', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'submit_trigger!' => 'key_enter' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'submit_typography',
				'selector' => '{{WRAPPER}} .digi-search__submit span',
			)
		);

		$this->add_responsive_control(
			'icon_submit_position',
			array(
				'label'                => esc_html__( 'Posición del icono', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start' => array(
						'title' => esc_html__( 'Antes', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'end'   => array(
						'title' => esc_html__( 'Después', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'condition'            => array( 'icon_submit[value]!' => '' ),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => array(
					'start' => '--dgs-sd: row; --dgs-sis: 0px; --dgs-sie: var(--dgs-sg, 8px);',
					'end'   => '--dgs-sd: row-reverse; --dgs-sis: var(--dgs-sg, 8px); --dgs-sie: 0px;',
				),
			)
		);

		$this->add_responsive_control(
			'icon_submit_size',
			array(
				'label'      => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => self::range_50(),
				'condition'  => array( 'icon_submit[value]!' => '' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-sz: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'icon_submit_gap',
			array(
				'label'      => esc_html__( 'Separación entre el texto y el icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => self::range_50(),
				'condition'  => array( 'icon_submit[value]!' => '' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-sg: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'submit_style_tabs' );

		$this->start_controls_tab( 'submit_style_normal_tab', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->register_submit_style_tabs( 'normal' );
		$this->end_controls_tab();

		$this->start_controls_tab( 'submit_style_hover_tab', array( 'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ) ) );
		$this->register_submit_style_tabs( 'hover' );

		$this->add_control(
			'submit_hover_animation',
			array(
				'label' => esc_html__( 'Animación al pasar el ratón', 'pro-elements' ),
				'type'  => Controls_Manager::HOVER_ANIMATION,
			)
		);

		$this->add_control(
			'submit_hover_transition',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 's' ),
				'range'      => self::range_time(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-st: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'submit_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-sr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'submit_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-sp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Controles del botón de envío en las pestañas Normal y Al pasar el ratón.
	 *
	 * @param string $tab_id `normal` o `hover`.
	 */
	protected function register_submit_style_tabs( $tab_id ) {
		$is_normal = 'normal' === $tab_id;
		$state     = $is_normal ? '' : ":$tab_id";
		$wrapper   = $is_normal ? '{{WRAPPER}}' : "{{WRAPPER}} .digi-search__submit$state";

		$this->add_control(
			"submit_text_color_$tab_id",
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$wrapper => '--dgs-sc: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			"submit_icon_color_$tab_id",
			array(
				'label'     => esc_html__( 'Color del icono', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array( 'icon_submit[value]!' => '' ),
				'selectors' => array(
					$wrapper => '--dgs-si: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => "submit_background_$tab_id",
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ),
				'selector' => "{{WRAPPER}} .digi-search__submit$state",
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => "submit_border_$tab_id",
				'selector' => "{{WRAPPER}} .digi-search__submit$state",
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => "submit_box_shadow_$tab_id",
				'selector' => "{{WRAPPER}} .digi-search__submit$state",
			)
		);
	}

	protected function register_style_section_results() {
		$this->start_controls_section(
			'style_section_results',
			array(
				'label'     => esc_html__( 'Resultados', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'live_results' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'results_background',
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ),
				'selector' => '{{WRAPPER}} .digi-search__results',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'results_border_type',
				'selector' => '{{WRAPPER}} .digi-search__box',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'results_box_shadow',
				'selector' => '{{WRAPPER}} .digi-search__box',
			)
		);

		$this->add_responsive_control(
			'results_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-rr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'results_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-rp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'results_distance_from_search_field',
			array(
				'label'      => esc_html__( 'Distancia al campo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
					'vw'  => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-rg: {{SIZE}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'results_is_dropdown_width',
			array(
				'label'              => esc_html__( 'Ancho del desplegable', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'search_field' => esc_html__( 'El del campo', 'pro-elements' ),
					'widget_width' => esc_html__( 'El del widget', 'pro-elements' ),
				),
				'default'            => 'search_field',
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'results_is_custom_width',
			array(
				'label'     => esc_html__( 'Ancho personalizado', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'default'   => '',
			)
		);

		$this->add_responsive_control(
			'results_custom_width',
			array(
				'label'      => esc_html__( 'Ancho', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 1000 ),
					'em'  => array( 'max' => 100 ),
					'rem' => array( 'max' => 100 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-rw: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'results_is_custom_width' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'results_max_height',
			array(
				'label'      => esc_html__( 'Alto máximo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 500 ),
					'%'   => array( 'max' => 50 ),
					'em'  => array( 'max' => 50 ),
					'rem' => array( 'max' => 50 ),
					'vw'  => array( 'max' => 50 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-rm: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'results_alignment',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start'  => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-h-align-center',
					),
					'end'    => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'toggle'               => true,
				'condition'            => array( 'results_is_custom_width' => 'yes' ),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => array(
					'start'  => '--dgs-ra: 0; --dgs-rb: auto; --dgs-rt: none;',
					'center' => '--dgs-ra: 50%; --dgs-rb: auto; --dgs-rt: translateX(-50%);',
					'end'    => '--dgs-ra: auto; --dgs-rb: 0; --dgs-rt: none;',
				),
			)
		);

		$this->add_responsive_control(
			'search_result_column_gap',
			array(
				'label'      => esc_html__( 'Separación entre columnas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-cg: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'search_result_row_gap',
			array(
				'label'      => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-rg2: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$loader = array(
			'live_results'  => 'yes',
			'template_id!'  => '',
			'enable_loader' => 'yes',
		);

		$this->add_control(
			'heading_search_reasult_loader',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Indicador de carga', 'pro-elements' ),
				'separator' => 'before',
				'condition' => $loader,
			)
		);

		$this->add_control(
			'search_result_loader_icon_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => $loader,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-lc: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'search_result_loader_icon_size',
			array(
				'label'      => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'condition'  => $loader,
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-ls: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_section_nothing_found_message() {
		$this->start_controls_section(
			'style_section_nothing_found_message',
			array(
				'label'     => esc_html__( 'Mensaje sin resultados', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => $this->get_nothing_found_conditions(),
			)
		);

		$this->add_responsive_control(
			'nothing_found_message_space_from_top',
			array(
				'label'      => esc_html__( 'Espacio superior', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => self::range_200(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-nt: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'nothing_found_message_space_from_bottom',
			array(
				'label'      => esc_html__( 'Espacio inferior', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => self::range_200(),
				'selectors'  => array(
					'{{WRAPPER}}' => '--dgs-nb: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'nothing_found_message_alignment',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
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
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-na: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'nothing_found_message_typography',
				'selector'  => '{{WRAPPER}} .digi-search__nothing',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'nothing_found_message_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--dgs-nc: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'nothing_found_message_text_shadow',
				'selector' => '{{WRAPPER}} .digi-search__nothing',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'nothing_found_message_text_stroke',
				'selector' => '{{WRAPPER}} .digi-search__nothing',
			)
		);

		$this->end_controls_section();
	}

	// ── Resultados (los pide la REST del sitio: `Search_Rest`) ─────────────────────────────────────────────────────────

	/** ¿Hay resultados en vivo? «Resultados en vivo» y una plantilla de bucle elegida. */
	public function has_live_results( array $settings ): bool {
		return 'yes' === ( $settings['live_results'] ?? '' ) && ! empty( $settings['template_id'] );
	}

	/** Pinta los resultados de `search_term` (página `page_number`) con la plantilla de bucle, o el mensaje sin resultados. */
	public function render_results() {
		$this->query_posts();
		$query = $this->get_query();
		if ( ! is_object( $query ) || empty( $query->found_posts ) ) {
			$this->handle_no_posts_found();
			return;
		}
		while ( $query->have_posts() ) {
			$query->the_post();
			$this->render_post();
		}
		wp_reset_postdata();
	}

	/** Paginación de los resultados (enlaces de `paginate_links()`), como la de PRO. */
	public function render_pagination() {
		$settings = $this->get_settings_for_display();
		$type     = (string) ( $settings['pagination_type_options'] ?? 'none' );
		$query    = $this->get_query();
		if ( 'none' === $type || ! is_object( $query ) || 1 >= (int) $query->max_num_pages ) {
			return;
		}
		$limit = (int) ( $settings['page_limit_settings'] ?? 5 );
		$total = $limit > (int) $query->max_num_pages || $limit < 1 ? (int) $query->max_num_pages : $limit;
		$prev  = esc_html( (string) ( $settings['pagination_prev_label'] ?? '' ) );
		$next  = esc_html( (string) ( $settings['pagination_next_label'] ?? '' ) );
		$args  = $this->paginate_args(
			array(
				'type'               => 'array',
				'current'            => $this->page_number,
				'total'              => $total,
				'prev_next'          => 'numbers' !== $type,
				'next_text'          => $next,
				'prev_text'          => $prev,
				'show_all'           => 'yes' !== ( $settings['pagination_shorten_settings'] ?? '' ),
				'before_page_number' => '<span class="elementor-screen-only">' . esc_html__( 'Página', 'pro-elements' ) . '</span>',
			)
		);
		$links = (array) paginate_links( $args );
		if ( 1 === $this->page_number && 'numbers' !== $type ) {
			array_unshift( $links, '<span class="prev page-numbers inactive">' . $prev . '</span>' );
		}
		if ( $this->page_number === $total && 'numbers' !== $type ) {
			$links[] = '<span class="next page-numbers inactive">' . $next . '</span>';
		}
		echo str_replace( '<a ', '<a rel="nofollow" ', implode( PHP_EOL, $links ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() y textos escapados.
	}

	/** Base de los enlaces de página: la página desde la que se pidió (`Referer`), con `?e-search-page=N`, como PRO. */
	protected function paginate_args( array $args ): array {
		$referer = function_exists( 'wp_get_referer' ) ? (string) wp_get_referer() : '';
		$parts   = wp_parse_url( $referer );
		$add     = array();
		if ( isset( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $add );
		}
		$post_id          = $referer ? url_to_postid( $referer ) : 0;
		$args['base']     = trailingslashit( (string) get_permalink( $post_id ) ) . '%_%';
		$args['format']   = '?e-search-page=%#%';
		$args['add_args'] = $add;
		if ( 0 === $post_id ) {
			unset( $args['format'] );
		}
		return $args;
	}

	protected function handle_no_posts_found() {
		$settings = $this->get_settings_for_display();
		if ( 'yes' !== ( $settings['enable_nothing_found_message'] ?? '' ) ) {
			return;
		}
		$tag = Utils::validate_html_tag( (string) ( $settings['nothing_found_message_html_tag'] ?? 'div' ) );
		printf( '<%1$s class="digi-search__nothing">%2$s</%1$s>', $tag, wp_kses_post( (string) ( $settings['nothing_found_message_text'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta validada.
	}

	/** Un resultado: la plantilla de bucle con su CSS dinámico, como PRO. */
	protected function render_post() {
		$template_id = (int) ( $this->get_settings_for_display( 'template_id' ) ?? 0 );
		$elementor   = \Elementor\Plugin::$instance;
		$document    = $template_id && isset( $elementor->documents ) ? $elementor->documents->get( $template_id ) : null;
		if ( ! $document ) {
			return;
		}
		$this->print_dynamic_css( (int) get_the_ID(), $template_id );
		$document->print_content();
	}

	/** CSS dinámico del resultado (el de `Loop_Css_Trait` de PRO: etiquetas dinámicas de la plantilla). */
	public function print_dynamic_css( $post_id, $post_id_for_data ) {
		$css_class = 'ElementorPro\\Modules\\LoopBuilder\\Files\\Css\\Loop_Dynamic_CSS';
		$documents = \Elementor\Plugin::$instance->documents ?? null;
		if ( ! class_exists( $css_class ) || ! $documents ) {
			return;
		}
		$document = $documents->get_doc_for_frontend( $post_id_for_data );
		if ( ! $document ) {
			return;
		}
		$documents->switch_to_document( $document );
		$css = (string) $css_class::create( $post_id, $post_id_for_data )->get_content();
		if ( '' !== $css ) {
			printf( '<style id="%s">%s</style>', esc_attr( 'loop-dynamic-' . $post_id_for_data ), str_replace( '.elementor-' . $post_id, '.e-loop-item-' . $post_id, $css ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS generado por el núcleo.
		}
		$documents->restore_document();
	}

	/** Resultados por página en el dispositivo pedido (el valor del dispositivo o del más cercano hacia escritorio). */
	private function get_posts_per_page_for_breakpoint( array $settings ): int {
		$key     = 'number_of_items';
		$desktop = isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ? (int) $settings[ $key ] : -1;
		$devices = array();
		$elementor = \Elementor\Plugin::$instance;
		if ( null !== $this->breakpoint && isset( $elementor->breakpoints ) && method_exists( $elementor->breakpoints, 'get_active_devices_list' ) ) {
			$devices = (array) $elementor->breakpoints->get_active_devices_list(
				array(
					'add_desktop'   => true,
					'desktop_first' => false,
					'reverse'       => false,
				)
			);
		}
		$index = array_search( $this->breakpoint, $devices, true );
		if ( false === $index ) {
			return $desktop;
		}
		foreach ( array_slice( $devices, $index ) as $device ) {
			$setting = 'desktop' === $device ? $key : $key . '_' . $device;
			if ( isset( $settings[ $setting ] ) && is_numeric( $settings[ $setting ] ) ) {
				return (int) $settings[ $setting ];
			}
		}
		return $desktop;
	}

	/** Tipos de contenido de «Consulta», como PRO (todos, productos, entradas, páginas y los públicos propios). */
	private function get_supported_post_types(): array {
		$types = array(
			'any'     => esc_html__( 'Todos', 'pro-elements' ),
			'product' => esc_html__( 'Productos', 'pro-elements' ),
			'post'    => esc_html__( 'Entradas', 'pro-elements' ),
			'page'    => esc_html__( 'Páginas', 'pro-elements' ),
		);
		if ( function_exists( 'get_post_types' ) ) {
			foreach ( (array) get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $name => $type ) {
				if ( ! in_array( $name, array( 'product', 'related_products', 'elementor_library' ), true ) && ! empty( $type->label ) ) {
					$types[ $name ] = $type->label;
				}
			}
		}
		return $types;
	}

	// ── Marcado ───────────────────────────────────────────────────────────────────────────────────────────────────────

	/** Documento donde vive el widget (el del bucle o plantilla que se pinta), como `Pro_Utils::get_current_post_id()`. */
	private function current_post_id(): int {
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->documents ) && $elementor->documents->get_current() ) {
			return (int) $elementor->documents->get_current()->get_main_id();
		}
		return function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	}

	/** Icono elegido (o nada si se vació), oculto a los lectores de pantalla. */
	private function icon( $icon ): string {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
			return '';
		}
		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ), 'i' );
		return (string) ob_get_clean();
	}

	/** Textos del script (sin escapar: viajan en un atributo escapado). */
	public static function strings(): array {
		return array(
			/* translators: %d: número de resultados. */
			'found' => __( '%d resultados', 'pro-elements' ),
			'one'   => __( '1 resultado', 'pro-elements' ),
			'none'  => __( 'No hay resultados', 'pro-elements' ),
			'pages' => __( 'Páginas de resultados', 'pro-elements' ),
		);
	}

	protected function render() {
		$settings = (array) $this->get_settings_for_display();
		$id       = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $this->get_id() );
		$live     = $this->has_live_results( $settings );
		$label    = $this->icon( $settings['icon_search'] ?? null );
		$clear    = $this->icon( $settings['icon_clear'] ?? null );
		$text     = (string) ( $settings['submit_button_text'] ?? '' );
		$shown    = 'key_enter' !== ( $settings['submit_trigger'] ?? 'click_submit' );
		$wide     = 'widget_width' === ( $settings['results_is_dropdown_width'] ?? 'search_field' );
		$root     = array( 'digi-search' );
		if ( '' !== $label ) {
			$root[] = 'digi-search--icon';
		}
		$config = '';
		if ( $live ) {
			$data   = array(
				'rest' => class_exists( Search_Rest::class ) ? Search_Rest::url() : '',
				'min'  => max( 1, (int) ( $settings['minimum_search_characters'] ?? 3 ) ),
				't'    => self::strings(),
			);
			$config = ' data-digi-search="' . esc_attr( (string) wp_json_encode( $data ) ) . '"';
		}
		$results = '';
		if ( $live ) {
			$loader  = 'yes' === ( $settings['enable_loader'] ?? '' ) ? '<div class="digi-search__loader" hidden>' . self::LOADER . '</div>' : '';
			$results = sprintf(
				'<div id="results-%1$s" class="digi-search__results%2$s" role="region" aria-label="%3$s" hidden><div class="digi-search__box"></div>%4$s</div><span class="elementor-screen-only" role="status"></span>',
				esc_attr( $id ),
				'previous_next' === ( $settings['pagination_type_options'] ?? 'none' ) ? ' digi-search--prevnext' : '',
				esc_attr__( 'Resultados de la búsqueda', 'pro-elements' ),
				$loader
			);
		}
		$submit = array( 'digi-search__submit' );
		if ( ! $shown ) {
			$submit[] = 'elementor-screen-only';
		}
		if ( ! empty( $settings['submit_hover_animation'] ) ) {
			$submit[] = 'elementor-animation-' . sanitize_html_class( (string) $settings['submit_hover_animation'] );
		}
		?>
		<search class="<?php echo esc_attr( implode( ' ', $root ) ); ?>" role="search"<?php echo $config; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba. ?>>
			<form class="digi-search__form" action="<?php echo esc_url( home_url() ); ?>" method="get">
				<?php do_action( 'elementor_pro/search/before_input', $this ); ?>
				<label class="digi-search__label" for="search-<?php echo esc_attr( $id ); ?>"><span class="elementor-screen-only"><?php echo esc_html__( 'Buscar', 'pro-elements' ); ?></span><?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager. ?></label>
				<div class="digi-search__field">
					<input id="search-<?php echo esc_attr( $id ); ?>" class="digi-search__input" type="search" name="s" value="" placeholder="<?php echo esc_attr( (string) ( $settings['search_input_placeholder_text'] ?? '' ) ); ?>" autocomplete="<?php echo 'yes' === ( $settings['autocomplete'] ?? '' ) ? 'on' : 'off'; ?>"<?php echo $live ? ' aria-controls="results-' . esc_attr( $id ) . '"' : ''; ?>>
					<?php if ( '' !== $clear ) : ?>
					<button type="button" class="digi-search__clear digi-search__clear--off" aria-label="<?php echo esc_attr__( 'Borrar la búsqueda', 'pro-elements' ); ?>"><?php echo $clear; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager. ?></button>
					<?php endif; ?>
					<?php echo $wide ? '' : $results; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba. ?>
				</div>
				<?php echo $wide ? $results : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba. ?>
				<?php do_action( 'elementor_pro/search/after_input', $this ); ?>
				<button class="<?php echo esc_attr( implode( ' ', $submit ) ); ?>" type="submit"<?php echo '' === $text ? ' aria-label="' . esc_attr__( 'Buscar', 'pro-elements' ) . '"' : ''; ?>><?php echo $this->icon( $settings['icon_submit'] ?? null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager. ?><?php if ( '' !== $text ) : ?><span<?php echo $shown ? '' : ' class="elementor-screen-only"'; ?>><?php echo esc_html( $text ); ?></span><?php endif; ?></button>
				<input type="hidden" name="e_search_props" value="<?php echo esc_attr( $id . '-' . $this->current_post_id() ); ?>">
			</form>
		</search>
		<?php
	}

	/**
	 * Plantilla del editor: el marcado del servidor. Los resultados en vivo también funcionan en el editor (la REST recibe
	 * el modelo del widget y comprueba que quien edita puede editar el documento).
	 */
	protected function content_template() {
		?>
		<#
		var digiId = view.getID(),
			digiLive = 'yes' === settings.live_results && !! settings.template_id,
			digiShown = 'key_enter' !== settings.submit_trigger,
			digiWide = 'widget_width' === settings.results_is_dropdown_width,
			digiIcon = function( icon ) {
				if ( ! icon || ! icon.value ) {
					return '';
				}
				var rendered = elementor.helpers.renderIcon( view, icon, { 'aria-hidden': true }, 'i', 'object' );
				return rendered && rendered.value ? rendered.value : '';
			},
			digiLabel = digiIcon( settings.icon_search ),
			digiClear = digiIcon( settings.icon_clear ),
			digiSubmit = digiIcon( settings.icon_submit ),
			digiText = settings.submit_button_text || '',
			digiConfig = JSON.stringify( { rest: '<?php echo esc_js( class_exists( Search_Rest::class ) ? Search_Rest::url() : '' ); ?>', min: Math.max( 1, parseInt( settings.minimum_search_characters, 10 ) || 3 ), t: <?php echo wp_json_encode( self::strings() ); ?> } ),
			digiResults = '';
		if ( digiLive ) {
			digiResults = '<div id="results-' + _.escape( digiId ) + '" class="digi-search__results' + ( 'previous_next' === settings.pagination_type_options ? ' digi-search--prevnext' : '' ) + '" role="region" aria-label="<?php echo esc_attr__( 'Resultados de la búsqueda', 'pro-elements' ); ?>" hidden><div class="digi-search__box"></div>' + ( 'yes' === settings.enable_loader ? <?php echo wp_json_encode( '<div class="digi-search__loader" hidden>' . self::LOADER . '</div>' ); ?> : '' ) + '</div><span class="elementor-screen-only" role="status"></span>';
		}
		#>
		<search class="digi-search{{ digiLabel ? ' digi-search--icon' : '' }}" role="search"<# if ( digiLive ) { #> data-digi-search="{{ digiConfig }}"<# } #>>
			<form class="digi-search__form" action="<?php echo esc_url( home_url() ); ?>" method="get">
				<label class="digi-search__label" for="search-{{ digiId }}"><span class="elementor-screen-only"><?php echo esc_html__( 'Buscar', 'pro-elements' ); ?></span>{{{ digiLabel }}}</label>
				<div class="digi-search__field">
					<input id="search-{{ digiId }}" class="digi-search__input" type="search" name="s" value="" placeholder="{{ settings.search_input_placeholder_text }}" autocomplete="{{ 'yes' === settings.autocomplete ? 'on' : 'off' }}"<# if ( digiLive ) { #> aria-controls="results-{{ digiId }}"<# } #>>
					<# if ( digiClear ) { #><button type="button" class="digi-search__clear digi-search__clear--off" aria-label="<?php echo esc_attr__( 'Borrar la búsqueda', 'pro-elements' ); ?>">{{{ digiClear }}}</button><# } #>
					<# if ( ! digiWide ) { print( digiResults ); } #>
				</div>
				<# if ( digiWide ) { print( digiResults ); } #>
				<button class="digi-search__submit{{ digiShown ? '' : ' elementor-screen-only' }}{{ settings.submit_hover_animation ? ' elementor-animation-' + settings.submit_hover_animation : '' }}" type="submit"<# if ( ! digiText ) { #> aria-label="<?php echo esc_attr__( 'Buscar', 'pro-elements' ); ?>"<# } #>>{{{ digiSubmit }}}<# if ( digiText ) { #><span class="{{ digiShown ? '' : 'elementor-screen-only' }}">{{ digiText }}</span><# } #></button>
			</form>
		</search>
		<?php
	}
}
