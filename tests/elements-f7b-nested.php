<?php
/**
 * F7b, frente F: slider anidado ligero (`digitalisimo-nested-slider`, C5; V.7 de la especificación), con dobles de
 * WordPress, del núcleo y de `F7b\Module` (frente A), y la base real del núcleo
 * (`elementor/modules/nested-elements/base/widget-nested-base.php`).
 *
 * - Arranque y guardas: `init()` (en `plugins_loaded`) sólo engancha y arranca la presentación del frente G, sin cargar
 *   clases del núcleo; la guarda corre al registrar los widgets y al encolar el script del editor: sin el experimento de
 *   anidados, sin `Widget_Nested_Base` o sin su repetidor, ni widget ni script (y `F7b\Module::requires()` anota lo
 *   que falta).
 * - Registro como F4-Woo: idempotente, tolerante y sin repetir un tipo que el núcleo ya tenga; `WIDGETS` en el formato
 *   de `Widget_Registry::WIDGETS`.
 * - Recursos sólo donde se usa: hoja y script sólo se registran (`defer`, sin dependencias ni jQuery) y los declara el
 *   widget; el script del editor sólo en el editor, detrás de `nested-elements`.
 * - Panel: título, icono y palabras clave distintos del «Carrusel» anidado de PRO; navegación sin «ninguna» (no se
 *   quitan a la vez flechas y puntos); controles que no son de estilo con `style_transfer => false`; los de estilo sin
 *   `default`; configuración de anidado (repetidor mejorado, puntos como destino, pista como contenedor de hijos).
 * - Marcado y ARIA del servidor: `section` con `aria-roledescription` y `aria-label` (traducible, sin HTML), pausa
 *   primera y oculta hasta el script, flechas, puntos «Ir a la diapositiva N» con `aria-current` en el primero, región
 *   viva según la reproducción automática, hijos con `role="group"` y «N de M» (y un contenedor anidado sin ellos);
 *   listas cerradas ante valores forjados; sin `inert`, `<script>` ni `<img>` en el HTML.
 * - Plantilla del editor: mismas listas, mismos textos y `data-digi-start`; el punto nuevo del repetidor.
 * - Fixture del navegador (`tests/fixtures/f7b-nested-slider.html`): sus sliders son exactamente el HTML de este
 *   `render()` y su plantilla, la de `content_template()`. Con `--write-fixture` se regenera.
 */
namespace Elementor {
	class Controls_Manager {
		const RAW_HTML = 'raw_html'; const TEXT = 'text'; const SELECT = 'select'; const NUMBER = 'number'; const SWITCHER = 'switcher';
		const SLIDER = 'slider'; const COLOR = 'color'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Repeater {
		private $fields = array();
		public function add_control( $id, $args ) { $this->fields[ $id ] = $args + array( 'name' => $id ); }
		public function get_controls() { return $this->fields; }
	}
	class Plugin {
		public static $instance;
		public $experiments;
	}
	class Experiments_Double {
		public $active = array();
		public function is_feature_active( $name ) { return ! empty( $this->active[ $name ] ); }
	}
	/** Lo que el widget usa del núcleo: ajustes, controles, hijos y render. */
	abstract class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $sections = array();
		public $children = array();
		public $id = 'a1b2c3d';
		private $tab = 'content';
		public function __construct( array $settings = array(), array $children = array(), $id = 'a1b2c3d' ) { $this->settings = $settings; $this->children = $children; $this->id = $id; }
		public function get_id() { return $this->id; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_children() { return $this->children; }
		public function get_data() { return array( 'elType' => 'widget', 'widgetType' => $this->get_name(), 'settings' => $this->settings ); }
		public function start_controls_section( $id, $args ) { $this->tab = $args['tab'] ?? 'content'; $this->sections[ $id ] = $args; }
		public function end_controls_section() { $this->tab = 'content'; }
		public function add_control( $id, $args ) {
			if ( isset( $this->controls[ $id ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $id ); }
			$this->controls[ $id ] = $args + array( 'tab' => $this->tab );
		}
		public function add_responsive_control( $id, $args ) { $this->add_control( $id, $args + array( 'responsive' => true ) ); }
		protected function get_initial_config() { return array( 'name' => $this->get_name() ); }
		public function initial_config() { return $this->get_initial_config(); }
		public function controls() { $this->controls = array(); $this->sections = array(); $this->register_controls(); return $this->controls; }
		public function html() { ob_start(); $this->render(); return ob_get_clean(); }
		public function template() { ob_start(); $this->content_template(); return ob_get_clean(); }
		public function single_template() { ob_start(); $this->content_template_single_repeater_item(); return ob_get_clean(); }
		public function print_template() {}
		protected function register_controls() {}
		protected function render() {}
		protected function content_template() {}
	}
	class Element_Base {
		public static function render_markdown() { return ''; }
	}
	/** Contenedor hijo: pinta su contenido y luego pasa por `elementor/frontend/container/should_render`, como el núcleo. */
	class Container_Double {
		public $id;
		public $inner;
		public $nested;
		public $attributes = array();
		public function __construct( $id, $inner = '', $nested = null ) { $this->id = $id; $this->inner = $inner; $this->nested = $nested; }
		public function get_id() { return $this->id; }
		public function add_render_attribute( $key, $attributes ) { $this->attributes = array_merge( $this->attributes, $attributes ); }
		public function print_element() {
			ob_start();
			if ( $this->nested ) { $this->nested->print_element(); }
			echo $this->inner;
			$content = ob_get_clean();
			if ( ! apply_filters( 'elementor/frontend/container/should_render', true, $this ) ) { return; }
			$attrs = '';
			foreach ( $this->attributes as $name => $value ) { $attrs .= ' ' . $name . '="' . esc_attr( $value ) . '"'; }
			echo '<div class="elementor-element elementor-element-' . esc_attr( $this->id ) . ' e-con-full e-flex e-con e-child" data-id="' . esc_attr( $this->id ) . '" data-element_type="container"' . $attrs . '>' . $content . '</div>';
			$this->attributes = array();
		}
	}
	class Widgets_Manager_Double {
		public $types = array();
		public function register( $widget ) { $this->types[ $widget->get_name() ] = $widget; }
		public function get_widget_types( $name = null ) { return null === $name ? $this->types : ( $this->types[ $name ] ?? null ); }
	}
}
namespace Digitalisimo\Elements\F7b {
	/** Doble del frente A con la firma del contrato. */
	final class Module {
		public static $calls = array();
		public static function requires( array $symbols, string $who ): bool {
			self::$calls[] = array( $who, $symbols );
			foreach ( $symbols as $symbol ) {
				if ( ! class_exists( $symbol ) && ! function_exists( $symbol ) ) { return false; }
			}
			return true;
		}
	}
}
namespace Digitalisimo\Elements\F7b\Nested {
	/** Doble del frente G: sólo cuenta los arranques. */
	final class Presentation {
		public static $inits = 0;
		public static function init() { self::$inits++; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );
	$root    = realpath( __DIR__ . '/../digitalisimo-elements' );
	$fixture = __DIR__ . '/fixtures/f7b-nested-slider.html';
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	$checks = 0;
	function check_nested( $ok, $message ) { global $checks; if ( ! $ok ) { throw new \RuntimeException( $message ); } $checks++; }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['wp_filters'][ $hook ][ $priority ] ?? array() as $i => $entry ) {
			if ( $entry[0] === $callback ) { unset( $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ] ); return true; }
		}
		return false;
	}
	function apply_filters( $hook, $value, ...$args ) {
		$callbacks = $GLOBALS['wp_filters'][ $hook ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
		return $value;
	}
	function hooked( $hook ) { $n = 0; foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entries ) { $n += count( $entries ); } return $n; }
	function __( $text, $domain = 'default' ) { $GLOBALS['domains'][ $domain ] = true; return $text; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_html( $text ) { return esc_attr( $text ); }
	function esc_attr__( $text, $domain = 'default' ) { return esc_attr( __( $text, $domain ) ); }
	function esc_html__( $text, $domain = 'default' ) { return esc_html( __( $text, $domain ) ); }
	function esc_js( $text ) { return addslashes( (string) $text ); }
	function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	$GLOBALS['styles']  = array();
	$GLOBALS['scripts'] = array();
	$GLOBALS['enqueued'] = array();
	$GLOBALS['inline']  = array();
	function wp_style_is( $handle, $list = 'enqueued' ) { return isset( $GLOBALS['styles'][ $handle ] ); }
	function wp_script_is( $handle, $list = 'enqueued' ) { return isset( $GLOBALS['scripts'][ $handle ] ); }
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $handle ] = compact( 'src', 'deps', 'ver' ); return true; }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' ); return true; }
	function wp_enqueue_style( $handle ) { $GLOBALS['enqueued'][] = 'style:' . $handle; }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { $GLOBALS['enqueued'][] = 'script:' . $handle; $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' ); }
	function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['inline'][ $handle ][] = array( $data, $position ); return true; }

	$module_file = $root . '/modules/digitalisimo-f7b/nested/class-module.php';
	$widget_file = $root . '/modules/digitalisimo-f7b/nested/class-slider-widget.php';
	require $module_file;
	$M = '\Digitalisimo\Elements\F7b\Nested\Module';

	// 1. Arranque en plugins_loaded: sólo engancha. No toca Widget_Nested_Base (hereda de Widget_Base, que el núcleo
	// declara en `init`: su autocarga fallaría) ni llama a la guarda; la presentación del frente G arranca una vez.
	$autoloaded = array();
	spl_autoload_register( function ( $class ) use ( &$autoloaded ) { $autoloaded[] = $class; } );
	$M::init();
	$M::init();
	check_nested( array() === \Digitalisimo\Elements\F7b\Module::$calls && ! array_intersect( array( 'Elementor\Modules\NestedElements\Base\Widget_Nested_Base', 'Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater', 'Elementor\Widget_Base' ), $autoloaded ), 'El arranque no carga clases del núcleo ni pasa la guarda (todavía no puede responder).' );
	check_nested( 1 === \Digitalisimo\Elements\F7b\Nested\Presentation::$inits, 'La presentación del frente G arranca una sola vez desde aquí.' );
	foreach ( array( 'elementor/frontend/after_register_styles', 'elementor/frontend/after_register_scripts', 'elementor/widgets/register', 'elementor/editor/after_enqueue_scripts' ) as $hook ) {
		check_nested( 1 === hooked( $hook ), 'Un solo enganche en ' . $hook . '.' );
	}
	check_nested( array( 'elementor/frontend/after_register_styles', 'elementor/frontend/after_register_scripts', 'elementor/widgets/register', 'elementor/editor/after_enqueue_scripts' ) === array_keys( $GLOBALS['wp_filters'] ), 'Nada más: ni wp_enqueue_scripts, ni el contenido, ni el render de otros elementos (coste cero donde no se usa).' );
	check_nested( isset( $GLOBALS['wp_filters']['elementor/widgets/register'][20] ), 'Registra los widgets a prioridad 20, como Widget_Registry y Woo\Registry.' );

	// Formato del registro, el de Widget_Registry::WIDGETS (lo leen front-budget y kit-inventory con la misma expresión).
	$module_src = file_get_contents( $module_file );
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $module_src, $entries, PREG_SET_ORDER );
	check_nested( 1 === count( $entries ) && 'digitalisimo-nested-slider' === $entries[0][1], 'WIDGETS tiene una entrada legible con la expresión de Widget_Registry.' );
	preg_match( "/'file'\s*=>\s*'([^']+)'/", $entries[0][2], $f );
	preg_match( "/'css'\s*=>\s*'([^']+)'/", $entries[0][2], $c );
	preg_match( "/'js'\s*=>\s*'([^']+)'/", $entries[0][2], $j );
	check_nested( is_file( $root . '/modules/digitalisimo-f7b/' . $f[1] ) && is_file( $root . '/' . $c[1] ) && is_file( $root . '/' . $j[1] ), 'Los archivos del registro existen (file relativo al módulo; css y js, a la raíz).' );

	// Recursos: sólo registrados, defer, sin dependencias ni jQuery; nada encolado en el front.
	$M::register_assets();
	$M::register_assets();
	$style  = $GLOBALS['styles']['digitalisimo-nested-slider'] ?? null;
	$script = $GLOBALS['scripts']['digitalisimo-nested-slider'] ?? null;
	check_nested( $style && str_ends_with( $style['src'], 'modules/digitalisimo-f7b/assets/css/nested-slider.css' ) && array() === $style['deps'], 'La hoja se registra con su ruta y sin dependencias.' );
	check_nested( $script && str_ends_with( $script['src'], 'modules/digitalisimo-f7b/assets/js/nested-slider.js' ) && array() === $script['deps'] && 'defer' === ( $script['args']['strategy'] ?? '' ) && true === ( $script['args']['in_footer'] ?? false ), 'El script se registra en el pie, con defer y sin dependencias (ni jQuery ni el frontend del núcleo).' );
	check_nested( array() === $GLOBALS['enqueued'], 'Registrar no encola nada: lo encola el núcleo donde se pinta el widget.' );
	check_nested( ! preg_match( '/wp_enqueue_style\(/', $module_src ) && 1 === preg_match_all( '/wp_enqueue_script\(/', $module_src ), 'El módulo sólo encola el script del editor.' );

	// 2. Guarda en elementor/widgets/register y en el editor: experimento, base de anidados y repetidor.
	\Elementor\Plugin::$instance              = new \stdClass();
	\Elementor\Plugin::$instance->experiments = new \Elementor\Experiments_Double();
	$manager = new \Elementor\Widgets_Manager_Double();
	check_nested( array() === $M::widgets( $manager ) && array() === $manager->types && array() === \Digitalisimo\Elements\F7b\Module::$calls, 'Sin el experimento de anidados no se registra el widget (ni se anota como símbolo que falta).' );
	$M::editor_script();
	check_nested( array() === $GLOBALS['enqueued'], 'Sin el experimento no se encola el script del editor.' );
	\Elementor\Plugin::$instance->experiments->active['nested-elements'] = true;
	$M::reset();
	check_nested( array() === $M::widgets( $manager ) && array( array( 'nested', array( 'Elementor\Modules\NestedElements\Base\Widget_Nested_Base', 'Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater' ) ) ) === \Digitalisimo\Elements\F7b\Module::$calls, 'Con el experimento pero sin la base de anidados: F7b\Module::requires() anota lo que falta y no se registra nada.' );
	$M::editor_script();
	check_nested( array() === $GLOBALS['enqueued'] && 1 === count( \Digitalisimo\Elements\F7b\Module::$calls ), 'Tampoco el script del editor, con la guarda memorizada en la petición.' );
	require $root . '/elementor/modules/nested-elements/base/widget-nested-base.php';
	$M::reset();
	check_nested( array() === $M::widgets( $manager ), 'Con la base pero sin Control_Nested_Repeater, tampoco.' );
	eval( 'namespace Elementor\Modules\NestedElements\Controls; class Control_Nested_Repeater { const CONTROL_TYPE = "nested-elements-repeater"; }' );
	$M::reset();
	check_nested( array( 'digitalisimo-nested-slider' ) === $M::widgets( $manager ) && array( 'digitalisimo-nested-slider' ) === array_keys( $manager->types ), 'Con el experimento, la base y su repetidor se registra el slider anidado.' );
	check_nested( array() === $M::widgets( $manager ), 'Un tipo ya registrado no se registra otra vez.' );
	check_nested( array() === $M::widgets( null ) && array() === $M::widgets( new \stdClass() ), 'Un gestor que no es el del núcleo no rompe nada.' );
	$M::editor_script();
	$editor = $GLOBALS['scripts']['digitalisimo-nested-slider-editor'] ?? null;
	check_nested( array( 'script:digitalisimo-nested-slider-editor' ) === $GLOBALS['enqueued'] && array( 'nested-elements' ) === $editor['deps'] && str_ends_with( $editor['src'], 'modules/digitalisimo-f7b/assets/js/nested-slider-editor.js' ), 'En el editor, el script detrás de nested-elements (el que define NestedElementBase).' );
	$inline = $GLOBALS['inline']['digitalisimo-nested-slider-editor'][0] ?? array( '', '' );
	check_nested( 'before' === $inline[1] && 'window.digitalisimoNestedSlider={"slide":"diapositiva","of":"%1$s de %2$s"};' === $inline[0], 'El editor recibe los textos de las diapositivas, los mismos del servidor.' );

	// 3. Panel.
	$class  = $M::load( 'digitalisimo-nested-slider' );
	$widget = new $class( array(), array() );
	$pro    = file_get_contents( $root . '/modules/nested-carousel/widgets/nested-carousel.php' );
	preg_match( "/function get_keywords\(\) \{\s*return \[([^\]]*)\]/", $pro, $pro_kw );
	preg_match_all( "/'([^']+)'/", $pro_kw[1], $pro_kw );
	$pro_keywords = array_map( 'strtolower', $pro_kw[1] );
	check_nested( 'digitalisimo-nested-slider' === $widget->get_name() && 'Slider anidado ligero' === $widget->get_title() && 'eicon-slider-push' === $widget->get_icon() && array( 'digitalisimo' ) === $widget->get_categories(), 'Nombre, título, icono y categoría propios.' );
	check_nested( count( $pro_keywords ) >= 5 && array() === array_intersect( array_map( 'strtolower', $widget->get_keywords() ), $pro_keywords ) && ! str_contains( strtolower( $widget->get_title() ), 'carrusel' ), 'Palabras clave y título distintos de los del Carrusel anidado de PRO.' );
	check_nested( array( 'digitalisimo-nested-slider' ) === $widget->get_style_depends() && array( 'digitalisimo-nested-slider' ) === $widget->get_script_depends(), 'Declara sólo su hoja y su script.' );
	check_nested( $widget->has_widget_inner_wrapper(), 'Sin el marcado optimizado, envoltorio interior como los anidados del núcleo.' );
	\Elementor\Plugin::$instance->experiments->active['e_optimized_markup'] = true;
	check_nested( ! $widget->has_widget_inner_wrapper(), 'Con el marcado optimizado, sin envoltorio interior.' );
	$config = $widget->initial_config();
	check_nested( true === $config['support_nesting'] && true === $config['support_improved_repeaters'] && array( '.digi-nslider__dots' ) === $config['target_container'] && 'button' === $config['node'] && empty( $config['is_interlaced'] ), 'Anidado con repetidor mejorado: los puntos son el destino de cada fila y los hijos van sin intercalar.' );
	check_nested( '.digi-nslider__track' === $config['defaults']['elements_placeholder_selector'] && 'slide_title' === $config['defaults']['repeater_title_setting'] && 'Diapositiva n.º %d' === $config['defaults']['elements_title'], 'Los hijos van en la pista; título del repetidor y de cada hijo.' );
	check_nested( 3 === count( $config['defaults']['elements'] ) && 'container' === $config['defaults']['elements'][0]['elType'] && 'Diapositiva n.º 2' === $config['defaults']['elements'][1]['settings']['_title'] && 'full' === $config['defaults']['elements'][2]['settings']['content_width'], 'Tres contenedores de ancho completo por defecto.' );

	$controls = $widget->controls();
	check_nested( 'nested-elements-repeater' === $controls['slides']['type'] && 3 === count( $controls['slides']['default'] ) && isset( $controls['slides']['fields']['slide_title'] ), 'Repetidor anidado «slides» con título por diapositiva.' );
	check_nested( array( 'both', 'arrows', 'dots' ) === array_keys( $controls['navigation']['options'] ) && 'both' === $controls['navigation']['default'], 'Navegación sin «ninguna»: no se pueden quitar a la vez flechas y puntos.' );
	check_nested( array( 'slide', 'fade', 'scale' ) === array_keys( $controls['effect']['options'] ) && 'slide' === $controls['effect']['default'], 'Transiciones slide, fade y scale.' );
	check_nested( '' === $controls['autoplay']['default'] && 2000 === $controls['autoplay_delay']['min'] && 'yes' === $controls['pause_on_hover']['default'] && array( 'autoplay' => 'yes' ) === $controls['pause_on_hover']['condition'], 'Sin reproducción automática por defecto; pausa al pasar el puntero, sólo con ella.' );
	check_nested( 'Diapositivas' === $controls['carousel_label']['default'], 'Nombre accesible con texto por defecto traducible.' );
	$styled = 0;
	foreach ( $controls + $controls['slides']['fields'] as $id => $control ) {
		if ( 'raw_html' === $control['type'] ) { continue; }
		if ( ! empty( $control['selectors'] ) ) {
			$styled++;
			check_nested( ! array_key_exists( 'default', $control ) && ! isset( $control['style_transfer'] ), $id . ': un control de estilo no lleva default (la hoja tiene el valor) y se copia con «Pegar estilo».' );
			foreach ( $control['selectors'] as $selector => $rule ) {
				check_nested( str_starts_with( $selector, '{{WRAPPER}} .digi-nslider' ) && ( str_contains( $rule, '--digi-ns-' ) || 'slider_height' === $id ), $id . ': escribe una variable propia (o la altura de la pista) en el slider.' );
			}
		} else {
			check_nested( false === ( $control['style_transfer'] ?? null ), $id . ': un control que no es de estilo declara style_transfer => false.' );
		}
	}
	check_nested( $styled >= 6, 'Hay controles de estilo (' . $styled . ').' );

	// 4. Marcado del servidor.
	function slides( $n, $nested = false ) {
		$children = array();
		for ( $i = 1; $i <= $n; $i++ ) {
			$inner = '<h3>Diapositiva ' . $i . '</h3>' . ( 2 === $i ? '<p>Contenido más alto de la segunda diapositiva.</p><p>Otra línea.</p>' : '' ) . '<a href="#enlace-' . $i . '">Enlace ' . $i . '</a>';
			$children[] = new \Elementor\Container_Double( 'c' . $i . 'aaaa', $inner, $nested && 1 === $i ? new \Elementor\Container_Double( 'n1aaaa', '<span>Anidado</span>' ) : null );
		}
		return $children;
	}
	function rows( $n ) { $rows = array(); for ( $i = 1; $i <= $n; $i++ ) { $rows[] = array( '_id' => 'r' . $i, 'slide_title' => 'Diapositiva n.º ' . $i ); } return $rows; }
	function slider_html( $class, array $settings, $children, $id = 'a1b2c3d' ) { $w = new $class( $settings + array( 'slides' => rows( 3 ), 'effect' => 'slide', 'navigation' => 'both', 'arrows_position' => 'bar', 'loop' => 'yes', 'autoplay' => '', 'autoplay_delay' => 5000, 'pause_on_hover' => 'yes', 'carousel_label' => 'Diapositivas' ), $children, $id ); return $w->html(); }
	function tag( $html, $re ) { return preg_match( $re, $html, $m ) ? $m[0] : ''; }

	$html = slider_html( $class, array(), slides( 3, true ) );
	$open = tag( $html, '/<section[^>]*>/' );
	check_nested( '<section class="digi-nslider" aria-roledescription="carrusel" aria-label="Diapositivas" data-digi-effect="slide" data-digi-loop="1">' === $open, 'La región: section con aria-roledescription, aria-label, efecto y bucle.' );
	check_nested( ! str_contains( $html, 'digi-nslider__pause' ) && str_contains( $html, 'aria-live="polite"' ), 'Sin reproducción automática: sin botón de pausa y región viva polite.' );
	$prev = strpos( $html, 'digi-nslider__prev' );
	$dots = strpos( $html, 'digi-nslider__dots' );
	$next = strpos( $html, 'digi-nslider__next' );
	$track = strpos( $html, 'digi-nslider__track' );
	check_nested( $prev < $dots && $dots < $next && $next < $track, 'Orden: anterior, puntos, siguiente y la pista (los controles antes que el contenido).' );
	check_nested( str_contains( $html, '<button type="button" class="digi-nslider__btn digi-nslider__prev" aria-controls="digi-nslider-a1b2c3d" aria-label="Diapositiva anterior"><svg' ) && str_contains( $html, 'aria-label="Diapositiva siguiente"' ), 'Flechas como botones con nombre y aria-controls de la pista.' );
	preg_match_all( '/<button type="button" class="digi-nslider__dot"[^>]*><\/button>/', $html, $dot_tags );
	check_nested( 3 === count( $dot_tags[0] ) && str_contains( $dot_tags[0][0], 'aria-label="Ir a la diapositiva 1" aria-current="true"' ) && ! str_contains( $dot_tags[0][1] . $dot_tags[0][2], 'aria-current' ) && str_contains( $dot_tags[0][2], 'aria-label="Ir a la diapositiva 3"' ), 'Puntos «Ir a la diapositiva N»; aria-current="true" sólo en el primero.' );
	check_nested( ! str_contains( $html, 'aria-selected' ) && ! str_contains( $html, 'role="tab' ), 'Puntos sin aria-selected ni rol de pestaña (ARIA válido).' );
	check_nested( str_contains( $html, '<div class="digi-nslider__dots" data-digi-dot="Ir a la diapositiva %d">' ) && str_contains( $html, '<div class="digi-nslider__track" id="digi-nslider-a1b2c3d" aria-live="polite">' ), 'Puntos visibles con su plantilla de nombre; la pista con id y región viva.' );
	preg_match_all( '/data-element_type="container"([^>]*)>/', $html, $wrappers );
	check_nested( 4 === count( $wrappers[1] ), 'Se pintan tres hijos y el contenedor anidado del primero.' );
	check_nested( str_contains( $html, 'data-id="c1aaaa" data-element_type="container" role="group" aria-roledescription="diapositiva" aria-label="1 de 3">' ) && str_contains( $html, 'aria-label="3 de 3">' ), 'Cada hijo es una diapositiva: role="group", aria-roledescription y «N de M».' );
	check_nested( str_contains( $html, 'data-id="n1aaaa" data-element_type="container"><span>' ), 'Un contenedor anidado dentro de una diapositiva no recibe los atributos.' );
	check_nested( 0 === hooked( 'elementor/frontend/container/should_render' ), 'El filtro de los hijos se retira después de pintar cada uno.' );
	check_nested( ! preg_match( '/\binert\b|<script|<img|style="/', $html ), 'El servidor no pone inert (sin JavaScript todo es alcanzable), ni <script>, <img> o estilos en línea.' );

	$auto = slider_html( $class, array( 'autoplay' => 'yes', 'autoplay_delay' => 100, 'effect' => 'fade' ), slides( 3 ) );
	check_nested( str_contains( tag( $auto, '/<section[^>]*>/' ), 'data-digi-effect="fade" data-digi-loop="1" data-digi-autoplay="2000" data-digi-hover="1"' ), 'Con reproducción automática: retraso acotado a 2000 ms y pausa al pasar el puntero.' );
	check_nested( 1 === preg_match( '/<section[^>]*><button type="button" class="digi-nslider__btn digi-nslider__pause" aria-controls="digi-nslider-a1b2c3d" aria-label="Pausar" data-digi-pause="Pausar" data-digi-play="Reanudar" hidden><svg/', $auto ), 'El botón «Pausar» es el primer hijo de la región (primero en el orden de foco) y espera oculto al script.' );
	check_nested( str_contains( $auto, 'aria-live="off"' ) && ! str_contains( $auto, 'aria-live="polite"' ), 'Con reproducción automática, región viva off.' );
	check_nested( str_contains( slider_html( $class, array( 'autoplay' => 'yes', 'autoplay_delay' => 99999, 'pause_on_hover' => '' ), slides( 3 ) ), 'data-digi-autoplay="20000">' ), 'Retraso acotado a 20000 ms; sin pausa al pasar el puntero, sin data-digi-hover.' );
	check_nested( str_contains( slider_html( $class, array( 'autoplay' => 'yes', 'autoplay_delay' => 'x' ), slides( 3 ) ), 'data-digi-autoplay="5000"' ), 'Un retraso no numérico vuelve a 5000 ms.' );

	$arrows = slider_html( $class, array( 'navigation' => 'arrows', 'arrows_position' => 'sides', 'loop' => '' ), slides( 3 ) );
	check_nested( str_contains( $arrows, '<section class="digi-nslider digi-nslider--sides"' ) && str_contains( $arrows, 'data-digi-dot="Ir a la diapositiva %d" hidden>' ) && str_contains( $arrows, 'digi-nslider__prev' ) && ! str_contains( $arrows, 'data-digi-loop' ), 'Sólo flechas, a los lados y sin bucle: puntos ocultos (siguen en el marcado para el editor).' );
	$dots_only = slider_html( $class, array( 'navigation' => 'dots', 'arrows_position' => 'sides' ), slides( 3 ) );
	check_nested( ! str_contains( $dots_only, 'digi-nslider__prev' ) && ! str_contains( $dots_only, 'digi-nslider--sides' ) && ! str_contains( $dots_only, 'digi-nslider__dots" data-digi-dot="Ir a la diapositiva %d" hidden' ), 'Sólo puntos: sin flechas y sin la clase de los lados.' );
	$forged = slider_html( $class, array( 'navigation' => 'none', 'effect' => 'cube', 'arrows_position' => 'x', 'carousel_label' => '<b>Novedades</b> "del" mes' ), slides( 3 ) );
	check_nested( str_contains( $forged, 'aria-label="Novedades &quot;del&quot; mes" data-digi-effect="slide"' ) && str_contains( $forged, 'digi-nslider__prev' ) && str_contains( $forged, 'digi-nslider__dot"' ) && ! str_contains( $forged, ' hidden>' ), 'Valores forjados: navegación «none» vuelve a flechas y puntos, efecto desconocido a slide, nombre sin HTML y escapado.' );
	check_nested( str_contains( slider_html( $class, array( 'carousel_label' => '   ' ), slides( 2 ) ), 'aria-label="Diapositivas"' ), 'Un nombre vacío vuelve al nombre por defecto.' );
	$two = slider_html( $class, array(), slides( 2 ) );
	check_nested( str_contains( $two, 'aria-label="2 de 2"' ) && 2 === substr_count( $two, 'digi-nslider__dot"' ) && ! str_contains( $two, '3 de' ), 'Tres filas y dos hijos: se pintan dos diapositivas («N de 2») y dos puntos.' );
	$one = slider_html( $class, array( 'autoplay' => 'yes' ), slides( 1 ) );
	check_nested( ! str_contains( $one, 'digi-nslider__prev' ) && ! str_contains( $one, 'digi-nslider__pause' ) && str_contains( $one, ' hidden>' ) && ! str_contains( $one, 'data-digi-autoplay' ), 'Una sola diapositiva: sin flechas, sin pausa, sin reproducción automática y puntos ocultos.' );
	check_nested( str_contains( slider_html( $class, array(), array() ), '<div class="digi-nslider__track" id="digi-nslider-a1b2c3d" aria-live="polite"></div></section>' ), 'Sin hijos, una pista vacía y nada más que romper.' );

	// 4b. Instantánea versionada de 4.3.0.169 (F8, V.10.4 y V.17.11): sin el contador, el HTML es byte a byte el de la
	// versión publicada. Las huellas se tomaron con el widget de 4.3.0.169 (`--print-baseline` las vuelve a calcular);
	// no dependen del historial de git. `counter` vacío, «no», o con una sola diapositiva, tampoco cambian nada.
	$baseline_cases = array(
		'defaults'   => array( array(), 3, true, 'a1b2c3d' ),
		'autoplay'   => array( array( 'autoplay' => 'yes', 'autoplay_delay' => 100, 'effect' => 'fade' ), 3, false, 'a1b2c3d' ),
		'arrows'     => array( array( 'navigation' => 'arrows', 'arrows_position' => 'sides', 'loop' => '' ), 3, false, 'a1b2c3d' ),
		'dots'       => array( array( 'navigation' => 'dots', 'arrows_position' => 'sides' ), 3, false, 'a1b2c3d' ),
		'forged'     => array( array( 'navigation' => 'none', 'effect' => 'cube', 'arrows_position' => 'x', 'carousel_label' => '<b>Novedades</b> "del" mes' ), 3, false, 'a1b2c3d' ),
		'two'        => array( array( 'carousel_label' => '   ' ), 2, false, 'a1b2c3d' ),
		'one'        => array( array( 'autoplay' => 'yes' ), 1, false, 'a1b2c3d' ),
		'none'       => array( array(), 0, false, 'a1b2c3d' ),
		'w1slide'    => array( array( 'loop' => '', 'carousel_label' => 'Novedades' ), 3, false, 'w1slide' ),
		'w2fade'     => array( array( 'effect' => 'fade', 'autoplay' => 'yes', 'autoplay_delay' => 2000, 'carousel_label' => 'Destacados' ), 3, false, 'w2fade' ),
		'w3scale'    => array( array( 'effect' => 'scale', 'navigation' => 'arrows', 'arrows_position' => 'sides', 'carousel_label' => 'Opiniones' ), 3, false, 'w3scale' ),
	);
	$baseline = array(
		'defaults' => 'e18d28f8b43af350ae4481755381ae74e9b4324a2245c0df42f29d1f499b529b',
		'autoplay' => 'efa6b71597fb2a4b3847f661c652da0056ecb48e843602ab5d0e0c6836d7355f',
		'arrows'   => 'f554a802c0f717152f0c8ef4486dea071692aafab9a38e5ecc0b7ab1b4dd70ee',
		'dots'     => '49092345dc29049a5f5d40d7570130f531dadfb0bad22886671d6441c5ace218',
		'forged'   => '096e5b43354dce4692daeaa9470505f6b71f8c0b5549d184d6805b4eebca9d65',
		'two'      => '8470ead07bfe45a5bc2d22960054a351982c0aff139480f23b7062569749c0a2',
		'one'      => 'a2df4f95c4ac561f35e2cd0c0d699362beff702e1bc91277d215b55c93d579ac',
		'none'     => 'd0afe0e5e1ba634064449dc7dccc57729defb489ed0e07791a22e3cafc75bf96',
		// Los tres sliders del fixture publicado con 4.3.0.169 (`tests/fixtures/f7b-nested-slider.html`).
		'w1slide'  => '566e034a1ab3f047d10110e9b360d31f16af531a98fa90c7ae4448e5942daf00',
		'w2fade'   => '7beb5614f735613e4743f21901f0d8ba438ce258960d0caa8a5517e41fc23db1',
		'w3scale'  => 'ac58978b301552ca44e35d90070edb117bd01028ac726fb1e277c74a41270ebf',
	);
	$computed = array();
	foreach ( $baseline_cases as $key => $case ) {
		$computed[ $key ] = hash( 'sha256', slider_html( $class, $case[0], slides( $case[1], $case[2] ), $case[3] ) );
	}
	if ( in_array( '--print-baseline', $argv ?? array(), true ) ) {
		foreach ( $computed as $key => $sha ) { echo "\t\t'" . $key . "' => '" . $sha . "',\n"; }
	}
	foreach ( $baseline_cases as $key => $case ) {
		check_nested( isset( $baseline[ $key ] ) && $baseline[ $key ] === $computed[ $key ], $key . ': sin contador, el HTML es el de 4.3.0.169 (instantánea versionada).' );
		foreach ( array( '', 'no', 'yes ' ) as $off ) {
			check_nested( $computed[ $key ] === hash( 'sha256', slider_html( $class, $case[0] + array( 'counter' => $off ), slides( $case[1], $case[2] ), $case[3] ) ), $key . ': counter = «' . $off . '» no cambia el HTML.' );
		}
	}
	check_nested( $computed['one'] === hash( 'sha256', slider_html( $class, $baseline_cases['one'][0] + array( 'counter' => 'yes' ), slides( 1 ) ) ), 'Con una sola diapositiva, el contador activo no pinta nada.' );

	// 5. Plantilla del editor y punto nuevo del repetidor.
	$template = $widget->template();
	check_nested( str_contains( $template, "_.contains( [ 'slide', 'fade', 'scale' ], settings.effect )" ) && str_contains( $template, "_.contains( [ 'both', 'arrows', 'dots' ], settings.navigation )" ), 'La plantilla aplica las mismas listas cerradas que el servidor.' );
	check_nested( str_contains( $template, 'Math.max( 2000, Math.min( 20000, parseInt( settings.autoplay_delay, 10 ) || 5000 ) )' ) && str_contains( $template, "'Diapositivas'" ), 'Mismos topes de retraso y el mismo nombre por defecto.' );
	foreach ( array( 'aria-roledescription="carrusel"', 'data-digi-start="{{ digiStart }}"', 'data-digi-pause="Pausar"', 'data-digi-play="Reanudar"', 'aria-label="Diapositiva anterior"', 'aria-label="Diapositiva siguiente"', 'data-digi-dot="Ir a la diapositiva %d"', 'aria-label="Ir a la diapositiva {{ index + 1 }}"', 'class="digi-nslider__track" id="{{ digiTrack }}"', '<# if ( digiAuto ) { #>off<# } else { #>polite<# } #>' ) as $token ) {
		check_nested( str_contains( $template, $token ), 'La plantilla lleva ' . $token . '.' );
	}
	check_nested( substr_count( $template, '<#' ) === substr_count( $template, '#>' ), 'Bloques <# #> equilibrados en la plantilla.' );
	$single = $widget->single_template();
	check_nested( 1 === preg_match( '/<button type="button" class="digi-nslider__dot" aria-controls="\{\{ digiTrack \}\}" aria-label="Ir a la diapositiva \{\{ digiN \}\}"><\/button>/', $single ) && str_contains( $single, 'view.collection.length + 1' ), 'El punto nuevo del repetidor: un botón con su número.' );

	// 6. Textos: dominio de las traducciones propias, en español y sin proveedores.
	$widget_src = file_get_contents( $widget_file );
	preg_match_all( "/(?:__|esc_html__|esc_attr__)\(\s*'[^']*',\s*'([a-z-]+)'\s*\)/", $widget_src . $module_src, $domains );
	check_nested( count( $domains[1] ) >= 30 && array( 'pro-elements' ) === array_values( array_unique( $domains[1] ) ), 'Todos los textos con el dominio pro-elements.' );
	check_nested( ! preg_match( '/swiper|bricks|elementor\.com|https?:\/\//i', $widget_src . file_get_contents( $root . '/modules/digitalisimo-f7b/assets/js/nested-slider.js' ) . file_get_contents( $root . '/modules/digitalisimo-f7b/assets/css/nested-slider.css' ) ), 'Sin nombres de librerías de terceros ni URL en el widget, la hoja y el script.' );
	check_nested( ! preg_match( "/__\(\s*'(Slides?|Carousel|Next|Previous|Pause|Play|Go to)\\b/", $widget_src ), 'Textos visibles en español.' );

	// 7. Fixture del navegador: tres sliders con el HTML de render() y la plantilla del editor.
	$cases = array(
		'w1slide' => array( 'settings' => array( 'loop' => '', 'carousel_label' => 'Novedades' ), 'title' => 'slide, flechas y puntos, sin bucle' ),
		'w2fade'  => array( 'settings' => array( 'effect' => 'fade', 'autoplay' => 'yes', 'autoplay_delay' => 2000, 'carousel_label' => 'Destacados' ), 'title' => 'fade con reproducción automática' ),
		'w3scale' => array( 'settings' => array( 'effect' => 'scale', 'navigation' => 'arrows', 'arrows_position' => 'sides', 'carousel_label' => 'Opiniones' ), 'title' => 'scale, sólo flechas a los lados, con bucle' ),
	);
	$blocks = '';
	foreach ( $cases as $id => $case ) {
		$section = slider_html( $class, $case['settings'], slides( 3 ), $id );
		$blocks .= "<!-- {$case['title']} -->\n<div class=\"elementor-element elementor-element-{$id} elementor-widget elementor-widget-digitalisimo-nested-slider\" data-id=\"{$id}\" data-element_type=\"widget\" data-widget_type=\"digitalisimo-nested-slider.default\">\n<!-- digi:server:{$id} -->" . $section . "<!-- /digi:server -->\n</div>\n";
	}
	$expected_template = "<script type=\"text/template\" id=\"tmpl-digi-nslider\">\n" . trim( $template ) . "\n</script>";
	$page = "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Slider anidado ligero</title>\n"
		. "<!-- Generado por tests/elements-f7b-nested.php --write-fixture: no editar a mano. Lo usan elements-f7b-nested-slider.mjs y elements-f7b-nested-slider-chrome.mjs. -->\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/elementor/assets/css/frontend.min.css\">\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-f7b/assets/css/nested-slider.css\">\n"
		. "<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1a1a1a;background:#fff}.e-con{--display:flex;--flex-direction:column;--padding-top:16px;--padding-bottom:16px;--padding-left:16px;--padding-right:16px;flex-direction:var(--flex-direction)}.elementor-widget-digitalisimo-nested-slider{margin:24px 16px}.digi-test-grid{--display:grid}.digi-after{padding:16px}</style>\n</head>\n<body>\n"
		. "<div class=\"elementor elementor-1\" data-elementor-type=\"wp-page\" data-elementor-id=\"1\">\n" . $blocks . "</div>\n"
		. "<p class=\"digi-after\"><a href=\"#fin\" id=\"fin\">Enlace después de los sliders</a></p>\n"
		. "<div class=\"digi-test-hidden\"><div class=\"elementor-element e-con e-flex\" hidden>Contenedor flex oculto</div><div class=\"elementor-element e-con e-grid digi-test-grid\" hidden>Contenedor grid oculto</div><div class=\"elementor-column\" hidden>Columna oculta</div></div>\n"
		. $expected_template . "\n"
		. "<script src=\"../../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/nested-slider.js\" defer></script>\n</body>\n</html>\n";
	if ( in_array( '--write-fixture', $argv ?? array(), true ) ) {
		file_put_contents( $fixture, $page );
		echo "Fixture escrito: tests/fixtures/f7b-nested-slider.html\n";
	}
	check_nested( is_file( $fixture ), 'Existe el fixture del navegador (se genera con --write-fixture).' );
	check_nested( file_get_contents( $fixture ) === $page, 'El fixture del navegador es exactamente el render y la plantilla actuales (regenerar con --write-fixture).' );

	echo "F7b, slider anidado: {$checks} comprobaciones en verde.\n";
}
