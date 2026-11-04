<?php
namespace Digitalisimo\Elements\Navigation\Widgets;

use Digitalisimo\Elements\Navigation\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Menú de WordPress (`digitalisimo-nav-menu`, C3 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c3.md`, frente E):
 * sustituye al «WordPress Menu» de PRO (`modules/nav-menu/widgets/nav-menu.php`, mapa
 * `modules/digitalisimo-convert/maps/nav-menu.php`).
 *
 * - **Controles**: los 56 del widget de PRO y sus 6 grupos, con el mismo nombre, tipo, valor por defecto, opciones,
 *   condiciones y dispositivos (estructura y controles derivados de PRO Elements, GPL); textos en español y selectores
 *   sobre el marcado propio. Las clases que PRO pone en el contenedor del widget (`prefix_class`) son propias:
 *   `digi-nav--align-*`, `digi-nav--dropdown-*`, `digi-nav--stretch`, `digi-nav--text-*`, `digi-nav--toggle`.
 * - **Marcado** (patrón de navegación de divulgación, sin SmartMenus ni jQuery):
 *   `div.digi-nav` › `nav.digi-nav__main.digi-nav__container` (menú principal, `aria-label` = «Nombre del menú»),
 *   `button.digi-nav__toggle` (hamburguesa, `aria-expanded`/`aria-controls`) y `nav.digi-nav__dropdown.digi-nav__container`
 *   (el menú desplegable bajo el punto de corte). Cada elemento con hijos lleva el indicador dentro del enlace
 *   (`span.digi-nav__arrow`, decorativo, como el de PRO) y, después del enlace, `button.digi-nav__sub-toggle` con
 *   `aria-expanded`, `aria-controls` y nombre «Submenú de …»: el script lo coloca sobre el indicador, así el aspecto es el
 *   de PRO y el enlace sigue llevando a su página. Los submenús son `ul.digi-nav__dropdown`.
 * - **Recursos**: `assets/css/nav-menu.css` (diseño, submenús, hamburguesa, desplegable) y, sólo si hay puntero,
 *   `assets/css/nav-menu-pointer.css` (las animaciones del puntero); `assets/js/nav-menu.js` (`defer`, sin dependencias).
 *   Los puntos de corte que no son los predeterminados se imprimen una vez por página, junto al primer menú que los usa.
 */
class Nav_Menu extends \Elementor\Widget_Base {

	const NAME = 'digitalisimo-nav-menu';

	/** Hoja de los punteros: sólo se encola si el menú principal tiene uno. */
	const POINTER_HANDLE = 'digitalisimo-nav-menu-pointer';

	const POINTER_CSS = 'modules/digitalisimo-navigation/assets/css/nav-menu-pointer.css';

	/** Puntos de corte que la hoja ya cubre (los predeterminados del núcleo). */
	const SHEET_BREAKPOINTS = array(
		'mobile' => 767,
		'tablet' => 1024,
	);

	/** @var int Número de menú dentro de la página (ids únicos), como en PRO. */
	protected $nav_menu_index = 1;

	/** @var array<string,bool> Reglas de punto de corte ya impresas en esta página. */
	private static $printed_breakpoints = array();

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Menú de WordPress', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-nav-menu';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'menú', 'menu', 'navegación', 'nav', 'submenú', 'hamburguesa', 'cabecera', 'digitalisimo' );
	}

	/** Como en PRO: sin envoltorio interior con el marcado optimizado. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/**
	 * La hoja propia y, con puntero en el menú principal, la de los punteros. La instancia de registro (editor) declara
	 * las dos.
	 */
	public function get_style_depends(): array {
		$out = Module::style_depends( self::NAME );
		if ( ! self::register_pointer_style() ) {
			return $out;
		}
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) ) {
			$out[] = self::POINTER_HANDLE;
			return $out;
		}
		$s = (array) $this->get_settings();
		if ( 'dropdown' !== ( $s['layout'] ?? 'horizontal' ) && ! in_array( (string) ( $s['pointer'] ?? 'underline' ), array( '', 'none' ), true ) ) {
			$out[] = self::POINTER_HANDLE;
		}
		return $out;
	}

	public function get_script_depends(): array {
		return Module::script_depends( self::NAME );
	}

	/** Registra la hoja de los punteros (idempotente). Devuelve si está disponible. */
	public static function register_pointer_style(): bool {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! is_file( Module::path( self::POINTER_CSS ) ) ) {
			return false;
		}
		if ( ! wp_style_is( self::POINTER_HANDLE, 'registered' ) ) {
			wp_register_style( self::POINTER_HANDLE, plugins_url( self::POINTER_CSS, DIGITALISIMO_ELEMENTS_FILE ), array(), defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false );
		}
		return true;
	}

	protected function get_nav_menu_index() {
		return $this->nav_menu_index++;
	}

	/** Menús del sitio (`slug => nombre`). */
	private function get_available_menus() {
		$options = array();
		foreach ( function_exists( 'wp_get_nav_menus' ) ? (array) wp_get_nav_menus() : array() as $menu ) {
			if ( is_object( $menu ) && isset( $menu->slug ) ) {
				$options[ $menu->slug ] = $menu->name;
			}
		}
		return $options;
	}

	/** Puntos de corte activos del núcleo (`nombre => objeto`), sin el gestor, los predeterminados. */
	private static function breakpoints(): array {
		$plugin = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
		if ( is_object( $plugin ) && isset( $plugin->breakpoints ) && is_object( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ) {
			return (array) $plugin->breakpoints->get_active_breakpoints();
		}
		return array();
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Diseño', 'pro-elements' ),
			)
		);

		$this->add_control(
			'menu_name',
			array(
				'label'   => esc_html__( 'Nombre del menú', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Menú', 'pro-elements' ),
			)
		);

		$menus = $this->get_available_menus();

		if ( ! empty( $menus ) ) {
			$this->add_control(
				'menu',
				array(
					'label'        => esc_html__( 'Menú', 'pro-elements' ),
					'type'         => Controls_Manager::SELECT,
					'options'      => $menus,
					'default'      => array_keys( $menus )[0],
					'save_default' => true,
					'separator'    => 'after',
					'description'  => sprintf(
						/* translators: 1: apertura del enlace, 2: cierre del enlace. */
						esc_html__( 'Los menús se gestionan en la %1$spantalla de Menús%2$s.', 'pro-elements' ),
						sprintf( '<a href="%s" target="_blank">', function_exists( 'admin_url' ) ? admin_url( 'nav-menus.php' ) : '' ),
						'</a>'
					),
				)
			);
		} else {
			$this->add_control(
				'menu',
				array(
					'type'       => Controls_Manager::ALERT,
					'alert_type' => 'info',
					'heading'    => esc_html__( 'El sitio no tiene menús.', 'pro-elements' ),
					'content'    => sprintf(
						/* translators: 1: apertura del enlace, 2: cierre del enlace. */
						esc_html__( 'Crea uno en la %1$spantalla de Menús%2$s.', 'pro-elements' ),
						sprintf( '<a href="%s" target="_blank">', function_exists( 'admin_url' ) ? admin_url( 'nav-menus.php?action=edit&menu=0' ) : '' ),
						'</a>'
					),
					'separator'  => 'after',
				)
			);
		}

		$this->add_control(
			'layout',
			array(
				'label'              => esc_html__( 'Disposición', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'horizontal',
				'options'            => array(
					'horizontal' => esc_html__( 'Horizontal', 'pro-elements' ),
					'vertical'   => esc_html__( 'Vertical', 'pro-elements' ),
					'dropdown'   => esc_html__( 'Desplegable', 'pro-elements' ),
				),
				'frontend_available' => true,
			)
		);

		$start = is_rtl() ? 'end' : 'start';
		$end   = is_rtl() ? 'start' : 'end';

		$this->add_control(
			'align_items',
			array(
				'label'              => esc_html__( 'Alineación', 'pro-elements' ),
				'type'               => Controls_Manager::CHOOSE,
				'options'            => array(
					'start'   => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => "eicon-align-$start-h",
					),
					'center'  => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-align-center-h',
					),
					'end'     => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => "eicon-align-$end-h",
					),
					'justify' => array(
						'title' => esc_html__( 'Estirar', 'pro-elements' ),
						'icon'  => 'eicon-align-stretch-h',
					),
				),
				// Valores antiguos (izquierda y derecha), como en PRO.
				'classes_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'prefix_class'       => 'digi-nav--align-',
				'condition'          => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$this->add_control(
			'pointer',
			array(
				'label'          => esc_html__( 'Puntero', 'pro-elements' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => 'underline',
				'options'        => array(
					'none'        => esc_html__( 'Ninguno', 'pro-elements' ),
					'underline'   => esc_html__( 'Subrayado', 'pro-elements' ),
					'overline'    => esc_html__( 'Línea superior', 'pro-elements' ),
					'double-line' => esc_html__( 'Doble línea', 'pro-elements' ),
					'framed'      => esc_html__( 'Marco', 'pro-elements' ),
					'background'  => esc_html__( 'Fondo', 'pro-elements' ),
					'text'        => esc_html__( 'Texto', 'pro-elements' ),
				),
				'style_transfer' => true,
				'condition'      => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$this->add_control(
			'animation_line',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'     => esc_html__( 'Fundido', 'pro-elements' ),
					'slide'    => esc_html__( 'Deslizar', 'pro-elements' ),
					'grow'     => esc_html__( 'Crecer', 'pro-elements' ),
					'drop-in'  => esc_html__( 'Caer hacia dentro', 'pro-elements' ),
					'drop-out' => esc_html__( 'Caer hacia fuera', 'pro-elements' ),
					'none'     => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => array( 'underline', 'overline', 'double-line' ),
				),
			)
		);

		$this->add_control(
			'animation_framed',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'    => esc_html__( 'Fundido', 'pro-elements' ),
					'grow'    => esc_html__( 'Crecer', 'pro-elements' ),
					'shrink'  => esc_html__( 'Encoger', 'pro-elements' ),
					'draw'    => esc_html__( 'Dibujar', 'pro-elements' ),
					'corners' => esc_html__( 'Esquinas', 'pro-elements' ),
					'none'    => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => 'framed',
				),
			)
		);

		$this->add_control(
			'animation_background',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'                   => esc_html__( 'Fundido', 'pro-elements' ),
					'grow'                   => esc_html__( 'Crecer', 'pro-elements' ),
					'shrink'                 => esc_html__( 'Encoger', 'pro-elements' ),
					'sweep-left'             => esc_html__( 'Barrido a la izquierda', 'pro-elements' ),
					'sweep-right'            => esc_html__( 'Barrido a la derecha', 'pro-elements' ),
					'sweep-up'               => esc_html__( 'Barrido hacia arriba', 'pro-elements' ),
					'sweep-down'             => esc_html__( 'Barrido hacia abajo', 'pro-elements' ),
					'shutter-in-vertical'    => esc_html__( 'Persiana vertical hacia dentro', 'pro-elements' ),
					'shutter-out-vertical'   => esc_html__( 'Persiana vertical hacia fuera', 'pro-elements' ),
					'shutter-in-horizontal'  => esc_html__( 'Persiana horizontal hacia dentro', 'pro-elements' ),
					'shutter-out-horizontal' => esc_html__( 'Persiana horizontal hacia fuera', 'pro-elements' ),
					'none'                   => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => 'background',
				),
			)
		);

		$this->add_control(
			'animation_text',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'grow',
				'options'   => array(
					'grow'   => esc_html__( 'Crecer', 'pro-elements' ),
					'shrink' => esc_html__( 'Encoger', 'pro-elements' ),
					'sink'   => esc_html__( 'Hundir', 'pro-elements' ),
					'float'  => esc_html__( 'Flotar', 'pro-elements' ),
					'skew'   => esc_html__( 'Inclinar', 'pro-elements' ),
					'rotate' => esc_html__( 'Girar', 'pro-elements' ),
					'none'   => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => 'text',
				),
			)
		);

		$icon_prefix = Icons_Manager::is_migration_allowed() ? 'fas ' : 'fa ';

		$this->add_control(
			'submenu_icon',
			array(
				'label'                  => esc_html__( 'Indicador de submenú', 'pro-elements' ),
				'type'                   => Controls_Manager::ICONS,
				'separator'              => 'before',
				'default'                => array(
					'value'   => $icon_prefix . 'fa-caret-down',
					'library' => 'fa-solid',
				),
				'recommended'            => array(
					'fa-solid' => array(
						'chevron-down',
						'angle-down',
						'caret-down',
						'plus',
					),
				),
				'label_block'            => false,
				'skin'                   => 'inline',
				'exclude_inline_options' => array( 'svg' ),
				'frontend_available'     => true,
			)
		);

		$this->add_control(
			'heading_mobile_dropdown',
			array(
				'label'     => esc_html__( 'Desplegable en móvil', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$dropdown_options = array();
		foreach ( self::breakpoints() as $breakpoint_key => $breakpoint_instance ) {
			// Como en PRO: portátil y pantalla ancha no se ofrecen (el desplegable es para dispositivos pequeños).
			if ( in_array( $breakpoint_key, array( 'laptop', 'widescreen' ), true ) ) {
				continue;
			}
			$dropdown_options[ $breakpoint_key ] = sprintf(
				/* translators: 1: punto de corte, 2: operador de comparación, 3: valor en píxeles. */
				esc_html__( '%1$s (%2$s %3$dpx)', 'pro-elements' ),
				$breakpoint_instance->get_label(),
				'>',
				$breakpoint_instance->get_value()
			);
		}
		$dropdown_options['none'] = esc_html__( 'Ninguno', 'pro-elements' );

		$this->add_control(
			'dropdown',
			array(
				'label'        => esc_html__( 'Punto de corte', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'tablet',
				'options'      => $dropdown_options,
				'prefix_class' => 'digi-nav--dropdown-',
				'condition'    => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$this->add_control(
			'full_width',
			array(
				'label'              => esc_html__( 'Ancho completo', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'description'        => esc_html__( 'Estira el desplegable del menú a todo el ancho de la ventana.', 'pro-elements' ),
				'prefix_class'       => 'digi-nav--',
				'return_value'       => 'stretch',
				'frontend_available' => true,
				'condition'          => array(
					'dropdown!' => 'none',
				),
			)
		);

		$this->add_control(
			'text_align',
			array(
				'label'        => esc_html__( 'Alineación del texto', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'aside',
				'options'      => array(
					'aside'  => esc_html__( 'A un lado', 'pro-elements' ),
					'center' => esc_html__( 'Centro', 'pro-elements' ),
				),
				'prefix_class' => 'digi-nav--text-',
				'condition'    => array(
					'dropdown!' => 'none',
				),
			)
		);

		$this->add_control(
			'toggle',
			array(
				'label'              => esc_html__( 'Botón del menú', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'burger',
				'options'            => array(
					''       => esc_html__( 'Ninguno', 'pro-elements' ),
					'burger' => esc_html__( 'Hamburguesa', 'pro-elements' ),
				),
				'prefix_class'       => 'digi-nav--toggle digi-nav--',
				'render_type'        => 'template',
				'frontend_available' => true,
				'condition'          => array(
					'dropdown!' => 'none',
				),
			)
		);

		$this->start_controls_tabs( 'nav_icon_options' );

		$this->start_controls_tab(
			'nav_icon_normal_options',
			array(
				'label'     => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->add_control(
			'toggle_icon_normal',
			array(
				'label'            => esc_html__( 'Icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'label_block'      => false,
				'skin_settings'    => array(
					'inline' => array(
						'none' => array(
							'label' => esc_html__( 'Predeterminado', 'pro-elements' ),
							'icon'  => 'eicon-menu-bar',
						),
						'icon' => array(
							'icon' => 'eicon-star',
						),
					),
				),
				'recommended'      => array(
					'fa-solid'   => array(
						'plus-square',
						'plus',
						'plus-circle',
						'bars',
					),
					'fa-regular' => array(
						'plus-square',
					),
				),
				'condition'        => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'nav_icon_hover_options',
			array(
				'label'     => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
				'condition' => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->add_control(
			'toggle_icon_hover_animation',
			array(
				'label'              => esc_html__( 'Animación al pasar el ratón', 'pro-elements' ),
				'type'               => Controls_Manager::HOVER_ANIMATION,
				'frontend_available' => true,
				'render_type'        => 'template',
				'condition'          => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'nav_icon_active_options',
			array(
				'label'     => esc_html__( 'Activo', 'pro-elements' ),
				'condition' => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->add_control(
			'toggle_icon_active',
			array(
				'label'            => esc_html__( 'Icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'label_block'      => false,
				'skin_settings'    => array(
					'inline' => array(
						'none' => array(
							'label' => esc_html__( 'Predeterminado', 'pro-elements' ),
							'icon'  => 'eicon-close',
						),
						'icon' => array(
							'icon' => 'eicon-star',
						),
					),
				),
				'recommended'      => array(
					'fa-solid'   => array(
						'window-close',
						'times-circle',
						'times',
						'minus-square',
						'minus-circle',
						'minus',
					),
					'fa-regular' => array(
						'window-close',
						'times-circle',
						'minus-square',
					),
				),
				'condition'        => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'toggle_align',
			array(
				'label'                => esc_html__( 'Alineación del botón', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'default'              => 'center',
				'options'              => array(
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
				),
				'selectors_dictionary' => array(
					'left'   => 'margin-right: auto',
					'center' => 'margin: 0 auto',
					'right'  => 'margin-left: auto',
				),
				'selectors'            => array(
					'{{WRAPPER}} .digi-nav__toggle' => '{{VALUE}}',
				),
				'condition'            => array(
					'toggle!'   => '',
					'dropdown!' => 'none',
				),
				'separator'            => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_main-menu',
			array(
				'label'     => esc_html__( 'Menú principal', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'menu_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .digi-nav__main .digi-nav__link',
			)
		);

		$this->start_controls_tabs( 'tabs_menu_item_style' );

		$this->start_controls_tab(
			'tab_menu_item_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'color_menu_item',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__main .digi-nav__link' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_menu_item_hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			)
		);

		$hover = '{{WRAPPER}} .digi-nav__main .digi-nav__link:hover, {{WRAPPER}} .digi-nav__main .digi-nav__link.digi-nav__active, {{WRAPPER}} .digi-nav__main .digi-nav__link.digi-nav__open, {{WRAPPER}} .digi-nav__main .digi-nav__link:focus';

		$this->add_control(
			'color_menu_item_hover',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					$hover => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
				'condition' => array(
					'pointer!' => 'background',
				),
			)
		);

		$this->add_control(
			'color_menu_item_hover_pointer_bg',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => array(
					$hover => 'color: {{VALUE}}',
				),
				'condition' => array(
					'pointer' => 'background',
				),
			)
		);

		$this->add_control(
			'pointer_color_menu_item_hover',
			array(
				'label'     => esc_html__( 'Color del puntero', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__main:not(.digi-nav--p-framed) .digi-nav__link:before, {{WRAPPER}} .digi-nav__main:not(.digi-nav--p-framed) .digi-nav__link:after' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .digi-nav--p-framed .digi-nav__link:before, {{WRAPPER}} .digi-nav--p-framed .digi-nav__link:after' => 'border-color: {{VALUE}}',
				),
				'condition' => array(
					'pointer!' => array( 'none', 'text' ),
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_menu_item_active',
			array(
				'label' => esc_html__( 'Activo', 'pro-elements' ),
			)
		);

		$this->add_control(
			'color_menu_item_active',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__main .digi-nav__link.digi-nav__active' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'pointer_color_menu_item_active',
			array(
				'label'     => esc_html__( 'Color del puntero', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__main:not(.digi-nav--p-framed) .digi-nav__link.digi-nav__active:before, {{WRAPPER}} .digi-nav__main:not(.digi-nav--p-framed) .digi-nav__link.digi-nav__active:after' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .digi-nav--p-framed .digi-nav__link.digi-nav__active:before, {{WRAPPER}} .digi-nav--p-framed .digi-nav__link.digi-nav__active:after' => 'border-color: {{VALUE}}',
				),
				'condition' => array(
					'pointer!' => array( 'none', 'text' ),
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$divider_condition = array(
			'nav_menu_divider' => 'yes',
			'layout'           => 'horizontal',
		);

		$this->add_control(
			'nav_menu_divider',
			array(
				'label'     => esc_html__( 'Separador', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'condition' => array(
					'layout' => 'horizontal',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-nav-divider-content: "";',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'nav_menu_divider_style',
			array(
				'label'     => esc_html__( 'Estilo', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'solid'  => esc_html__( 'Continuo', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
				),
				'default'   => 'solid',
				'condition' => $divider_condition,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-nav-divider-style: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'nav_menu_divider_weight',
			array(
				'label'      => esc_html__( 'Grosor', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 20,
					),
					'em'  => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'condition'  => $divider_condition,
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-nav-divider-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'nav_menu_divider_height',
			array(
				'label'      => esc_html__( 'Altura', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
					'%'   => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'condition'  => $divider_condition,
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-nav-divider-height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'nav_menu_divider_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'condition' => $divider_condition,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-nav-divider-color: {{VALUE}}',
				),
			)
		);

		// Como en PRO: un separador del panel para resolver condiciones complejas.
		$this->add_control(
			'hr',
			array(
				'type' => Controls_Manager::DIVIDER,
			)
		);

		$this->add_responsive_control(
			'pointer_width',
			array(
				'label'      => esc_html__( 'Grosor del puntero', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 30,
					),
					'em'  => array(
						'max' => 3,
					),
					'rem' => array(
						'max' => 3,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav--p-framed .digi-nav__link:before' => 'border-width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-nav--p-framed.digi-nav--a-draw .digi-nav__link:before' => 'border-width: 0 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-nav--p-framed.digi-nav--a-draw .digi-nav__link:after' => 'border-width: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0',
					'{{WRAPPER}} .digi-nav--p-framed.digi-nav--a-corners .digi-nav__link:before' => 'border-width: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-nav--p-framed.digi-nav--a-corners .digi-nav__link:after' => 'border-width: 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0',
					'{{WRAPPER}} .digi-nav--p-underline .digi-nav__link:after, {{WRAPPER}} .digi-nav--p-overline .digi-nav__link:before, {{WRAPPER}} .digi-nav--p-double-line .digi-nav__link:before, {{WRAPPER}} .digi-nav--p-double-line .digi-nav__link:after' => 'height: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'pointer' => array( 'underline', 'overline', 'double-line', 'framed' ),
				),
			)
		);

		$this->add_responsive_control(
			'padding_horizontal_menu_item',
			array(
				'label'      => esc_html__( 'Relleno horizontal', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
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
					'{{WRAPPER}} .digi-nav__main .digi-nav__link' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'padding_vertical_menu_item',
			array(
				'label'      => esc_html__( 'Relleno vertical', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
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
					'{{WRAPPER}} .digi-nav__main .digi-nav__link' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'menu_space_between',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--digi-nav-gap: calc( {{SIZE}}{{UNIT}} / 2 );',
					'{{WRAPPER}} .digi-nav__main:not(.digi-nav--layout-horizontal) .digi-nav__list > li:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'border_radius_menu_item',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav__link:before' => 'border-radius: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-nav--a-shutter-in-horizontal .digi-nav__link:before' => 'border-radius: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0',
					'{{WRAPPER}} .digi-nav--a-shutter-in-horizontal .digi-nav__link:after' => 'border-radius: 0 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-nav--a-shutter-in-vertical .digi-nav__link:before' => 'border-radius: 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0',
					'{{WRAPPER}} .digi-nav--a-shutter-in-vertical .digi-nav__link:after' => 'border-radius: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'pointer' => 'background',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_dropdown',
			array(
				'label' => esc_html__( 'Desplegable', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'dropdown_description',
			array(
				'raw'             => esc_html__( 'En escritorio afecta a los submenús; en móvil, a todo el menú.', 'pro-elements' ),
				'type'            => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->start_controls_tabs( 'tabs_dropdown_item_style' );

		$this->start_controls_tab(
			'tab_dropdown_item_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'color_dropdown_item',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__dropdown a, {{WRAPPER}} .digi-nav__toggle' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'background_color_dropdown_item',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__dropdown' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_dropdown_item_hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			)
		);

		$dropdown_hover = '{{WRAPPER}} .digi-nav__dropdown a:hover, {{WRAPPER}} .digi-nav__dropdown a:focus, {{WRAPPER}} .digi-nav__dropdown a.digi-nav__active, {{WRAPPER}} .digi-nav__dropdown a.digi-nav__open';

		$this->add_control(
			'color_dropdown_item_hover',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					$dropdown_hover . ', {{WRAPPER}} .digi-nav__toggle:hover, {{WRAPPER}} .digi-nav__toggle:focus' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'background_color_dropdown_item_hover',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					$dropdown_hover => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_dropdown_item_active',
			array(
				'label' => esc_html__( 'Activo', 'pro-elements' ),
			)
		);

		$this->add_control(
			'color_dropdown_item_active',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__dropdown a.digi-nav__active' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'background_color_dropdown_item_active',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__dropdown a.digi-nav__active' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'dropdown_typography',
				'global'    => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'exclude'   => array( 'line_height' ),
				'selector'  => '{{WRAPPER}} .digi-nav__dropdown .digi-nav__link, {{WRAPPER}} .digi-nav__dropdown .digi-nav__sublink',
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'dropdown_border',
				'selector'  => '{{WRAPPER}} .digi-nav__dropdown',
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'dropdown_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav__dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .digi-nav__dropdown li:first-child a' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-top-right-radius: {{RIGHT}}{{UNIT}};',
					'{{WRAPPER}} .digi-nav__dropdown li:last-child a' => 'border-bottom-right-radius: {{BOTTOM}}{{UNIT}}; border-bottom-left-radius: {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'dropdown_box_shadow',
				'exclude'  => array(
					'box_shadow_position',
				),
				'selector' => '{{WRAPPER}} .digi-nav__main .digi-nav__dropdown, {{WRAPPER}} .digi-nav__container.digi-nav__dropdown',
			)
		);

		$this->add_responsive_control(
			'padding_horizontal_dropdown_item',
			array(
				'label'      => esc_html__( 'Relleno horizontal', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'vw' => array(
						'min' => 0,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav__dropdown a' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'padding_vertical_dropdown_item',
			array(
				'label'      => esc_html__( 'Relleno vertical', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
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
					'vh'  => array(
						'min' => 0,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav__dropdown a' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'heading_dropdown_divider',
			array(
				'label'     => esc_html__( 'Separador', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'dropdown_divider',
				'selector' => '{{WRAPPER}} .digi-nav__dropdown li:not(:last-child)',
				'exclude'  => array( 'width' ),
			)
		);

		$this->add_control(
			'dropdown_divider_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
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
					'{{WRAPPER}} .digi-nav__dropdown li:not(:last-child)' => 'border-bottom-width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'dropdown_divider_border!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_top_distance',
			array(
				'label'      => esc_html__( 'Distancia', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => -100,
						'max' => 100,
					),
					'em'  => array(
						'min' => -10,
						'max' => 10,
					),
					'rem' => array(
						'min' => -10,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav__main > .digi-nav__list > li > .digi-nav__dropdown, {{WRAPPER}} .digi-nav__container.digi-nav__dropdown' => 'margin-top: {{SIZE}}{{UNIT}} !important',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_toggle',
			array(
				'label'     => esc_html__( 'Botón del menú', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'toggle!'   => '',
					'dropdown!' => 'none',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_toggle_style' );

		$this->start_controls_tab(
			'tab_toggle_style_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'toggle_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} button.digi-nav__toggle'     => 'color: {{VALUE}}', // Más específico que el color del texto del desplegable.
					'{{WRAPPER}} button.digi-nav__toggle svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__toggle' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_toggle_style_hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			)
		);

		$this->add_control(
			'toggle_color_hover',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} button.digi-nav__toggle:hover, {{WRAPPER}} button.digi-nav__toggle:focus' => 'color: {{VALUE}}',
					'{{WRAPPER}} button.digi-nav__toggle:hover svg, {{WRAPPER}} button.digi-nav__toggle:focus svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color_hover',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-nav__toggle:hover, {{WRAPPER}} .digi-nav__toggle:focus' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'toggle_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 15,
					),
					'em'  => array(
						'max' => 1.5,
					),
					'rem' => array(
						'max' => 1.5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-nav-toggle-size: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'toggle_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 20,
					),
					'em'  => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav__toggle' => 'border-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'toggle_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-nav__toggle' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	/** Etiqueta del botón de un submenú. */
	public static function sub_label( string $title ): string {
		/* translators: %s: título del elemento del menú. */
		return sprintf( __( 'Submenú de %s', 'pro-elements' ), $title );
	}

	/** HTML del indicador de submenú (decorativo), o '' sin icono. */
	public static function indicator( $icon ): string {
		$icon  = is_array( $icon ) ? $icon : array();
		$value = (string) ( $icon['value'] ?? '' );
		if ( '' === $value || is_array( $icon['value'] ) ) {
			return '';
		}
		if ( 'fa ' === substr( $value, 0, 3 ) && Icons_Manager::is_migration_allowed() ) {
			$icon['value'] = 'fas ' . substr( $value, 3 );
		}
		$html = (string) Icons_Manager::try_get_icon_html( $icon, array( 'aria-hidden' => 'true' ) );
		return '' === $html ? '' : '<span class="digi-nav__arrow" aria-hidden="true">' . $html . '</span>';
	}

	protected function render() {
		$available_menus = $this->get_available_menus();
		if ( ! $available_menus ) {
			return;
		}
		$settings = (array) $this->get_active_settings();
		$menu     = (string) ( $settings['menu'] ?? '' );
		$layout   = in_array( $settings['layout'] ?? '', array( 'horizontal', 'vertical', 'dropdown' ), true ) ? $settings['layout'] : 'horizontal';
		$args     = array(
			'echo'        => false,
			'menu'        => $menu,
			'menu_class'  => 'digi-nav__list',
			'menu_id'     => 'menu-' . $this->get_nav_menu_index() . '-' . $this->get_id(),
			'fallback_cb' => '__return_empty_string',
			'container'   => '',
			'digi_nav'    => 'main',
		);
		$indicator = self::indicator( $settings['submenu_icon'] ?? array() );

		$filters = array(
			array( 'nav_menu_link_attributes', array( __CLASS__, 'link_attributes' ), 10, 4 ),
			array( 'nav_menu_submenu_css_class', array( __CLASS__, 'submenu_classes' ), 10, 3 ),
			array( 'nav_menu_item_id', array( __CLASS__, 'no_item_id' ), 10, 4 ),
			array( 'walker_nav_menu_start_el', array( __CLASS__, 'sub_toggle' ), 10, 4 ),
		);
		$title = static function ( $title, $item, $menu_args = null ) use ( $indicator ) {
			if ( '' === $indicator || ! is_object( $menu_args ) || empty( $menu_args->digi_nav ) || ! in_array( 'menu-item-has-children', (array) ( $item->classes ?? array() ), true ) ) {
				return $title;
			}
			return $title . $indicator;
		};
		$filters[] = array( 'nav_menu_item_title', $title, 10, 3 );
		foreach ( $filters as $filter ) {
			add_filter( $filter[0], $filter[1], $filter[2], $filter[3] );
		}
		$menu_html = (string) wp_nav_menu( $args );
		// El menú del desplegable (bajo el punto de corte): la misma lista con otro id.
		$args['menu_id']  = 'menu-' . $this->get_nav_menu_index() . '-' . $this->get_id();
		$args['digi_nav'] = 'dropdown';
		$dropdown_html    = (string) wp_nav_menu( $args );
		foreach ( $filters as $filter ) {
			remove_filter( $filter[0], $filter[1], $filter[2] );
		}
		if ( '' === trim( $menu_html ) ) {
			return;
		}

		$label = (string) ( $settings['menu_name'] ?? '' );
		$id    = (string) $this->get_id();
		echo $this->breakpoint_style( (string) ( $settings['dropdown'] ?? 'tablet' ), $layout ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS generado con números.
		echo '<div class="digi-nav">';
		if ( 'dropdown' !== $layout ) {
			$classes = array( 'digi-nav__main', 'digi-nav__container', 'digi-nav--layout-' . $layout );
			$pointer = (string) ( $settings['pointer'] ?? '' );
			if ( '' !== $pointer ) {
				$classes[] = 'digi-nav--p-' . $pointer;
				// La animación del puntero elegido (la primera clave `animation*` con valor, como en PRO).
				foreach ( $settings as $key => $value ) {
					if ( 0 === strpos( (string) $key, 'animation' ) && is_string( $value ) && '' !== $value ) {
						$classes[] = 'digi-nav--a-' . $value;
						break;
					}
				}
			}
			printf( '<nav class="%s"%s>%s</nav>', esc_attr( implode( ' ', $classes ) ), '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '', $menu_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu escapa.
		}
		$this->render_menu_toggle( $settings, 'digi-nav-dd-' . $id );
		printf( '<nav class="digi-nav__dropdown digi-nav__container" id="%s"%s>%s</nav>', esc_attr( 'digi-nav-dd-' . $id ), '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '', $dropdown_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu escapa.
		echo '</div>';
	}

	/**
	 * Reglas del punto de corte elegido cuando la hoja no las trae (puntos de corte propios del sitio o los «extra»): una
	 * vez por página y punto de corte, con los mismos selectores que la hoja.
	 */
	public function breakpoint_style( string $dropdown, string $layout ): string {
		if ( 'dropdown' === $layout || 'none' === $dropdown || '' === $dropdown || ! preg_match( '/^[a-z_]+$/', $dropdown ) ) {
			return '';
		}
		$breakpoints = self::breakpoints();
		if ( ! isset( $breakpoints[ $dropdown ] ) || ! is_object( $breakpoints[ $dropdown ] ) ) {
			return '';
		}
		$max = (int) $breakpoints[ $dropdown ]->get_value();
		if ( $max <= 0 || ( self::SHEET_BREAKPOINTS[ $dropdown ] ?? 0 ) === $max || isset( self::$printed_breakpoints[ $dropdown . $max ] ) ) {
			return '';
		}
		self::$printed_breakpoints[ $dropdown . $max ] = true;
		$c = '.digi-nav--dropdown-' . $dropdown;
		return sprintf( '<style>@media(max-width:%1$dpx){%3$s .digi-nav__main{display:none}}@media(min-width:%2$dpx){%3$s .digi-nav__toggle,%3$s .digi-nav__container.digi-nav__dropdown{display:none}}</style>', $max, $max + 1, $c );
	}

	/** Para las pruebas: olvida los puntos de corte impresos. */
	public static function reset_printed(): void {
		self::$printed_breakpoints = array();
	}

	/**
	 * Hamburguesa: botón nativo con `aria-expanded` y `aria-controls` (PRO usaba un `div` con `role="button"`), con los
	 * iconos de abrir y cerrar de PRO (o los elegidos).
	 */
	private function render_menu_toggle( array $settings, string $controls ): void {
		if ( 'burger' !== ( $settings['toggle'] ?? '' ) ) {
			return;
		}
		$animation = ! empty( $settings['toggle_icon_hover_animation'] ) ? ' elementor-animation-' . sanitize_html_class( (string) $settings['toggle_icon_hover_animation'] ) : '';
		$edit      = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && is_object( \Elementor\Plugin::$instance->editor ) && method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
		printf(
			'<button type="button" class="%s" aria-expanded="false" aria-controls="%s" aria-label="%s">',
			esc_attr( 'digi-nav__toggle' . ( $edit ? ' elementor-clickable' : '' ) ),
			esc_attr( $controls ),
			esc_attr__( 'Menú', 'pro-elements' )
		);
		$icons = array(
			'open'  => ! empty( $settings['toggle_icon_normal']['value'] ) ? $settings['toggle_icon_normal'] : array(
				'library' => 'eicons',
				'value'   => 'eicon-menu-bar',
			),
			'close' => ! empty( $settings['toggle_icon_active']['value'] ) ? $settings['toggle_icon_active'] : array(
				'library' => 'eicons',
				'value'   => 'eicon-close',
			),
		);
		foreach ( $icons as $state => $icon ) {
			$class = 'digi-nav__toggle-' . $state . $animation;
			if ( 'svg' === ( $icon['library'] ?? '' ) ) {
				echo '<span class="' . esc_attr( $class ) . '">';
				Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				echo '</span>';
			} else {
				Icons_Manager::render_icon(
					$icon,
					array(
						'aria-hidden' => 'true',
						'class'       => $class,
					)
				);
			}
		}
		echo '</button>';
	}

	/** Filtro `nav_menu_link_attributes`: clases propias del enlace (nivel, actual, ancla). */
	public static function link_attributes( $atts, $item, $args = null, $depth = 0 ) {
		if ( ! is_object( $args ) || empty( $args->digi_nav ) ) {
			return $atts;
		}
		$atts      = (array) $atts;
		$classes   = $depth ? 'digi-nav__sublink' : 'digi-nav__link';
		$is_anchor = false !== strpos( (string) ( $atts['href'] ?? '' ), '#' );
		if ( ! $is_anchor && in_array( 'current-menu-item', (array) ( $item->classes ?? array() ), true ) ) {
			$classes .= ' digi-nav__active';
		}
		if ( $is_anchor ) {
			$classes .= ' digi-nav__anchor';
		}
		$atts['class'] = empty( $atts['class'] ) ? $classes : $atts['class'] . ' ' . $classes;
		return $atts;
	}

	/** Filtro `nav_menu_submenu_css_class`. */
	public static function submenu_classes( $classes, $args = null, $depth = 0 ) {
		if ( is_object( $args ) && ! empty( $args->digi_nav ) ) {
			$classes   = (array) $classes;
			$classes[] = 'digi-nav__dropdown';
		}
		return $classes;
	}

	/** Filtro `nav_menu_item_id`: sin id en los elementos (el menú se pinta dos veces), como en PRO. */
	public static function no_item_id( $id, $item = null, $args = null, $depth = 0 ) {
		return is_object( $args ) && ! empty( $args->digi_nav ) ? '' : $id;
	}

	/**
	 * Filtro `walker_nav_menu_start_el`: tras el enlace de un elemento con hijos, el botón que abre su submenú (el script
	 * enlaza `aria-controls` con la lista que sigue y lo coloca sobre el indicador).
	 */
	public static function sub_toggle( $output, $item, $depth = 0, $args = null ) {
		if ( ! is_object( $args ) || empty( $args->digi_nav ) || ! in_array( 'menu-item-has-children', (array) ( $item->classes ?? array() ), true ) ) {
			return $output;
		}
		$title = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) ( $item->title ?? '' ) ) ) );
		return $output . '<button type="button" class="digi-nav__sub-toggle" aria-expanded="false" aria-label="' . esc_attr( self::sub_label( $title ) ) . '"></button>';
	}

	public function render_plain_content() {}

	/** Exportación (kits): el id del menú junto a su slug, como en PRO. */
	public function on_export( $element ) {
		$slug        = $element['settings']['menu'] ?? '';
		$menu_object = function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $slug ) : null;
		if ( ! $menu_object instanceof \WP_Term ) {
			unset( $element['settings']['menu'] );
			return $element;
		}
		$menu_id = $menu_object->term_id ?? 0;
		if ( ! empty( $menu_id ) ) {
			$element['settings']['menu_id'] = $menu_id;
		}
		return $element;
	}

	/**
	 * Importación (kits): el slug del menú importado a partir de su id (un menú con el mismo slug llega como
	 * «…-duplicate»), como en PRO.
	 */
	public static function on_import_update_dynamic_content( array $element_config, array $data, $controls = null ): array {
		$old_menu_id = $element_config['settings']['menu_id'] ?? 0;
		if ( empty( $old_menu_id ) ) {
			return $element_config;
		}
		$new_menu_id = $data['term_ids'][ $old_menu_id ] ?? 0;
		$menu_object = $new_menu_id && function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $new_menu_id ) : null;
		$new_slug    = is_object( $menu_object ) ? (string) ( $menu_object->slug ?? '' ) : '';
		if ( '' !== $new_slug ) {
			$element_config['settings']['menu'] = $new_slug;
		}
		unset( $element_config['settings']['menu_id'] );
		return $element_config;
	}
}
