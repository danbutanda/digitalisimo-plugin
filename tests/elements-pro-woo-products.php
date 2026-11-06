<?php
/**
 * Productos de tienda propios (C7 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c7.md, frente N, fork P):
 * Productos, Productos (clásico), Productos del archivo y Productos del archivo (clásico)
 * (`modules/digitalisimo-woo/store/`), frente a los widgets de PRO que sustituyen (`modules/woocommerce/widgets/`:
 * `products.php`, `products-deprecated.php`, `archive-products.php`, `archive-products-deprecated.php`) y sus mapas
 * (`modules/digitalisimo-convert/maps/{woocommerce-products,wc-products,wc-archive-products,woocommerce-archive-products}.php`).
 *
 * Carga los archivos **reales** de PRO (con su base `products-base.php`, `base-widget.php`, el trait de consulta, la
 * presentación «Classic» y los renderizadores) y los propios sobre una `Widget_Base` que registra los controles, y
 * compara cada par: controles, grupos y secciones con el mismo nombre, orden, tipo, valor por defecto (también por
 * dispositivo), opciones, condiciones, rangos, globales y selectores (con las clases de PRO pasadas a las propias);
 * registro (nombre, título, categoría, oculto del panel los clásicos, línea de `Woo\Registry`); render con un
 * WooCommerce de prueba (los mismos argumentos de consulta y el mismo HTML que PRO, salvo las clases propias); mapas
 * (cada control de PRO, también los heredados de sus padres, declarado; conversión de ajustes reales sin
 * `pro.unmapped` y con cada clave en un control propio); hojas (≤ 4 KB, foco visible, sin `--e-global-*` ni `url()`);
 * lista negra del empaquetado y dominios de proveedores; partes de Productos y vista rápida enganchadas al propio.
 */
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors {
		const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent';
	}
	class Global_Typography {
		const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent';
	}
}
namespace Elementor\Core\Breakpoints {
	class Manager {
		public static function get_default_config() { return array( 'mobile' => array(), 'mobile_extra' => array(), 'tablet' => array(), 'tablet_extra' => array(), 'laptop' => array(), 'widescreen' => array() ); }
	}
}
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $skins    = array();
		private $section = '';
		private $injection = null;
		public function __construct() {
			if ( method_exists( $this, 'register_skins' ) ) {
				( new \ReflectionMethod( $this, 'register_skins' ) )->invoke( $this );
			}
		}
		public function get_name() { return 'widget'; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_style_depends(): array { return array(); }
		public function get_script_depends(): array { return array(); }
		public function show_in_panel() { return true; }
		public function has_widget_inner_wrapper(): bool { return true; }
		public function add_skin( $skin ) { $this->skins[ $skin->get_id() ] = $skin; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args + array( 'tab' => 'content' ); $this->section = $name; $this->order[] = 'section:' . $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name, $args = array() ) { $this->order[] = 'tabs:' . $name; }
		public function start_controls_tab( $name, $args ) { $this->order[] = 'tab:' . $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function start_injection( $args ) { $this->injection = $args; }
		public function end_injection() { $this->injection = null; }
		public function add_control( $name, $args, $options = array() ) { $this->controls[ $name ] = $args + array( 'section' => $this->section ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args, $options = array() ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args, $options = array() ) { $this->groups[ $args['name'] ] = array( 'type' => $type, 'section' => $this->section ) + $args; $this->order[] = 'group:' . $args['name']; }
		public function update_control( $name, $args, $options = array() ) {
			$merge = ! empty( $options['recursive'] ) ? 'array_replace_recursive' : 'array_merge';
			if ( isset( $this->controls[ $name ] ) ) {
				$this->controls[ $name ] = $merge( $this->controls[ $name ], $args );
			} elseif ( isset( $this->sections[ $name ] ) ) {
				$this->sections[ $name ] = $merge( $this->sections[ $name ], $args );
			} else {
				// Un control de un grupo (`query_post_type`): se anota como cambio del grupo.
				$this->controls[ $name ] = $args + array( 'updated' => true );
			}
		}
		public function remove_control( $name ) { unset( $this->controls[ $name ] ); $this->order = array_values( array_diff( $this->order, array( $name ) ) ); $this->removed[] = $name; }
		public function remove_responsive_control( $name ) { $this->remove_control( $name ); }
		public $removed = array();
		protected function deprecated_notice( $plugin, $since, $last = '', $replacement = '' ) {
			$this->start_controls_section( 'Deprecated', array( 'label' => 'Deprecated' ) );
			$this->add_control( 'deprecated_notice', array( 'type' => 'deprecated_notice' ) );
			$this->end_controls_section();
		}
	}
	class Skin_Base {
		public $parent;
		public function __construct( $parent ) { $this->parent = $parent; $this->_register_controls_actions(); }
		protected function _register_controls_actions() {}
		public function add_control( $id, $args ) { $this->parent->add_control( $this->get_id() . '_' . $id, $args ); }
		public function get_instance_value( $key ) { return $this->parent->settings[ $this->get_id() . '_' . $key ] ?? null; }
		public function set_parent( $parent ) { $this->parent = $parent; }
	}
	class Controls_Stack { const RESPONSIVE_DESKTOP = 'desktop'; const RESPONSIVE_TABLET = 'tablet'; const RESPONSIVE_MOBILE = 'mobile'; }
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const NUMBER = 'number';
		const TAB_STYLE = 'style'; const TAB_CONTENT = 'content'; const ALERT = 'alert'; const RAW_HTML = 'raw_html'; const HIDDEN = 'hidden';
		const DEPRECATED_NOTICE = 'deprecated_notice';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Plugin { public static $instance; public $experiments; public $editor; public $preview; }
	class Test_State {
		public $optimized = false;
		public function is_feature_active( $f ) { return 'e_optimized_markup' === $f && $this->optimized; }
		public function is_edit_mode() { return false; }
		public function is_preview_mode() { return false; }
	}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } public static function get_title() { return 'PRO'; } }
}
namespace ElementorPro\Base {
	abstract class Base_Widget extends \Elementor\Widget_Base {
		public function get_categories() { return array( 'pro-elements' ); }
	}
}
namespace ElementorPro\Modules\QueryControl\Controls {
	class Group_Control_Query { public static function get_type() { return 'query-group'; } }
	class Group_Control_Posts {
		public static function get_type() { return 'posts'; }
		public static function on_export_remove_setting_from_element( $element, $name ) { $element['exported'] = $name; return $element; }
	}
}
namespace ElementorPro\Modules\QueryControl {
	class Module {
		const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_POST = 'post';
		private static $instance;
		public static function instance() { return self::$instance ?? ( self::$instance = new self() ); }
		public function get_query_args( $control_id, $settings ) { return array( 'post_type' => 'product', 'posts_per_page' => (int) ( $settings['posts_per_page'] ?? 4 ), 'from' => $control_id ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$root = __DIR__ . '/../digitalisimo-elements/';
	define( 'DIGITALISIMO_ELEMENTS_FILE', $root . 'pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	$GLOBALS['wcp'] = array( 'filters' => array(), 'removed' => array(), 'queries' => array(), 'notices' => 0, 'front' => false, 'theme' => true, 'paged' => 1 );
	function __( $v, $d = '' ) { return $v; }
	function _x( $v, $c, $d = '' ) { return $v; }
	function esc_html__( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_kses_post( $v ) { return (string) $v; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['wcp']['filters'][ $hook ][] = $cb; return true; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['wcp']['filters'][ $hook ][] = $cb; return true; }
	function remove_action( $hook, $cb, $priority = 10 ) { $GLOBALS['wcp']['removed'][] = $hook . ( is_string( $cb ) ? ':' . $cb : '' ); return true; }
	function remove_filter( $hook, $cb, $priority = 10 ) { $GLOBALS['wcp']['removed'][] = $hook; return true; }
	function apply_filters( $hook, $value ) { foreach ( $GLOBALS['wcp']['filters'][ $hook ] ?? array() as $cb ) { $value = call_user_func( $cb, $value ); } return $value; }
	function do_action( $hook ) { foreach ( $GLOBALS['wcp']['filters'][ $hook ] ?? array() as $cb ) { call_user_func( $cb ); } }
	function get_theme_support( $f ) { return $GLOBALS['wcp']['theme']; }
	function is_front_page() { return $GLOBALS['wcp']['front']; }
	function is_page( $id = 0 ) { return false; }
	function is_singular() { return true; }
	function get_queried_object_id() { return 77; }
	function is_checkout() { return false; }
	function get_query_var( $k, $d = '' ) { return 'paged' === $k ? $GLOBALS['wcp']['paged'] : $d; }
	function wc_get_page_id( $p ) { return 5; }
	function wc_string_to_bool( $v ) { return true === $v || 'yes' === $v || 1 === $v || '1' === $v || 'true' === $v; }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_parse_id_list( $l ) { return array_map( 'intval', (array) $l ); }
	function wc_get_product_visibility_term_ids() { return array( 'featured' => 31, 'outofstock' => 32, 'exclude-from-catalog' => 33 ); }
	function wc_print_notices() { $GLOBALS['wcp']['notices']++; }
	function wc_get_product_ids_on_sale() { return array( 11, 12 ); }
	function get_term_by( $f, $id ) { return (object) array( 'taxonomy' => 'product_cat' ); }
	function wc_get_product( $id = false ) { return $GLOBALS['wcp']['product'] ?? false; }
	function WC() { return $GLOBALS['wcp']['wc']; }
	/** WooCommerce de prueba: el atajo `[products]` pinta la lista con lo que recibe y anota sus argumentos. */
	class WC_Shortcode_Products {
		protected $type = 'products'; protected $attributes = array(); protected $query_args = array();
		protected function parse_attributes( $a ) { return array_merge( array( 'limit' => '-1', 'columns' => '', 'rows' => '', 'paginate' => false, 'cache' => true ), $a ); }
		protected function set_visibility_query_args( &$q ) { $q['tax_query'][] = array( 'taxonomy' => 'product_visibility', 'operator' => 'NOT IN' ); }
		protected function set_sale_products_query_args( &$q ) { $q['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() ); }
		protected function get_query_results() { return (object) array( 'ids' => array( 11, 12 ), 'total' => 2, 'total_pages' => 1, 'per_page' => 4, 'current_page' => 1 ); }
		public function get_content() {
			$GLOBALS['wcp']['queries'][] = $this->query_args;
			$r = $this->get_query_results();
			return '<div class="woocommerce columns-' . esc_attr( $this->attributes['columns'] ?? '' ) . '"><ul class="products columns-4">' . implode( '', array_map( static function ( $id ) { return '<li class="product post-' . $id . '"><a href="/p/' . $id . '/" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">Producto ' . $id . '</a></li>'; }, $r->ids ) ) . '</ul></div>';
		}
	}
	class WCP_Query {
		public function get_meta_query() { return array(); }
		public function get_catalog_ordering_args( $orderby = '', $order = '' ) { return array( 'orderby' => '' === $orderby ? 'menu_order title' : $orderby, 'order' => '' === $order ? 'ASC' : $order, 'meta_key' => '' ); }
	}
	$GLOBALS['wcp']['wc'] = (object) array( 'query' => new WCP_Query(), 'session' => true, 'version' => '11.2.1' );
	class WP_Query { public $args; public function __construct( $args ) { $this->args = $args; } public function have_posts() { return false; } }
	function check_p( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	$state = new \Elementor\Test_State();
	\Elementor\Plugin::$instance->experiments = $state;
	\Elementor\Plugin::$instance->editor      = $state;
	\Elementor\Plugin::$instance->preview     = $state;

	// Archivos reales de PRO.
	$pro = $root . 'modules/woocommerce/';
	foreach ( array( 'traits/product-id-trait', 'traits/products-trait', 'classes/base-products-renderer', 'classes/products-renderer', 'classes/current-query-renderer', 'widgets/base-widget', 'widgets/products-base', 'skins/skin-classic', 'widgets/products', 'widgets/products-deprecated', 'widgets/archive-products', 'widgets/archive-products-deprecated' ) as $file ) {
		require $pro . $file . '.php';
	}
	// Propios, por la autocarga del módulo.
	require $root . 'modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry as Maps;
	use Digitalisimo\Elements\Convert\Translator;
	use Digitalisimo\Elements\Woo\Registry;
	use Digitalisimo\Elements\Woo\Store;

	$pairs = array(
		'woocommerce-products'         => array( 'pro' => \ElementorPro\Modules\Woocommerce\Widgets\Products::class, 'own' => Store\Products::class, 'name' => 'digitalisimo-woo-products', 'title' => 'Productos', 'cat' => 'woocommerce-elements', 'panel' => true ),
		'wc-products'                  => array( 'pro' => \ElementorPro\Modules\Woocommerce\Widgets\Products_Deprecated::class, 'own' => Store\Products_Classic::class, 'name' => 'digitalisimo-woo-products-classic', 'title' => 'Productos (clásico)', 'cat' => 'woocommerce-elements', 'panel' => false ),
		'wc-archive-products'          => array( 'pro' => \ElementorPro\Modules\Woocommerce\Widgets\Archive_Products::class, 'own' => Store\Archive_Products::class, 'name' => 'digitalisimo-woo-archive-products', 'title' => 'Productos del archivo', 'cat' => 'woocommerce-elements-archive', 'panel' => true ),
		'woocommerce-archive-products' => array( 'pro' => \ElementorPro\Modules\Woocommerce\Widgets\Archive_Products_Deprecated::class, 'own' => Store\Archive_Products_Classic::class, 'name' => 'digitalisimo-woo-archive-products-classic', 'title' => 'Productos del archivo (clásico)', 'cat' => 'woocommerce-elements-archive', 'panel' => false ),
	);
	// Clases y prefijos de PRO → propios (los mismos que `classes` de los mapas).
	$classes = array(
		'.elementor-wc-products'                 => '.digi-wc-products',
		'elementor-products-grid elementor-'     => 'digi-products-grid digi-',
		'elementor-product-loop-item--align-'    => 'digi-products--align-',
		'elementor-show-pagination-border-'      => 'digi-products--pagination-border-',
		'products-heading-'                      => 'digi-products--heading-',
		'.products-heading-show'                 => '.digi-products--heading-show',
		'.elementor-products-nothing-found'      => '.digi-products__nothing-found',
	);
	$to_own = static function ( $value ) use ( &$to_own, $classes ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ is_string( $k ) ? strtr( $k, $classes ) : $k ] = $to_own( $v );
			}
			return $out;
		}
		return is_string( $value ) ? strtr( $value, $classes ) : $value;
	};
	// Lo que se compara de cada control o grupo (no los textos, que son propios y en español).
	$shape = static function ( $args ) use ( &$shape ) {
		$text = array( 'label', 'title', 'content', 'description', 'placeholder', 'label_on', 'label_off', 'heading', 'section' );
		$out  = array();
		foreach ( (array) $args as $k => $v ) {
			if ( in_array( $k, $text, true ) ) {
				continue;
			}
			if ( 'options' === $k && is_array( $v ) ) {
				$out[ $k ] = array_keys( $v );
				continue;
			}
			if ( 'default' === $k && is_string( $v ) && ! is_numeric( $v ) && '' !== $v && ! in_array( $v, array( 'yes', 'no', 'product', 'date', 'desc', 'asc', 'wc-products', 'current_query', 'show', 'classic' ), true ) ) {
				$out[ $k ] = '(texto)';
				continue;
			}
			$out[ $k ] = is_array( $v ) ? $shape( $v ) : $v;
		}
		return $out;
	};
	$make = static function ( $class, array $settings = array() ) {
		$widget = new $class();
		( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
		foreach ( $widget->skins as $skin ) {
			$skin->register_controls( $widget );
		}
		$defaults = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$defaults[ $name ] = $control['default'];
			}
		}
		foreach ( $widget->groups as $name => $group ) {
			foreach ( (array) ( $group['fields_options'] ?? array() ) as $field => $opts ) {
				if ( isset( $opts['default'] ) ) {
					$defaults[ $name . '_' . $field ] = $opts['default'];
				}
			}
		}
		foreach ( $widget->controls as $name => $control ) {
			if ( ! empty( $control['updated'] ) && array_key_exists( 'default', $control ) ) {
				$defaults[ $name ] = $control['default'];
			}
		}
		$defaults += array( 'query_post_type' => 'product', 'query_orderby' => 'date', 'query_order' => 'desc' );
		$widget->settings = array_merge( $defaults, $settings );
		return $widget;
	};
	$render = static function ( $widget ) {
		ob_start();
		if ( $widget->skins ) {
			$skin = reset( $widget->skins );
			$skin->set_parent( $widget );
			$skin->render();
		} else {
			( new ReflectionMethod( $widget, method_exists( $widget, 'render_widget' ) ? 'render_widget' : 'render' ) )->invoke( $widget );
		}
		return (string) ob_get_clean();
	};

	// 1. Registro y controles, par a par.
	$registry = file_get_contents( $root . 'modules/digitalisimo-woo/class-registry.php' );
	$all_pro  = array();
	foreach ( $pairs as $source => $pair ) {
		$p = $make( $pair['pro'] );
		$o = $make( $pair['own'] );
		check_p( $pair['name'] === $o->get_name() && $source === $p->get_name(), "$source: nombres" );
		check_p( $pair['title'] === $o->get_title(), "$source: título" );
		check_p( array( $pair['cat'] ) === $o->get_categories() && $p->get_categories() === $o->get_categories(), "$source: categoría" );
		check_p( $pair['panel'] === $o->show_in_panel() && $p->show_in_panel() === $o->show_in_panel(), "$source: visible en el panel como PRO" );
		check_p( isset( Registry::WIDGETS[ $pair['name'] ] ) && Registry::TITLES[ $pair['name'] ] === $pair['title'] && str_contains( $registry, "'" . $pair['name'] . "' => array( 'file' => 'store/" ), "$source: línea en Woo\\Registry" );
		$pc = array_keys( $p->controls );
		$oc = array_diff( array_keys( $o->controls ), array( 'deprecated_notice' ) );
		$pc = array_diff( $pc, array( 'deprecated_notice' ) );
		check_p( array_values( $pc ) === array_values( $oc ), "$source: mismos controles en el mismo orden. Sólo PRO: " . implode( ', ', array_diff( $pc, $oc ) ) . ' · sólo propio: ' . implode( ', ', array_diff( $oc, $pc ) ) );
		check_p( array_keys( $p->groups ) === array_keys( $o->groups ), "$source: mismos grupos" );
		check_p( array_keys( $p->sections ) === array_keys( $o->sections ), "$source: mismas secciones: " . implode( ',', array_keys( $p->sections ) ) . ' / ' . implode( ',', array_keys( $o->sections ) ) );
		check_p( $p->removed === $o->removed, "$source: mismos controles quitados" );
		foreach ( $p->controls as $name => $control ) {
			if ( 'deprecated_notice' === $name ) {
				continue;
			}
			$a = $shape( $to_own( $control ) );
			$b = $shape( $o->controls[ $name ] );
			check_p( $a === $b, "$source: el control $name difiere de PRO:\n" . json_encode( $a ) . "\n" . json_encode( $b ) );
		}
		foreach ( $p->groups as $name => $group ) {
			$a = $shape( $to_own( $group ) );
			$b = $shape( $o->groups[ $name ] );
			check_p( $a === $b, "$source: el grupo $name difiere de PRO:\n" . json_encode( $a ) . "\n" . json_encode( $b ) );
		}
		foreach ( $p->sections as $name => $section ) {
			check_p( $shape( $to_own( $section ) ) === $shape( $o->sections[ $name ] ), "$source: la sección $name difiere de PRO" );
		}
		// Con el marcado optimizado, la misma envoltura interna que PRO.
		foreach ( array( false, true ) as $optimized ) {
			$state->optimized = $optimized;
			check_p( $p->has_widget_inner_wrapper() === $o->has_widget_inner_wrapper(), "$source: envoltura interna como PRO (optimizado: " . (int) $optimized . ')' );
		}
		$state->optimized = false;
		$all_pro[ $source ] = array( 'controls' => array_merge( array_keys( $p->controls ), array_map( static function ( $n ) { return $n; }, array_keys( $p->groups ) ) ), 'groups' => array_keys( $p->groups ) );
		// Recursos: sólo propios.
		$styles = $o->get_style_depends();
		check_p( $styles && ! preg_grep( '/^(?!digitalisimo-woo-)/', $styles ), "$source: hojas propias (" . implode( ',', $styles ) . ')' );
		check_p( array() === $o->get_script_depends(), "$source: sin script" );
	}
	$pro_count = array_sum( array_map( static function ( $x ) { return count( $x['controls'] ); }, $all_pro ) );
	check_p( $pro_count > 250, "Se compararon los controles y grupos de los cuatro ($pro_count)." );

	// 2. Render con un WooCommerce de prueba: los mismos argumentos de consulta y el mismo HTML que PRO.
	$cases = array(
		'por defecto'       => array(),
		'oferta'            => array( 'query_post_type' => 'sale', 'rows' => 2, 'columns' => 3 ),
		'destacados'        => array( 'query_post_type' => 'featured', 'query_orderby' => 'price', 'query_order' => 'asc' ),
		'selección manual'  => array( 'query_post_type' => 'by_id', 'query_posts_ids' => array( '11', '12' ) ),
		'términos'          => array( 'query_include' => array( 'terms' ), 'query_include_term_ids' => array( '7' ), 'query_exclude' => array( 'current_post', 'manual_selection' ), 'query_exclude_ids' => array( '9' ) ),
		'paginación'        => array( 'paginate' => 'yes', 'allow_order' => 'yes', 'show_result_count' => '', 'automatically_align_buttons' => 'yes' ),
		'consulta actual'   => array( 'query_post_type' => 'current_query', 'paginate' => 'yes' ),
	);
	$GLOBALS['wp_query'] = new class() { public $posts = array( 11 ); public $found_posts = 1; public $max_num_pages = 1; public $query_vars = array( 'post_type' => 'product' ); public function get( $k, $d = '' ) { return 'posts_per_page' === $k ? 12 : $d; } };
	foreach ( array( 'woocommerce-products', 'wc-archive-products' ) as $source ) {
		foreach ( $cases as $label => $settings ) {
			$GLOBALS['wcp']['queries'] = array();
			$GLOBALS['wcp']['filters'] = array();
			$a  = $render( $make( $pairs[ $source ]['pro'], $settings ) );
			$qa = $GLOBALS['wcp']['queries'];
			$fa = array_keys( $GLOBALS['wcp']['filters'] );
			$GLOBALS['wcp']['queries'] = array();
			$GLOBALS['wcp']['filters'] = array();
			$b  = $render( $make( $pairs[ $source ]['own'], $settings ) );
			$qb = $GLOBALS['wcp']['queries'];
			$fb = array_keys( $GLOBALS['wcp']['filters'] );
			check_p( $qa === $qb, "$source ($label): los mismos argumentos de consulta que PRO:\n" . json_encode( $qa ) . "\n" . json_encode( $qb ) );
			check_p( $fa === $fb, "$source ($label): los mismos ganchos que PRO (" . implode( ',', $fa ) . ' / ' . implode( ',', $fb ) . ')' );
			check_p( $a === $b && str_contains( $b, '<ul class="products elementor-grid columns-4">' ), "$source ($label): el mismo HTML que PRO:\n$a\n$b" );
		}
	}
	// Sin resultados (archivo): el mensaje propio con su clase.
	check_p( str_contains( file_get_contents( $root . 'modules/digitalisimo-woo/store/class-products.php' ), "echo '<div class=\"digi-products__nothing-found\">'" ), 'El mensaje sin resultados lleva la clase propia.' );
	// Clásico: la consulta de PRO y el mismo bucle.
	$GLOBALS['wcp']['filters'] = array();
	$classic = $make( Store\Products_Classic::class, array( 'posts_per_page' => '6', 'filter_by' => 'sale', 'orderby' => 'price', 'order' => 'asc', 'classic_columns' => '3' ) );
	check_p( '4' === $make( Store\Products_Classic::class )->settings['classic_columns'] && isset( $classic->controls['classic_columns'] ), 'Clásico: control de columnas de la presentación (4 por defecto).' );
	$pro_classic = $make( \ElementorPro\Modules\Woocommerce\Widgets\Products_Deprecated::class, $classic->settings );
	$classic->query_posts();
	$pro_classic->query_posts();
	check_p( $classic->get_query()->args === $pro_classic->get_query()->args && isset( $classic->get_query()->args['post__in'] ), 'Clásico: la misma consulta que PRO (' . json_encode( $classic->get_query()->args ) . ')' );
	$featured = $make( Store\Products_Classic::class, array( 'filter_by' => 'featured' ) );
	$featured->query_posts();
	check_p( 31 === ( $featured->get_query()->args['tax_query'][0]['terms'] ?? 0 ), 'Clásico: destacados por la visibilidad de WooCommerce.' );

	// 3. Mapas: cobertura con los heredados, destino propio, listos y ocultos, conversión real.
	$maps = array();
	foreach ( $pairs as $source => $pair ) {
		$map = Maps::map( $source, true );
		check_p( null !== $map && $pair['name'] === $map['target'], "$source: mapa hacia {$pair['name']}" );
		check_p( 'ready' === ( $map['status'] ?? '' ) && 'hide' === ( $map['panel'] ?? '' ), "$source: mapa listo y oculto del panel" );
		$declared = array_merge( (array) ( $map['keep'] ?? array() ), array_keys( (array) ( $map['rename'] ?? array() ) ), array_keys( (array) ( $map['groups'] ?? array() ) ), array_keys( (array) ( $map['drop'] ?? array() ) ), array_keys( (array) ( $map['approx'] ?? array() ) ), (array) ( $map['ui'] ?? array() ) );
		$missing  = array_diff( $all_pro[ $source ]['controls'], $declared, array( 'deprecated_notice' ) );
		check_p( ! $missing, "$source: el mapa no declara " . implode( ', ', $missing ) );
		$maps[ $source ] = $map;
	}
	// Conversión de ajustes reales: dispositivos, globales, dinámicos, grupos, CSS personalizado y partes propias.
	$own_keys = static function ( $class ) use ( $make ) {
		$w    = $make( $class );
		$keys = array_keys( $w->controls );
		return array( $keys, array_keys( $w->groups ) );
	};
	$devices = static function ( $key ) { return preg_replace( '/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', '', $key ); };
	$sample = array(
		'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'rows' => '2', 'paginate' => 'yes', 'allow_order' => 'yes', 'show_result_count' => 'yes',
		'query_post_type' => 'by_id', 'query_posts_ids' => array( '11' ), 'query_orderby' => 'price', 'query_order' => 'asc', 'query_exclude' => array( 'manual_selection' ), 'query_exclude_ids' => array( '9' ),
		'column_gap' => array( 'size' => 12, 'unit' => 'px' ), 'column_gap_tablet' => array( 'size' => 8, 'unit' => 'px' ), 'row_gap_mobile' => array( 'size' => 20, 'unit' => 'px' ), 'align' => 'center', 'align_mobile' => 'left',
		'image_border_border' => 'solid', 'image_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px' ), 'image_border_radius' => array( 'top' => '4', 'unit' => 'px' ),
		'title_color' => '#123456', 'title_typography_typography' => 'custom', 'title_typography_font_size' => array( 'size' => 18, 'unit' => 'px' ), 'title_typography_font_size_mobile' => array( 'size' => 14, 'unit' => 'px' ),
		'star_color' => '#ff0', 'price_color' => '#090', 'old_price_color' => '#999', 'button_text_color' => '#fff', 'button_background_color' => '#000', 'button_hover_background_color' => '#333', 'button_border_border' => 'solid', 'button_border_radius' => array( 'top' => '3', 'unit' => 'px' ),
		'automatically_align_buttons' => 'yes', 'box_shadow_box_shadow_type' => 'yes', 'box_bg_color' => '#fafafa', 'show_onsale_flash' => 'yes', 'onsale_text_color' => '#fff', 'onsale_horizontal_position' => 'right',
		'pagination_border_color' => '#ddd', 'products_title_show' => 'show', 'products_upsells_title_text' => 'Te puede gustar',
		'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'image', 'title', 'price' ), 'digitalisimo_wc_layout' => 'carousel',
		'_margin' => array( 'top' => '10' ), 'custom_css' => 'selector.elementor-wc-products ul.products{gap:0} selector.products-heading-show h2{color:red} .elementor-product-loop-item--align-center{}',
		'__globals__' => array( 'title_color' => 'globals/colors?id=primary', 'button_typography_typography' => 'globals/typography?id=accent' ),
		'__dynamic__' => array( 'products_upsells_title_text' => '[elementor-tag id="x" name="post-title" settings="%7B%7D"]' ),
	);
	foreach ( $pairs as $source => $pair ) {
		$settings = $sample;
		if ( 'wc-products' === $source ) {
			$settings = array_merge( array_intersect_key( $sample, array_flip( array( 'column_gap', 'title_color', 'title_typography_typography', 'custom_css', '__globals__', '_margin', 'button_border_border' ) ) ), array( 'posts_per_page' => '6', 'posts_post_type' => 'product', 'posts_posts_ids' => array( '11' ), 'filter_by' => 'sale', 'orderby' => 'price', 'order' => 'asc', 'exclude' => array( 'manual_selection' ), 'exclude_ids' => array( '9' ), 'avoid_duplicates' => 'yes', '_skin' => 'classic', 'classic_columns' => '3' ) );
		} elseif ( 'woocommerce-products' !== $source ) {
			unset( $settings['digitalisimo_wc_custom'], $settings['digitalisimo_wc_parts'], $settings['digitalisimo_wc_layout'], $settings['products_upsells_title_text'], $settings['__dynamic__'], $settings['columns'], $settings['columns_tablet'], $settings['columns_mobile'], $settings['rows'] );
			$settings['nothing_found_message'] = 'Sin productos.';
			$settings['nothing_found_color'] = '#c00';
		}
		$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => $source, 'settings' => $settings ) ) ) );
		$log  = array();
		$out  = Converter::tree( $tree, $log );
		$el   = $out[0]['elements'][0];
		check_p( $pair['name'] === $el['widgetType'] && 'w1' === $el['id'], "$source: «Migrar» lo convierte al propio con su id" );
		$codes = array_merge( ...array_map( static function ( $l ) { return $l['codes'] ?? array(); }, $log ) );
		check_p( ! array_filter( $codes, static function ( $c ) { return 'pro.unmapped' === $c['code']; } ), "$source: ninguna clave sin declarar: " . json_encode( $codes ) );
		list( $keys, $groups ) = $own_keys( $pair['own'] );
		foreach ( array_keys( $el['settings'] ) as $key ) {
			if ( in_array( $key, array( '__globals__', '__dynamic__', '_digitalisimo_pro', 'custom_css' ), true ) || 0 === strpos( $key, '_' ) || 0 === strpos( $key, 'digitalisimo_' ) ) {
				continue;
			}
			$base = $devices( $key );
			$ok   = in_array( $base, $keys, true );
			foreach ( $groups as $g ) {
				$ok = $ok || 0 === strpos( $key, $g . '_' );
			}
			check_p( $ok, "$source: la clave convertida $key no es un control del propio" );
		}
		foreach ( array_keys( $el['settings']['__globals__'] ?? array() ) as $key ) {
			check_p( isset( $el['settings'][ $key ] ) || in_array( $key, array( 'title_color', 'button_typography_typography' ), true ), "$source: global $key conservado" );
		}
		check_p( 'selector.digi-wc-products ul.products{gap:0} selector.digi-products--heading-show h2{color:red} .digi-products--align-center{}' === $el['settings']['custom_css'], "$source: CSS personalizado con las clases propias: " . $el['settings']['custom_css'] );
		if ( 'woocommerce-products' === $source ) {
			check_p( 'yes' === $el['settings']['digitalisimo_wc_custom'] && array( 'image', 'title', 'price' ) === $el['settings']['digitalisimo_wc_parts'] && 'carousel' === $el['settings']['digitalisimo_wc_layout'], 'woocommerce-products: las partes de Productos pasan al propio' );
			check_p( '[elementor-tag id="x" name="post-title" settings="%7B%7D"]' === $el['settings']['__dynamic__']['products_upsells_title_text'] && '2' === $el['settings']['columns_tablet'] && 'globals/colors?id=primary' === $el['settings']['__globals__']['title_color'], 'woocommerce-products: dinámicos, dispositivos y globales' );
			// Un widget sin cambios llega con los textos por defecto de PRO (los propios están en español).
			$bare = Converter::tree( array( array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => $source, 'settings' => array() ) ) );
			check_p( 'Related Products' === $bare[0]['settings']['products_related_products_title_text'] && 'You may also like...' === $bare[0]['settings']['products_upsells_title_text'] && 'You may be interested in...' === $bare[0]['settings']['products_cross_sells_title_text'], 'woocommerce-products: los títulos por defecto de PRO viajan al propio' );
		}
		if ( in_array( $source, array( 'wc-archive-products', 'woocommerce-archive-products' ), true ) ) {
			$bare = Converter::tree( array( array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => $source, 'settings' => array() ) ) );
			check_p( 'It seems we can’t find what you’re looking for.' === $bare[0]['settings']['nothing_found_message'], "$source: el mensaje por defecto de PRO viaja al propio" );
		}
		if ( 'wc-products' === $source ) {
			check_p( 'classic' === $el['settings']['_skin'] && '3' === $el['settings']['classic_columns'] && '6' === $el['settings']['posts_per_page'] && array( '11' ) === $el['settings']['posts_posts_ids'], 'wc-products: presentación, columnas y consulta `posts`' );
		}
		// Una segunda pasada no repite nada.
		$again = array();
		Converter::tree( $out, $again );
		check_p( ! $again, "$source: una segunda pasada no convierte nada" );
	}

	// 4. Hojas y archivos: topes, foco visible, sin paleta del núcleo, lista negra y proveedores.
	$store = $root . 'modules/digitalisimo-woo/store/';
	foreach ( array( 'products.css', 'archive-products.css' ) as $sheet ) {
		$css = (string) file_get_contents( $store . 'assets/' . $sheet );
		check_p( strlen( $css ) <= 4096 && ! str_contains( $css, '--e-global-' ) && ! preg_match( '/url\(/i', $css ), "$sheet: ≤ 4 KB, sin --e-global-* ni url()" );
		check_p( ! preg_match( '/animation|transition/', $css ), "$sheet: sin animaciones" );
	}
	check_p( preg_match( '/:focus-visible\{outline:2px solid [^;]+;outline-offset:2px\}/', (string) file_get_contents( $store . 'assets/products.css' ) ), 'products.css: foco visible de 2 px con separación de 2 px' );
	$mine = array( 'class-products-base.php', 'class-products.php', 'class-products-classic.php', 'class-archive-products.php', 'class-archive-products-classic.php', 'class-products-trait.php', 'class-skin-classic.php', 'class-base-products-renderer.php', 'class-products-renderer.php', 'class-current-query-renderer.php', 'class-products-fragments.php', 'class-on-import-trait.php', 'assets/products.css', 'assets/archive-products.css' );
	$maps_dir = $root . 'modules/digitalisimo-convert/maps/';
	$files = array_merge( array_map( static function ( $f ) use ( $store ) { return $store . $f; }, $mine ), array_map( static function ( $f ) use ( $maps_dir ) { return $maps_dir . $f . '.php'; }, array_keys( $pairs ) ) );
	$vendors = json_decode( (string) file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
	$hosts   = array_merge( $vendors['hosts'], $vendors['future'] ?? array() );
	foreach ( $files as $file ) {
		$src = (string) file_get_contents( $file );
		check_p( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $src ), basename( $file ) . ': nombra algo vetado por el empaquetado' );
		foreach ( $hosts as $host ) {
			check_p( ! preg_match( '/(?<![a-z0-9-])(?:[a-z0-9-]+\.)*' . preg_quote( $host, '/' ) . '(?![a-z0-9-])/i', $src ), basename( $file ) . ": nombra el dominio $host" );
		}
		if ( str_ends_with( $file, '.php' ) && str_contains( $file, '/store/' ) ) {
			check_p( ! preg_match( '/^\s*(require|include)[^;]*modules\/woocommerce\//m', $src ) && ! preg_match( '/extends\s+\\\\?ElementorPro\\\\/', $src ), basename( $file ) . ': no carga ni hereda una clase de PRO' );
			check_p( ! str_contains( $src, "'elementor-pro'" ), basename( $file ) . ': textos con el dominio pro-elements' );
		}
	}

	// 5. Partes de Productos, vista rápida y scripts del editor también para el propio.
	$parts = (string) file_get_contents( $root . 'modules/digitalisimo-widgets/class-products-parts.php' );
	check_p( str_contains( $parts, "'elementor/element/digitalisimo-woo-products/section_content/after_section_end'" ) && str_contains( $parts, 'instanceof \\Digitalisimo\\Elements\\Woo\\Store\\Products' ), 'Partes de Productos: controles y salida en el propio' );
	check_p( str_contains( (string) file_get_contents( $root . 'modules/digitalisimo-woo/quick-view/class-route.php' ), "'digitalisimo-woo-products' !== \$type" ), 'Vista rápida: acepta el propio' );
	foreach ( array( 'assets/js/carousel-engine.js', 'assets/js/table.js' ) as $js ) {
		check_p( str_contains( (string) file_get_contents( $root . $js ), "frontend/element_ready/digitalisimo-woo-products.default" ), "$js: se reinicia en el editor también con el propio" );
	}
	check_p( str_contains( (string) file_get_contents( $root . 'modules/digitalisimo-woo/class-module.php' ), "'Store\\\\Products_Fragments'" ), 'Woo\\Module arranca los fragmentos de los Productos propios' );

	echo "DIGITALÍSIMO Elements: Productos de tienda propios (4 widgets, $pro_count controles y grupos de PRO comparados, render, mapas y conversión) validados.\n";
}
