<?php
namespace Elementor {
	class Widget_Base {
		public $test_settings = array();
		public function get_settings_for_display() { return $this->test_settings; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ); }
	function get_intermediate_image_sizes() { return array( 'thumbnail', 'medium', 'large' ); }
	function get_post_meta( $id, $key ) { return 'ALT guardado'; }
	function esc_url( $value ) { return filter_var( $value, FILTER_VALIDATE_URL ) ? htmlspecialchars( $value, ENT_QUOTES ) : ''; }
	function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
		$tag = '<img src="/media/' . $id . '.webp" width="800" height="400"';
		foreach ( $attrs as $key => $value ) $tag .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		return $tag . '>';
	}
	require __DIR__ . '/../digitalisimo-tools/modules/elementor-slider/class-widget.php';
	$widget = new \Digitalisimo\Tools\Elementor_Slider_Widget();
	$widget->test_settings = array(
		'mode' => 'repeat', 'repeat_image' => array( 'id' => 12 ), 'repeat_alt' => 'Onda',
		'repeat_count' => 3, 'mirror' => 'yes', 'animation' => 'continuous', 'infinite' => 'yes',
		'visible' => 5, 'visible_tablet' => 5, 'visible_mobile' => 5,
		'priority' => 'high', 'loading' => 'lazy', 'image_size' => 'large', 'label' => 'Ondas',
	);
	$method = new \ReflectionMethod( $widget, 'render' );
	ob_start();
	$method->invoke( $widget );
	$html = ob_get_clean();
	$assert = function ( $condition, $message ) { if ( ! $condition ) throw new \RuntimeException( $message ); };
	$assert( 20 === substr_count( $html, 'aria-hidden="true"' ), 'Debe ocultarse la copia y todas las repeticiones decorativas.' );
	$assert( 20 === substr_count( $html, '<img ' ), 'La serie impar con espejo debe cubrir la vista y duplicarse una vez.' );
	$assert( 1 === substr_count( $html, 'alt="Onda"' ) && 19 === substr_count( $html, 'alt=""' ), 'Las imágenes decorativas deben tener ALT vacío.' );
	$assert( 1 === substr_count( $html, 'fetchpriority="high"' ) && 1 === substr_count( $html, 'loading="eager"' ), 'Sólo la primera imagen prioritaria debe ser eager y de prioridad alta.' );
	$assert( 19 === substr_count( $html, 'loading="lazy"' ), 'Las demás imágenes, incluida la copia decorativa, deben cargarse lazy.' );
	$assert( 10 === substr_count( $html, 'digi-slider__item--mirror' ), 'El espejo debe alternarse en ambas series.' );
	$widget->test_settings = array(
		'mode' => 'multiple', 'images' => array( array( 'image' => array( 'id' => 13 ), 'alt' => 'Cliente', 'link' => array( 'url' => 'https://example.com', 'is_external' => true ) ) ),
		'animation' => 'off', 'image_size' => 'large',
	);
	ob_start();
	$method->invoke( $widget );
	$html = ob_get_clean();
	$assert( 1 === substr_count( $html, '<img ' ) && 0 === substr_count( $html, 'aria-hidden="true"' ), 'Sin animación no debe duplicarse el DOM.' );
	$assert( false !== strpos( $html, 'rel="noopener"' ) && false !== strpos( $html, 'alt="Cliente"' ), 'Debe conservar el enlace y ALT.' );
	echo "Slider Optimizado: render, espejo, duplicación decorativa y prioridad correctos.\n";
}
