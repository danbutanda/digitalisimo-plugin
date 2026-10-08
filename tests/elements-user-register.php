<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) {}
	}
	class Controls_Manager { const TEXT = 'text'; const RAW_HTML = 'raw'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function is_multisite() { return $GLOBALS['multisite']; }
	function get_site_option( $key ) { return $GLOBALS['network_registration']; }
	function get_option( $key ) { return $GLOBALS['site_registration']; }
	function is_user_logged_in() { return $GLOBALS['logged_in']; }
	function wp_registration_url() { return 'https://registro.test/wp-login.php?action=register'; }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function check_register( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-user-register.php';
	$widget = new \Digitalisimo\Elements\User_Register_Widget();
	check_register( array( 'digitalisimo-user-register' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'Sólo debe cargar CSS condicional.' );
	$widget->settings = array( 'description' => 'Alta <b>segura</b>', 'button_text' => 'Crear <script>mal</script>cuenta' );
	$render = new \ReflectionMethod( $widget, 'render' );
	$GLOBALS['multisite'] = false; $GLOBALS['site_registration'] = true; $GLOBALS['logged_in'] = false;
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_register( str_contains( $html, 'wp-login.php?action=register' ) && str_contains( $html, 'Alta segura' ) && ! str_contains( $html, '<script>' ), 'Debe enlazar al registro nativo y sanear el texto.' );
	$GLOBALS['site_registration'] = false;
	ob_start(); $render->invoke( $widget ); check_register( '' === ob_get_clean(), 'No debe ofrecer registro cerrado en WordPress individual.' );
	$GLOBALS['multisite'] = true; $GLOBALS['network_registration'] = 'blog';
	ob_start(); $render->invoke( $widget ); check_register( '' === ob_get_clean(), 'Registrar sitios no equivale a registrar usuarios.' );
	$GLOBALS['network_registration'] = 'user';
	ob_start(); $render->invoke( $widget ); check_register( str_contains( ob_get_clean(), 'wp-login.php?action=register' ), 'Debe respetar la política de registro de usuarios de red.' );
	$GLOBALS['logged_in'] = true;
	ob_start(); $render->invoke( $widget ); check_register( '' === ob_get_clean(), 'No debe ofrecer crear otra cuenta al usuario conectado.' );
	echo "DIGITALÍSIMO Elements: acceso seguro al registro validado.\n";
}
