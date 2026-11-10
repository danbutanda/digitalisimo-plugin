<?php
/**
 * «Ocultar si falta el shortcode» (`Digitalisimo\Elements\Shortcode_Guard`), con dobles de WordPress y del núcleo.
 *
 * - Arranque: sólo el gancho del control en la sección «Shortcode» del widget Shortcode y el filtro `should_render` de
 *   widgets, después de Visibilidad (prioridad 20); init idempotente.
 * - Control: interruptor sin `default` y con `style_transfer => false` (una instancia existente no cambia), una sola vez
 *   por pila, con textos en español y el dominio `pro-elements`.
 * - Front: con el interruptor encendido, un shortcode no registrado oculta el widget (también el envoltorio); sin el
 *   interruptor, con todos registrados, con el campo vacío o sin shortcodes, en el lienzo del editor, en otro widget o
 *   si ya estaba oculto, no cambia nada. Cubre a las clases que heredan del widget aunque tengan otro nombre, y usa el
 *   valor resuelto (etiquetas dinámicas).
 * - Lectura de etiquetas: aperturas, no cierres ni `[[escapes]]`, sin repetir.
 * - Guarda del paquete: ningún nombre ni prefijo de los productos de origen.
 * Uso: node <php-wasm-cli> tests/elements-shortcode-guard.php
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Controls_Manager { const SWITCHER = 'switcher'; const TEXTAREA = 'textarea'; }
	class Plugin { public static $instance; }
	class Widget_Base {
		public $settings = array();
		public $display  = array();
		public $controls = array();
		public $asked    = array();
		public function __construct( array $settings = array() ) { $this->settings = $settings; }
		public function get_name() { return 'widget'; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) {
			$this->asked[] = null === $key ? '*' : $key;
			$all = array_merge( $this->settings, $this->display );
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function get_controls( $id = null ) { return null === $id ? $this->controls : ( $this->controls[ $id ] ?? null ); }
		public function add_control( $id, $args ) {
			if ( isset( $this->controls[ $id ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $id ); }
			$this->controls[ $id ] = $args;
		}
	}
	class Widget_Shortcode extends Widget_Base { public function get_name() { return 'shortcode'; } }
	class Widget_Heading extends Widget_Base { public function get_name() { return 'heading'; } }
}
namespace Legacy {
	/** Una clase que hereda del widget Shortcode con otro nombre (como un adaptador). */
	class Form_Shortcode extends \Elementor\Widget_Shortcode { public function get_name() { return 'old-form'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	$checks = 0;
	function check_guard( $ok, $message ) { global $checks; if ( ! $ok ) { throw new \RuntimeException( $message ); } $checks++; }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function apply_filters( $hook, $value, ...$args ) {
		$callbacks = $GLOBALS['wp_filters'][ $hook ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
		return $value;
	}
	function hooked( $hook ) { $n = 0; foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entries ) { $n += count( $entries ); } return $n; }
	function __( $text, $domain = 'default' ) { $GLOBALS['domains'][ $domain ] = true; return $text; }
	$GLOBALS['shortcodes'] = array( 'gallery' => true, 'fluentform' => true, 'row' => true );
	function shortcode_exists( $tag ) { return isset( $GLOBALS['shortcodes'][ $tag ] ); }

	$file = $root . '/modules/digitalisimo-widgets/class-shortcode-guard.php';
	require $file;
	$G = '\Digitalisimo\Elements\Shortcode_Guard';
	\Elementor\Plugin::$instance         = new \stdClass();
	\Elementor\Plugin::$instance->editor = new class() { public $edit = false; public function is_edit_mode() { return $this->edit; } };

	// 1. Arranque.
	$G::init();
	$G::init();
	$expected = array( 'elementor/element/shortcode/section_shortcode/before_section_end', 'elementor/frontend/widget/should_render' );
	check_guard( $expected === array_keys( $GLOBALS['wp_filters'] ), 'Sólo engancha el control del widget Shortcode y should_render de widgets: ' . implode( ', ', array_keys( $GLOBALS['wp_filters'] ) ) );
	foreach ( $expected as $hook ) { check_guard( 1 === hooked( $hook ), 'Un solo enganche (init idempotente) en ' . $hook . '.' ); }
	check_guard( isset( $GLOBALS['wp_filters']['elementor/frontend/widget/should_render'][20] ) && 2 === $GLOBALS['wp_filters']['elementor/frontend/widget/should_render'][20][0][1], 'should_render va en la prioridad 20 (después de Visibilidad, en la 10) y recibe el widget.' );
	$code = php_strip_whitespace( $file );
	check_guard( ! preg_match( '/wp_enqueue_|wp_head|wp_footer|the_content|do_shortcode|remove_shortcode|add_shortcode/', $code ), 'Ni recursos, ni ganchos del contenido, ni cambios en los shortcodes del sitio.' );

	// 2. Control.
	$stack = new \Elementor\Widget_Shortcode();
	$G::register_controls( $stack, array() );
	$G::register_controls( $stack, array() );
	$control = $stack->controls[ $G::KEY ] ?? array();
	check_guard( 'digitalisimo_shortcode_if_registered' === $G::KEY && array( $G::KEY ) === array_keys( $stack->controls ), 'Un solo control, digitalisimo_shortcode_if_registered, aunque el gancho llegue dos veces.' );
	check_guard( 'switcher' === $control['type'] && 'yes' === $control['return_value'] && ! array_key_exists( 'default', $control ) && false === $control['style_transfer'], 'Interruptor sin default (ninguna instancia cambia) y que no es de estilo (style_transfer => false).' );
	check_guard( 'Ocultar si falta el shortcode' === $control['label'] && str_contains( $control['description'], 'En el editor siempre se ve' ), 'Etiqueta y descripción en español.' );

	// 3. Lectura de etiquetas.
	check_guard( array( 'contact-form-7' ) === $G::tags( '[contact-form-7 id="21" title="Contacto"]' ), 'La etiqueta termina en el primer espacio.' );
	check_guard( array( 'row', 'col' ) === $G::tags( "[row]\n[col]Hola[/col][col]x[/col]\n[/row]" ), 'Aperturas en orden, sin repetir y sin cierres.' );
	check_guard( array() === $G::tags( '[[gallery]] y [/x]' ) && array() === $G::tags( 'Sin corchetes' ) && array() === $G::tags( '' ) && array() === $G::tags( null ) && array() === $G::tags( array( '[x]' ) ), 'Un [[escape]], un cierre suelto, un texto sin corchetes o un valor que no es texto no abren nada.' );
	check_guard( array( 'ninja_form' ) === $G::tags( 'Antes [ninja_form id=3] después' ) && array( 'a.b' ) === $G::tags( '[a.b/]' ), 'Un shortcode en medio del texto y uno que se cierra solo también cuentan.' );

	// 4. Front.
	$render = function ( $element, $before = true ) { return apply_filters( 'elementor/frontend/widget/should_render', $before, $element ); };
	$missing = array( 'shortcode' => '[contact-form-7 id="21"]', $G::KEY => 'yes' );
	check_guard( false === $render( new \Elementor\Widget_Shortcode( $missing ) ), 'Encendido y con el shortcode sin registrar: no se pinta nada (ni el envoltorio).' );
	check_guard( true === $render( new \Elementor\Widget_Shortcode( array( 'shortcode' => '[contact-form-7 id="21"]' ) ) ) && true === $render( new \Elementor\Widget_Shortcode( array( 'shortcode' => '[contact-form-7 id="21"]', $G::KEY => '' ) ) ), 'Apagado: el widget se pinta como siempre (el núcleo deja el texto del shortcode).' );
	check_guard( true === $render( new \Elementor\Widget_Shortcode( array( 'shortcode' => '[fluentform id="1"]', $G::KEY => 'yes' ) ) ), 'Con el shortcode registrado se pinta.' );
	check_guard( false === $render( new \Elementor\Widget_Shortcode( array( 'shortcode' => '[gallery ids="1"][formidable id=2]', $G::KEY => 'yes' ) ) ), 'Basta un shortcode sin registrar para ocultarlo: su texto saldría en la página.' );
	foreach ( array( '', '   ', 'Texto sin shortcodes', '[[contact-form-7]]', null ) as $value ) {
		check_guard( true === $render( new \Elementor\Widget_Shortcode( array( 'shortcode' => $value, $G::KEY => 'yes' ) ) ), 'Sin shortcodes que abrir (' . json_encode( $value ) . ') no cambia nada.' );
	}
	check_guard( false === $render( new \Elementor\Widget_Shortcode( array( $G::KEY => 'yes' ) ), false ), 'Lo que ya estaba oculto sigue oculto.' );
	$dynamic = new \Elementor\Widget_Shortcode( array( 'shortcode' => '', $G::KEY => 'yes' ) );
	$dynamic->display = array( 'shortcode' => '[weforms id="4"]' );
	check_guard( false === $render( $dynamic ) && array( 'shortcode' ) === $dynamic->asked, 'Usa el valor resuelto (etiqueta dinámica) y sólo resuelve el campo del shortcode.' );
	$off = new \Elementor\Widget_Shortcode( array( 'shortcode' => '[x]' ) );
	$render( $off );
	check_guard( array() === $off->asked, 'Apagado no resuelve nada (coste 0).' );
	check_guard( false === $render( new \Legacy\Form_Shortcode( $missing ) ), 'Una clase que hereda del widget Shortcode con otro nombre también se oculta.' );
	check_guard( true === $render( new \Elementor\Widget_Heading( array( 'shortcode' => '[x]', $G::KEY => 'yes', 'title' => '[x]' ) ) ) && true === $render( new \stdClass() ) && true === $render( null ), 'Otro widget (aunque tenga la misma clave) u otro objeto: no cambia.' );
	\Elementor\Plugin::$instance->editor->edit = true;
	check_guard( true === $render( new \Elementor\Widget_Shortcode( $missing ) ), 'En el lienzo del editor siempre se ve, para poder corregirlo.' );
	\Elementor\Plugin::$instance->editor->edit = false;
	$GLOBALS['shortcodes']['contact-form-7'] = true;
	check_guard( true === $render( new \Elementor\Widget_Shortcode( $missing ) ), 'Al activar el plugin que lo registra, vuelve a pintarse (decide el sitio en cada petición).' );

	// 5. Textos, dominio y guarda del paquete.
	$source = file_get_contents( $file );
	preg_match_all( "/__\(\s*'[^']*',\s*'([a-z-]+)'\s*\)/", $source, $domains );
	check_guard( 2 === count( $domains[1] ) && array( 'pro-elements' ) === array_values( array_unique( $domains[1] ) ), 'Textos traducibles con el dominio pro-elements.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	check_guard( ! preg_match( $guard, $source ), 'Pasa la guarda de menciones del paquete (scripts/build-packages.sh).' );
	check_guard( str_contains( file_get_contents( $root . '/modules/digitalisimo-widgets/class-registry.php' ), "'class-shortcode-guard.php' => Shortcode_Guard::class" ), 'Arranca desde la lista de rasgos de Widget_Registry::init().' );

	echo "DIGITALÍSIMO Elements: «Ocultar si falta el shortcode» validado ({$checks} comprobaciones).\n";
}
