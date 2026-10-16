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
	check_slider_load( 4 === count( $images ) && str_contains( $html, 'digi-slider--preparing' ) && str_contains( $html, '--digi-loop-scale:0.2' ) && str_contains( $html, 'data-digi-sources="1"' ), 'El bucle debe iniciar pausado con dos grupos del largo necesario, la escala de velocidad equivalente y una sola imagen distinta.' );
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
	check_slider_load( str_contains( $css, 'calc(var(--digi-loop-duration,var(--digi-duration))*var(--digi-loop-scale,1)*var(--digi-fit-scale,1))' ) && str_contains( $css, '.digi-slider--preparing .digi-slider__track{animation-delay:3s}' ) && str_contains( $css, 'linear infinite backwards' ) && str_contains( $css, 'linear 1 both' ), 'La duración se escala por variables (editor en vivo), sin script el movimiento arranca solo y el modo de una pasada conserva el final.' );
	$js = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/js/slider-optimizado.js' );
	check_slider_load( str_contains( $js, "slider.classList.contains('digi-slider--mirror-repeat')" ) && str_contains( $js, "classList.contains('digi-slider--mirror')" ) && str_contains( $js, 'slider.dataset.digiSources' ) && str_contains( $js, "classList.contains('digi-slider--natural')" ), 'El arranque espera también a las copias alejadas de la onda; el recorte respeta la paridad del espejo, las imágenes distintas y el ancho natural.' );
	$widget->settings['repeat_count'] = 100;
	$widget->settings['speed'] = 75;
	$many = $render();
	check_slider_load( 4 === substr_count( $many, '<img ' ) && str_contains( $many, '--digi-loop-duration:2s' ), 'Aun con 100 repeticiones la pista no debe crecer ni ejecutar una duración excesivamente breve.' );
	$widget->settings['infinite'] = '';
	$widget->settings['repeat_count'] = 3;
	$once = $render();
	check_slider_load( 3 === substr_count( $once, '<img ' ) && ! str_contains( $once, 'digi-slider--mirror-loop' ) && ! str_contains( $once, '--digi-loop-' ) && ! str_contains( $once, 'data-digi-sources' ), 'Sin bucle infinito deben conservarse todas las repeticiones elegidas.' );
	$widget->settings['mirror'] = '';
	$widget->settings['repeat_count'] = 2;
	$widget->settings['animation'] = 'off';
	$static = $render();
	check_slider_load( ! str_contains( $static, 'digi-slider--preparing' ) && 2 === substr_count( $static, '<img' ), 'El modo estático debe conservar sus imágenes sin esperar JavaScript.' );

	// Onda hecha con diez copias de la misma imagen en modo múltiple (como en sitios reales): cuenta como una sola imagen,
	// el grupo cubre la vista más exigente (tableta: 6 + 1, par con espejo) y la velocidad se conserva.
	$widget->settings = array( 'mode' => 'multiple', 'gallery_images' => array_fill( 0, 10, array( 'id' => 1 ) ), 'mirror' => 'yes', 'animation' => 'continuous', 'infinite' => 'yes', 'direction' => 'right', 'visible' => 1, 'visible_tablet' => 6, 'visible_mobile' => 1, 'speed' => 75 );
	$wave = $render();
	check_slider_load( 16 === substr_count( $wave, '<img ' ) && 8 === substr_count( $wave, 'digi-slider__item--mirror' ) && preg_match( '/digi-slider--mirror[" ]/', $wave ) && ! str_contains( $wave, 'digi-slider--mirror-repeat' ) && str_contains( $wave, '--digi-loop-scale:0.8' ) && str_contains( $wave, 'data-digi-sources="1"' ), 'Diez copias de una imagen forman un grupo de ocho (seis visibles más uno, par) a la misma velocidad.' );
	check_slider_load( 16 === substr_count( $wave, 'aria-hidden="true"' ), 'Las copias colapsadas se anuncian una sola vez: siete decorativas en el primer grupo y la copia entera.' );
	// Doce logotipos distintos con nueve visibles: el grupo ya cubre la vista y se conservan todos, sin reescalar.
	$widget->settings = array( 'mode' => 'multiple', 'gallery_images' => array_map( static function ( $id ) { return array( 'id' => $id ); }, range( 1, 12 ) ), 'animation' => 'continuous', 'infinite' => 'yes', 'visible' => 9, 'visible_tablet' => 6, 'visible_mobile' => 3 );
	$logos = $render();
	check_slider_load( 24 === substr_count( $logos, '<img ' ) && ! str_contains( $logos, '--digi-loop-' ) && ! str_contains( $logos, 'digi-slider--mirror' ) && str_contains( $logos, 'data-digi-sources="12"' ), 'Doce logotipos distintos se conservan enteros y a su velocidad, y el script sabe que son doce.' );
	// Tres logotipos con nueve visibles: se repiten hasta cubrir la vista (diez), el mismo largo que antes.
	$widget->settings['gallery_images'] = array_map( static function ( $id ) { return array( 'id' => $id ); }, range( 1, 3 ) );
	$three = $render();
	check_slider_load( 20 === substr_count( $three, '<img ' ) && ! str_contains( $three, '--digi-loop-' ) && str_contains( $three, 'data-digi-sources="3"' ), 'Tres logotipos se repiten hasta cubrir nueve visibles más uno.' );
	// Tres logotipos con tres visibles: bastan cuatro por grupo y la duración baja en proporción (antes eran diez).
	$widget->settings['visible'] = 3; $widget->settings['visible_tablet'] = 3; $widget->settings['visible_mobile'] = 3;
	$four = $render();
	check_slider_load( 8 === substr_count( $four, '<img ' ) && str_contains( $four, '--digi-loop-scale:0.4' ), 'Con tres visibles el grupo queda en cuatro y la duración se escala para conservar la velocidad.' );
	// «Elementos visibles» vacío en escritorio usa el 9 de la hoja de estilos; un breakpoint adicional también cuenta.
	$widget->settings['visible'] = '';
	$blank = $render();
	check_slider_load( 20 === substr_count( $blank, '<img ' ) && ! str_contains( $blank, '--digi-loop-' ), 'Un campo vacío no cuenta como cero: el grupo cubre los nueve visibles de la hoja de estilos.' );
	$widget->settings['visible'] = 3; $widget->settings['visible_widescreen'] = 5;
	$wide = $render();
	check_slider_load( 12 === substr_count( $wide, '<img ' ) && str_contains( $wide, '--digi-loop-scale:0.6' ), 'Un breakpoint adicional de Elementor entra en el cálculo del largo.' );
	// «Ancho natural»: cada copia mide lo que mide la imagen, así que se conserva el largo anterior y no se escala.
	$widget->settings = array( 'mode' => 'repeat', 'repeat_image' => array( 'id' => 1 ), 'repeat_count' => 10, 'natural_width' => 'yes', 'animation' => 'continuous', 'infinite' => 'yes', 'visible' => 1, 'visible_tablet' => 1, 'visible_mobile' => 1 );
	$natural = $render();
	check_slider_load( 20 === substr_count( $natural, '<img ' ) && str_contains( $natural, 'digi-slider--natural' ) && ! str_contains( $natural, '--digi-loop-' ), 'Con ancho natural el bucle conserva sus diez copias y su velocidad.' );
	echo "DIGITALÍSIMO Elements: carga inicial del slider y largo del bucle validados.\n";
}
