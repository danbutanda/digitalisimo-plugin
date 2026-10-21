<?php
/**
 * Kits (etapa K, §13.1 «preserve»): el marcador `digitalisimo-unconverted` (D9, §10.3) y lo demás del frente D.
 *
 * 1-12. Marcador: envolver en su sitio y en la raíz, lo conocido intacto, desenvolver cuando el tipo existe (con la
 *       caché invalidada una vez), `on_export`, contadores, `render()` vacío y `should_render`, `content_template`
 *       escapado, la biblioteca, nada registrado sin marcadores, `e-component` en la prioridad 5 antes de la captura
 *       de la 10, y la conversión que nunca corre en el front.
 * 13.  Template Kits de Envato → kit 3.0 (§10.2; §13.1 «guard» punto 4).
 * 14.  Fuentes, iconos y medios remotos (§10.5).
 * 15.  Biblioteca de plantillas: un ZIP de kit va al importador de kits (D12, §10.6).
 *
 * Sin WordPress ni el núcleo: dobles mínimos de sus funciones y gestores. Lanza una excepción si falta ZipArchive.
 */
namespace Elementor {
	class Plugin {
		public static $instance;
	}
	class Controls_Manager {
		const HIDDEN   = 'hidden';
		const RAW_HTML = 'raw_html';
	}
	class Widget_Base {
		public $test_controls = array();
		private $test_section = '';
		public function __construct( $data = array(), $args = null ) {}
		public function start_controls_section( $id, $args = array() ) {
			$this->test_section = $id;
		}
		public function add_control( $id, $args = array() ) {
			$this->test_controls[ $id ] = $args + array( 'section' => $this->test_section );
		}
		public function end_controls_section() {
			$this->test_section = '';
		}
		public function test_register() {
			$this->test_controls = array();
			$this->register_controls();
			return $this->test_controls;
		}
		protected function register_controls() {}
	}
	class Fonts {
		public static $types = array();
		public static function get_font_type( $name ) {
			return isset( self::$types[ $name ] ) ? self::$types[ $name ] : false;
		}
	}
	class Icons_Manager {
		public static $tabs = array();
		public static function get_icon_manager_tabs() {
			return self::$tabs;
		}
	}
}

namespace Elementor\Core\Files\CSS {
	class Post {
		public static $deleted = array();
		public $rendered       = array();
		private $id;
		public function __construct( $id = 0 ) {
			$this->id = $id;
		}
		public static function create( $id ) {
			return new self( $id );
		}
		public function delete() {
			self::$deleted[] = $this->id;
			delete_post_meta( $this->id, '_elementor_css' );
		}
		protected function render_styles( $element ) {
			$this->rendered[] = $element;
		}
	}
}

namespace Elementor\Modules\Components {
	class Module {
		public static $supported = false;
		public static function is_import_export_supported() {
			return self::$supported;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	if ( ! class_exists( 'ZipArchive' ) ) {
		throw new RuntimeException( 'Esta prueba necesita ZipArchive (§13.1: nada de SKIP).' );
	}

	$GLOBALS['t'] = array();
	$reset_wp     = function () {
		$GLOBALS['t'] = array(
			'hooks'      => array(),
			'options'    => array(),
			'autoload'   => array(),
			'meta'       => array(),
			'caps'       => array(
				'manage_options'  => true,
				'edit_posts'      => true,
				'unfiltered_html' => true,
			),
			'user'       => 1,
			'admin'      => false,
			'converted'  => 0,
		);
	};
	$reset_wp();

	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['t']['hooks'][ $hook ][ $priority ][] = $callback;
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function has_filter( $hook, $callback = false ) {
		if ( empty( $GLOBALS['t']['hooks'][ $hook ] ) ) {
			return false;
		}
		if ( false === $callback ) {
			return true;
		}
		foreach ( $GLOBALS['t']['hooks'][ $hook ] as $priority => $callbacks ) {
			foreach ( $callbacks as $registered ) {
				if ( $registered == $callback ) { // phpcs:ignore Universal.Operators.StrictComparisons -- arrays de callback.
					return $priority;
				}
			}
		}
		return false;
	}
	function apply_filters( $hook, $value, ...$args ) {
		$hooks = isset( $GLOBALS['t']['hooks'][ $hook ] ) ? $GLOBALS['t']['hooks'][ $hook ] : array();
		ksort( $hooks );
		foreach ( $hooks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = call_user_func( $callback, $value, ...$args );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$hooks = isset( $GLOBALS['t']['hooks'][ $hook ] ) ? $GLOBALS['t']['hooks'][ $hook ] : array();
		ksort( $hooks );
		foreach ( $hooks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				call_user_func( $callback, ...$args );
			}
		}
	}
	function wp_load_alloptions() {
		$all = array();
		foreach ( $GLOBALS['t']['options'] as $name => $value ) {
			if ( ! empty( $GLOBALS['t']['autoload'][ $name ] ) ) {
				$all[ $name ] = (string) $value;
			}
		}
		return $all;
	}
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['t']['options'] ) ? $GLOBALS['t']['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['t']['options'][ $name ] = $value;
		if ( null !== $autoload ) {
			$GLOBALS['t']['autoload'][ $name ] = (bool) $autoload;
		} elseif ( ! isset( $GLOBALS['t']['autoload'][ $name ] ) ) {
			$GLOBALS['t']['autoload'][ $name ] = true;
		}
		return true;
	}
	function delete_option( $name ) {
		unset( $GLOBALS['t']['options'][ $name ], $GLOBALS['t']['autoload'][ $name ] );
		return true;
	}
	function get_post_meta( $id, $key = '', $single = false ) {
		return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
	}
	function update_post_meta( $id, $key, $value ) {
		$GLOBALS['t']['meta'][ $id ][ $key ] = $value;
		return true;
	}
	function delete_post_meta( $id, $key ) {
		unset( $GLOBALS['t']['meta'][ $id ][ $key ] );
		return true;
	}
	function current_user_can( $cap ) {
		return ! empty( $GLOBALS['t']['caps'][ $cap ] );
	}
	function get_current_user_id() {
		return $GLOBALS['t']['user'];
	}
	function is_admin() {
		return $GLOBALS['t']['admin'];
	}
	function wp_doing_cron() {
		return false;
	}
	function wp_verify_nonce( $nonce, $action ) {
		return 'ok' === $nonce && 'elementor_ajax' === $action ? 1 : false;
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_js( $text ) {
		return addslashes( (string) $text );
	}
	function esc_url( $url ) {
		return (string) $url;
	}
	function admin_url( $path = '' ) {
		return 'https://a.test/wp-admin/' . ltrim( $path, '/' );
	}
	class WP_Error {
		public $code;
		public $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	/** `$wpdb` mínimo: sólo la suma de una meta que hace `Preserve::recount_site()`. */
	class Test_Wpdb {
		public $postmeta = 'wp_postmeta';
		public function prepare( $query, ...$args ) {
			return array( $query, $args );
		}
		public function get_var( $prepared ) {
			$key = $prepared[1][0];
			$sum = 0;
			foreach ( $GLOBALS['t']['meta'] as $meta ) {
				$sum += isset( $meta[ $key ] ) ? (int) $meta[ $key ] : 0;
			}
			return (string) $sum;
		}
	}
	$GLOBALS['wpdb'] = new Test_Wpdb();

	/** Gestores del núcleo: tipos conocidos configurables y el evento de registro de widgets. */
	class Test_Type {
		public $name;
		public function __construct( $name ) {
			$this->name = $name;
		}
		public function get_name() {
			return $this->name;
		}
	}
	class Test_Widgets {
		public $known = array();
		public $types = null;
		public function get_widget_types( $name = null ) {
			if ( null === $this->types ) {
				$this->types = array();
				foreach ( $this->known as $known ) {
					$this->types[ $known ] = new Test_Type( $known );
				}
				do_action( 'elementor/widgets/register', $this );
			}
			if ( null === $name ) {
				return $this->types;
			}
			return isset( $this->types[ $name ] ) ? $this->types[ $name ] : null;
		}
		public function register( $widget ) {
			if ( null === $this->types ) {
				$this->get_widget_types();
			}
			$this->types[ $widget->get_name() ] = $widget;
			return true;
		}
	}
	class Test_Element {
		public $data;
		public function __construct( array $data ) {
			$this->data = $data;
		}
		public function get_data( $item = null ) {
			if ( null === $item ) {
				return $this->data;
			}
			return isset( $this->data[ $item ] ) ? $this->data[ $item ] : null;
		}
		public function get_name() {
			return isset( $this->data['widgetType'] ) ? $this->data['widgetType'] : $this->data['elType'];
		}
	}
	class Test_Elements {
		public $known   = array( 'section', 'column', 'container' );
		public $created = array();
		public function get_element_types( $name = null ) {
			return in_array( $name, $this->known, true ) ? new Test_Type( $name ) : null;
		}
		public function create_element_instance( array $data ) {
			$this->created[] = $data;
			return new Test_Element( $data );
		}
	}
	class Test_Doc_Header {
		public static function get_property( $key ) {
			return 'location' === $key ? 'header' : null;
		}
	}
	class Test_Doc_Page {
		public static function get_property( $key ) {
			return null;
		}
	}
	class Test_Documents {
		public $types = array(
			'page'    => 'Test_Doc_Page',
			'section' => 'Test_Doc_Page',
			'header'  => 'Test_Doc_Header',
		);
		public function get_document_type( $type, $fallback = 'post' ) {
			if ( isset( $this->types[ $type ] ) ) {
				return $this->types[ $type ];
			}
			return false !== $fallback && isset( $this->types[ $fallback ] ) ? $this->types[ $fallback ] : false;
		}
	}
	class Test_Document {
		public $id;
		public $autosave = false;
		public function __construct( $id ) {
			$this->id = $id;
		}
		public function get_main_id() {
			return $this->id;
		}
		public function is_autosave() {
			return $this->autosave;
		}
	}

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	};

	$module_dir = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import';
	require $module_dir . '/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Unconverted_Widget;
	use Digitalisimo\Elements\Import\Kits\Preserve;
	use Digitalisimo\Elements\Import\Kits\Library;
	use Digitalisimo\Elements\Import\Sources\Envato_Kit;
	use Digitalisimo\Elements\Import\Sources\Asset_Scan;

	/** Núcleo nuevo con estos widgets y elementos registrados; olvida lo memorizado por Preserve. */
	$core = function ( array $widgets = array( 'heading', 'text-editor' ), array $elements = array( 'section', 'column', 'container' ) ) {
		$plugin                   = new stdClass();
		$plugin->widgets_manager  = new Test_Widgets();
		$plugin->elements_manager = new Test_Elements();
		$plugin->documents        = new Test_Documents();
		$plugin->widgets_manager->known  = $widgets;
		$plugin->elements_manager->known = $elements;
		\Elementor\Plugin::$instance     = $plugin;
		Preserve::reset();
		return $plugin;
	};
	$el = function ( $id, $type, array $settings = array(), array $children = array(), $widget = null ) {
		$element = array(
			'id'       => $id,
			'elType'   => $type,
			'settings' => $settings,
			'elements' => $children,
		);
		if ( null !== $widget ) {
			$element['widgetType'] = $widget;
		}
		return $element;
	};
	$widget = function ( $id, $type, array $settings = array(), array $children = array() ) use ( $el ) {
		return $el( $id, 'widget', $settings, $children, $type );
	};
	$section = function ( $id, array $columns ) use ( $el ) {
		return $el( $id, 'section', array(), $columns );
	};
	$column = function ( $id, array $widgets ) use ( $el ) {
		return $el( $id, 'column', array( '_column_size' => 100 ), $widgets );
	};
	$info = function ( $marker ) {
		return $marker['settings'][ Preserve::SETTING ];
	};

	// ── 1. Un desconocido en su sitio ──────────────────────────────────────────────────────────────────────────
	$core();
	$hero    = $widget( 'a1b2c3d', 'acme-hero', array( 'title' => 'Hola', '_margin' => array( 'top' => 4 ) ) );
	$heading = $widget( 'h1h1h1h', 'heading', array( 'title' => 'Conocido' ) );
	$doc     = array( $section( 's1s1s1s', array( $column( 'c1c1c1c', array( $heading, $hero ) ) ) ) );
	$wrapped = Preserve::wrap( $doc, Preserve::SOURCE_KIT );
	$marker  = $wrapped[0]['elements'][0]['elements'][1];
	$check( Preserve::is_marker( $marker ) && 'a1b2c3d' === $marker['id'] && array() === $marker['elements'], '1: el desconocido pasa a marcador con su mismo ID y sin hijos.' );
	$check(
		array(
			'v'       => 1,
			'type'    => 'widget/acme-hero',
			'reason'  => 'unknown',
			'source'  => 'kit',
			'element' => $hero,
		) === $info( $marker ),
		'1: la carga es { v, type, reason, source, element } con el original completo.'
	);
	$check( $heading === $wrapped[0]['elements'][0]['elements'][0] && 'c1c1c1c' === $wrapped[0]['elements'][0]['id'], '1: lo que lo rodea no cambia.' );
	$check( Preserve::payload( $marker ) === $hero && null === Preserve::payload( $heading ), '1: payload() devuelve el original sólo de un marcador.' );
	$check( Preserve::wrap( $wrapped ) === $wrapped, '1: envolver es idempotente: un marcador nunca se envuelve otra vez.' );

	// ── 2. Un contenedor desconocido en la raíz → envoltorio ──────────────────────────────────────────────────
	$row     = $el( 'r1r1r1r', 'acme-row', array( 'gap' => 3 ), array( $widget( 'k1k1k1k', 'heading', array( 'title' => 'Hijo' ) ) ) );
	$mixed   = Preserve::wrap( array( $section( 's2s2s2s', array() ), $row ) );
	$wrapper = $mixed[1];
	$check( 'section' === $wrapper['elType'] && 1 === $wrapper['settings'][ Preserve::WRAPPER_SETTING ] && false === $wrapper['isInner'], '2: en un documento con secciones, el marcador de la raíz va en una sección envoltorio.' );
	$inner = $wrapper['elements'][0];
	$check( 'column' === $inner['elType'] && 1 === $inner['settings'][ Preserve::WRAPPER_SETTING ] && 100 === $inner['settings']['_column_size'], '2: … y en una columna envoltorio al 100 %.' );
	$check( Preserve::is_marker( $inner['elements'][0] ) && $row === Preserve::payload( $inner['elements'][0] ) && 'acme-row' === $info( $inner['elements'][0] )['type'], '2: la carga conserva el contenedor desconocido con sus hijos conocidos y sus IDs (CV-19).' );
	$check( Preserve::wrap( array( $section( 's2s2s2s', array() ), $row ) ) === $mixed, '2: los IDs de los envoltorios son estables.' );
	$flex  = $el( 'f1f1f1f', 'e-flexbox', array(), array() );
	$roots = Preserve::wrap( array( $el( 'k0k0k0k', 'container', array(), array() ), $flex ) );
	$check( 'container' === $roots[1]['elType'] && ! empty( $roots[1]['settings'][ Preserve::WRAPPER_SETTING ] ) && Preserve::is_marker( $roots[1]['elements'][0] ), '2: con contenedores y sin secciones, el envoltorio es un contenedor.' );
	$core( array( 'heading' ), array( 'section', 'column' ) );
	$off = Preserve::wrap( array( $el( 'k0k0k0k', 'container', array(), array( $widget( 'w0w0w0w', 'heading' ) ) ) ) );
	$check( 'section' === $off[0]['elType'] && 'column' === $off[0]['elements'][0]['elType'] && 'container' === $info( $off[0]['elements'][0]['elements'][0] )['type'], '2: con el experimento de contenedores apagado, el contenedor entra como marcador en sección y columna (§10.4).' );
	$core();
	$odd = Preserve::wrap( array( $section( 's3s3s3s', array( $el( 'x1x1x1x', 'acme-column', array(), array() ) ) ) ) );
	$check( 'column' === $odd[0]['elements'][0]['elType'] && ! empty( $odd[0]['elements'][0]['settings'][ Preserve::WRAPPER_SETTING ] ), '2: un desconocido hijo directo de una sección va dentro de una columna envoltorio.' );
	$core( array( 'heading', 'nested-tabs' ) );
	$tabs   = $widget( 'n1n1n1n', 'nested-tabs', array(), array( $widget( 'z1z1z1z', 'acme-tab' ) ) );
	$nested = Preserve::wrap( array( $el( 'k9k9k9k', 'container', array(), array( $tabs ) ) ) );
	$check( 'container' === $nested[0]['elements'][0]['elements'][0]['elType'] && true === $nested[0]['elements'][0]['elements'][0]['isInner'], '2: un desconocido hijo de un widget anidado va dentro de un contenedor envoltorio.' );
	$core();
	$nameless = Preserve::wrap( array( $section( 's4s4s4s', array( $column( 'c4c4c4c', array( array( 'id' => 'q1q1q1q', 'elType' => 'widget', 'settings' => array() ), array( 'id' => '"><x', 'elType' => 'widget', 'widgetType' => 'acme' ) ) ) ) ) ) );
	$cells    = $nameless[0]['elements'][0]['elements'];
	$check( Preserve::is_marker( $cells[0] ) && 'widget' === $info( $cells[0] )['type'], '2: un widget sin widgetType se conserva aparte (el núcleo pediría la lista entera de widgets).' );
	$check( 1 === preg_match( '/^[0-9a-f]{7}$/', $cells[1]['id'] ) && '"><x' === Preserve::payload( $cells[1] )['id'], '2: un ID con otra forma recibe uno derivado; el original sigue en la carga.' );

	// ── 3. Lo conocido no cambia ──────────────────────────────────────────────────────────────────────────────
	$known = array( $section( 's5s5s5s', array( $column( 'c5c5c5c', array( $heading, $widget( 't5t5t5t', 'text-editor', array( 'editor' => '<p>x</p>' ) ) ) ) ) ), $el( 'k5k5k5k', 'container', array( 'flex' => 1 ), array( $heading ) ) );
	$check( Preserve::wrap( $known ) === $known && 0 === Preserve::count_markers( $known ), '3: un árbol sin desconocidos sale idéntico.' );
	$check( array( 'x', 3 ) === Preserve::wrap( array( 'x', 3 ) ), '3: lo que no es un elemento se deja para el núcleo (que rechaza el documento).' );

	// ── 4. Desenvolver cuando el tipo existe, con la caché invalidada una vez ────────────────────────────────
	$core();
	$reset_wp();
	$wrapped_root = Preserve::wrap( array( $section( 's6s6s6s', array( $column( 'c6c6c6c', array( $heading, $hero ) ) ) ), $row ) );
	$core( array( 'heading', 'text-editor', 'acme-hero' ), array( 'section', 'column', 'container', 'acme-row' ) );
	update_option( Module::OPTION_UNCONVERTED, 2, true );
	Preserve::boot();
	$check( false !== has_filter( 'elementor/document/load/data' ), '4: con marcadores en el sitio se engancha load/data.' );
	$document = new Test_Document( 10 );
	$GLOBALS['t']['meta'][10] = array(
		Module::META_UNCONVERTED   => 2,
		'_elementor_css'           => array( 'status' => 'file' ),
		'_elementor_element_cache' => 'html',
	);
	$loaded = apply_filters( 'elementor/document/load/data', $wrapped_root, $document );
	$check( array( $section( 's6s6s6s', array( $column( 'c6c6c6c', array( $heading, $hero ) ) ) ), $row ) === $loaded, '4: los dos vuelven a su sitio y el envoltorio de la raíz desaparece.' );
	$check( ! isset( $GLOBALS['t']['meta'][10]['_elementor_css'] ) && ! isset( $GLOBALS['t']['meta'][10]['_elementor_element_cache'] ) && array( 10 ) === \Elementor\Core\Files\CSS\Post::$deleted, '4: la primera vez se borran el CSS y la caché de elementos del documento (CC-22).' );
	$check( '' !== get_post_meta( 10, Preserve::META_RESTORED ) && 2 === get_post_meta( 10, Module::META_UNCONVERTED ), '4: queda la huella de lo devuelto; el contador del documento no cambia hasta guardar.' );
	$GLOBALS['t']['meta'][10]['_elementor_css']           = array( 'status' => 'file' );
	$GLOBALS['t']['meta'][10]['_elementor_element_cache'] = 'html';
	apply_filters( 'elementor/document/load/data', $wrapped_root, $document );
	$check( isset( $GLOBALS['t']['meta'][10]['_elementor_css'], $GLOBALS['t']['meta'][10]['_elementor_element_cache'] ) && 1 === count( \Elementor\Core\Files\CSS\Post::$deleted ), '4: la segunda carga no vuelve a borrar la caché.' );
	$check( $wrapped_root === apply_filters( 'elementor/document/load/data', $wrapped_root, new Test_Document( 11 ) ), '4: un documento sin _digitalisimo_unconverted no se toca.' );
	$partial = array( $section( 's7s7s7s', array( $column( 'c7c7c7c', array( $hero, $widget( 'g7g7g7g', 'acme-gone' ) ) ) ) ) );
	$wrapped = Preserve::wrap(
		$partial,
		'kit',
		array(
			'known' => function ( $el_type, $widget_type ) {
				return 'widget' !== $el_type;
			},
		)
	);
	$back    = Preserve::restore( $wrapped, array(), $restored );
	$check( $hero === $back[0]['elements'][0]['elements'][0] && Preserve::is_marker( $back[0]['elements'][0]['elements'][1] ) && array( 'widget/acme-hero' ) === $restored, '4: sólo vuelve lo que ya existe; lo demás sigue como marcador.' );
	$core( array( 'heading' ), array( 'section', 'column' ) );
	$two     = Preserve::wrap( array( $el( 'r8r8r8r', 'acme-row', array(), array() ) ) );
	$two[0]['elements'][0]['elements'][] = $heading;
	$core( array( 'heading' ), array( 'section', 'column', 'acme-row' ) );
	$kept = Preserve::restore( $two );
	$check( 'section' === $kept[0]['elType'] && 'acme-row' === $kept[0]['elements'][0]['elements'][0]['elType'] && $heading === $kept[0]['elements'][0]['elements'][1], '4: si alguien añadió contenido al envoltorio, el envoltorio se queda y el original vuelve en su posición.' );
	$check( array( $el( 'r8r8r8r', 'acme-row', array(), array() ) ) === Preserve::unwrap( Preserve::wrap( array( $el( 'r8r8r8r', 'acme-row', array(), array() ) ), 'kit', array( 'known' => function () { return false; } ) ) ), '4: unwrap() devuelve la forma de antes de importar, sea cual sea el motivo.' );

	// ── 5. on_export devuelve el original con su ID ───────────────────────────────────────────────────────────
	$core();
	$marker  = Preserve::marker( $hero, Preserve::REASON_UNKNOWN, Preserve::SOURCE_KIT );
	$export  = $marker;
	$export['id'] = 'nuevo01';
	$exporter = new Unconverted_Widget();
	$check( $hero === $exporter->on_export( $export ) && 'a1b2c3d' === $exporter->on_export( $export )['id'], '5: al exportar viaja el original, con su ID de siempre.' );
	$check( $heading === $exporter->on_export( $heading ), '5: sin carga, on_export no cambia nada.' );
	$check( 'x' === Preserve::payload( Preserve::with_payload( $marker, array( 'elType' => 'x' ) ) )['elType'] && $heading === Preserve::with_payload( $heading, array() ), '5: with_payload() cambia sólo la carga de un marcador (para el remapeo).' );

	// ── 6. Contadores y meta por documento ────────────────────────────────────────────────────────────────────
	$core();
	$reset_wp();
	$two_markers = Preserve::wrap( array( $section( 's9s9s9s', array( $column( 'c9c9c9c', array( $hero, $widget( 'b9b9b9b', 'acme-b' ) ) ) ) ) ) );
	Preserve::after_save( new Test_Document( 20 ), array( 'elements' => $two_markers ) );
	$check( 2 === get_post_meta( 20, Module::META_UNCONVERTED ) && 2 === Preserve::count() && ! empty( $GLOBALS['t']['autoload'][ Module::OPTION_UNCONVERTED ] ), '6: guardar fija la meta del documento y el total del sitio, con autoload.' );
	Preserve::after_save( new Test_Document( 21 ), array( 'elements' => Preserve::wrap( array( $section( 'sasasas', array( $column( 'cacacac', array( $hero ) ) ) ) ) ) ) );
	$check( 3 === Preserve::count(), '6: el total suma los documentos.' );
	$autosave           = new Test_Document( 20 );
	$autosave->autosave = true;
	Preserve::after_save( $autosave, array( 'elements' => array() ) );
	$check( 2 === get_post_meta( 20, Module::META_UNCONVERTED ), '6: un autoguardado no cuenta.' );
	Preserve::after_save( new Test_Document( 20 ), array( 'settings' => array( 'x' => 1 ) ) );
	$check( 2 === get_post_meta( 20, Module::META_UNCONVERTED ), '6: guardar sólo ajustes no cambia el contador.' );
	update_post_meta( 20, Preserve::META_RESTORED, 'abc' );
	Preserve::after_save( new Test_Document( 20 ), array( 'elements' => array( $heading ) ) );
	$check( '' === get_post_meta( 20, Module::META_UNCONVERTED ) && '' === get_post_meta( 20, Preserve::META_RESTORED ) && 1 === Preserve::count(), '6: sin marcadores, la meta se borra y el total baja.' );
	Preserve::after_save( new Test_Document( 21 ), array( 'elements' => array() ) );
	$check( 0 === Preserve::count() && ! array_key_exists( Module::OPTION_UNCONVERTED, $GLOBALS['t']['options'] ), '6: con cero marcadores la opción desaparece (un sitio sin marcadores no la carga).' );

	// ── 7. render() vacío y should_render del envoltorio ──────────────────────────────────────────────────────
	$render = new ReflectionMethod( Unconverted_Widget::class, 'render' );
	if ( PHP_VERSION_ID < 80100 ) {
		$render->setAccessible( true );
	}
	ob_start();
	$render->invoke( new Unconverted_Widget() );
	$check( '' === ob_get_clean(), '7: render() no imprime nada (presupuesto 0/0).' );
	$probe = new Unconverted_Widget();
	$check( array() === $probe->get_style_depends() && array() === $probe->get_script_depends() && false === $probe->show_in_panel() && Module::WIDGET === $probe->get_name(), '7: sin CSS ni JS y fuera del panel.' );
	$only = Preserve::wrap( array( $section( 'plain01', array() ), $el( 'rarara1', 'acme-row' ) ) )[1];
	$check( false === Preserve::filter_should_render( true, new Test_Element( $only ) ) && false === Preserve::filter_should_render( true, new Test_Element( $only['elements'][0] ) ), '7: la sección y la columna envoltorio no se pintan si sólo tienen marcadores.' );
	$with = $only;
	$with['elements'][0]['elements'][] = $heading;
	$check( true === Preserve::filter_should_render( true, new Test_Element( $with ) ), '7: con contenido de verdad, el envoltorio se pinta.' );
	$check( true === Preserve::filter_should_render( true, new Test_Element( $section( 'plain00', array() ) ) ) && false === Preserve::filter_should_render( false, new Test_Element( $with ) ), '7: una sección normal no se toca y un «no» del núcleo se respeta.' );

	// ── 8. content_template escapa ────────────────────────────────────────────────────────────────────────────
	$template = new ReflectionMethod( Unconverted_Widget::class, 'content_template' );
	if ( PHP_VERSION_ID < 80100 ) {
		$template->setAccessible( true );
	}
	ob_start();
	$template->invoke( new Unconverted_Widget() );
	$card = (string) ob_get_clean();
	$check( false !== strpos( $card, '{{ digiInfo.type' ) && false === strpos( $card, '{{{' ), '8: el tipo del kit se imprime con {{ }} (escapado), nunca con {{{ }}}.' );
	$check( false !== strpos( $card, 'Contenido conservado' ) && false !== strpos( $card, 'Componente v4' ) && false === stripos( $card, 'color:#' ), '8: la tarjeta está en español y no define colores propios.' );
	$controls = $probe->test_register();
	$check( isset( $controls[ Preserve::SETTING ] ) && 'hidden' === $controls[ Preserve::SETTING ]['type'] && 'raw_html' === $controls['digitalisimo_unconverted_notice']['type'], '8: un control oculto guarda la carga y un aviso fijo explica el marcador.' );

	// ── 9. El filtro de la biblioteca envuelve ────────────────────────────────────────────────────────────────
	$plugin = $core( array( 'heading', 'e-component' ) );
	$reset_wp();
	Preserve::boot();
	$component = $widget( 'cpcpcp1', 'e-component', array( 'component_instance' => array( 'component_id' => 9 ) ) );
	$library   = apply_filters( 'elementor/template_library/sources/local/import/elements', array( $section( 'slslsl1', array( $column( 'clclcl1', array( $hero, $component ) ) ) ) ) );
	$cells     = $library[0]['elements'][0]['elements'];
	$check( Preserve::is_marker( $cells[0] ) && 'library' === $info( $cells[0] )['source'] && $component === $cells[1], '9: la biblioteca envuelve lo desconocido (origen library) y deja los componentes, que su núcleo conserva.' );
	$check( null !== $plugin->widgets_manager->get_widget_types( Module::WIDGET ) && false !== has_filter( 'elementor/document/after_save' ), '9: al envolver, el marcador se registra aunque el sitio no tuviera ninguno y los guardados recuentan.' );

	// ── 10. Sin marcadores no se registran ni load/data ni el widget ──────────────────────────────────────────
	$plugin = $core();
	$reset_wp();
	Preserve::boot();
	$check( false === has_filter( 'elementor/document/load/data' ) && false === has_filter( 'elementor/frontend/section/should_render' ) && false === has_filter( 'elementor/element/parse_css' ), '10: sin marcadores, ni load/data, ni should_render, ni parse_css.' );
	$check( 20 === has_filter( 'elementor/widgets/register', array( Preserve::class, 'register_widget' ) ) && 20 === has_filter( 'elementor/template_library/sources/local/import/elements', array( Preserve::class, 'filter_library_import' ) ), '10: sólo quedan el registro condicionado (20) y el filtro de la biblioteca (20).' );
	$check( null === $plugin->widgets_manager->get_widget_types( Module::WIDGET ), '10: el registro de widgets no incluye el marcador.' );
	$plugin = $core();
	Preserve::boot();
	Preserve::expect( true );
	$check( null !== $plugin->widgets_manager->get_widget_types( Module::WIDGET ), '10: con una importación anunciada, el marcador sí se registra.' );

	// ── 11. e-component envuelto en la prioridad 5, antes de la captura de la 10 (CC-10, V-25) ────────────────
	$core( array( 'heading', 'e-component' ) );
	$reset_wp();
	$GLOBALS['t']['caps']['unfiltered_html'] = false;
	\Elementor\Modules\Components\Module::$supported = false;
	$captured = null;
	add_filter( 'elementor/document/save/data', array( Preserve::class, 'on_save_data' ), 5, 2 );
	add_filter(
		'elementor/document/save/data',
		function ( $data ) use ( &$captured ) {
			$captured         = $data['elements'];
			$data['elements'] = array();
			return $data;
		},
		10,
		2
	);
	$saved = apply_filters( 'elementor/document/save/data', array( 'elements' => array( $section( 'scscsc1', array( $column( 'ccccc11', array( $component, $hero ) ) ) ) ) ), new Test_Document( 30 ) );
	$cells = $captured[0]['elements'][0]['elements'];
	$check( array() === $saved['elements'] && Preserve::is_marker( $cells[0] ) && 'component' === $info( $cells[0] )['reason'] && 'widget/e-component' === $info( $cells[0] )['type'], '11: la captura del núcleo ya recibe el componente como marcador «component».' );
	$check( 'unknown' === $info( $cells[1] )['reason'], '11: en la misma pasada, el desconocido es «unknown».' );
	$stats = Preserve::stats();
	$check( array( 'widget/e-component' => array( 'component' => 1 ), 'widget/acme-hero' => array( 'unknown' => 1 ) ) === $stats['documents'][30] && 2 === $stats['kses'][30], '11: stats() cuenta los marcadores del documento y avisa de que kses saneará la carga (CC-17).' );
	$check( array( 'widget/e-component' => 'component', 'widget/acme-hero' => 'unknown' ) === $stats['why'], '11: stats() dice por qué falta cada tipo.' );
	$check( 'experiment:container' === Preserve::why( $el( 'k', 'container' ) ) && 'experiment:atomic' === Preserve::why( $el( 'f', 'e-flexbox' ) ) && 'experiment:nested-elements' === Preserve::why( $widget( 'n', 'mega-menu', array(), array( $el( 'k', 'container' ) ) ) ) && 'plugin:woocommerce' === Preserve::why( $widget( 'w', 'woocommerce-cart' ) ) && 'component' === Preserve::why( $component, Preserve::REASON_COMPONENT ) && 'unknown' === Preserve::why( $hero ), '11: why() distingue experimento apagado, WooCommerce, componente y desconocido (§10.4).' );
	$check( $captured === Preserve::restore( $captured ), '11: un componente nunca vuelve solo, aunque el tipo exista.' );
	\Elementor\Modules\Components\Module::$supported = true;
	Preserve::reset();
	$check( $component === Preserve::wrap( array( $section( 'x', array( $column( 'y', array( $component ) ) ) ) ) )[0]['elements'][0]['elements'][0], '11: si el núcleo admite componentes en kits, no se envuelven.' );
	\Elementor\Modules\Components\Module::$supported = false;
	$check( 'component' === $info( Preserve::wrap( array( $section( 'x', array( $column( 'y', array( $component ) ) ) ) ), 'migration' )[0]['elements'][0]['elements'][0] )['reason'], '11: en Migración rige la misma regla que en un kit.' );

	// ── 12. La conversión nunca corre en el front ─────────────────────────────────────────────────────────────
	$core();
	$reset_wp();
	update_option( Module::OPTION_UNCONVERTED, 1, true );
	Preserve::boot();
	add_filter(
		Module::FILTER_UNCONVERTED_CONVERT,
		function ( $element, $original, $type ) {
			++$GLOBALS['t']['converted'];
			return 'widget/acme-hero' === $type ? array( 'id' => $original['id'], 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $original['settings']['title'] ), 'elements' => array() ) : $element;
		},
		10,
		3
	);
	$GLOBALS['t']['meta'][40] = array( Module::META_UNCONVERTED => 1 );
	$stored                   = Preserve::wrap( array( $section( 'sfsfsf1', array( $column( 'cfcfcf1', array( $hero ) ) ) ) ) );
	$front                    = apply_filters( 'elementor/document/load/data', $stored, new Test_Document( 40 ) );
	$check( $stored === $front && 0 === $GLOBALS['t']['converted'], '12: en el front, el filtro de conversión ni se llama.' );
	$GLOBALS['t']['admin']              = true;
	$GLOBALS['t']['caps']['edit_posts'] = false;
	apply_filters( 'elementor/document/load/data', $stored, new Test_Document( 40 ) );
	$check( 0 === $GLOBALS['t']['converted'], '12: un visitante en admin-ajax tampoco lo dispara.' );
	$GLOBALS['t']['caps']['edit_posts'] = true;
	$editor                             = apply_filters( 'elementor/document/load/data', $stored, new Test_Document( 40 ) );
	$check( 'heading' === $editor[0]['elements'][0]['elements'][0]['widgetType'] && 'Hola' === $editor[0]['elements'][0]['elements'][0]['settings']['title'] && 1 === $GLOBALS['t']['converted'], '12: en el editor, la conversión de Migración sustituye el marcador.' );
	$check( 'heading' === Preserve::convert( $stored )[0]['elements'][0]['elements'][0]['widgetType'], '12: Migración puede convertir con convert().' );

	// ── Extra: el CSS del original que ya se pinta se genera con su ID ────────────────────────────────────────
	$plugin = $core( array( 'heading', 'acme-hero' ) );
	$css    = new \Elementor\Core\Files\CSS\Post( 50 );
	Preserve::parse_css( $css, new Test_Element( Preserve::marker( $hero ) ) );
	$check( 1 === count( $css->rendered ) && $hero === $css->rendered[0]->data, '12+: parse_css genera los estilos del original devuelto, con su mismo ID.' );
	Preserve::parse_css( $css, new Test_Element( Preserve::marker( $widget( 'gone001', 'acme-gone' ) ) ) );
	Preserve::parse_css( $css, new Test_Element( Preserve::marker( $component, Preserve::REASON_COMPONENT ) ) );
	Preserve::parse_css( $css, new Test_Element( $heading ) );
	$check( 1 === count( $css->rendered ), '12+: ni lo que sigue sin existir, ni un componente, ni un elemento normal.' );

	// ── 13. Template Kits de Envato → kit 3.0 (§10.2) ─────────────────────────────────────────────────────────
	$core();
	$page_content = array( $section( 'e1e1e1e', array( $column( 'e2e2e2e', array( $widget( 'e3e3e3e', 'heading', array( 'title' => 'Inicio' ) ) ) ) ) ) );
	$files        = array(
		'manifest.json'          => array(
			'manifest_version' => '1.0.0',
			'title'            => 'Kit Sintético',
			'page_builder'     => 'elementor',
			'required_plugins' => array(
				array(
					'name'    => 'Formularios de prueba',
					'version' => '2.1',
					'file'    => 'formularios/formularios.php',
					'author'  => 'Autor',
					'url'     => 'https://demo-autor.test/plugin',
				),
				'Plugin sin datos',
			),
			'templates'        => array(
				array( 'name' => 'Estilos', 'source' => 'templates/global.json', 'type' => 'section', 'metadata' => array( 'template_type' => 'global-styles' ) ),
				array( 'name' => 'Inicio', 'source' => 'templates/home.json', 'type' => 'page', 'screenshot' => 'screens/home.jpg', 'metadata' => array( 'template_type' => 'single-page', 'include_in_zip' => '1', 'wp_page_template' => 'elementor_header_footer', 'additional_template_information' => array( 'x' ) ) ),
				array( 'name' => 'Cabecera', 'source' => 'templates/header.json', 'type' => 'header', 'metadata' => array( 'template_type' => 'section-header', 'elementor_pro_conditions' => array( 'include/general' ) ) ),
				array( 'name' => 'Pie', 'source' => 'templates/footer.json', 'type' => 'footer', 'metadata' => array( 'template_type' => 'section-footer' ) ),
				array( 'name' => 'Raro', 'source' => 'templates/raro.json', 'type' => 'acme-type' ),
				array( 'name' => 'Fuera', 'source' => '../fuera.json', 'type' => 'page' ),
				array( 'name' => 'Sin archivo', 'source' => 'templates/missing.json', 'type' => 'page', 'metadata' => array( 'include_in_zip' => '0' ) ),
				array( 'name' => 'Inicio 2', 'source' => 'templates/otra/home.json', 'type' => 'page' ),
				array( 'name' => 'Rota', 'source' => 'templates/rota.json', 'type' => 'page' ),
				array( 'name' => 'Larga', 'source' => 'templates/' . str_repeat( 'a', 80 ) . '.json', 'type' => 'page' ),
			),
		),
		'templates/global.json'  => array(
			'content'       => array(),
			'page_settings' => array(
				'system_colors' => array( array( '_id' => 'primary', 'title' => 'Primario', 'color' => '#123456' ) ),
				'site_name'     => 'Sitio del autor',
				'site_logo'     => array( 'url' => 'https://demo-autor.test/logo.png', 'id' => 4 ),
			),
		),
		'templates/home.json'    => array( 'content' => $page_content, 'page_settings' => array( 'hide_title' => 'yes' ), 'metadata' => array( 'template_type' => 'single-page' ) ),
		'templates/header.json'  => array( 'content' => $page_content, 'page_settings' => array(), 'metadata' => array( 'elementor_pro_conditions' => array( 'include/singular' ) ) ),
		'templates/footer.json'  => json_encode( array( 'content' => $page_content, 'metadata' => array( 'elementor_pro_conditions' => array( 'include/general', 'exclude/singular/page/42', 'include/archive/category/7', 'mal', 'include/archive/category/x"y', 'include/general' ) ) ) ),
		'templates/raro.json'    => array( 'content' => $page_content ),
		'TEMPLATES/OTRA/HOME.JSON' => array( 'content' => $page_content ),
		'templates/rota.json'    => array( 'content' => array( 'no es un elemento' ) ),
		'templates/' . str_repeat( 'a', 80 ) . '.json' => array( 'content' => $page_content ),
	);
	$env = array(
		'registered' => function ( $type ) {
			return in_array( $type, array( 'page', 'section', 'header', 'footer' ), true );
		},
		'location'   => function ( $type ) {
			return in_array( $type, array( 'header', 'footer' ), true ) ? $type : '';
		},
	);
	$out      = Envato_Kit::to_kit3( $files, $env );
	$manifest = $out['manifest.json'];
	$long     = str_repeat( 'a', Envato_Kit::MAX_KEY );
	$check( array( 'manifest.json', 'templates/home.json', 'templates/header.json', 'templates/footer.json', 'templates/raro.json', 'templates/home-2.json', 'templates/' . $long . '.json', 'site-settings.json', 'digitalisimo/theme-builder.json' ) === array_keys( $out ), '13: salen el manifiesto, una plantilla por archivo válido (con -2 si se repite y la clave acotada), los ajustes globales y las condiciones completas.' );
	$check( '3.0' === $manifest['version'] && ! isset( $manifest['manifest_version'] ) && 'Kit Sintético' === $manifest['title'] && 'kit-sint-tico' === $manifest['name'], '13: manifiesto 3.0, sin la marca del formato (no vuelve a pasar por el adaptador nativo).' );
	$check( false === $manifest['site-settings']['theme'] && false === $manifest['site-settings']['experiments'] && true === $manifest['site-settings']['globalColors'], '13: los estilos globales se importan sin tema ni experimentos.' );
	$check( array( 'settings' => array( 'system_colors' => array( array( '_id' => 'primary', 'title' => 'Primario', 'color' => '#123456' ) ) ) ) === $out['site-settings.json'], '13: los globales pasan a site-settings.json sin la identidad del sitio.' );
	$check( array( 'title' => 'Inicio', 'doc_type' => 'page', 'thumbnail' => '' ) === $manifest['templates']['home'], '13: una página entra como página, sin miniatura (la captura no viaja).' );
	$check(
		array(
			'title'      => 'Cabecera',
			'doc_type'   => 'header',
			'thumbnail'  => '',
			'location'   => 'header',
			'conditions' => array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ) ),
		) === $manifest['templates']['header'],
		'13: una cabecera lleva su ubicación y sólo la primera include sin sub_id.'
	);
	$check( array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ) ) === $out['digitalisimo/theme-builder.json']['header']['conditions'], '13: manda el manifiesto cuando las dos lecturas difieren (CV-13).' );
	$check(
		array(
			array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ),
			array( 'type' => 'exclude', 'name' => 'singular', 'sub_name' => 'page', 'sub_id' => '42' ),
			array( 'type' => 'include', 'name' => 'archive', 'sub_name' => 'category', 'sub_id' => '7' ),
		) === $out['digitalisimo/theme-builder.json']['footer']['conditions'] && array() === $out['digitalisimo/theme-builder.json']['footer']['fields'],
		'13: la lista completa de condiciones va a theme-builder.json, sin repetidas ni inválidas (y se lee del archivo si el manifiesto no la trae).'
	);
	$check( 'footer' === $manifest['templates']['footer']['location'] && array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ) ) === $manifest['templates']['footer']['conditions'], '13: el manifiesto nunca lleva condiciones con sub_id: se aplicarían sin traducir (V-24).' );
	$check( 'section' === $manifest['templates']['raro']['doc_type'] && 'acme-type' === $manifest['templates']['raro']['original_type'] && ! isset( $manifest['templates']['raro']['location'] ), '13: un tipo de documento desconocido entra como section con su tipo original (CC-4).' );
	$check( array( 'content' => $page_content, 'settings' => array( 'hide_title' => 'yes', 'template' => 'elementor_header_footer' ), 'metadata' => array() ) === $out['templates/home.json'], '13: la plantilla conserva su contenido, wp_page_template pasa a settings.template y metadata queda vacía (CC-21).' );
	foreach ( $out as $path => $data ) {
		if ( 0 === strpos( $path, 'templates/' ) ) {
			$check( array() === $data['metadata'] && ! isset( $data['page_settings'] ), "13: {$path} no lleva claves del formato." );
		}
	}
	$check( array( array( 'name' => 'Formularios de prueba', 'plugin' => 'formularios/formularios.php', 'pluginUri' => '', 'version' => '2.1' ), array( 'name' => 'Plugin sin datos', 'plugin' => '', 'pluginUri' => '', 'version' => '' ) ) === $manifest['plugins'], '13: los plugins pedidos van al análisis en la forma 3.0, sin enlaces ni autores.' );
	$own = $manifest[ Module::MANIFEST_KEY ];
	$check( 'envato' === $own['format'] && Envato_Kit::kit_hash( 'Kit Sintético', array_column( $files['manifest.json']['templates'], 'source' ) ) === $own['kit'] && array( 'theme-builder' => 1 ) === $own['parts'], '13: la huella del kit y la parte theme-builder quedan en digitalisimo.' );
	$codes = array_column( $own['conversion']['report'], 'code' );
	foreach ( array( 'envato.invalid-source', 'envato.not-included', 'envato.unknown-type', 'envato.page-template', 'envato.metadata-dropped', 'envato.condition-invalid', 'envato.metadata-mismatch', 'envato.invalid-content' ) as $code ) {
		$check( in_array( $code, $codes, true ), "13: el informe de la conversión anota {$code}." );
	}
	$check( array( 'acme-type' => 1 ) === $own['conversion']['templates']['unknown_types'] && 3 === $own['conversion']['templates']['skipped'] && 6 === $own['conversion']['templates']['total'], '13: la conversión cuenta lo convertido, lo omitido y los tipos que faltan.' );
	$check( false === strpos( (string) json_encode( $manifest ), 'demo-autor.test' ), '13: ningún enlace ni dato del sitio del autor llega al manifiesto.' );
	$normalizer = 'Digitalisimo\\Elements\\Import\\Kits\\Normalizer';
	if ( is_callable( array( $normalizer, 'safe_path' ) ) ) {
		foreach ( array_keys( $out ) as $path ) {
			$check( $normalizer::safe_path( $path ), "13: {$path} es una ruta que admite el normalizador." );
		}
	}
	$plain = Envato_Kit::convert( $files['manifest.json'], $files, $env );
	$check( array( 'name' => 'Formularios de prueba', 'version' => '2.1', 'file' => 'formularios/formularios.php' ) === $plain['analysis']['requested']['plugins'][0] && true === $plain['analysis']['global_styles'] && $out['templates/home.json'] === $plain['files']['templates/home.json'], '13: convert() da lo mismo con el análisis aparte.' );
	$check( Envato_Kit::kit_hash( 'T', array( 'b/x.json', 'a/y.json' ) ) === Envato_Kit::kit_hash( 'T', array( 'a/y.json', 'b/x.json' ) ), '13: la huella no depende del orden de las plantillas.' );
	foreach ( array( array( 'version' => '3.0' ), array( 'manifest_version' => '' ) ) as $not ) {
		$threw = false;
		try {
			Envato_Kit::to_kit3( array( 'manifest.json' => $not ) );
		} catch ( RuntimeException $error ) {
			$threw = true;
		}
		$check( $threw, '13: un manifiesto que no es de Template Kit se rechaza.' );
	}
	$threw = false;
	try {
		Envato_Kit::to_kit3( array( 'manifest.json' => array( 'manifest_version' => '1', 'page_builder' => 'otro', 'templates' => array() ) ) );
	} catch ( RuntimeException $error ) {
		$threw = false !== strpos( $error->getMessage(), 'otro constructor' );
	}
	$check( $threw, '13: un Template Kit de otro constructor se rechaza con su motivo.' );
	$check( null === Envato_Kit::parse_condition( 'include' ) && null === Envato_Kit::parse_condition( 'todo/general' ) && null === Envato_Kit::parse_condition( array( 'type' => 'include', 'name' => '<x>' ) ) && array( 'type' => 'exclude', 'name' => 'archive', 'sub_name' => 'by_author', 'sub_id' => '3' ) === Envato_Kit::parse_condition( '/exclude/archive/by_author/3/' ), '13: las condiciones se validan pieza por pieza.' );
	$check( 'header' === Envato_Kit::location_of( 'header' ) && '' === Envato_Kit::location_of( 'page' ) && 'single' === Envato_Kit::location_of( 'single-post' ) && Envato_Kit::is_registered( 'page' ) && ! Envato_Kit::is_registered( 'acme-type' ), '13: por defecto, los tipos y las ubicaciones salen de los documentos registrados en el núcleo.' );
	$check( Envato_Kit::is_envato( array( 'manifest_version' => '1.0.0' ) ) && ! Envato_Kit::is_envato( array( 'version' => '3.0' ) ), '13: is_envato() reconoce el formato por su versión de manifiesto.' );

	// ── 14. Fuentes, iconos y medios remotos (§10.5) ──────────────────────────────────────────────────────────
	$docs = array(
		array(
			$el(
				'm1m1m1m',
				'section',
				array( 'background_image' => array( 'url' => 'https://CDN.demo-autor.test/a.jpg', 'id' => 5 ) ),
				array(
					$column(
						'm2m2m2m',
						array(
							$widget( 'm3m3m3m', 'heading', array( 'typography_font_family' => 'Fuente Google', 'title_typography_font_family' => 'Fuente Sistema', 'custom_css' => 'selector{background:url("https://bloqueado.test/x.png")} a{background:url(data:image/png;base64,AAAA)}' ) ),
							$widget( 'm4m4m4m', 'icon', array( 'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) ) ),
							$widget( 'm5m5m5m', 'icon', array( 'selected_icon' => array( 'value' => array( 'url' => 'https://a.test/wp-content/uploads/x.svg', 'id' => 3 ), 'library' => 'svg' ) ) ),
							$widget( 'm6m6m6m', 'icon', array( 'selected_icon' => array( 'value' => 'icon-x', 'library' => 'conjunto-ajeno' ) ) ),
							$widget( 'm7m7m7m', 'button', array( 'link' => array( 'url' => 'https://enlace.test/', 'is_external' => 'on', 'nofollow' => '' ) ) ),
							$widget( 'm8m8m8m', 'image', array( 'image' => array( 'url' => '/wp-content/uploads/relativa.png', 'id' => 8 ) ) ),
							$widget( 'm9m9m9m', 'gallery', array( 'gallery' => array( array( 'id' => 1, 'url' => 'https://cdn.demo-autor.test/b.jpg' ) ) ) ),
							$widget( 'mamamam', 'heading', array( 'typography_font_family' => 'Iconos Raros' ) ),
							Preserve::marker( $widget( 'mbmbmbm', 'acme', array( 'typography_font_family' => 'Fuente Propia' ) ) ),
						)
					),
				)
			),
		),
	);
	$kit_settings = array(
		'system_typography' => array( array( '_id' => 'primary', 'typography_font_family' => 'Fuente Google' ) ),
		'custom_typography' => array( array( '_id' => 'k1', 'typography_font_family' => 'Fuente Que Falta' ) ),
	);
	$scan = Asset_Scan::scan(
		$docs,
		$kit_settings,
		array(
			'font_type'    => function ( $family ) {
				$types = array(
					'Fuente Google'  => 'googlefonts',
					'Fuente Sistema' => 'system',
					'Fuente Propia'  => 'custom',
				);
				return isset( $types[ $family ] ) ? $types[ $family ] : false;
			},
			'icon_family'  => function ( $family ) {
				return 'Iconos Raros' === $family;
			},
			'icon_library' => function ( $library ) {
				return 'fa-solid' === $library;
			},
			'blocked'      => function ( $url ) {
				return false !== strpos( $url, 'bloqueado.test' );
			},
			'origin_hosts' => Asset_Scan::hosts_of( array( 'home' => 'https://A.test', 'site' => 'https://a.test/wp', 'uploads' => '' ) ),
		)
	);
	$check(
		array(
			'google'  => array( 'Fuente Google' => 2 ),
			'system'  => array( 'Fuente Sistema' => 1 ),
			'custom'  => array( 'Fuente Propia' => 1 ),
			'missing' => array( 'Fuente Que Falta' => 1 ),
			'icon'    => array( 'Iconos Raros' => 1 ),
		) === $scan['fonts'],
		'14: las familias se clasifican con el registro del sitio (Google se lista, no se descarga), los iconos siguen la regla de SEO y también se mira dentro de un marcador.'
	);
	$check( array( 'conjunto-ajeno' => array( 'count' => 1, 'status' => 'missing' ), 'fa-solid' => array( 'count' => 1, 'status' => 'available' ) ) === $scan['icons']['libraries'] && 1 === $scan['icons']['svg'], '14: cada biblioteca de iconos existe o falta en este sitio; un SVG cuenta como medio.' );
	$check(
		array(
			'a.test'               => array( 'count' => 1, 'origin' => true, 'blocked' => false ),
			'bloqueado.test'       => array( 'count' => 1, 'origin' => false, 'blocked' => true ),
			'cdn.demo-autor.test'  => array( 'count' => 2, 'origin' => false, 'blocked' => false ),
		) === $scan['media']['hosts'] && 3 === $scan['media']['external'] && 1 === $scan['media']['relative'],
		'14: los medios remotos se agrupan por host (origen, externos y bloqueados); los enlaces y los data: no cuentan.'
	);
	$check( 0 === $scan['truncated'], '14: nada truncado en un kit pequeño.' );
	\Elementor\Fonts::$types = array( 'Fuente Google' => 'googlefonts' );
	$check( 'googlefonts' === Asset_Scan::site_font_type( 'Fuente Google' ) && false === Asset_Scan::site_icon_family( 'Iconos' ) && null === Asset_Scan::site_icon_library( 'fa-solid' ) && false === Asset_Scan::vendor_url( 'https://x.test' ), '14: por defecto, lo del sitio: registro de fuentes, SEO si está, iconos si el núcleo los dice y el escudo del proveedor.' );

	// ── 15. Biblioteca: un ZIP de kit va al importador de kits (D12) ──────────────────────────────────────────
	$tmp_dir = sys_get_temp_dir() . '/digikit-preserve-' . bin2hex( random_bytes( 4 ) );
	mkdir( $tmp_dir );
	$zip = function ( $name, array $entries ) use ( $tmp_dir ) {
		$path    = $tmp_dir . '/' . $name;
		$archive = new ZipArchive();
		if ( true !== $archive->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'No se pudo crear el ZIP de prueba ' . $name );
		}
		foreach ( $entries as $entry => $content ) {
			$archive->addFromString( $entry, $content );
		}
		$archive->close();
		return $path;
	};
	$kit_root   = $zip( 'raiz.zip', array( 'manifest.json' => '{}', 'templates/1.json' => '{}' ) );
	$kit_sub    = $zip( 'sub.zip', array( 'mi-kit/manifest.json' => '{}', '__MACOSX/mi-kit/._manifest.json' => 'x' ) );
	$kit_inner  = $zip( 'market.zip', array( 'Documentacion/index.html' => '<p>x</p>', 'kit.zip' => 'PK' ) );
	$loose      = $zip( 'sueltas.zip', array( 'a.json' => '{}', 'b.json' => '{}' ) );
	$deep       = $zip( 'honda.zip', array( 'a/b/manifest.json' => '{}' ) );
	$two_inner  = $zip( 'dos.zip', array( 'uno.zip' => 'PK', 'dos.zip' => 'PK' ) );
	$check( Library::looks_like_kit( $kit_root ) && Library::looks_like_kit( $kit_sub ) && Library::looks_like_kit( $kit_inner ), '15: un kit se reconoce por su manifest.json en la raíz o en una subcarpeta, o por un único ZIP interior.' );
	$check( ! Library::looks_like_kit( $loose ) && ! Library::looks_like_kit( $deep ) && ! Library::looks_like_kit( $two_inner ) && ! Library::looks_like_kit( $tmp_dir . '/no-existe.zip' ), '15: un ZIP de plantillas sueltas, uno con el manifiesto a dos niveles o con dos ZIP interiores sigue el camino nativo.' );

	$core();
	$reset_wp();
	Library::reset();
	Library::boot();
	$check( 5 === has_filter( 'wp_ajax_elementor_library_direct_actions', array( Library::class, 'intercept' ) ), '15: la intercepción corre antes del núcleo (prioridad 5).' );
	$prepared = array();
	Library::set_upload_check(
		function ( $file ) {
			return is_file( $file );
		}
	);
	Library::set_preparer(
		function ( $file ) use ( &$prepared ) {
			$prepared[] = $file;
			return array( 'token' => str_repeat( 'ab', 32 ) );
		}
	);
	$request = array(
		'action'         => 'elementor_library_direct_actions',
		'library_action' => 'direct_import_template',
		'_nonce'         => 'ok',
	);
	$upload  = function ( $path, $name = 'kit.zip', $error = 0 ) {
		return array(
			'file' => array(
				'name'     => $name,
				'tmp_name' => $path,
				'error'    => $error,
			),
		);
	};
	$check( null === Library::handle( array( 'library_action' => 'export_template', '_nonce' => 'ok' ), $upload( $kit_root ) ) && ! Preserve::should_register(), '15: otra acción de la biblioteca no se toca (ni anuncia importación).' );
	$GLOBALS['t']['caps']['manage_options'] = false;
	$check( null === Library::handle( $request, $upload( $kit_root ) ) && Preserve::should_register(), '15: sin manage_options sigue el núcleo, pero el marcador queda anunciado para su importación.' );
	$GLOBALS['t']['caps']['manage_options'] = true;
	$check( null === Library::handle( array( '_nonce' => 'otro' ) + $request, $upload( $kit_root ) ), '15: con un nonce ajeno sigue el núcleo (que lo rechaza).' );
	$check( null === Library::handle( $request, array() ) && null === Library::handle( $request, $upload( $kit_root, 'kit.zip', 1 ) ) && null === Library::handle( $request, $upload( $kit_root, 'plantilla.json' ) ), '15: sin archivo, con un error de subida o con un JSON sigue el núcleo.' );
	$check( null === Library::handle( $request, $upload( $loose ) ) && array() === $prepared, '15: un ZIP de plantillas sueltas sigue el núcleo y no se prepara nada.' );
	Library::set_upload_check(
		function () {
			return false;
		}
	);
	$check( null === Library::handle( $request, $upload( $kit_root ) ), '15: un archivo que no llegó por una subida HTTP no se toca.' );
	Library::set_upload_check(
		function ( $file ) {
			return is_file( $file );
		}
	);
	$url = Library::handle( $request, $upload( $kit_root, 'MI-KIT.ZIP' ) );
	$check( is_string( $url ) && false !== strpos( $url, '#/import-customization?referrer=digitalisimo&id=' . str_repeat( 'ab', 32 ) ) && array( $kit_root ) === $prepared, '15: un kit se prepara una vez y lleva a la App con su token.' );
	Library::set_preparer(
		function () {
			throw new RuntimeException( 'El kit tiene una entrada no admitida.' );
		}
	);
	$message = '';
	try {
		Library::handle( $request, $upload( $kit_sub ) );
	} catch ( RuntimeException $error ) {
		$message = $error->getMessage();
	}
	$check( 'El kit tiene una entrada no admitida.' === $message, '15: un kit rechazado por el guardián no sigue al camino nativo: se explica el motivo.' );
	Library::set_preparer(
		function () {
			return array( 'token' => '../x' );
		}
	);
	$message = '';
	try {
		Library::handle( $request, $upload( $kit_inner ) );
	} catch ( RuntimeException $error ) {
		$message = $error->getMessage();
	}
	$check( 'No se pudo preparar el kit.' === $message, '15: un token con otra forma nunca llega a la URL.' );
	foreach ( glob( $tmp_dir . '/*' ) as $file ) {
		unlink( $file );
	}
	rmdir( $tmp_dir );

	echo "DIGITALÍSIMO Elements: marcador digitalisimo-unconverted (envolver, desenvolver, contadores, render, biblioteca, componentes v4 y conversión sólo en el editor), Template Kits de Envato, fuentes, iconos y medios remotos e intercepción de la biblioteca validados.\n";
}
