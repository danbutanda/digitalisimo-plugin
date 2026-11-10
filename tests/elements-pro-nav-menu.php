<?php
/**
 * Menú de WordPress (`digitalisimo-nav-menu`), el widget propio que sustituye al «WordPress Menu» de PRO (C3 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c3.md, frente E), y su mapa `modules/digitalisimo-convert/maps/nav-menu.php`:
 * - registro y recursos (hojas ≤ 4 KB cada una, sin `--e-global-*`, `url()` ni clases de PRO; script ≤ 6 KB sin jQuery ni
 *   SmartMenus; sólo las hojas que el menú usa);
 * - controles: se cargan a la vez el widget de PRO y el propio, y cada control de PRO tiene uno propio con el mismo
 *   nombre, tipo, valor por defecto (salvo los textos, que se traducen), opciones, condiciones, dispositivos, sección y
 *   pestaña; los grupos, con el mismo tipo, `global`, `exclude` y condición; los selectores, propios;
 * - cobertura del mapa: cada control de PRO está declarado una sola vez;
 * - marcado del servidor sobre un `wp_nav_menu()` de prueba que aplica los mismos filtros que el de WordPress: menú
 *   principal y desplegable, hamburguesa con `aria-expanded`/`aria-controls`, botón de cada submenú con nombre propio,
 *   indicador decorativo, clases de nivel, actual y ancla, sin ids repetidos; disposiciones, sin hamburguesa, sin
 *   indicador, puntos de corte propios (CSS una vez por página) y escape;
 * - conversión con `Converter::tree()` de menús de PRO reales (por defecto, estilos, responsivos, `__globals__`,
 *   `__dynamic__`, CSS personalizado, `menu_id` de un kit): ningún `pro.unmapped`, cada clave cae en un control propio y
 *   lo convertido se pinta;
 * - `tests/fixtures/nav-menu.html` (la usa tests/elements-pro-nav-menu-chrome.mjs) es el marcado de hoy (`--write` la
 *   regenera).
 * Uso: node php-wasm-cli tests/elements-pro-nav-menu.php [--write]
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Plugin { public static $instance; public $experiments; public $breakpoints; public $editor; }
	class Controls_Manager {
		const TEXT = 'text'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading';
		const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const ICONS = 'icons'; const RAW_HTML = 'raw_html';
		const ALERT = 'alert'; const DIVIDER = 'divider'; const HOVER_ANIMATION = 'hover_animation'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Icons_Manager {
		public static $migration = true;
		public static function is_migration_allowed() { return self::$migration; }
		public static function get_icon_manager_tabs() { return array(); }
		public static function try_get_icon_html( $icon, $attributes = array(), $tag = 'i' ) {
			if ( empty( $icon['library'] ) ) {
				return '';
			}
			$class = trim( ( $attributes['class'] ?? '' ) . ' ' . $icon['value'] );
			unset( $attributes['class'] );
			$attrs = '';
			foreach ( $attributes as $k => $v ) {
				$attrs .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES ) . '"';
			}
			return '<i class="' . htmlspecialchars( $class, ENT_QUOTES ) . '"' . $attrs . '></i>';
		}
		public static function render_icon( $icon, $attributes = array(), $tag = 'i' ) { echo self::try_get_icon_html( $icon, $attributes, $tag ); return true; }
	}
	/** Registro de controles común: el de PRO y el propio pasan por aquí. */
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $type     = false;
		protected $section = '';
		protected $tab = '';
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		/** Como el núcleo: sin los ajustes de controles cuya condición no se cumple. */
		public function get_active_settings() {
			$out = array();
			foreach ( $this->settings as $key => $value ) {
				$ok = true;
				foreach ( (array) ( $this->controls[ $key ]['condition'] ?? array() ) as $term => $want ) {
					$not  = '!' === substr( $term, -1 );
					$have = $this->settings[ rtrim( $term, '!' ) ] ?? null;
					$hit  = is_array( $want ) ? in_array( $have, $want, true ) : $have === $want;
					$ok   = $ok && ( $not ? ! $hit : $hit );
				}
				if ( $ok ) {
					$out[ $key ] = $value;
				}
			}
			return $out;
		}
		public function is_type_instance() { return $this->type; }
		public function get_id() { return 'abc1234'; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name ) {}
		public function start_controls_tab( $name, $args ) { $this->tab = $name; }
		public function end_controls_tab() { $this->tab = ''; }
		public function end_controls_tabs() { $this->tab = ''; }
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( '_section' => $this->section, '_tab' => $this->tab ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type, '_section' => $this->section, '_tab' => $this->tab ) + $args; $this->order[] = '@' . $args['name']; }
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace ElementorPro\Base {
	abstract class Base_Widget extends \Elementor\Widget_Base {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['nm'] = array( 'filters' => array(), 'rtl' => false, 'features' => array(), 'styles' => array(), 'menus' => array( 'principal' => 'Principal', 'secundario' => 'Secundario' ) );
	class WP_Term { public $term_id; public $slug; }
	function __( $t, $d = '' ) { return $t; }
	function esc_html__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function is_rtl() { return $GLOBALS['nm']['rtl']; }
	function admin_url( $p = '' ) { return 'https://ejemplo.test/wp-admin/' . $p; }
	function plugins_url( $p, $f ) { return 'https://ejemplo.test/wp-content/plugins/digitalisimo-elements/' . $p; }
	function wp_style_is( $h, $s ) { return isset( $GLOBALS['nm']['styles'][ $h ] ); }
	function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['nm']['styles'][ $h ] = $src; return true; }
	function wp_script_is( $h, $s ) { return isset( $GLOBALS['nm']['scripts'][ $h ] ); }
	function wp_register_script( $h, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['nm']['scripts'][ $h ] = $src; return true; }
	function wp_get_nav_menus() {
		$out = array();
		foreach ( $GLOBALS['nm']['menus'] as $slug => $name ) {
			$out[] = (object) array( 'slug' => $slug, 'name' => $name );
		}
		return $out;
	}
	function wp_get_nav_menu_object( $menu ) {
		$t          = new WP_Term();
		$t->term_id = 'principal' === $menu ? 7 : ( 9 === $menu ? 9 : 0 );
		$t->slug    = 9 === $menu ? 'principal-2' : (string) $menu;
		return $t->term_id ? $t : false;
	}
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['nm']['filters'][ $hook ][] = $cb; }
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['nm']['filters'][ $hook ] ?? array() as $i => $f ) {
			if ( $f === $cb ) {
				unset( $GLOBALS['nm']['filters'][ $hook ][ $i ] );
			}
		}
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['nm']['filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$args );
		}
		return $value;
	}
	function __return_empty_string() { return ''; }
	function check_nm( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	/**
	 * Árbol del menú de prueba: Inicio (actual), Servicios (con hijos y nietos), Equipo («#», padre sin destino),
	 * Contacto (ancla).
	 */
	function nm_tree() {
		return array(
			array( 'Inicio', 'https://ejemplo.test/', array( 'current-menu-item' ), array() ),
			array(
				'Servicios',
				'https://ejemplo.test/servicios/',
				array(),
				array(
					array( 'Diseño', 'https://ejemplo.test/diseno/', array(), array() ),
					array(
						'Desarrollo <b>web</b>',
						'https://ejemplo.test/desarrollo/',
						array(),
						array(
							array( 'Tiendas', 'https://ejemplo.test/tiendas/', array(), array() ),
						),
					),
				),
			),
			array( 'Equipo', '#', array(), array( array( 'Personas', 'https://ejemplo.test/personas/', array(), array() ) ) ),
			array( 'Contacto', 'https://ejemplo.test/#contacto', array(), array() ),
		);
	}
	/** Recorrido como `Walker_Nav_Menu`: los mismos filtros, en el mismo orden y con los mismos argumentos. */
	function nm_walk( array $items, int $depth, $args ) {
		$out = '';
		foreach ( $items as $i => $row ) {
			list( $title, $url, $extra, $children ) = $row;
			$classes = array_merge( array( 'menu-item', 'menu-item-type-custom' ), $extra, $children ? array( 'menu-item-has-children' ) : array() );
			$item    = (object) array( 'title' => $title, 'url' => $url, 'classes' => $classes, 'ID' => 100 + $depth * 10 + $i );
			$id      = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
			$atts    = array( 'href' => $url );
			if ( in_array( 'current-menu-item', $classes, true ) ) {
				$atts['aria-current'] = 'page';
			}
			$atts  = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );
			$attrs = '';
			foreach ( $atts as $k => $v ) {
				$attrs .= ' ' . $k . '="' . esc_attr( $v ) . '"';
			}
			$label  = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );
			$output = apply_filters( 'walker_nav_menu_start_el', '<a' . $attrs . '>' . $label . '</a>', $item, $depth, $args );
			$out   .= '<li' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $output;
			if ( $children ) {
				$sub  = apply_filters( 'nav_menu_submenu_css_class', array( 'sub-menu' ), $args, $depth );
				$out .= '<ul class="' . esc_attr( implode( ' ', $sub ) ) . '">' . nm_walk( $children, $depth + 1, $args ) . '</ul>';
			}
			$out .= '</li>';
		}
		return $out;
	}
	function wp_nav_menu( $args ) {
		$args = (object) $args;
		if ( ! isset( $GLOBALS['nm']['menus'][ $args->menu ] ) ) {
			return '';
		}
		$GLOBALS['nm']['calls'][] = $args;
		return '<ul id="' . esc_attr( $args->menu_id ) . '" class="' . esc_attr( $args->menu_class ) . '">' . nm_walk( nm_tree(), 0, $args ) . '</ul>';
	}

	/** Punto de corte de prueba. */
	class NM_Breakpoint {
		private $label;
		private $value;
		public function __construct( $label, $value ) { $this->label = $label; $this->value = $value; }
		public function get_label() { return $this->label; }
		public function get_value() { return $this->value; }
	}
	$GLOBALS['nm']['breakpoints'] = array( 'mobile' => new NM_Breakpoint( 'Móvil vertical', 767 ), 'tablet' => new NM_Breakpoint( 'Tableta vertical', 1024 ) );

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-navigation/class-module.php';
	\Digitalisimo\Elements\Navigation\Module::init();
	require $root . 'modules/digitalisimo-navigation/widgets/class-nav-menu.php';
	require $root . 'modules/nav-menu/widgets/nav-menu.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return ! empty( $GLOBALS['nm']['features'][ $f ] ); } };
	\Elementor\Plugin::$instance->breakpoints = new class() { public function get_active_breakpoints() { return $GLOBALS['nm']['breakpoints']; } };
	\Elementor\Plugin::$instance->editor      = new class() { public function is_edit_mode() { return false; } };

	use Digitalisimo\Elements\Navigation\Module;
	use Digitalisimo\Elements\Navigation\Widgets\Nav_Menu;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Translator;

	$controls_of = static function ( $widget ) {
		( digi_test_accessible( new ReflectionMethod( $widget, 'register_controls' ) ) )->invoke( $widget );
		return $widget;
	};
	$own = $controls_of( new Nav_Menu() );
	$pro = $controls_of( new \ElementorPro\Modules\NavMenu\Widgets\Nav_Menu() );

	/** Ajustes como los daría Elementor: los valores por defecto de cada control más los guardados. */
	$defaults = static function ( $widget ) {
		$out = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $name ] = $control['default'];
			}
		}
		return $out;
	};
	$render = static function ( array $settings ) use ( $own, $defaults ) {
		$widget           = new Nav_Menu();
		$widget->controls = $own->controls;
		$widget->settings = $settings + $defaults( $own );
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $widget, 'render' ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	};

	// 1. Registro y recursos.
	check_nm( 'digitalisimo-nav-menu' === $own->get_name() && 'Menú de WordPress' === $own->get_title() && array( 'digitalisimo' ) === $own->get_categories() && 'eicon-nav-menu' === $own->get_icon(), 'Nombre, título, icono y categoría propios.' );
	check_nm( isset( Module::WIDGETS['digitalisimo-nav-menu'] ) && Nav_Menu::class === Module::WIDGETS['digitalisimo-nav-menu']['class'] && 'Menú de WordPress' === Module::TITLES['digitalisimo-nav-menu'] && ! in_array( 'digitalisimo-nav-menu', Module::NESTED, true ), 'El módulo de navegación registra el widget (no anidado).' );
	check_nm( true === $own->has_widget_inner_wrapper(), 'Sin el marcado optimizado, con envoltorio interior (como PRO).' );
	$GLOBALS['nm']['features'] = array( 'e_optimized_markup' => true );
	check_nm( false === $own->has_widget_inner_wrapper(), 'Con el marcado optimizado, sin envoltorio.' );
	$GLOBALS['nm']['features'] = array();
	$type       = new Nav_Menu();
	$type->type = true;
	$all        = $type->get_style_depends();
	check_nm( array( 'digitalisimo-nav-menu', 'digitalisimo-nav-menu-dropdown', 'digitalisimo-nav-menu-pointer-line', 'digitalisimo-nav-menu-pointer-framed', 'digitalisimo-nav-menu-pointer-background', 'digitalisimo-nav-menu-pointer-text' ) === $all, 'La instancia de registro (editor) declara todas las hojas: ' . implode( ', ', $all ) );
	foreach ( array_keys( Nav_Menu::SHEETS ) as $handle ) {
		check_nm( isset( $GLOBALS['nm']['styles'][ $handle ] ), "$handle: registrada (sin encolar)." );
	}
	// Recursos de página del núcleo (`_elementor_page_assets`) y caché de elementos: el núcleo encola por su handle lo que
	// guardó, sin instanciar el widget, y un handle sin registrar no se imprime. Las hojas adicionales se registran también
	// con las del módulo (`Module::register_assets()`, en `elementor/frontend/after_register_*`).
	$GLOBALS['nm']['styles'] = array();
	Module::register_assets();
	foreach ( array_merge( array( 'digitalisimo-nav-menu' ), array_keys( Nav_Menu::SHEETS ) ) as $handle ) {
		check_nm( isset( $GLOBALS['nm']['styles'][ $handle ] ), "$handle: registrada antes de pintar, sin instanciar el widget." );
	}
	check_nm( array( 'digitalisimo-nav-menu' ) === $own->get_script_depends(), 'Un solo script, el propio.' );
	$sheets = static function ( array $s ) use ( $own, $defaults ) {
		$w           = new Nav_Menu();
		$w->settings = $s + $defaults( $own );
		return implode( ',', array_map( static function ( $h ) { return str_replace( 'digitalisimo-nav-menu', '', $h ); }, $w->get_style_depends() ) );
	};
	check_nm( ',-dropdown,-pointer-line' === $sheets( array() ), 'Por defecto: la hoja, la del desplegable y la del subrayado.' );
	check_nm( ',-dropdown,-pointer-framed' === $sheets( array( 'pointer' => 'framed' ) ) && ',-dropdown,-pointer-background' === $sheets( array( 'pointer' => 'background' ) ) && ',-dropdown,-pointer-text' === $sheets( array( 'pointer' => 'text' ) ) && ',-dropdown,-pointer-line' === $sheets( array( 'pointer' => 'double-line' ) ), 'Una hoja por familia de puntero.' );
	check_nm( ',-dropdown' === $sheets( array( 'pointer' => 'none' ) ) && ',-dropdown' === $sheets( array( 'layout' => 'dropdown' ) ) && ',-pointer-line' === $sheets( array( 'dropdown' => 'none' ) ), 'Sin puntero o sólo desplegable: sin hoja de puntero; sin punto de corte: sin hoja del desplegable.' );
	$css_files = array( 'nav-menu' => Module::WIDGETS['digitalisimo-nav-menu']['css'] ) + Nav_Menu::SHEETS;
	$css_all   = '';
	foreach ( $css_files as $handle => $file ) {
		$css = (string) file_get_contents( $root . $file );
		check_nm( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$handle: la hoja pesa " . strlen( $css ) . ' B (tope 4 096).' );
		check_nm( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/elementor-nav-menu|elementor-item|e--pointer|e--animation|elementor-menu-toggle|sub-arrow|highlighted|sm-vertical/', $css ), "$handle: sin el kit, url() ni clases de PRO." );
		if ( preg_match( '/transition|animation:/', $css ) ) {
			check_nm( str_contains( $css, 'prefers-reduced-motion' ), "$handle: anima sin respetar el movimiento reducido." );
		}
		$css_all .= $css;
	}
	check_nm( str_contains( $css_all, ':focus-visible{outline:2px solid currentColor;outline-offset:2px}' ), 'Foco visible propio de enlaces y botones (2 px con separación de 2 px).' );
	// Indicador como PRO (widget-nav-menu.min.css): el relleno se hereda del enlace (negro por defecto en el desplegable, el
	// del puntero sobre el puntero de fondo) y sólo los submenús del menú principal lo ponen en currentColor y lo giran, sea
	// SVG o icono de fuente.
	check_nm( str_contains( $css_all, '.digi-nav__arrow svg{width:1em;height:1em}' ) && str_contains( $css_all, '.digi-nav__main .digi-nav__dropdown .digi-nav__arrow>*{fill:currentColor;transform:rotate(' ) && 1 === substr_count( $css_all, 'fill:currentColor' ), 'Indicador como PRO: relleno heredado del enlace; currentColor y giro sólo en los submenús del menú principal.' );
	$js = (string) file_get_contents( $root . Module::WIDGETS['digitalisimo-nav-menu']['js'] );
	// Como SmartMenus en PRO: el primer clic, toque o Intro en un padre con el submenú cerrado lo abre sin navegar.
	check_nm( str_contains( $js, "if (t && (!isOpen(a.parentNode) || a.getAttribute('href') == '#' ||" ), 'El primer clic en un padre cerrado abre su submenú (el segundo navega), como PRO.' );
	// Y, como SmartMenus, el enlace del padre anuncia el estado del submenú (aria-expanded), que el script pone y cambia.
	check_nm( str_contains( $js, 'a && a.setAttribute(E, false);' ) && str_contains( $js, 'a.setAttribute(E, open)' ), 'El enlace de un padre lleva aria-expanded con el estado de su submenú, como SmartMenus en PRO.' );
	check_nm( strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|smartmenus|elementorProFrontend/i', $js ) && str_contains( $js, "'aria-expanded'" ) && str_contains( $js, 'Escape' ) && str_contains( $js, 'frontend/element_ready/digitalisimo-nav-menu.default' ), 'Script ≤ 6 KB sin jQuery ni SmartMenus: aria-expanded, Escape y reinicio en el editor (' . strlen( $js ) . ' B).' );
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-navigation/widgets/class-nav-menu.php' );
	$map    = require $root . 'modules/digitalisimo-convert/maps/nav-menu.php';
	foreach ( array( $source, $css_all, $js, (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/nav-menu.php' ) ) as $text ) {
		check_nm( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $text ), 'Sin nombres de otros proveedores (guarda de build-packages.sh).' );
	}

	// 2. Un control propio por cada control de PRO, igual salvo los textos y los selectores.
	$same = static function ( $key, $a, $b ) { return ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ); };
	check_nm( array_keys( $pro->controls ) === array_keys( $own->controls ), 'Los mismos controles en el mismo orden. Sobran: ' . implode( ', ', array_diff( array_keys( $own->controls ), array_keys( $pro->controls ) ) ) . ' · faltan: ' . implode( ', ', array_diff( array_keys( $pro->controls ), array_keys( $own->controls ) ) ) );
	check_nm( $pro->order === $own->order, 'Los mismos controles y grupos en el mismo orden.' );
	$pro_classes = '/elementor-nav-menu|elementor-item|elementor-sub-item|e--pointer|e--animation|elementor-menu-toggle|sub-arrow|highlighted|--e-nav-menu|--nav-menu-icon/';
	foreach ( $pro->controls as $name => $p ) {
		$o = $own->controls[ $name ];
		foreach ( array( 'type', 'condition', 'conditions', 'responsive', 'separator', 'frontend_available', 'render_type', 'size_units', 'range', 'label_block', 'return_value', 'skin', 'exclude_inline_options', 'recommended', 'fa4compatibility', 'classes_dictionary', 'selectors_dictionary', 'save_default', 'style_transfer', 'alert_type', 'content_classes', 'global', 'tablet_default', 'mobile_default', '_section', '_tab' ) as $key ) {
			check_nm( $same( $key, $p, $o ), "$name: «{$key}» distinto de PRO (" . json_encode( $p[ $key ] ?? null ) . ' / ' . json_encode( $o[ $key ] ?? null ) . ').' );
		}
		if ( 'text' !== $p['type'] ) {
			check_nm( $same( 'default', $p, $o ), "$name: el valor por defecto no es el de PRO (" . json_encode( $p['default'] ?? null ) . ' / ' . json_encode( $o['default'] ?? null ) . ').' );
		}
		if ( isset( $p['options'] ) ) {
			check_nm( array_keys( $p['options'] ) === array_keys( $o['options'] ), "$name: las opciones no son las de PRO." );
		}
		check_nm( isset( $p['selectors'] ) === isset( $o['selectors'] ) && count( (array) ( $p['selectors'] ?? array() ) ) === count( (array) ( $o['selectors'] ?? array() ) ), "$name: selectores en PRO y aquí no coinciden en número." );
		if ( ! empty( $o['selectors'] ) ) {
			check_nm( ! preg_match( $pro_classes, implode( ' ', array_keys( $o['selectors'] ) ) . implode( ' ', $o['selectors'] ) ), "$name: selector o variable de PRO." );
			// Cada regla de PRO tiene su equivalente con las mismas propiedades (en el mismo orden).
			check_nm( array_values( $p['selectors'] ) === array_map( static function ( $v ) { return str_replace( array( '--digi-nav-divider', '--digi-nav-gap', '--digi-nav-toggle-size' ), array( '--e-nav-menu-divider', '--e-nav-menu-horizontal-menu-item-margin', '--nav-menu-icon-size' ), $v ); }, array_values( $o['selectors'] ) ), "$name: las propiedades de los selectores no son las de PRO." );
		}
		check_nm( isset( $p['prefix_class'] ) === isset( $o['prefix_class'] ) && ( ! isset( $o['prefix_class'] ) || 0 === strpos( $o['prefix_class'], 'digi-nav-' ) ), "$name: clase del contenedor propia donde PRO la pone." );
	}
	foreach ( $pro->groups as $name => $p ) {
		check_nm( isset( $own->groups[ $name ] ), "$name: sin grupo propio." );
		$o = $own->groups[ $name ];
		foreach ( array( 'type', 'exclude', 'global', 'condition', 'separator', '_section', '_tab' ) as $key ) {
			check_nm( $same( $key, $p, $o ), "Grupo $name: «{$key}» distinto de PRO." );
		}
		check_nm( ! preg_match( $pro_classes, (string) $o['selector'] ) && str_contains( (string) $o['selector'], 'digi-nav' ), "Grupo $name: selector de PRO." );
	}
	foreach ( $pro->sections as $name => $p ) {
		check_nm( isset( $own->sections[ $name ] ) && $same( 'tab', $p, $own->sections[ $name ] ) && $same( 'condition', $p, $own->sections[ $name ] ), "Sección $name: falta o cambia de pestaña o condición." );
	}
	check_nm( array( 'principal', 'secundario' ) === array_keys( $own->controls['menu']['options'] ) && 'principal' === $own->controls['menu']['default'] && 'select' === $own->controls['menu']['type'], 'Menú: los del sitio, el primero por defecto y guardado.' );
	check_nm( array( 'mobile', 'tablet', 'none' ) === array_keys( $own->controls['dropdown']['options'] ) && 'Móvil vertical (> 767px)' === $own->controls['dropdown']['options']['mobile'], 'Punto de corte: los activos (sin portátil ni pantalla ancha) y «Ninguno».' );
	check_nm( 'Menú de WordPress' !== $own->controls['menu_name']['default'] && 'Menú' === $own->controls['menu_name']['default'], 'Nombre del menú en español.' );
	// Sin menús: el aviso, como PRO.
	$GLOBALS['nm']['menus'] = array();
	$empty_own = $controls_of( new Nav_Menu() );
	$empty_pro = $controls_of( new \ElementorPro\Modules\NavMenu\Widgets\Nav_Menu() );
	check_nm( 'alert' === $empty_own->controls['menu']['type'] && 'alert' === $empty_pro->controls['menu']['type'] && str_contains( $empty_own->controls['menu']['content'], 'nav-menus.php?action=edit&menu=0' ), 'Sin menús: el aviso con el enlace para crear uno.' );
	check_nm( '' === $render( array( 'menu' => 'principal' ) ), 'Sin menús en el sitio no se pinta nada.' );
	$GLOBALS['nm']['menus'] = array( 'principal' => 'Principal', 'secundario' => 'Secundario' );
	// Puntos de corte extra: las mismas opciones que PRO.
	$GLOBALS['nm']['breakpoints'] = array( 'mobile' => new NM_Breakpoint( 'Móvil vertical', 767 ), 'mobile_extra' => new NM_Breakpoint( 'Móvil horizontal', 880 ), 'tablet' => new NM_Breakpoint( 'Tableta vertical', 1024 ), 'tablet_extra' => new NM_Breakpoint( 'Tableta horizontal', 1200 ), 'laptop' => new NM_Breakpoint( 'Portátil', 1366 ), 'widescreen' => new NM_Breakpoint( 'Pantalla ancha', 2400 ) );
	$x_own = $controls_of( new Nav_Menu() );
	$x_pro = $controls_of( new \ElementorPro\Modules\NavMenu\Widgets\Nav_Menu() );
	check_nm( array_keys( $x_pro->controls['dropdown']['options'] ) === array_keys( $x_own->controls['dropdown']['options'] ) && array( 'mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'none' ) === array_keys( $x_own->controls['dropdown']['options'] ), 'Con los puntos de corte extra, las mismas opciones que PRO.' );

	// 3. Cobertura del mapa.
	check_nm( 'nav-menu' === $map['source'] && 'digitalisimo-nav-menu' === $map['target'] && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), 'Origen, destino, estado y panel del mapa.' );
	$declared = array_merge( $map['keep'], array_keys( $map['rename'] ?? array() ), array_keys( $map['groups'] ), $map['ui'], array_keys( $map['drop'] ?? array() ) );
	foreach ( array_keys( $pro->controls ) as $name ) {
		check_nm( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
		if ( in_array( $name, $map['ui'], true ) ) {
			check_nm( in_array( $own->controls[ $name ]['type'], array( 'heading', 'divider', 'raw_html' ), true ), "$name: elemento del panel sin valor." );
		} else {
			check_nm( isset( $own->controls[ $name ] ), "$name: sin control propio." );
		}
	}
	foreach ( array_keys( $pro->groups ) as $name ) {
		check_nm( ( $map['groups'][ $name ] ?? '' ) === $name && isset( $own->groups[ $name ] ), "$name: grupo sin declarar o sin destino." );
	}
	check_nm( count( $declared ) === count( array_unique( $declared ) ) && count( $declared ) === count( $pro->controls ) + count( $pro->groups ) + 1, 'El mapa declara cada control de PRO una sola vez, más `menu_id` (' . count( $declared ) . ' / ' . ( count( $pro->controls ) + count( $pro->groups ) + 1 ) . ').' );
	foreach ( $map['defaults'] as $key => $value ) {
		check_nm( isset( $pro->controls[ $key ] ) && ( 'menu_name' === $key ? 'Menu' === $value : $pro->controls[ $key ]['default'] === $value ), "$key: el valor por defecto del mapa no es el de PRO." );
	}

	// 4. Marcado del servidor.
	$GLOBALS['nm']['breakpoints'] = array( 'mobile' => new NM_Breakpoint( 'Móvil vertical', 767 ), 'tablet' => new NM_Breakpoint( 'Tableta vertical', 1024 ) );
	$GLOBALS['nm']['calls']       = array();
	$html                         = $render( array( 'menu' => 'principal' ) );
	check_nm( 2 === count( $GLOBALS['nm']['calls'] ) && 'menu-1-abc1234' === $GLOBALS['nm']['calls'][0]->menu_id && 'menu-2-abc1234' === $GLOBALS['nm']['calls'][1]->menu_id && 'digi-nav__list' === $GLOBALS['nm']['calls'][0]->menu_class && '' === $GLOBALS['nm']['calls'][0]->container, 'Dos llamadas a wp_nav_menu() (principal y desplegable) con ids únicos, como PRO.' );
	check_nm( 0 === strpos( $html, '<div class="digi-nav"><nav class="digi-nav__main digi-nav__container digi-nav--layout-horizontal digi-nav--p-underline digi-nav--a-fade" aria-label="Menú"><ul id="menu-1-abc1234" class="digi-nav__list">' ), 'Menú principal con disposición, puntero, animación y nombre: ' . substr( $html, 0, 220 ) );
	check_nm( str_contains( $html, '<button type="button" class="digi-nav__toggle" aria-expanded="false" aria-controls="digi-nav-dd-abc1234" aria-label="Menú"><i class="digi-nav__toggle-open eicon-menu-bar" aria-hidden="true"></i><i class="digi-nav__toggle-close eicon-close" aria-hidden="true"></i></button><nav class="digi-nav__dropdown digi-nav__container" id="digi-nav-dd-abc1234" aria-label="Menú" inert><ul id="menu-2-abc1234" class="digi-nav__list">' ), 'Hamburguesa nativa con los iconos de PRO, que controla el desplegable.' );
	check_nm( ! preg_match( '/<li id=|role="menu|tabindex|aria-hidden="true"><ul/', $html ) && 1 === substr_count( $html, ' inert>' ), 'Desplegable cerrado e inert; sin ids en los elementos (el menú se pinta dos veces), sin role=menu, sin tabindex y sin ocultar el desplegable con aria-hidden.' );
	check_nm( 2 === substr_count( $html, '<a href="https://ejemplo.test/" aria-current="page" class="digi-nav__link digi-nav__active">Inicio</a>' ), 'El elemento actual: aria-current de WordPress y la clase propia (en los dos menús).' );
	check_nm( 2 === substr_count( $html, '<a href="https://ejemplo.test/servicios/" class="digi-nav__link">Servicios<span class="digi-nav__arrow" aria-hidden="true"><i class="fas fa-caret-down" aria-hidden="true"></i></span></a><button type="button" class="digi-nav__sub-toggle" aria-expanded="false" aria-label="Submenú de Servicios" hidden></button><ul class="sub-menu digi-nav__dropdown">' ), 'Un padre: indicador decorativo dentro del enlace, botón «Submenú de …» y su lista.' );
	check_nm( 2 === substr_count( $html, 'class="digi-nav__sublink">Desarrollo <b>web</b><span class="digi-nav__arrow"' ) && 2 === substr_count( $html, 'aria-label="Submenú de Desarrollo web"' ), 'Un padre de segundo nivel: clase de subnivel, indicador y nombre sin etiquetas.' );
	check_nm( 6 === substr_count( $html, 'digi-nav__sub-toggle' ) && 6 === substr_count( $html, 'class="sub-menu digi-nav__dropdown"' ), 'Un botón por submenú en cada menú.' );
	check_nm( 2 === substr_count( $html, 'href="https://ejemplo.test/#contacto" class="digi-nav__link digi-nav__anchor">Contacto</a>' ) && 2 === substr_count( $html, 'href="#" class="digi-nav__link digi-nav__anchor">Equipo' ), 'Anclas con su clase.' );
	check_nm( 0 === count( array_filter( array_intersect_key( $GLOBALS['nm']['filters'], array_flip( array( 'nav_menu_link_attributes', 'nav_menu_submenu_css_class', 'nav_menu_item_id', 'walker_nav_menu_start_el', 'nav_menu_item_title' ) ) ) ) ), 'Los filtros del menú se quitan después de pintarlo.' );
	$plain = $render( array( 'menu' => 'principal', 'layout' => 'vertical', 'pointer' => 'framed', 'animation_framed' => 'draw', 'toggle' => '', 'submenu_icon' => array( 'value' => '', 'library' => '' ), 'menu_name' => 'Pie <x>' ) );
	check_nm( str_contains( $plain, '<nav class="digi-nav__main digi-nav__container digi-nav--layout-vertical digi-nav--p-framed digi-nav--a-draw" aria-label="Pie &lt;x&gt;">' ), 'Vertical con marco: la primera animación con valor (como PRO) y el nombre escapado: ' . substr( $plain, 0, 200 ) );
	check_nm( ! str_contains( $plain, 'digi-nav__toggle' ) && ! str_contains( $plain, 'digi-nav__arrow' ) && ! str_contains( $plain, ' inert' ) && 6 === substr_count( $plain, 'digi-nav__sub-toggle' ), 'Sin hamburguesa ni indicador: el desplegable abierto (sin inert); los botones de los submenús siguen.' );
	$no_dd = $render( array( 'menu' => 'principal', 'dropdown' => 'none' ) );
	check_nm( ! str_contains( $no_dd, 'digi-nav__toggle' ) && ! str_contains( $no_dd, 'digi-nav__container"' ) && 3 === substr_count( $no_dd, 'digi-nav__sub-toggle' ), 'Sin punto de corte: sólo el menú principal (ni hamburguesa ni desplegable).' );
	$GLOBALS['nm']['features'] = array( 'e_font_icon_svg' => true );
	check_nm( str_contains( $render( array( 'menu' => 'principal', 'submenu_icon' => array( 'value' => 'fas fa-chevron-down', 'library' => 'fa-solid' ) ) ), '<i class="fa-svg-chevron-down fas fa-chevron-down"' ), 'Con iconos en SVG, el chevrón lleva la clase que lo achica, como PRO.' );
	$GLOBALS['nm']['features'] = array();
	$drop = $render( array( 'menu' => 'secundario', 'layout' => 'dropdown', 'toggle_icon_normal' => array( 'value' => 'fas fa-bars', 'library' => 'fa-solid' ), 'toggle_icon_hover_animation' => 'grow' ) );
	check_nm( ! str_contains( $drop, 'digi-nav__main' ) && str_contains( $drop, '<i class="digi-nav__toggle-open elementor-animation-grow fas fa-bars" aria-hidden="true"></i><i class="digi-nav__toggle-close elementor-animation-grow eicon-close"' ), 'Sólo desplegable: sin menú principal; icono propio y animación del botón como PRO.' );
	check_nm( '' === $render( array( 'menu' => 'otro' ) ), 'Un menú que no existe no pinta nada.' );
	$none = $render( array( 'menu' => 'principal', 'pointer' => 'none' ) );
	check_nm( str_contains( $none, 'digi-nav--p-none"' ) && ! str_contains( $none, 'digi-nav--a-' ), 'Puntero «Ninguno»: la clase como en PRO, sin animación (ni hoja de puntero).' );
	// Puntos de corte que la hoja no trae: una vez por página.
	$probe = new Nav_Menu();
	check_nm( '' === $probe->breakpoint_style( 'tablet', 'horizontal' ) && '' === $probe->breakpoint_style( 'mobile', 'horizontal' ) && '' === $probe->breakpoint_style( 'none', 'horizontal' ) && '' === $probe->breakpoint_style( 'tablet', 'dropdown' ), 'Los puntos de corte predeterminados los trae la hoja.' );
	$GLOBALS['nm']['breakpoints']['tablet'] = new NM_Breakpoint( 'Tableta vertical', 1100 );
	$GLOBALS['nm']['breakpoints']['mobile_extra'] = new NM_Breakpoint( 'Móvil horizontal', 880 );
	check_nm( '<style>@media(max-width:1100px){.digi-nav--dropdown-tablet .digi-nav__main{display:none}}@media(min-width:1101px){.digi-nav--dropdown-tablet .digi-nav__toggle,.digi-nav--dropdown-tablet .digi-nav__container.digi-nav__dropdown{display:none}}</style>' === $probe->breakpoint_style( 'tablet', 'horizontal' ) && '' === $probe->breakpoint_style( 'tablet', 'horizontal' ), 'Tableta a 1 100 px: sus reglas, una vez por página.' );
	check_nm( str_contains( $probe->breakpoint_style( 'mobile_extra', 'horizontal' ), 'max-width:880px' ) && '' === $probe->breakpoint_style( 'laptop', 'horizontal' ) && '' === $probe->breakpoint_style( 'x;}', 'horizontal' ), 'Móvil horizontal: sus reglas; un punto de corte inactivo o raro, nada.' );
	Nav_Menu::reset_printed();
	$GLOBALS['nm']['breakpoints'] = array( 'mobile' => new NM_Breakpoint( 'Móvil vertical', 767 ), 'tablet' => new NM_Breakpoint( 'Tableta vertical', 1024 ) );
	$GLOBALS['nm']['rtl'] = true;
	$rtl = $controls_of( new Nav_Menu() );
	check_nm( array( 'left' => 'end', 'right' => 'start' ) === $rtl->controls['align_items']['classes_dictionary'] && 'eicon-align-end-h' === $rtl->controls['align_items']['options']['start']['icon'], 'RTL: el diccionario de alineación antiguo y los iconos, como PRO.' );
	$GLOBALS['nm']['rtl'] = false;
	// Exportación e importación de kits: el id del menú junto a su slug, como PRO.
	$exported = $own->on_export( array( 'settings' => array( 'menu' => 'principal' ) ) );
	check_nm( 7 === $exported['settings']['menu_id'] && 'principal' === $exported['settings']['menu'], 'Exportar guarda el id del menú.' );
	check_nm( array( 'settings' => array() ) === $own->on_export( array( 'settings' => array( 'menu' => 'otro' ) ) ), 'Exportar un menú que no existe quita el slug.' );
	$imported = Nav_Menu::on_import_update_dynamic_content( array( 'settings' => array( 'menu' => 'principal', 'menu_id' => 7 ) ), array( 'term_ids' => array( 7 => 9 ) ) );
	check_nm( array( 'settings' => array( 'menu' => 'principal-2' ) ) === $imported, 'Importar cambia el slug por el del menú importado y quita el id.' );

	// 5. Conversión de menús de PRO.
	$cases = array(
		'defaults' => array( 'menu' => 'principal' ),
		'layout'   => array( 'menu' => 'secundario', 'layout' => 'vertical', 'align_items' => 'center', 'pointer' => 'background', 'animation_background' => 'sweep-left', 'submenu_icon' => array( 'value' => 'fas fa-chevron-down', 'library' => 'fa-solid' ), 'dropdown' => 'mobile', 'full_width' => 'stretch', 'text_align' => 'center', 'toggle_align' => 'right', 'toggle_icon_normal' => array( 'value' => 'fas fa-bars', 'library' => 'fa-solid' ), 'toggle_icon_hover_animation' => 'pulse' ),
		'style'    => array( 'menu' => 'principal', 'menu_typography_typography' => 'custom', 'menu_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'menu_typography_font_weight' => '600', 'color_menu_item' => '#111111', 'color_menu_item_hover' => '#245eff', 'pointer_color_menu_item_hover' => '#e63946', 'color_menu_item_active' => '#2a9d8f', 'pointer_color_menu_item_active' => '#264653', 'nav_menu_divider' => 'yes', 'nav_menu_divider_style' => 'dashed', 'nav_menu_divider_weight' => array( 'unit' => 'px', 'size' => 2 ), 'nav_menu_divider_height' => array( 'unit' => '%', 'size' => 50 ), 'nav_menu_divider_color' => '#cccccc', 'pointer_width' => array( 'unit' => 'px', 'size' => 4 ), 'pointer_width_mobile' => array( 'unit' => 'px', 'size' => 2 ), 'padding_horizontal_menu_item' => array( 'unit' => 'px', 'size' => 12 ), 'padding_horizontal_menu_item_tablet' => array( 'unit' => 'px', 'size' => 8 ), 'padding_vertical_menu_item' => array( 'unit' => 'px', 'size' => 6 ), 'menu_space_between' => array( 'unit' => 'px', 'size' => 20 ), 'border_radius_menu_item' => array( 'unit' => 'px', 'size' => 4 ) ),
		'dropdown' => array( 'menu' => 'principal', 'color_dropdown_item' => '#ffffff', 'background_color_dropdown_item' => '#111111', 'color_dropdown_item_hover' => '#111111', 'background_color_dropdown_item_hover' => '#ffd166', 'color_dropdown_item_active' => '#245eff', 'background_color_dropdown_item_active' => '#eeeeee', 'dropdown_typography_typography' => 'custom', 'dropdown_typography_font_size' => array( 'unit' => 'px', 'size' => 15 ), 'dropdown_border_border' => 'solid', 'dropdown_border_width' => array( 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ), 'dropdown_border_color' => '#dddddd', 'dropdown_border_radius' => array( 'unit' => 'px', 'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'isLinked' => true ), 'dropdown_box_shadow_box_shadow_type' => 'yes', 'dropdown_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 4, 'blur' => 12, 'spread' => 0, 'color' => 'rgba(0,0,0,.2)' ), 'padding_horizontal_dropdown_item' => array( 'unit' => 'px', 'size' => 24 ), 'padding_vertical_dropdown_item' => array( 'unit' => 'px', 'size' => 12 ), 'dropdown_divider_border' => 'solid', 'dropdown_divider_color' => '#eeeeee', 'dropdown_divider_width' => array( 'unit' => 'px', 'size' => 1 ), 'dropdown_top_distance' => array( 'unit' => 'px', 'size' => 8 ), 'dropdown_top_distance_mobile' => array( 'unit' => 'px', 'size' => 0 ) ),
		'toggle'   => array( 'menu' => 'principal', 'toggle_color' => '#ffffff', 'toggle_background_color' => '#111111', 'toggle_color_hover' => '#ffd166', 'toggle_background_color_hover' => '#245eff', 'toggle_size' => array( 'unit' => 'px', 'size' => 28 ), 'toggle_border_width' => array( 'unit' => 'px', 'size' => 2 ), 'toggle_border_radius' => array( 'unit' => 'px', 'size' => 50 ), 'toggle_icon_active' => array( 'value' => 'fas fa-times', 'library' => 'fa-solid' ) ),
		'globals'  => array( 'menu' => 'principal', 'color_menu_item' => '', '__globals__' => array( 'color_menu_item' => 'globals/colors?id=primary', 'color_menu_item_hover' => 'globals/colors?id=accent', 'pointer_color_menu_item_hover' => 'globals/colors?id=secondary', 'nav_menu_divider_color' => 'globals/colors?id=text', 'menu_typography_typography' => 'globals/typography?id=primary', 'dropdown_typography_typography' => 'globals/typography?id=accent', 'color_dropdown_item' => 'globals/colors?id=text', '_background_color' => 'globals/colors?id=secondary' ) ),
		'dynamic'  => array( 'menu' => 'principal', '__dynamic__' => array( 'menu_name' => '[elementor-tag id="t1" name="site-title" settings="%7B%7D"]' ) ),
		'kit'      => array( 'menu' => 'principal', 'menu_id' => 7, 'menu_name' => 'Principal', 'pointer' => 'text', 'animation_text' => 'float', 'layout' => 'horizontal', 'dropdown' => 'none' ),
		'css'      => array( 'menu' => 'principal', 'custom_css' => "selector .elementor-nav-menu--main .elementor-item.elementor-item-active{font-weight:700}\nselector .elementor-menu-toggle.elementor-active{color:red}\nselector .e--pointer-underline.e--animation-grow .elementor-item:after{height:4px}\n.elementor-widget-nav-menu .sub-arrow i{font-size:8px}\nselector .elementor-nav-menu--dropdown a.highlighted{color:#000}", '_css_classes' => 'menu-cabecera', '_element_id' => 'principal', 'sticky' => 'top', 'hide_mobile' => 'hidden-mobile', '__fa4_migrated' => array( 'toggle_icon_normal' => true ) ),
	);
	$tree = array( array( 'id' => 'p1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'nav-menu', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_nm( count( $cases ) === count( $log ), 'Se convierte cada menú del árbol (' . count( $log ) . ').' );
	$own_keys = array_keys( $own->controls );
	$lands    = static function ( $key ) use ( $own_keys, $own ) {
		$base = Translator::base( (string) $key );
		if ( in_array( $base, $own_keys, true ) || 'menu_id' === $base || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
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
		check_nm( 'digitalisimo-nav-menu' === $element['widgetType'] && $tree[0]['elements'][ $index ]['id'] === $element['id'], $element['id'] . ': pasa al Menú de WordPress con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_nm( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_nm( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_nm( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni pro.approx; sólo el CSS: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_nm( 'Menu' === $d['menu_name'] && 'horizontal' === $d['layout'] && 'underline' === $d['pointer'] && 'tablet' === $d['dropdown'] && 'burger' === $d['toggle'] && 'fas fa-caret-down' === $d['submenu_icon']['value'] && 'nav-menu' === $d['_digitalisimo_pro'], 'Un menú sin cambios llega con los valores visibles de PRO.' );
	$l = $out['layout'];
	check_nm( 'vertical' === $l['layout'] && 'center' === $l['align_items'] && 'sweep-left' === $l['animation_background'] && 'stretch' === $l['full_width'] && 'mobile' === $l['dropdown'] && 'pulse' === $l['toggle_icon_hover_animation'] && 'fas fa-bars' === $l['toggle_icon_normal']['value'], 'Diseño, puntero, punto de corte, ancho completo e iconos.' );
	check_nm( 18 === $out['style']['menu_typography_font_size']['size'] && 8 === $out['style']['padding_horizontal_menu_item_tablet']['size'] && 2 === $out['style']['pointer_width_mobile']['size'] && 'dashed' === $out['style']['nav_menu_divider_style'], 'Estilo del menú principal, por dispositivo.' );
	check_nm( 'yes' === $out['dropdown']['dropdown_box_shadow_box_shadow_type'] && 0 === $out['dropdown']['dropdown_top_distance_mobile']['size'] && 'solid' === $out['dropdown']['dropdown_divider_border'] && '#ffd166' === $out['dropdown']['background_color_dropdown_item_hover'], 'Estilo del desplegable.' );
	check_nm( 50 === $out['toggle']['toggle_border_radius']['size'] && 'fas fa-times' === $out['toggle']['toggle_icon_active']['value'], 'Estilo e icono del botón.' );
	check_nm( $cases['globals']['__globals__'] === $out['globals']['__globals__'] && $cases['dynamic']['__dynamic__'] === $out['dynamic']['__dynamic__'], 'Colores, tipografías y etiquetas dinámicas siguen a su control.' );
	check_nm( 7 === $out['kit']['menu_id'] && 'float' === $out['kit']['animation_text'], 'El id del menú de un kit pasa tal cual (lo traduce la importación).' );
	check_nm( "selector .digi-nav__main .digi-nav__link.digi-nav__active{font-weight:700}\nselector .digi-nav__toggle[aria-expanded=true]{color:red}\nselector .digi-nav--p-underline.digi-nav--a-grow .digi-nav__link:after{height:4px}\n.elementor-widget-digitalisimo-nav-menu .digi-nav__arrow i{font-size:8px}\nselector .digi-nav__dropdown a.digi-nav__open{color:#000}" === $out['css']['custom_css'], 'CSS personalizado con las clases propias: ' . json_encode( $out['css']['custom_css'] ) );
	check_nm( 'menu-cabecera' === $out['css']['_css_classes'] && 'principal' === $out['css']['_element_id'] && 'top' === $out['css']['sticky'] && 'hidden-mobile' === $out['css']['hide_mobile'] && array( 'toggle_icon_normal' => true ) === $out['css']['__fa4_migrated'], 'Avanzado, fijado e iconos migrados intactos.' );
	check_nm( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	$painted = $render( $out['layout'] );
	check_nm( str_contains( $painted, 'digi-nav--layout-vertical digi-nav--p-background digi-nav--a-sweep-left' ) && str_contains( $painted, 'aria-label="Menu"' ) && str_contains( $painted, 'fas fa-chevron-down' ) && str_contains( $painted, 'elementor-animation-pulse fas fa-bars' ), 'El menú convertido se pinta con sus ajustes.' );

	// 6. Página de prueba para tests/elements-pro-nav-menu-chrome.mjs: los menús que pinta el widget de hoy.
	$fixture_cases = array(
		'n1' => array( 'settings' => array( 'menu' => 'principal' ), 'wrapper' => 'digi-nav--dropdown-tablet digi-nav--toggle digi-nav--burger digi-nav--text-aside' ),
		'n2' => array( 'settings' => array( 'menu' => 'principal', 'pointer' => 'background' ), 'wrapper' => 'digi-nav--dropdown-mobile digi-nav--toggle digi-nav--burger digi-nav--stretch digi-nav--text-center digi-nav--align-end' ),
		'n3' => array( 'settings' => array( 'menu' => 'principal', 'layout' => 'vertical', 'pointer' => 'framed', 'animation_framed' => 'draw', 'dropdown' => 'none' ), 'wrapper' => 'digi-nav--dropdown-none" style="width:240px' ),
	);
	$body = '';
	foreach ( $fixture_cases as $fid => $fc ) {
		$w           = new Nav_Menu();
		$w->controls = $own->controls;
		$w->settings = $fc['settings'] + $defaults( $own );
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $w, 'render' ) ) )->invoke( $w );
		$body .= '<div class="elementor-element elementor-widget elementor-widget-digitalisimo-nav-menu ' . $fc['wrapper'] . '" data-id="' . $fid . '">' . str_replace( 'abc1234', $fid, (string) ob_get_clean() ) . "</div>\n<div class=\"spacer\"><p id=\"contacto\">Contacto</p></div>\n";
	}
	$links = '';
	foreach ( array( 'nav-menu' => Module::WIDGETS['digitalisimo-nav-menu']['css'] ) + Nav_Menu::SHEETS as $file ) {
		$links .= '<link rel="stylesheet" href="../../digitalisimo-elements/' . $file . "\">\n";
	}
	$fixture = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>digi-nav</title>\n<!-- Generada por tests/elements-pro-nav-menu.php (--write); la usa tests/elements-pro-nav-menu-chrome.mjs. -->\n<style>:root{--direction-multiplier:1}body{margin:0;font:16px/1.5 sans-serif}.elementor-widget{position:relative;margin:0 0 40px;padding:0 20px}.spacer{height:300px}i[class*=fa-]{display:inline-block;width:.7em;height:.7em;background:currentColor}i[class*=eicon-]{display:inline-block;width:1em;height:1em;background:currentColor}button{border:1px solid #c36;padding:.5rem 1rem;border-radius:3px;color:#c36}</style>\n" . $links . "</head><body>\n" . $body . "<script src=\"../../digitalisimo-elements/modules/digitalisimo-navigation/assets/js/nav-menu.js\"></script>\n</body></html>\n";
	$path    = __DIR__ . '/fixtures/nav-menu.html';
	if ( in_array( '--write', $argv ?? array(), true ) ) {
		file_put_contents( $path, $fixture );
	}
	check_nm( is_file( $path ) && file_get_contents( $path ) === $fixture, 'tests/fixtures/nav-menu.html es el marcado de hoy (regenerar con --write).' );

	echo 'DIGITALÍSIMO Elements: Menú de WordPress (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, hojas de ' . implode( '/', array_map( static function ( $f ) use ( $root ) { return filesize( $root . $f ); }, array( 'nav-menu' => Module::WIDGETS['digitalisimo-nav-menu']['css'] ) + Nav_Menu::SHEETS ) ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa desde el de PRO (' . count( $cases ) . " casos) validados.\n";
}
