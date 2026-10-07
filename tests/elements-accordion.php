<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		public function get_id() { return 'test123'; }
		public function parse_text_editor( $text ) { return $text; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	class Controls_Manager { const TEXT = 'text'; const WYSIWYG = 'wysiwyg'; const REPEATER = 'repeater'; const NUMBER = 'number'; const SWITCHER = 'switcher'; const SELECT = 'select'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function wp_kses_post( $value ) { return strip_tags( (string) $value, '<p><strong>' ); }
	function check_accordion( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-accordion.php';
	$widget = new \Digitalisimo\Elements\Accordion_Widget();
	check_accordion( 'digitalisimo-accordion' === $widget->get_name() && array( 'digitalisimo-accordion' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El acordeón sólo debe requerir su CSS.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_accordion( isset( $widget->controls['tabs']['fields']['tab_title'], $widget->controls['tabs']['fields']['tab_content'], $widget->controls['active_item'], $widget->controls['multiple'] ), 'Faltan controles principales.' );
	$widget->settings = array( 'tabs' => array( array( 'tab_title' => 'Pregunta <script>', 'tab_content' => '<p>Respuesta <strong>válida</strong></p><script>alert(1)</script>' ), array( 'tab_title' => 'Segunda', 'tab_content' => '<p>Otra</p>' ), array( 'tab_title' => '', 'tab_content' => '' ) ), 'active_item' => 1, 'multiple' => '', 'title_html_tag' => 'script' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_accordion( 2 === substr_count( $html, '<details ' ) && 2 === substr_count( $html, 'name="digi-accordion-test123"' ) && 1 === substr_count( $html, ' open' ), 'Deben renderizarse dos elementos con grupo exclusivo y apertura inicial.' );
	check_accordion( str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, '<strong>válida</strong>' ), 'El título y contenido deben pasar por escape y sanitización.' );
	check_accordion( str_contains( $html, '<span class="digi-accordion__title">' ), 'La etiqueta inválida debe usar span.' );
	$widget->settings['multiple'] = 'yes';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $multi = ob_get_clean();
	check_accordion( ! str_contains( $multi, ' name=' ), 'El modo múltiple no debe agrupar details.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget ); $editor = ob_get_clean();
	check_accordion( str_contains( $editor, 'digi-accordion__summary' ) && str_contains( $editor, 'tab_content' ), 'La plantilla de Elementor debe conservar elementos y contenido.' );
	echo "DIGITALÍSIMO Elements: Acordeón nativo validado.\n";
}
