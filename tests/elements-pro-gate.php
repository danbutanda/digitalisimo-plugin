<?php
/**
 * Puerta de los paquetes de PRO (F-front, frente A, §3.1 y §6.1): cada razón con datos sintéticos, los negativos, la
 * decisión en `wp_footer` 19 sobre una cola de scripts con dependencias reales, la réplica de contadores y los
 * contratos estáticos (plugin.php sigue encolando los dos paquetes; el módulo no nombra el complemento retirado).
 *
 * Dobles: un sistema de ganchos con prioridades, opciones, metas, una cola de scripts que resuelve dependencias como
 * WordPress (`enqueued` también por dependencia) y las clases mínimas del constructor.
 */
namespace Elementor {
	class Widget_Base {
		protected $data;
		public function __construct( $data = array() ) { $this->data = $data; }
		public function get_name() { return $this->data['widgetType'] ?? ''; }
		public function get_data( $item = null ) { return null === $item ? $this->data : ( $this->data[ $item ] ?? null ); }
	}
	class Widget_Heading extends Widget_Base {}
	class Widget_Button extends Widget_Base {}
	class Widget_Html extends Widget_Base {}
	class Widget_Text_Editor extends Widget_Base {}
	class Widget_Tabs extends Widget_Base {}
	class Element_Section extends Widget_Base { public function get_name() { return 'section'; } }
	class Element_Column extends Widget_Base { public function get_name() { return 'column'; } }
	class Plugin { public static $instance; public $widgets_manager; public $elements_manager; public $editor; public $preview; public $frontend; }
}
namespace Elementor\Includes\Elements { class Container extends \Elementor\Widget_Base { public function get_name() { return 'container'; } } }
namespace Elementor\Modules\AtomicWidgets\Elements { class Atomic_Heading extends \Elementor\Widget_Base {} class Div_Block extends \Elementor\Widget_Base {} }
namespace ElementorPro\Modules\NavMenu\Widgets { class Nav_Menu extends \Elementor\Widget_Base {} }
namespace ElementorPro\Modules\Countdown\Widgets { class Countdown extends \Elementor\Widget_Base {} }
namespace ElementorPro\Modules\Pricing\Widgets { class Price_Table extends \Elementor\Widget_Base {} }
namespace ElementorPro\Modules\Library\Widgets { class Template extends \Elementor\Widget_Base {} }
namespace ElementorPro\Modules\GlobalWidget\Widgets {
	class Global_Widget extends \Elementor\Widget_Base {
		public $original;
		public function get_original_element_instance() { return $this->original; }
	}
}
namespace ElementorPro\Modules\Popup {
	class Document {
		public $id;
		public function __construct( $id ) { $this->id = $id; }
		public function get_post() { return (object) array( 'ID' => $this->id ); }
		public function get_main_id() { return $this->id; }
		public function get_name() { return 'popup'; }
	}
}
namespace Digitalisimo\Elements\Legacy { class Legacy_Navbar extends \ElementorPro\Modules\NavMenu\Widgets\Nav_Menu {} }
namespace Digitalisimo\Elements { class Own_Card extends \Elementor\Widget_Base {} class Own_Accordion extends \Elementor\Widget_Base {} }
namespace Acme\Addon { class Fancy extends \Elementor\Widget_Base {} }
namespace Digitalisimo\Elements\Front {
	// Doble de B (§7.2): la puerta sólo lee el valor aplicado.
	final class Transition { public static function applied(): array { return $GLOBALS['applied']; } }
}

namespace {
	use Digitalisimo\Elements\Front\Pro_Gate;

	define( 'ABSPATH', __DIR__ );
	$GLOBALS['post_types'] = array();
	$GLOBALS['statuses']   = array();
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$failures = array();
	$checks   = 0;
	function check_gate( $ok, $message ) { global $failures, $checks; $checks++; if ( ! $ok ) { $failures[] = $message; } }

	// --- Ganchos -----------------------------------------------------------------------------------------------
	$GLOBALS['hooks'] = array();
	$GLOBALS['did']   = array();
	function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $name, $callback, $priority = 10, $args = 1 ) { return add_filter( $name, $callback, $priority, $args ); }
	function apply_filters( $name, $value, ...$rest ) {
		if ( empty( $GLOBALS['hooks'][ $name ] ) ) { return $value; }
		$byPriority = $GLOBALS['hooks'][ $name ];
		ksort( $byPriority );
		foreach ( $byPriority as $callbacks ) {
			foreach ( $callbacks as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $rest ), 0, $entry[1] ) ); }
		}
		return $value;
	}
	function do_action( $name, ...$args ) {
		$GLOBALS['did'][ $name ] = ( $GLOBALS['did'][ $name ] ?? 0 ) + 1;
		if ( empty( $GLOBALS['hooks'][ $name ] ) ) { return; }
		$byPriority = $GLOBALS['hooks'][ $name ];
		ksort( $byPriority );
		foreach ( $byPriority as $callbacks ) {
			foreach ( $callbacks as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); }
		}
	}
	function did_action( $name ) { return $GLOBALS['did'][ $name ] ?? 0; }

	// --- Opciones, metas y contexto ------------------------------------------------------------------------------
	function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; $GLOBALS['autoload'][ $name ] = $autoload; return true; }
	function get_post_meta( $id, $key, $single = false ) { $GLOBALS['meta_reads'][ $id ] = ( $GLOBALS['meta_reads'][ $id ] ?? 0 ) + 1; return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
	function get_post_type( $id ) { return $GLOBALS['post_types'][ $id ] ?? ''; }
	function get_posts( $args ) {
		$ids = array();
		foreach ( $GLOBALS['post_types'] as $id => $type ) {
			if ( $type === $args['post_type'] && ( $GLOBALS['statuses'][ $id ] ?? '' ) === $args['post_status'] ) { $ids[] = $id; }
		}
		return $ids;
	}
	function update_meta_cache( $type, $ids ) { return true; }
	function is_admin() { return ! empty( $GLOBALS['world']['admin'] ); }
	function wp_doing_ajax() { return false; }
	function is_preview() { return ! empty( $GLOBALS['world']['wp_preview'] ); }
	function is_customize_preview() { return ! empty( $GLOBALS['world']['customizer'] ); }

	// --- Cola de scripts ---------------------------------------------------------------------------------------
	final class Fake_Scripts {
		public $registered = array();
		public $queue      = array();
		public function register( $handle, $deps = array() ) {
			if ( ! isset( $this->registered[ $handle ] ) ) { $this->registered[ $handle ] = (object) array( 'deps' => $deps, 'extra' => array() ); }
		}
		public function query( $handle, $list ) {
			if ( 'registered' === $list ) { return isset( $this->registered[ $handle ] ); }
			return in_array( $handle, $this->queue, true ) || $this->recurse( $this->queue, $handle, array() );
		}
		private function recurse( $queue, $handle, $seen ) {
			foreach ( $queue as $queued ) {
				if ( isset( $seen[ $queued ] ) || ! isset( $this->registered[ $queued ] ) ) { continue; }
				$seen[ $queued ] = true;
				$deps = $this->registered[ $queued ]->deps;
				if ( in_array( $handle, $deps, true ) || $this->recurse( $deps, $handle, $seen ) ) { return true; }
			}
			return false;
		}
		public function get_data( $handle, $key ) { return $this->registered[ $handle ]->extra[ $key ] ?? false; }
		public function add_data( $handle, $key, $value ) { $this->registered[ $handle ]->extra[ $key ] = $value; return true; }
		/** Lo que WordPress imprimiría: la cola en orden, cada dependencia antes que quien la pide. */
		public function printed() {
			$out  = array();
			$seen = array();
			$walk = function ( $handle ) use ( &$walk, &$out, &$seen ) {
				if ( isset( $seen[ $handle ] ) ) { return; }
				$seen[ $handle ] = true;
				foreach ( $this->registered[ $handle ]->deps ?? array() as $dep ) { $walk( $dep ); }
				$out[] = $handle;
			};
			foreach ( $this->queue as $handle ) { $walk( $handle ); }
			return $out;
		}
	}
	function wp_scripts() { return $GLOBALS['wp_scripts']; }
	function wp_register_script( $handle, $src = '', $deps = array() ) { wp_scripts()->register( $handle, $deps ); return true; }
	function wp_enqueue_script( $handle, $src = '', $deps = array() ) {
		if ( $src ) { wp_scripts()->register( $handle, $deps ); }
		if ( ! in_array( $handle, wp_scripts()->queue, true ) ) { wp_scripts()->queue[] = $handle; }
	}
	function wp_dequeue_script( $handle ) { wp_scripts()->queue = array_values( array_diff( wp_scripts()->queue, array( $handle ) ) ); }
	function wp_script_is( $handle, $list = 'enqueued' ) { return wp_scripts()->query( $handle, $list ); }
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['inline_calls'][] = array( $handle, $position );
		$current   = wp_scripts()->get_data( $handle, $position );
		$current   = is_array( $current ) ? $current : array();
		$current[] = $data;
		return wp_scripts()->add_data( $handle, $position, $current );
	}
	// Como WordPress: las traducciones añaden `wp-i18n` a las dependencias del script.
	function wp_set_script_translations( $handle ) {
		$deps = &wp_scripts()->registered[ $handle ]->deps;
		if ( ! in_array( 'wp-i18n', $deps, true ) ) { $deps[] = 'wp-i18n'; }
	}

	// --- Constructor ---------------------------------------------------------------------------------------------
	$GLOBALS['widget_classes'] = array(
		'heading'           => 'Elementor\\Widget_Heading',
		'button'            => 'Elementor\\Widget_Button',
		'html'              => 'Elementor\\Widget_Html',
		'text-editor'       => 'Elementor\\Widget_Text_Editor',
		'tabs'              => 'Elementor\\Widget_Tabs',
		'nav-menu'          => 'ElementorPro\\Modules\\NavMenu\\Widgets\\Nav_Menu',
		'countdown'         => 'ElementorPro\\Modules\\Countdown\\Widgets\\Countdown',
		'price-table'       => 'ElementorPro\\Modules\\Pricing\\Widgets\\Price_Table',
		'template'          => 'ElementorPro\\Modules\\Library\\Widgets\\Template',
		'global'            => 'ElementorPro\\Modules\\GlobalWidget\\Widgets\\Global_Widget',
		'legacy-navbar'     => 'Digitalisimo\\Elements\\Legacy\\Legacy_Navbar',
		'digitalisimo-card' => 'Digitalisimo\\Elements\\Own_Card',
		'digitalisimo-accordion' => 'Digitalisimo\\Elements\\Own_Accordion',
		'fancy-addon'       => 'Acme\\Addon\\Fancy',
		'e-heading'         => 'Elementor\\Modules\\AtomicWidgets\\Elements\\Atomic_Heading',
	);
	$GLOBALS['element_classes'] = array(
		'section'     => 'Elementor\\Element_Section',
		'column'      => 'Elementor\\Element_Column',
		'container'   => 'Elementor\\Includes\\Elements\\Container',
		'e-div-block' => 'Elementor\\Modules\\AtomicWidgets\\Elements\\Div_Block',
	);
	final class Fake_Widgets { public function get_widget_types( $name ) { $c = $GLOBALS['widget_classes'][ $name ] ?? ''; return $c ? new $c() : null; } }
	final class Fake_Elements { public function get_element_types( $name ) { $c = $GLOBALS['element_classes'][ $name ] ?? ''; return $c ? new $c() : null; } }
	final class Fake_Flag { public $method; public $key; public function __construct( $key ) { $this->key = $key; } public function is_edit_mode() { return ! empty( $GLOBALS['world'][ $this->key ] ); } public function is_preview_mode() { return ! empty( $GLOBALS['world'][ $this->key ] ); } }
	final class Fake_Frontend { public function has_elementor_in_page() { return ! empty( $GLOBALS['world']['has_content'] ); } }
	final class Fake_Document {
		public $id;
		public function __construct( $id ) { $this->id = $id; }
		public function get_post() { return (object) array( 'ID' => $this->id ); }
		public function get_main_id() { return $this->id; }
		public function get_name() { return 'wp-page'; }
	}
	$plugin                   = new \Elementor\Plugin();
	$plugin->widgets_manager  = new Fake_Widgets();
	$plugin->elements_manager = new Fake_Elements();
	$plugin->editor           = new Fake_Flag( 'editor' );
	$plugin->preview          = new Fake_Flag( 'preview' );
	$plugin->frontend         = new Fake_Frontend();
	\Elementor\Plugin::$instance = $plugin;

	function class_of_double( $el_type, $widget_type ) {
		if ( 'widget' === $el_type ) { return $GLOBALS['widget_classes'][ $widget_type ] ?? ''; }
		return $GLOBALS['element_classes'][ $el_type ] ?? '';
	}

	require $root . '/modules/digitalisimo-front/class-pro-gate.php';

	Pro_Gate::init();
	Pro_Gate::init();
	check_gate( 1 === count( $GLOBALS['hooks']['wp_footer'][19] ?? array() ), 'init() es idempotente y decide en wp_footer 19.' );
	check_gate( isset( $GLOBALS['hooks']['wp_print_footer_scripts'][0] ), 'El cierre corre en wp_print_footer_scripts 0, antes del defer de SEO (1).' );
	check_gate( isset( $GLOBALS['hooks']['elementor/frontend/builder_content_data'][ PHP_INT_MAX ] ) && isset( $GLOBALS['hooks']['elementor_pro/frontend/localize_settings'][ PHP_INT_MAX ] ), 'Los datos y hasPopUps se capturan al final de sus filtros.' );

	// Filtros de prueba: terceros libres y carga forzada, según el escenario.
	add_filter( 'digitalisimo_elements_pro_frontend_third_party', function ( $needs, $class, $type ) { return in_array( $class, $GLOBALS['world']['free'] ?? array(), true ) ? false : $needs; }, 10, 3 );
	add_filter( 'digitalisimo_elements_pro_frontend_needed', function ( $forced, $reasons ) { return $GLOBALS['world']['forced'] ?? $forced; }, 10, 2 );

	// --- Recorrido puro ------------------------------------------------------------------------------------------
	function w( $type, $settings = array(), $extra = array() ) { return array_merge( array( 'id' => substr( md5( $type . serialize( $settings ) ), 0, 7 ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() ), $extra ); }
	function c( $children, $settings = array(), $type = 'container' ) { return array( 'id' => 'c' . count( $children ), 'elType' => $type, 'settings' => $settings, 'elements' => $children ); }
	$GLOBALS['templates'] = array();
	$GLOBALS['loads']     = array();
	function load_double( $id ) { $GLOBALS['loads'][] = $id; return $GLOBALS['templates'][ $id ] ?? null; }
	function walk_of( $elements, $depth = 0 ) { $GLOBALS['loads'] = array(); return Pro_Gate::walk( $elements, 'class_of_double', 'load_double', $depth ); }
	$GLOBALS['world'] = array();

	check_gate( array() === walk_of( array() ), 'Vacío: sin razones.' );
	check_gate( array() === walk_of( array( c( array( w( 'heading', array( 'title' => 'Hola' ) ), w( 'button', array( 'link' => array( 'url' => '#contacto' ) ) ), w( 'tabs' ) ) ) ) ), 'Núcleo: sin razones.' );
	check_gate( array() === walk_of( array( c( array( w( 'digitalisimo-card' ), w( 'digitalisimo-accordion' ) ) ) ) ), 'Widgets propios: sin razones.' );
	check_gate( array() === walk_of( array( c( array( w( 'price-table' ) ) ) ) ), 'PRO sin controlador (tabla de precios): sin razones (D1).' );
	check_gate( array() === walk_of( array( c( array( c( array( w( 'heading' ) ), array(), 'column' ) ), array(), 'section' ) ) ), 'Sección y columna: sin razones.' );
	check_gate( array() === walk_of( array( array( 'id' => 'v4', 'elType' => 'e-div-block', 'settings' => array(), 'elements' => array( w( 'e-heading' ) ) ) ) ), 'Atómicos (v4): sin razones.' );
	check_gate( array() === walk_of( array( w( 'unregistered-widget' ) ) ), 'Un tipo sin registrar no se pinta: sin razones.' );
	check_gate( array() === walk_of( array( 'no-es-un-elemento', 7, null ) ), 'Entradas que no son elementos se ignoran.' );
	check_gate( array( 'class:countdown' ) === walk_of( array( c( array( w( 'heading' ), w( 'countdown' ) ) ) ) ), 'class: un widget con controlador.' );
	check_gate( array( 'class:legacy-navbar' ) === walk_of( array( w( 'legacy-navbar' ) ) ), 'class: un adaptador que extiende el Menú de PRO se ve por su clase, aunque su nombre no esté en HANDLED.' );
	check_gate( array( 'setting:sticky' ) === walk_of( array( c( array( w( 'heading' ) ), array( 'sticky' => 'top' ) ) ) ), 'setting:sticky en un contenedor.' );
	check_gate( array( 'setting:sticky' ) === walk_of( array( w( 'digitalisimo-card', array( 'sticky' => 'bottom' ) ) ) ), 'setting:sticky en un widget propio.' );
	check_gate( array() === walk_of( array( c( array(), array( 'sticky' => '' ) ) ) ), 'sticky vacío no cuenta.' );
	foreach ( Pro_Gate::MOTION_KEYS as $key ) {
		check_gate( array( 'setting:motion' ) === walk_of( array( w( 'digitalisimo-card', array( $key => 'yes' ) ) ) ), 'setting:motion con ' . $key . '.' );
	}
	check_gate( array() === walk_of( array( w( 'heading', array( 'motion_fx_motion_fx_scrolling' => '' ) ) ) ), 'Efectos de movimiento apagados no cuentan.' );

	$tag = function ( $name ) { return '[elementor-tag id="a1b2c3d" name="' . $name . '" settings="%7B%22popup%22%3A%2212%22%7D"]'; };
	foreach ( Pro_Gate::TAGS as $name ) {
		check_gate( array( 'tag:' . $name ) === walk_of( array( w( 'button', array( '__dynamic__' => array( 'link' => $tag( $name ) ) ) ) ) ), 'tag:' . $name . ' en __dynamic__.' );
	}
	check_gate( array( 'tag:popup' ) === walk_of( array( w( 'digitalisimo-accordion', array( 'tabs' => array( array( 'title' => 'Uno' ), array( '__dynamic__' => array( 'link' => $tag( 'popup' ) ) ) ) ) ) ) ), 'tag: dentro de un repetidor.' );
	check_gate( array() === walk_of( array( w( 'button', array( '__dynamic__' => array( 'link' => $tag( 'post-url' ) ) ) ) ) ), 'Otra etiqueta dinámica no cuenta.' );
	$hash = '#' . rawurlencode( 'elementor-action:action=popup:open&settings=' . base64_encode( '{"id":"12","toggle":false}' ) );
	check_gate( array( 'action:popup' ) === walk_of( array( w( 'button', array( 'link' => array( 'url' => $hash ) ) ) ) ), 'action: hash codificado en link.url.' );
	check_gate( array( 'action:off_canvas' ) === walk_of( array( w( 'button', array( 'link' => array( 'url' => '#elementor-action:action=off_canvas:toggle&settings=e30=' ) ) ) ) ), 'action: hash sin codificar.' );
	check_gate( array( 'action:reload-page' ) === walk_of( array( w( 'text-editor', array( 'editor' => '<a href="#ELEMENTOR-ACTION%3aACTION%3dRELOAD-PAGE">x</a>' ) ) ) ), 'action: sin distinguir mayúsculas.' );
	check_gate( array() === walk_of( array( w( 'button', array( 'link' => array( 'url' => '#' . rawurlencode( 'elementor-action:action=lightbox&settings=e30=' ) ) ) ) ) ), 'lightbox es del núcleo: no cuenta.' );
	check_gate( array( 'code:global' ) === walk_of( array( w( 'html', array( 'html' => '<script>elementorProFrontend.modules.popup.showPopup({id:12});</script>' ) ) ) ), 'code:global con elementorProFrontend.' );
	check_gate( array( 'code:global' ) === walk_of( array( w( 'html', array( 'html' => '<script>console.log(ElementorProFrontendConfig.ajaxurl)</script>' ) ) ) ), 'code:global con ElementorProFrontendConfig.' );

	// Anidadas.
	$GLOBALS['templates'] = array(
		10 => array( c( array( w( 'countdown' ) ) ) ),
		11 => array( c( array( w( 'heading' ) ) ) ),
		12 => json_encode( array( c( array( w( 'nav-menu' ) ) ) ) ),
		13 => '{"no-es":',
		14 => json_encode( array( w( 'text-editor', array( 'editor' => '[elementor-template id="10"]' ) ) ) ),
		20 => array( w( 'template', array( 'template_id' => '21' ) ) ),
		21 => array( w( 'template', array( 'template_id' => '20' ) ) ),
		30 => array( w( 'template', array( 'template_id' => '31' ) ) ),
		31 => array( w( 'template', array( 'template_id' => '32' ) ) ),
		32 => array( w( 'template', array( 'template_id' => '33' ) ) ),
		33 => array( w( 'template', array( 'template_id' => '34' ) ) ),
		34 => array( w( 'countdown' ) ),
		40 => array( w( 'template', array( 'template_id' => '41' ) ) ),
		41 => array( w( 'template', array( 'template_id' => '40' ) ), w( 'countdown' ) ),
		50 => array( w( 'countdown' ) ),
	);
	check_gate( array( 'nested:10' ) === walk_of( array( w( 'template', array( 'template_id' => '10' ) ) ) ), 'nested: template_id.' );
	check_gate( array() === walk_of( array( w( 'template', array( 'template_id' => 11 ) ) ) ), 'nested: una plantilla sin razones no cuenta.' );
	check_gate( array( 'nested:10' ) === walk_of( array( w( 'digitalisimo-accordion', array( 'tabs' => array( array( 'source' => 'anywhere', 'anywhere_id' => '10' ) ) ) ) ) ), 'nested: anywhere_id en un repetidor.' );
	check_gate( array( 'nested:10' ) === walk_of( array( w( 'digitalisimo-card', array( 'slides' => array( array( 'digitalisimo_template_id' => 10 ) ) ) ) ) ), 'nested: digitalisimo_template_id en un repetidor.' );
	check_gate( array( 'nested:10' ) === walk_of( array( w( 'digitalisimo-card', array( 'template_id_a' => '10' ) ) ) ), 'nested: clave con sufijo.' );
	check_gate( array( 'nested:10' ) === walk_of( array( w( 'global', array(), array( 'templateID' => 10 ) ) ) ), 'nested: templateID del widget global.' );
	foreach ( array( '[elementor-template id="10"]', "[elementor-template id='10']", '[elementor-template id=10]', '[elementor-template id=&quot;10&quot;]' ) as $shortcode ) {
		check_gate( array( 'nested:10' ) === walk_of( array( w( 'tabs', array( 'tabs' => array( array( 'tab_content' => '<p>' . $shortcode . '</p>' ) ) ) ) ) ), 'nested: shortcode ' . $shortcode . '.' );
	}
	check_gate( array( 'nested:12' ) === walk_of( array( w( 'template', array( 'template_id' => '12' ) ) ) ), 'nested: datos guardados como cadena JSON.' );
	check_gate( array( 'nested:14' ) === walk_of( array( w( 'template', array( 'template_id' => '14' ) ) ) ), 'nested: shortcode con comillas escapadas (\\") dentro del JSON guardado.' );
	check_gate( array( 'doubt:json:13' ) === walk_of( array( w( 'template', array( 'template_id' => '13' ) ) ) ), 'doubt: JSON inválido.' );
	check_gate( array() === walk_of( array( w( 'template', array( 'template_id' => '20' ) ) ) ), 'nested: un ciclo sin razones termina sin razones.' );
	check_gate( array( 'nested:40' ) === walk_of( array( w( 'template', array( 'template_id' => '40' ) ) ) ), 'nested: un ciclo con razón la encuentra.' );
	check_gate( array( 'nested:32' ) === walk_of( array( w( 'template', array( 'template_id' => '32' ) ) ) ), 'nested: una plantilla a profundidad 3 todavía se recorre.' );
	check_gate( array( 'doubt:depth:34' ) === walk_of( array( w( 'template', array( 'template_id' => '31' ) ) ) ), 'doubt: profundidad mayor que 3.' );
	check_gate( array( 'doubt:depth:33' ) === walk_of( array( w( 'template', array( 'template_id' => '30' ) ) ) ), 'doubt: la cadena se corta en la primera plantilla demasiado profunda.' );
	check_gate( array( 'doubt:depth:34' ) === walk_of( array( w( 'template', array( 'template_id' => '34' ) ) ), 3 ), 'doubt: la profundidad cuenta desde la del árbol.' );
	walk_of( array( w( 'template', array( 'template_id' => '11' ) ), w( 'template', array( 'template_id' => '11' ) ), w( 'tabs', array( 'tabs' => array( array( 'tab_content' => '[elementor-template id="11"]' ) ) ) ) ) );
	check_gate( array( 11 ) === $GLOBALS['loads'], 'nested: cada plantilla se lee una sola vez (memoria por id).' );
	for ( $i = 100; $i < 160; $i++ ) { $GLOBALS['templates'][ $i ] = array( w( 'heading' ) ); }
	$many = array();
	for ( $i = 100; $i < 160; $i++ ) { $many[] = w( 'template', array( 'template_id' => (string) $i ) ); }
	check_gate( array( 'doubt:limit' ) === walk_of( $many ), 'doubt: más de 50 documentos anidados.' );
	check_gate( 50 === count( $GLOBALS['loads'] ), 'El límite detiene la lectura en 50 documentos.' );
	check_gate( array( 'class:countdown' ) === walk_of( array( w( 'template', array( 'template_id' => '50' ) ), w( 'countdown' ) ) ) && array() === $GLOBALS['loads'], 'La estructura del árbol se recorre antes que sus plantillas.' );
	check_gate( array( 'third-party:Acme\\Addon\\Fancy' ) === walk_of( array( w( 'fancy-addon' ) ) ), 'third-party: clase fuera de los tres espacios de nombres.' );
	$GLOBALS['world']['free'] = array( 'Acme\\Addon\\Fancy' );
	check_gate( array() === walk_of( array( w( 'fancy-addon' ) ) ), 'third-party: el filtro lo declara libre.' );
	$GLOBALS['world']['free'] = array();
	$throws = function () { throw new \RuntimeException( 'roto' ); };
	check_gate( array( 'doubt:exception' ) === Pro_Gate::walk( array( w( 'heading' ) ), $throws, 'load_double' ), 'doubt: una excepción en el recorrido.' );
	check_gate( array( 'doubt:exception' ) === Pro_Gate::walk( array( w( 'template', array( 'template_id' => '10' ) ) ), 'class_of_double', $throws ), 'doubt: una excepción al leer una plantilla.' );
	check_gate( array( 'class:countdown' ) === walk_of( array( w( 'countdown', array( 'html' => 'elementorProFrontend' ) ) ) ), 'El recorrido se detiene en la primera razón y devuelve una sola.' );

	// --- Petición completa -------------------------------------------------------------------------------------
	$GLOBALS['scenario'] = array();
	add_action( 'wp_footer', function () { foreach ( $GLOBALS['world']['popups'] ?? array() as $popup ) { render_doc( $popup['doc'], $popup['data'], ! empty( $popup['excerpt'] ) ); } }, 10 );
	add_action( 'wp_footer', 'core_footer', 10 );
	add_action( 'wp_footer', function () { if ( isset( $GLOBALS['world']['at19'] ) ) { call_user_func( $GLOBALS['world']['at19'] ); } }, 19 );
	add_action( 'wp_footer', function () { do_action( 'wp_print_footer_scripts' ); }, 20 );
	add_action( 'wp_print_footer_scripts', function () { $GLOBALS['printed'] = wp_scripts()->printed(); }, 10 );
	add_action( 'wp_footer', function () { if ( isset( $GLOBALS['world']['at21'] ) ) { call_user_func( $GLOBALS['world']['at21'] ); } }, 21 );

	/** `Frontend::wp_footer()` y `enqueue_frontend_scripts()` de plugin.php, con su orden. */
	function core_footer() {
		if ( empty( $GLOBALS['world']['has_content'] ) ) { return; }
		if ( ! empty( $GLOBALS['world']['manual_pro'] ) ) {
			wp_enqueue_script( 'elementor-pro-frontend', 'frontend.min.js', array( 'elementor-frontend-modules' ) );
			wp_enqueue_script( 'pro-elements-handlers' );
			return;
		}
		wp_enqueue_script( 'elementor-pro-frontend', 'frontend.min.js', array( 'elementor-frontend-modules' ) );
		wp_set_script_translations( 'elementor-pro-frontend' );
		wp_enqueue_script( 'pro-elements-handlers' );
		$settings = apply_filters( 'elementor_pro/frontend/localize_settings', array( 'ajaxurl' => '/wp-admin/admin-ajax.php', 'popup' => array( 'hasPopUps' => ! empty( $GLOBALS['world']['has_popups'] ) ) ) );
		wp_add_inline_script( 'elementor-pro-frontend', 'var ElementorProFrontendConfig = ' . json_encode( $settings ) . ';', 'before' );
		if ( ! empty( $GLOBALS['world']['addon_script'] ) ) { wp_enqueue_script( 'acme-addon', 'acme.js', array( $GLOBALS['world']['addon_script'] ) ); }
	}

	/** `get_builder_content()`: filtro de datos, acción previa, elementos clásicos que traen elementor-frontend, acción final. */
	function render_doc( $doc, $data, $excerpt = false ) {
		$data = apply_filters( 'elementor/frontend/builder_content_data', $data, $doc->id );
		do_action( 'elementor/frontend/before_get_builder_content', $doc, $excerpt );
		if ( empty( $data ) ) { return; }
		$classic = false;
		array_walk_recursive( $data, function ( $value, $key ) use ( &$classic ) { if ( 'elType' === $key && in_array( $value, array( 'widget', 'section', 'container' ), true ) ) { $classic = true; } } );
		if ( $classic && ! empty( $GLOBALS['world']['classic_scripts'] ) ) { wp_enqueue_script( 'elementor-frontend' ); }
		foreach ( $GLOBALS['world']['render'][ $doc->id ] ?? array() as $element ) { do_action( 'elementor/frontend/before_render', $element ); }
		if ( ! $excerpt ) { $GLOBALS['world']['has_content'] = true; }
		do_action( 'elementor/frontend/get_builder_content', $doc, $excerpt, false );
	}

	/**
	 * Una petición: documentos impresos en el contenido, pie con popups (10), puerta (19) e impresión (20).
	 *
	 * @param array $docs  id => datos.
	 * @param array $world Contexto del escenario.
	 */
	function request( array $docs, array $world = array() ) {
		Pro_Gate::reset();
		$GLOBALS['world']        = array_merge( array( 'classic_scripts' => true, 'free' => array() ), $world );
		$GLOBALS['applied']      = $world['applied'] ?? array( 'pro_conditional' => true, 'hash' => 'x' );
		$GLOBALS['options']      = $world['options'] ?? array( Pro_Gate::SNIPPETS_OPTION => '0' );
		$GLOBALS['meta']         = $world['meta'] ?? array();
		$GLOBALS['meta_reads']   = array();
		$GLOBALS['inline_calls'] = array();
		$GLOBALS['printed']      = array();
		$GLOBALS['did']          = array();
		$GLOBALS['wp_scripts']   = new Fake_Scripts();
		foreach ( array( 'jquery-core' => array(), 'jquery-migrate' => array(), 'jquery' => array( 'jquery-core', 'jquery-migrate' ), 'elementor-frontend-modules' => array( 'jquery' ), 'elementor-frontend' => array( 'elementor-frontend-modules' ), 'wp-hooks' => array(), 'wp-i18n' => array( 'wp-hooks' ), 'pro-elements-handlers' => array( 'elementor-frontend' ) ) as $handle => $deps ) { wp_register_script( $handle, '', $deps ); }
		foreach ( $docs as $id => $data ) {
			if ( ! isset( $GLOBALS['meta'][ $id ]['_elementor_data'] ) && is_array( $data ) ) { $GLOBALS['meta'][ $id ]['_elementor_data'] = json_encode( $data ); }
		}
		do_action( 'wp' );
		foreach ( $docs as $id => $data ) { render_doc( new Fake_Document( $id ), $data, in_array( $id, $world['excerpts'] ?? array(), true ) ); }
		if ( isset( $world['before_footer'] ) ) { call_user_func( $world['before_footer'] ); }
		do_action( 'wp_footer' );
		return array(
			'reasons' => Pro_Gate::reasons(),
			'printed' => $GLOBALS['printed'],
			'before'  => wp_scripts()->get_data( 'elementor-frontend', 'before' ),
		);
	}
	function has_pro( $result ) { return in_array( 'elementor-pro-frontend', $result['printed'], true ) && in_array( 'pro-elements-handlers', $result['printed'], true ); }
	function no_pro( $result ) { return ! in_array( 'elementor-pro-frontend', $result['printed'], true ) && ! in_array( 'pro-elements-handlers', $result['printed'], true ); }
	function has_reason( $result, $prefix ) { foreach ( $result['reasons'] as $reason ) { if ( 0 === strpos( $reason, $prefix ) ) { return true; } } return false; }
	$counter = trim( file_get_contents( $root . '/modules/digitalisimo-front/assets/popup-counter.js' ) );

	$page = array( 7 => array( c( array( w( 'heading' ), w( 'button' ) ) ) ) );
	$r    = request( $page );
	check_gate( array() === $r['reasons'] && no_pro( $r ), 'Sin razones, los dos paquetes se retiran en wp_footer 19.' );
	check_gate( in_array( 'elementor-frontend', $r['printed'], true ) && in_array( 'jquery-core', $r['printed'], true ), 'elementor-frontend y jQuery siguen imprimiéndose.' );
	check_gate( ! in_array( 'wp-i18n', $r['printed'], true ) && ! in_array( 'wp-hooks', $r['printed'], true ), 'wp-i18n y wp-hooks sólo llegaban por elementor-pro-frontend: tampoco se imprimen.' );
	check_gate( false === $r['before'] || array() === $r['before'], 'Sin popups no se añade la réplica de contadores.' );
	check_gate( ! Pro_Gate::needed(), 'needed() es falso sin razones.' );

	$r = request( $page, array( 'has_popups' => true ) );
	check_gate( no_pro( $r ) && is_array( $r['before'] ) && array( $counter ) === $r['before'], 'Con hasPopUps capturado, la réplica va en posición before de elementor-frontend.' );
	check_gate( array( array( 'elementor-pro-frontend', 'before' ), array( 'elementor-frontend', 'before' ) ) === $GLOBALS['inline_calls'], 'La réplica se añade siempre en before, nunca en after.' );

	$r = request( array( 7 => array( c( array( w( 'heading' ), w( 'countdown' ) ) ) ) ), array( 'has_popups' => true ) );
	check_gate( array( 'class:countdown' ) === $r['reasons'] && has_pro( $r ) && Pro_Gate::needed(), 'Con una razón no se toca nada.' );
	$order = array_flip( $r['printed'] );
	check_gate( isset( $order['wp-i18n'], $order['wp-hooks'] ) && $order['wp-i18n'] < $order['elementor-pro-frontend'] && $order['elementor-pro-frontend'] < $order['pro-elements-handlers'], 'Cierre de dependencias: wp-i18n llega por elementor-pro-frontend, que se imprime antes que los controladores.' );
	check_gate( false === $r['before'], 'Con PRO no se añade la réplica.' );

	$r = request( $page, array( 'applied' => array() ) );
	check_gate( array( 'always:pro_conditional' ) === $r['reasons'] && has_pro( $r ), 'Sin transición aplicada, la puerta no actúa.' );
	$r = request( $page, array( 'applied' => array( 'pro_conditional' => false, 'hash' => 'x' ) ) );
	check_gate( array( 'always:pro_conditional' ) === $r['reasons'] && has_pro( $r ), 'Con pro_conditional apagado, la puerta no actúa.' );
	foreach ( array( 'editor' => 'always:editor', 'preview' => 'always:preview', 'wp_preview' => 'always:wp-preview', 'customizer' => 'always:customizer' ) as $flag => $expected ) {
		$r = request( $page, array( $flag => true ) );
		check_gate( array( $expected ) === $r['reasons'] && has_pro( $r ), 'Contexto ' . $expected . '.' );
	}
	foreach ( array( 'render_mode' => 'always:render_mode', 'theme_template_id' => 'always:theme_template_id' ) as $param => $expected ) {
		$_GET = array( $param => '12' );
		$r    = request( $page );
		check_gate( array( $expected ) === $r['reasons'] && has_pro( $r ), 'Contexto ' . $expected . '.' );
	}
	$_GET = array();
	$r = request( $page, array( 'forced' => true ) );
	check_gate( array( 'always:filter' ) === $r['reasons'] && has_pro( $r ), 'El filtro …_needed en true fuerza la carga.' );
	$r = request( $page, array( 'forced' => false ) );
	check_gate( no_pro( $r ), 'El filtro …_needed en false no cambia una decisión sin razones.' );
	$r = request( array( 7 => array( w( 'countdown' ) ) ), array( 'forced' => false ) );
	check_gate( has_pro( $r ), 'El filtro …_needed en false no retira paquetes que una razón pide (falla abierto).' );

	// Popups por condiciones, impresos en wp_footer 10.
	$r = request( $page, array( 'popups' => array( array( 'doc' => new \ElementorPro\Modules\Popup\Document( 90 ), 'data' => array( w( 'heading' ) ) ) ) ) );
	check_gate( array( 'doc:90:popup' ) === $r['reasons'] && has_pro( $r ), 'doc:<id>:popup con un popup impreso en el pie.' );
	$r = request( array(), array( 'popups' => array( array( 'doc' => new \ElementorPro\Modules\Popup\Document( 90 ), 'data' => array( w( 'heading' ) ) ) ) ) );
	check_gate( array( 'doc:90:popup' ) === $r['reasons'] && has_pro( $r ), 'Página sin contenido del constructor con popup por condición.' );
	$r = request( $page, array( 'popups' => array( array( 'doc' => new \ElementorPro\Modules\Popup\Document( 90 ), 'data' => array( w( 'heading' ) ), 'excerpt' => true ) ) ) );
	check_gate( array() === $r['reasons'], 'Un popup en un extracto no cuenta.' );

	// Extractos.
	$r = request( array( 7 => array( w( 'heading' ) ), 8 => array( w( 'countdown' ) ) ), array( 'excerpts' => array( 8 ) ) );
	check_gate( array() === $r['reasons'] && no_pro( $r ), 'Lo que sólo se pinta en un extracto no cuenta.' );

	// Cadenas en el JSON guardado: el contenido filtrado no las tiene, la cadena guardada sí.
	$r = request( array( 7 => array( w( 'heading' ) ) ), array( 'meta' => array( 7 => array( '_elementor_data' => json_encode( array( w( 'button', array( '__dynamic__' => array( 'link' => $tag( 'popup' ) ) ) ) ) ) ) ) ) );
	check_gate( array( 'tag:popup' ) === $r['reasons'] && has_pro( $r ), 'tag: se busca en la cadena JSON guardada del documento impreso.' );
	// Caché de elementos: la plantilla anidada no se vuelve a imprimir, pero su id está en los datos.
	$r = request( array( 7 => array( w( 'digitalisimo-accordion', array( 'tabs' => array( array( 'template_id' => '60' ) ) ), array( '_element_cache' => 'no' ) ) ) ), array( 'meta' => array( 60 => array( '_elementor_data' => json_encode( array( w( 'countdown' ) ) ) ) ) ) );
	check_gate( array( 'nested:60' ) === $r['reasons'] && has_pro( $r ), 'nested: plantilla de un acordeón propio servido desde la caché.' );
	$r = request( array( 7 => array( w( 'tabs', array( 'tabs' => array( array( 'tab_content' => "[elementor-template id='61']" ) ) ) ) ) ), array( 'meta' => array( 61 => array( '_elementor_data' => json_encode( array( w( 'nav-menu' ) ) ) ) ) ) );
	check_gate( array( 'nested:61' ) === $r['reasons'], 'nested: shortcode en las pestañas del núcleo, leído de la cadena guardada.' );
	$r = request( array( 7 => array( w( 'global', array(), array( 'templateID' => 62 ) ) ) ), array( 'meta' => array( 62 => array( '_elementor_data' => json_encode( array( w( 'countdown' ) ) ) ) ) ) );
	check_gate( array( 'nested:62' ) === $r['reasons'] && has_pro( $r ), 'Widget global: su plantilla se recorre.' );
	$r = request( array( 7 => array( w( 'heading' ) ), 70 => array( w( 'heading' ) ) ), array( 'before_footer' => function () { render_doc( new Fake_Document( 70 ), array( w( 'heading' ) ) ); } ) );
	check_gate( 1 === $GLOBALS['meta_reads'][70], 'Cada documento impreso se recorre una vez por petición.' );

	// Red de seguridad en before_render.
	$global           = new \ElementorPro\Modules\GlobalWidget\Widgets\Global_Widget( array( 'widgetType' => 'global' ) );
	$global->original = new \ElementorPro\Modules\Countdown\Widgets\Countdown( array( 'widgetType' => 'countdown', 'settings' => array() ) );
	$r = request( $page, array( 'render' => array( 7 => array( $global ) ) ) );
	check_gate( array( 'render:countdown' ) === $r['reasons'] && has_pro( $r ), 'render: el widget global se juzga por su instancia original.' );
	$r = request( $page, array( 'render' => array( 7 => array( new \Elementor\Widget_Heading( array( 'widgetType' => 'heading', 'settings' => array( 'sticky' => 'top' ) ) ) ) ) ) );
	check_gate( array( 'render:heading' ) === $r['reasons'], 'render: ajustes de sticky en un elemento pintado.' );
	$r = request( array( 7 => array( w( 'heading' ) ), 8 => array( w( 'heading' ) ) ), array( 'excerpts' => array( 8 ), 'render' => array( 8 => array( $global ) ) ) );
	check_gate( array() === $r['reasons'], 'render: lo pintado en un extracto no cuenta.' );
	$r = request( $page, array( 'render' => array( 7 => array( new \Elementor\Widget_Heading( array( 'widgetType' => 'heading', 'settings' => array() ) ) ) ) ) );
	check_gate( array() === $r['reasons'] && no_pro( $r ), 'render: un elemento del núcleo no cuenta.' );

	// Fragmentos de código (D12).
	$r = request( $page, array( 'options' => array( Pro_Gate::SNIPPETS_OPTION => '1' ) ) );
	check_gate( array( 'snippets' ) === $r['reasons'] && has_pro( $r ), 'snippets: la bandera fuerza la carga.' );
	$GLOBALS['post_types'] = array( 300 => 'elementor_snippet', 301 => 'elementor_snippet', 302 => 'page' );
	$GLOBALS['statuses']   = array( 300 => 'publish', 301 => 'draft', 302 => 'publish' );
	$r = request( $page, array( 'options' => array(), 'meta' => array( 300 => array( '_elementor_code' => '<script>jQuery(function(){});</script>' ), 301 => array( '_elementor_code' => 'elementorProFrontend' ), 302 => array( '_elementor_code' => 'elementorProFrontend' ) ) ) );
	check_gate( array() === $r['reasons'] && '0' === $GLOBALS['options'][ Pro_Gate::SNIPPETS_OPTION ] && true === $GLOBALS['autoload'][ Pro_Gate::SNIPPETS_OPTION ], 'snippets: sin la bandera se calcula una vez (sólo fragmentos publicados) y se guarda con carga automática.' );
	$GLOBALS['meta'][300]['_elementor_code'] = '<script>ElementorProFrontendConfig.urls</script>';
	Pro_Gate::on_meta( 1, 302, '_elementor_code' );
	check_gate( '0' === $GLOBALS['options'][ Pro_Gate::SNIPPETS_OPTION ], 'snippets: la meta de otro tipo de entrada no recalcula.' );
	Pro_Gate::on_meta( 1, 300, '_elementor_code' );
	check_gate( '1' === $GLOBALS['options'][ Pro_Gate::SNIPPETS_OPTION ], 'snippets: guardar el código de un fragmento recalcula la bandera.' );
	$GLOBALS['statuses'][300] = 'trash';
	Pro_Gate::on_status( 'trash', 'publish', (object) array( 'post_type' => 'elementor_snippet' ) );
	check_gate( '0' === $GLOBALS['options'][ Pro_Gate::SNIPPETS_OPTION ], 'snippets: despublicar un fragmento recalcula la bandera.' );
	check_gate( false === Pro_Gate::refresh_snippets(), 'refresh_snippets() devuelve la bandera.' );

	// API para otros módulos.
	$r = request( $page, array( 'before_footer' => function () { Pro_Gate::require( 'f7b<script>' ); } ) );
	check_gate( array( 'require:f7bscript' ) === $r['reasons'] && has_pro( $r ), 'require(): razón saneada que mantiene los paquetes.' );

	// Razones tardías.
	$r = request( $page, array( 'has_popups' => true, 'at19' => function () { Pro_Gate::require( 'tarde' ); } ) );
	check_gate( array( 'require:tarde' ) === $r['reasons'] && has_pro( $r ), 'Una razón entre 19 y 20 vuelve a encolar los dos paquetes.' );
	check_gate( array() === $r['before'], 'Al volver a encolarlos se retira la réplica de contadores.' );
	$order = array_flip( $r['printed'] );
	check_gate( $order['elementor-pro-frontend'] < $order['pro-elements-handlers'] && isset( $order['wp-i18n'] ), 'Al volver, conservan su orden y sus dependencias.' );
	$r = request( $page, array( 'at21' => function () { Pro_Gate::require( 'despues' ); } ) );
	check_gate( array( 'late:require:despues' ) === $r['reasons'] && no_pro( $r ), 'Después de imprimir los scripts del pie sólo queda anotada como late:.' );
	$r = request( $page, array( 'at21' => function () { render_doc( new Fake_Document( 71 ), array( w( 'countdown' ) ) ); } ) );
	check_gate( array( 'late:class:countdown' ) === $r['reasons'], 'Un documento pintado después de 20 queda anotado como tardío.' );

	// Dependencias ajenas: los dos paquetes viajan juntos.
	$r = request( $page, array( 'addon_script' => 'elementor-pro-frontend' ) );
	check_gate( array( 'doubt:dependency' ) === $r['reasons'] && has_pro( $r ), 'Un script ajeno que depende de elementor-pro-frontend trae los dos paquetes.' );
	$order = array_flip( $r['printed'] );
	check_gate( $order['elementor-pro-frontend'] < $order['pro-elements-handlers'], 'Con una dependencia ajena, los paquetes conservan su orden.' );
	$r = request( $page, array( 'addon_script' => 'pro-elements-handlers' ) );
	check_gate( array( 'doubt:dependency' ) === $r['reasons'] && has_pro( $r ), 'Un script ajeno que depende de pro-elements-handlers trae los dos paquetes.' );
	$r = request( $page, array( 'at19' => function () { wp_enqueue_script( 'acme-late', 'late.js', array( 'elementor-pro-frontend' ) ); } ) );
	check_gate( array( 'doubt:dependency' ) === $r['reasons'] && has_pro( $r ), 'Una dependencia ajena encolada después de 19 también los devuelve.' );
	$r = request( $page, array( 'manual_pro' => true ) );
	check_gate( array( 'doubt:config' ) === $r['reasons'] && has_pro( $r ), 'Si los paquetes no los encoló plugin.php (sin su filtro), no se retiran.' );

	// D19: página sólo v4, elementor-frontend llegaba por los paquetes.
	$r = request( array( 7 => array( array( 'id' => 'v4', 'elType' => 'e-div-block', 'settings' => array(), 'elements' => array( w( 'e-heading' ) ) ) ) ), array( 'classic_scripts' => false ) );
	check_gate( no_pro( $r ) && in_array( 'elementor-frontend', $r['printed'], true ), 'D19: en una página sólo v4 se conserva elementor-frontend.' );
	$r = request( array(), array() );
	check_gate( array() === $r['printed'], 'Sin contenido del constructor no se encola nada.' );

	// Excepciones en los ganchos: fallar abierto.
	$GLOBALS['element_classes']['e-broken'] = 'Missing\\Broken_Class_That_Does_Not_Exist';
	$r = request( array( 7 => array( array( 'id' => 'x', 'elType' => 'e-broken', 'settings' => array(), 'elements' => array() ) ) ) );
	check_gate( has_pro( $r ) && ( has_reason( $r, 'doubt:' ) || has_reason( $r, 'third-party:' ) ), 'Un tipo cuya clase no existe falla abierto.' );

	// --- Contratos estáticos -------------------------------------------------------------------------------------
	$plugin_php = file_get_contents( $root . '/plugin.php' );
	$enqueue    = substr( $plugin_php, strpos( $plugin_php, 'public function enqueue_frontend_scripts()' ), 1200 );
	check_gate( (bool) preg_match( "/wp_enqueue_script\(\s*'elementor-pro-frontend'/", $enqueue ) && false !== strpos( $enqueue, "wp_enqueue_script( 'pro-elements-handlers' );" ), 'plugin.php sigue encolando los dos paquetes (contrato de fallo abierto).' );
	check_gate( false !== strpos( $plugin_php, "add_action( 'elementor/frontend/before_enqueue_scripts', [ \$this, 'enqueue_frontend_scripts' ] );" ), 'plugin.php sigue encolándolos en before_enqueue_scripts.' );
	$gate = file_get_contents( $root . '/modules/digitalisimo-front/class-pro-gate.php' );
	$js   = file_get_contents( $root . '/modules/digitalisimo-front/assets/popup-counter.js' );
	foreach ( array( 'class-pro-gate.php' => $gate, 'popup-counter.js' => $js ) as $file => $source ) {
		check_gate( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $source ), $file . ' no nombra el complemento retirado ni sus prefijos.' );
	}
	check_gate( ! preg_match( "/wp_add_inline_script\([^;]*'after'/", $gate ), 'La puerta nunca añade código en posición after.' );
	check_gate( 52 === count( Pro_Gate::HANDLED ) && 52 === count( array_unique( Pro_Gate::HANDLED ) ), 'HANDLED tiene los 52 nombres, sin repetir.' );
	check_gate( in_array( 'lottie', Pro_Gate::HANDLED, true ) && in_array( 'code-highlight', Pro_Gate::HANDLED, true ), 'A ↔ D: lottie y code-highlight están en HANDLED.' );
	check_gate( array( 'template_id', 'anywhere_id', 'digitalisimo_template_id' ) === Pro_Gate::TEMPLATE_KEYS, 'TEMPLATE_KEYS según el contrato.' );
	check_gate( strlen( $js ) <= 512, 'La réplica de contadores pesa como mucho 0,5 KB (' . strlen( $js ) . ' B).' );
	check_gate( false === strpos( $gate, 'str_contains' ) && false === strpos( $gate, 'str_starts_with' ) && ! preg_match( '/\bmatch\s*\(/', $gate ) && ! preg_match( '/\?->/', $gate ), 'Sólo PHP 7.4.' );
	$motion = file_get_contents( $root . '/modules/motion-fx/module.php' );
	foreach ( array( "'name' => 'motion_fx'", "'name' => 'background_motion_fx'", "'motion_fx_motion_fx_scrolling'", "'motion_fx_motion_fx_mouse'" ) as $needle ) {
		check_gate( false !== strpos( $motion, $needle ), 'modules/motion-fx/module.php sigue declarando ' . $needle . ' (MOTION_KEYS).' );
	}
	check_gate( array( 'motion_fx_motion_fx_scrolling', 'motion_fx_motion_fx_mouse', 'background_motion_fx_motion_fx_scrolling', 'background_motion_fx_motion_fx_mouse' ) === Pro_Gate::MOTION_KEYS, 'MOTION_KEYS: los dos grupos por las dos activaciones.' );
	check_gate( false !== strpos( file_get_contents( $root . '/modules/sticky/module.php' ), "\$element->add_control(\n\t\t\t'sticky'," ), 'modules/sticky/module.php sigue declarando el control sticky.' );
	foreach ( array( 'popup' => 'modules/popup/tag.php', 'off-canvas' => 'modules/off-canvas/tag.php', 'reload-page' => 'modules/dynamic-tags/tags/reload-page.php' ) as $name => $file ) {
		check_gate( false !== strpos( file_get_contents( $root . '/' . $file ), "return '" . $name . "';" ) && in_array( $name, Pro_Gate::TAGS, true ), 'La etiqueta ' . $name . ' existe y está en TAGS.' );
	}
	check_gate( false !== strpos( file_get_contents( $root . '/modules/popup/module.php' ), "\$settings['popup']['hasPopUps']" ), 'hasPopUps sigue en el filtro de configuración de los popups.' );

	if ( $failures ) {
		fwrite( STDERR, "Puerta de PRO: " . count( $failures ) . " fallos de {$checks} comprobaciones:\n - " . implode( "\n - ", $failures ) . "\n" );
		exit( 1 );
	}
	echo "DIGITALÍSIMO Elements: puerta de PRO en verde ({$checks} comprobaciones).\n";
}
