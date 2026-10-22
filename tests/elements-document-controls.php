<?php
/**
 * F7a, frente L: controles de documento (`modules/digitalisimo-design/document/class-document-controls.php`).
 *
 * - Controles en `PageBase` (entradas, páginas, páginas de aterrizaje) y en la página de la biblioteca, detrás de
 *   «Ocultar título» (inyección tras `post_status` con respaldo `post_title`); nunca en el kit, los botones flotantes,
 *   las plantillas del Theme Builder, los popups ni los elementos de bucle.
 * - «Ocultar cabecera» y «Ocultar pie» sólo con el tema DIGITALÍSIMO activo, reconocido por carpeta o por `Update URI`
 *   con el `Theme_Compat` real; con otro tema no aparecen ni se aplican.
 * - Con el `Locations_Manager` real de PRO: la ubicación oculta queda vacía (`do_location()` devuelve `false`), el
 *   respaldo del tema no se pinta por su filtro público, y la otra parte, los ganchos y las demás peticiones siguen.
 * - `queried()` en entrada, portada estática, página de entradas, tienda y archivo.
 * - El widget «Imagen destacada» se oculta sólo con el contenido consultado y nunca dentro de un bucle.
 * - Sin ajustes, ningún gancho. Guarda sin el Theme Builder. El tema no cambia: su árbol es el ZIP de su versión.
 */
namespace Elementor {
	class Controls_Manager { const SWITCHER = 'switcher'; const RAW_HTML = 'raw_html'; const TEXT = 'text'; }
	/** Pila con la inyección como en `Controls_Stack` (`at = after`, con `fallback`). */
	class Controls_Stack {
		public $controls = array();
		public $order = array();
		public $injection = null;
		public $injections = array();
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function start_injection( array $position ) {
			if ( null !== $this->injection ) { throw new \RuntimeException( 'Inyección abierta dos veces.' ); }
			$of = isset( $this->controls[ $position['of'] ] ) ? $position['of'] : ( $position['fallback']['of'] ?? '' );
			if ( ! isset( $this->controls[ $of ] ) ) { throw new \RuntimeException( 'Inyección sin destino.' ); }
			$this->injection    = array_search( $of, $this->order, true ) + 1;
			$this->injections[] = $of;
		}
		public function end_injection() { $this->injection = null; }
		public function add_control( $name, array $args ) {
			if ( null === $this->injection ) { throw new \RuntimeException( 'Control fuera de la inyección: ' . $name ); }
			if ( isset( $this->controls[ $name ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $name ); }
			$this->controls[ $name ] = $args;
			array_splice( $this->order, $this->injection++, 0, array( $name ) );
		}
	}
	class Plugin { public static $instance; }
}
namespace Elementor\Core\Base {
	/** Documento: `register_controls()` dispara la acción y después cada tipo inyecta lo suyo, como el núcleo. */
	class Document extends \Elementor\Controls_Stack {
		public $id = 0;
		public static function get_type() { return 'base'; }
		public function get_name() { return static::get_type(); }
		public function __construct( $id = 0, array $base = array( 'post_title', 'post_status' ) ) {
			$this->id = $id;
			foreach ( $base as $name ) { $this->controls[ $name ] = array( 'type' => 'text' ); $this->order[] = $name; }
		}
		public function register() { do_action( 'elementor/documents/register_controls', $this ); $this->after_action(); return $this; }
		protected function after_action() {}
		public function print_content() { echo '<div data-doc="' . (int) $this->id . '">plantilla ' . (int) $this->id . '</div>'; }
	}
}
namespace Elementor\Core\DocumentTypes {
	/** `PageBase::register_controls()`: la acción y luego «Ocultar título» tras `post_status` (`page-base.php:69-112`). */
	abstract class PageBase extends \Elementor\Core\Base\Document {
		protected function after_action() {
			$this->start_injection( array( 'of' => 'post_status', 'fallback' => array( 'of' => 'post_title' ) ) );
			$this->add_control( 'hide_title', array( 'type' => 'switcher', 'selectors' => array( ':root' => '--page-title-display: none' ) ) );
			$this->end_injection();
		}
	}
	class Post extends PageBase { public static function get_type() { return 'wp-post'; } }
	class Page extends PageBase { public static function get_type() { return 'wp-page'; } }
}
namespace Elementor\Core\Kits\Documents {
	class Kit extends \Elementor\Core\DocumentTypes\PageBase { public static function get_type() { return 'kit'; } }
}
namespace Elementor\Modules\FloatingButtons\Documents {
	class Floating_Buttons extends \Elementor\Core\DocumentTypes\PageBase {
		public static function get_type() { return 'floating-buttons'; }
		protected function after_action() {}
	}
}
namespace Elementor\Modules\LandingPages\Documents {
	class Landing_Page extends \Elementor\Core\DocumentTypes\PageBase { public static function get_type() { return 'landing-page'; } }
}
namespace Elementor\Modules\Library\Documents {
	abstract class Library_Document extends \Elementor\Core\Base\Document {}
	/** Página de la biblioteca: también inyecta «Ocultar título» después de la acción (`library/documents/page.php:79-84`). */
	class Page extends Library_Document {
		public static function get_type() { return 'page'; }
		protected function after_action() {
			$this->start_injection( array( 'of' => 'post_status', 'fallback' => array( 'of' => 'post_title' ) ) );
			$this->add_control( 'hide_title', array( 'type' => 'switcher' ) );
			$this->end_injection();
		}
	}
}
namespace ElementorPro\Modules\ThemeBuilder\Documents {
	class Theme_Document extends \Elementor\Modules\Library\Documents\Library_Document { public static function get_type() { return 'header'; } }
	class Single extends Theme_Document { public static function get_type() { return 'single-post'; } }
}
namespace ElementorPro\Modules\Popup {
	class Document extends \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document { public static function get_type() { return 'popup'; } }
}
namespace ElementorPro\Modules\LoopBuilder\Documents {
	class Loop extends \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document { public static function get_type() { return 'loop-item'; } }
}
namespace ElementorPro\Modules\Posts\Traits {
	trait Pagination_Trait {}
}
namespace ElementorPro\Core {
	class Utils { public static function set_global_authordata() {} }
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() { return (object) array( 'inspector' => new \stdClass() ); }
	}
}
namespace ElementorPro\Modules\ThemeBuilder\Classes {
	/** Gestor de condiciones: las plantillas que cada ubicación tendría por sus condiciones. */
	class Conditions_Manager {
		public $by_location = array();
		public $calls = 0;
		public function get_documents_for_location( $location ) { $this->calls++; return $this->by_location[ $location ] ?? array(); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ELEMENTOR_VERSION', '4.3.5' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$repo = realpath( __DIR__ . '/..' );

	function check_document( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function remove_action( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['wp_filters'][ $hook ][ $priority ] ?? array() as $i => $entry ) { if ( $entry[0] === $callback ) { unset( $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ] ); } }
		return true;
	}
	function apply_filters( $hook, $value, ...$rest ) {
		if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) { return $value; }
		ksort( $GLOBALS['wp_filters'][ $hook ] );
		foreach ( $GLOBALS['wp_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $rest ), 0, $entry[1] ) ); }
		}
		return $value;
	}
	/** Como `WP_Hook`: un callback quitado durante la ejecución (los `remove_hooks` de PRO) ya no corre. */
	function do_action( $hook, ...$args ) {
		if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) { return; }
		ksort( $GLOBALS['wp_filters'][ $hook ] );
		foreach ( array_keys( $GLOBALS['wp_filters'][ $hook ] ) as $priority ) {
			foreach ( array_keys( $GLOBALS['wp_filters'][ $hook ][ $priority ] ?? array() ) as $i ) {
				if ( isset( $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ] ) ) {
					$entry = $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ];
					call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
				}
			}
		}
	}
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
	function hooks_on( $hook ) { $n = 0; foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $callbacks ) { $n += count( $callbacks ); } return $n; }

	// Consulta actual.
	$GLOBALS['view'] = array();
	function view( array $view ) { $GLOBALS['view'] = $view + array( 'singular' => false, 'home' => false, 'front' => false, 'shop' => false, 'queried' => 0, 'the_id' => 0 ); }
	function is_singular() { return $GLOBALS['view']['singular']; }
	function is_home() { return $GLOBALS['view']['home']; }
	function is_front_page() { return $GLOBALS['view']['front']; }
	function is_shop() { return $GLOBALS['view']['shop']; }
	function wc_get_page_id( $page ) { return 'shop' === $page ? 40 : -1; }
	function get_queried_object_id() { return $GLOBALS['view']['queried']; }
	function get_the_ID() { return $GLOBALS['view']['the_id']; }
	function get_post_status( $id ) { return 'publish'; }
	$GLOBALS['options'] = array( 'page_for_posts' => 30 );
	function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
	$GLOBALS['meta'] = array();
	$GLOBALS['meta_reads'] = 0;
	function get_post_meta( $id, $key = '', $single = false ) { $GLOBALS['meta_reads']++; return $GLOBALS['meta'][ (int) $id ][ $key ] ?? ''; }

	// Tema activo para el `Theme_Compat` real (carpeta o `Update URI`).
	$GLOBALS['template'] = 'digitalisimo-theme';
	$GLOBALS['theme_uris'] = array( 'copia-propia' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme', 'hello-elementor' => '' );
	function get_template() { return $GLOBALS['template']; }
	function wp_get_theme( $stylesheet = '' ) {
		return new class( $GLOBALS['theme_uris'][ $stylesheet ] ?? '' ) {
			private $uri;
			public function __construct( $uri ) { $this->uri = $uri; }
			public function get( $header ) { return 'UpdateURI' === $header ? $this->uri : ''; }
		};
	}

	require $root . '/modules/digitalisimo-design/class-module.php';
	require $root . '/modules/digitalisimo-theme/class-module.php';
	require $root . '/modules/theme-builder/classes/locations-manager.php';
	require $root . '/modules/digitalisimo-design/document/class-document-controls.php';
	$controls = \Digitalisimo\Elements\Design\Document\Document_Controls::class;

	// Guarda: sin el Theme Builder (su módulo se declara abajo) no se engancha nada.
	$controls::init();
	check_document( ! hooks_on( 'elementor/documents/register_controls' ) && ! hooks_on( 'template_redirect' ), 'Sin el Theme Builder no se registra nada.' );
	$missing = \Digitalisimo\Elements\Design\Module::missing();
	check_document( isset( $missing['document'] ) && in_array( 'ElementorPro\Modules\ThemeBuilder\Module::instance', $missing['document'], true ), 'La guarda anota el símbolo que falta.' );
	$GLOBALS['wp_filters'] = array();
}
namespace ElementorPro\Modules\ThemeBuilder {
	if ( true ) { // Declarada en ejecución, después de la prueba de la guarda.
		class Module {
			public static $instance;
			public $conditions;
			public $locations;
			public $documents = array();
			public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }
			public function get_conditions_manager() { return $this->conditions; }
			public function get_locations_manager() { return $this->locations; }
			public function get_document( $id ) { return $this->documents[ $id ] ?? null; }
		}
	}
}
namespace {
	$controls::init();
	$controls::init();
	check_document( 1 === hooks_on( 'elementor/documents/register_controls' ) && 1 === count( $GLOBALS['wp_filters']['template_redirect'][15] ?? array() ), 'Un gancho de controles y template_redirect en la prioridad 15.' );
	$base_hooks = $GLOBALS['wp_filters'];

	// Controles: tipos de página, detrás de «Ocultar título».
	$expected_theme = array( 'post_title', 'post_status', 'hide_title', $controls::HEADER, $controls::FOOTER, $controls::FEATURED );
	foreach ( array( '\Elementor\Core\DocumentTypes\Post', '\Elementor\Core\DocumentTypes\Page', '\Elementor\Modules\LandingPages\Documents\Landing_Page', '\Elementor\Modules\Library\Documents\Page' ) as $class ) {
		$doc = ( new $class( 5 ) )->register();
		check_document( $expected_theme === $doc->order, $class . ': controles detrás de «Ocultar título»: ' . implode( ',', $doc->order ) );
		check_document( array( 'post_status', 'post_status' ) === $doc->injections, $class . ': inyección tras post_status.' );
	}
	$doc = ( new \Elementor\Core\DocumentTypes\Page( 5, array( 'post_title' ) ) )->register();
	check_document( array( 'post_title', 'hide_title', $controls::HEADER, $controls::FOOTER, $controls::FEATURED ) === $doc->order, 'Sin post_status, respaldo post_title (R-M14).' );
	foreach ( array( $controls::HEADER, $controls::FOOTER, $controls::FEATURED ) as $key ) {
		$args = $doc->controls[ $key ];
		check_document( 'switcher' === $args['type'] && 'yes' === $args['return_value'] && '' === $args['default'] && empty( $args['selectors'] ) && empty( $args['prefix_class'] ), $key . ': SWITCHER yes/vacío sin salida.' );
		check_document( false === stripos( $args['label'] . $args['description'], 'elementor' ) && 0 === strpos( $args['label'], 'Ocultar' ), $key . ': texto en español y sin el nombre del núcleo.' );
	}
	check_document( array( 'type' => 'switcher', 'selectors' => array( ':root' => '--page-title-display: none' ) ) === $doc->controls['hide_title'], '«Ocultar título» no cambia.' );
	check_document( false !== strpos( $doc->controls[ $controls::FEATURED ]['description'], 'bucles' ), 'La ayuda de la imagen destacada dice que los bucles no cambian.' );
	foreach ( array( '\Elementor\Core\Kits\Documents\Kit', '\Elementor\Modules\FloatingButtons\Documents\Floating_Buttons', '\ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document', '\ElementorPro\Modules\ThemeBuilder\Documents\Single', '\ElementorPro\Modules\Popup\Document', '\ElementorPro\Modules\LoopBuilder\Documents\Loop' ) as $class ) {
		$doc = ( new $class( 9 ) )->register();
		check_document( ! array_intersect( array( $controls::HEADER, $controls::FOOTER, $controls::FEATURED, $controls::NOTE ), $doc->order ), $class . ': sin controles de documento.' );
	}
	$doc = ( new \Elementor\Core\DocumentTypes\Page( 5, array() ) );
	$controls::register_controls( $doc );
	check_document( array() === $doc->order, 'Sin post_status ni post_title no se inyecta nada.' );

	// Otro tema: cabecera y pie no aparecen; la nota remite a las condiciones; la imagen destacada sí.
	$GLOBALS['template'] = 'hello-elementor';
	$doc = ( new \Elementor\Core\DocumentTypes\Page( 5 ) )->register();
	check_document( array( 'post_title', 'post_status', 'hide_title', $controls::NOTE, $controls::FEATURED ) === $doc->order, 'Con otro tema no se ofrecen cabecera ni pie: ' . implode( ',', $doc->order ) );
	check_document( 'raw_html' === $doc->controls[ $controls::NOTE ]['type'] && false !== strpos( $doc->controls[ $controls::NOTE ]['raw'], 'condiciones' ), 'La nota explica las condiciones de la plantilla.' );
	// Una copia del tema en otra carpeta se reconoce por su Update URI.
	$GLOBALS['template'] = 'copia-propia';
	$doc = ( new \Elementor\Core\DocumentTypes\Page( 5 ) )->register();
	check_document( isset( $doc->controls[ $controls::HEADER ], $doc->controls[ $controls::FOOTER ] ), 'Una copia del tema en otra carpeta se reconoce por su Update URI.' );
	$GLOBALS['template'] = 'digitalisimo-theme';

	// queried().
	view( array( 'singular' => true, 'queried' => 5, 'the_id' => 5 ) );
	check_document( 5 === $controls::queried(), 'Vista individual.' );
	view( array( 'singular' => true, 'front' => true, 'queried' => 2 ) );
	check_document( 2 === $controls::queried(), 'Portada estática.' );
	view( array( 'home' => true ) );
	check_document( 30 === $controls::queried(), 'Página de entradas.' );
	view( array( 'home' => true, 'front' => true ) );
	check_document( 0 === $controls::queried(), 'Portada con las últimas entradas: sin contenido propio.' );
	view( array( 'shop' => true ) );
	check_document( 40 === $controls::queried(), 'Tienda.' );
	view( array() );
	check_document( 0 === $controls::queried(), 'Archivo: sin contenido propio.' );

	// Theme Builder real: cola de ubicaciones de PRO y ganchos del tema (header.php, plantilla en 5, respaldo en 10).
	$tb = \ElementorPro\Modules\ThemeBuilder\Module::instance();
	$tb->conditions = new \ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Manager();
	$tb->conditions->by_location = array( 'header' => array( 11 => true ), 'footer' => array( 12 => true ) );
	$tb->documents = array( 11 => new \Elementor\Core\Base\Document( 11 ), 12 => new \Elementor\Core\Base\Document( 12 ) );
	$theme_source = file_get_contents( $repo . '/digitalisimo-theme/inc/template-tags.php' );
	check_document( false !== strpos( $theme_source, "return (bool) apply_filters( 'digitalisimo_theme_header_footer', \$show, \$part );" ), 'El tema conserva su filtro público como última palabra.' );
	$fresh_request = function () use ( $tb, $base_hooks, $controls ) {
		$GLOBALS['wp_filters'] = $base_hooks;
		$tb->locations = ( new \ReflectionClass( '\ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager' ) )->newInstanceWithoutConstructor();
		foreach ( array( 'header', 'footer' ) as $part ) {
			$tb->locations->register_location( $part, array( 'hook' => 'digitalisimo_' . $part, 'remove_hooks' => array( 'digitalisimo_theme_' . $part ) ) );
		}
		$reset = new \ReflectionProperty( $controls, 'hidden' );
		$reset->setValue( null, array( 'basura' ) );
	};
	if ( ! function_exists( 'digitalisimo_theme_header' ) ) {
		/** Respaldo del tema (`digitalisimo-theme/inc/template-tags.php`): sólo su filtro público decide. */
		function digitalisimo_theme_header() { if ( apply_filters( 'digitalisimo_theme_header_footer', true, 'header' ) ) { echo '<header>respaldo</header>'; } }
		function digitalisimo_theme_footer() { if ( apply_filters( 'digitalisimo_theme_header_footer', true, 'footer' ) ) { echo '<footer>respaldo</footer>'; } }
	}
	$render = function () {
		add_action( 'digitalisimo_header', 'digitalisimo_theme_header', 10 );
		add_action( 'digitalisimo_footer', 'digitalisimo_theme_footer', 10 );
		add_action( 'digitalisimo_before_header', function () { echo '[antes]'; } );
		ob_start();
		do_action( 'template_redirect' );
		do_action( 'digitalisimo_before_header' );
		do_action( 'digitalisimo_header' );
		$header = ob_get_clean();
		ob_start();
		do_action( 'digitalisimo_footer' );
		return array( $header, ob_get_clean() );
	};

	// Sin ajustes: ningún gancho y todo como hoy.
	$fresh_request();
	view( array( 'singular' => true, 'queried' => 5, 'the_id' => 5 ) );
	$GLOBALS['meta'][5]['_elementor_page_settings'] = array( 'hide_title' => 'yes' );
	list( $header, $footer ) = $render();
	check_document( '[antes]<div data-doc="11">plantilla 11</div>' === $header && '<div data-doc="12">plantilla 12</div>' === $footer, 'Sin ajustes, cabecera y pie como hoy: ' . $header . ' | ' . $footer );
	check_document( ! hooks_on( 'digitalisimo_theme_header_footer' ) && ! hooks_on( 'elementor/frontend/widget/should_render' ) && ! hooks_on( 'elementor/element/is_dynamic_content' ) && 0 === $tb->conditions->calls - 2, 'Sin ajustes no se engancha nada ni se piden plantillas de más.' );

	// Ocultar cabecera con el tema DIGITALÍSIMO: ni plantilla ni respaldo; los ganchos y el pie siguen.
	$fresh_request();
	$GLOBALS['meta'][5]['_elementor_page_settings'] = array( $controls::HEADER => 'yes' );
	list( $header, $footer ) = $render();
	check_document( '[antes]' === $header, 'La cabecera desaparece entera y el gancho anterior sigue: ' . $header );
	check_document( '<div data-doc="12">plantilla 12</div>' === $footer, 'El pie no cambia: ' . $footer );
	check_document( false === $tb->locations->do_location( 'header' ), 'do_location( header ) queda vacía.' );
	check_document( true === apply_filters( 'digitalisimo_theme_header_footer', true, 'footer' ) && false === apply_filters( 'digitalisimo_theme_header_footer', true, 'header' ), 'El filtro del tema sólo para la parte pedida.' );
	// Ocultar pie (y no la cabecera).
	$fresh_request();
	$GLOBALS['meta'][5]['_elementor_page_settings'] = array( $controls::FOOTER => 'yes' );
	list( $header, $footer ) = $render();
	check_document( '[antes]<div data-doc="11">plantilla 11</div>' === $header && '' === $footer, 'Ocultar el pie no toca la cabecera: ' . $header . ' | ' . $footer );
	// Sin plantilla de cabecera, sólo el respaldo del tema, que también se oculta.
	$fresh_request();
	$tb->conditions->by_location = array( 'footer' => array( 12 => true ) );
	$GLOBALS['meta'][5]['_elementor_page_settings'] = array( $controls::HEADER => 'yes' );
	list( $header ) = $render();
	check_document( '[antes]' === $header, 'El respaldo del tema tampoco se pinta: ' . $header );
	$fresh_request();
	$GLOBALS['meta'][5]['_elementor_page_settings'] = array();
	list( $header ) = $render();
	check_document( '[antes]<header>respaldo</header>' === $header, 'Sin el ajuste, el respaldo vuelve en la petición siguiente: ' . $header );
	$tb->conditions->by_location = array( 'header' => array( 11 => true ), 'footer' => array( 12 => true ) );

	// Con otro tema, un ajuste guardado antes no se aplica (su cabecera ocuparía el hueco).
	$GLOBALS['template'] = 'hello-elementor';
	$fresh_request();
	$GLOBALS['meta'][5]['_elementor_page_settings'] = array( $controls::HEADER => 'yes', $controls::FOOTER => 'yes' );
	list( $header, $footer ) = $render();
	check_document( '[antes]<div data-doc="11">plantilla 11</div>' === $header && '<div data-doc="12">plantilla 12</div>' === $footer && ! hooks_on( 'digitalisimo_theme_header_footer' ), 'Con otro tema la página no cambia.' );
	$GLOBALS['template'] = 'digitalisimo-theme';

	// Imagen destacada: sólo el widget, sólo con el contenido consultado y nunca en un bucle.
	$widget = function ( $name ) { return new class( $name ) { private $n; public function __construct( $n ) { $this->n = $n; } public function get_name() { return $this->n; } }; };
	$fresh_request();
	view( array( 'singular' => true, 'queried' => 5, 'the_id' => 5 ) );
	$GLOBALS['meta'][5]['_elementor_page_settings'] = array( $controls::FEATURED => 'yes' );
	do_action( 'template_redirect' );
	check_document( ! hooks_on( 'digitalisimo_theme_header_footer' ), 'La imagen destacada no toca cabecera ni pie.' );
	$featured = $widget( 'theme-post-featured-image' );
	check_document( false === apply_filters( 'elementor/frontend/widget/should_render', true, $featured ), 'En la propia página (o su plantilla de entrada) se oculta.' );
	check_document( true === apply_filters( 'elementor/frontend/widget/should_render', true, $widget( 'image' ) ), 'Otro widget no cambia.' );
	check_document( true === apply_filters( 'elementor/element/is_dynamic_content', false, array(), $featured ) && false === apply_filters( 'elementor/element/is_dynamic_content', false, array(), $widget( 'image' ) ), 'En esta petición el widget se pinta fuera de la caché de elementos.' );
	$GLOBALS['view']['the_id'] = 6;
	check_document( true === apply_filters( 'elementor/frontend/widget/should_render', true, $featured ), 'Con otra entrada (otro contenido) no se oculta.' );
	$GLOBALS['view']['the_id'] = 5;
	$GLOBALS['wp_query'] = new \stdClass();
	$GLOBALS['wp_query']->is_loop_widget = true;
	check_document( true === apply_filters( 'elementor/frontend/widget/should_render', true, $featured ), 'En un bucle que incluye la entrada (Loop Grid o Carousel) no se oculta.' );
	unset( $GLOBALS['wp_query']->is_loop_widget );
	\Elementor\Plugin::$instance = (object) array( 'documents' => new class() { public $current; public function get_current() { return $this->current; } } );
	\Elementor\Plugin::$instance->documents->current = new \ElementorPro\Modules\LoopBuilder\Documents\Loop( 70 );
	check_document( true === apply_filters( 'elementor/frontend/widget/should_render', true, $featured ), 'Dentro de un elemento de bucle (también de términos) no se oculta.' );
	\Elementor\Plugin::$instance->documents->current = new \ElementorPro\Modules\ThemeBuilder\Documents\Single( 71 );
	check_document( false === apply_filters( 'elementor/frontend/widget/should_render', true, $featured ), 'En la plantilla de entrada sí se oculta.' );
	check_document( false === apply_filters( 'elementor/frontend/widget/should_render', false, $featured ), 'Lo que ya no se pintaba sigue sin pintarse.' );
	// La página siguiente, sin el ajuste: nada.
	$fresh_request();
	view( array( 'singular' => true, 'queried' => 8, 'the_id' => 8 ) );
	do_action( 'template_redirect' );
	check_document( ! hooks_on( 'elementor/frontend/widget/should_render' ) && true === $controls::featured_image( true, $featured ), 'Una petición sin el ajuste no oculta nada.' );
	// Archivo: no se lee ninguna meta.
	$fresh_request();
	view( array() );
	$GLOBALS['meta_reads'] = 0;
	do_action( 'template_redirect' );
	check_document( 0 === $GLOBALS['meta_reads'], 'Sin contenido propio no se lee ninguna meta.' );

	// Contrato con el tema: sólo su filtro público, ninguna función del tema.
	$source = file_get_contents( $root . '/modules/digitalisimo-design/document/class-document-controls.php' );
	check_document( ! preg_match( '/\bdigitalisimo_theme_[a-z_]+\s*\(/', $source ) && false !== strpos( $source, "'digitalisimo_theme_header_footer'" ) && false !== strpos( $source, 'Theme_Compat' ), 'Sólo el filtro público del tema y el ayudante Theme_Compat.' );

	// El tema no cambia: su árbol es, byte a byte, el ZIP publicado de su versión (1.0.0, fijada en theme-structure.php).
	preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', (string) file_get_contents( $repo . '/digitalisimo-theme/style.css' ), $version );
	$zip_path = '';
	foreach ( array( 'dist', 'rollback' ) as $folder ) {
		if ( isset( $version[1] ) && is_file( $repo . '/' . $folder . '/digitalisimo-theme-' . $version[1] . '.zip' ) ) { $zip_path = $repo . '/' . $folder . '/digitalisimo-theme-' . $version[1] . '.zip'; break; }
	}
	check_document( '' !== $zip_path, 'Falta el ZIP publicado del tema ' . ( $version[1] ?? '?' ) . '.' );
	$zip = new \ZipArchive();
	check_document( true === $zip->open( $zip_path ), 'No se pudo abrir ' . $zip_path );
	$published = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( '/' !== substr( $name, -1 ) ) { $published[ $name ] = hash( 'sha256', (string) $zip->getFromIndex( $i ) ); }
	}
	$zip->close();
	$tree = array();
	$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $repo . '/digitalisimo-theme', \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$tree[ 'digitalisimo-theme/' . str_replace( '\\', '/', substr( $file->getPathname(), strlen( $repo . '/digitalisimo-theme/' ) ) ) ] = hash_file( 'sha256', $file->getPathname() );
	}
	ksort( $published );
	ksort( $tree );
	check_document( $published === $tree, 'digitalisimo-theme/ cambió respecto de ' . basename( $zip_path ) . ': ' . implode( ', ', array_keys( array_diff_assoc( $tree, $published ) + array_diff_assoc( $published, $tree ) ) ) );
	check_document( false === strpos( implode( '', array_map( 'file_get_contents', array_map( function ( $f ) use ( $repo ) { return $repo . '/' . $f; }, array_keys( $tree ) ) ) ), 'digitalisimo_hide_' ), 'El tema no conoce los controles de documento.' );

	echo "DIGITALÍSIMO Elements: controles de documento validados (tema " . $version[1] . " sin cambios).\n";
}
