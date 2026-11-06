<?php
namespace Digitalisimo\Elements\Navigation\Widgets;

use Digitalisimo\Elements\Navigation\Module;
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
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Menú con submenús de contenido (`digitalisimo-mega-menu`, C3 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato
 * `docs/pro/c3.md`, frente E): sustituye al «Menu» anidado de PRO (`modules/mega-menu/widgets/mega-menu.php`, mapa
 * `modules/digitalisimo-convert/maps/mega-menu.php`). El contenido de cada submenú es un contenedor hijo, como en PRO.
 *
 * - **Controles**: los del widget de PRO (y los de su repetidor), con el mismo nombre, tipo, valor por defecto,
 *   opciones, condiciones y dispositivos. Estructura y controles derivados de PRO Elements (GPL); textos en español,
 *   selectores sobre el marcado propio y variables `--dmm-*`. Una diferencia: «Animación» (`open_animation`) es un
 *   selector con los mismos valores (`''` y `fadeIn`) en lugar del control propio de PRO, que sólo existe con su módulo.
 * - **Marcado**: `nav.digi-mm` › `button.digi-mm__toggle` (hamburguesa) y `div.digi-mm__wrapper` › `ul.digi-mm__heading`
 *   › `li.digi-mm__item` › `div.digi-mm__title` (enlace o texto, icono y `button.digi-mm__dropdown-icon` con
 *   `aria-expanded`, `aria-controls` y nombre «Submenú de …») y `div.digi-mm__content` con el contenedor hijo (con su id
 *   `digi-mm-content-<n><i>`). Mismo orden y estructura que PRO, así sus estilos se repiten con los mismos selectores.
 * - **Script** (`assets/js/mega-menu.js`, `defer`, sin dependencias): abre con el ratón o con clic según «Abrir con» (en
 *   móvil y tableta, siempre con clic, como PRO; «ratón» escucha `mouseenter`/`mouseleave`, como PRO, así que en una
 *   pantalla táctil de escritorio el toque en el título abre), Escape cierra y devuelve el foco, Inicio y Fin entre los
 *   elementos, hamburguesa, contenido a todo el ancho del contenedor de secciones estiradas o ajustado y colocado bajo su
 *   elemento, y anclas activas. Editor: `assets/js/mega-menu-editor.js` coloca cada contenedor hijo en su elemento.
 * - **«Desplazamiento horizontal»** (`assets/js/mega-menu-scroll.js`, `SCRIPTS`, sólo si algún dispositivo lo activa, y
 *   siempre en el editor): como PRO, si los elementos no caben, la fila empieza por el primero (o por el último con
 *   «Final») y se arrastra con el ratón.
 * - **Recursos**: `mega-menu.css`, `mega-menu-items.css` y `mega-menu-dropdown.css` (≤ 4 KB cada una).
 */
class Mega_Menu extends Widget_Nested_Base {

	const NAME = 'digitalisimo-mega-menu';

	/** Hojas además de la propia. */
	const SHEETS = array(
		'digitalisimo-mega-menu-items'    => 'modules/digitalisimo-navigation/assets/css/mega-menu-items.css',
		'digitalisimo-mega-menu-dropdown' => 'modules/digitalisimo-navigation/assets/css/mega-menu-dropdown.css',
	);

	/** Scripts además del propio: «Desplazamiento horizontal» (posición inicial y arrastre con el ratón, como PRO). */
	const SCRIPTS = array(
		'digitalisimo-mega-menu-scroll' => 'modules/digitalisimo-navigation/assets/js/mega-menu-scroll.js',
	);

	/** Puntos de corte que la hoja ya cubre. */
	const SHEET_BREAKPOINTS = array(
		'mobile' => 767,
		'tablet' => 1024,
	);

	/** @var array<string,bool> Reglas de punto de corte ya impresas en esta página. */
	private static $printed_breakpoints = array();

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Menú con submenús de contenido', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-mega-menu';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'menú', 'menu', 'mega menú', 'mega menu', 'submenú', 'navegación', 'anidado', 'nested', 'digitalisimo' );
	}

	/** Como en PRO: sólo con el experimento de contenedores. */
	public function show_in_panel() {
		return Module::feature( 'container' );
	}

	/** Como los anidados del núcleo: sin envoltorio interior con el marcado optimizado. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		self::register_sheets();
		return array_merge( Module::style_depends( self::NAME ), array_keys( self::SHEETS ) );
	}

	/** El script del desplazamiento horizontal, sólo si los ajustes lo activan (el tipo del editor, siempre). */
	public function get_script_depends(): array {
		self::register_scripts();
		$own = Module::script_depends( self::NAME );
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) || self::scrolls( (array) $this->get_settings() ) ) {
			return array_merge( $own, array_keys( self::SCRIPTS ) );
		}
		return $own;
	}

	/** ¿Activan unos ajustes «Desplazamiento horizontal» en algún dispositivo? (Sólo existe en la disposición horizontal.) */
	public static function scrolls( array $s ): bool {
		if ( 'horizontal' !== (string) ( $s['item_layout'] ?? 'horizontal' ) ) {
			return false;
		}
		foreach ( $s as $key => $value ) {
			if ( 'enable' === $value && preg_match( '/^horizontal_scroll(_[a-z_]+)?$/', (string) $key ) ) {
				return true;
			}
		}
		return false;
	}

	/** Registra los scripts adicionales (`defer`, en el pie, sin dependencias; idempotente, sin encolarlos). */
	public static function register_scripts(): void {
		if ( ! function_exists( 'wp_register_script' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		foreach ( self::SCRIPTS as $handle => $file ) {
			if ( ! wp_script_is( $handle, 'registered' ) && is_file( Module::path( $file ) ) ) {
				wp_register_script( $handle, plugins_url( $file, DIGITALISIMO_ELEMENTS_FILE ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
	}

	/** Registra las hojas adicionales (idempotente, sin encolarlas). */
	public static function register_sheets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		foreach ( self::SHEETS as $handle => $file ) {
			if ( ! wp_style_is( $handle, 'registered' ) && is_file( Module::path( $file ) ) ) {
				wp_register_style( $handle, plugins_url( $file, DIGITALISIMO_ELEMENTS_FILE ), array(), $version );
			}
		}
	}

	/** Puntos de corte activos del núcleo (`nombre => objeto`). */
	private static function breakpoints(): array {
		$plugin = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
		if ( is_object( $plugin ) && isset( $plugin->breakpoints ) && is_object( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ) {
			return (array) $plugin->breakpoints->get_active_breakpoints();
		}
		return array();
	}

	/** Opciones de «Posición horizontal del contenido» (invertidas en RTL), como PRO. */
	private function get_content_horizontal_controls(): array {
		$horizontal_controls = array(
			'left'   => array(
				'title' => esc_html__( 'Izquierda', 'pro-elements' ),
				'icon'  => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => esc_html__( 'Centro', 'pro-elements' ),
				'icon'  => 'eicon-h-align-center',
			),
			'right'  => array(
				'title' => esc_html__( 'Derecha', 'pro-elements' ),
				'icon'  => 'eicon-h-align-right',
			),
		);
		return is_rtl() ? array_reverse( $horizontal_controls ) : $horizontal_controls;
	}

	/** Título de un elemento nuevo (`%d` = número). */
	protected function get_default_children_title() {
		/* translators: %d: número del elemento. */
		return esc_html__( 'Elemento n.º %d', 'pro-elements' );
	}

	protected function get_default_children_elements() {
		$out = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$out[] = array(
				'elType'   => 'container',
				'settings' => array( '_title' => sprintf( $this->get_default_children_title(), $i ) ),
			);
		}
		return $out;
	}

	protected function get_default_repeater_title_setting_key() {
		return 'item_title';
	}

	protected function get_default_children_placeholder_selector() {
		return '.digi-mm__heading';
	}

	protected function get_default_children_container_placeholder_selector() {
		return '.digi-mm__content';
	}

	/**
	 * Clase del contenedor del widget. Como PRO (`elementor-widget-n-menu`, distinta de `elementor-widget-mega-menu`), no es
	 * la que usa el núcleo para los valores globales por defecto del kit (`.elementor-widget-<nombre>`): en PRO esos valores
	 * (tipografía de acento de los títulos, color del separador) nunca llegaban al menú, y un sitio migrado debe verse igual.
	 */
	protected function get_html_wrapper_class() {
		return 'elementor-widget-digi-mega-menu';
	}

	/** Selectores de los controles del botón y del contenido activo (los de PRO, sobre el marcado propio). */
	protected function get_control_selector_class( $control_item, $state = '' ) {
		$container = Module::feature( 'e_optimized_markup' ) ? '' : ' > .elementor-widget-container';
		if ( 'menu_toggle_icon' === $control_item ) {
			return "{{WRAPPER}}{$container} > .digi-mm > .digi-mm__toggle{$state} > .digi-mm__toggle-icon";
		}
		if ( 'active_content_container' === $control_item ) {
			return ":where( {{WRAPPER}}{$container} > .digi-mm > .digi-mm__wrapper > .digi-mm__heading > .digi-mm__item > .digi-mm__content ) > .e-con{$state}";
		}
		return '';
	}

	/** Selector de la tipografía de los títulos, como PRO. */
	protected function get_typography_selector( $heading_selector ): string {
		return "{$heading_selector} > .digi-mm__item > .digi-mm__title, {$heading_selector} > .digi-mm__item > .digi-mm__title > .digi-mm__title-container, {$heading_selector} > .digi-mm__item > .digi-mm__title > .digi-mm__title-container > span";
	}

	protected function register_controls() {
		$optimized_markup = Module::feature( 'e_optimized_markup' );
		$widget_container_selector = $optimized_markup ? '' : ' > .elementor-widget-container';
		$heading_selector = "{{WRAPPER}}{$widget_container_selector} > .digi-mm > .digi-mm__wrapper > .digi-mm__heading";
		$start = is_rtl() ? 'right' : 'left';
		$end = is_rtl() ? 'left' : 'right';
		$logical_dimensions_inline_start = is_rtl() ? '{{RIGHT}}{{UNIT}}' : '{{LEFT}}{{UNIT}}';
		$logical_dimensions_inline_end = is_rtl() ? '{{LEFT}}{{UNIT}}' : '{{RIGHT}}{{UNIT}}';
		$start_logical = is_rtl() ? 'end' : 'start';
		$end_logical = is_rtl() ? 'start' : 'end';

		$this->start_controls_section(
			'section_layout',
			[
				'label' => esc_html__( 'Diseño', 'pro-elements' ),
			]
		);

		$this->add_control(
			'menu_name',
			[
				'label' => esc_html__( 'Nombre del menú', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Menú', 'pro-elements' ),
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'item_title',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Título del elemento', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'item_link',
			[
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
				'type' => Controls_Manager::URL,
				'placeholder' => esc_html__( 'Pega una URL o escribe', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'frontend_available' => true,
			]
		);

		$repeater->add_control(
			'item_dropdown_content',
			[
				'label' => esc_html__( 'Contenido desplegable', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'NO', 'pro-elements' ),
				'label_on' => esc_html__( 'SÍ', 'pro-elements' ),
				'default' => 'no',
				'description' => esc_html__( 'Haz clic en el elemento del menú para editar el contenido de su submenú.', 'pro-elements' ),
				'frontend_available' => true,
			]
		);

		$repeater->add_control(
			'item_icon',
			[
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin' => 'inline',
				'label_block' => false,
			]
		);

		$repeater->add_control(
			'item_icon_active',
			[
				'label' => esc_html__( 'Icono activo', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin' => 'inline',
				'label_block' => false,
				'condition' => [
					'item_icon[value]!' => '',
				],
			]
		);

		$repeater->add_control(
			'element_id',
			[
				'label' => esc_html__( 'ID de CSS', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'title' => esc_html__( 'Tu id propio SIN la almohadilla. Ej.: mi-id', 'pro-elements' ),
				'style_transfer' => false,
			]
		);

		$this->add_control(
			'menu_items',
			[
				'label' => esc_html__( 'Elementos del menú', 'pro-elements' ),
				'type' => Control_Nested_Repeater::CONTROL_TYPE,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'item_title' => esc_html__( 'Elemento n.º 1', 'pro-elements' ),
					],
					[
						'item_title' => esc_html__( 'Elemento n.º 2', 'pro-elements' ),
					],
					[
						'item_title' => esc_html__( 'Elemento n.º 3', 'pro-elements' ),
					],
				],
				'title_field' => '{{{ item_title }}}',
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'content_width',
			[
				'label' => esc_html__( 'Ancho del contenido', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'full_width',
				'separator' => 'before',
				'options' => [
					'full_width' => esc_html__( 'Ancho completo', 'pro-elements' ),
					'fit_to_content' => esc_html__( 'Ajustado al contenido', 'pro-elements' ),
				],
				'prefix_class' => 'digi-mm--',
				'frontend_available' => true,
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-dropdown-content-max-width: {{VALUE}};',
				],
				'selectors_dictionary' => [
					'full_width' => 'initial',
					'fit_to_content' => 'fit-content',
				],
			]
		);

		$this->add_control(
			'content_horizontal_position',
			[
				'label' => esc_html__( 'Posición horizontal del contenido', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => $this->get_content_horizontal_controls(),
				'default' => 'center',
				'condition' => [
					'content_width' => 'fit_to_content',
				],
				'frontend_available' => true,
				'render_type' => 'ui',
			]
		);

		$this->add_control(
			'item_layout',
			[
				'label' => esc_html__( 'Disposición de los elementos', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'horizontal',
				'options' => [
					'horizontal' => esc_html__( 'Horizontal', 'pro-elements' ),
					'dropdown' => esc_html__( 'Desplegable', 'pro-elements' ),
				],
				'prefix_class' => 'digi-mm--layout-',
				'frontend_available' => true,
			]
		);

		$this->add_responsive_control( 'item_position_horizontal', [
			'label' => esc_html__( 'Posición de los elementos', 'pro-elements' ),
			'type' => Controls_Manager::CHOOSE,
			'options' => [
				'start' => [
					'title' => esc_html__( 'Inicio', 'pro-elements' ),
					'icon' => "eicon-align-$start_logical-h",
				],
				'center' => [
					'title' => esc_html__( 'Centro', 'pro-elements' ),
					'icon' => 'eicon-align-center-h',
				],
				'end' => [
					'title' => esc_html__( 'Final', 'pro-elements' ),
					'icon' => "eicon-align-$end_logical-h",
				],
				'stretch' => [
					'title' => esc_html__( 'Estirar', 'pro-elements' ),
					'icon' => 'eicon-align-stretch-h',
				],
			],
			'selectors_dictionary' => [
				'start' => '--dmm-heading-justify-content: initial; --dmm-title-flex-grow: initial; --dmm-title-justify-content: initial; --dmm-title-justify-content-mobile: initial;',
				'center' => '--dmm-heading-justify-content: center; --dmm-title-flex-grow: initial; --dmm-title-justify-content: initial; --dmm-title-justify-content-mobile: center;',
				'end' => '--dmm-heading-justify-content: flex-end; --dmm-title-flex-grow: initial; --dmm-title-justify-content: initial; --dmm-title-justify-content-mobile: flex-end;',
				'stretch' => '--dmm-heading-justify-content: space-between; --dmm-title-flex-grow: 1; --dmm-title-justify-content: center; --dmm-title-justify-content-mobile: center;',
			],
			'selectors' => [
				'{{WRAPPER}}' => '{{VALUE}}',
			],
			'condition' => [
				'item_layout' => 'horizontal',
			],
			'frontend_available' => true,
		]);

		$this->add_responsive_control( 'item_position_dropdown', [
			'label' => esc_html__( 'Posición de los elementos', 'pro-elements' ),
			'type' => Controls_Manager::CHOOSE,
			'options' => [
				'start' => [
					'title' => esc_html__( 'Inicio', 'pro-elements' ),
					'icon' => "eicon-align-$start_logical-h",
				],
				'center' => [
					'title' => esc_html__( 'Centro', 'pro-elements' ),
					'icon' => 'eicon-align-center-h',
				],
				'end' => [
					'title' => esc_html__( 'Final', 'pro-elements' ),
					'icon' => "eicon-align-$end_logical-h",
				],
			],
			'selectors_dictionary' => [
				'start' => '--dmm-title-justify-content: initial;  --dmm-title-justify-content-mobile: initial;',
				'center' => '--dmm-title-justify-content: center; --dmm-title-justify-content-mobile: center;',
				'end' => '--dmm-title-justify-content: flex-end; --dmm-title-justify-content-mobile: flex-end;',
			],
			'selectors' => [
				'{{WRAPPER}}' => '{{VALUE}}',
			],
			'condition' => [
				'item_layout' => 'dropdown',
			],
		]);

		$this->add_control(
			'submenu_indicator_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Indicador de submenú', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'menu_item_icon',
			[
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-caret-down',
					'library' => 'fa-solid',
				],
				'recommended' => [
					'fa-solid' => [
						'chevron-down',
						'angle-down',
						'angle-double-down',
						'caret-down',
						'caret-square-down',
					],
					'fa-regular' => [
						'caret-square-down',
					],
				],
				'skin' => 'inline',
				'label_block' => false,
			]
		);

		$this->add_control(
			'menu_item_icon_active',
			[
				'label' => esc_html__( 'Icono activo', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon_active',
				'default' => [
					'value' => 'fas fa-caret-up',
					'library' => 'fa-solid',
				],
				'recommended' => [
					'fa-solid' => [
						'chevron-up',
						'angle-up',
						'angle-double-up',
						'caret-up',
						'caret-square-up',
					],
					'fa-regular' => [
						'caret-square-up',
					],
				],
				'skin' => 'inline',
				'label_block' => false,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_dropdown_effect', [
			'label' => esc_html__( 'Efecto del submenú', 'pro-elements' ),
		] );

		$this->add_control(
			'open_on',
			[
				'label' => esc_html__( 'Abrir con', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'hover',
				'options' => [
					'hover' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
					'click' => esc_html__( 'Clic', 'pro-elements' ),
				],
				'condition' => [
					'item_layout' => 'horizontal',
				],
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'open_on_hover_description',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => esc_html__( 'El efecto al pasar el ratón no corre mientras editas: míralo en la vista previa.', 'pro-elements' ),
				'content_classes' => 'elementor-control-field-description',
				'condition' => [
					'item_layout' => 'horizontal',
					'open_on' => 'hover',
				],
			]
		);

		$this->add_control(
			'open_animation',
			[
				'label' => esc_html__( 'Animación', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Ninguna', 'pro-elements' ),
					'fadeIn' => esc_html__( 'Fundido de entrada', 'pro-elements' ),
				],
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'open_animation_duration',
			[
				'label' => esc_html__( 'Duración de la animación', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default' => [
					'unit' => 'ms',
					'size' => 500,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-open-animation-duration: {{SIZE}}{{UNIT}}',
				],
				'condition' => [
					'open_animation!' => '',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'menu_toggle_section', [
			'label' => esc_html__( 'Botón del menú', 'pro-elements' ),
		] );

		$this->add_responsive_control(
			'toggle_align',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => "eicon-align-$start_logical-h",
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-h-align-center',
					],
					'flex-end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => "eicon-align-$end_logical-h",
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-toggle-align: {{VALUE}}',
				],
			]
		);

		$this->start_controls_tabs( 'menu_toggle_tabs_section' );

		$this->start_controls_tab( 'menu_toggle_normal_options', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'menu_toggle_icon_normal',
			[
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin' => 'inline',
				'label_block' => false,
				'skin_settings' => [
					'inline' => [
						'none' => [
							'label' => esc_html__( 'Predeterminado', 'pro-elements' ),
							'icon' => 'eicon-menu-bar',
						],
						'icon' => [
							'icon' => 'eicon-star',
						],
					],
				],
				'recommended' => [
					'fa-solid' => [
						'plus-square',
						'plus',
						'plus-circle',
						'bars',
					],
					'fa-regular' => [
						'plus-square',
					],
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'menu_toggle_hover_options', [
			'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
		] );

		$this->add_control(
			'menu_toggle_icon_hover_animation',
			[
				'label' => esc_html__( 'Animación al pasar el ratón', 'pro-elements' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
				'frontend_available' => true,
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'menu_toggle_animation_duration',
			[
				'label' => esc_html__( 'Duración de la animación', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'render_type' => 'ui',
				'default' => [
					'unit' => 'ms',
					'size' => 500,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-toggle-icon-wrapper-animation-duration: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'menu_toggle_active_options', [
			'label' => esc_html__( 'Activo', 'pro-elements' ),
		] );

		$this->add_control(
			'menu_toggle_icon_active',
			[
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin' => 'inline',
				'label_block' => false,
				'skin_settings' => [
					'inline' => [
						'none' => [
							'label' => esc_html__( 'Predeterminado', 'pro-elements' ),
							'icon' => 'eicon-close',
						],
						'icon' => [
							'icon' => 'eicon-star',
						],
					],
				],
				'recommended' => [
					'fa-solid' => [
						'window-close',
						'times-circle',
						'times',
						'minus-square',
						'minus-circle',
						'minus',
					],
					'fa-regular' => [
						'window-close',
						'times-circle',
						'minus-square',
					],
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section( 'section_responsive_mega_menu', [
			'label' => esc_html__( 'Ajustes adicionales', 'pro-elements' ),
		] );

		$this->add_responsive_control(
			'horizontal_scroll',
			[
				'label' => esc_html__( 'Desplazamiento horizontal', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'description' => esc_html__( 'Desplaza los elementos del menú si no caben en su contenedor.', 'pro-elements' ),
				'options' => [
					'disable' => esc_html__( 'Desactivado', 'pro-elements' ),
					'enable' => esc_html__( 'Activado', 'pro-elements' ),
				],
				'default' => 'disable',
				'selectors_dictionary' => [
					'disable' => '--dmm-heading-wrap: wrap; --dmm-heading-overflow-x: initial;',
					'enable' => '--dmm-heading-wrap: nowrap; --dmm-heading-overflow-x: scroll;',
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'frontend_available' => true,
				'condition' => [
					'item_layout' => 'horizontal',
				],
			]
		);

		$dropdown_options = [
			'none' => esc_html__( 'Ninguno', 'pro-elements' ),
		];

		$excluded_breakpoints = [
			'widescreen',
		];

		foreach ( self::breakpoints() as $breakpoint_key => $breakpoint_instance ) {
			// Exclude the larger breakpoints from the dropdown selector.
			if ( in_array( $breakpoint_key, $excluded_breakpoints, true ) ) {
				continue;
			}

			$dropdown_options[ $breakpoint_key ] = sprintf(
				/* translators: 1: Breakpoint label, 2: Comparison operator, 3: Breakpoint value in pixels. */
				esc_html__( '%1$s (%2$s %3$dpx)', 'pro-elements' ),
				$breakpoint_instance->get_label(),
				'>',
				$breakpoint_instance->get_value()
			);
		}

		$this->add_control(
			'breakpoint_selector',
			[
				'label' => esc_html__( 'Punto de corte', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'description' => esc_html__( 'Los elementos pasan a desplegable en las pantallas más pequeñas que el punto de corte elegido.', 'pro-elements' ),
				'options' => $dropdown_options,
				'default' => 'tablet',
				'prefix_class' => 'digi-mm--bp-',
				'frontend_available' => true,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_menu_items_style', [
			'label' => esc_html__( 'Elementos del menú', 'pro-elements' ),
			'tab' => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'menu_item_title_space_between', [
			'label' => esc_html__( 'Separación entre elementos', 'pro-elements' ),
			'type' => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em', 'rem', 'custom' ],
			'range' => [
				'px' => [
					'max' => 200,
				],
				'em' => [
					'max' => 20,
				],
				'rem' => [
					'max' => 20,
				],
			],
			'default' => [
				'size' => 0,
			],
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-title-space-between: {{SIZE}}{{UNIT}}',
			],
		] );

		$this->add_responsive_control( 'menu_item_title_distance_from_content', [
			'label' => esc_html__( 'Distancia al contenido', 'pro-elements' ),
			'type' => Controls_Manager::SLIDER,
			'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
			'range' => [
				'px' => [
					'max' => 200,
				],
				'em' => [
					'max' => 20,
				],
				'rem' => [
					'max' => 20,
				],
			],
			'default' => [
				'size' => 0,
			],
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-title-distance-from-content: {{SIZE}}{{UNIT}}',
			],
			'frontend_available' => true,
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name' => 'menu_item_title_typography',
			'global' => [
				'default' => Global_Typography::TYPOGRAPHY_ACCENT,
			],
			'selector' => $this->get_typography_selector( $heading_selector ),
			'fields_options' => [
				'font_size' => [
					'selectors' => [
						'{{WRAPPER}}' => '--dmm-title-font-size: {{SIZE}}{{UNIT}}',
					],
				],
				'line_height' => [
					'selectors' => [
						'{{SELECTOR}}' => '--dmm-title-line-height: {{SIZE}}',
					],
				],
			],
		] );

		$this->start_controls_tabs( 'menu_item_title_style' );

		$this->start_controls_tab(
			'menu_item_title_normal',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			]
		);

		$this->add_control(
			'menu_item_title_text_color_normal',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-title-color-normal: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'menu_item_title_text_shadow_normal',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:not( .digi-mm__current ):not( :hover )",
				'fields_options' => [
					'text_shadow_type' => [
						'label' => esc_html__( 'Sombra', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'menu_item_title_background_color',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:not( .digi-mm__current ):not( :hover )",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'menu_item_title_box_border',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:not( .digi-mm__current ):not( :hover )",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'menu_item_title_box_shadow',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:not( .digi-mm__current ):not( :hover )",
			]
		);

		$divider_condition = [
			'menu_divider' => 'yes',
			'item_layout' => 'horizontal',
		];

		$this->add_control(
			'menu_divider',
			[
				'label' => esc_html__( 'Separador', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'condition' => [
					'item_layout' => 'horizontal',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-divider-content: "";',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'menu_divider_style',
			[
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'solid' => esc_html__( 'Continuo', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
				],
				'default' => 'solid',
				'condition' => $divider_condition,
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-divider-style: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'menu_divider_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 20,
					],
					'em' => [
						'max' => 2,
					],
					'rem' => [
						'max' => 2,
					],
				],
				'condition' => $divider_condition,
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-divider-width: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'menu_divider_height',
			[
				'label' => esc_html__( 'Altura', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vh', 'custom' ],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
					'%' => [
						'min' => 1,
						'max' => 100,
					],
				],
				'condition' => $divider_condition,
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-divider-height: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'menu_divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'condition' => $divider_condition,
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-divider-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab(); // End Normal Tab

		$this->start_controls_tab(
			'menu_item_title_hover',
			[
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			]
		);

		$this->add_control(
			'menu_item_title_text_color_hover',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' => '--dmm-title-color-hover: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'menu_item_title_text_shadow_hover',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:hover:not( .digi-mm__current )",
				'fields_options' => [
					'text_shadow_type' => [
						'label' => esc_html__( 'Sombra', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'menu_item_title_background_color_hover',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:hover:not( .digi-mm__current )",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'menu_item_title_box_border_hover',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:hover:not( .digi-mm__current )",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'menu_item_title_box_shadow_hover',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title:hover:not( .digi-mm__current )",
			]
		);

		$this->add_control(
			'hover_animation',
			[
				'label' => esc_html__( 'Animación al pasar el ratón', 'pro-elements' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
			]
		);

		$this->add_control(
			'menu_item_title_transition_duration',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-title-transition: {{SIZE}}{{UNIT}}',
				],
				'default' => [
					'unit' => 'ms',
					'size' => 300,
				],
			]
		);
		$this->end_controls_tab(); // End Hover Tab

		$this->start_controls_tab(
			'menu_item_title_active',
			[
				'label' => esc_html__( 'Activo', 'pro-elements' ),
			]
		);

		$this->add_control(
			'menu_item_title_text_color_active',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' => '--dmm-title-color-active: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'menu_item_title_text_shadow_active',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title.digi-mm__current",
				'fields_options' => [
					'text_shadow_type' => [
						'label' => esc_html__( 'Sombra', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'menu_item_title_background_color_active',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title.digi-mm__current",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'menu_item_title_box_border_active',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title.digi-mm__current",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'menu_item_title_box_shadow_active',
				'selector' => "{$heading_selector} > .digi-mm__item > .digi-mm__title.digi-mm__current",
			]
		);

		$this->end_controls_tab(); // End Active Tab

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'menu_item_title_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					"{$heading_selector} > .digi-mm__item > .digi-mm__title" => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'menu_item_title_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-title-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// Icon Style
		$this->start_controls_section( 'icon_section_style', [
			'label' => esc_html__( 'Icono', 'pro-elements' ),
			'tab' => Controls_Manager::TAB_STYLE,
		] );

		$styling_block_start = '--dmm-title-direction: column; --dmm-icon-order: initial; --dmm-icon-align-items: flex-end; --dmm-title-justify-content: center; --dmm-title-align-items-toggle: initial;';
		$styling_inline_end = '--dmm-title-direction: row; --dmm-icon-order: 1; --dmm-icon-align-items: initial; --dmm-title-justify-content: initial; --dmm-title-align-items-toggle: center;';
		$styling_block_end = '--dmm-title-direction: column; --dmm-icon-order: 1; --dmm-icon-align-items: flex-start; --dmm-title-justify-content: center; --dmm-title-align-items-toggle: initial;';
		$styling_inline_start = '--dmm-title-direction: row; --dmm-icon-order: initial; --dmm-icon-align-items: initial; --dmm-title-justify-content: initial; --dmm-title-align-items-toggle: center;';

		$this->add_responsive_control( 'icon_position', [
			'label' => esc_html__( 'Posición', 'pro-elements' ),
			'type' => Controls_Manager::CHOOSE,
			'options' => [
				'block-start' => [
					'title' => esc_html__( 'Encima', 'pro-elements' ),
					'icon' => 'eicon-v-align-top',
				],
				'inline-end' => [
					'title' => esc_html__( 'Después', 'pro-elements' ),
					'icon' => 'eicon-h-align-' . $end,
				],
				'block-end' => [
					'title' => esc_html__( 'Debajo', 'pro-elements' ),
					'icon' => 'eicon-v-align-bottom',
				],
				'inline-start' => [
					'title' => esc_html__( 'Antes', 'pro-elements' ),
					'icon' => 'eicon-h-align-' . $start,
				],
			],
			'selectors_dictionary' => [
				'block-start' => $styling_block_start,
				'inline-end' => $styling_inline_end,
				'block-end' => $styling_block_end,
				'inline-start' => $styling_inline_start,
				// Styling duplication for BC reasons.
				'top' => $styling_block_start,
				'end' => $styling_inline_end,
				'bottom' => $styling_block_end,
				'start' => $styling_inline_start,
			],
			'selectors' => [
				'{{WRAPPER}}' => '{{VALUE}}',
			],
		] );

		$this->add_responsive_control( 'icon_size', [
			'label' => esc_html__( 'Tamaño', 'pro-elements' ),
			'type' => Controls_Manager::SLIDER,
			'range' => [
				'px' => [
					'max' => 100,
				],
				'em' => [
					'min' => 0,
					'max' => 10,
				],
				'rem' => [
					'min' => 0,
					'max' => 10,
				],
			],
			'default' => [
				'unit' => 'px',
				'size' => 16,
			],
			'size_units' => [ 'px', 'em', 'rem' ],
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-icon-size: {{SIZE}}{{UNIT}}',
			],
		] );

		$this->add_responsive_control( 'icon_spacing', [
			'label' => esc_html__( 'Separación', 'pro-elements' ),
			'type' => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em', 'rem', 'vw', 'custom' ],
			'range' => [
				'px' => [
					'max' => 400,
				],
				'em' => [
					'max' => 40,
				],
				'rem' => [
					'max' => 40,
				],
				'vw' => [
					'min' => 0,
					'max' => 50,
					'step' => 0.1,
				],
			],
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-icon-gap: {{SIZE}}{{UNIT}}',
			],
		] );

		$this->start_controls_tabs( 'icon_style_states' );

		$this->start_controls_tab(
			'icon_section_normal',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			]
		);

		$this->add_control( 'icon_color', [
			'label' => esc_html__( 'Color', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-icon-color: {{VALUE}};',
			],
		] );

		$this->end_controls_tab();

		$this->start_controls_tab(
			'icon_section_hover',
			[
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			]
		);

		$this->add_control( 'icon_color_hover', [
			'label' => esc_html__( 'Color', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-icon-color-hover: {{VALUE}};',
			],
		] );

		$this->end_controls_tab();

		$this->start_controls_tab(
			'icon_section_active',
			[
				'label' => esc_html__( 'Activo', 'pro-elements' ),
			]
		);

		$this->add_control( 'icon_color_active', [
			'label' => esc_html__( 'Color', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-icon-color-active: {{VALUE}};',
			],
		] );

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section( 'section_dropdown_indicator_style', [
			'label' => esc_html__( 'Indicador de submenú', 'pro-elements' ),
			'tab' => Controls_Manager::TAB_STYLE,
			'conditions' => [
				'relation' => 'or',
				'terms' => [
					[
						'name' => 'menu_item_icon[value]',
						'operator' => '!==',
						'value' => '',
					],
					[
						'name' => 'menu_item_icon_active[value]',
						'operator' => '!==',
						'value' => '',
					],
				],
			],
		] );

		$this->add_responsive_control(
			'style_dropdown_indicator_size',
			[
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-dropdown-indicator-size: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_responsive_control(
			'style_dropdown_indicator_rotate',
			[
				'label' => esc_html__( 'Girar', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'deg', 'grad', 'rad', 'turn', 'custom' ],
				'default' => [
					'unit' => 'deg',
				],
				'tablet_default' => [
					'unit' => 'deg',
				],
				'mobile_default' => [
					'unit' => 'deg',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-dropdown-indicator-rotate: rotate({{SIZE}}{{UNIT}})',
				],
			]
		);

		$this->add_responsive_control(
			'style_dropdown_indicator_space',
			[
				'label' => esc_html__( 'Espacio', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-dropdown-indicator-space: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->start_controls_tabs( 'style_menu_dropdown_indicator' );

		foreach ( [ 'normal', 'hover', 'active' ] as $state ) {
			$this->add_dropdown_indicator_state_based_style_controls( $state );
		}

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section( 'section_menu_toggle_style', [
			'label' => esc_html__( 'Botón del menú', 'pro-elements' ),
			'tab' => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control(
			'style_menu_toggle_icon_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Icono del botón', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'style_menu_toggle_icon_size',
			[
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'default' => [
					'size' => 20,
				],
				'range' => [
					'px' => [
						'max' => 200,
					],
					'em' => [
						'max' => 20,
					],
					'rem' => [
						'max' => 20,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-toggle-icon-size: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->start_controls_tabs( 'style_menu_toggle_tabs' );

		$this->start_controls_tab( 'style_menu_toggle_tab_normal', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control( 'menu_toggle_icon_color_normal', [
			'label' => esc_html__( 'Color', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'render_type' => 'ui',
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-toggle-icon-color: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'menu_toggle_icon_background_color_normal',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', '[aria-expanded="false"]:not( :hover )' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
						'selectors' => [
							'{{SELECTOR}}' => 'background: {{VALUE}}',
						],
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'menu_toggle_icon_border_normal',
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', '[aria-expanded="false"]:not( :hover )' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'menu_toggle_icon_box_shadow_normal',
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', '[aria-expanded="false"]:not( :hover )' ),
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'style_menu_toggle_hover', [
			'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
		] );

		$this->add_control( 'menu_toggle_icon_color_hover', [
			'label' => esc_html__( 'Color', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'render_type' => 'ui',
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-toggle-icon-color-hover: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'menu_toggle_icon_background_color_hover',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', ':hover:is( [aria-expanded="true"], [aria-expanded="false"] )' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
						'selectors' => [
							'{{SELECTOR}}' => 'background: {{VALUE}}',
						],
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'menu_toggle_icon_border_hover',
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', ':hover:is( [aria-expanded="true"], [aria-expanded="false"] )' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'menu_toggle_icon_box_shadow_hover',
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', ':hover:is( [aria-expanded="true"], [aria-expanded="false"] )' ),
			]
		);

		$this->add_control(
			'menu_toggle_icon_animation_duration',
			[
				'label' => esc_html__( 'Duración de la animación', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default' => [
					'unit' => 'ms',
					'size' => 500,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-toggle-icon-hover-duration: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'style_menu_toggle_active', [
			'label' => esc_html__( 'Activo', 'pro-elements' ),
		] );

		$this->add_control( 'menu_toggle_icon_color_active', [
			'label' => esc_html__( 'Color', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'render_type' => 'ui',
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-toggle-icon-color-active: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'menu_toggle_icon_background_color_active',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', '[aria-expanded="true"]' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
						'selectors' => [
							'{{SELECTOR}}' => 'background: {{VALUE}}',
						],
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'menu_toggle_icon_border_active',
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', '[aria-expanded="true"]' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'menu_toggle_icon_box_shadow_active',
				'selector' => $this->get_control_selector_class( 'menu_toggle_icon', '[aria-expanded="true"]' ),
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'menu_toggle_icon_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-toggle-icon-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'menu_toggle_icon__padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-toggle-icon-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control( 'menu_toggle_icon_distance_from_dropdown', [
			'label' => esc_html__( 'Distancia al desplegable', 'pro-elements' ),
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
			'default' => [
				'size' => 0,
			],
			'placeholder' => [
				'size' => 0,
			],
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-toggle-icon-distance-from-dropdown: {{SIZE}}{{UNIT}}',
			],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_content_style', [
			'label' => esc_html__( 'Contenido', 'pro-elements' ),
			'tab' => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'content_background_color',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => $this->get_control_selector_class( 'active_content_container' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'content_border',
				'selector' => $this->get_control_selector_class( 'active_content_container' ),
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_responsive_control(
			'content_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					$this->get_control_selector_class( 'active_content_container' ) => '--border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'content_shadow',
				'selector' => $this->get_control_selector_class( 'active_content_container' ),
			]
		);

		$this->add_responsive_control(
			'content_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					$this->get_control_selector_class( 'active_content_container' ) => '--padding-top: {{TOP}}{{UNIT}}; --padding-right: {{RIGHT}}{{UNIT}}; --padding-bottom: {{BOTTOM}}{{UNIT}}; --padding-left: {{LEFT}}{{UNIT}};',
					// Todo: Remove in version 3.21.0: https://elementor.atlassian.net/browse/ED-11888.
					// Remove together with support for physical properties inside the container widget.
					":where( [data-core-v316-plus='true'] .elementor-element.elementor-widget-digi-mega-menu{$widget_container_selector} > .digi-mm > .digi-mm__wrapper > .digi-mm__heading > .digi-mm__item > .digi-mm__content ) > .e-con" => "--padding-block-start: {{TOP}}{{UNIT}}; --padding-inline-end: $logical_dimensions_inline_end; --padding-block-end: {{BOTTOM}}{{UNIT}}; --padding-inline-start: $logical_dimensions_inline_start;",
				],
				'separator' => 'before',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_dropdown_menu_style', [
			'label' => esc_html__( 'Menú desplegable', 'pro-elements' ),
			'tab' => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control(
			'dropdown_menu_items_title',
			[
				'label' => esc_html__( 'Elementos del menú', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'dropdown_menu_items_description',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => esc_html__( 'Estilos de los elementos cuando el menú pasa a desplegable', 'pro-elements' ),
				'content_classes' => 'elementor-control-field-description',
			]
		);

		$this->start_controls_tabs( 'menu_dropdown_states_section' );

		$this->start_controls_tab(
			'normal_menu_dropdown_states',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			]
		);

		$this->add_control( 'dropdown_menu_item_text_color_normal', [
			'label' => esc_html__( 'Color del texto', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-title-normal-color-dropdown: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'dropdown_menu_item_background_color_normal',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => "{{WRAPPER}}{$widget_container_selector} > .digi-mm[data-layout='dropdown'] > .digi-mm__wrapper > .digi-mm__heading > .digi-mm__item > .digi-mm__title:not( .digi-mm__current )",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
						'selectors' => [
							'{{SELECTOR}}' => 'background: {{VALUE}}',
						],
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'dropdown_menu_item_box_shadow_normal',
				'selector' => "{{WRAPPER}}{$widget_container_selector} > .digi-mm[data-layout='dropdown'] > .digi-mm__wrapper > .digi-mm__heading > .digi-mm__item > .digi-mm__title:not( .digi-mm__current )",

			]
		);

		$this->end_controls_tab(); // Normal tab end

		$this->start_controls_tab(
			'active_menu_dropdown_states',
			[
				'label' => esc_html__( 'Activo', 'pro-elements' ),
			]
		);

		$this->add_control( 'dropdown_menu_item_text_color_active', [
			'label' => esc_html__( 'Color del texto', 'pro-elements' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--dmm-title-active-color-dropdown: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'dropdown_menu_item_background_color_active',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => "{{WRAPPER}}{$widget_container_selector} > .digi-mm[data-layout='dropdown'] > .digi-mm__wrapper > .digi-mm__heading > .digi-mm__item > .digi-mm__title.digi-mm__current",
				'fields_options' => [
					'color' => [
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'dropdown_menu_item_box_shadow_active',
				'selector' => "{{WRAPPER}}{$widget_container_selector} > .digi-mm[data-layout='dropdown'] > .digi-mm__wrapper > .digi-mm__heading > .digi-mm__item > .digi-mm__title.digi-mm__current",

			]
		);

		$this->end_controls_tab(); // Active tab end

		$this->end_controls_tabs();

		$this->add_control(
			'menu_dropdown_box_title',
			[
				'label' => esc_html__( 'Caja del desplegable', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'menu_dropdown_box_description',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => esc_html__( 'Estilo de la caja desplegable que contiene los elementos del menú.', 'pro-elements' ),
				'content_classes' => 'elementor-control-field-description',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'menu_dropdown_box_border',
				'fields_options' => [
					'border' => [
						'selectors' => [
							'{{WRAPPER}}' => '--dmm-dropdown-content-box-border-style: {{VALUE}}',
						],
					],
					'color' => [
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
						'selectors' => [
							'{{WRAPPER}}' => '--dmm-dropdown-content-box-border-color: {{VALUE}}',
						],
					],
					'width' => [
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
						'selectors' => [
							'{{WRAPPER}}' => "--dmm-dropdown-content-box-border-block-start-width: {{TOP}}{{UNIT}}; --dmm-dropdown-content-box-border-inline-end-width: $logical_dimensions_inline_end; --dmm-dropdown-content-box-border-block-end-width: {{BOTTOM}}{{UNIT}}; --dmm-dropdown-content-box-border-inline-start-width: $logical_dimensions_inline_start;",
						],
					],
				],
			]
		);

		$this->add_control(
			'menu_dropdown_box_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dmm-dropdown-content-box-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'menu_dropdown_box_shadow',
				'fields_options' => [
					'box_shadow' => [
						'selectors' => [
							'{{WRAPPER}}' => '--dmm-dropdown-content-box-shadow-horizontal: {{HORIZONTAL}}px; --dmm-dropdown-content-box-shadow-vertical: {{VERTICAL}}px; --dmm-dropdown-content-box-shadow-blur: {{BLUR}}px; --dmm-dropdown-content-box-shadow-spread: {{SPREAD}}px; --dmm-dropdown-content-box-shadow-color: {{COLOR}}; --dmm-dropdown-content-box-shadow-position: {{box_shadow_position.VALUE}};',
						],
					],
				],
			]
		);

		$this->end_controls_section();
	}


	private function add_dropdown_indicator_state_based_style_controls( string $state ) {
		$label = esc_html__( 'Normal', 'pro-elements' );
		$selector = '--dmm-dropdown-indicator-color-normal: {{VALUE}};';
		if ( 'hover' === $state ) {
			$label = esc_html__( 'Al pasar el ratón', 'pro-elements' );
			$selector = '--dmm-dropdown-indicator-color-hover: {{VALUE}};';
		}
		if ( 'active' === $state ) {
			$label = esc_html__( 'Activo', 'pro-elements' );
			$selector = '--dmm-dropdown-indicator-color-active: {{VALUE}};';
		}
		$this->start_controls_tab('style_menu_dropdown_indicator_' . $state, [
			'label' => $label,
		] );

		$this->add_control(
			'menu_dropdown_indicator_color_' . $state,
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => $selector,
				],
			]
		);

		$this->end_controls_tab();
	}

	/** Etiqueta del botón de un submenú. */
	public static function sub_label( string $title ): string {
		/* translators: %s: título del elemento del menú. */
		return sprintf( __( 'Submenú de %s', 'pro-elements' ), $title );
	}

	protected function render() {
		$settings = (array) $this->get_settings_for_display();
		$number   = (string) $this->get_widget_number();
		$items    = isset( $settings['menu_items'] ) && is_array( $settings['menu_items'] ) ? array_values( $settings['menu_items'] ) : array();
		echo $this->breakpoint_style( (string) ( $settings['breakpoint_selector'] ?? 'tablet' ), (string) ( $settings['item_layout'] ?? 'horizontal' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS generado con números.
		$attrs = array(
			'class'              => 'digi-mm',
			'data-widget-number' => $number,
			'data-digi-open'     => 'click' === ( $settings['open_on'] ?? 'hover' ) ? 'click' : 'hover',
		);
		if ( 'fadeIn' === ( $settings['open_animation'] ?? '' ) ) {
			$attrs['data-digi-anim'] = 'fadeIn';
		}
		if ( 'fit_to_content' === ( $settings['content_width'] ?? 'full_width' ) ) {
			$attrs['data-digi-fit'] = in_array( $settings['content_horizontal_position'] ?? '', array( 'left', 'right' ), true ) ? $settings['content_horizontal_position'] : 'center';
		}
		if ( '' !== (string) ( $settings['menu_name'] ?? '' ) ) {
			$attrs['aria-label'] = (string) $settings['menu_name'];
		}
		echo '<nav' . self::attributes( $attrs ) . '>';
		$this->render_menu_toggle( $settings, $number );
		echo '<div class="digi-mm__wrapper" id="' . esc_attr( 'digi-mm-bar-' . $number ) . '"><ul class="digi-mm__heading">';
		foreach ( $items as $index => $item ) {
			$this->render_item( (int) $index, is_array( $item ) ? $item : array(), $settings, $number );
		}
		echo '</ul></div></nav>';
	}

	/** Atributos HTML escapados. */
	private static function attributes( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $name => $value ) {
			$out .= ' ' . $name . '="' . esc_attr( is_array( $value ) ? implode( ' ', array_filter( $value ) ) : (string) $value ) . '"';
		}
		return $out;
	}

	/** Hamburguesa: botón nativo con los iconos de PRO (o los elegidos). */
	private function render_menu_toggle( array $settings, string $number ): void {
		$animation = ! empty( $settings['menu_toggle_icon_hover_animation'] ) ? 'elementor-animation-' . sanitize_html_class( (string) $settings['menu_toggle_icon_hover_animation'] ) : '';
		echo '<button type="button"' . self::attributes(
			array(
				'class'         => array( 'digi-mm__toggle', $animation ),
				'id'            => 'digi-mm-toggle-' . $number,
				'aria-expanded' => 'false',
				'aria-controls' => 'digi-mm-bar-' . $number,
				'aria-label'    => __( 'Menú', 'pro-elements' ),
			)
		) . '>';
		$icons = array(
			'open'  => ! empty( $settings['menu_toggle_icon_normal']['value'] ) ? $settings['menu_toggle_icon_normal'] : array(
				'library' => 'eicons',
				'value'   => 'eicon-menu-bar',
			),
			'close' => ! empty( $settings['menu_toggle_icon_active']['value'] ) ? $settings['menu_toggle_icon_active'] : array(
				'library' => 'eicons',
				'value'   => 'eicon-close',
			),
		);
		foreach ( $icons as $state => $icon ) {
			echo '<span class="digi-mm__toggle-icon digi-mm__toggle-' . esc_attr( $state ) . '">';
			Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			echo '</span>';
		}
		echo '</button>';
	}

	/** Un elemento del menú: título (enlace o texto, icono, botón del submenú) y su contenido. */
	private function render_item( int $index, array $item, array $settings, string $number ): void {
		$display  = $index + 1;
		$title    = (string) ( $item['item_title'] ?? '' );
		$link     = isset( $item['item_link'] ) && is_array( $item['item_link'] ) ? $item['item_link'] : array();
		$url      = trim( (string) ( $link['url'] ?? '' ) );
		$dropdown = 'yes' === ( $item['item_dropdown_content'] ?? '' );
		$current  = $this->get_current_menu_item_class( $url );
		$classes  = array( 'digi-mm__title' );
		if ( '' !== $current ) {
			$classes[] = $current;
		}
		if ( 'click' === ( $settings['open_on'] ?? 'hover' ) && $dropdown && $this->item_has_dropdown_with_content( $index ) ) {
			$classes[] = 'digi-mm__click';
		}
		if ( ! empty( $settings['hover_animation'] ) ) {
			$classes[] = 'elementor-animation-' . sanitize_html_class( (string) $settings['hover_animation'] );
		}
		$title_id  = '' !== (string) ( $item['element_id'] ?? '' ) ? (string) $item['element_id'] : 'digi-mm-title-' . $number . $display;
		$button_id = 'digi-mm-dropdown-icon-' . $number . $display;
		echo '<li class="digi-mm__item"><div' . self::attributes(
			array(
				'id'    => $title_id,
				'class' => $classes,
			)
		) . '>';
		$key = 'digi-mm-link-' . ( $item['_id'] ?? $display );
		if ( '' !== $url ) {
			$this->remove_render_attribute( $key );
			$this->add_render_attribute( $key, 'class', array( 'digi-mm__title-container', 'digi-mm__link' ) );
			$this->add_link_attributes( $key, $link );
			if ( 'digi-mm__current' === $current ) {
				$this->add_render_attribute( $key, 'aria-current', 'page' );
			}
			echo '<a ' . $this->get_render_attribute_string( $key ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados por el núcleo.
		} else {
			echo '<div class="digi-mm__title-container">';
		}
		$icon = Icons_Manager::try_get_icon_html( $item['item_icon'] ?? array(), array( 'aria-hidden' => 'true' ) );
		if ( $icon ) {
			$active = ! empty( $item['item_icon_active']['value'] ) ? Icons_Manager::try_get_icon_html( $item['item_icon_active'], array( 'aria-hidden' => 'true' ) ) : $icon;
			echo '<span class="digi-mm__icon"><span class="digi-mm__icon-active">' . $active . '</span><span class="digi-mm__icon-inactive">' . $icon . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado del núcleo.
		}
		echo '<span class="digi-mm__title-text">' . wp_kses_post( $title ) . '</span>';
		echo '' !== $url ? '</a>' : '</div>';
		if ( $dropdown ) {
			$closed = Icons_Manager::try_get_icon_html( $settings['menu_item_icon'] ?? array(), array( 'aria-hidden' => 'true' ) );
			$opened = Icons_Manager::try_get_icon_html( $settings['menu_item_icon_active'] ?? array(), array( 'aria-hidden' => 'true' ) );
			echo '<button type="button"' . self::attributes(
				array(
					'class'          => 'digi-mm__dropdown-icon',
					'id'             => $button_id,
					'data-tab-index' => $display,
					'aria-expanded'  => 'false',
					'aria-controls'  => 'digi-mm-content-' . $number . $display,
					'aria-label'     => self::sub_label( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $title ) ) ) ),
				)
			) . '><span class="digi-mm__dropdown-opened">' . $opened . '</span><span class="digi-mm__dropdown-closed">' . $closed . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado del núcleo.
		}
		echo '</div>';
		if ( $dropdown ) {
			echo '<div class="digi-mm__content">';
			$this->print_child( $index, $dropdown, $button_id );
			echo '</div>';
		}
		echo '</li>';
	}

	/**
	 * Pinta el contenedor hijo `$index` (si el elemento tiene contenido) con su id, su número y `aria-labelledby`, por el
	 * mismo filtro que usan los anidados del núcleo: sólo el hijo directo los recibe.
	 *
	 * @param int    $index                Posición del hijo.
	 * @param bool   $has_dropdown_content «Contenido desplegable» activado.
	 * @param string $labelled_by          Id del botón del submenú.
	 */
	public function print_child( $index, $has_dropdown_content = false, $labelled_by = '' ) {
		$children = (array) $this->get_children();
		if ( ! $this->item_has_dropdown_with_content( (int) $index ) || ! $has_dropdown_content ) {
			return;
		}
		$child  = $children[ $index ];
		$number = (string) $this->get_widget_number();
		$add    = static function ( $should_render, $container = null ) use ( $child, $index, $number, $labelled_by ) {
			if ( $container === $child && method_exists( $container, 'add_render_attribute' ) ) {
				$container->add_render_attribute(
					'_wrapper',
					array(
						'id'              => 'digi-mm-content-' . $number . ( $index + 1 ),
						'data-tab-index'  => $index + 1,
						'aria-labelledby' => $labelled_by,
					)
				);
			}
			return $should_render;
		};
		add_filter( 'elementor/frontend/container/should_render', $add, 10, 2 );
		$child->print_element();
		remove_filter( 'elementor/frontend/container/should_render', $add, 10 );
	}

	/** ¿El elemento tiene un contenedor hijo con algo dentro? (como PRO). */
	protected function item_has_dropdown_with_content( int $index ): bool {
		$children = (array) $this->get_children();
		if ( empty( $children[ $index ] ) || ! method_exists( $children[ $index ], 'get_data' ) ) {
			return false;
		}
		$data = (array) $children[ $index ]->get_data();
		return ! empty( $data['elements'] );
	}

	/** `digi-mm__anchor` para un ancla, `digi-mm__current` si el enlace es la página que se ve (como PRO). */
	protected function get_current_menu_item_class( string $menu_link_url ): string {
		$menu_link_url = trim( $menu_link_url );
		if ( false !== strpos( $menu_link_url, '#' ) ) {
			return 'digi-mm__anchor';
		}
		$permalink = $this->get_permalink_for_current_page();
		if ( '' === $menu_link_url || '' === $permalink ) {
			return '';
		}
		return self::parse_url( $permalink ) === self::parse_url( $menu_link_url ) ? 'digi-mm__current' : '';
	}

	/** Partes comparables de una URL (sin `www.` ni barras de los extremos), como PRO. */
	public static function parse_url( string $url ): array {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parts = is_array( $parts ) ? $parts : array();
		return array(
			'host'  => ! empty( $parts['host'] ) ? str_replace( 'www.', '', $parts['host'] ) : '',
			'path'  => ! empty( $parts['path'] ) ? trim( $parts['path'], '/' ) : '',
			'query' => ! empty( $parts['query'] ) ? $parts['query'] : '',
		);
	}

	/** Enlace permanente de lo que se está viendo (portada, entradas, archivos, búsqueda…), como PRO. */
	public function get_permalink_for_current_page(): string {
		if ( ! function_exists( 'is_front_page' ) ) {
			return '';
		}
		if ( ! is_front_page() && is_home() ) {
			return (string) get_post_type_archive_link( 'post' );
		} elseif ( is_front_page() && is_home() ) {
			return (string) home_url();
		} elseif ( is_year() ) {
			return (string) get_year_link( get_query_var( 'year' ) );
		} elseif ( is_month() ) {
			return (string) get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
		} elseif ( is_day() ) {
			return (string) get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$queried = get_queried_object();
			$link    = is_object( $queried ) ? get_term_link( $queried->term_id, $queried->taxonomy ) : '';
			return is_string( $link ) ? $link : '';
		} elseif ( is_author() ) {
			return (string) get_author_posts_url( get_the_author_meta( 'ID' ) );
		} elseif ( is_search() ) {
			return (string) get_search_link();
		} elseif ( is_archive() ) {
			return (string) get_post_type_archive_link( get_post_type() );
		}
		return (string) get_the_permalink();
	}

	/**
	 * Reglas del punto de corte elegido cuando la hoja no las trae (puntos de corte propios del sitio, «extra» o
	 * portátil): una vez por página y punto de corte.
	 */
	public function breakpoint_style( string $breakpoint, string $layout ): string {
		if ( 'dropdown' === $layout || 'none' === $breakpoint || ! preg_match( '/^[a-z_]+$/', $breakpoint ) ) {
			return '';
		}
		$breakpoints = self::breakpoints();
		if ( ! isset( $breakpoints[ $breakpoint ] ) || ! is_object( $breakpoints[ $breakpoint ] ) ) {
			return '';
		}
		$max = (int) $breakpoints[ $breakpoint ]->get_value();
		if ( $max <= 0 || ( self::SHEET_BREAKPOINTS[ $breakpoint ] ?? 0 ) === $max || isset( self::$printed_breakpoints[ $breakpoint . $max ] ) ) {
			return '';
		}
		self::$printed_breakpoints[ $breakpoint . $max ] = true;
		$c = '.elementor .digi-mm--bp-' . $breakpoint;
		return sprintf( '<style>@media(max-width:%1$dpx){%2$s{--dmm-wrapper-display:none}%2$s .digi-mm__toggle{display:flex}}</style>', $max, $c );
	}

	/** Para las pruebas: olvida los puntos de corte impresos. */
	public static function reset_printed(): void {
		self::$printed_breakpoints = array();
	}

	/**
	 * Repetidor mejorado del núcleo, entrelazado como en PRO: cada fila es un `li` de `.digi-mm__heading` y su contenedor
	 * hijo va en su `.digi-mm__content` (lo coloca `assets/js/mega-menu-editor.js`).
	 */
	protected function get_initial_config(): array {
		return array_merge(
			parent::get_initial_config(),
			array(
				'support_improved_repeaters' => true,
				'target_container'           => array( '.digi-mm__heading' ),
				'node'                       => 'li',
				'is_interlaced'              => true,
			)
		);
	}

	/** Textos para las plantillas del editor (sin escapar: las plantillas escapan). */
	public static function editor_strings(): array {
		return array(
			'menu' => __( 'Menú', 'pro-elements' ),
			'sub'  => self::sub_label( '%s' ),
		);
	}

	/**
	 * Un elemento en el editor: el mismo marcado que `render_item()` (sin el contenedor hijo, que coloca el editor).
	 * Variables: `item`, `index` (desde 1), `elementUid`, `settings`, `permalinkUrl`.
	 */
	protected function item_template(): string {
		$sub = wp_json_encode( self::editor_strings()['sub'], JSON_UNESCAPED_UNICODE );
		return <<<TPL
<#
const url = 'string' === typeof item.item_link ? item.item_link : ( item.item_link && item.item_link.url ) || '',
	hasDropdown = 'yes' === item.item_dropdown_content,
	uid = elementUid + index,
	strip = ( s ) => String( s || '' ).replace( /<[^>]*>/g, '' ).replace( /\s+/g, ' ' ).trim(),
	same = ( a, b ) => { try { const x = new URL( a, location.href ), y = new URL( b, location.href ); return x.hostname.replace( 'www.', '' ) === y.hostname.replace( 'www.', '' ) && x.pathname.replace( /^\/+|\/+$/g, '' ) === y.pathname.replace( /^\/+|\/+$/g, '' ) && x.search === y.search; } catch ( e ) { return false; } },
	current = url.indexOf( '#' ) > -1 ? 'digi-mm__anchor' : ( url && permalinkUrl && same( url, permalinkUrl ) ? 'digi-mm__current' : '' ),
	icon = elementor.helpers.renderIcon( view, item.item_icon, { 'aria-hidden': true }, 'i', 'object' ) || {},
	iconActive = item.item_icon_active && item.item_icon_active.value ? elementor.helpers.renderIcon( view, item.item_icon_active, { 'aria-hidden': true }, 'i', 'object' ) || {} : icon,
	classes = [ 'digi-mm__title', current, hasDropdown ? 'digi-mm__click' : '', settings.hover_animation ? 'elementor-animation-' + settings.hover_animation : '' ].filter( Boolean );
#>
<li class="digi-mm__item">
	<div id="{{ item.element_id || 'digi-mm-title-' + uid }}" class="{{ classes.join( ' ' ) }}">
		<# if ( url ) { #><a class="digi-mm__title-container digi-mm__link" href="{{ elementor.helpers.sanitizeUrl( url ) }}"><# } else { #><div class="digi-mm__title-container"><# } #>
		<# if ( icon.value ) { #><span class="digi-mm__icon"><span class="digi-mm__icon-active">{{{ iconActive.value }}}</span><span class="digi-mm__icon-inactive">{{{ icon.value }}}</span></span><# } #>
		<span class="digi-mm__title-text" data-binding-type="repeater-item" data-binding-repeater-name="menu_items" data-binding-setting="item_title" data-binding-index="{{ index }}" data-binding-dynamic="true">{{{ item.item_title }}}</span>
		<# if ( url ) { #></a><# } else { #></div><# } #>
		<# if ( hasDropdown ) { #><button type="button" class="digi-mm__dropdown-icon" id="digi-mm-dropdown-icon-{{ uid }}" data-tab-index="{{ index }}" aria-expanded="false" aria-controls="digi-mm-content-{{ uid }}" aria-label="{{ {$sub}.replace( '%s', strip( item.item_title ) ) }}"><span class="digi-mm__dropdown-opened">{{{ indicatorActive.value }}}</span><span class="digi-mm__dropdown-closed">{{{ indicator.value }}}</span></button><# } #>
	</div>
	<div class="digi-mm__content"></div>
</li>
TPL;
	}

	/** Cabecera común de las plantillas del editor (iconos del indicador, número del widget, página actual). */
	protected function template_head(): string {
		$permalink = function_exists( 'esc_url' ) ? esc_url( $this->get_permalink_for_current_page() ) : '';
		return "const indicator = elementor.helpers.renderIcon( view, settings.menu_item_icon, { 'aria-hidden': true }, 'i', 'object' ) || {},\n\tindicatorActive = elementor.helpers.renderIcon( view, settings.menu_item_icon_active, { 'aria-hidden': true }, 'i', 'object' ) || {},\n\telementUid = view.getIDInt().toString().substr( 0, 3 ),\n\tpermalinkUrl = '" . $permalink . "';";
	}

	protected function content_template() {
		$menu = wp_json_encode( self::editor_strings()['menu'], JSON_UNESCAPED_UNICODE );
		echo '<# if ( ! settings.menu_items ) { return; } ' . $this->template_head() . " #>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla.
		?>
		<nav class="digi-mm" data-widget-number="{{ elementUid }}" data-digi-open="{{ 'click' === settings.open_on ? 'click' : 'hover' }}"<# if ( 'fadeIn' === settings.open_animation ) { #> data-digi-anim="fadeIn"<# } #><# if ( 'fit_to_content' === settings.content_width ) { #> data-digi-fit="{{ [ 'left', 'right' ].includes( settings.content_horizontal_position ) ? settings.content_horizontal_position : 'center' }}"<# } #><# if ( settings.menu_name ) { #> aria-label="{{ settings.menu_name }}"<# } #>>
			<#
			const toggleNormal = settings.menu_toggle_icon_normal && settings.menu_toggle_icon_normal.value ? settings.menu_toggle_icon_normal : { library: 'eicons', value: 'eicon-menu-bar' },
				toggleActive = settings.menu_toggle_icon_active && settings.menu_toggle_icon_active.value ? settings.menu_toggle_icon_active : { library: 'eicons', value: 'eicon-close' },
				toggleNormalHtml = elementor.helpers.renderIcon( view, toggleNormal, { 'aria-hidden': true }, 'i', 'object' ) || {},
				toggleActiveHtml = elementor.helpers.renderIcon( view, toggleActive, { 'aria-hidden': true }, 'i', 'object' ) || {};
			#>
			<button type="button" class="digi-mm__toggle elementor-clickable<# if ( settings.menu_toggle_icon_hover_animation ) { #> elementor-animation-{{ settings.menu_toggle_icon_hover_animation }}<# } #>" id="digi-mm-toggle-{{ elementUid }}" aria-expanded="false" aria-controls="digi-mm-bar-{{ elementUid }}" aria-label="{{ <?php echo $menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON. ?> }}"><span class="digi-mm__toggle-icon digi-mm__toggle-open">{{{ toggleNormalHtml.value }}}</span><span class="digi-mm__toggle-icon digi-mm__toggle-close">{{{ toggleActiveHtml.value }}}</span></button>
			<div class="digi-mm__wrapper" id="digi-mm-bar-{{ elementUid }}">
				<ul class="digi-mm__heading">
					<# _.each( settings.menu_items, function( item, i ) { const index = i + 1; #>
					<?php echo $this->item_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla. ?>
					<# } ); #>
				</ul>
			</div>
		</nav>
		<?php
	}

	/** Un elemento nuevo del repetidor mejorado (el núcleo lo inserta en `.digi-mm__heading`). */
	protected function content_template_single_repeater_item() {
		echo '<# const settings = view.container.settings.attributes, item = data, index = view.collection.length + 1; ' . $this->template_head() . " #>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla.
		echo $this->item_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla.
	}
}
