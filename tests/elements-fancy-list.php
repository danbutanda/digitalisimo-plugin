<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		private $attributes = array();
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = $args; }
		public function get_settings_for_display() { return $this->settings; }
		public function add_link_attributes( $name, $link ) { $this->attributes[ $name ]['href'] = $link['url']; }
		public function get_render_attribute_string( $name ) { return 'href="' . htmlspecialchars( $this->attributes[ $name ]['href'], ENT_QUOTES, 'UTF-8' ) . '"'; }
	}
	class Controls_Manager {
		const TEXT = 'text'; const SELECT = 'select'; const ICONS = 'icons'; const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const SLIDER = 'slider'; const SWITCHER = 'switcher'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style';
	}
	class Repeater {
		private $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Icons_Manager { public static function render_icon( $icon, $options ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function absint( $n ) { return abs( (int) $n ); }
	function get_post_meta( $id, $key, $single ) { return 'Alt real'; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img alt="' . esc_attr( $attrs['alt'] ) . '" loading="' . esc_attr( $attrs['loading'] ) . '">'; }
	function verify_fancy( $ok, $message ) { if ( ! $ok ) throw new \RuntimeException( $message ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-list.php';
	$widget = new \Digitalisimo\Elements\Fancy_List_Widget();
	verify_fancy( 'digitalisimo-fancy-list' === $widget->get_name() && array( 'digitalisimo-fancy-list' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El widget sólo debe depender de su CSS.' );
	$controls = new \ReflectionMethod( $widget, 'register_controls' );
	$controls->invoke( $widget );
	verify_fancy( isset( $widget->controls['icon_list']['fields']['img'], $widget->controls['columns']['selectors'], $widget->controls['title_typography'], $widget->controls['image_size'] ), 'El editor debe registrar repetidor, controles responsivos y estilos nativos.' );
	$widget->settings = array(
		'layout_style' => 'invalid', 'title_tags' => 'script', 'show_number_icon' => 'yes',
		'icon_list' => array(
			array( 'text' => '<script>mal</script>', 'text_details' => 'Detalle', 'img' => array( 'url' => 'https://example.test/p.jpg', 'id' => 2 ), 'link' => array( 'url' => 'https://example.test/' ) ),
			array( 'text' => '', 'text_details' => '', 'img' => array(), 'list_icon' => array() ),
			array( 'text' => 'Segundo', 'list_icon' => array( 'value' => 'fas fa-star' ) ),
		),
	);
	$method = new \ReflectionMethod( $widget, 'render' );
	ob_start(); $method->invoke( $widget ); $html = ob_get_clean();
	verify_fancy( str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, '<h4 class="digi-fancy-list__title">' ), 'El título y la etiqueta deben estar saneados.' );
	verify_fancy( str_contains( $html, '<div class="digi-fancy-list__content"><h4' ), 'El encabezado no debe quedar dentro de un span inválido.' );
	verify_fancy( 2 === substr_count( $html, '<li ' ) && str_contains( $html, 'digi-fancy-list--style-1' ), 'Debe omitir elementos vacíos y usar estilo seguro.' );
	verify_fancy( str_contains( $html, 'alt="Alt real"' ) && str_contains( $html, 'loading="lazy"' ) && str_contains( $html, 'href="https://example.test/"' ), 'Debe conservar ALT, lazy loading y enlace.' );
	verify_fancy( str_contains( $html, '>1</span>' ) && str_contains( $html, '>2</span>' ), 'La numeración debe omitir elementos vacíos.' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/fancy-list.css' );
	verify_fancy( str_contains( $css, '.digi-fancy-list__items' ) && ! str_contains( $css, 'uikit' ), 'El CSS debe estar aislado y libre de UIkit.' );
	$template = new \ReflectionMethod( $widget, 'content_template' );
	ob_start(); $template->invoke( $widget ); $editor = ob_get_clean();
	verify_fancy( str_contains( $editor, 'digi-fancy-list__content' ) && str_contains( $editor, 'renderIcon' ) && str_contains( $editor, '{{ title }}' ), 'La vista previa del editor debe reflejar la lista sin insertar texto HTML sin escapar.' );
	echo "DIGITALÍSIMO Elements: Lista destacada validada.\n";
}
