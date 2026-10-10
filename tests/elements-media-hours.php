<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_mh( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return 0 === strpos( (string) $v, 'javascript:' ) ? '' : (string) $v; }
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ); }
	foreach ( array( 'audio-player', 'image-compare', 'business-hours' ) as $file ) {
		require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-' . $file . '.php';
	}
	$render = static function ( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); };

	$audio = new \Digitalisimo\Elements\Audio_Player_Widget();
	$audio->settings = array( 'loop' => 'yes', 'preload' => 'none', 'tracks' => array( array( 'title' => 'Uno', 'artist' => 'Ana <b>x</b>', 'audio_url' => array( 'url' => 'https://s.test/1.mp3' ), 'cover' => array( 'url' => 'https://s.test/c.jpg' ) ), array( 'title' => 'Sin archivo' ) ) );
	$html = $render( $audio );
	check_mh( str_contains( $html, '<audio controls preload="none" loop src="https://s.test/1.mp3" aria-label="Uno — Ana x"></audio>' ) && ! str_contains( $html, 'Sin archivo' ) && str_contains( $html, 'digi-audio__cover' ), 'El audio usa <audio> nativo con nombre y omite pistas sin archivo.' );

	$cmp = new \Digitalisimo\Elements\Image_Compare_Widget();
	$cmp->settings = array( 'before_image' => array( 'url' => 'https://s.test/a.jpg' ), 'after_image' => array( 'url' => 'https://s.test/b.jpg' ), 'before_label' => 'Antes', 'after_label' => 'Después', 'start' => 130 );
	$c = $render( $cmp );
	check_mh( str_contains( $c, '--digi-compare-pos:100%' ) && str_contains( $c, 'type="range" min="0" max="100" value="100" aria-label="Comparar Antes y Después"' ) && str_contains( $c, 'alt="Antes"' ), 'El comparador usa un control deslizante nativo y limita la posición.' );
	$cmp->settings['after_image'] = array();
	check_mh( '' === $render( $cmp ), 'Sin las dos imágenes no se muestra.' );

	$hours = new \Digitalisimo\Elements\Business_Hours_Widget();
	$hours->settings = array( 'heading' => 'Horario', 'highlight_today' => 'yes', 'rows' => array( array( 'day' => 'Lunes', 'hours' => '9 – 18' ), array( 'day' => 'Sábado', 'hours' => 'Cerrado', 'highlight' => 'yes' ), array( 'day' => 'Lunes a viernes', 'hours' => '9 – 18' ) ) );
	$h = $render( $hours );
	check_mh( str_contains( $h, 'data-weekday="1"><dt>Lunes</dt><dd>9 – 18</dd>' ) && str_contains( $h, 'is-highlighted' ) && str_contains( $h, 'data-weekday="6"' ) && str_contains( $h, '<dt>Lunes a viernes</dt>' ) && 3 === substr_count( $h, '<dt>' ) && 2 === substr_count( $h, 'data-weekday' ), 'El horario es una lista de descripción con el día reconocido.' );
	echo "DIGITALÍSIMO Elements: audio, comparador y horario validados.\n";
}
