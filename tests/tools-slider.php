<?php
namespace Elementor {
	class Controls_Manager {
		const SELECT = 'select';
		const GALLERY = 'gallery';
		const REPEATER = 'repeater';
		const MEDIA = 'media';
		const TEXT = 'text';
		const URL = 'url';
		const NUMBER = 'number';
		const SWITCHER = 'switcher';
		const SLIDER = 'slider';
		const DIMENSIONS = 'dimensions';
		const TAB_STYLE = 'style';
	}
	class Utils { public static function get_placeholder_image_src() { return '/placeholder.png'; } }
	class Repeater {
		private $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
	}
	class Widget_Base {
		public $test_settings = array();
		public $test_controls = array();
		public function get_settings_for_display() { return $this->test_settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->test_controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->test_controls[ $name ] = $args; }
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
	$controls = new \ReflectionMethod( $widget, 'register_controls' );
	$controls->invoke( $widget );
	if ( 'gallery' !== ( $widget->test_controls['gallery_images']['type'] ?? '' ) || 'repeater' !== ( $widget->test_controls['images']['type'] ?? '' ) ) throw new \RuntimeException( 'El editor debe ofrecer selección múltiple y conservar los elementos individuales.' );
	$visible = $widget->test_controls['visible'];
	if ( 'number' !== $visible['type'] || 1 !== $visible['min'] || 9 !== $visible['max'] || 1 !== $visible['step'] || 9 !== $visible['default'] || 6 !== $visible['tablet_default'] || 3 !== $visible['mobile_default'] ) throw new \RuntimeException( 'El editor debe permitir 1–9 elementos visibles por dispositivo.' );
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
	$assert( false !== strpos( $html, 'sizes="(max-width: 767px) 20vw, (max-width: 1024px) 20vw, 20vw"' ), 'Los tamaños responsive deben seguir la cantidad configurada.' );
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
	$widget->test_settings['gallery_images'] = array( array( 'id' => 21, 'url' => '/media/21.webp' ), array( 'id' => 22, 'url' => '/media/22.webp' ), array( 'id' => 23, 'url' => '/media/23.webp' ), array( 'id' => 0 ) );
	$widget->test_settings['images'] = array();
	ob_start();
	$method->invoke( $widget );
	$html = ob_get_clean();
	$assert( 3 === substr_count( $html, '<img ' ) && 3 === substr_count( $html, 'alt="ALT guardado"' ), 'Una selección múltiple debe producir el slider sin crear elementos individuales.' );
	$widget->test_settings['images'] = array( array( 'image' => array( 'id' => 13 ), 'alt' => 'Cliente', 'link' => array( 'url' => 'https://example.com', 'is_external' => true ) ) );
	ob_start();
	$method->invoke( $widget );
	$html = ob_get_clean();
	$assert( 4 === substr_count( $html, '<img ' ), 'Tres imágenes seleccionadas juntas y un elemento anterior deben formar cuatro diapositivas.' );
	$positions = array_map( function ( $id ) use ( $html ) { return strpos( $html, '/media/' . $id . '.webp' ); }, array( 21, 22, 23, 13 ) );
	$assert( ! in_array( false, $positions, true ) && $positions[0] < $positions[1] && $positions[1] < $positions[2] && $positions[2] < $positions[3], 'La galería debe respetar el orden y conservar el elemento individual al final.' );
	$assert( 3 === substr_count( $html, 'alt="ALT guardado"' ) && 1 === substr_count( $html, 'alt="Cliente"' ) && 1 === substr_count( $html, 'href="https://example.com"' ), 'La galería debe usar el ALT de cada adjunto sin duplicar el enlace individual.' );
	$widget->test_settings['images'] = array();
	$widget->test_settings['animation'] = 'continuous';
	$widget->test_settings['infinite'] = 'yes';
	ob_start();
	$method->invoke( $widget );
	$html = ob_get_clean();
	$assert( 20 === substr_count( $html, '<img ' ) && 3 === substr_count( $html, 'alt="ALT guardado"' ) && 17 === substr_count( $html, 'alt=""' ) && 18 === substr_count( $html, 'aria-hidden="true"' ), 'La galería debe mantener un único conjunto duplicado y ocultar las imágenes de relleno.' );
	echo "Slider Optimizado: selección múltiple, elementos anteriores, espejo y accesibilidad correctos.\n";
}
