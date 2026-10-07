<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	class Controls_Manager { const TEXT = 'text'; const ICONS = 'icons'; const SELECT = 'select'; const SLIDER = 'slider'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function check_scroll_button( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-scroll-button.php';
	$widget = new \Digitalisimo\Elements\Scroll_Button_Widget();
	check_scroll_button( 'digitalisimo-scroll-button' === $widget->get_name() && array( 'digitalisimo-scroll-button' ) === $widget->get_style_depends() && array( 'digitalisimo-scroll-button' ) === $widget->get_script_depends(), 'Los recursos deben cargarse sólo con este widget.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_scroll_button( isset( $widget->controls['section_id'], $widget->controls['duration'], $widget->controls['offset'], $widget->controls['scroll_button_position'] ), 'Faltan los controles de desplazamiento.' );
	$widget->settings = array( 'section_id' => 'sección-final', 'scroll_button_text' => '<script>Continuar</script>', 'duration' => array( 'size' => 8000 ), 'offset' => array( 'size' => -500 ), 'scroll_button_position' => 'bottom-right' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_scroll_button( str_contains( $html, 'href="#sección-final"' ) && str_contains( $html, 'data-duration="5000"' ) && str_contains( $html, 'data-offset="-200"' ), 'El destino o los límites numéricos son incorrectos.' );
	check_scroll_button( str_contains( $html, 'digi-scroll-button--bottom-right' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ), 'La salida debe escapar el texto y validar la ubicación.' );
	$widget->settings['section_id'] = 'id con espacio';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $invalid = ob_get_clean();
	check_scroll_button( '' === $invalid, 'Un ID inválido no debe generar un enlace roto.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget ); $editor = ob_get_clean();
	check_scroll_button( str_contains( $editor, 'section_id' ) && str_contains( $editor, 'digi-scroll-button__link' ), 'La vista previa del editor debe mostrar el enlace.' );
	echo "DIGITALÍSIMO Elements: Botón de desplazamiento validado.\n";
}
