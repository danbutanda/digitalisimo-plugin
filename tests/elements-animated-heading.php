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
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SWITCHER = 'switcher'; const URL = 'url'; const SELECT = 'select'; const NUMBER = 'number'; const CHOOSE = 'choose'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style';
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function wp_json_encode( $value ) { return json_encode( $value, JSON_UNESCAPED_UNICODE ); }
	function absint( $value ) { return abs( (int) $value ); }
	function check_animated_heading( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-animated-heading.php';
	$widget = new \Digitalisimo\Elements\Animated_Heading_Widget();
	check_animated_heading( array( 'digitalisimo-animated-heading' ) === $widget->get_style_depends() && array( 'digitalisimo-animated-heading' ) === $widget->get_script_depends(), 'Los recursos deben cargarse sólo con el widget.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	foreach ( array( 'before_text', 'animated_text', 'after_text', 'heading_tag', 'heading_link', 'rotate', 'interval' ) as $control ) { check_animated_heading( isset( $widget->controls[ $control ] ), 'Falta el control ' . $control ); }
	$widget->settings = array( 'before_text' => 'Creamos', 'animated_text' => "Ideas\nResultados", 'after_text' => 'para ti', 'heading_tag' => 'h1', 'heading_link' => array( 'url' => 'https://example.test/' ), 'rotate' => 'yes', 'interval' => 100 );
	$render = new \ReflectionMethod( $widget, 'render' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_animated_heading( 1 === substr_count( $html, '<h1 ' ) && 1 === substr_count( $html, '</h1>' ) && str_contains( $html, '>Ideas</span>' ), 'El primer término y el H1 deben ser visibles desde el HTML inicial.' );
	check_animated_heading( str_contains( $html, 'data-digi-phrases=' ) && str_contains( $html, 'data-digi-interval="1500"' ) && str_contains( $html, 'href="https://example.test/"' ), 'La rotación y el enlace deben tener valores válidos.' );
	$widget->settings['animated_text'] = '<script>x</script>';
	$widget->settings['heading_tag'] = 'script';
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_animated_heading( str_contains( $html, '<h2 ' ) && ! str_contains( $html, '<script' ) && ! str_contains( $html, 'data-digi-phrases=' ), 'Una etiqueta inválida o una sola frase no debe activar la rotación.' );
	$js = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/js/animated-heading.js' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/animated-heading.css' );
	check_animated_heading( str_contains( $js, 'prefers-reduced-motion' ) && str_contains( $js, 'document.fonts.ready' ) && str_contains( $js, 'initialized' ) && str_contains( $css, 'white-space:nowrap' ), 'La rotación debe respetar movimiento reducido, fuentes e instancias múltiples.' );
	echo "DIGITALÍSIMO Elements: Encabezado animado visible y aislado validado.\n";
}
