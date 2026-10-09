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
	class Controls_Manager { const MEDIA = 'media'; const TEXT = 'text'; const URL = 'url'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const SWITCHER = 'switcher'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_meta( $id, $key, $single ) { return 7 === $id ? 'Alt registrado' : ''; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { $GLOBALS['brand_grid_image_attrs'] = $attrs; return 7 === $id ? '<img alt="' . esc_attr( $attrs['alt'] ) . '">' : ''; }
	function check_grid( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-brand-grid.php';
	$widget = new \Digitalisimo\Elements\Brand_Grid_Widget();
	check_grid( array( 'digitalisimo-brand-grid' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El grid no debe requerir JS ni hojas compartidas.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_grid( isset( $widget->controls['brand_items']['fields']['brand_name'], $widget->controls['columns'], $widget->controls['brand_event'], $widget->controls['image_size'] ), 'El editor debe conservar los datos y controles principales.' );
	$widget->settings = array( 'brand_items' => array( array( 'brand_name' => 'Marca & Co', 'image' => array( 'id' => 7, 'url' => 'https://example.test/logo.webp' ), 'link' => array( 'url' => 'https://example.test/' ) ), array( 'brand_name' => '<script>alert(1)</script>', 'image' => array( 'url' => 'https://example.test/otro.webp' ) ), array( 'image' => 'invalid' ), array( 'brand_name' => 'Marca 4', 'image' => array( 'url' => 'https://example.test/cuatro.webp' ) ) ), 'brand_event' => 'click', 'show_brand_name' => 'yes', 'show_website_link' => 'yes', 'brand_html_tag' => 'h3' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_grid( 3 === substr_count( $html, '<li class="digi-brand-grid__item">' ) && 3 === substr_count( $html, '<details' ), 'Deben omitirse datos inválidos y abrirse los detalles sin JS.' );
	check_grid( str_contains( $html, 'alt="Alt registrado"' ) && ! isset( $GLOBALS['brand_grid_image_attrs']['loading'] ) && ! str_contains( $html, 'loading="eager"' ), 'El ALT debe conservarse y WordPress debe decidir la carga según la posición real.' );
	check_grid( str_contains( $html, 'href="https://example.test/"' ) && str_contains( $html, 'Visitar Marca &amp; Co' ), 'El enlace debe tener texto accesible.' );
	check_grid( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ), 'El nombre debe escaparse.' );
	$widget->settings['brand_event'] = 'invalid';
	$widget->settings['brand_html_tag'] = 'script';
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $safe = ob_get_clean();
	check_grid( str_contains( $safe, 'digi-brand-grid--always' ) && str_contains( $safe, '<h3 class="digi-brand-grid__name">' ) && ! str_contains( $safe, '<details' ), 'Opciones inválidas deben usar valores seguros.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_grid( str_contains( $editor, 'digi-brand-grid__list' ) && str_contains( $editor, '<details' ), 'La vista previa debe reflejar la cuadrícula.' );
	echo "DIGITALÍSIMO Elements: Cuadrícula de marcas validada.\n";
}
