<?php
/**
 * Dobles de las pruebas de fondos de F7a (`elements-design-gradient.php` y `elements-design-backgrounds.php`).
 *
 * Carga del núcleo incluido lo que se puede cargar sin WordPress: `Stylesheet` (V-B3: las reglas pasan por el
 * analizador real), `Group_Control_Base` y `Group_Control_Background` (los selectores y los nombres de los controles
 * en los que se inyecta son los reales) y el saneador SVG. Lo demás son dobles mínimos: la pila de controles imita
 * `add_control()`, `add_responsive_control()` (duplicación por dispositivo, como al generar el CSS), la inyección y
 * los popovers; `Post` sólo da su hoja y su selector único.
 *
 * Las clases que comprueban las guardas (`Repeater`, el saneador) se declaran con `bg_declare_late()`, para que cada
 * prueba pueda llamar antes a un `init()` sin ellas.
 */

namespace Elementor {
	class Controls_Manager {
		const TAB_CONTENT    = 'content';
		const TAB_STYLE      = 'style';
		const TAB_ADVANCED   = 'advanced';
		const SECTION        = 'section';
		const ALERT          = 'alert';
		const CHOOSE         = 'choose';
		const COLOR          = 'color';
		const GALLERY        = 'gallery';
		const HIDDEN         = 'hidden';
		const MEDIA          = 'media';
		const NUMBER         = 'number';
		const POPOVER_TOGGLE = 'popover_toggle';
		const RAW_HTML       = 'raw_html';
		const REPEATER       = 'repeater';
		const SELECT         = 'select';
		const SLIDER         = 'slider';
		const SWITCHER       = 'switcher';
		const TEXT           = 'text';
	}

	final class BG_Breakpoint {
		private $value;
		private $direction;
		public function __construct( $value, $direction ) {
			$this->value     = $value;
			$this->direction = $direction;
		}
		public function get_value() {
			return $this->value;
		}
		public function get_direction() {
			return $this->direction;
		}
	}

	final class BG_Breakpoints {
		public $active = array();
		public function __construct( array $active ) {
			$this->active = $active;
		}
		public function get_active_breakpoints() {
			return $this->active;
		}
		public function get_device_min_breakpoint( $device ) {
			return isset( $this->active[ $device ] ) ? $this->active[ $device ]->get_value() : 0;
		}
	}

	class Plugin {
		public static $instance;
		public $breakpoints;
	}

	/** Pila de controles mínima, con el orden, las secciones, la inyección y los popovers del núcleo. */
	abstract class Controls_Stack {
		public $controls     = array();
		public $redeclared   = array();
		public $data         = array();
		protected $name      = '';
		protected $type      = '';
		protected $id        = 'abc1234';
		private $section     = null;
		private $injection   = null;
		private $in_popover  = false;

		public function __construct( string $name = '', string $type = '', array $settings = array(), string $id = 'abc1234' ) {
			$this->name = $name;
			$this->type = $type;
			$this->data = $settings;
			$this->id   = $id;
		}
		public function get_name() {
			return $this->name;
		}
		public function get_type() {
			return $this->type;
		}
		public function get_id() {
			return $this->id;
		}
		public function get_unique_selector() {
			return '.elementor-element-' . $this->id;
		}
		public function get_controls( $id = null ) {
			if ( null === $id ) {
				return $this->controls;
			}
			return isset( $this->controls[ $id ] ) ? $this->controls[ $id ] : null;
		}
		public function get_current_section() {
			return $this->section;
		}
		public function start_controls_section( $id, array $args = array() ) {
			$this->add_control( $id, $args + array( 'type' => Controls_Manager::SECTION ) );
			$this->section = array( 'section' => $id, 'tab' => isset( $args['tab'] ) ? $args['tab'] : '' );
		}
		public function end_controls_section() {
			$this->section = null;
		}
		public function start_injection( array $position ) {
			if ( null !== $this->injection ) {
				throw new \RuntimeException( 'Inyección ya abierta.' );
			}
			$keys  = array_keys( $this->controls );
			$index = array_search( $position['of'], $keys, true );
			if ( false === $index ) {
				$this->injection = false;
				return;
			}
			$this->injection = array(
				'index'   => $index + 1,
				'section' => isset( $this->controls[ $position['of'] ]['section'] ) ? $this->controls[ $position['of'] ]['section'] : '',
			);
		}
		public function end_injection() {
			$this->injection = null;
		}
		public function start_popover() {
			$this->in_popover = true;
		}
		public function end_popover() {
			$this->in_popover = false;
		}
		public function add_group_control( $type, array $args ) {
			$group = new Group_Control_Background();
			$group->add_controls( $this, $args );
		}
		public function add_control( $id, array $args, $options = array() ) {
			if ( isset( $this->controls[ $id ] ) ) {
				$this->redeclared[] = $id;
				return false;
			}
			$args['name'] = $id;
			if ( ! isset( $args['section'] ) ) {
				$args['section'] = is_array( $this->injection ) ? $this->injection['section'] : ( $this->section ? $this->section['section'] : '' );
			}
			if ( $this->in_popover ) {
				$args['popover'] = array();
			}
			if ( is_array( $this->injection ) ) {
				$index          = $this->injection['index']++;
				$this->controls = array_slice( $this->controls, 0, $index, true ) + array( $id => $args ) + array_slice( $this->controls, $index, null, true );
			} else {
				$this->controls[ $id ] = $args;
			}
			return true;
		}
		/** Como el núcleo al generar el CSS (duplicación `on`): una copia por dispositivo activo. */
		public function add_responsive_control( $id, array $args, $options = array() ) {
			$devices = array( 'desktop' => array( 'max' => 'desktop' ) );
			foreach ( array_reverse( Plugin::$instance->breakpoints->get_active_breakpoints(), true ) as $name => $bp ) {
				$devices[ $name ] = array( $bp->get_direction() => $name );
			}
			foreach ( $devices as $device => $responsive ) {
				$copy = $args;
				unset( $copy['device_args'] );
				if ( 'desktop' !== $device ) {
					unset( $copy['default'] );
				}
				$copy['responsive'] = $responsive;
				$this->add_control( 'desktop' === $device ? $id : $id . '_' . $device, $copy, $options );
			}
		}
		/** Valores por defecto de escritorio más lo guardado. */
		public function get_settings() {
			$settings = $this->data;
			foreach ( $this->controls as $name => $control ) {
				if ( ! array_key_exists( $name, $settings ) && array_key_exists( 'default', $control ) ) {
					$settings[ $name ] = $control['default'];
				}
			}
			return $settings;
		}
	}

	class Element_Base extends Controls_Stack {}

	class BG_Common extends Controls_Stack {
		const WRAPPER_SELECTOR_CHILD = '{{WRAPPER}} > .elementor-widget-container';
	}

	class BG_Common_Optimized extends Controls_Stack {
		const WRAPPER_SELECTOR_CHILD = '{{WRAPPER}}';
	}
}

namespace Elementor\Modules\DynamicTags {
	class Module {
		const POST_META_CATEGORY = 'post_meta';
		const URL_CATEGORY       = 'url';
	}
}

namespace Elementor\Core\Files\CSS {
	class Post {
		public $post_id;
		private $sheet;
		public function __construct( $post_id ) {
			$this->post_id = $post_id;
			$this->sheet   = new \Elementor\Stylesheet();
			foreach ( \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $name => $bp ) {
				$this->sheet->add_device( $name, $bp->get_value() );
			}
		}
		public function get_stylesheet() {
			return $this->sheet;
		}
		public function get_element_unique_selector( $element ) {
			return '.elementor-' . $this->post_id . ' .elementor-element' . $element->get_unique_selector();
		}
	}
}

namespace Elementor\Core\DynamicTags {
	class Dynamic_CSS extends \Elementor\Core\Files\CSS\Post {}
}

namespace Digitalisimo\Elements\Import\Kits {
	/** Doble del contexto de importación de K: sólo `active()` (durante una importación, Library no crea copias). */
	final class Context {
		public static $active = false;

		public static function active(): bool {
			return self::$active;
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
		define( 'ELEMENTOR_VERSION', '4.3.5' );
	}

	$GLOBALS['bg_hooks'] = array();

	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['bg_hooks'][ $hook ][] = array( $callback, $args );
		return true;
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_action( $hook, $callback, $priority, $args );
	}
	function do_action( $hook, ...$values ) {
		foreach ( isset( $GLOBALS['bg_hooks'][ $hook ] ) ? $GLOBALS['bg_hooks'][ $hook ] : array() as $entry ) {
			call_user_func_array( $entry[0], array_slice( $values, 0, $entry[1] ) );
		}
	}
	function apply_filters( $hook, $value, ...$values ) {
		foreach ( isset( $GLOBALS['bg_hooks'][ $hook ] ) ? $GLOBALS['bg_hooks'][ $hook ] : array() as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $values ), 0, $entry[1] ) );
		}
		return $value;
	}
	function esc_html__( $text ) {
		return $text;
	}
	function esc_html_x( $text ) {
		return $text;
	}
	function __( $text ) {
		return $text;
	}

	/** Número de callbacks de un gancho. */
	function bg_hook_count( string $hook ): int {
		return isset( $GLOBALS['bg_hooks'][ $hook ] ) ? count( $GLOBALS['bg_hooks'][ $hook ] ) : 0;
	}

	/** Veces que un callback concreto está en un gancho. */
	function bg_callback_count( string $hook, $callback ): int {
		$count = 0;
		foreach ( isset( $GLOBALS['bg_hooks'][ $hook ] ) ? $GLOBALS['bg_hooks'][ $hook ] : array() as $entry ) {
			$count += $entry[0] === $callback ? 1 : 0;
		}
		return $count;
	}

	function bg_assert( $condition, string $message ): void {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}

	/** Puntos de corte activos del doble (`[ nombre => [ valor, dirección ] ]`, del más pequeño al mayor). */
	function bg_breakpoints( array $active ): void {
		$objects = array();
		foreach ( $active as $name => $data ) {
			$objects[ $name ] = new \Elementor\BG_Breakpoint( $data[0], $data[1] );
		}
		\Elementor\Plugin::$instance              = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->breakpoints = new \Elementor\BG_Breakpoints( $objects );
	}

	/** `Repeater` y el saneador, declarados tarde a propósito (guardas). */
	function bg_declare_late(): void {
		if ( ! class_exists( 'Elementor\\Repeater', false ) ) {
			eval( 'namespace Elementor; class Repeater extends Controls_Stack {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}
		if ( ! class_exists( 'Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer', false ) ) {
			require BG_ROOT . 'elementor/core/utils/svg/svg-sanitizer.php';
		}
	}

	/**
	 * Regla del núcleo para un control (`add_control_rules()` reducido a SIZE, UNIT y VALUE, con referencias a otros
	 * controles), escrita como cadena igual que el núcleo: así la prueba ve la fusión real del `Stylesheet`.
	 */
	function bg_core_rule( \Elementor\Core\Files\CSS\Post $css, \Elementor\Controls_Stack $element, string $control_name, array $settings ): void {
		$control = $element->get_controls( $control_name );
		$wrapper = $css->get_element_unique_selector( $element );
		$suffix  = '';
		if ( ! empty( $control['responsive'] ) && ! isset( $control['responsive']['max'] ) || ( isset( $control['responsive']['max'] ) && 'desktop' !== $control['responsive']['max'] ) ) {
			$suffix = '_' . ( isset( $control['responsive']['max'] ) ? $control['responsive']['max'] : $control['responsive']['min'] );
		}
		foreach ( $control['selectors'] as $selector => $template ) {
			$failed = false;
			$output = preg_replace_callback(
				'/{{(?:([^.}]+)\.)?([^}| ]*)}}/',
				function ( $match ) use ( $control_name, $settings, $suffix, $element, &$failed ) {
					$name = '' !== $match[1] ? $match[1] : $control_name;
					// Como parse_property_placeholder(): otro control responsivo se lee en el mismo dispositivo, sin herencia.
					if ( '' !== $match[1] && '' !== $suffix && null !== $element->get_controls( $name . $suffix ) ) {
						$name .= $suffix;
					}
					$value = isset( $settings['__globals__'][ $name ] ) && preg_match( '/id=(\w+)$/', $settings['__globals__'][ $name ], $g ) ? 'var( --e-global-color-' . $g[1] . ' )' : ( isset( $settings[ $name ] ) ? $settings[ $name ] : null );
					if ( 'VALUE' === $match[2] ) {
						$out = is_array( $value ) ? '' : (string) $value;
					} else {
						$out = is_array( $value ) && isset( $value[ strtolower( $match[2] ) ] ) ? (string) $value[ strtolower( $match[2] ) ] : '';
					}
					if ( '' === $out ) {
						$failed = true;
					}
					return $out;
				},
				$template
			);
			if ( ! $failed ) {
				$query = null;
				if ( ! empty( $control['responsive'] ) ) {
					$query = array_intersect_key( $control['responsive'], array( 'min' => 1, 'max' => 1 ) );
					if ( isset( $query['max'] ) && 'desktop' === $query['max'] ) {
						$query = null;
					}
				}
				$css->get_stylesheet()->add_rules( str_replace( '{{WRAPPER}}', $wrapper, $selector ), $output, $query );
			}
		}
	}

	define( 'BG_ROOT', dirname( __DIR__, 3 ) . '/digitalisimo-elements/' );
	require BG_ROOT . 'elementor/includes/stylesheet.php';
	require BG_ROOT . 'elementor/includes/interfaces/group-control.php';
	require BG_ROOT . 'elementor/includes/controls/groups/base.php';
	require BG_ROOT . 'elementor/includes/controls/groups/background.php';
	require BG_ROOT . 'modules/digitalisimo-design/class-module.php';
	require BG_ROOT . 'modules/digitalisimo-design/class-css.php';
	\Digitalisimo\Elements\Design\Module::register_autoloader();
}
