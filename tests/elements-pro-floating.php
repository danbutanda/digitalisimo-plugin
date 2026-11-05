<?php
/**
 * Botones de contacto y Barra flotante de C8 (frente P, contrato docs/pro/c8.md): los widgets propios
 * `digitalisimo-contact-buttons` (nueve presentaciones) y `digitalisimo-floating-bar` (dos), que sustituyen a las
 * variantes de PRO `contact-buttons-var-<n>` y `floating-bars-var-<n>`. Sin WordPress, con dobles mínimos de Elementor
 * (los de tests/elements-pro-link-in-bio.php) y los archivos reales de la base del núcleo y de PRO:
 *
 * - módulo: declara cada widget, su título, sus presentaciones y su base del núcleo; hoja común (≤ 4 KB, foco visible y
 *   movimiento reducido) y una por presentación sin `--e-global-*`, `url()` ni clases del núcleo; script propio sin jQuery;
 * - sonda: para cada presentación, la base del núcleo con la configuración copiada de PRO registra los mismos controles
 *   que la variante de PRO (orden, tipo, condiciones, selectores, opciones, dispositivos y valores por defecto salvo los
 *   textos, en español);
 * - unión (`Variants::merge`): cada control de cada variante está en el widget propio con `presentation` en su condición
 *   cuando no está en todas; `rewrite()` pasa clases y variables del núcleo a las propias;
 * - marcado: el render propio de cada presentación es el de PRO con las clases propias, salvo nombres accesibles (en
 *   español), ids por elemento, el aspa en SVG, el estado anunciado del cupón y sin diálogos sin foco;
 * - ninguno de los archivos nombra lo que veta `scripts/build-packages.sh`.
 *
 * Uso: node …/php-wasm-cli tests/elements-pro-floating.php (php-wasm sale con 0 aunque falle: buscar «FALLA»).
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $recorded = array();
		public $sections = array();
		public $attrs    = array();
		public function __construct( $data = array(), $args = null ) {
			if ( isset( $data['settings'] ) ) {
				$this->settings = $data['settings'];
			}
		}
		public function print_render() { $this->render(); }
		public function build(): array {
			$this->recorded = array();
			$this->register_controls();
			return $this->recorded;
		}
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) { return $this->get_settings( $key ); }
		public function is_type_instance() { return empty( $this->settings ); }
		public function get_id() { return 'abc1234'; }
		public function get_name() { return 'test'; }
		public function start_controls_section( $name, $args = array() ) { $this->recorded[ $name ] = array( 'type' => 'section' ) + $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->recorded[ $name ] = array( 'type' => 'tabs' ) + $args; }
		public function end_controls_tabs() {}
		public function start_controls_tab( $name, $args = array() ) { $this->recorded[ $name ] = array( 'type' => 'tab' ) + $args; }
		public function end_controls_tab() {}
		public function start_popover() {}
		public function end_popover() {}
		public function start_injection( $args ) {}
		public function end_injection() {}
		public function add_control( $name, $args, $options = array() ) { $this->recorded[ $name ] = $args; }
		public function add_responsive_control( $name, $args, $options = array() ) { $this->recorded[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args = array(), $options = array() ) { $this->recorded[ 'group:' . $args['name'] ] = array( 'type' => 'group-' . $type ) + $args; }
		public function update_control( $name, $args, $options = array() ) { if ( isset( $this->recorded[ $name ] ) ) { $this->recorded[ $name ] = array_merge( $this->recorded[ $name ], $args ); } }
		public function get_controls( $name = null ) { return null === $name ? $this->recorded : ( $this->recorded[ $name ] ?? null ); }
		public function add_render_attribute( $key, $attr, $value = null ) {
			$pairs = is_array( $attr ) ? $attr : array( $attr => $value );
			foreach ( $pairs as $k => $v ) {
				$this->attrs[ $key ][ $k ] = array_merge( $this->attrs[ $key ][ $k ] ?? array(), (array) $v );
			}
		}
		public function get_render_attribute_string( $key ) {
			$out = array();
			foreach ( $this->attrs[ $key ] ?? array() as $k => $v ) {
				$out[] = $k . '="' . htmlspecialchars( implode( ' ', $v ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $key ) { echo $this->get_render_attribute_string( $key ); }
		public function add_link_attributes( $key, $link ) { if ( ! empty( $link['url'] ) ) { $this->add_render_attribute( $key, 'href', $link['url'] ); } }
	}
	class Repeater extends Widget_Base {
		public function get_controls( $name = null ) {
			$out = array();
			foreach ( $this->recorded as $id => $c ) {
				$out[ $id ] = array( 'name' => $id ) + $c;
			}
			return $out;
		}
	}
	class Controls_Manager {
		const ALERT = 'alert'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const DIVIDER = 'divider'; const HEADING = 'heading';
		const MEDIA = 'media'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const SWITCHER = 'switcher';
		const TAB_CONTENT = 'content'; const TAB_STYLE = 'style'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url';
		const CHOOSE = 'choose'; const SELECT2 = 'select2'; const GALLERY = 'gallery'; const NUMBER2 = 'number'; const ICONS = 'icons'; const NUMBER = 'number'; const HIDDEN = 'hidden';
		const ANIMATION = 'animation'; const HOVER_ANIMATION = 'hover_animation'; const RAW_HTML = 'raw_html'; const TAB_ADVANCED = 'advanced';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Shapes {
		const FILTER_EXCLUDE = 'exclude';
		const FILTER_INCLUDE = 'include';
		public static function get_shapes( $shape = null ) { return array( 'mountains' => array( 'title' => 'Montañas', 'has_negative' => true ), 'wave' => array( 'title' => 'Ola', 'height_only' => true ) ); }
		public static function filter_shapes( $by, $filter = self::FILTER_INCLUDE ) { return array_filter( self::get_shapes(), function( $s ) use ( $by, $filter ) { return self::FILTER_INCLUDE === $filter xor empty( $s[ $by ] ); } ); }
		public static function get_shape_path( $name, $negative = false ) { return ''; }
	}
	class Utils {
		public static function get_placeholder_image_src() { return 'https://sitio.test/placeholder.png'; }
		public static function file_get_contents( $f ) { return ''; }
		public static function parse_custom_attributes( $s ) { return array(); }
		public static function has_pro() { return false; }
		public static function print_unescaped_internal_string( $s ) { echo $s; }
		public static function validate_html_tag( $t ) { return in_array( $t, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ? $t : 'div'; }
	}
	class Icons_Manager {
		public static function render_icon( $icon, $attrs = array() ) { echo '<svg class="e-font-icon-svg e-' . str_replace( ' ', '-', (string) ( $icon['value'] ?? '' ) ) . '" aria-hidden="true"></svg>'; return true; }
	}
	class Plugin {
		public static $instance;
		public $experiments;
		public $breakpoints;
		public $controls_manager;
		public static function elementor() { return self::$instance; }
	}
}
namespace Elementor\Modules\FloatingButtons\Control {
	class Hover_Animation_Floating_Buttons { const TYPE = 'hover_animation_floating_buttons'; }
}
namespace ElementorPro\Base {
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace Elementor\Modules\FloatingButtons\Documents {
	class Floating_Buttons { public static function get_title() { return 'Floating Element'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function _x( $v ) { return $v; }
	function esc_html_x( $v ) { return $v; }
	function is_rtl() { return false; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v, $p = null ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
		$out = '<img src="https://sitio.test/' . $id . '-' . $size . '.jpg" srcset="https://sitio.test/' . $id . '-1024.jpg 1024w" alt="Texto ' . $id . '"';
		foreach ( $attrs as $k => $v ) { $out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"'; }
		return $out . '>';
	}
	function wp_get_attachment_url( $id ) { return 'https://sitio.test/' . $id . '.jpg'; }
	function get_the_ID() { return 42; }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function check_fl( $ok, $message ) {
		$GLOBALS['fl_checks'] = ( $GLOBALS['fl_checks'] ?? 0 ) + 1;
		if ( ! $ok ) {
			echo 'FALLA: ' . $message . "\n";
			$GLOBALS['fl_failed'] = true;
		}
	}
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return true; } };
	\Elementor\Plugin::$instance->breakpoints = new class() {
		public function get_active_breakpoints() { $b = new class() { public function get_label() { return 'Móvil'; } }; return array( 'tablet' => $b, 'mobile' => $b ); }
		public function get_active_devices_list( $a = array() ) { return array( 'desktop', 'tablet', 'mobile' ); }
	};
	\Elementor\Plugin::$instance->controls_manager = new class() { public function __call( $n, $a ) { return null; } };

