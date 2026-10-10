<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
	}
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_pr( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ); }
	function strip_shortcodes( $v ) { return preg_replace( '/\[[^\]]*\]/', '', (string) $v ); }
	function get_post() { return (object) array( 'post_content' => str_repeat( 'palabra ', 450 ) . '[galeria]' ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-progress-bars.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-reading-time.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-reading-progress.php';
	$render = static function ( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); };

	$bars = new \Digitalisimo\Elements\Progress_Bars_Widget();
	$bars->settings = array( 'layout' => 'bar', 'show_value' => 'yes', 'suffix' => '%', 'items' => array( array( '_id' => 'a', 'label' => 'Diseño <b>x</b>', 'value' => 150, 'max' => 100 ), array( 'label' => 'SEO', 'value' => 40, 'max' => 50, 'text' => 'Nota' ) ) );
	$html = $render( $bars );
	check_pr( str_contains( $html, 'role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" aria-label="Diseño x"' ) && str_contains( $html, '--digi-progress-value:100' ) && str_contains( $html, '--digi-progress-value:80' ) && str_contains( $html, '<p class="digi-progress__text">Nota</p>' ), 'Las barras limitan el valor al máximo y exponen su valor accesible.' );
	$bars->settings['layout'] = 'circle';
	$circle = $render( $bars );
	check_pr( str_contains( $circle, 'stroke-dashoffset:0' ) && str_contains( $circle, 'stroke-dashoffset:56.55' ) && str_contains( $circle, '<span class="digi-progress__value">40%</span>' ), 'El círculo calcula su arco en el servidor.' );

	check_pr( 3 === \Digitalisimo\Elements\Reading_Time_Widget::minutes( str_repeat( 'a ', 401 ), 200 ) && 1 === \Digitalisimo\Elements\Reading_Time_Widget::minutes( '', 200 ), 'Los minutos se redondean hacia arriba y nunca son cero.' );
	$time = new \Digitalisimo\Elements\Reading_Time_Widget();
	$time->settings = array( 'words_per_minute' => 150, 'before' => 'Lectura:', 'minute_text' => 'min' );
	check_pr( str_contains( $render( $time ), 'Lectura: <span class="digi-reading-time__value">3</span> min' ), 'El tiempo de lectura usa el contenido de la entrada sin shortcodes.' );
	$bar = new \Digitalisimo\Elements\Reading_Progress_Widget();
	$bar->settings = array( 'position' => 'bottom' );
	check_pr( str_contains( $render( $bar ), 'digi-reading-progress--bottom" aria-hidden="true" data-digi-reading-progress' ), 'La barra de lectura es decorativa y se fija abajo si se pide.' );
	echo "DIGITALÍSIMO Elements: progreso y lectura validados.\n";
}
