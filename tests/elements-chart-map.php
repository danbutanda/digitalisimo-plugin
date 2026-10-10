<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_id() { return 'ch1'; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_cm( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-chart.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-map.php';
	$render = static function ( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); };

	check_cm( array( 2.0, 4.5, -1.0 ) === \Digitalisimo\Elements\Chart_Widget::numbers( '2; 4,5; -1' ), 'Los valores admiten coma decimal y negativos.' );
	$chart = new \Digitalisimo\Elements\Chart_Widget();
	$chart->settings = array( 'chart_type' => 'bar', 'caption' => 'Ventas <b>x</b>', 'labels' => 'Ene; Feb', 'suffix' => ' €', 'show_legend' => 'yes', 'datasets' => array( array( 'label' => 'A', 'data' => '10; 20', 'color' => 'red;}' ), array( 'label' => 'B', 'data' => 'nada' ) ) );
	$html = $render( $chart );
	check_cm( str_contains( $html, '<figcaption class="digi-chart__caption" id="digi-chart-ch1">Ventas x</figcaption>' ) && 2 === substr_count( $html, '<rect ' ) && str_contains( $html, 'fill="#3f51ef"' ) && str_contains( $html, '<td>20 €</td>' ) && str_contains( $html, '<th scope="row">A</th>' ), 'El gráfico dibuja barras con colores válidos y una tabla de datos.' );
	$chart->settings['chart_type'] = 'pie';
	check_cm( 2 === substr_count( $render( $chart ), '<path ' ), 'Los sectores usan la primera serie.' );
	$chart->settings['chart_type'] = 'line';
	check_cm( str_contains( $render( $chart ), '<polyline points=' ), 'Las líneas unen los valores.' );

	check_cm( 1 === count( \Digitalisimo\Elements\Map_Widget::points( array( array( 'lat' => '24,8', 'lng' => '89.3' ), array( 'lat' => '95', 'lng' => '0' ), array( 'lat' => '' ) ) ) ), 'Sólo se aceptan coordenadas válidas.' );
	$map = new \Digitalisimo\Elements\Map_Widget();
	$map->settings = array( 'zoom' => array( 'size' => 10 ), 'show_list' => 'yes', 'markers' => array( array( 'title' => 'Oficina', 'lat' => '19.43', 'lng' => '-99.13', 'content' => 'Centro' ) ) );
	$m = $render( $map );
	check_cm( str_contains( $m, 'src="https://www.openstreetmap.org/export/embed.html?bbox=' ) && str_contains( $m, 'marker=19.430000%2C-99.130000' ) && str_contains( $m, 'title="Mapa: Oficina"' ) && str_contains( $m, 'referrerpolicy="no-referrer"' ) && str_contains( $m, '<strong>Oficina</strong><span>Centro</span>' ), 'El mapa usa el visor de OpenStreetMap con su marcador y lista.' );
	echo "DIGITALÍSIMO Elements: gráfico y mapa validados.\n";
}
