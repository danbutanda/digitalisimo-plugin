<?php
/**
 * Arnés del Slider de dispositivos sin WordPress ni Elementor: imitaciones mínimas de `Widget_Base`, `Repeater`,
 * `Controls_Manager` y de las funciones de WordPress que usa el widget, y `ds_widget()` / `ds_render()`.
 * Lo usan tests/elements-device-slider.php (marcado) y tests/elements-device-slider-chrome.mjs (que pide el HTML real
 * del widget con `php tests/fixtures/device-slider-harness.php` y la lista de ajustes en JSON por la entrada estándar).
 * DS_WIDGET_DIR cambia la carpeta de la que se carga el widget (para pintar una versión anterior en las capturas).
 */
namespace {
	require_once __DIR__ . '/php74.php';
}
namespace Elementor {
	if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
		class Widget_Base {
			public $settings = array(); public $controls = array(); public $sections = array(); private $attributes = array();
			public function get_settings_for_display() { return $this->settings; }
			public function get_settings() { return $this->settings; }
			public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
			public function end_controls_section() {}
			public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
			public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
			public function add_link_attributes( $name, $link ) {
				$this->attributes[ $name ] = array( 'href' => $link['url'] );
				if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; }
				if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); }
			}
			public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
			public function get_render_attribute_string( $name ) {
				$out = array();
				foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; }
				return implode( ' ', $out );
			}
		}
		class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
		class Controls_Manager { const SELECT = 'select'; const GALLERY = 'gallery'; const MEDIA = 'media'; const TEXT = 'text'; const URL = 'url'; const REPEATER = 'repeater'; const SLIDER = 'slider'; const COLOR = 'color'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const TAB_STYLE = 'style'; }
	}
}
namespace {
	defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ );
	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
		function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
		function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
		function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
		function absint( $v ) { return abs( (int) $v ); }
		function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ); }
	}
	require_once ( getenv( 'DS_WIDGET_DIR' ) ?: __DIR__ . '/../../digitalisimo-elements/modules/digitalisimo-widgets' ) . '/class-device-slider.php';

	/** Widget con sus controles registrados. */
	function ds_widget() {
		$widget = new \Digitalisimo\Elements\Device_Slider_Widget();
		( digi_test_accessible( new \ReflectionMethod( $widget, 'register_controls' ) ) )->invoke( $widget );
		return $widget;
	}

	/** HTML del widget con estos ajustes (los valores por defecto de los controles, como hace Elementor, si se piden). */
	function ds_render( array $settings, $with_defaults = false ) {
		$widget = ds_widget();
		if ( $with_defaults ) {
			foreach ( $widget->controls as $name => $control ) {
				if ( array_key_exists( 'default', $control ) && ! array_key_exists( $name, $settings ) ) { $settings[ $name ] = $control['default']; }
			}
		}
		$widget->settings = $settings;
		ob_start();
		( digi_test_accessible( new \ReflectionMethod( $widget, 'render' ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	}

	// Desde la línea de órdenes: imprime el HTML de cada juego de ajustes de la lista JSON, separados por \f.
	if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
		$list = json_decode( (string) ( $argv[1] ?? stream_get_contents( STDIN ) ), true );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $settings ) { $out[] = ds_render( (array) $settings, true ); }
		echo implode( "\f", $out );
	}
}
