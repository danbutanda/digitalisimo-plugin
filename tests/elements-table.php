<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function check_table( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-table.php';
	$widget = new \Digitalisimo\Elements\Table_Widget();
	check_table( array( 'digitalisimo-table' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'La tabla debe cargar sólo su CSS.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_table( isset( $widget->controls['content'], $widget->controls['delimiter'], $widget->controls['first_column_header'] ), 'Deben existir datos, separador y encabezados de fila.' );
	$widget->settings = array( 'caption' => 'Precios <script>mal</script>', 'content' => "Producto,Descripción,Precio\nServicio,\"Incluye diseño, soporte\",$100\nOtro,<script>alert(1)</script>,$200", 'first_column_header' => 'yes' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_table( str_contains( $html, '<caption>Precios mal</caption>' ) && 3 === substr_count( $html, '<th scope="col">' ), 'Debe construir título y encabezados reales.' );
	check_table( str_contains( $html, 'Incluye diseño, soporte' ) && 2 === substr_count( $html, '<th scope="row">' ), 'Debe leer comas entrecomilladas y encabezados de fila.' );
	check_table( ! str_contains( $html, '<script>' ) && str_contains( $html, 'digi-table-wrap' ) && str_contains( $html, 'tabindex="0"' ), 'Debe sanear datos y permitir desplazar con teclado.' );
	$widget->settings = array( 'content' => "Uno,Dos" );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget );
	check_table( '' === ob_get_clean(), 'Una tabla sin filas de datos no debe pintarse.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_table( str_contains( $editor, 'var rows = []' ) && str_contains( $editor, 'digi-table-wrap' ), 'El editor debe analizar el CSV y mostrar la tabla.' );
	echo "DIGITALÍSIMO Elements: Tabla CSV validada.\n";
}
