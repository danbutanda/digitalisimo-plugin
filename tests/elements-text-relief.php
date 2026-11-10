<?php
/**
 * «Relieve 3D» (`Digitalisimo\Elements\Text_Relief`), con dobles de WordPress y del núcleo.
 *
 * - Arranque: sólo los ganchos de sus controles (Encabezado y Encabezado avanzado), del pintado de widgets y del registro
 *   de recursos; nada en el contenido, el `head` ni `wp_enqueue_scripts` (coste 0 donde no se usa).
 * - Recursos: hoja ≤ 512 B y script ≤ 1,5 KB, `defer`, sin dependencias ni jQuery, sólo registrados: los encola el
 *   núcleo por la declaración `assets` del interruptor.
 * - Controles: las claves del rasgo, ninguno con `default`, `style_transfer => false` en evento, giro y sentido, todos
 *   detrás del interruptor (giro y sentido, también del evento) y con selectores acotados al propio widget.
 * - Configuración: lista cerrada de eventos y unidades, topes, valores de respaldo y nada si está apagado.
 * - Marcado: el título no cambia; sólo el envoltorio recibe `data-digi-relief`, nunca en el lienzo del editor.
 * - Textos en español con el dominio `pro-elements`, sin nombres de los productos de origen (guarda del paquete).
 * - Fixture del navegador (`tests/fixtures/f8-text-relief.html`): sus widgets salen de este mismo código;
 *   `--write-fixture` lo regenera.
 * Uso: node <php-wasm-cli> tests/elements-text-relief.php
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Controls_Manager {
		const TAB_STYLE = 'style'; const SWITCHER = 'switcher'; const SELECT = 'select'; const SLIDER = 'slider'; const COLOR = 'color'; const NUMBER = 'number';
	}
	class Plugin { public static $instance; }
	/** Pila de controles: secciones y controles, como los guarda el núcleo. */
	class Stack_Double {
		public $name;
		public $sections = array();
		public $controls = array();
		private $section = '';
		public function __construct( $name ) { $this->name = $name; }
		public function get_name() { return $this->name; }
		public function get_controls( $id = null ) { return null === $id ? $this->controls : ( $this->controls[ $id ] ?? null ); }
		public function start_controls_section( $id, $args ) { $this->sections[ $id ] = $args; $this->section = $id; }
		public function end_controls_section() { $this->section = ''; }
		public function add_control( $id, $args ) {
			if ( isset( $this->controls[ $id ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $id ); }
			$this->controls[ $id ] = $args + array( '_section' => $this->section );
		}
		public function add_responsive_control( $id, $args ) { $this->add_control( $id, $args + array( '_responsive' => true ) ); }
		public function add_group_control( $type, $args ) { throw new \RuntimeException( 'Sin grupos: ' . $args['name'] ); }
	}
	/** Elemento en el front: ajustes (anota cuáles se piden) y atributos de render. */
	class Element_Double {
		public $settings;
		public $asked = array();
		public $attributes = array();
		public function __construct( array $settings ) { $this->settings = $settings; }
		public function get_settings( $key = null ) { $this->asked[] = null === $key ? '*' : $key; return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) { throw new \RuntimeException( 'El rasgo no resuelve dinámicos.' ); }
		public function add_render_attribute( $element, $key, $value = null ) { $this->attributes[ $element ][ $key ] = $value; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.180' );
	$root    = realpath( __DIR__ . '/../digitalisimo-elements' );
	$fixture = __DIR__ . '/fixtures/f8-text-relief.html';
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	$checks = 0;
	function check_relief( $ok, $message ) { global $checks; if ( ! $ok ) { throw new \RuntimeException( $message ); } $checks++; }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function hooked( $hook ) { $n = 0; foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entries ) { $n += count( $entries ); } return $n; }
	function __( $text, $domain = 'default' ) { $GLOBALS['domains'][ $domain ] = true; return $text; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	$GLOBALS['styles'] = array(); $GLOBALS['scripts'] = array(); $GLOBALS['enqueued'] = array();
	function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['styles'][ $handle ] ) : in_array( 'style:' . $handle, $GLOBALS['enqueued'], true ); }
	function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['scripts'][ $handle ] ) : in_array( 'script:' . $handle, $GLOBALS['enqueued'], true ); }
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $handle ] = compact( 'src', 'deps', 'ver' ); return true; }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' ); return true; }
	function wp_enqueue_style( $handle ) { $GLOBALS['enqueued'][] = 'style:' . $handle; }
	function wp_enqueue_script( $handle ) { $GLOBALS['enqueued'][] = 'script:' . $handle; }

	$file = $root . '/modules/digitalisimo-widgets/class-text-relief.php';
	require $file;
	$R = '\Digitalisimo\Elements\Text_Relief';
	\Elementor\Plugin::$instance         = new \stdClass();
	\Elementor\Plugin::$instance->editor = new class() { public $edit = false; public function is_edit_mode() { return $this->edit; } };

	// 1. Arranque y coste 0.
	$R::init();
	$R::init();
	$expected = array( 'elementor/element/heading/section_title_style/after_section_end', 'elementor/element/digitalisimo-advanced-heading/section_heading_style/after_section_end', 'elementor/frontend/widget/before_render', 'elementor/frontend/after_register_styles', 'elementor/frontend/after_register_scripts' );
	check_relief( $expected === array_keys( $GLOBALS['wp_filters'] ), 'Sólo engancha los controles de los dos encabezados, el pintado de widgets y el registro de recursos: ' . implode( ', ', array_keys( $GLOBALS['wp_filters'] ) ) );
	foreach ( $expected as $hook ) { check_relief( 1 === hooked( $hook ), 'Un solo enganche (init idempotente) en ' . $hook . '.' ); }
	$source = file_get_contents( $file );
	$code   = php_strip_whitespace( $file );
	check_relief( ! preg_match( '/common-optimized|elementor\/element\/common\/|render_content|wp_enqueue_(style|script)\(|wp_head|wp_footer|the_content|wp_enqueue_scripts/', $code ), 'Ni en todos los widgets, ni en el contenido, ni encolado a mano: el título no cambia en el servidor y los recursos llegan por assets.' );

	// 2. Recursos.
	$R::register_assets();
	$R::register_assets();
	$style  = $GLOBALS['styles']['digitalisimo-text-relief'] ?? null;
	$script = $GLOBALS['scripts']['digitalisimo-text-relief'] ?? null;
	check_relief( $style && str_ends_with( $style['src'], 'modules/digitalisimo-widgets/assets/css/text-relief.css' ) && array() === $style['deps'] && '4.3.0.180' === $style['ver'], 'La hoja se registra con su ruta, sin dependencias y con la versión.' );
	check_relief( $script && str_ends_with( $script['src'], 'modules/digitalisimo-widgets/assets/js/text-relief.js' ) && array() === $script['deps'] && 'defer' === ( $script['args']['strategy'] ?? '' ) && true === ( $script['args']['in_footer'] ?? false ), 'El script se registra en el pie, con defer y sin dependencias (ni jQuery ni el frontend del núcleo).' );
	check_relief( array() === $GLOBALS['enqueued'], 'Registrar no encola nada.' );
	$css = file_get_contents( $root . '/' . $R::CSS );
	$js  = file_get_contents( $root . '/' . $R::JS );
	check_relief( strlen( $css ) <= 512, 'text-relief.css pesa ' . strlen( $css ) . ' B (tope 512 B).' );
	check_relief( strlen( $js ) <= 1536, 'text-relief.js pesa ' . strlen( $js ) . ' B (tope 1,5 KB).' );
	check_relief( ! preg_match( '/jQuery|\$\(|https?:\/\/|innerHTML|eval\(|new Function/', $js ), 'El script, sin jQuery, URL, innerHTML ni código dinámico: mueve y clona nodos.' );
	check_relief( str_contains( $js, "prefers-reduced-motion:reduce" ) && str_contains( $js, "'aria-hidden','true'" ) && str_contains( $js, "'inert',''" ) && str_contains( $js, "removeAttribute('id')" ) && str_contains( $js, "{passive:!0}" ), 'Copias fuera del árbol de accesibilidad e inertes, sin id repetidos; giro sólo sin movimiento reducido y con oyentes pasivos.' );
	check_relief( ! preg_match( '/url\(|#[0-9a-f]{3}(?:[0-9a-f]{3})?\b|rgba?\(|--e-global|!important/i', $css ), 'La hoja no fija colores, url() ni !important.' );
	check_relief( str_contains( $css, 'perspective:var(--digi-relief-perspective,500px)' ) && str_contains( $css, 'transform-style:preserve-3d' ) && str_contains( $css, '.digi-relief__copy{position:absolute;top:0;left:0;pointer-events:none;-webkit-user-select:none;user-select:none}' ), 'Perspectiva por variable (500 px sin valor), pila en 3D y copias superpuestas, sin puntero ni selección.' );
	preg_match_all( '/\.([a-z][a-z0-9_-]*)/', preg_replace( '/\{[^{}]*\}/', '{}', $css ), $classes );
	check_relief( array() === array_filter( array_unique( $classes[1] ), function ( $c ) { return ! preg_match( '/^(digi-|elementor-heading-title$)/', $c ); } ), 'La hoja sólo usa clases digi- y el título del encabezado del núcleo.' );

	// 3. Controles, en los dos encabezados.
	foreach ( array( 'heading', 'digitalisimo-advanced-heading' ) as $widget ) {
		$stack = new \Elementor\Stack_Double( $widget );
		$R::register_controls( $stack, array() );
		$R::register_controls( $stack, array() );
		$section = $stack->sections['digitalisimo_relief_section'] ?? array();
		check_relief( 1 === count( $stack->sections ) && 'style' === ( $section['tab'] ?? '' ) && 'Relieve 3D' === ( $section['label'] ?? '' ), $widget . ': una sola sección «Relieve 3D» en la pestaña Estilo, aunque el gancho llegue dos veces.' );
		$keys = array( 'digitalisimo_relief', 'digitalisimo_relief_depth', 'digitalisimo_relief_layers', 'digitalisimo_relief_color', 'digitalisimo_relief_perspective', 'digitalisimo_relief_fade', 'digitalisimo_relief_event', 'digitalisimo_relief_rotation', 'digitalisimo_relief_direction' );
		check_relief( $keys === array_keys( $stack->controls ), $widget . ': las claves del rasgo, en orden: ' . implode( ', ', array_keys( $stack->controls ) ) );
	}
	$defaults = array();
	foreach ( $stack->controls as $id => $control ) {
		if ( array_key_exists( 'default', $control ) ) { $defaults[] = $id; }
	}
	check_relief( array() === $defaults, 'Ningún control nace con default (un encabezado existente no cambia): ' . implode( ', ', $defaults ) );
	foreach ( array( 'digitalisimo_relief_event', 'digitalisimo_relief_rotation', 'digitalisimo_relief_direction' ) as $id ) {
		check_relief( false === ( $stack->controls[ $id ]['style_transfer'] ?? null ), $id . ': no es de estilo (style_transfer => false).' );
	}
	foreach ( array( 'digitalisimo_relief', 'digitalisimo_relief_depth', 'digitalisimo_relief_layers', 'digitalisimo_relief_fade' ) as $id ) {
		check_relief( true === ( $stack->controls[ $id ]['style_transfer'] ?? null ), $id . ': es de estilo y lo declara (style_transfer => true).' );
	}
	foreach ( $stack->controls as $id => $control ) {
		if ( 'digitalisimo_relief' === $id ) { continue; }
		check_relief( 'yes' === ( $control['condition']['digitalisimo_relief'] ?? '' ), $id . ': sólo con el interruptor encendido.' );
	}
	foreach ( array( 'digitalisimo_relief_rotation', 'digitalisimo_relief_direction' ) as $id ) {
		check_relief( '' === ( $stack->controls[ $id ]['condition']['digitalisimo_relief_event!'] ?? null ), $id . ': sólo si gira.' );
	}
	$switch = $stack->controls['digitalisimo_relief'];
	$terms  = array( 'terms' => array( array( 'name' => 'digitalisimo_relief', 'operator' => '===', 'value' => 'yes' ) ) );
	check_relief( 'switcher' === $switch['type'] && 'yes' === $switch['return_value'] && array( 'styles', 'scripts' ) === array_keys( $switch['assets'] ) && array( array( 'name' => 'digitalisimo-text-relief', 'conditions' => $terms ) ) === $switch['assets']['styles'] && $switch['assets']['styles'] === $switch['assets']['scripts'], 'El interruptor declara en assets la hoja y el script, sólo con el rasgo encendido.' );
	check_relief( array( 'px', 'em', 'rem' ) === $stack->controls['digitalisimo_relief_depth']['size_units'] && 1 === $stack->controls['digitalisimo_relief_layers']['min'] && 100 === $stack->controls['digitalisimo_relief_layers']['max'], 'Profundidad en px, em o rem; de 1 a 100 capas.' );
	check_relief( array( '', 'pointer', 'scroll', 'scroll-x', 'scroll-y' ) === array_keys( $stack->controls['digitalisimo_relief_event']['options'] ) && array( '', 'reverse' ) === array_keys( $stack->controls['digitalisimo_relief_direction']['options'] ), 'Evento y sentido con valores cerrados ("" es «nunca» y «normal»).' );
	check_relief( array( '{{WRAPPER}} .digi-relief__copy' => 'color: {{VALUE}};' ) === $stack->controls['digitalisimo_relief_color']['selectors'] && array( '{{WRAPPER}}' => '--digi-relief-perspective: {{SIZE}}{{UNIT}};' ) === $stack->controls['digitalisimo_relief_perspective']['selectors'], 'El color sólo pinta las copias y la perspectiva va por variable del propio widget.' );

	// 4. Configuración.
	$on = array( 'digitalisimo_relief' => 'yes' );
	check_relief( null === $R::config( array() ) && null === $R::config( array( 'digitalisimo_relief' => '', 'digitalisimo_relief_layers' => 4 ) ), 'Apagado: sin configuración.' );
	check_relief( array( 'd' => 30.0, 'u' => 'px', 'l' => 8, 'f' => 0 ) === $R::config( $on ), 'Valores de respaldo: 30 px, 8 capas, sin desvanecer y sin giro.' );
	$full = $R::config( $on + array( 'digitalisimo_relief_depth' => array( 'unit' => 'em', 'size' => '1.5' ), 'digitalisimo_relief_layers' => '12', 'digitalisimo_relief_fade' => 'yes', 'digitalisimo_relief_event' => 'pointer', 'digitalisimo_relief_rotation' => array( 'unit' => 'deg', 'size' => -40 ), 'digitalisimo_relief_direction' => 'reverse' ) );
	check_relief( array( 'd' => 1.5, 'u' => 'em', 'l' => 12, 'f' => 1, 'e' => 'pointer', 'r' => -40.0, 'v' => 1 ) === $full, 'Todos los valores: ' . json_encode( $full ) );
	check_relief( array( 'd' => 30.0, 'u' => 'px', 'l' => 8, 'f' => 0, 'e' => 'scroll-y', 'r' => 35.0, 'v' => 0 ) === $R::config( $on + array( 'digitalisimo_relief_event' => 'scroll-y' ) ), 'Con giro y sin ángulo: 35°.' );
	$forged = $R::config( $on + array( 'digitalisimo_relief_depth' => array( 'unit' => '%', 'size' => 50 ), 'digitalisimo_relief_layers' => 1000, 'digitalisimo_relief_event' => 'click" onload="x', 'digitalisimo_relief_direction' => 'sideways' ) );
	check_relief( array( 'd' => 50.0, 'u' => 'px', 'l' => 100, 'f' => 0 ) === $forged, 'Unidad fuera de la lista a px, capas topadas y evento forjado ignorado: ' . json_encode( $forged ) );
	check_relief( array( 'd' => 1000.0, 'u' => 'px', 'l' => 1, 'f' => 0 ) === $R::config( $on + array( 'digitalisimo_relief_depth' => array( 'unit' => 'px', 'size' => 99999 ), 'digitalisimo_relief_layers' => 0 ) ) && 0.0 === $R::config( $on + array( 'digitalisimo_relief_depth' => array( 'unit' => 'rem', 'size' => -3 ) ) )['d'], 'Profundidad entre 0 y su tope; al menos una capa.' );
	foreach ( array( '', 'abc', null, true, array( 3 ), 'INF', 'NAN' ) as $bad ) {
		$got = $R::config( $on + array( 'digitalisimo_relief_depth' => array( 'unit' => 'em', 'size' => $bad ), 'digitalisimo_relief_layers' => $bad ) );
		check_relief( 30.0 === $got['d'] && 'px' === $got['u'] && 8 === $got['l'], 'Un valor vacío o que no es un número (' . json_encode( $bad ) . ') usa el respaldo.' );
	}

	// 5. Pintado: before_render.
	$element = new \Elementor\Element_Double( array( 'digitalisimo_relief' => '', 'digitalisimo_relief_layers' => 3 ) );
	$R::before_render( $element );
	check_relief( array() === $element->attributes && array( 'digitalisimo_relief' ) === $element->asked, 'Apagado: ni atributos ni más lecturas que el interruptor (coste 0).' );
	$element = new \Elementor\Element_Double( $on + array( 'digitalisimo_relief_layers' => 5, 'digitalisimo_relief_event' => 'scroll', 'title' => 'x' ) );
	$R::before_render( $element );
	check_relief( array( '_wrapper' => array( 'data-digi-relief' => '{"d":30.0,"u":"px","l":5,"f":0,"e":"scroll","r":35.0,"v":0}' ) ) === $element->attributes || array( '_wrapper' => array( 'data-digi-relief' => '{"d":30,"u":"px","l":5,"f":0,"e":"scroll","r":35,"v":0}' ) ) === $element->attributes, 'Encendido: el envoltorio recibe la configuración validada: ' . json_encode( $element->attributes ) );
	check_relief( ! in_array( 'title', $element->asked, true ) && ! in_array( '*', $element->asked, true ), 'Sólo lee los ajustes del rasgo.' );
	\Elementor\Plugin::$instance->editor->edit = true;
	$editing = new \Elementor\Element_Double( $on );
	$R::before_render( $editing );
	check_relief( array() === $editing->attributes, 'En el lienzo del editor no se añade nada.' );
	\Elementor\Plugin::$instance->editor->edit = false;
	$R::before_render( new \stdClass() );
	$R::before_render( null );

	// 6. Textos, dominio y guarda del paquete.
	preg_match_all( "/__\(\s*'[^']*',\s*'([a-z-]+)'\s*\)/", $source, $domains );
	check_relief( count( $domains[1] ) >= 20 && array( 'pro-elements' ) === array_values( array_unique( $domains[1] ) ), 'Textos traducibles con el dominio pro-elements (' . count( $domains[1] ) . ').' );
	check_relief( ! preg_match( "/__\(\s*'(Depth|Layers|Perspective|Fade|Event|Rotation|Direction|None|Pointer|Scroll|Reverse)\b/", $source ), 'Textos visibles en español.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]|ztext|z-layer|z-text/i';
	check_relief( ! preg_match( $guard, $source . $css . $js ), 'Pasa la guarda de menciones del paquete (scripts/build-packages.sh) y no copia nombres de otras bibliotecas.' );
	check_relief( str_contains( file_get_contents( $root . '/modules/digitalisimo-widgets/class-registry.php' ), "'class-text-relief.php' => Text_Relief::class" ), 'Arranca desde la lista de rasgos de Widget_Registry::init().' );

	// 7. Fixture del navegador: encabezados pintados con este código y el estilo que generarían sus controles.
	function relief_widget( $R, $id, array $settings, $inner, $type = 'heading', $optimized = false ) {
		$element = new \Elementor\Element_Double( $settings );
		$R::before_render( $element );
		$attrs = '';
		foreach ( $element->attributes['_wrapper'] ?? array() as $name => $value ) { $attrs .= ' ' . $name . '="' . esc_attr( $value ) . '"'; }
		$body = $optimized ? $inner : '<div class="elementor-widget-container">' . $inner . '</div>';
		return '<div class="elementor-element elementor-element-' . $id . ' elementor-widget elementor-widget-' . $type . '" data-id="' . $id . '" data-element_type="widget" data-widget_type="' . $type . '.default"' . $attrs . '>' . $body . '</div>';
	}
	$title = function ( $text, $tag = 'h2' ) { return '<' . $tag . ' class="elementor-heading-title elementor-size-default">' . $text . '</' . $tag . '>'; };
	$cases = array(
		relief_widget( $R, 'r0plain', array(), $title( 'Tres de' ) ),
		relief_widget( $R, 'r1base', $on + array( 'digitalisimo_relief_depth' => array( 'unit' => 'px', 'size' => 30 ), 'digitalisimo_relief_layers' => 8, 'digitalisimo_relief_fade' => 'yes' ), $title( 'Tres de' ) ),
		relief_widget( $R, 'r2link', $on + array( 'digitalisimo_relief_layers' => 4 ), $title( '<a href="#destino" id="enlace-titulo">Con enlace</a>', 'h3' ), 'heading', true ),
		relief_widget( $R, 'r3color', $on + array( 'digitalisimo_relief_depth' => array( 'unit' => 'em', 'size' => 1 ), 'digitalisimo_relief_layers' => 3 ), $title( 'Color propio' ) ),
		relief_widget( $R, 'r4pointer', $on + array( 'digitalisimo_relief_event' => 'pointer', 'digitalisimo_relief_rotation' => array( 'unit' => 'deg', 'size' => 20 ) ), $title( 'Gira' ) ),
		relief_widget( $R, 'r5reverse', $on + array( 'digitalisimo_relief_event' => 'pointer', 'digitalisimo_relief_direction' => 'reverse', 'digitalisimo_relief_rotation' => array( 'unit' => 'deg', 'size' => 20 ) ), $title( 'Al revés' ) ),
		relief_widget( $R, 'r6scroll', $on + array( 'digitalisimo_relief_event' => 'scroll-y' ), $title( 'Al desplazar' ) ),
		relief_widget( $R, 'r7adv', $on + array( 'digitalisimo_relief_layers' => 2 ), '<div class="digi-advanced-heading"><span class="digi-advanced-heading__sub">Antes</span><h2 class="digi-advanced-heading__title"><span class="digi-advanced-heading__main">Avanzado</span> <span class="digi-advanced-heading__split">extra</span></h2></div>', 'digitalisimo-advanced-heading' ),
	);
	$generated = '.elementor-element-r3color .digi-relief__copy{color: #c0392b;}.elementor-element-r3color{--digi-relief-perspective: 200px;}';
	$page = "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Relieve 3D</title>\n"
		. "<!-- Generado por tests/elements-text-relief.php --write-fixture: no editar a mano. Lo usa elements-text-relief-chrome.mjs. -->\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/elementor/assets/css/frontend.min.css\">\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-widgets/assets/css/text-relief.css\">\n"
		. "<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1a1a1a;background:#fff}.digi-test-page{padding:0 16px 1600px;text-align:center}.elementor-widget{margin-bottom:64px}.elementor-heading-title{font-size:32px;line-height:1;margin:0}.digi-advanced-heading__title{font-size:32px;margin:0}\n" . $generated . "</style>\n</head>\n<body>\n<div class=\"digi-test-page\">\n"
		. implode( "\n", $cases ) . "\n"
		. "<p><a href=\"#fin\" id=\"fin\">Enlace final</a></p>\n</div>\n"
		. "<script src=\"../../digitalisimo-elements/modules/digitalisimo-widgets/assets/js/text-relief.js\" defer></script>\n</body>\n</html>\n";
	if ( in_array( '--write-fixture', $argv ?? array(), true ) ) {
		file_put_contents( $fixture, $page );
		echo "Fixture escrito: tests/fixtures/f8-text-relief.html\n";
	}
	check_relief( is_file( $fixture ) && file_get_contents( $fixture ) === $page, 'El fixture del navegador es exactamente el marcado actual (regenerar con --write-fixture).' );
	check_relief( 7 === substr_count( $page, 'data-digi-relief=' ) && 2 === substr_count( $page, '>Tres de</h2>' ) && ! str_contains( implode( '', $cases ), 'digi-relief' . '__' ), 'El fixture tiene siete encabezados con relieve y uno sin él, y el servidor no pinta capas.' );

	echo "Relieve 3D: {$checks} comprobaciones en verde.\n";
}
