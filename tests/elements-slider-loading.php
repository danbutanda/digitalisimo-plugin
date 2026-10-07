<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display() { return $this->settings; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function get_intermediate_image_sizes() { return array( 'large' ); }
	function get_post_meta( $id, $key, $single ) { return 'Onda'; }
	function wp_get_attachment_image_src( $id, $size ) { return array( 'https://example.test/onda.webp', 100, 20 ); }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
		$html = '<img data-id="' . (int) $id . '"';
		foreach ( $attrs as $key => $value ) $html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		return $html . '>';
	}
	function check_slider_load( $condition, $message ) { if ( ! $condition ) throw new \RuntimeException( $message ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-slider/class-widget.php';
	$widget = new \Digitalisimo\Elements\Elementor_Slider_Widget();
	$widget->settings = array( 'mode' => 'repeat', 'repeat_image' => array( 'id' => 1 ), 'repeat_alt' => 'Onda', 'repeat_count' => 2, 'animation' => 'continuous', 'infinite' => 'yes', 'direction' => 'right', 'visible' => 1, 'visible_tablet' => 1, 'visible_mobile' => 1, 'loading' => 'auto', 'priority' => 'high' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	preg_match_all( '/<img\b[^>]*>/', $html, $matches );
	$images = $matches[0];
	check_slider_load( 20 === count( $images ) && str_contains( $html, 'digi-slider--preparing' ), 'El bucle debe iniciar pausado con dos grupos completos.' );
	check_slider_load( str_contains( $images[0], 'loading="lazy"' ) && ! str_contains( $images[0], 'fetchpriority="high"' ), 'La primera imagen fuera de pantalla no debe competir por prioridad.' );
	check_slider_load( str_contains( $images[9], 'loading="eager"' ) && str_contains( $images[10], 'loading="eager"' ) && str_contains( $images[10], 'fetchpriority="high"' ), 'La unión visible del bucle derecho debe cargarse antes del movimiento.' );
	check_slider_load( str_contains( $images[10], 'width="100"' ) && str_contains( $images[10], 'height="20"' ), 'Las dimensiones deben reservar espacio sin saltos.' );
	$widget->settings['animation'] = 'off';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $static = ob_get_clean();
	check_slider_load( ! str_contains( $static, 'digi-slider--preparing' ) && 2 === substr_count( $static, '<img' ), 'El modo estático debe conservar sus imágenes sin esperar JavaScript.' );
	echo "DIGITALÍSIMO Elements: carga inicial del slider validada.\n";
}
