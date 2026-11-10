<?php
/**
 * Menú con submenús de contenido (`digitalisimo-mega-menu`), el widget propio que sustituye al «Menu» anidado de PRO
 * (C3 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c3.md, frente E), y su mapa
 * `modules/digitalisimo-convert/maps/mega-menu.php`:
 * - registro y recursos (tres hojas ≤ 4 KB, sin `--e-global-*`, `url()` ni clases de PRO; script ≤ 6 KB sin jQuery que
 *   abre con `mouseenter` como PRO, script del «Desplazamiento horizontal» ≤ 2,5 KB sólo si algún dispositivo lo activa o
 *   en el editor, registrado con `defer`, y script del editor sobre `nested-elements`);
 * - controles: se cargan a la vez el widget de PRO y el propio, y cada control de PRO (también los del repetidor) tiene
 *   uno propio con el mismo nombre, tipo (salvo «Animación», un selector con los mismos valores), valor por defecto
 *   (salvo los textos, que se traducen), opciones, condiciones, dispositivos, sección y pestaña, y selectores con las
 *   mismas propiedades sobre el marcado propio; los grupos, igual;
 * - cobertura del mapa: cada control de PRO está declarado una sola vez, sin aproximaciones;
 * - marcado del servidor: hamburguesa, elementos con enlace o sin él, icono, botón de submenú con `aria-expanded`,
 *   `aria-controls` y nombre «Submenú de …», contenido con el contenedor hijo (id, número, `aria-labelledby`; sólo el hijo
 *   directo), elemento actual y ancla, clic, animación, ajuste al contenido, puntos de corte propios y escape;
 * - plantilla del editor: el mismo marcado (clases, ids y atributos) que el servidor;
 * - conversión con `Converter::tree()` de menús de PRO reales con sus hijos intactos: ningún `pro.unmapped`, cada clave
 *   cae en un control propio y lo convertido se pinta;
 * - `tests/fixtures/mega-menu.html` (la usa tests/elements-pro-mega-menu-chrome.mjs) es el marcado de hoy (`--write`).
 * Uso: node php-wasm-cli tests/elements-pro-mega-menu.php [--write]
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Plugin { public static $instance; public $experiments; public $breakpoints; public $editor; }
	class Controls_Manager {
		const TEXT = 'text'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading';
		const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const ICONS = 'icons'; const RAW_HTML = 'raw_html';
		const URL = 'url'; const HOVER_ANIMATION = 'hover_animation'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Icons_Manager {
		public static function try_get_icon_html( $icon, $attributes = array(), $tag = 'i' ) {
			if ( empty( $icon['library'] ) || empty( $icon['value'] ) ) {
				return '';
			}
			return '<i class="' . htmlspecialchars( (string) $icon['value'], ENT_QUOTES ) . '" aria-hidden="true"></i>';
		}
		public static function render_icon( $icon, $attributes = array(), $tag = 'i' ) { echo self::try_get_icon_html( $icon, $attributes, $tag ); return true; }
	}
	class Control_Hover_Animation {}
	/** Registro de controles común: el de PRO y el propio pasan por aquí. */
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $children = array();
		public $attrs    = array();
		public $type     = false;
		protected $section = '';
		protected $tab = '';
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function is_type_instance() { return $this->type; }
		public function get_children() { return $this->children; }
		public function get_id() { return 'abc1234'; }
		public function get_widget_number(): string { return '123'; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name ) {}
		public function start_controls_tab( $name, $args ) { $this->tab = $name; }
		public function end_controls_tab() { $this->tab = ''; }
		public function end_controls_tabs() { $this->tab = ''; }
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( '_section' => $this->section, '_tab' => $this->tab ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type, '_section' => $this->section, '_tab' => $this->tab ) + $args; $this->order[] = '@' . $args['name']; }
		public function remove_render_attribute( $key ) { unset( $this->attrs[ $key ] ); }
		public function add_render_attribute( $key, $attr, $value = null ) {
			if ( is_array( $attr ) ) {
				foreach ( $attr as $k => $v ) { $this->add_render_attribute( $key, $k, $v ); }
				return;
			}
			$this->attrs[ $key ][ $attr ] = array_merge( $this->attrs[ $key ][ $attr ] ?? array(), (array) $value );
		}
		public function add_link_attributes( $key, array $url ) {
			if ( ! empty( $url['url'] ) ) { $this->add_render_attribute( $key, 'href', $url['url'] ); }
			if ( ! empty( $url['is_external'] ) ) { $this->add_render_attribute( $key, 'target', '_blank' ); }
			if ( ! empty( $url['nofollow'] ) ) { $this->add_render_attribute( $key, 'rel', 'nofollow' ); }
		}
		public function get_render_attribute_string( $key ) {
			$out = array();
			foreach ( $this->attrs[ $key ] ?? array() as $k => $v ) { $out[] = $k . '="' . htmlspecialchars( implode( ' ', $v ), ENT_QUOTES ) . '"'; }
			return implode( ' ', $out );
		}
		protected function get_initial_config() { return array(); }
	}
	class Repeater extends Widget_Base {
		public function get_controls() { return $this->controls; }
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace Elementor\Core\Breakpoints { class Manager {} }
namespace Elementor\Modules\NestedElements\Base {
	abstract class Widget_Nested_Base extends \Elementor\Widget_Base {
		abstract protected function get_default_children_elements();
		abstract protected function get_default_repeater_title_setting_key();
		protected function get_default_children_title() { return 'Item #%d'; }
		protected function get_default_children_placeholder_selector() { return ''; }
		protected function get_default_children_container_placeholder_selector() { return ''; }
		protected function get_initial_config() {
			return array( 'defaults' => array( 'elements' => $this->get_default_children_elements(), 'elements_placeholder_selector' => $this->get_default_children_placeholder_selector(), 'child_container_placeholder_selector' => $this->get_default_children_container_placeholder_selector() ), 'support_nesting' => true );
		}
	}
}
namespace Elementor\Modules\NestedElements\Controls {
	class Control_Nested_Repeater { const CONTROL_TYPE = 'nested-elements-repeater'; }
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace ElementorPro\Base {
	trait Base_Widget_Trait {}
	abstract class Widget_Nested_Base_Pro extends \Elementor\Modules\NestedElements\Base\Widget_Nested_Base {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['mm'] = array( 'filters' => array(), 'rtl' => false, 'features' => array( 'container' => true, 'nested-elements' => true ), 'styles' => array(), 'permalink' => 'https://ejemplo.test/servicios/' );
	function __( $t, $d = '' ) { return $t; }
	function esc_html__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_html_x( $t, $c, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<b><strong><em><i><span><br>' ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
	function wp_parse_url( $u ) { return parse_url( $u ); }
	function is_rtl() { return $GLOBALS['mm']['rtl']; }
	function plugins_url( $p, $f ) { return 'https://ejemplo.test/wp-content/plugins/digitalisimo-elements/' . $p; }
	function wp_style_is( $h, $s ) { return isset( $GLOBALS['mm']['styles'][ $h ] ); }
	function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['mm']['styles'][ $h ] = $src; return true; }
	function wp_script_is( $h, $s ) { return isset( $GLOBALS['mm']['scripts'][ $h ] ); }
	function wp_register_script( $h, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['mm']['scripts'][ $h ] = array( $src, $deps, $args ); return true; }
	function is_front_page() { return false; }
	function is_home() { return false; }
	function is_year() { return false; }
	function is_month() { return false; }
	function is_day() { return false; }
	function is_category() { return false; }
	function is_tag() { return false; }
	function is_tax() { return false; }
	function is_author() { return false; }
	function is_search() { return false; }
	function is_archive() { return false; }
	function get_the_permalink() { return $GLOBALS['mm']['permalink']; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['mm']['filters'][ $hook ][] = $cb; }
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['mm']['filters'][ $hook ] ?? array() as $i => $f ) {
			if ( $f === $cb ) {
				unset( $GLOBALS['mm']['filters'][ $hook ][ $i ] );
			}
		}
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['mm']['filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$args );
		}
		return $value;
	}
	function check_mm( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	/** Contenedor hijo de prueba: pinta `div.e-con` con los atributos que le añada el filtro del núcleo, y su interior. */
	class MM_Child {
		public $attrs = array();
		public $html;
		public $inner;
		public function __construct( $html, $inner = null ) { $this->html = $html; $this->inner = $inner; }
		public function get_data() { return array( 'elements' => '' === $this->html ? array() : array( array( 'elType' => 'widget' ) ) ); }
		public function add_render_attribute( $key, $attrs ) { $this->attrs = array_merge( $this->attrs, $attrs ); }
		public function print_element() {
			$this->attrs = array();
			if ( ! apply_filters( 'elementor/frontend/container/should_render', true, $this ) ) {
				return;
			}
			$out = '<div class="e-con"';
			foreach ( $this->attrs as $name => $value ) {
				$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}
			echo $out . '>' . $this->html;
			if ( $this->inner ) {
				$this->inner->print_element();
			}
			echo '</div>';
		}
	}
	class MM_Breakpoint {
		private $label;
		private $value;
		public function __construct( $label, $value ) { $this->label = $label; $this->value = $value; }
		public function get_label() { return $this->label; }
		public function get_value() { return $this->value; }
	}
	$GLOBALS['mm']['breakpoints'] = array( 'mobile' => new MM_Breakpoint( 'Móvil vertical', 767 ), 'tablet' => new MM_Breakpoint( 'Tableta vertical', 1024 ) );

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-navigation/class-module.php';
	\Digitalisimo\Elements\Navigation\Module::init();
	require $root . 'modules/digitalisimo-navigation/widgets/class-mega-menu.php';
	require $root . 'modules/mega-menu/traits/url-helper-trait.php';
	require $root . 'modules/mega-menu/controls/control-menu-dropdown-animation.php';
	require $root . 'modules/mega-menu/widgets/mega-menu.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return ! empty( $GLOBALS['mm']['features'][ $f ] ); } };
	\Elementor\Plugin::$instance->breakpoints = new class() { public function get_active_breakpoints() { return $GLOBALS['mm']['breakpoints']; } };

	use Digitalisimo\Elements\Navigation\Module;
	use Digitalisimo\Elements\Navigation\Widgets\Mega_Menu;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Translator;

	$controls_of = static function ( $widget ) {
		( digi_test_accessible( new ReflectionMethod( $widget, 'register_controls' ) ) )->invoke( $widget );
		return $widget;
	};
	$own = $controls_of( new Mega_Menu() );
	$pro = $controls_of( new \ElementorPro\Modules\MegaMenu\Widgets\Mega_Menu() );
	$defaults = static function ( $widget ) {
		$out = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $name ] = $control['default'];
			}
		}
		return $out;
	};
	$render = static function ( array $settings, array $kids = array() ) use ( $own, $defaults ) {
		$widget           = new Mega_Menu();
		$widget->settings = $settings + $defaults( $own );
		$widget->children = $kids;
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $widget, 'render' ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	$rows = static function ( array $titles, array $extra = array() ) {
		$out = array();
		foreach ( $titles as $i => $title ) {
			$out[] = ( $extra[ $i ] ?? array() ) + array( '_id' => 'r' . ( $i + 1 ), 'item_title' => $title, 'item_link' => array( 'url' => '' ), 'item_dropdown_content' => 'no', 'item_icon' => array( 'value' => '', 'library' => '' ), 'item_icon_active' => array( 'value' => '', 'library' => '' ), 'element_id' => '' );
		}
		return $out;
	};

	// 1. Registro y recursos.
	check_mm( 'digitalisimo-mega-menu' === $own->get_name() && 'Menú con submenús de contenido' === $own->get_title() && array( 'digitalisimo' ) === $own->get_categories() && 'eicon-mega-menu' === $own->get_icon(), 'Nombre, título, icono y categoría propios.' );
	check_mm( isset( Module::WIDGETS['digitalisimo-mega-menu'] ) && Mega_Menu::class === Module::WIDGETS['digitalisimo-mega-menu']['class'] && in_array( 'digitalisimo-mega-menu', Module::NESTED, true ) && 'Menú con submenús de contenido' === Module::TITLES['digitalisimo-mega-menu'], 'El módulo de navegación registra el widget como anidado.' );
	check_mm( true === $own->show_in_panel() && true === $own->has_widget_inner_wrapper(), 'Con contenedores, en el panel; sin el marcado optimizado, con envoltorio interior.' );
	$GLOBALS['mm']['features'] = array( 'e_optimized_markup' => true );
	check_mm( false === $own->show_in_panel() && false === $own->has_widget_inner_wrapper(), 'Sin contenedores, fuera del panel (como PRO); con el marcado optimizado, sin envoltorio.' );
	$GLOBALS['mm']['features'] = array( 'container' => true, 'nested-elements' => true );
	check_mm( array( 'digitalisimo-mega-menu', 'digitalisimo-mega-menu-items', 'digitalisimo-mega-menu-dropdown' ) === $own->get_style_depends() && array( 'digitalisimo-mega-menu' ) === $own->get_script_depends() && isset( $GLOBALS['mm']['styles']['digitalisimo-mega-menu-items'], $GLOBALS['mm']['styles']['digitalisimo-mega-menu-dropdown'] ), 'Tres hojas (registradas) y un script, propios.' );
	$css_all = '';
	foreach ( array( 'mega-menu' => Module::WIDGETS['digitalisimo-mega-menu']['css'] ) + Mega_Menu::SHEETS as $handle => $file ) {
		$css = (string) file_get_contents( $root . $file );
		check_mm( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$handle: la hoja pesa " . strlen( $css ) . ' B (tope 4 096).' );
		check_mm( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/e-n-menu|--n-menu|elementor-widget-n-menu|\.e-current|\.e-active|\.e-click/', $css ), "$handle: sin el kit, url() ni clases de PRO." );
		if ( preg_match( '/transition|animation/', $css ) ) {
			check_mm( str_contains( $css, 'prefers-reduced-motion' ), "$handle: anima sin respetar el movimiento reducido." );
		}
		$css_all .= $css;
	}
	check_mm( str_contains( $css_all, ':focus-visible{outline:2px solid currentColor;outline-offset:2px}' ) && str_contains( $css_all, 'width:max(100%,24px);height:max(100%,24px)' ), 'Foco visible de 2 px y objetivos de 24 × 24 px como mínimo en los botones.' );
	$js        = (string) file_get_contents( $root . Module::WIDGETS['digitalisimo-mega-menu']['js'] );
	$editor_js = (string) file_get_contents( $root . 'modules/digitalisimo-navigation/assets/js/mega-menu-editor.js' );
	check_mm( strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|elementorProFrontend|swiper/i', $js ) && str_contains( $js, "'aria-expanded'" ) && str_contains( $js, 'Escape' ) && str_contains( $js, 'frontend/element_ready/digitalisimo-mega-menu.default' ) && str_contains( $js, 'digi-mm-go' ), 'Script ≤ 6 KB sin jQuery: aria-expanded, Escape, reinicio en el editor y apertura desde el panel (' . strlen( $js ) . ' B).' );
	// «Abrir con: ratón» con los eventos de ratón que PRO escucha (mouseenter/mouseleave): también los que el navegador emula
	// tras un toque, así que en una pantalla táctil por encima del punto de corte de tableta el toque en el título abre.
	check_mm( str_contains( $js, "it.li.addEventListener('mouseenter', () => hover() && show(it));" ) && str_contains( $js, "it.li.addEventListener('mouseleave'," ) && ! str_contains( $js, 'pointerType' ), '«Abrir con: ratón» escucha mouseenter/mouseleave, como PRO (el toque emulado también abre).' );
	// «Desplazamiento horizontal» (posición inicial y arrastre de PRO): script aparte, sólo cuando algún dispositivo lo activa.
	$scroll_js = (string) file_get_contents( $root . Mega_Menu::SCRIPTS['digitalisimo-mega-menu-scroll'] );
	check_mm( strlen( $scroll_js ) > 0 && strlen( $scroll_js ) <= 2560 && ! preg_match( '/jQuery|\$\(|elementorProFrontend/i', $scroll_js ) && str_contains( $scroll_js, "'mousedown'" ) && str_contains( $scroll_js, "'mousemove'" ) && str_contains( $scroll_js, 'scrollLeft' ) && str_contains( $scroll_js, "'--dmm-heading-justify-content'" ) && str_contains( $scroll_js, "'start'" ) && str_contains( $scroll_js, 'frontend/element_ready/digitalisimo-mega-menu.default' ), 'Script del desplazamiento horizontal ≤ 2,5 KB sin jQuery: arrastre, posición inicial y reinicio en el editor (' . strlen( $scroll_js ) . ' B).' );
	check_mm( str_contains( $css_all, '.digi-mm__heading:active{cursor:grabbing}' ) && ! str_contains( $css_all, '.digi-mm__scroll{' ) && str_contains( $css_all, '.digi-mm__scroll-active:before{content:"";inset:0 -1000vw;position:absolute;z-index:2}' ), 'Arrastre: cursor de agarre al pulsar sobre la fila de cualquier menú (lo pone la hoja: PRO lo hace en todos) y velo que evita abrir un enlace al soltar, como PRO.' );
	$scripts = static function ( array $s, $type = false ) {
		$w           = new Mega_Menu();
		$w->settings = $s;
		$w->type     = $type;
		return implode( ',', $w->get_script_depends() );
	};
	$GLOBALS['mm']['scripts'] = array();
	check_mm( 'digitalisimo-mega-menu' === $scripts( array() ) && 'digitalisimo-mega-menu' === $scripts( array( 'horizontal_scroll' => 'disable', 'horizontal_scroll_mobile' => 'disable' ) ) && 'digitalisimo-mega-menu' === $scripts( array( 'item_layout' => 'dropdown', 'horizontal_scroll' => 'enable' ) ), 'Sin «Desplazamiento horizontal» (o sólo desplegable): sólo el script propio.' );
	check_mm( 'digitalisimo-mega-menu,digitalisimo-mega-menu-scroll' === $scripts( array( 'horizontal_scroll' => 'enable' ) ) && 'digitalisimo-mega-menu,digitalisimo-mega-menu-scroll' === $scripts( array( 'horizontal_scroll_mobile' => 'enable' ) ) && 'digitalisimo-mega-menu,digitalisimo-mega-menu-scroll' === $scripts( array(), true ), 'Activado en cualquier dispositivo, o en el editor: también el del desplazamiento.' );
	$reg = $GLOBALS['mm']['scripts']['digitalisimo-mega-menu-scroll'] ?? null;
	check_mm( is_array( $reg ) && str_ends_with( $reg[0], 'modules/digitalisimo-navigation/assets/js/mega-menu-scroll.js' ) && array() === $reg[1] && array( 'in_footer' => true, 'strategy' => 'defer' ) === $reg[2], 'El script del desplazamiento se registra con defer, en el pie y sin dependencias.' );
	// Recursos de página del núcleo (`_elementor_page_assets`) y caché de elementos: el núcleo encola por su handle lo que
	// guardó, sin instanciar el widget, y un handle sin registrar no se imprime. Las hojas y el script adicionales se
	// registran también con los del módulo (`Module::register_assets()`, en `elementor/frontend/after_register_*`).
	$GLOBALS['mm']['features'] = array( 'container' => true, 'nested-elements' => true );
	$GLOBALS['mm']['styles']   = array();
	$GLOBALS['mm']['scripts']  = array();
	Module::register_assets();
	check_mm( array( 'digitalisimo-mega-menu', 'digitalisimo-mega-menu-items', 'digitalisimo-mega-menu-dropdown' ) === array_values( array_intersect( array_keys( $GLOBALS['mm']['styles'] ), array( 'digitalisimo-mega-menu', 'digitalisimo-mega-menu-items', 'digitalisimo-mega-menu-dropdown' ) ) ) && isset( $GLOBALS['mm']['scripts']['digitalisimo-mega-menu'], $GLOBALS['mm']['scripts']['digitalisimo-mega-menu-scroll'] ) && array( 'in_footer' => true, 'strategy' => 'defer' ) === $GLOBALS['mm']['scripts']['digitalisimo-mega-menu-scroll'][2], 'Las hojas y el script adicionales se registran antes de pintar, sin instanciar el widget (recursos de página y caché de elementos del núcleo): ' . implode( ',', array_keys( $GLOBALS['mm']['styles'] + $GLOBALS['mm']['scripts'] ) ) );
	check_mm( strlen( $editor_js ) <= 6144 && ! preg_match( '/jQuery|elementorPro/i', $editor_js ) && str_contains( $editor_js, "'digitalisimo-mega-menu'" ) && str_contains( $editor_js, 'attachBuffer' ) && str_contains( $editor_js, 'elementor/nested-container/atomic-repeater' ) && str_contains( $editor_js, 'digi-mm-go' ), 'Script del editor: registra el tipo, entrelaza los hijos, abre el elemento que se edita y repinta tras el repetidor.' );
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-navigation/widgets/class-mega-menu.php' );
	foreach ( array( $source, $css_all, $js, $editor_js, (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/mega-menu.php' ) ) as $text ) {
		check_mm( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $text ), 'Sin nombres de otros proveedores (guarda de build-packages.sh).' );
	}
	check_mm( ! preg_match( "/'elementor-pro'|e-n-menu|--n-menu|ElementorPro/", $source ), 'El widget propio no usa el dominio, las clases ni las variables de PRO.' );
	$config = ( digi_test_accessible( new ReflectionMethod( $own, 'get_initial_config' ) ) )->invoke( $own );
	check_mm( true === $config['support_improved_repeaters'] && array( '.digi-mm__heading' ) === $config['target_container'] && 'li' === $config['node'] && true === $config['is_interlaced'] && '.digi-mm__heading' === $config['defaults']['elements_placeholder_selector'] && '.digi-mm__content' === $config['defaults']['child_container_placeholder_selector'] && 3 === count( $config['defaults']['elements'] ) && 'Elemento n.º 1' === $config['defaults']['elements'][0]['settings']['_title'], 'Repetidor mejorado entrelazado como PRO; tres elementos nuevos.' );

	// 2. Un control propio por cada control de PRO.
	$same = static function ( $key, $a, $b ) { return ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ); };
	$vars = static function ( $v ) { return str_replace( array( 'elementor-widget-digi-mega-menu', '.digi-mm__current', '.digi-mm__', '.digi-mm', '--dmm-', 'digi-mm--' ), array( 'elementor-widget-n-menu', '.e-current', '.e-n-menu-', '.e-n-menu', '--n-menu-', 'e-' ), (string) $v ); };
	check_mm( array_keys( $pro->controls ) === array_keys( $own->controls ), 'Los mismos controles en el mismo orden. Sobran: ' . implode( ', ', array_diff( array_keys( $own->controls ), array_keys( $pro->controls ) ) ) . ' · faltan: ' . implode( ', ', array_diff( array_keys( $pro->controls ), array_keys( $own->controls ) ) ) );
	check_mm( $pro->order === $own->order, 'Los mismos controles y grupos en el mismo orden.' );
	$compare = static function ( array $p, array $o, string $name ) use ( $same, $vars ) {
		foreach ( array( 'condition', 'conditions', 'responsive', 'separator', 'frontend_available', 'render_type', 'size_units', 'range', 'label_block', 'return_value', 'skin', 'skin_settings', 'recommended', 'fa4compatibility', 'style_transfer', 'content_classes', 'global', 'tablet_default', 'mobile_default', 'placeholder', 'dynamic', 'ai', '_section', '_tab' ) as $key ) {
			if ( 'skin_settings' === $key && isset( $p[ $key ] ) ) {
				continue; // Etiquetas del panel, traducidas.
			}
			if ( 'placeholder' === $key && is_string( $p[ $key ] ?? null ) ) {
				continue;
			}
			check_mm( $same( $key, $p, $o ), "$name: «{$key}» distinto de PRO (" . json_encode( $p[ $key ] ?? null ) . ' / ' . json_encode( $o[ $key ] ?? null ) . ').' );
		}
		check_mm( ! isset( $p['selectors_dictionary'] ) || array_map( $vars, $o['selectors_dictionary'] ) === $p['selectors_dictionary'], "$name: el diccionario de selectores no es el de PRO con las variables propias." );
		if ( 'open_animation' === $name ) {
			check_mm( 'animation_menu_dropdown' === $p['type'] && 'select' === $o['type'] && array( '', 'fadeIn' ) === array_keys( $o['options'] ) && array( 'fadeIn' ) === array_keys( \ElementorPro\Modules\MegaMenu\Controls\Control_Menu_Dropdown_Animation::get_animations() ), 'open_animation: un selector con los valores de la animación de PRO («» y fadeIn).' );
		} else {
			check_mm( $same( 'type', $p, $o ), "$name: tipo distinto de PRO." );
		}
		if ( ! in_array( $p['type'], array( 'text', 'nested-elements-repeater' ), true ) ) {
			check_mm( $same( 'default', $p, $o ), "$name: el valor por defecto no es el de PRO (" . json_encode( $p['default'] ?? null ) . ' / ' . json_encode( $o['default'] ?? null ) . ').' );
		}
		if ( isset( $p['options'] ) ) {
			check_mm( array_keys( $p['options'] ) === array_keys( $o['options'] ), "$name: las opciones no son las de PRO." );
		}
		check_mm( isset( $p['selectors'] ) === isset( $o['selectors'] ), "$name: selectores en PRO y no aquí (o al revés)." );
		if ( ! empty( $o['selectors'] ) ) {
			check_mm( array_map( $vars, array_keys( $o['selectors'] ) ) === array_keys( $p['selectors'] ) && array_map( $vars, array_values( $o['selectors'] ) ) === array_values( $p['selectors'] ), "$name: los selectores no son los de PRO sobre el marcado propio." );
		}
		check_mm( isset( $p['prefix_class'] ) === isset( $o['prefix_class'] ) && ( ! isset( $o['prefix_class'] ) || 0 === strpos( $o['prefix_class'], 'digi-mm--' ) ), "$name: clase del contenedor propia donde PRO la pone." );
	};
	foreach ( $pro->controls as $name => $p ) {
		$compare( $p, $own->controls[ $name ], $name );
	}
	$pf = $pro->controls['menu_items']['fields'];
	$of = $own->controls['menu_items']['fields'];
	check_mm( array_keys( $pf ) === array_keys( $of ), 'Repetidor: los mismos campos.' );
	foreach ( $pf as $name => $p ) {
		$compare( $p, $of[ $name ], 'menu_items.' . $name );
	}
	check_mm( 3 === count( $own->controls['menu_items']['default'] ) && 'Elemento n.º 2' === $own->controls['menu_items']['default'][1]['item_title'] && '{{{ item_title }}}' === $own->controls['menu_items']['title_field'], 'Repetidor: tres filas por defecto con su título.' );
	foreach ( $pro->groups as $name => $p ) {
		check_mm( isset( $own->groups[ $name ] ), "$name: sin grupo propio." );
		$o = $own->groups[ $name ];
		foreach ( array( 'type', 'types', 'exclude', 'global', 'condition', 'separator', '_section', '_tab' ) as $key ) {
			check_mm( $same( $key, $p, $o ), "Grupo $name: «{$key}» distinto de PRO." );
		}
		check_mm( $vars( $o['selector'] ?? '' ) === ( $p['selector'] ?? '' ), "Grupo $name: el selector no es el de PRO sobre el marcado propio: " . ( $o['selector'] ?? '' ) );
		foreach ( (array) ( $p['fields_options'] ?? array() ) as $field => $opts ) {
			check_mm( isset( $o['fields_options'][ $field ] ) && array_map( $vars, array_keys( (array) ( $o['fields_options'][ $field ]['selectors'] ?? array() ) ) ) === array_keys( (array) ( $opts['selectors'] ?? array() ) ) && array_map( $vars, array_values( (array) ( $o['fields_options'][ $field ]['selectors'] ?? array() ) ) ) === array_values( (array) ( $opts['selectors'] ?? array() ) ), "Grupo $name.$field: selectores distintos de PRO." );
		}
	}
	foreach ( $pro->sections as $name => $p ) {
		check_mm( isset( $own->sections[ $name ] ) && $same( 'tab', $p, $own->sections[ $name ] ) && $same( 'condition', $p, $own->sections[ $name ] ) && $same( 'conditions', $p, $own->sections[ $name ] ), "Sección $name: falta o cambia de pestaña o condición." );
	}
	check_mm( array( 'none', 'mobile', 'tablet' ) === array_keys( $own->controls['breakpoint_selector']['options'] ), 'Punto de corte: «Ninguno» y los activos, como PRO.' );

	// 3. Cobertura del mapa.
	$map = require $root . 'modules/digitalisimo-convert/maps/mega-menu.php';
	check_mm( 'mega-menu' === $map['source'] && 'digitalisimo-mega-menu' === $map['target'] && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), 'Origen, destino, estado y panel del mapa.' );
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), $map['ui'], array_keys( $map['repeaters'] ) );
	foreach ( array_keys( $pro->controls ) as $name ) {
		check_mm( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
		check_mm( ! in_array( $name, $map['ui'], true ) || in_array( $own->controls[ $name ]['type'], array( 'heading', 'raw_html' ), true ), "$name: elemento del panel sin valor." );
	}
	foreach ( array_keys( $pro->groups ) as $name ) {
		check_mm( ( $map['groups'][ $name ] ?? '' ) === $name, "$name: grupo sin declarar." );
	}
	check_mm( count( $declared ) === count( array_unique( $declared ) ) && count( $declared ) === count( $pro->controls ) + count( $pro->groups ), 'El mapa declara cada control de PRO una sola vez (' . count( $declared ) . ' / ' . ( count( $pro->controls ) + count( $pro->groups ) ) . ').' );
	check_mm( array_keys( $pf ) === $map['repeaters']['menu_items']['keep'], 'Los campos del repetidor, todos.' );

	// 4. Marcado del servidor.
	$kids  = array( new MM_Child( '<p>Contenido uno</p>' ), new MM_Child( '' ), new MM_Child( '<p>Tres</p>', new MM_Child( '<p>Dentro</p>' ) ) );
	$items = $rows(
		array( 'Servicios', 'Equipo', 'Blog <b>y</b> más' ),
		array(
			array( 'item_dropdown_content' => 'yes', 'item_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) ),
			array( 'item_dropdown_content' => 'yes', 'item_link' => array( 'url' => 'https://ejemplo.test/servicios/', 'is_external' => 'on' ) ),
			array( 'item_link' => array( 'url' => '#blog' ), 'element_id' => 'mi-blog', 'item_dropdown_content' => 'yes' ),
		)
	);
	$html = $render( array( 'menu_items' => $items ), $kids );
	check_mm( 0 === strpos( $html, '<nav class="digi-mm" data-widget-number="123" data-digi-open="hover" aria-label="Menú"><button type="button" class="digi-mm__toggle" id="digi-mm-toggle-123" aria-expanded="false" aria-controls="digi-mm-bar-123" aria-label="Menú"><span class="digi-mm__toggle-icon digi-mm__toggle-open"><i class="eicon-menu-bar" aria-hidden="true"></i></span><span class="digi-mm__toggle-icon digi-mm__toggle-close"><i class="eicon-close" aria-hidden="true"></i></span></button><div class="digi-mm__wrapper" id="digi-mm-bar-123"><ul class="digi-mm__heading">' ), 'Raíz, hamburguesa nativa con los iconos de PRO y lista: ' . substr( $html, 0, 300 ) );
	check_mm( str_contains( $html, '<li class="digi-mm__item"><div id="digi-mm-title-1231" class="digi-mm__title"><div class="digi-mm__title-container"><span class="digi-mm__icon"><span class="digi-mm__icon-active"><i class="fas fa-star" aria-hidden="true"></i></span><span class="digi-mm__icon-inactive"><i class="fas fa-star" aria-hidden="true"></i></span></span><span class="digi-mm__title-text">Servicios</span></div><button type="button" class="digi-mm__dropdown-icon" id="digi-mm-dropdown-icon-1231" data-tab-index="1" aria-expanded="false" aria-controls="digi-mm-content-1231" aria-label="Submenú de Servicios"><span class="digi-mm__dropdown-opened"><i class="fas fa-caret-up" aria-hidden="true"></i></span><span class="digi-mm__dropdown-closed"><i class="fas fa-caret-down" aria-hidden="true"></i></span></button></div><div class="digi-mm__content"><div class="e-con" id="digi-mm-content-1231" data-tab-index="1" aria-labelledby="digi-mm-dropdown-icon-1231"><p>Contenido uno</p></div></div></li>' ), 'Elemento sin enlace, con icono, botón «Submenú de …» y su contenedor hijo (id, número y aria-labelledby).' );
	check_mm( str_contains( $html, '<div id="digi-mm-title-1232" class="digi-mm__title digi-mm__current"><a class="digi-mm__title-container digi-mm__link" href="https://ejemplo.test/servicios/" target="_blank" aria-current="page">' ) && str_contains( $html, '<div class="digi-mm__content"></div>' ), 'Elemento con enlace a la página actual (clase y aria-current) y con contenido vacío (no se pinta el hijo).' );
	check_mm( str_contains( $html, '<div id="mi-blog" class="digi-mm__title digi-mm__anchor"><a class="digi-mm__title-container digi-mm__link" href="#blog">' ) && str_contains( $html, 'aria-label="Submenú de Blog y más"' ) && str_contains( $html, '<span class="digi-mm__title-text">Blog <b>y</b> más</span>' ), 'Ancla con su id propio; el nombre del botón sin etiquetas.' );
	check_mm( 1 === substr_count( $html, 'id="digi-mm-content-1233"' ) && str_contains( $html, '<p>Tres</p><div class="e-con"><p>Dentro</p></div>' ), 'Sólo el hijo directo recibe los atributos.' );
	check_mm( empty( $GLOBALS['mm']['filters']['elementor/frontend/container/should_render'] ), 'El filtro del núcleo se quita después de pintar cada hijo.' );
	$click = $render( array( 'menu_items' => $items, 'open_on' => 'click', 'open_animation' => 'fadeIn', 'content_width' => 'fit_to_content', 'content_horizontal_position' => 'left', 'hover_animation' => 'grow', 'menu_name' => '', 'menu_toggle_icon_hover_animation' => 'pulse', 'menu_toggle_icon_normal' => array( 'value' => 'fas fa-bars', 'library' => 'fa-solid' ) ), $kids );
	check_mm( 0 === strpos( $click, '<nav class="digi-mm" data-widget-number="123" data-digi-open="click" data-digi-anim="fadeIn" data-digi-fit="left"><button type="button" class="digi-mm__toggle elementor-animation-pulse"' ) && str_contains( $click, '<i class="fas fa-bars"' ), 'Clic, animación, ajuste a la izquierda, sin nombre y botón con icono y animación propios.' );
	check_mm( str_contains( $click, 'class="digi-mm__title digi-mm__click elementor-animation-grow"' ) && str_contains( $click, 'class="digi-mm__title digi-mm__current elementor-animation-grow"' ), 'Con clic, sólo los elementos con contenido llevan la clase de clic (como PRO).' );
	$plain = $render( array( 'menu_items' => $rows( array( 'Uno' ) ), 'menu_item_icon' => array( 'value' => '', 'library' => '' ) ) );
	check_mm( ! str_contains( $plain, 'digi-mm__dropdown-icon' ) && ! str_contains( $plain, 'digi-mm__content' ) && str_contains( $plain, '<span class="digi-mm__title-text">Uno</span></div></div></li>' ), 'Un elemento sin contenido desplegable: sin botón ni contenido.' );
	// Puntos de corte que la hoja no trae: una vez por página.
	$probe = new Mega_Menu();
	check_mm( '' === $probe->breakpoint_style( 'tablet', 'horizontal' ) && '' === $probe->breakpoint_style( 'none', 'horizontal' ) && '' === $probe->breakpoint_style( 'tablet', 'dropdown' ), 'Los puntos de corte predeterminados los trae la hoja.' );
	$GLOBALS['mm']['breakpoints']['laptop'] = new MM_Breakpoint( 'Portátil', 1366 );
	check_mm( '<style>@media(max-width:1366px){.elementor .digi-mm--bp-laptop{--dmm-wrapper-display:none}.elementor .digi-mm--bp-laptop .digi-mm__toggle{display:flex}}</style>' === $probe->breakpoint_style( 'laptop', 'horizontal' ) && '' === $probe->breakpoint_style( 'laptop', 'horizontal' ), 'Portátil: sus reglas, una vez por página.' );
	Mega_Menu::reset_printed();
	unset( $GLOBALS['mm']['breakpoints']['laptop'] );

	// 5. Plantilla del editor: el mismo marcado que el servidor.
	ob_start();
	( digi_test_accessible( new ReflectionMethod( $own, 'content_template' ) ) )->invoke( $own );
	$tpl = (string) ob_get_clean();
	foreach ( array( 'class="digi-mm"', 'data-widget-number="{{ elementUid }}"', 'class="digi-mm__toggle elementor-clickable', 'id="digi-mm-toggle-{{ elementUid }}" aria-expanded="false" aria-controls="digi-mm-bar-{{ elementUid }}"', 'digi-mm__toggle-icon digi-mm__toggle-open', 'class="digi-mm__wrapper" id="digi-mm-bar-{{ elementUid }}"', '<ul class="digi-mm__heading">', '<li class="digi-mm__item">', 'digi-mm-title-', 'class="digi-mm__title-container digi-mm__link"', 'class="digi-mm__title-container"', 'digi-mm__icon-active', 'digi-mm__icon-inactive', 'class="digi-mm__title-text" data-binding-type="repeater-item" data-binding-repeater-name="menu_items" data-binding-setting="item_title"', 'class="digi-mm__dropdown-icon" id="digi-mm-dropdown-icon-{{ uid }}" data-tab-index="{{ index }}" aria-expanded="false" aria-controls="digi-mm-content-{{ uid }}"', 'digi-mm__dropdown-opened', 'digi-mm__dropdown-closed', '<div class="digi-mm__content"></div>', 'data-digi-anim="fadeIn"', 'data-digi-fit=', 'digi-mm__current', 'digi-mm__anchor', 'digi-mm__click' ) as $piece ) {
		check_mm( str_contains( $tpl, $piece ), "Plantilla del editor: falta «{$piece}»." );
	}
	check_mm( str_contains( $tpl, '"Submenú de %s".replace(' ) && ! str_contains( $tpl, 'elementorPro' ) && ! str_contains( $tpl, 'e-n-menu' ), 'Plantilla: el nombre del botón del servidor; sin el módulo de PRO del editor.' );
	ob_start();
	( digi_test_accessible( new ReflectionMethod( $own, 'content_template_single_repeater_item' ) ) )->invoke( $own );
	$single = (string) ob_get_clean();
	check_mm( str_contains( $single, 'view.collection.length + 1' ) && str_contains( $single, '<li class="digi-mm__item">' ) && str_contains( $single, '<div class="digi-mm__content"></div>' ), 'Un elemento nuevo del repetidor: el mismo marcado.' );

	// 6. Conversión.
	$kid = static function ( $id, $text ) {
		return array( 'id' => $id, 'elType' => 'container', 'isInner' => true, 'settings' => array( 'content_width' => 'full' ), 'elements' => array( array( 'id' => $id . 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $text ), 'elements' => array() ), array( 'id' => $id . 'q', 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => array( 'blockquote_content' => 'Cita' ), 'elements' => array() ) ) );
	};
	$pro_rows = array( array( '_id' => 'a1', 'item_title' => 'Servicios', 'item_dropdown_content' => 'yes' ), array( '_id' => 'a2', 'item_title' => 'Blog', 'item_link' => array( 'url' => 'https://ejemplo.test/blog/', 'is_external' => '', 'nofollow' => '' ) ), array( '_id' => 'a3', 'item_title' => 'Equipo', 'item_dropdown_content' => 'yes', 'item_icon' => array( 'value' => 'fas fa-users', 'library' => 'fa-solid' ), 'element_id' => 'equipo' ) );
	$cases = array(
		'defaults' => array(),
		'layout'   => array( 'menu_items' => $pro_rows, 'content_width' => 'fit_to_content', 'content_horizontal_position' => 'right', 'item_layout' => 'dropdown', 'item_position_dropdown' => 'center', 'menu_item_icon' => array( 'value' => 'fas fa-chevron-down', 'library' => 'fa-solid' ), 'open_on' => 'click', 'open_animation' => 'fadeIn', 'open_animation_duration' => array( 'unit' => 'ms', 'size' => 300 ), 'horizontal_scroll' => 'enable', 'breakpoint_selector' => 'mobile', 'toggle_align' => 'flex-end' ),
		'style'    => array( 'menu_items' => $pro_rows, 'item_position_horizontal' => 'stretch', 'item_position_horizontal_mobile' => 'center', 'menu_item_title_space_between' => array( 'unit' => 'px', 'size' => 16 ), 'menu_item_title_typography_typography' => 'custom', 'menu_item_title_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'menu_item_title_text_color_normal' => '#111111', 'menu_item_title_text_color_hover' => '#245eff', 'menu_item_title_text_color_active' => '#e63946', 'menu_item_title_background_color_background' => 'classic', 'menu_item_title_background_color_color' => '#f1faee', 'menu_item_title_box_border_border' => 'solid', 'menu_divider' => 'yes', 'menu_divider_color' => '#cccccc', 'hover_animation' => 'grow', 'menu_item_title_padding' => array( 'unit' => 'px', 'top' => '10', 'right' => '20', 'bottom' => '10', 'left' => '20', 'isLinked' => false ), 'icon_position' => 'block-start', 'icon_size' => array( 'unit' => 'px', 'size' => 20 ), 'style_dropdown_indicator_rotate' => array( 'unit' => 'deg', 'size' => 180 ), 'menu_dropdown_indicator_color_hover' => '#245eff', 'style_menu_toggle_icon_size' => array( 'unit' => 'px', 'size' => 28 ), 'menu_toggle_icon_background_color_active_background' => 'classic', 'content_background_color_background' => 'classic', 'content_background_color_color' => '#ffffff', 'content_shadow_box_shadow_type' => 'yes', 'content_padding' => array( 'unit' => 'px', 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'isLinked' => true ), 'dropdown_menu_item_text_color_normal' => '#222222', 'menu_dropdown_box_border_border' => 'solid', 'menu_dropdown_box_shadow_box_shadow_type' => 'yes' ),
		'globals'  => array( 'menu_items' => $pro_rows, '__globals__' => array( 'menu_item_title_typography_typography' => 'globals/typography?id=accent', 'menu_item_title_text_color_normal' => 'globals/colors?id=text', 'menu_divider_color' => 'globals/colors?id=secondary', '_background_color' => 'globals/colors?id=primary' ) ),
		'dynamic'  => array( 'menu_items' => array( array( '_id' => 'd1', 'item_title' => 'Uno', 'item_dropdown_content' => 'yes', '__dynamic__' => array( 'item_title' => '[elementor-tag id="t1" name="post-title" settings="%7B%7D"]', 'item_link' => '[elementor-tag id="t2" name="internal-url" settings="%7B%7D"]' ) ) ), '__dynamic__' => array( 'menu_name' => '[elementor-tag id="t3" name="site-title" settings="%7B%7D"]' ) ),
		'css'      => array( 'menu_items' => $pro_rows, 'custom_css' => "selector .e-n-menu-title.e-current{font-weight:700}\nselector .e-n-menu-content > .e-con.e-active{--n-menu-title-color-normal:red}\n.elementor-widget-n-menu .e-n-menu-toggle .e-close{opacity:.5}", '_css_classes' => 'cabecera', 'sticky' => 'top' ),
	);
	$tree = array( array( 'id' => 'p1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'mega-menu', 'settings' => $settings, 'elements' => array( $kid( $id . '1', 'Uno' ), $kid( $id . '2', 'Dos' ), $kid( $id . '3', 'Tres' ) ) );
	}
	$converted = Converter::tree( $tree, $log, true );
	$menus     = array_values( array_filter( $log, static function ( $entry ) { return 'mega-menu' === $entry['source']; } ) );
	check_mm( count( $cases ) === count( $menus ), 'Se convierte cada menú del árbol (' . count( $menus ) . ').' );
	$own_keys = array_keys( $own->controls );
	$lands    = static function ( $key ) use ( $own_keys, $own ) {
		$base = Translator::base( (string) $key );
		if ( in_array( $base, $own_keys, true ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( array_keys( $own->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$out = array();
	foreach ( $converted[0]['elements'] as $index => $element ) {
		$original = $tree[0]['elements'][ $index ];
		check_mm( 'digitalisimo-mega-menu' === $element['widgetType'] && $original['id'] === $element['id'], $element['id'] . ': pasa al Menú con submenús de contenido con el mismo id.' );
		foreach ( $element['elements'] as $k => $child ) {
			$expect                              = $original['elements'][ $k ];
			$expect['elements'][1]['settings']   = $child['elements'][1]['settings'];
			$expect['elements'][1]['widgetType'] = $child['elements'][1]['widgetType'];
			check_mm( $expect === $child, $element['id'] . ": el contenedor hijo $k no se toca (sólo los widgets de PRO de dentro, con su propio mapa)." );
		}
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_mm( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_mm( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
		foreach ( (array) ( $element['settings']['menu_items'] ?? array() ) as $row ) {
			foreach ( array_keys( $row ) as $key ) {
				check_mm( isset( $of[ $key ] ) || '_id' === $key || '__dynamic__' === $key, $element['id'] . ": menu_items.$key no cae en un campo propio." );
			}
		}
	}
	$codes = array();
	foreach ( $menus as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_mm( array( 'css:pro.css:custom_css' ) === $codes && ! isset( $map['approx'] ), 'Ningún pro.unmapped, pro.dropped ni pro.approx («Desplazamiento horizontal» se repite entero: posición inicial y arrastre); sólo el CSS: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_mm( 'Menu' === $d['menu_name'] && 'full_width' === $d['content_width'] && 'hover' === $d['open_on'] && 'tablet' === $d['breakpoint_selector'] && array( 'Item #1', 'Item #2', 'Item #3' ) === array_column( $d['menu_items'], 'item_title' ) && 'mega-menu' === $d['_digitalisimo_pro'], 'Un menú sin cambios llega con los valores visibles de PRO y sus tres filas.' );
	$l = $out['layout'];
	check_mm( 'fit_to_content' === $l['content_width'] && 'right' === $l['content_horizontal_position'] && 'dropdown' === $l['item_layout'] && 'click' === $l['open_on'] && 'fadeIn' === $l['open_animation'] && 'mobile' === $l['breakpoint_selector'] && array( 'a1', 'a2', 'a3' ) === array_column( $l['menu_items'], '_id' ) && 'equipo' === $l['menu_items'][2]['element_id'] && 'yes' === $l['menu_items'][0]['item_dropdown_content'], 'Diseño, efecto y filas con su _id y sus campos.' );
	check_mm( 'center' === $out['style']['item_position_horizontal_mobile'] && 18 === $out['style']['menu_item_title_typography_font_size']['size'] && 180 === $out['style']['style_dropdown_indicator_rotate']['size'] && 'yes' === $out['style']['content_shadow_box_shadow_type'] && '24' === $out['style']['content_padding']['top'], 'Estilo, por dispositivo y por grupos.' );
	check_mm( $cases['globals']['__globals__'] === $out['globals']['__globals__'] && $cases['dynamic']['__dynamic__'] === $out['dynamic']['__dynamic__'] && $cases['dynamic']['menu_items'][0]['__dynamic__'] === $out['dynamic']['menu_items'][0]['__dynamic__'], 'Globales y etiquetas dinámicas siguen a su control, también en las filas.' );
	check_mm( "selector .digi-mm__title.digi-mm__current{font-weight:700}\nselector .digi-mm__content > .e-con.digi-mm__active{--dmm-title-color-normal:red}\n.elementor-widget-digi-mega-menu .digi-mm__toggle .digi-mm__toggle-close{opacity:.5}" === $out['css']['custom_css'], 'CSS personalizado con las clases y variables propias: ' . json_encode( $out['css']['custom_css'] ) );
	check_mm( 'cabecera' === $out['css']['_css_classes'] && 'top' === $out['css']['sticky'], 'Avanzado y fijado intactos.' );
	check_mm( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	$painted = $render( $out['layout'], array( new MM_Child( '<p>A</p>' ), new MM_Child( '' ), new MM_Child( '<p>C</p>' ) ) );
	check_mm( str_contains( $painted, 'data-digi-open="click" data-digi-anim="fadeIn" data-digi-fit="right"' ) && str_contains( $painted, 'id="equipo" class="digi-mm__title digi-mm__click"' ) && str_contains( $painted, 'fas fa-chevron-down' ) && str_contains( $painted, 'aria-label="Submenú de Servicios"' ), 'El menú convertido se pinta con sus ajustes.' );

	// 7. Página de prueba para tests/elements-pro-mega-menu-chrome.mjs.
	$fixture_cases = array(
		'm1' => array( 'settings' => array(), 'wrapper' => 'digi-mm--full_width digi-mm--layout-horizontal digi-mm--bp-tablet' ),
		'm2' => array( 'settings' => array( 'open_on' => 'click', 'content_width' => 'fit_to_content', 'content_horizontal_position' => 'center', 'open_animation' => 'fadeIn' ), 'wrapper' => 'digi-mm--fit_to_content digi-mm--layout-horizontal digi-mm--bp-mobile' ),
		'm3' => array( 'settings' => array( 'item_layout' => 'dropdown' ), 'wrapper' => 'digi-mm--full_width digi-mm--layout-dropdown digi-mm--bp-tablet' ),
		// «Desplazamiento horizontal» con ocho elementos que no caben a 375 px (sí a 1 280): la variable de cada ajuste en
		// el contenedor, como la escribe el CSS del elemento.
		'm4' => array( 'settings' => array( 'horizontal_scroll' => 'enable', 'item_position_horizontal' => 'end', 'breakpoint_selector' => 'none' ), 'rows' => 'scroll', 'wrapper' => 'digi-mm--full_width digi-mm--layout-horizontal digi-mm--bp-none" style="--dmm-heading-wrap:nowrap;--dmm-heading-overflow-x:scroll;--dmm-heading-justify-content:flex-end' ),
		'm5' => array( 'settings' => array( 'horizontal_scroll' => 'enable', 'item_position_horizontal' => 'center', 'breakpoint_selector' => 'none' ), 'rows' => 'scroll', 'wrapper' => 'digi-mm--full_width digi-mm--layout-horizontal digi-mm--bp-none" style="--dmm-heading-wrap:nowrap;--dmm-heading-overflow-x:scroll;--dmm-heading-justify-content:center' ),
		'm6' => array( 'settings' => array( 'horizontal_scroll' => 'enable', 'item_position_horizontal' => 'end', 'breakpoint_selector' => 'none' ), 'rows' => 'scroll', 'wrapper' => 'digi-mm--full_width digi-mm--layout-horizontal digi-mm--bp-none" dir="rtl" style="--dmm-heading-wrap:nowrap;--dmm-heading-overflow-x:scroll;--dmm-heading-justify-content:flex-end' ),
	);
	$scroll_rows = $rows(
		array( 'Inicio', 'Servicios', 'Equipo', 'Blog', 'Tienda', 'Recursos', 'Ayuda', 'Contacto' ),
		array_map( static function ( $n ) { return array( 'item_link' => array( 'url' => '#fila-' . $n ) ); }, range( 1, 8 ) )
	);
	$fixture_rows = $rows(
		array( 'Servicios', 'Equipo', 'Blog', 'Contacto' ),
		array(
			array( 'item_dropdown_content' => 'yes' ),
			array( 'item_dropdown_content' => 'yes', 'item_link' => array( 'url' => '#equipo' ) ),
			array( 'item_link' => array( 'url' => 'https://ejemplo.test/blog/' ) ),
			array( 'item_dropdown_content' => 'yes', 'item_link' => array( 'url' => '#contacto' ) ),
		)
	);
	$fixture_kids = static function () {
		return array( new MM_Child( '<p>Contenido de servicios</p><a href="#s1">Diseño</a> <a href="#s2">Desarrollo</a>' ), new MM_Child( '<p>Contenido del equipo</p><a href="#e1">Personas</a>' ), new MM_Child( '' ), new MM_Child( '<p>Escríbenos</p><button type="button">Enviar</button>' ) );
	};
	$body = '';
	foreach ( $fixture_cases as $fid => $fc ) {
		$w           = new Mega_Menu();
		$w->settings = $fc['settings'] + array( 'menu_items' => isset( $fc['rows'] ) ? $scroll_rows : $fixture_rows ) + $defaults( $own );
		$w->children = isset( $fc['rows'] ) ? array() : $fixture_kids();
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $w, 'render' ) ) )->invoke( $w );
		$body .= '<div class="elementor-element elementor-widget elementor-widget-digi-mega-menu ' . $fc['wrapper'] . '" data-id="' . $fid . '">' . str_replace( '123', substr( $fid, 1 ) . '00', (string) ob_get_clean() ) . "</div>\n<div class=\"spacer\"></div>\n";
	}
	$links = '';
	foreach ( array( 'mega-menu' => Module::WIDGETS['digitalisimo-mega-menu']['css'] ) + Mega_Menu::SHEETS as $file ) {
		$links .= '<link rel="stylesheet" href="../../digitalisimo-elements/' . $file . "\">\n";
	}
	$fixture = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>digi-mm</title>\n<!-- Generada por tests/elements-pro-mega-menu.php (--write); la usa tests/elements-pro-mega-menu-chrome.mjs. -->\n<style>body{margin:0;font:16px/1.5 sans-serif}.elementor-widget{position:relative;margin:0 20px 40px}.e-con{--width:100%;width:var(--width);box-sizing:border-box;position:relative;display:flex;flex-direction:column;gap:8px;padding:16px}.spacer{height:420px}i[class*=fa-],i[class*=eicon-]{display:inline-block;width:1em;height:1em;background:currentColor}button{border:1px solid #c36;padding:.5rem 1rem;border-radius:3px;color:#c36}#equipo,#contacto{height:400px}</style>\n" . $links . "</head><body class=\"elementor\">\n" . $body . "<div id=\"equipo\"></div><div id=\"contacto\"></div>\n<script src=\"../../digitalisimo-elements/modules/digitalisimo-navigation/assets/js/mega-menu.js\"></script>\n<script src=\"../../digitalisimo-elements/modules/digitalisimo-navigation/assets/js/mega-menu-scroll.js\"></script>\n</body></html>\n";
	$path    = __DIR__ . '/fixtures/mega-menu.html';
	if ( in_array( '--write', $argv ?? array(), true ) ) {
		file_put_contents( $path, $fixture );
	}
	check_mm( is_file( $path ) && file_get_contents( $path ) === $fixture, 'tests/fixtures/mega-menu.html es el marcado de hoy (regenerar con --write).' );

	echo 'DIGITALÍSIMO Elements: Menú con submenús de contenido (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, ' . count( $of ) . ' campos por fila, hojas de ' . implode( '/', array_map( static function ( $f ) use ( $root ) { return filesize( $root . $f ); }, array( 'mega-menu' => Module::WIDGETS['digitalisimo-mega-menu']['css'] ) + Mega_Menu::SHEETS ) ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa desde el de PRO (' . count( $cases ) . " casos con hijos) validados.\n";
}
