<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) {}
	}
	class Controls_Manager { const SELECT = 'select'; const MEDIA = 'media'; const URL = 'url'; const TEXT = 'text'; }
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
	echo "DIGITALÍSIMO Elements: reproductor nativo validado.\n";
}
