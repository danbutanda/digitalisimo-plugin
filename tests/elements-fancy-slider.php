<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $links = array(); private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; $this->attributes[ $name ] = array( 'href' => $link['url'] ); if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; } if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); } }
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $name ) { $out = array(); foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; } return implode( ' ', $out ); }
	}
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
	class Controls_Manager { const SELECT = 'select'; const TEXT = 'text'; const WYSIWYG = 'wysiwyg'; const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><strong><em><br>' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_meta( $id, $key, $single ) { return 'Imagen destacada'; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img class="' . $attrs['class'] . '" src="https://site.test/imagen.webp" alt="' . $attrs['alt'] . '" srcset="https://site.test/imagen.webp 800w"' . ( isset( $attrs['loading'] ) ? ' loading="lazy"' : '' ) . '>'; }
	function check_fancy_slider( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-slider.php';
	$widget = new \Digitalisimo\Elements\Fancy_Slider_Widget();
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_fancy_slider( isset( $widget->controls['slides'], $widget->controls['image_position'] ) && array( 'digitalisimo-carousel-engine' ) === $widget->get_script_depends(), 'Debe usar controles editoriales y el motor compartido.' );
	$widget->settings = array( 'title_tags' => 'script', 'slides' => array(
		array( 'title' => 'Primera <script>x</script>', 'sub_title' => 'Subtítulo', 'description' => '<p>Descripción</p><script>x</script>', 'slide_image' => array( 'id' => 10, 'url' => 'https://site.test/imagen.webp' ), 'slide_button' => 'Leer', 'button_link' => array( 'url' => 'https://site.test/leer', 'is_external' => true, 'nofollow' => true ) ),
		array( 'title' => 'Segunda', 'slide_image' => array( 'id' => 11, 'url' => 'https://site.test/imagen.webp' ), 'title_link' => array( 'url' => 'https://site.test/segunda', 'is_external' => true ) ),
	) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_fancy_slider( 2 === substr_count( $html, '<li class="digi-carousel__slide ' ) && str_contains( $html, 'data-digi-carousel-next' ), 'Debe renderizar dos diapositivas y la navegación del motor.' );
	check_fancy_slider( str_contains( $html, 'srcset=' ) && 1 === substr_count( $html, 'loading="lazy"' ) && str_contains( $html, 'alt="Imagen destacada"' ), 'La primera imagen debe cargar de inmediato; las siguientes, diferidas y con ALT/srcset.' );
	check_fancy_slider( str_contains( $html, '<h2 class="digi-fancy-slider__title">' ) && ! str_contains( $html, '<script' ) && str_contains( $html, 'href="https://site.test/leer"' ), 'El texto y las etiquetas deben sanearse sin perder el enlace.' );
	check_fancy_slider( str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && str_contains( $html, 'target="_blank"' ), 'Título y botón externos deben proteger la pestaña.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_fancy_slider( str_contains( $editor, 'titleLink.url' ) && str_contains( $editor, 'item.slide_image.alt' ) && str_contains( $editor, 'data-digi-carousel-next' ), 'El editor debe mostrar enlaces, ALT y navegación.' );
	$widget->settings['slides'] = array( $widget->settings['slides'][0] );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $single = ob_get_clean();
	check_fancy_slider( ! str_contains( $single, 'data-digi-carousel-next' ), 'Una sola diapositiva no debe mostrar flechas.' );
	echo "DIGITALÍSIMO Elements: Slider destacado validado.\n";
}
