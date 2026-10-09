<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
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
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const URL = 'url'; const ICONS = 'icons'; const TAB_STYLE = 'style'; const CHOOSE = 'choose'; const COLOR = 'color'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function check_call_out( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-call-out.php';
	$widget = new \Digitalisimo\Elements\Call_Out_Widget();
	check_call_out( array( 'digitalisimo-call-out' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'Call Out sólo debe cargar CSS propio.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_call_out( isset( $widget->controls['button_icon'], $widget->controls['link'] ), 'Faltan los controles de icono y enlace.' );
	$widget->settings = array( 'title' => 'Consulta', 'description' => 'Descripción', 'button_text' => 'Abrir', 'button_icon' => array( 'value' => 'fas fa-star' ), 'link' => array( 'url' => 'https://example.test/contacto', 'is_external' => true, 'nofollow' => true ) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_call_out( str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="nofollow noopener noreferrer"' ), 'El enlace externo debe proteger la pestaña y conservar nofollow.' );
	check_call_out( str_contains( $html, '<i aria-hidden="true">' ) && str_contains( $html, '<h3 ' ), 'El icono y el título semántico deben conservarse.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_call_out( str_contains( $editor, 'elementor.helpers.renderIcon' ) && str_contains( $editor, 'settings.link.is_external' ) && str_contains( $editor, 'noopener noreferrer' ), 'El editor debe mostrar el icono y respetar los atributos del enlace.' );
	echo "DIGITALÍSIMO Elements: Call Out validado.\n";
}
