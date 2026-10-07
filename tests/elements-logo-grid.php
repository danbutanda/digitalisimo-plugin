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
	class Controls_Manager { const GALLERY = 'gallery'; const MEDIA = 'media'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const SWITCHER = 'switcher'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_get_attachment_url( $id ) { return 'https://example.test/media/' . $id . '.webp'; }
	function get_post_meta( $id, $key, $single ) { return 12 === $id ? 'Logo & compañía' : ''; }
	function get_the_title( $id ) { return 'Adjunto ' . $id; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return 12 === $id ? '<img class="digi-logo-grid__image" alt="' . esc_attr( $attrs['alt'] ) . '" loading="' . $attrs['loading'] . '">' : ''; }
	function check_logo_grid( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-logo-grid.php';
	$widget = new \Digitalisimo\Elements\Logo_Grid_Widget();
	check_logo_grid( array( 'digitalisimo-logo-grid' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El widget debe usar sólo su CSS.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_logo_grid( isset( $widget->controls['gallery_images'], $widget->controls['logo_list']['fields']['logo_tooltip'], $widget->controls['columns'], $widget->controls['layout'], $widget->controls['height'] ), 'Faltan controles de galería, detalle o diseño responsivo.' );
	$widget->settings = array( 'gallery_images' => array( array( 'id' => 12 ), array( 'url' => 'https://example.test/manual.webp', 'alt' => 'Manual', 'width' => 320, 'height' => 160 ) ), 'logo_list' => array( array( 'image' => array( 'url' => 'https://example.test/tercero.webp' ), 'name' => '<script>mal</script>', 'description' => 'Descripción & más', 'link' => array( 'url' => 'https://example.test/' ), 'logo_tooltip' => 'yes' ), array( 'image' => array() ) ), 'layout' => 'border', 'thumbnail_size' => 'medium' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_logo_grid( 3 === substr_count( $html, '<li class="digi-logo-grid__item">' ) && str_contains( $html, 'digi-logo-grid--border' ), 'Deben omitirse imágenes vacías y validar el diseño.' );
	check_logo_grid( str_contains( $html, 'alt="Logo &amp; compañía"' ) && str_contains( $html, 'alt="Manual"' ) && str_contains( $html, 'loading="lazy"' ), 'Debe conservar ALT y carga diferida.' );
	check_logo_grid( str_contains( $html, 'width="320" height="160"' ) && str_contains( $html, 'href="https://example.test/"' ), 'Debe reservar dimensiones y conservar el enlace.' );
	check_logo_grid( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ) && str_contains( $html, 'Descripción &amp; más' ), 'Nombre y descripción deben escaparse.' );
	$widget->settings['layout'] = 'invalido';
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $safe = ob_get_clean();
	check_logo_grid( str_contains( $safe, 'digi-logo-grid--box' ), 'El diseño inválido debe volver a tarjetas.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_logo_grid( str_contains( $editor, 'gallery_images' ) && str_contains( $editor, 'digi-logo-grid__caption' ), 'La vista previa debe admitir imágenes múltiples y leyenda.' );
	echo "DIGITALÍSIMO Elements: Cuadrícula de logotipos validada.\n";
}
