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
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
	class Controls_Manager { const SELECT = 'select'; const TEXT = 'text'; const URL = 'url'; const REPEATER = 'repeater'; const SLIDER = 'slider'; const COLOR = 'color'; const ICONS = 'icons'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ); }
	function check_fancy_icons( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-icons.php';
	$widget = new \Digitalisimo\Elements\Fancy_Icons_Widget();
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_fancy_icons( isset( $widget->controls['share_items'] ) && array() === $widget->get_script_depends(), 'Debe registrar el repetidor y no depender de scripts.' );
	$widget->settings = array( 'share_items' => array(
		array( '_id' => 'a1', 'social_type' => 'icon', 'social_name' => 'Facebook', 'social_icon' => array( 'value' => 'fab fa-facebook' ), 'social_link' => array( 'url' => 'https://example.com/red' ) ),
		array( '_id' => 'b2', 'social_type' => 'text', 'social_name' => 'Contacto', 'social_link' => array( 'url' => '' ) ),
		array( '_id' => 'c3', 'social_type' => 'icon', 'social_name' => '', 'social_icon' => array( 'value' => 'fas fa-star' ) ),
	) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_fancy_icons( 2 === substr_count( $html, '<li ' ) && str_contains( $html, 'aria-label="Facebook"' ) && str_contains( $html, 'href="https://example.com/red"' ), 'Debe renderizar sólo enlaces con contenido y nombre accesible.' );
	check_fancy_icons( str_contains( $html, '<span class="digi-fancy-icons__content"><span>Contacto</span></span>' ) && ! str_contains( $html, 'elementor-repeater-item-c3' ), 'No debe producir enlaces vacíos ni iconos sin nombre.' );
	echo "DIGITALÍSIMO Elements: Iconos destacados validados.\n";
}
