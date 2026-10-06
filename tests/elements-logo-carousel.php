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
	class Controls_Manager { const GALLERY = 'gallery'; const MEDIA = 'media'; const TEXT = 'text'; const URL = 'url'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; }
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function absint( $v ) { return abs( (int) $v ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function get_post_meta( $id, $key, $single ) { return 12 === $id ? 'Marca & Compañía' : ''; }
	function get_the_title( $id ) { return 'Imagen sin ALT'; }
	function wp_get_attachment_url( $id ) { return 'https://example.test/' . $id . '.png'; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img class="digi-logo-carousel__image" alt="' . esc_attr( $attrs['alt'] ) . '">'; }
	function check_logo( $condition, $message ) { if ( ! $condition ) throw new \RuntimeException( $message ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-logo-carousel.php';
	$widget = new \Digitalisimo\Elements\Logo_Carousel_Widget();
	check_logo( array( 'digitalisimo-carousel-engine', 'digitalisimo-logo-carousel' ) === $widget->get_style_depends() && array( 'digitalisimo-carousel-engine' ) === $widget->get_script_depends(), 'Debe usar sólo el motor compartido y su estilo.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_logo( isset( $widget->controls['gallery_images'], $widget->controls['logo_items']['fields']['link'], $widget->controls['columns'], $widget->controls['height'] ), 'El editor debe permitir carga múltiple, enlaces individuales y controles responsivos.' );
	$widget->settings = array( 'gallery_images' => array( array( 'id' => 12, 'url' => 'https://example.test/12.png' ), array( 'id' => 0, 'url' => 'https://example.test/manual.png', 'alt' => 'Logo manual' ) ), 'logo_items' => array( array( 'image' => array( 'id' => 0, 'url' => 'https://example.test/last.png' ), 'name' => 'Último <Logo>', 'link' => array( 'url' => 'https://example.test/firma' ) ), array( 'image' => array() ) ), 'navigation' => 'arrows' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_logo( 3 === substr_count( $html, '<li class="digi-carousel__slide' ) && str_contains( $html, 'data-digi-carousel-next' ), 'Debe renderizar sólo imágenes válidas y sus controles.' );
	check_logo( str_contains( $html, 'alt="Marca &amp; Compañía"' ) && str_contains( $html, 'alt="Logo manual"' ) && str_contains( $html, 'alt="Último &lt;Logo&gt;"' ), 'Los textos alternativos deben conservarse y escaparse.' );
	check_logo( str_contains( $html, 'href="https://example.test/firma"' ) && str_contains( $html, 'loading="lazy"' ), 'El enlace individual y la carga diferida deben existir.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_logo( str_contains( $editor, 'gallery_images' ) && str_contains( $editor, 'digi-logo-carousel__image' ), 'La vista previa debe admitir la galería múltiple.' );
	echo "DIGITALÍSIMO Elements: Carrusel de logotipos validado.\n";
}
