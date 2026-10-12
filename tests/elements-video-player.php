<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display() { return $this->settings; }
		public function get_settings() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) {}
	}
	class Controls_Manager { const SELECT = 'select'; const MEDIA = 'media'; const URL = 'url'; const TEXT = 'text'; const REPEATER = 'repeater'; }
	class Repeater { public function add_control( $name, $args ) {} public function get_controls() { return array(); } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function absint( $value ) { return abs( (int) $value ); }
	function get_post_mime_type( $id ) { return 7 === $id ? 'video/mp4' : 'image/png'; }
	function wp_get_attachment_url( $id ) { return 'https://sitio.test/uploads/video.mp4'; }
	function wp_get_attachment_image_url( $id, $size ) { return 'https://sitio.test/uploads/poster.jpg'; }
	function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function check_video( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-video-player.php';
	$widget = new \Digitalisimo\Elements\Video_Player_Widget();
	check_video( array( 'digitalisimo-video-player' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'Debe cargar sólo CSS condicional.' );
	$render = new \ReflectionMethod( $widget, 'render' );
	$widget->settings = array( 'source' => 'media', 'video' => array( 'id' => 7 ), 'poster' => array( 'id' => 8 ), 'title' => 'Presentación <b>real</b>', 'ratio' => '4/3' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_video( str_contains( $html, '<video controls playsinline preload="none"' ) && str_contains( $html, 'data-ratio="4/3"' ) && str_contains( $html, 'poster="https://sitio.test/uploads/poster.jpg"' ) && str_contains( $html, 'Presentación real' ), 'Debe conservar controles, proporción, portada y nombre accesible.' );
	$widget->settings['video']['id'] = 9;
	ob_start(); $render->invoke( $widget ); check_video( '' === ob_get_clean(), 'No debe insertar una imagen como video.' );
	$widget->settings = array( 'source' => 'url', 'video_url' => array( 'url' => 'javascript:alert(1)' ) );
	ob_start(); $render->invoke( $widget ); check_video( '' === ob_get_clean(), 'No debe aceptar esquemas inseguros.' );
	$widget->settings['video_url']['url'] = 'https://cdn.test/clip.webm';
	ob_start(); $render->invoke( $widget ); check_video( str_contains( ob_get_clean(), 'https://cdn.test/clip.webm' ), 'Debe aceptar un video HTTPS explícito.' );
	$widget->settings = array( 'source' => 'media', 'video' => array( 'id' => 7 ), 'title' => 'Principal', 'playlist_trigger' => 'hover', 'playlist' => array(
		array( 'source' => 'url', 'video_url' => array( 'url' => 'https://cdn.test/a.mp4' ), 'poster' => array( 'url' => 'https://cdn.test/a.jpg' ), 'title' => 'Primero <i>' ),
		array( 'source' => 'url', 'video_url' => array( 'url' => 'javascript:alert(1)' ), 'title' => 'Inválido' ),
		array( 'source' => 'url', 'video_url' => array( 'url' => 'https://cdn.test/b.mp4' ), 'title' => '' ),
	) );
	ob_start(); $render->invoke( $widget ); $list = ob_get_clean();
	check_video( str_contains( $list, 'class="digi-video-player digi-video-player--playlist" data-digi-playlist="hover"' ) && str_contains( $list, 'aria-label="Primero" poster="https://cdn.test/a.jpg"><source src="https://cdn.test/a.mp4">' ), 'Con lista, el primer vídeo válido ocupa el reproductor.' );
	check_video( 2 === substr_count( $list, '<button type="button" class="digi-video-player__item"' ) && str_contains( $list, 'aria-pressed="true" data-kind="video" data-src="https://cdn.test/a.mp4" data-poster="https://cdn.test/a.jpg" data-title="Primero"><span class="digi-video-player__item-title">Primero</span></button>' ) && str_contains( $list, 'aria-pressed="false" data-kind="video" data-src="https://cdn.test/b.mp4" data-poster="" data-title="Video 2"><span class="digi-video-player__item-title">Video 2</span></button>' ) && ! str_contains( $list, 'javascript' ), 'La lista omite direcciones no válidas y numera los vídeos sin título.' );
	check_video( str_contains( $list, '<div class="digi-video-player__stage"><video ' ) && str_contains( $list, '</video></div><ul class="digi-video-player__playlist"' ), 'Con lista, el vídeo va en su escenario y la lista queda fuera de la proporción.' );
	check_video( array( 'digitalisimo-video-player' ) === $widget->get_script_depends(), 'Con lista se carga el script que cambia de vídeo.' );
	$widget->settings['playlist'] = array_slice( $widget->settings['playlist'], 0, 1 );
	ob_start(); $render->invoke( $widget ); $single = ob_get_clean();
	check_video( ! str_contains( $single, 'digi-video-player__playlist' ) && str_contains( $single, 'aria-label="Principal"' ) && array() === $widget->get_script_depends(), 'Con un solo vídeo en la lista se usa el video principal, sin script.' );
	check_video( 'https://www.youtube-nocookie.com/embed/YE7VzlLtp-4?rel=0' === \Digitalisimo\Elements\Video_Player_Widget::embed_url( 'https://www.youtube.com/watch?v=YE7VzlLtp-4' ) && 'https://player.vimeo.com/video/76979871?dnt=1' === \Digitalisimo\Elements\Video_Player_Widget::embed_url( 'https://vimeo.com/76979871' ) && 'https://www.dailymotion.com/embed/video/x7tgad0' === \Digitalisimo\Elements\Video_Player_Widget::embed_url( 'https://www.dailymotion.com/video/x7tgad0' ) && 'https://fast.wistia.net/embed/iframe/abc123' === \Digitalisimo\Elements\Video_Player_Widget::embed_url( 'https://home.wistia.com/medias/abc123' ) && '' === \Digitalisimo\Elements\Video_Player_Widget::embed_url( 'https://cdn.test/a.mp4' ), 'Los enlaces de los cuatro servicios se incrustan sin rastreo cuando es posible.' );
	$widget->settings = array( 'source' => 'embed', 'embed_url' => 'https://youtu.be/YE7VzlLtp-4', 'title' => 'Tráiler' );
	ob_start(); $render->invoke( $widget ); $embed = ob_get_clean();
	check_video( '<div class="digi-video-player" data-ratio="16/9"><iframe class="digi-video-player__embed" src="https://www.youtube-nocookie.com/embed/YE7VzlLtp-4?rel=0" title="Tráiler" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe></div>' === $embed, 'Un vídeo incrustado usa iframe con título y carga diferida.' );
	$widget->settings = array( 'source' => 'url', 'video_url' => array( 'url' => 'https://vimeo.com/76979871' ) );
	ob_start(); $render->invoke( $widget ); $vimeo = ob_get_clean();
	check_video( str_contains( $vimeo, 'src="https://player.vimeo.com/video/76979871?dnt=1"' ) && ! str_contains( $vimeo, '<video' ), 'Un enlace de servicio escrito como URL externa también se incrusta.' );
	$widget->settings = array( 'playlist' => array(
		array( 'source' => 'embed', 'embed_url' => 'https://youtu.be/YE7VzlLtp-4', 'title' => 'YouTube', 'description' => 'Primer <b>clip</b>' ),
		array( 'source' => 'url', 'video_url' => array( 'url' => 'https://cdn.test/a.mp4' ), 'title' => 'Archivo' ),
		array( 'source' => 'embed', 'embed_url' => 'https://ejemplo.test/nada', 'title' => 'Inválido' ),
	) );
	ob_start(); $render->invoke( $widget ); $mixed = ob_get_clean();
	check_video( str_contains( $mixed, '<div class="digi-video-player__stage"><iframe class="digi-video-player__embed"' ) && str_contains( $mixed, 'data-kind="embed"' ) && str_contains( $mixed, 'data-kind="video"' ) && str_contains( $mixed, '<span class="digi-video-player__item-description">Primer clip</span>' ) && ! str_contains( $mixed, 'Inválido' ), 'La lista mezcla vídeos incrustados y archivos, con descripción en texto plano.' );
	echo "DIGITALÍSIMO Elements: reproductor nativo validado.\n";
}
