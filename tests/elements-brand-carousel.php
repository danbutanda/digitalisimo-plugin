<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) { $this->attributes[ $name ]['href'] = $link['url']; }
		public function get_render_attribute_string( $name ) { return 'href="' . htmlspecialchars( $this->attributes[ $name ]['href'], ENT_QUOTES, 'UTF-8' ) . '"'; }
	}
	class Controls_Manager { const MEDIA = 'media'; const TEXT = 'text'; const URL = 'url'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const SWITCHER = 'switcher'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Repeater {
		private $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_meta( $id, $key, $single ) { return 'Logo real'; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { $GLOBALS['brand_carousel_image_attrs'] = $attrs; return '<img alt="' . esc_attr( $attrs['alt'] ) . '" class="' . esc_attr( $attrs['class'] ) . '">'; }
	function check_brand( $ok, $message ) { if ( ! $ok ) throw new \RuntimeException( $message ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-brand-carousel.php';
	$widget = new \Digitalisimo\Elements\Brand_Carousel_Widget();
	check_brand( array( 'digitalisimo-carousel-engine', 'digitalisimo-brand-carousel' ) === $widget->get_style_depends() && array( 'digitalisimo-carousel-engine' ) === $widget->get_script_depends(), 'El carrusel debe declarar solamente el motor compartido y su CSS.' );
	$controls = new \ReflectionMethod( $widget, 'register_controls' );
	$controls->invoke( $widget );
	check_brand( isset( $widget->controls['brand_items']['fields']['image'], $widget->controls['columns']['selectors'], $widget->controls['navigation'] ), 'El editor debe incluir repetidor, columnas y navegación.' );
	$widget->settings = array(
		'brand_html_tag' => 'script', 'navigation' => 'arrows',
		'brand_items' => array(
			array( 'brand_name' => '<script>Mal</script>', 'image' => array( 'id' => 12, 'url' => 'https://example.test/logo.png' ), 'link' => array( 'url' => 'https://example.test/' ), 'website_link_text' => 'Visitar' ),
			array( 'brand_name' => '', 'image' => array() ),
			array( 'brand_name' => 'Segunda', 'image' => array( 'url' => 'https://example.test/b.png' ) ),
		),
	);
	$render = new \ReflectionMethod( $widget, 'render' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_brand( 2 === substr_count( $html, '<li class="digi-carousel__slide' ) && str_contains( $html, 'style="--digi-carousel-count:2"' ) && str_contains( $html, 'data-digi-carousel-track' ) && str_contains( $html, 'data-digi-carousel-next' ), 'El motor debe renderizar dos tarjetas, ancho efectivo y controles.' );
	check_brand( str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, '<h3 class="digi-brand-carousel__name">' ), 'Texto y etiqueta deben ser seguros.' );
	check_brand( str_contains( $html, 'alt="Logo real"' ) && str_contains( $html, 'href="https://example.test/"' ) && str_contains( $html, 'aria-roledescription="carrusel"' ), 'ALT, enlace y región accesible deben conservarse.' );
	check_brand( ! isset( $GLOBALS['brand_carousel_image_attrs']['loading'] ) && ! str_contains( $html, 'loading="lazy"' ), 'WordPress debe decidir la carga del logo según la ubicación real del carrusel.' );
	$template = new \ReflectionMethod( $widget, 'content_template' );
	ob_start(); $template->invoke( $widget ); $editor = ob_get_clean();
	check_brand( str_contains( $editor, 'data-digi-carousel-track' ) && str_contains( $editor, '{{ item.brand_name }}' ) && ! str_contains( $editor, 'loading="lazy"' ), 'La vista previa debe incluir el carrusel sin forzar la carga de imágenes.' );
	$js = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/js/carousel-engine.js' );
	check_brand( ! str_contains( $js, 'Swiper' ) && ! str_contains( $js, 'UIkit' ) && str_contains( $js, 'prefers-reduced-motion' ), 'El motor debe ser nativo y respetar movimiento reducido.' );
	echo "DIGITALÍSIMO Elements: Motor y carrusel de marcas validados.\n";
}
