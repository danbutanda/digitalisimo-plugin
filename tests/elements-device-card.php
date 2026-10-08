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
	class Controls_Manager { const SELECT = 'select'; const GALLERY = 'gallery'; const MEDIA = 'media'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url'; const REPEATER = 'repeater'; const SLIDER = 'slider'; const COLOR = 'color'; const ICONS = 'icons'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_meta( $id, $key, $single ) { return $id === 10 ? 'Captura móvil' : ''; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img class="' . $attrs['class'] . '" src="https://site.test/captura.webp" alt="' . htmlspecialchars( $attrs['alt'], ENT_QUOTES, 'UTF-8' ) . '" srcset="https://site.test/captura.webp 800w">'; }
	function check_device( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-device-slider.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-card.php';
	$slider = new \Digitalisimo\Elements\Device_Slider_Widget();
	$card = new \Digitalisimo\Elements\Fancy_Card_Widget();
	foreach ( array( $slider, $card ) as $widget ) { (new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget ); }
	check_device( array( 'digitalisimo-carousel-engine' ) === $slider->get_script_depends() && array() === $card->get_script_depends(), 'Sólo el slider debe usar el motor compartido.' );
	$slider->settings = array( 'device_type' => 'mobile', 'gallery_images' => array( array( 'id' => 10, 'url' => 'https://site.test/captura.webp' ) ), 'slides' => array( array( 'image' => array( 'url' => 'https://site.test/otra.webp' ), 'title' => 'Otra', 'link' => array( 'url' => 'https://site.test/otra' ) ) ) );
	ob_start(); (new \ReflectionMethod( $slider, 'render' ))->invoke( $slider ); $html = ob_get_clean();
	check_device( str_contains( $html, 'digi-device-slider--mobile' ) && 2 === substr_count( $html, '<li class="digi-carousel__slide ' ) && str_contains( $html, 'srcset=' ) && str_contains( $html, 'alt="Captura móvil"' ), 'El slider debe mostrar dos capturas y conservar ALT/srcset.' );
	check_device( str_contains( $html, 'data-digi-carousel-next' ) && str_contains( $html, 'href="https://site.test/otra"' ), 'Debe usar controles del motor y enlace individual.' );
	$slider->settings['slides'] = array();
	ob_start(); (new \ReflectionMethod( $slider, 'render' ))->invoke( $slider ); $one = ob_get_clean();
	check_device( ! str_contains( $one, 'data-digi-carousel-next' ), 'Una captura no debe mostrar flechas.' );
	$card->settings = array( 'icon_type' => 'image', 'image' => array( 'id' => 10, 'url' => 'https://site.test/captura.webp' ), 'title_text' => 'Tarjeta <script>x</script>', 'description_text' => 'Texto', 'title_size' => 'script', 'button_text' => 'Leer', 'link' => array( 'url' => 'https://site.test/leer' ) );
	ob_start(); (new \ReflectionMethod( $card, 'render' ))->invoke( $card ); $html = ob_get_clean();
	check_device( str_contains( $html, '<article class="digi-fancy-card">' ) && str_contains( $html, '<h3 ' ) && str_contains( $html, 'href="https://site.test/leer"' ) && ! str_contains( $html, '<script' ), 'La tarjeta debe tener estructura semántica y salida saneada.' );
	$card->settings['link']['url'] = '';
	ob_start(); (new \ReflectionMethod( $card, 'render' ))->invoke( $card ); $html = ob_get_clean();
	check_device( ! str_contains( $html, '<a ' ), 'Sin URL no debe existir enlace vacío.' );
	echo "DIGITALÍSIMO Elements: Slider de dispositivos y Tarjeta destacada validados.\n";
}
