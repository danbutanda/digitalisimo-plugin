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
	// Estado en vivo: lunes 12 de octubre de 2026 a las 10:30 UTC.
	\Digitalisimo\Elements\Business_Hours_Widget::$now = new \DateTimeImmutable( '2026-10-12 10:30:00 UTC' );
	$week = array( array( '_id' => 'm', 'day' => 'Lun', 'weekday' => '1', 'hours' => '', 'opens' => '09:00', 'closes' => '17:00' ), array( '_id' => 'd', 'day' => 'Domingo', 'hours' => 'Cerrado' ) );
	$hours->settings = array( 'live_status' => 'yes', 'open_text' => 'Abierto <em>ya</em>', 'closed_text' => 'Cerrado', 'show_clock' => 'yes', 'show_date' => 'yes', 'rows' => $week );
	$h = $render( $hours );
	check_mh( str_contains( $h, 'data-weekday="1" data-opens="09:00" data-closes="17:00"><dt>Lun</dt><dd>09:00 – 17:00</dd>' ), 'El día explícito manda sobre el nombre y el horario vacío se escribe con apertura y cierre.' );
	check_mh( str_contains( $h, '<div class="digi-hours__status is-open" role="status" data-state="open">Abierto &lt;em&gt;ya&lt;/em&gt;</div><template class="digi-hours__alt" data-state="closed">Cerrado</template>' ), 'Abierto a las 10:30 en la zona del sitio; el otro mensaje viaja en un template.' );
	check_mh( str_contains( $h, 'data-tz="UTC"' ) && str_contains( $h, 'data-today="2026-10-12"' ) && str_contains( $h, '<time class="digi-hours__clock">10:30</time><span class="digi-hours__date">2026-10-12</span>' ), 'La hora y la fecha se pintan en la zona del negocio.' );
	$hours->settings['timezone'] = '-6';
	$h = $render( $hours );
	check_mh( str_contains( $h, 'data-offset="-360"' ) && str_contains( $h, 'is-closed' ) && str_contains( $h, '<time class="digi-hours__clock">04:30</time>' ), 'Con UTC−6 son las 4:30 y el negocio está cerrado.' );
	$hours->settings['timezone'] = '5.5';
	check_mh( str_contains( $render( $hours ), 'data-offset="330"' ) && str_contains( $render( $hours ), '16:00' ), 'Los desplazamientos fraccionarios se respetan.' );
	$late = array( array( 'weekday' => 0, 'opens' => '22:00', 'closes' => '02:00' ) );
	check_mh( \Digitalisimo\Elements\Business_Hours_Widget::is_open( $late, new \DateTimeImmutable( '2026-10-12 01:30:00 UTC' ) ) && ! \Digitalisimo\Elements\Business_Hours_Widget::is_open( $late, new \DateTimeImmutable( '2026-10-12 02:00:00 UTC' ) ) && \Digitalisimo\Elements\Business_Hours_Widget::is_open( $late, new \DateTimeImmutable( '2026-10-11 23:00:00 UTC' ) ), 'Un cierre anterior a la apertura termina al día siguiente.' );
	check_mh( '17:05' === \Digitalisimo\Elements\Business_Hours_Widget::clock_value( '17:05' ) && '' === \Digitalisimo\Elements\Business_Hours_Widget::clock_value( '25:00' ) && '09:00' === \Digitalisimo\Elements\Business_Hours_Widget::clock_value( '9:00' ), 'Las horas se normalizan a HH:MM.' );
	$hours->settings = array( 'rows' => $week );
	$h = $render( $hours );
	check_mh( ! str_contains( $h, 'data-opens' ) && ! str_contains( $h, 'digi-hours__status' ) && ! str_contains( $h, 'data-tz' ), 'Sin estado en vivo el horario se pinta como antes.' );
	\Digitalisimo\Elements\Business_Hours_Widget::$now = null;
	echo "DIGITALÍSIMO Elements: audio, comparador y horario validados.\n";
}
