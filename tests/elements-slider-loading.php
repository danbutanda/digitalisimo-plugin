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
	$render = static function () use ( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); };
	$widget->settings = array( 'mode' => 'repeat', 'repeat_image' => array( 'id' => 1 ), 'repeat_alt' => 'Onda', 'repeat_count' => 2, 'animation' => 'continuous', 'infinite' => 'yes', 'direction' => 'right', 'visible' => 1, 'visible_tablet' => 1, 'visible_mobile' => 1, 'loading' => 'auto', 'priority' => 'high' );
	$html = $render();
	preg_match_all( '/<img\b[^>]*>/', $html, $matches );
	$images = $matches[0];
	// Un grupo cubre el viewport más un elemento (aquí dos) y la duración se escala desde el largo de diez que tenía el bucle.
	check_slider_load( 4 === count( $images ) && str_contains( $html, 'digi-slider--preparing' ) && str_contains( $html, '--digi-loop-duration:6s' ), 'El bucle debe iniciar pausado con dos grupos del largo necesario y la velocidad equivalente.' );
	check_slider_load( str_contains( $images[0], 'loading="eager"' ) && ! str_contains( $images[0], 'fetchpriority="high"' ), 'La unión visible del bucle derecho se carga de inmediato sin competir por la prioridad.' );
	check_slider_load( str_contains( $images[2], 'loading="eager"' ) && str_contains( $images[2], 'fetchpriority="high"' ), 'La primera imagen visible del bucle derecho lleva la prioridad.' );
	check_slider_load( str_contains( $images[2], 'width="100"' ) && str_contains( $images[2], 'height="20"' ), 'Las dimensiones deben reservar espacio sin saltos.' );
	$widget->settings['mirror'] = 'yes';
	$mirror = $render();
	check_slider_load( str_contains( $mirror, 'digi-slider--mirror-repeat' ) && preg_match( '/digi-slider--mirror[" ]/', $mirror ), 'La imagen repetida con espejo debe identificar su modo sin alterar otros sliders.' );
	check_slider_load( str_contains( $mirror, 'digi-slider--mirror-loop' ) && str_contains( $mirror, '--digi-loop-duration:6s' ), 'La onda debe conservar la velocidad equivalente con sólo dos mitades por grupo.' );
	check_slider_load( 4 === substr_count( $mirror, 'loading="eager"' ) && ! str_contains( $mirror, 'loading="lazy"' ), 'Las cuatro mitades de la onda deben estar listas antes de animar.' );
	check_slider_load( 2 === substr_count( $mirror, 'digi-slider__item--mirror' ), 'El espejo debe alternar en ambas series y conservar la unión del bucle.' );
	check_slider_load( 4 === substr_count( $mirror, 'sizes="100vw"' ), 'La imagen de onda debe solicitarse al ancho real de cada viewport.' );
	$groups = explode( '<div class="digi-slider__group"', $mirror );
	check_slider_load( 3 === count( $groups ) && str_contains( $groups[2], 'aria-hidden="true"' ), 'El bucle reflejado debe conservar sólo una copia decorativa.' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/slider-optimizado.css' );
	check_slider_load( str_contains( $css, '.digi-slider--mirror-repeat{--digi-gap:0px!important}' ) && str_contains( $css, '.digi-slider--mirror-loop{--digi-visible:1!important}' ), 'Las mitades reflejadas deben conservar el ancho del viewport sin separación.' );
	check_slider_load( str_contains( $css, 'var(--digi-loop-duration,var(--digi-duration))' ) && str_contains( $css, '.digi-slider--preparing .digi-slider__track{animation-delay:3s}' ) && str_contains( $css, 'animation-fill-mode:backwards' ), 'La duración escalada vale para todo bucle y, sin script, el movimiento arranca solo.' );
	$js = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/js/slider-optimizado.js' );
	check_slider_load( str_contains( $js, "slider.classList.contains('digi-slider--mirror-repeat')" ) && str_contains( $js, "classList.contains('digi-slider--mirror')" ), 'El arranque espera también a las copias alejadas de la onda y el recorte respeta la paridad del espejo.' );
	$widget->settings['repeat_count'] = 100;
	$widget->settings['speed'] = 75;
	$many = $render();
	check_slider_load( 4 === substr_count( $many, '<img ' ) && str_contains( $many, '--digi-loop-duration:2s' ), 'Aun con 100 repeticiones la pista no debe crecer ni ejecutar una duración excesivamente breve.' );
	$widget->settings['infinite'] = '';
	$widget->settings['repeat_count'] = 3;
	$once = $render();
	check_slider_load( 3 === substr_count( $once, '<img ' ) && ! str_contains( $once, 'digi-slider--mirror-loop' ) && ! str_contains( $once, '--digi-loop-duration' ), 'Sin bucle infinito deben conservarse todas las repeticiones elegidas.' );
	$widget->settings['mirror'] = '';
	$widget->settings['repeat_count'] = 2;
	$widget->settings['animation'] = 'off';
	$static = $render();
	check_slider_load( ! str_contains( $static, 'digi-slider--preparing' ) && 2 === substr_count( $static, '<img' ), 'El modo estático debe conservar sus imágenes sin esperar JavaScript.' );

	// Onda hecha con diez copias de la misma imagen en modo múltiple (como en sitios reales): cuenta como una sola imagen,
	// el grupo cubre la vista más exigente (tableta: 6 + 1, par con espejo) y la velocidad se conserva.
	$widget->settings = array( 'mode' => 'multiple', 'gallery_images' => array_fill( 0, 10, array( 'id' => 1 ) ), 'mirror' => 'yes', 'animation' => 'continuous', 'infinite' => 'yes', 'direction' => 'right', 'visible' => 1, 'visible_tablet' => 6, 'visible_mobile' => 1, 'speed' => 75 );
	$wave = $render();
	check_slider_load( 16 === substr_count( $wave, '<img ' ) && 8 === substr_count( $wave, 'digi-slider__item--mirror' ) && preg_match( '/digi-slider--mirror[" ]/', $wave ) && ! str_contains( $wave, 'digi-slider--mirror-repeat' ) && str_contains( $wave, '--digi-loop-duration:60s' ), 'Diez copias de una imagen forman un grupo de ocho (seis visibles más uno, par) a la misma velocidad.' );
	// Doce logotipos distintos con nueve visibles: el grupo ya cubre la vista y se conservan todos, sin reescalar.
	$widget->settings = array( 'mode' => 'multiple', 'gallery_images' => array_map( static function ( $id ) { return array( 'id' => $id ); }, range( 1, 12 ) ), 'animation' => 'continuous', 'infinite' => 'yes', 'visible' => 9, 'visible_tablet' => 6, 'visible_mobile' => 3 );
	$logos = $render();
	check_slider_load( 24 === substr_count( $logos, '<img ' ) && ! str_contains( $logos, '--digi-loop-duration' ) && ! str_contains( $logos, 'digi-slider--mirror' ), 'Doce logotipos distintos se conservan enteros y a su velocidad.' );
	// Tres logotipos con nueve visibles: se repiten hasta cubrir la vista (diez), el mismo largo que antes.
	$widget->settings['gallery_images'] = array_map( static function ( $id ) { return array( 'id' => $id ); }, range( 1, 3 ) );
	$three = $render();
	check_slider_load( 20 === substr_count( $three, '<img ' ) && ! str_contains( $three, '--digi-loop-duration' ), 'Tres logotipos se repiten hasta cubrir nueve visibles más uno.' );
	// Tres logotipos con tres visibles: bastan cuatro por grupo y la duración baja en proporción (antes eran diez).
	$widget->settings['visible'] = 3; $widget->settings['visible_tablet'] = 3; $widget->settings['visible_mobile'] = 3;
	$four = $render();
	check_slider_load( 8 === substr_count( $four, '<img ' ) && str_contains( $four, '--digi-loop-duration:12s' ), 'Con tres visibles el grupo queda en cuatro y la duración se escala para conservar la velocidad.' );
	echo "DIGITALÍSIMO Elements: carga inicial del slider y largo del bucle validados.\n";
}
