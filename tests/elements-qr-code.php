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
	class Controls_Manager { const SWITCHER = 'switcher'; const TEXTAREA = 'textarea'; const TEXT = 'text'; const NUMBER = 'number'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function get_permalink() { return 'https://sitio.test/pagina/'; }
	function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function sanitize_hex_color( $v ) { return preg_match( '/^#[0-9a-f]{6}$/i', $v ) ? $v : null; }
	function wp_parse_url( $v, $part ) { return parse_url( $v, $part ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function check_qr( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-qr-code.php';
	$widget = new \Digitalisimo\Elements\QR_Code_Widget();
	check_qr( array( 'digitalisimo-qr-code' ) === $widget->get_script_depends(), 'El widget debe cargar sólo su generador condicional.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_qr( isset( $widget->controls['site_link'], $widget->controls['text'], $widget->controls['size'] ), 'Debe ofrecer URL de la página o texto y tamaño.' );
	$widget->settings = array( 'site_link' => 'yes', 'size' => 999, 'foreground' => 'red', 'label' => '<script>x</script>Escanear' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_qr( str_contains( $html, 'data-text="https://sitio.test/pagina/"' ) && str_contains( $html, 'data-size="512"' ) && str_contains( $html, 'data-fill="#111111"' ), 'Debe usar el permalink del sitio y acotar tamaño/color.' );
	check_qr( str_contains( $html, 'href="https://sitio.test/pagina/"' ) && ! str_contains( $html, '<script>' ), 'Debe conservar un enlace accesible y sanear el rótulo.' );
	$widget->settings = array( 'site_link' => '', 'text' => 'Contacto & ayuda', 'size' => 32 );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $text = ob_get_clean();
	check_qr( str_contains( $text, 'data-size="64"' ) && str_contains( $text, 'Contacto &amp; ayuda' ) && ! str_contains( $text, 'href=' ), 'El texto plano debe mantenerse legible sin enlace falso.' );
	$widget->settings = array( 'site_link' => '', 'text' => '' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget );
	check_qr( '' === ob_get_clean(), 'No debe crear un QR vacío.' );
	echo "DIGITALÍSIMO Elements: Código QR validado.\n";
}
