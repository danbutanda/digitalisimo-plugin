<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Gráfico de barras, líneas o sectores dibujado en el servidor como SVG, con leyenda y la tabla de
 * datos para lectores de pantalla. No carga librerías de gráficos.
 */
class Chart_Widget extends \Elementor\Widget_Base {
	const PALETTE = array( '#3f51ef', '#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40' );

	public function get_name() { return 'digitalisimo-chart'; }
	public function get_title() { return 'Gráfico'; }
	public function get_icon() { return 'eicon-dual-button'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'gráfico', 'chart', 'barras', 'líneas', 'datos' ); }
	public function get_style_depends() { return array( 'digitalisimo-chart' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_data', array( 'label' => 'Datos' ) );
		$this->add_control( 'chart_type', array( 'label' => 'Tipo', 'type' => $c::SELECT, 'default' => 'bar', 'options' => array( 'bar' => 'Barras', 'line' => 'Líneas', 'pie' => 'Sectores (primera serie)' ) ) );
		$this->add_control( 'caption', array( 'label' => 'Título del gráfico', 'type' => $c::TEXT, 'default' => '' ) );
		$this->add_control( 'labels', array( 'label' => 'Etiquetas', 'type' => $c::TEXT, 'default' => 'Ene; Feb; Mar; Abr', 'description' => 'Separadas por punto y coma.' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'label', array( 'label' => 'Serie', 'type' => $c::TEXT, 'default' => 'Serie' ) );
		$repeater->add_control( 'data', array( 'label' => 'Valores', 'type' => $c::TEXT, 'default' => '10; 20; 15; 30', 'description' => 'Separados por punto y coma.' ) );
		$repeater->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR ) );
		$this->add_control( 'datasets', array( 'label' => 'Series', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'label' => 'Ventas', 'data' => '10; 20; 15; 30' ) ), 'title_field' => '{{{ label }}}' ) );
		$this->add_control( 'show_legend', array( 'label' => 'Leyenda', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'prefix', array( 'label' => 'Prefijo de valores', 'type' => $c::TEXT, 'default' => '' ) );
		$this->add_control( 'suffix', array( 'label' => 'Sufijo de valores', 'type' => $c::TEXT, 'default' => '' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Gráfico', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'grid_color', array( 'label' => 'Color de la cuadrícula', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-chart' => '--digi-chart-grid: {{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-chart' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Lista de números desde «1; 2; 3» (acepta coma decimal). */
	public static function numbers( $text ) {
		$out = array();
		foreach ( preg_split( '/\s*;\s*/', trim( (string) $text ) ) as $part ) {
			$clean = preg_replace( '/[^0-9,.\-]/', '', $part );
			if ( preg_match( '/\d/', (string) $clean ) ) {
				$out[] = (float) str_replace( ',', '.', $clean );
			}
		}
		return $out;
	}

	private static function color( $value, $index ) {
		$value = trim( (string) $value );
		return preg_match( '/^(#[0-9a-fA-F]{3,8}|(rgb|hsl)a?\([0-9.,\s%]+\))$/', $value ) ? $value : self::PALETTE[ $index % count( self::PALETTE ) ];
	}

	private static function fmt( $value, $prefix, $suffix ) {
		return $prefix . rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' ) . $suffix;
	}

	/** SVG del gráfico en un lienzo de 600×320. */
	public static function svg( $type, array $labels, array $series ) {
		$w = 600; $h = 320; $pad = 36;
		if ( 'pie' === $type ) {
			$values = array_map( 'abs', $series[0]['values'] ?? array() );
			$total  = array_sum( $values );
			if ( $total <= 0 ) { return ''; }
			$out = ''; $angle = -M_PI / 2; $cx = 300; $cy = 160; $r = 140;
			foreach ( $values as $i => $value ) {
				$sweep = $value / $total * 2 * M_PI;
				$x1 = $cx + $r * cos( $angle ); $y1 = $cy + $r * sin( $angle );
				$angle += $sweep;
				$x2 = $cx + $r * cos( $angle ); $y2 = $cy + $r * sin( $angle );
				$large = $sweep > M_PI ? 1 : 0;
				$path  = count( $values ) === 1 || $sweep >= 2 * M_PI - 0.0001 ? 'M' . ( $cx - $r ) . ',' . $cy . 'a' . $r . ',' . $r . ' 0 1,0 ' . ( 2 * $r ) . ',0a' . $r . ',' . $r . ' 0 1,0 -' . ( 2 * $r ) . ',0' : sprintf( 'M%s,%s L%.2f,%.2f A%s,%s 0 %d,1 %.2f,%.2f Z', $cx, $cy, $x1, $y1, $r, $r, $large, $x2, $y2 );
				$out  .= '<path d="' . $path . '" fill="' . esc_attr( self::color( '', $i ) ) . '"/>';
			}
			return $out;
		}
		$max = 0;
		foreach ( $series as $set ) { $max = max( $max, $set['values'] ? max( $set['values'] ) : 0 ); }
		$max   = $max > 0 ? $max : 1;
		$count = max( 1, count( $labels ) );
		$plotw = $w - 2 * $pad; $ploth = $h - 2 * $pad;
		$out   = '';
		for ( $g = 0; $g <= 4; $g++ ) {
			$y    = $pad + $ploth * $g / 4;
			$out .= '<line class="digi-chart__grid" x1="' . $pad . '" y1="' . $y . '" x2="' . ( $w - $pad ) . '" y2="' . $y . '"/>';
		}
		foreach ( $labels as $i => $label ) {
			$out .= '<text class="digi-chart__axis" x="' . round( $pad + $plotw * ( $i + 0.5 ) / $count, 2 ) . '" y="' . ( $h - 10 ) . '" text-anchor="middle">' . esc_html( $label ) . '</text>';
		}
		$n = max( 1, count( $series ) );
		foreach ( $series as $s => $set ) {
			if ( 'line' === $type ) {
				$points = array();
				foreach ( $set['values'] as $i => $value ) {
					$points[] = round( $pad + $plotw * ( $i + 0.5 ) / $count, 2 ) . ',' . round( $pad + $ploth * ( 1 - max( 0, $value ) / $max ), 2 );
				}
				$out .= '<polyline points="' . implode( ' ', $points ) . '" fill="none" stroke="' . esc_attr( $set['color'] ) . '" stroke-width="3"/>';
				continue;
			}
			$group = $plotw / $count * 0.8;
			$bw    = $group / $n;
			foreach ( $set['values'] as $i => $value ) {
				$bh   = $ploth * max( 0, $value ) / $max;
				$x    = $pad + $plotw * $i / $count + $plotw / $count * 0.1 + $bw * $s;
				$out .= '<rect x="' . round( $x, 2 ) . '" y="' . round( $pad + $ploth - $bh, 2 ) . '" width="' . round( $bw * 0.9, 2 ) . '" height="' . round( $bh, 2 ) . '" fill="' . esc_attr( $set['color'] ) . '"/>';
			}
		}
		return $out;
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$type   = in_array( $s['chart_type'] ?? 'bar', array( 'bar', 'line', 'pie' ), true ) ? $s['chart_type'] : 'bar';
		$labels = array_values( array_filter( array_map( 'trim', explode( ';', wp_strip_all_tags( (string) ( $s['labels'] ?? '' ) ) ) ), 'strlen' ) );
		$series = array();
		foreach ( is_array( $s['datasets'] ?? null ) ? $s['datasets'] : array() as $i => $set ) {
			$values = self::numbers( $set['data'] ?? '' );
			if ( $values ) {
				$series[] = array( 'label' => trim( wp_strip_all_tags( (string) ( $set['label'] ?? '' ) ) ), 'values' => $values, 'color' => self::color( $set['color'] ?? '', $i ) );
			}
		}
		if ( ! $series ) { return; }
		$svg = self::svg( $type, $labels, $series );
		if ( '' === $svg ) { return; }
		$caption = trim( wp_strip_all_tags( (string) ( $s['caption'] ?? '' ) ) );
		$prefix  = (string) ( $s['prefix'] ?? '' );
		$suffix  = (string) ( $s['suffix'] ?? '' );
		$id      = 'digi-chart-' . $this->get_id();
		$legend  = '';
		if ( 'yes' === ( $s['show_legend'] ?? 'yes' ) ) {
			$entries = 'pie' === $type ? array_map( null, $labels, array_keys( $labels ) ) : array_map( null, array_column( $series, 'label' ), array_column( $series, 'color' ) );
			foreach ( $entries as $i => $entry ) {
				$legend .= '<li><span class="digi-chart__swatch" style="background:' . esc_attr( 'pie' === $type ? self::color( '', $i ) : $entry[1] ) . '"></span>' . esc_html( (string) $entry[0] ) . '</li>';
			}
			$legend = '<ul class="digi-chart__legend" aria-hidden="true">' . $legend . '</ul>';
		}
		$table = '<table class="digi-chart__table"><caption>' . esc_html( '' !== $caption ? $caption : 'Datos del gráfico' ) . '</caption><thead><tr><th scope="col"></th>';
		foreach ( $labels as $label ) { $table .= '<th scope="col">' . esc_html( $label ) . '</th>'; }
		$table .= '</tr></thead><tbody>';
		foreach ( 'pie' === $type ? array_slice( $series, 0, 1 ) : $series as $set ) {
			$table .= '<tr><th scope="row">' . esc_html( $set['label'] ) . '</th>';
			foreach ( $set['values'] as $value ) { $table .= '<td>' . esc_html( self::fmt( $value, $prefix, $suffix ) ) . '</td>'; }
			$table .= '</tr>';
		}
		$table .= '</tbody></table>';
		echo '<figure class="digi-chart digi-chart--' . esc_attr( $type ) . '">'
			. ( '' !== $caption ? '<figcaption class="digi-chart__caption" id="' . esc_attr( $id ) . '">' . esc_html( $caption ) . '</figcaption>' : '' )
			. '<svg class="digi-chart__svg" viewBox="0 0 600 320" aria-hidden="true" focusable="false">' . $svg . '</svg>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG construido con valores numéricos y textos escapados.
			. $legend . $table . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- leyenda y tabla escapadas al construirse.
	}
}
