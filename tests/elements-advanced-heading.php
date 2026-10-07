<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		private $links = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; }
		public function get_render_attribute_string( $name ) { return 'href="' . htmlspecialchars( $this->links[ $name ], ENT_QUOTES, 'UTF-8' ) . '"'; }
	}
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SWITCHER = 'switcher'; const URL = 'url'; const SELECT = 'select'; const CHOOSE = 'choose'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style';
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function check_heading( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-advanced-heading.php';
	$widget = new \Digitalisimo\Elements\Advanced_Heading_Widget();
	check_heading( array( 'digitalisimo-advanced-heading' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El componente debe cargar sólo su CSS.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	foreach ( array( 'sub_heading', 'main_heading', 'split_main_heading', 'split_text', 'link', 'header_size', 'align', 'advanced_heading_visibility', 'advanced_heading' ) as $control ) { check_heading( isset( $widget->controls[ $control ] ), 'Falta el control ' . $control ); }
	$widget->settings = array( 'sub_heading' => 'Antes', 'main_heading' => 'Agencia <script>x</script>', 'split_main_heading' => 'yes', 'split_text' => 'México', 'header_size' => 'h1', 'advanced_heading_visibility' => 'yes', 'advanced_heading' => 'Marca', 'link' => array( 'url' => 'https://example.test/' ) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_heading( 1 === substr_count( $html, '<h1 ' ) && 1 === substr_count( $html, '</h1>' ) && str_contains( $html, 'aria-hidden="true"' ), 'El título debe ser único y la decoración inaccesible.' );
	check_heading( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ) && str_contains( $html, 'href="https://example.test/"' ), 'Contenido y enlace deben escaparse.' );
	$widget->settings['header_size'] = 'script';
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_heading( str_contains( $html, '<h2 ' ) && ! str_contains( $html, '<script ' ), 'Una etiqueta inválida debe normalizarse.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_heading( str_contains( $editor, 'digi-advanced-heading__title' ) && str_contains( $editor, 'aria-hidden="true"' ), 'El editor debe mostrar la misma estructura semántica.' );
	echo "DIGITALÍSIMO Elements: Encabezado avanzado validado.\n";
}
