<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $links = array(); private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) {
			$this->links[ $name ] = $link['url'];
			$this->attributes[ $name ] = array( 'href' => $link['url'] );
			if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; }
			if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); }
		}
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $name ) {
			$out = array();
			foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; }
			return implode( ' ', $out );
		}
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
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ); }
	function get_post_meta( $id, $key, $single ) { return $id === 10 ? 'Captura móvil' : ''; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img class="' . $attrs['class'] . '" src="https://site.test/captura.webp" alt="' . htmlspecialchars( $attrs['alt'], ENT_QUOTES, 'UTF-8' ) . '" srcset="https://site.test/captura.webp 800w">'; }
	function check_device( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-device-slider.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-card.php';
	$slider = new \Digitalisimo\Elements\Device_Slider_Widget();
	$card = new \Digitalisimo\Elements\Fancy_Card_Widget();
	foreach ( array( $slider, $card ) as $widget ) { (new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget ); }
	check_device( array( 'digitalisimo-carousel-engine' ) === $slider->get_script_depends() && array() === $card->get_script_depends(), 'Sólo el slider debe usar el motor compartido.' );
	$slider->settings = array( 'device_type' => 'mobile', 'gallery_images' => array( array( 'id' => 10, 'url' => 'https://site.test/captura.webp' ) ), 'slides' => array( array( 'image' => array( 'url' => 'https://site.test/otra.webp' ), 'title' => 'Otra', 'link' => array( 'url' => 'https://site.test/otra', 'is_external' => true, 'nofollow' => true ) ) ) );
	ob_start(); (new \ReflectionMethod( $slider, 'render' ))->invoke( $slider ); $html = ob_get_clean();
	check_device( str_contains( $html, 'digi-device-slider--mobile' ) && 2 === substr_count( $html, '<li class="digi-carousel__slide ' ) && str_contains( $html, 'srcset=' ) && str_contains( $html, 'alt="Captura móvil"' ), 'El slider debe mostrar dos capturas y conservar ALT/srcset.' );
	check_device( str_contains( $html, 'data-digi-carousel-next' ) && str_contains( $html, 'href="https://site.test/otra"' ), 'Debe usar controles del motor y enlace individual.' );
	check_device( str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="nofollow noopener noreferrer"' ), 'El enlace externo debe proteger la pestaña.' );
	ob_start(); (new \ReflectionMethod( $slider, 'content_template' ))->invoke( $slider ); $editor = ob_get_clean();
	check_device( str_contains( $editor, 'item.title' ) && str_contains( $editor, 'data-digi-carousel-next' ) && str_contains( $editor, 'link.is_external' ), 'El editor debe mostrar títulos, enlaces y flechas del carrusel.' );
	$slider->settings['slides'] = array();
	ob_start(); (new \ReflectionMethod( $slider, 'render' ))->invoke( $slider ); $one = ob_get_clean();
	check_device( ! str_contains( $one, 'data-digi-carousel-next' ), 'Una captura no debe mostrar flechas.' );
	$slider->settings = array( 'device_type' => 'mobile', 'slides' => array(
		array( '_id' => 'c1', 'background' => 'color', 'title' => 'Color' ),
		array( '_id' => 'v1', 'background' => 'video', 'video' => array( 'url' => 'https://v.test/a.mp4' ), 'title' => 'Demo', 'link' => array( 'url' => 'https://a.test/' ) ),
		array( '_id' => 'y1', 'background' => 'youtube', 'youtube_url' => 'https://youtu.be/YE7VzlLtp-4' ),
		array( '_id' => 'x1', 'background' => 'youtube', 'youtube_url' => 'https://v.test/no-es-youtube' ),
		array( '_id' => 'i1', 'background' => 'video' ),
	) );
	ob_start(); (new \ReflectionMethod( $slider, 'render' ))->invoke( $slider ); $kinds = ob_get_clean();
	check_device( 3 === substr_count( $kinds, '<li class="digi-carousel__slide' ) && str_contains( $kinds, 'elementor-repeater-item-c1"><span class="digi-device-slider__color" aria-hidden="true"></span><span class="digi-device-slider__title">Color</span>' ), 'Una diapositiva de color se pinta con su título; las de vídeo sin dirección válida se omiten.' );
	check_device( str_contains( $kinds, '<video class="digi-device-slider__video" src="https://v.test/a.mp4" autoplay muted loop playsinline controls preload="metadata" aria-label="Demo"></video><a class="digi-device-slider__title" href="https://a.test/">Demo</a>' ), 'El vídeo tiene controles y su enlace pasa al título, fuera del reproductor.' );
	check_device( str_contains( $kinds, 'src="https://www.youtube-nocookie.com/embed/YE7VzlLtp-4?autoplay=1' ) && str_contains( $kinds, 'playlist=YE7VzlLtp-4' ) && str_contains( $kinds, 'rel=0" title="Vídeo de YouTube"' ) && str_contains( $kinds, 'loading="lazy"' ), 'YouTube usa el dominio sin cookies, título accesible y carga diferida fuera de la primera diapositiva.' );
	check_device( 'YE7VzlLtp-4' === \Digitalisimo\Elements\Device_Slider_Widget::youtube_id( 'https://www.youtube.com/watch?v=YE7VzlLtp-4&t=3' ) && 'YE7VzlLtp-4' === \Digitalisimo\Elements\Device_Slider_Widget::youtube_id( 'https://www.youtube.com/shorts/YE7VzlLtp-4' ) && '' === \Digitalisimo\Elements\Device_Slider_Widget::youtube_id( 'https://v.test/a.mp4' ), 'El identificador de YouTube se extrae de enlaces largos, cortos y Shorts.' );
	check_device( isset( $slider->controls['slides']['fields']['background'], $slider->controls['slides']['fields']['color'], $slider->controls['slides']['fields']['video'], $slider->controls['slides']['fields']['youtube_url'] ) && 'image' === $slider->controls['slides']['fields']['background']['default'], 'Las diapositivas existentes siguen siendo de imagen por defecto.' );
	$card->settings = array( 'icon_type' => 'image', 'image' => array( 'id' => 10, 'url' => 'https://site.test/captura.webp' ), 'title_text' => 'Tarjeta <script>x</script>', 'description_text' => 'Texto', 'title_size' => 'script', 'button_text' => 'Leer', 'link' => array( 'url' => 'https://site.test/leer', 'is_external' => true, 'nofollow' => true ) );
	ob_start(); (new \ReflectionMethod( $card, 'render' ))->invoke( $card ); $html = ob_get_clean();
	check_device( str_contains( $html, '<article class="digi-fancy-card">' ) && str_contains( $html, '<h3 ' ) && str_contains( $html, 'href="https://site.test/leer"' ) && ! str_contains( $html, '<script' ), 'La tarjeta debe tener estructura semántica y salida saneada.' );
	check_device( str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="nofollow noopener noreferrer"' ), 'La tarjeta debe proteger enlaces externos.' );
	ob_start(); (new \ReflectionMethod( $card, 'content_template' ))->invoke( $card ); $editor = ob_get_clean();
	check_device( str_contains( $editor, 'elementor.helpers.renderIcon' ) && str_contains( $editor, 'settings.image.alt' ) && str_contains( $editor, 'link.is_external' ), 'El editor debe mostrar icono, ALT y atributos de enlace.' );
	$card->settings['link']['url'] = '';
	ob_start(); (new \ReflectionMethod( $card, 'render' ))->invoke( $card ); $html = ob_get_clean();
	check_device( ! str_contains( $html, '<a ' ), 'Sin URL no debe existir enlace vacío.' );
	$card->settings['social_links'] = array(
		array( '_id' => 'a1', 'social_icon' => array( 'value' => 'fab fa-linkedin-in', 'library' => 'fa-brands' ), 'social_label' => 'LinkedIn <b>', 'social_link' => array( 'url' => 'https://www.linkedin.com/in/ana', 'is_external' => true ) ),
		array( '_id' => 'a2', 'social_icon' => array( 'value' => '' ), 'social_label' => 'Sitio', 'social_link' => array( 'url' => 'https://ana.test/' ) ),
		array( '_id' => 'a3', 'social_icon' => array( 'value' => 'fab fa-x-twitter' ), 'social_label' => 'X', 'social_link' => array( 'url' => '' ) ),
	);
	ob_start(); (new \ReflectionMethod( $card, 'render' ))->invoke( $card ); $html = ob_get_clean();
	check_device( 2 === substr_count( $html, '<li><a ' ) && str_contains( $html, '<ul class="digi-fancy-card__social">' ), 'Las redes sin enlace no se pintan.' );
	check_device( str_contains( $html, 'href="https://www.linkedin.com/in/ana" target="_blank" class="digi-fancy-card__social-link elementor-repeater-item-a1" rel="noopener noreferrer" aria-label="LinkedIn"><i aria-hidden="true"></i></a>' ), 'Un perfil con icono usa el nombre como aria-label.' );
	check_device( str_contains( $html, 'elementor-repeater-item-a2">Sitio</a>' ) && ! str_contains( $html, 'aria-label="Sitio"' ), 'Un perfil sin icono muestra su nombre como texto.' );
	check_device( isset( $card->controls['social_links']['fields']['social_icon'], $card->controls['social_links']['fields']['social_label'], $card->controls['social_links']['fields']['social_link'] ) && array() === $card->controls['social_links']['default'], 'El repetidor de redes empieza vacío para no cambiar tarjetas existentes.' );
	echo "DIGITALÍSIMO Elements: Slider de dispositivos y Tarjeta destacada validados.\n";
}
