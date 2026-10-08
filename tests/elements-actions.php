<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $links = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; }
		public function get_render_attribute_string( $name ) { return 'href="' . htmlspecialchars( $this->links[ $name ], ENT_QUOTES, 'UTF-8' ) . '"'; }
	}
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url'; const ICONS = 'icons'; const SWITCHER = 'switcher'; const SELECT = 'select'; const CHOOSE = 'choose'; const SLIDER = 'slider'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function check_actions( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-dual-button.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-call-out.php';
	$dual = new \Digitalisimo\Elements\Dual_Button_Widget();
	$callout = new \Digitalisimo\Elements\Call_Out_Widget();
	foreach ( array( $dual, $callout ) as $widget ) {
		check_actions( array() === $widget->get_script_depends() && 1 === count( $widget->get_style_depends() ), 'Ambos widgets deben usar sólo su CSS condicional.' );
		(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	}
	foreach ( array( 'button_a_text', 'button_a_link', 'button_b_text', 'button_b_link', 'show_middle_text' ) as $control ) { check_actions( isset( $dual->controls[ $control ] ), 'Falta ' . $control ); }
	$dual->settings = array( 'button_a_text' => 'Ver <script>x</script>', 'button_a_link' => array( 'url' => 'https://example.test/uno' ), 'button_b_text' => 'Contactar', 'button_b_link' => array( 'url' => '' ), 'show_middle_text' => 'yes', 'middle_text' => 'o', 'size' => 'large' );
	ob_start(); (new \ReflectionMethod( $dual, 'render' ))->invoke( $dual ); $html = ob_get_clean();
	check_actions( 1 === substr_count( $html, '<a ' ) && str_contains( $html, 'href="https://example.test/uno"' ) && str_contains( $html, 'digi-dual-button--large' ), 'Sólo el botón con URL debe ser enlace.' );
	check_actions( ! str_contains( $html, '<script' ) && str_contains( $html, 'Ver x' ) && str_contains( $html, 'digi-dual-button__middle' ), 'Los textos se deben sanear y mantener el separador.' );
	$callout->settings = array( 'title' => 'Ayuda <script>x</script>', 'description' => 'Línea 1\nLínea 2', 'title_size' => 'script', 'button_text' => 'Enviar', 'link' => array( 'url' => 'https://example.test/contacto' ) );
	ob_start(); (new \ReflectionMethod( $callout, 'render' ))->invoke( $callout ); $html = ob_get_clean();
	check_actions( str_contains( $html, '<h3 ' ) && ! str_contains( $html, '<script' ) && str_contains( $html, 'href="https://example.test/contacto"' ), 'Call Out debe usar título válido y enlace real.' );
	$callout->settings['link']['url'] = '';
	ob_start(); (new \ReflectionMethod( $callout, 'render' ))->invoke( $callout ); $html = ob_get_clean();
	check_actions( ! str_contains( $html, '<a ' ) && str_contains( $html, 'digi-call-out__content' ), 'Sin URL se conserva el contenido sin acción vacía.' );
	echo "DIGITALÍSIMO Elements: Botón doble y Call Out aislados validados.\n";
}
