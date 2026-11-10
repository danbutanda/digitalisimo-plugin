<?php
/**
 * F7b, frente B: acciones con destino (`modules/digitalisimo-f7b/actions/`, V.3 de la especificación).
 *
 * - Guardas: sin los símbolos del núcleo, o con `Flags::active( 'actions' )` y `Flags::active( 'hidden' )` falsos, no
 *   se engancha nada más que el estado de las interacciones (que no toca el front).
 * - Controles: sección «Acciones» en la pestaña Avanzado de `common`, contenedor, sección y columna (una sola vez aunque
 *   el núcleo repita el gancho para `common-optimized`), los tres controles con `style_transfer => false`, sin
 *   `default`, con las agujas de `Flags` y la declaración `assets` del script, evaluada con el `Conditions::check()`
 *   real del núcleo; campos del repetidor con las listas cerradas.
 * - Listas cerradas (`Rules`): cada disparador y acción, destinos del documento, clases, atributos, retraso,
 *   animación y topes; descendientes interactivos.
 * - Marcado (`Render::process()`) con HTML real de un contenedor flex, uno grid, una columna, una sección y widgets:
 *   `data-digi-actions`, `role="button" tabindex="0"` sólo sin descendientes interactivos, el único enlace o botón
 *   como control, `click` descartado con varios, `hidden` y `<noscript>` con el `display` de cada tipo.
 * - Ganchos: búferes anidados en el orden correcto, nada en el editor, hoja de la animación, paquetes de PRO y popup
 *   (al pintar y en cada documento impreso, también desde la caché), regla `[hidden]` en el CSS del documento.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Controls_Manager {
		const TAB_ADVANCED = 'advanced';
		const SWITCHER     = 'switcher';
		const REPEATER     = 'repeater';
		const RAW_HTML     = 'raw_html';
		const SELECT       = 'select';
		const TEXT         = 'text';
		const NUMBER       = 'number';
		const ANIMATION    = 'animation';
	}
	class Control_Animation {
		public static function get_animations() {
			return array(
				'Fading'  => array( 'fadeIn' => 'Fade In', 'fadeInUp' => 'Fade In Up' ),
				'Zooming' => array( 'zoomIn' => 'Zoom In' ),
			);
		}
	}
	class Plugin {
		public static $instance;
	}
	/** Pila de controles con secciones, como `Controls_Stack`. */
	class Stack {
		public $controls = array();
		public $sections = array();
		public $section  = null;
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function start_controls_section( $id, array $args ) {
			if ( null !== $this->section ) { throw new \RuntimeException( 'Sección abierta: ' . $this->section ); }
			$this->section         = $id;
			$this->sections[ $id ] = $args;
		}
		public function end_controls_section() { $this->section = null; }
		public function add_control( $name, array $args ) {
			if ( null === $this->section ) { throw new \RuntimeException( 'Control fuera de sección: ' . $name ); }
			if ( isset( $this->controls[ $name ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $name ); }
			$this->controls[ $name ] = $args + array( 'section' => $this->section );
		}
	}
	/** Elemento pintado: ajustes, tipo e id. */
	class Element {
		public $id; public $type; public $settings;
		public function __construct( $id, $type, array $settings ) { $this->id = $id; $this->type = $type; $this->settings = $settings; }
		public function get_id() { return $this->id; }
		public function get_type() { return $this->type; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
	}
}
namespace Digitalisimo\Elements\F7b {
	class Module {
		public static $ok = true;
		public static $calls = array();
		public static function requires( array $symbols, string $who ): bool { self::$calls[] = array( $who, $symbols ); return self::$ok; }
		public static function editing(): bool { return false; }
	}
	class Flags {
		public static $active = array();
		public static function has( string $key ): bool { return ! empty( self::$active[ $key ] ); }
		public static function active( string $key ): bool { return ! empty( self::$active[ $key ] ); }
	}
	class Hidden_Rule {
		const RULE = ':is(.elementor-element,.elementor-column)[hidden]{display:none!important}';
		public static $added = 0;
		public static function add( $post_css ): void { self::$added++; if ( false === strpos( $post_css->css, self::RULE ) ) { $post_css->css .= self::RULE; } }
	}
}
namespace Digitalisimo\Elements\Front {
	class Pro_Gate {
		public static $reasons = array();
		public static function require( string $reason ): void { self::$reasons[] = $reason; }
	}
}
namespace ElementorPro {
	class Plugin {
		public static $theme_builder = true;
		public $modules_manager;
		public static function instance() {
			$plugin                  = new self();
			$plugin->modules_manager = new class() { public function get_modules( $name ) { return 'theme-builder' === $name && Plugin::$theme_builder ? new \stdClass() : null; } };
			return $plugin;
		}
	}
}
namespace ElementorPro\Modules\Popup {
	class Module {
		public static $added = array();
		public static function add_popup_to_location( $popup_id ) { self::$added[] = (int) $popup_id; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$dir  = $root . '/modules/digitalisimo-f7b/actions';

	$GLOBALS['checks'] = 0;
	function check_actions( $ok, $message ) { $GLOBALS['checks']++; if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	$GLOBALS['wp_filters'] = array();
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][] = array( $callback, $args, $priority ); return true; }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); }
	function do_action( $hook, ...$args ) {
		$list = $GLOBALS['wp_filters'][ $hook ] ?? array();
		usort( $list, function ( $a, $b ) { return $a[2] <=> $b[2]; } );
		foreach ( $list as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); }
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); }
		return $value;
	}
	function __( $text, $domain = 'default' ) { $GLOBALS['domains'][ $domain ] = true; return $text; }
	function esc_html__( $text, $domain = 'default' ) { return __( $text, $domain ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function is_admin() { return false; }
	function wp_load_alloptions() { return $GLOBALS['alloptions'] ?? array(); }
	$GLOBALS['styles_registered'] = array( 'e-animation-fadeIn' => true, 'e-animation-zoomIn' => true );
	function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['styles_registered'][ $handle ] ) : in_array( $handle, $GLOBALS['styles_enqueued'] ?? array(), true ); }
	function wp_enqueue_style( $handle ) { $GLOBALS['styles_enqueued'][] = $handle; }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' ); return true; }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts_enqueued'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' ); }
	function wp_add_inline_script( $handle, $code, $position = 'after' ) { $GLOBALS['inline'][ $handle ][ $position ][] = $code; return true; }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	$GLOBALS['posts'] = array(
		77 => array( 'publish', 'popup' ),
		78 => array( 'draft', 'popup' ),
		79 => array( 'publish', 'page' ),
	);
	function get_post_status( $id ) { return $GLOBALS['posts'][ $id ][0] ?? false; }
	function get_post_meta( $id, $key, $single = false ) { return '_elementor_template_type' === $key ? ( $GLOBALS['posts'][ $id ][1] ?? '' ) : ''; }

	require $root . '/elementor/includes/conditions.php';
	foreach ( array( 'rules', 'controls', 'render', 'interactions-state', 'module' ) as $file ) {
		require $dir . '/class-' . $file . '.php';
	}
	use Digitalisimo\Elements\F7b\Actions\Controls;
	use Digitalisimo\Elements\F7b\Actions\Interactions_State;
	use Digitalisimo\Elements\F7b\Actions\Module;
	use Digitalisimo\Elements\F7b\Actions\Render;
	use Digitalisimo\Elements\F7b\Actions\Rules;
	use Digitalisimo\Elements\F7b\Flags;
	use Digitalisimo\Elements\F7b\Module as F7b;

	$hooks = function () { return array_keys( $GLOBALS['wp_filters'] ); };
	$priority = function ( $hook ) { return array_map( function ( $e ) { return $e[2]; }, $GLOBALS['wp_filters'][ $hook ] ?? array() ); };

	// -------------------------------------------------------------------------------------------------------------
	// 1. Guardas y banderas.
	Flags::$active = array();
	Module::init();
	check_actions( array( 'digitalisimo_elements_f7b_scan_document', 'digitalisimo_elements_f7b_scan_done' ) === $hooks(), 'Con los contadores a 0 sólo el inventario de interacciones (cron): ' . implode( ', ', $hooks() ) );
	check_actions( 'actions' === F7b::$calls[0][0] && in_array( 'Elementor\Controls_Manager', F7b::$calls[0][1], true ), 'La guarda se pide con el nombre del submódulo y los símbolos del núcleo.' );
	Module::reset();
	Interactions_State::reset();
	$GLOBALS['wp_filters'] = array();
	Flags::$active        = array( 'actions' => true, 'hidden' => true );
	F7b::$ok              = false;
	Module::init();
	check_actions( array( 'digitalisimo_elements_f7b_scan_document', 'digitalisimo_elements_f7b_scan_done' ) === $hooks(), 'Sin los símbolos no se registra nada más.' );
	Module::reset();
	Interactions_State::reset();
	$GLOBALS['wp_filters'] = array();
	F7b::$ok              = true;
	Flags::$active        = array( 'hidden' => true );
	Module::init();
	check_actions( ! in_array( 'elementor/frontend/builder_content_data', $hooks(), true ) && ! in_array( 'elementor/frontend/after_register_scripts', $hooks(), true ) && in_array( 'elementor/frontend/before_render', $hooks(), true ), 'Sólo «Oculto al cargar»: render y controles, sin script ni popups.' );
	Module::reset();
	Interactions_State::reset();
	$GLOBALS['wp_filters'] = array();
	Flags::$active        = array( 'actions' => true, 'hidden' => true );
	Module::init();
	Module::init();
	foreach ( Controls::STACKS as $stack ) {
		check_actions( 1 === count( $GLOBALS['wp_filters'][ "elementor/element/{$stack}/_section_responsive/after_section_end" ] ?? array() ), 'Un gancho de controles en ' . $stack );
	}
	check_actions( empty( $GLOBALS['wp_filters']['elementor/element/common-optimized/_section_responsive/after_section_end'] ), 'Nunca common-optimized (el núcleo repite common).' );
	check_actions( array( Render::OPEN ) === $priority( 'elementor/frontend/before_render' ) && array( Render::CLOSE ) === $priority( 'elementor/frontend/after_render' ) && 5 === Render::OPEN && 15 === Render::CLOSE, 'Búfer abierto antes (5) y cerrado después (15) que los de prioridad 10.' );
	foreach ( array( 'elementor/element/parse_css', 'elementor/frontend/builder_content_data', 'elementor/frontend/after_register_scripts', 'elementor/editor/after_enqueue_scripts' ) as $hook ) {
		check_actions( 1 === count( $GLOBALS['wp_filters'][ $hook ] ?? array() ), 'Un gancho: ' . $hook );
	}
	check_actions( ! in_array( 'elementor/element/is_dynamic_content', $hooks(), true ), 'Ninguna acción hace dinámico el elemento (C1-21).' );

	// Scripts.
	do_action( 'elementor/frontend/after_register_scripts' );
	$script = $GLOBALS['scripts'][ Module::HANDLE ] ?? array();
	check_actions( isset( $script['src'] ) && str_ends_with( $script['src'], '/modules/digitalisimo-f7b/assets/js/actions.js' ) && array( 'elementor-frontend' ) === $script['deps'] && 'test' === $script['ver'], 'actions.js registrado con su ruta, versión y elementor-frontend.' );
	check_actions( array( 'in_footer' => true, 'strategy' => 'defer' ) === $script['args'], 'En el pie y con defer.' );
	check_actions( empty( $GLOBALS['scripts_enqueued'] ), 'actions.js nunca se encola a mano: lo encola la declaración assets.' );
	do_action( 'elementor/editor/after_enqueue_scripts' );
	$editor = $GLOBALS['scripts_enqueued'][ Module::EDITOR_HANDLE ] ?? array();
	check_actions( isset( $editor['src'] ) && str_ends_with( $editor['src'], '/modules/digitalisimo-f7b/assets/js/actions-editor.js' ) && array( 'elementor-editor' ) === $editor['deps'], 'actions-editor.js sólo en el editor.' );
	$texts = $GLOBALS['inline'][ Module::EDITOR_HANDLE ]['before'][0] ?? '';
	check_actions( 0 === strpos( $texts, 'window.digitalisimoActionsTexts=' ) && false !== strpos( $texts, 'Elige un elemento' ), 'Textos del editor traducibles, en línea antes del script.' );

	// -------------------------------------------------------------------------------------------------------------
	// 2. Controles.
	$common = new \Elementor\Stack();
	do_action( 'elementor/element/common/_section_responsive/after_section_end', $common, array() );
	do_action( 'elementor/element/common/_section_responsive/after_section_end', $common, array() );
	check_actions( array( Controls::SECTION ) === array_keys( $common->sections ) && 'advanced' === $common->sections[ Controls::SECTION ]['tab'] && 'Acciones' === $common->sections[ Controls::SECTION ]['label'], 'Una sección «Acciones» en Avanzado aunque el gancho se repita.' );
	check_actions( array( Controls::HIDDEN, Controls::ON, Controls::LIST, Controls::NOTE ) === array_keys( $common->controls ), 'Oculto al cargar, interruptor, repetidor y nota, en ese orden.' );
	foreach ( array( Controls::HIDDEN, Controls::ON, Controls::LIST ) as $name ) {
		$control = $common->controls[ $name ];
		check_actions( false === $control['style_transfer'], 'style_transfer false en ' . $name . ' (V.12, C2-16).' );
		check_actions( ! array_key_exists( 'default', $control ) && empty( $control['selectors'] ) && empty( $control['prefix_class'] ) && empty( $control['frontend_available'] ), 'Sin default, selectores, clase ni data-settings: ' . $name );
		check_actions( 'none' === $control['render_type'], 'El editor no vuelve a pintar el elemento al cambiar ' . $name );
	}
	check_actions( 'switcher' === $common->controls[ Controls::HIDDEN ]['type'] && 'yes' === $common->controls[ Controls::HIDDEN ]['return_value'], 'Aguja de Flags: "_digitalisimo_hidden_initial":"yes".' );
	check_actions( 'switcher' === $common->controls[ Controls::ON ]['type'] && 'yes' === $common->controls[ Controls::ON ]['return_value'], 'Aguja de Flags: "_digitalisimo_actions_on":"yes".' );
	check_actions( '"_digitalisimo_actions_on":"yes"' === substr( json_encode( array( Controls::ON => 'yes' ) ), 1, -1 ) && '"_digitalisimo_hidden_initial":"yes"' === substr( json_encode( array( Controls::HIDDEN => 'yes' ) ), 1, -1 ), 'Las claves guardadas casan con las agujas.' );
	$assets = $common->controls[ Controls::ON ]['assets']['scripts'] ?? array();
	check_actions( array( 'scripts' ) === array_keys( $common->controls[ Controls::ON ]['assets'] ) && 1 === count( $assets ) && Module::HANDLE === $assets[0]['name'], 'El interruptor declara actions.js en assets (C1-11).' );
	foreach ( array( 'yes' => true, '' => false ) as $value => $expected ) {
		check_actions( $expected === \Elementor\Conditions::check( $assets[0]['conditions'], array( Controls::ON => $value ) ), 'actions.js con el interruptor en «' . $value . '»: ' . ( $expected ? 'sí' : 'no' ) );
	}
	$list = $common->controls[ Controls::LIST ];
	check_actions( 'repeater' === $list['type'] && array( Controls::ON => 'yes' ) === $list['condition'] && false === $list['prevent_empty'], 'Repetidor visible sólo con el interruptor y que puede quedar vacío.' );
	$fields = $list['fields'];
	check_actions( array( 'trigger', 'action', 'target_kind', 'target', 'popup_id', 'class_name', 'attribute', 'attribute_value', 'delay', 'animation', 'once' ) === array_keys( $fields ), 'Campos del repetidor.' );
	foreach ( $fields as $name => $field ) {
		check_actions( false === $field['style_transfer'] && $name === $field['name'], 'style_transfer false en el campo ' . $name );
	}
	check_actions( Rules::TRIGGERS === array_keys( $fields['trigger']['options'] ) && 'click' === $fields['trigger']['default'], 'Disparadores de la lista cerrada.' );
	check_actions( Rules::ACTIONS === array_keys( $fields['action']['options'] ) && 'show' === $fields['action']['default'], 'Acciones de la lista cerrada.' );
	check_actions( array_keys( Rules::ATTRIBUTES ) === array_keys( $fields['attribute']['options'] ), 'Atributos de la lista cerrada (C2-7).' );
	check_actions( array( '' ) === array_keys( $fields['target']['options'] ) && array( 'action' => Rules::TARGETED, 'target_kind' => 'element' ) === $fields['target']['condition'], 'El destino lo llena el editor con el modelo del documento.' );
	check_actions( 'number' === $fields['popup_id']['type'] && array( 'action' => 'popup_open' ) === $fields['popup_id']['condition'], 'Sin el control de consulta de PRO, el popup es un número.' );
	check_actions( Rules::MAX_DELAY === $fields['delay']['max'] && array( 'trigger' => 'load' ) === $fields['delay']['condition'], 'Retraso de 0 a 10 000 ms, sólo al cargar.' );
	check_actions( 'animation' === $fields['animation']['type'] && array( 'action' => Rules::ANIMATED ) === $fields['animation']['condition'], 'Animación de entrada del núcleo.' );
	check_actions( false !== strpos( $list['title_field'], '{{{ t[trigger] || t.click }}}' ) && false !== strpos( $list['title_field'], 'Mostrar u ocultar' ), 'Título de fila con los textos de las listas.' );
	check_actions( isset( $GLOBALS['domains']['pro-elements'] ) && 1 === count( $GLOBALS['domains'] ), 'Textos traducibles con el dominio de los módulos propios.' );
	$texts = array( $common->sections[ Controls::SECTION ]['label'], $common->controls[ Controls::NOTE ]['raw'] );
	foreach ( array( Controls::HIDDEN, Controls::ON ) as $name ) {
		$texts[] = $common->controls[ $name ]['label'];
		$texts[] = $common->controls[ $name ]['description'] ?? '';
	}
	foreach ( $fields as $field ) {
		$texts[] = $field['label'];
		$texts[] = $field['description'] ?? '';
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) { $texts[] = $option; }
	}
	foreach ( array_filter( $texts ) as $text ) {
		check_actions( false === stripos( $text, 'elementor' ) && false === stripos( $text, 'bricks' ), 'Texto visible sin nombres de proveedores: ' . $text );
	}
	check_actions( false !== strpos( $common->controls[ Controls::HIDDEN ]['description'], '4.3.0.169' ) && false !== strpos( $common->controls[ Controls::NOTE ]['raw'], '4.3.0.169' ), 'Aviso de la vuelta atrás junto a los controles (V.11, C1-15).' );
	foreach ( array( 'container', 'section', 'column' ) as $stack ) {
		$el = new \Elementor\Stack();
		do_action( "elementor/element/{$stack}/_section_responsive/after_section_end", $el, array() );
		check_actions( isset( $el->controls[ Controls::LIST ], $el->controls[ Controls::HIDDEN ] ), 'Controles en ' . $stack );
	}

	// -------------------------------------------------------------------------------------------------------------
	// 3. Listas cerradas.
	$ids  = array( 'abc1234' => true, 'tgt5678' => true, 'off9999' => true, 'grid111' => true );
	$row  = function ( array $row, ?array $doc = null ) use ( $ids ) { return Rules::row( $row, 'self000', func_num_args() > 1 ? $doc : $ids ); };
	check_actions( array( 't' => 'click', 'a' => 'show', 'g' => 'tgt5678' ) === $row( array( 'target' => 'tgt5678' ) ), 'Sin disparador ni acción guardados (valores por defecto): clic y mostrar.' );
	check_actions( array( 't' => 'hover', 'a' => 'toggle', 'g' => 'self000', 'o' => 1 ) === $row( array( 'trigger' => 'hover', 'action' => 'toggle', 'target_kind' => 'self', 'once' => 'yes' ) ), '«Este elemento» usa su propio ID.' );
	check_actions( null === $row( array( 'target' => 'nadie00' ) ), 'Un destino que no está en el documento no se registra (falla cerrada).' );
	check_actions( array( 't' => 'click', 'a' => 'show', 'g' => 'nadie00' ) === $row( array( 'target' => 'nadie00' ), null ), 'Sin la lista del documento, sólo la forma.' );
	foreach ( array( '', 'a b', '../x', 'x"y', str_repeat( 'a', 25 ) ) as $bad ) {
		check_actions( null === Rules::row( array( 'target' => $bad ), 'self000', null ), 'ID de destino inválido: ' . $bad );
	}
	check_actions( null === $row( array( 'trigger' => 'dblclick' ) ) && null === $row( array( 'action' => 'eval', 'target' => 'tgt5678' ) ) && null === $row( array( 'action' => 'set_style', 'target' => 'tgt5678' ) ), 'Disparador o acción fuera de la lista.' );
	foreach ( array( 'scroll_in', 'load' ) as $trigger ) {
		foreach ( array( 'show', 'hide', 'toggle', 'load_more' ) as $action ) {
			check_actions( null === $row( array( 'trigger' => $trigger, 'action' => $action, 'target' => 'tgt5678' ) ), $trigger . ' no admite ' . $action . ' (cambia el flujo, C2-5).' );
		}
		foreach ( Rules::FLOW_FREE as $action ) {
			$ok = $row( array( 'trigger' => $trigger, 'action' => $action, 'target' => 'tgt5678', 'class_name' => 'abierto', 'attribute' => 'aria-pressed', 'attribute_value' => 'true', 'popup_id' => 77 ) );
			check_actions( is_array( $ok ) && $action === $ok['a'], $trigger . ' admite ' . $action );
		}
	}
	check_actions( null === $row( array( 'trigger' => 'hover', 'action' => 'toggle', 'target' => 'zzz' ) ), 'toggle sólo con destino válido.' );
	foreach ( array( 'abierto', 'menu_open', 'is-1', str_repeat( 'a', 40 ) ) as $class ) {
		check_actions( $class === ( $row( array( 'action' => 'toggle_class', 'class_name' => $class, 'target' => 'tgt5678' ) )['c'] ?? null ), 'Clase admitida: ' . $class );
	}
	foreach ( array( 'elementor-hidden', 'e-con', 'digi-x', 'wp-block', 'Mayus', 'a b', '', str_repeat( 'a', 41 ), 'x"onload' ) as $class ) {
		check_actions( null === $row( array( 'action' => 'toggle_class', 'class_name' => $class, 'target' => 'tgt5678' ) ), 'Clase rechazada: ' . $class );
	}
	$attrs = array(
		array( 'aria-pressed', 'true', true ), array( 'aria-pressed', 'mixed', true ), array( 'aria-pressed', 'yes', false ),
		array( 'aria-current', 'page', true ), array( 'aria-current', 'x', false ),
		array( 'aria-label', 'Abrir el menú «principal»', true ), array( 'aria-label', 'a<b', false ), array( 'aria-label', str_repeat( 'á', 151 ), false ),
		array( 'data-digi-state', 'abierto-1', true ), array( 'data-digi-state', 'Abierto', false ),
		array( 'hidden', '', false ), array( 'aria-expanded', 'true', false ), array( 'aria-hidden', 'true', false ), array( 'aria-live', 'polite', false ),
		array( 'data-e-action-hash', '#x', false ), array( 'data-settings', '{}', false ), array( 'data-digi-actions', '[]', false ), array( 'onclick', 'x()', false ),
		array( 'href', 'javascript:x', false ), array( 'class', 'x', false ), array( 'role', 'dialog', false ),
	);
	foreach ( $attrs as list( $name, $value, $expected ) ) {
		$got = $row( array( 'action' => 'set_attribute', 'attribute' => $name, 'attribute_value' => $value, 'target' => 'tgt5678' ) );
		check_actions( $expected === is_array( $got ) && ( ! $expected || ( $name === $got['n'] && $value === $got['v'] ) ), 'set_attribute ' . $name . '=' . $value . ': ' . ( $expected ? 'admitido' : 'rechazado' ) );
	}
	check_actions( array( 't' => 'click', 'a' => 'popup_open', 'p' => 77 ) === $row( array( 'action' => 'popup_open', 'popup_id' => '77' ) ), 'popup_open con el ID del popup, sin destino.' );
	foreach ( array( 0, -1, 'abc', '7.5', array( 7 ) ) as $bad ) {
		check_actions( null === $row( array( 'action' => 'popup_open', 'popup_id' => $bad ) ), 'popup_open sin un ID válido no se registra.' );
	}
	check_actions( array( 't' => 'click', 'a' => 'popup_close' ) === $row( array( 'action' => 'popup_close', 'target' => 'tgt5678' ) ), 'popup_close no lleva destino.' );
	check_actions( array( 't' => 'load', 'a' => 'scroll_to', 'g' => 'tgt5678', 'd' => 10000 ) === $row( array( 'trigger' => 'load', 'action' => 'scroll_to', 'target' => 'tgt5678', 'delay' => 20000 ) ), 'Retraso con tope de 10 000 ms.' );
	check_actions( ! isset( $row( array( 'trigger' => 'load', 'action' => 'scroll_to', 'target' => 'tgt5678', 'delay' => -5 ) )['d'] ), 'Retraso negativo: 0.' );
	check_actions( ! isset( $row( array( 'action' => 'show', 'target' => 'tgt5678', 'delay' => 500 ) )['d'] ), 'El retraso sólo vale con «al cargar».' );
	check_actions( 'fadeIn' === ( $row( array( 'action' => 'show', 'target' => 'tgt5678', 'animation' => 'fadeIn' ) )['m'] ?? '' ), 'Animación de la lista del núcleo.' );
	foreach ( array( 'none', '', 'noExiste', 'fade In', 'x;y' ) as $bad ) {
		check_actions( ! isset( $row( array( 'action' => 'show', 'target' => 'tgt5678', 'animation' => $bad ) )['m'] ), 'Animación descartada: ' . $bad );
	}
	check_actions( ! isset( $row( array( 'action' => 'hide', 'target' => 'tgt5678', 'animation' => 'fadeIn' ) )['m'] ), 'Ocultar no anima.' );
	check_actions( ! isset( $row( array( 'trigger' => 'scroll_in', 'action' => 'scroll_to', 'target' => 'tgt5678', 'once' => 'yes' ) )['o'] ), '«Sólo la primera vez» sólo con clic o puntero.' );
	$many = Rules::rows( array_fill( 0, 30, array( 'target' => 'tgt5678' ) ), 'self000', $ids );
	check_actions( Rules::MAX_ROWS === count( $many ), 'Como mucho 20 filas.' );
	check_actions( array() === Rules::rows( 'x', 'self000' ) && array() === Rules::rows( array( array() ), 'bad id' ), 'Valores que no son filas, o elemento con ID inválido: nada.' );
	check_actions( Rules::needs_pro( array( array( 'a' => 'offcanvas_toggle' ) ) ) && ! Rules::needs_pro( array( array( 'a' => 'show' ) ) ), 'needs_pro().' );
	check_actions( Rules::needs_hidden_rule( array( array( 'a' => 'toggle' ) ) ) && ! Rules::needs_hidden_rule( array( array( 'a' => 'toggle_class' ) ) ), 'needs_hidden_rule().' );

	// Descendientes interactivos.
	$cases = array(
		''                                                       => array( 0, '' ),
		'<h2 class="t">Título</h2><img src="x.jpg" alt="">'      => array( 0, '' ),
		'<h2><a href="/x">Título</a></h2>'                       => array( 1, 'a' ),
		'<a class="elementor-button" role="button">Botón</a>'     => array( 1, 'a' ),
		'<button type="button">B</button>'                       => array( 1, 'button' ),
		'<a href="/a">a</a><a href="/b">b</a>'                    => array( 2, 'a' ),
		'<input type="hidden" name="x"><input type="text">'      => array( 1, 'input' ),
		'<INPUT TYPE=HIDDEN>'                                    => array( 0, '' ),
		'<video src="v.mp4" muted></video>'                       => array( 0, '' ),
		'<video src="v.mp4" controls></video>'                    => array( 1, 'video' ),
		'<div tabindex="0">x</div>'                               => array( 1, 'div' ),
		'<div tabindex="-1">x</div>'                              => array( 0, '' ),
		'<p contenteditable>x</p>'                                => array( 1, 'p' ),
		'<p contenteditable="false">x</p>'                        => array( 0, '' ),
		'<div data-href="/x" data-controls="1" data-tabindex="0">x</div>' => array( 0, '' ),
		'<script>var a="<a href=x>";</script><style>a{}</style>' => array( 0, '' ),
		'<!-- <button>x</button> --><span>y</span>'               => array( 0, '' ),
		'<noscript><a href="/x">x</a></noscript>'                 => array( 0, '' ),
		'<select><option>1</option></select><textarea><a href=x></textarea>' => array( 2, 'select' ),
		'<details><summary>Más</summary>texto</details>'          => array( 1, 'summary' ),
		'<iframe src="x"></iframe>'                               => array( 1, 'iframe' ),
	);
	foreach ( $cases as $html => list( $count, $tag ) ) {
		$found = Rules::analyze( $html );
		check_actions( $count === $found['count'] && $tag === $found['tag'], 'Interactivos en ' . $html . ': ' . $found['count'] );
	}

	// -------------------------------------------------------------------------------------------------------------
	// 4. Marcado con HTML real.
	$flex   = '<div class="elementor-element elementor-element-abc1234 e-flex e-con-boxed e-con e-parent" data-id="abc1234" data-element_type="container" data-settings="{&quot;content_width&quot;:&quot;boxed&quot;}"><div class="e-con-inner"><div class="elementor-element elementor-element-h111111 elementor-widget elementor-widget-heading" data-id="h111111" data-element_type="widget" data-widget_type="heading.default"><h2 class="elementor-heading-title elementor-size-default">Hola</h2></div></div></div>';
	$grid   = '<div class="elementor-element elementor-element-grid111 e-grid e-con-full e-con e-child" data-id="grid111" data-element_type="container"><div class="elementor-element elementor-element-l1 elementor-widget elementor-widget-text-editor" data-id="l1" data-element_type="widget" data-widget_type="text-editor.default"><p>Texto</p></div></div>';
	$column = '<div class="elementor-column elementor-col-50 elementor-top-column elementor-element elementor-element-col1234" data-id="col1234" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"></div></div>';
	$button = '<div class="elementor-element elementor-element-btn1234 elementor-widget elementor-widget-button" data-id="btn1234" data-element_type="widget" data-widget_type="button.default"><div class="elementor-button-wrapper"><a class="elementor-button elementor-size-sm" role="button"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">Ver más</span></span></a></div></div>';
	$link   = '<div class="elementor-element elementor-element-hed1234 elementor-widget elementor-widget-heading" data-id="hed1234" data-element_type="widget" data-widget_type="heading.default"><h2 class="elementor-heading-title elementor-size-default"><a href="https://ejemplo.test/">Título</a></h2></div>';
	$two    = '<div class="elementor-element elementor-element-two1234 e-flex e-con-full e-con e-parent" data-id="two1234" data-element_type="container"><a href="/a">a</a><a href="/b">b</a></div>';
	$show   = array( 't' => 'click', 'a' => 'show', 'g' => 'tgt5678' );
	$hover  = array( 't' => 'hover', 'a' => 'show', 'g' => 'tgt5678' );
	$attr   = function ( $html ) { return preg_match( '/data-digi-actions="([^"]*)"/', $html, $m ) ? json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true ) : null; };
	$open   = function ( $html ) { return substr( $html, 0, strpos( $html, '>' ) + 1 ); };

	$out = Render::process( $flex, array( 'id' => 'abc1234', 'type' => 'container', 'rows' => array( $show ), 'hidden' => false ) );
	check_actions( false !== strpos( $open( $out ), ' role="button" tabindex="0" data-digi-actions="' ) && array( $show ) === $attr( $out ), 'Contenedor sin descendientes interactivos: se convierte en botón y lleva las filas.' );
	check_actions( str_replace( $open( $out ), $open( $flex ), $out ) === $flex, 'Sólo cambia la etiqueta de apertura del envoltorio.' );
	$out = Render::process( $link, array( 'id' => 'hed1234', 'type' => 'widget', 'rows' => array( $show ), 'hidden' => false ) );
	check_actions( false === strpos( $out, 'role="button"' ) && array( $show ) === $attr( $out ), 'Encabezado con enlace: el disparador es el enlace; sin role.' );
	$out = Render::process( $button, array( 'id' => 'btn1234', 'type' => 'widget', 'rows' => array( $show ), 'hidden' => false ) );
	check_actions( false !== strpos( $out, '<a tabindex="0" class="elementor-button elementor-size-sm" role="button">' ) && 1 === substr_count( $out, 'role="button"' ) && false === strpos( $open( $out ), 'role=' ), 'Botón sin enlace: el «a» se hace enfocable, sin un segundo role ni botón en el envoltorio.' );
	$out = Render::process( str_replace( ' role="button"', '', $button ), array( 'id' => 'btn1234', 'type' => 'widget', 'rows' => array( $show ), 'hidden' => false ) );
	check_actions( false !== strpos( $out, '<a tabindex="0" role="button" class="elementor-button' ), 'Un «a» sin href ni role recibe tabindex y role.' );
	$out = Render::process( $two, array( 'id' => 'two1234', 'type' => 'container', 'rows' => array( $show ), 'hidden' => false ) );
	check_actions( $out === $two, 'Con varios descendientes interactivos, «click» no se registra: el HTML no cambia.' );
	$out = Render::process( $two, array( 'id' => 'two1234', 'type' => 'container', 'rows' => array( $show, $hover ), 'hidden' => false ) );
	check_actions( array( $hover ) === $attr( $out ) && false === strpos( $out, 'role=' ), 'Con varios, «hover» sigue (foco dentro) y no hay role.' );
	$as_link = '<a class="elementor-element elementor-element-lnk1234 e-flex e-con e-parent" data-id="lnk1234" data-element_type="container" href="/x"><div class="e-con-inner"><span>x</span></div></a>';
	$out     = Render::process( $as_link, array( 'id' => 'lnk1234', 'type' => 'container', 'rows' => array( $show ), 'hidden' => false ) );
	check_actions( false === strpos( $out, 'role=' ) && array( $show ) === $attr( $out ), 'Contenedor como enlace: el propio enlace dispara.' );
	$with_role = str_replace( 'data-id="abc1234"', 'data-id="abc1234" role="region"', $flex );
	$out       = Render::process( $with_role, array( 'id' => 'abc1234', 'type' => 'container', 'rows' => array( $show ), 'hidden' => false ) );
	check_actions( $out === $with_role, 'Un envoltorio con su propio role no se convierte y «click» no se registra.' );
	$out = Render::process( $flex, array( 'id' => 'abc1234', 'type' => 'container', 'rows' => array( array( 't' => 'scroll_in', 'a' => 'toggle_class', 'g' => 'tgt5678', 'c' => 'visto' ) ), 'hidden' => false ) );
	check_actions( false === strpos( $out, 'role=' ) && 'visto' === $attr( $out )[0]['c'], 'Sin clic ni puntero, el envoltorio no se convierte en botón.' );
	$label = array( 't' => 'click', 'a' => 'set_attribute', 'g' => 'tgt5678', 'n' => 'aria-label', 'v' => 'Abrir "menú" & <ver>' );
	$out   = Render::process( $flex, array( 'id' => 'abc1234', 'type' => 'container', 'rows' => array( $label ), 'hidden' => false ) );
	check_actions( array( $label ) === $attr( $out ) && false === strpos( $open( $out ), '<ver>' ), 'El JSON va escapado en el atributo.' );

	// «Oculto al cargar»: `hidden` y el <noscript> como último hijo, con el display de cada tipo.
	foreach ( array( array( $flex, 'abc1234', 'container', 'var(--display)' ), array( $grid, 'grid111', 'container', 'var(--display)' ), array( $column, 'col1234', 'column', 'flex' ), array( $link, 'hed1234', 'widget', 'block' ) ) as list( $html, $id, $type, $display ) ) {
		$out      = Render::process( $html, array( 'id' => $id, 'type' => $type, 'rows' => array(), 'hidden' => true ) );
		$noscript = '<noscript><style>.elementor-element.elementor-element-' . $id . '[hidden]{display:' . $display . '!important}</style></noscript>';
		check_actions( false !== strpos( $open( $out ), ' hidden>' ) && substr( $out, -strlen( $noscript . '</div>' ) ) === $noscript . '</div>', 'Oculto al cargar en ' . $id . ': hidden y <noscript> con display ' . $display );
		check_actions( strlen( $noscript ) <= 130, '<noscript> de ≈ 130 B: ' . strlen( $noscript ) );
	}
	$section = '<section class="elementor-section elementor-top-section elementor-element elementor-element-sec1234" data-id="sec1234" data-element_type="section"><div class="elementor-container elementor-column-gap-default"></div></section>';
	$out     = Render::process( $section, array( 'id' => 'sec1234', 'type' => 'section', 'rows' => array(), 'hidden' => true ) );
	check_actions( '</noscript></section>' === substr( $out, -21 ) && false !== strpos( $out, '{display:block!important}' ), 'Sección: display block y cierre </section>.' );
	$already = str_replace( 'data-id="col1234"', 'data-id="col1234" hidden', $column );
	check_actions( 1 === substr_count( Render::process( $already, array( 'id' => 'col1234', 'type' => 'column', 'rows' => array(), 'hidden' => true ) ), ' hidden' ), 'No se repite hidden.' );
	check_actions( 'nada' === Render::process( 'nada', array( 'id' => 'abc1234', 'type' => 'container', 'rows' => array( $show ), 'hidden' => true ) ) && '' === Render::process( '', array( 'id' => 'abc1234', 'rows' => array(), 'hidden' => true ) ), 'Sin el envoltorio (elemento que no se pinta), el HTML no cambia.' );

	// -------------------------------------------------------------------------------------------------------------
	// 5. Ganchos de render.
	$documents = new class() {
		public $current;
		public function get_current() { return $this->current; }
	};
	$documents->current = new class() {
		public function get_main_id() { return 10; }
		public function get_post() { return (object) array( 'ID' => 10 ); }
		public function get_elements_data() { return array( array( 'id' => 'abc1234', 'elements' => array( array( 'id' => 'tgt5678', 'elements' => array() ), array( 'id' => 'off9999' ) ) ) ); }
	};
	\Elementor\Plugin::$instance = (object) array(
		'documents' => $documents,
		'editor'    => new class() { public $edit = false; public function is_edit_mode() { return $this->edit; } },
		'preview'   => new class() { public function is_preview_mode() { return false; } },
	);
	Render::reset();
	$level  = ob_get_level();
	$outer  = new \Elementor\Element( 'abc1234', 'container', array( Controls::ON => 'yes', Controls::LIST => array( array( 'target' => 'tgt5678', 'animation' => 'fadeIn' ), array( 'action' => 'popup_open', 'popup_id' => 77 ), array( 'action' => 'popup_open', 'popup_id' => 78 ), array( 'target' => 'fuera00' ) ) ) );
	$inner  = new \Elementor\Element( 'tgt5678', 'widget', array( Controls::HIDDEN => 'yes' ) );
	$plain  = new \Elementor\Element( 'h111111', 'widget', array() );
	$wrap   = function ( $el, $body ) { do_action( 'elementor/frontend/before_render', $el ); echo '<div class="elementor-element elementor-element-' . $el->id . '" data-id="' . $el->id . '">' . $body . '</div>'; do_action( 'elementor/frontend/after_render', $el ); };
	ob_start();
	$wrap(
		$outer,
		( function () use ( $wrap, $inner, $plain ) {
			ob_start();
			$wrap( $inner, '<p>Contenido</p>' );
			$wrap( $plain, '<p>Otro</p>' );
			return ob_get_clean();
		} )()
	);
	$html = ob_get_clean();
	check_actions( $level === ob_get_level(), 'Los búferes se cierran todos.' );
	check_actions( array( array( 't' => 'click', 'a' => 'show', 'g' => 'tgt5678', 'm' => 'fadeIn' ), array( 't' => 'click', 'a' => 'popup_open', 'p' => 77 ), array( 't' => 'click', 'a' => 'popup_open', 'p' => 78 ) ) === $attr( $html ), 'Filas del documento (el destino de fuera se descarta).' );
	check_actions( false !== strpos( $html, 'data-id="tgt5678" hidden>' ) && false !== strpos( $html, 'elementor-element-tgt5678[hidden]{display:block!important}' ), 'El destino anidado sale oculto con su <noscript>.' );
	check_actions( false !== strpos( $html, '<div class="elementor-element elementor-element-h111111" data-id="h111111"><p>Otro</p></div>' ), 'Un elemento sin acciones queda igual.' );
	check_actions( in_array( 'e-animation-fadeIn', $GLOBALS['styles_enqueued'] ?? array(), true ), 'La hoja de la animación se encola al pintar (la caché del documento la guarda).' );
	check_actions( array( 'f7b-action' ) === array_values( array_unique( \Digitalisimo\Elements\Front\Pro_Gate::$reasons ) ) && array( 77 ) === \ElementorPro\Modules\Popup\Module::$added, 'Popup: paquetes de PRO y sólo el popup publicado en su ubicación (C1-6).' );
	\Elementor\Plugin::$instance->editor->edit = true;
	ob_start();
	$wrap( $outer, '' );
	$html = ob_get_clean();
	check_actions( false === strpos( $html, 'data-digi-actions' ) && $level === ob_get_level(), 'En el editor no cambia nada.' );
	\Elementor\Plugin::$instance->editor->edit = false;

	// En cada documento impreso, también desde la caché de elementos.
	\Digitalisimo\Elements\Front\Pro_Gate::$reasons = array();
	\ElementorPro\Modules\Popup\Module::$added       = array();
	$data = array( array( 'id' => 'a1', 'elements' => array( array( 'id' => 'b2', 'settings' => array( Controls::ON => 'yes', Controls::LIST => array( array( 'action' => 'popup_open', 'popup_id' => '77' ), array( 'action' => 'popup_open', 'popup_id' => 79 ) ) ) ) ) ) );
	check_actions( $data === apply_filters( 'elementor/frontend/builder_content_data', $data, 5 ), 'builder_content_data devuelve los mismos datos.' );
	check_actions( array( 'f7b-action' ) === \Digitalisimo\Elements\Front\Pro_Gate::$reasons && array( 77 ) === \ElementorPro\Modules\Popup\Module::$added, 'Documento impreso: PRO y el popup, nunca una página en la ubicación de popups.' );
	apply_filters( 'elementor/frontend/builder_content_data', $data, 5 );
	check_actions( 1 === count( \Digitalisimo\Elements\Front\Pro_Gate::$reasons ), 'Una vez por documento y petición.' );
	$quiet = array( array( 'id' => 'c3', 'settings' => array( Controls::ON => 'yes', Controls::LIST => array( array( 'target' => 'c3x', 'action' => 'toggle_class', 'class_name' => 'x' ) ) ) ) );
	apply_filters( 'elementor/frontend/builder_content_data', $quiet, 6 );
	check_actions( 1 === count( \Digitalisimo\Elements\Front\Pro_Gate::$reasons ), 'Sin popups ni paneles no se piden los paquetes.' );
	\ElementorPro\Plugin::$theme_builder = false;
	apply_filters( 'elementor/frontend/builder_content_data', $data, 7 );
	check_actions( array( 77 ) === \ElementorPro\Modules\Popup\Module::$added, 'Sin el módulo de plantillas de tema no se llama a add_popup_to_location().' );

	// Regla [hidden] en el CSS del documento.
	$css = (object) array( 'css' => '' );
	do_action( 'elementor/element/parse_css', $css, $plain );
	check_actions( 0 === \Digitalisimo\Elements\F7b\Hidden_Rule::$added, 'Sin ocultar, sin regla.' );
	do_action( 'elementor/element/parse_css', $css, new \Elementor\Element( 'x1', 'widget', array( Controls::ON => 'yes', Controls::LIST => array( array( 'action' => 'toggle_class', 'class_name' => 'a', 'target' => 'y' ) ) ) ) );
	check_actions( 0 === \Digitalisimo\Elements\F7b\Hidden_Rule::$added, 'Una clase no necesita la regla.' );
	do_action( 'elementor/element/parse_css', $css, $inner );
	do_action( 'elementor/element/parse_css', $css, $outer );
	check_actions( 2 === \Digitalisimo\Elements\F7b\Hidden_Rule::$added && \Digitalisimo\Elements\F7b\Hidden_Rule::RULE === $css->css, 'Oculto al cargar y mostrar: la regla, una sola vez en el documento (C2-2).' );

	// -------------------------------------------------------------------------------------------------------------
	// 6. Archivos.
	$js = (string) file_get_contents( $root . '/modules/digitalisimo-f7b/assets/js/actions.js' );
	check_actions( strlen( $js ) <= 6144, 'actions.js cabe en 6 KB: ' . strlen( $js ) . ' B (V.13).' );
	check_actions( ! preg_match( '/jQuery|\$\(/', $js ) && false === strpos( $js, 'eval(' ) && false === strpos( $js, 'innerHTML' ), 'Sin jQuery, eval ni innerHTML.' );
	$editor_js = (string) file_get_contents( $root . '/modules/digitalisimo-f7b/assets/js/actions-editor.js' );
	check_actions( strlen( $editor_js ) <= 8192 && ! preg_match( '/elementorCommon\.ajax|\.ajax\(|\bfetch\(|XMLHttpRequest|admin-ajax/', $editor_js ), 'actions-editor.js ≤ 8 KB y sin AJAX: ' . strlen( $editor_js ) . ' B.' );
	foreach ( glob( $dir . '/*.php' ) as $file ) {
		$source = (string) file_get_contents( $file );
		check_actions( false === stripos( $source, 'bricks' ), 'Sin nombres de Bricks: ' . basename( $file ) );
		check_actions( ! preg_match( '/\bSELECT\s+DISTINCT\b/i', $source ) && false === strpos( $source, '$wpdb' ), 'Sin consultas propias: ' . basename( $file ) );
	}
	$fixture = (string) file_get_contents( __DIR__ . '/fixtures/f7b-actions.html' );
	check_actions( false !== strpos( $fixture, '<style id="hidden-rule">' . \Digitalisimo\Elements\F7b\Hidden_Rule::RULE . '</style>' ), 'La página de la prueba en Chrome usa la regla del contrato.' );
	$rule_file = $root . '/modules/digitalisimo-f7b/class-hidden-rule.php';
	if ( is_file( $rule_file ) ) {
		check_actions( false !== strpos( (string) file_get_contents( $rule_file ), "const RULE = '" . \Digitalisimo\Elements\F7b\Hidden_Rule::RULE . "';" ), 'Hidden_Rule::RULE del frente A es la del contrato.' );
	}
	$gate = (string) file_get_contents( $root . '/modules/digitalisimo-front/class-pro-gate.php' );
	check_actions( false !== strpos( $gate, 'public static function require( string $reason ): void' ), 'Pro_Gate::require() existe con esa firma.' );
	$popup = (string) file_get_contents( $root . '/modules/popup/module.php' );
	check_actions( false !== strpos( $popup, 'public static function add_popup_to_location( $popup_id )' ), 'Popup\Module::add_popup_to_location() existe.' );
	$button_core = (string) file_get_contents( $root . '/elementor/includes/base/element-base.php' );
	check_actions( false !== strpos( $button_core, "'data-id' => \$id," ), 'El núcleo pinta data-id en el envoltorio (Render::process() lo busca).' );
	$document = (string) file_get_contents( $root . '/elementor/core/base/document.php' );
	check_actions( false !== strpos( $document, "'scripts' => \$scripts_to_queue," ) && false !== strpos( $document, "'styles' => \$styles_to_queue," ), 'La caché del documento guarda los handles encolados al pintar.' );
	$frontend = (string) file_get_contents( $root . '/elementor/includes/frontend.php' );
	check_actions( false !== strpos( $frontend, "apply_filters( 'elementor/frontend/builder_content_data', \$data, \$post_id )" ), 'builder_content_data corre en cada documento impreso.' );
	$skin = (string) file_get_contents( $root . '/modules/posts/skins/skin-base.php' ) . file_get_contents( $root . '/modules/posts/traits/button-widget-trait.php' );
	check_actions( false !== strpos( $skin, '<div class="e-load-more-anchor"' ) && false !== strpos( $skin, "'wrapper', 'class', 'elementor-button-wrapper'" ) && false !== strpos( $js, "'.e-load-more-anchor+.elementor-button-wrapper .elementor-button,.digi-loop__anchor+.digi-loop__more .elementor-button'" ), '«Cargar más» pulsa el botón que sigue al ancla de PRO.' );
	// Y el de las Cuadrículas propias de C5: el ancla y, justo después, «Cargar más» (`Posts_Base::render_pagination()`).
	$own_loop = (string) file_get_contents( $root . '/modules/digitalisimo-loop/class-posts-base.php' );
	check_actions( (bool) preg_match( '/<div class="digi-loop__anchor"[^;]*;\s*if \( self::LOAD_MORE_ON_CLICK === \$type \) \{\s*\$this->render_button\(\);/', $own_loop ) && false !== strpos( $own_loop, '<div class="digi-loop__more elementor-button-wrapper"><a class="' ), '«Cargar más» también en las Cuadrículas propias: el botón sigue al ancla propia.' );

	echo 'DIGITALÍSIMO Elements F7b: acciones con destino validadas (' . $GLOBALS['checks'] . " comprobaciones).\n";
}
