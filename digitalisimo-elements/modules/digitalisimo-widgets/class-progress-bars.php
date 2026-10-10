<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Barras de progreso o indicadores circulares con su valor accesible (`role="progressbar"`).
 * El relleno se anima con CSS al cargar y respeta el movimiento reducido; no usa JavaScript.
 */
class Progress_Bars_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-progress-bars'; }
	public function get_title() { return 'Barras de progreso'; }
	public function get_icon() { return 'eicon-skill-bar'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'progreso', 'barra', 'habilidades', 'porcentaje', 'circular' ); }
	public function get_style_depends() { return array( 'digitalisimo-progress-bars' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_items', array( 'label' => 'Barras' ) );
		$this->add_control( 'layout', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'bar', 'options' => array( 'bar' => 'Barras', 'circle' => 'Circular' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'label', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'default' => 'Habilidad', 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'value', array( 'label' => 'Valor', 'type' => $c::NUMBER, 'default' => 80, 'min' => 0 ) );
		$repeater->add_control( 'max', array( 'label' => 'Máximo', 'type' => $c::NUMBER, 'default' => 100, 'min' => 1 ) );
		$repeater->add_control( 'text', array( 'label' => 'Texto', 'type' => $c::TEXTAREA, 'default' => '' ) );
		$repeater->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => '--digi-progress-color: {{VALUE}};' ) ) );
		$this->add_control( 'items', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'label' => 'Diseño', 'value' => 90 ), array( 'label' => 'Desarrollo', 'value' => 80 ) ), 'title_field' => '{{{ label }}} · {{{ value }}}' ) );
		$this->add_control( 'show_value', array( 'label' => 'Mostrar el valor', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_max', array( 'label' => 'Mostrar el máximo', 'type' => $c::SWITCHER, 'default' => '', 'condition' => array( 'show_value' => 'yes' ) ) );
		$this->add_control( 'suffix', array( 'label' => 'Unidad', 'type' => $c::TEXT, 'default' => '%', 'condition' => array( 'show_value' => 'yes' ) ) );
		$this->add_control( 'label_tag', array( 'label' => 'Etiqueta del nombre', 'type' => $c::SELECT, 'default' => 'span', 'options' => array( 'span' => 'span', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'p' => 'p' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Barras', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'fill_color', array( 'label' => 'Color del relleno', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-progress' => '--digi-progress-color: {{VALUE}};' ) ) );
		$this->add_control( 'track_color', array( 'label' => 'Color del fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-progress' => '--digi-progress-track: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'bar_height', array( 'label' => 'Grosor', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 2, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .digi-progress' => '--digi-progress-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'circle_size', array( 'label' => 'Tamaño del círculo', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 60, 'max' => 400 ) ), 'selectors' => array( '{{WRAPPER}} .digi-progress--circle' => '--digi-progress-circle: {{SIZE}}{{UNIT}};' ), 'condition' => array( 'layout' => 'circle' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-progress' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'label_typography', 'selector' => '{{WRAPPER}} .digi-progress__label' ) );
		$this->end_controls_section();
	}

	/** Valores normalizados de un elemento: [nombre, valor, máximo, porcentaje 0–100]. */
	public static function item_values( array $item ) {
		$max   = max( 1, (float) ( $item['max'] ?? 100 ) );
		$value = max( 0, min( $max, (float) ( $item['value'] ?? 0 ) ) );
		return array( trim( wp_strip_all_tags( (string) ( $item['label'] ?? '' ) ) ), $value, $max, round( $value / $max * 100, 2 ) );
	}

	private static function number( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$items  = is_array( $s['items'] ?? null ) ? $s['items'] : array();
		$circle = 'circle' === ( $s['layout'] ?? 'bar' );
		$tag    = in_array( $s['label_tag'] ?? 'span', array( 'span', 'h3', 'h4', 'h5', 'p' ), true ) ? $s['label_tag'] : 'span';
		$out    = '';
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			list( $label, $value, $max, $percent ) = self::item_values( $item );
			$shown = 'yes' === ( $s['show_value'] ?? 'yes' ) ? self::number( $value ) . ( 'yes' === ( $s['show_max'] ?? '' ) ? ' / ' . self::number( $max ) : '' ) . (string) ( $s['suffix'] ?? '%' ) : '';
			$text  = trim( (string) ( $item['text'] ?? '' ) );
			$aria  = 'role="progressbar" aria-valuemin="0" aria-valuemax="' . esc_attr( self::number( $max ) ) . '" aria-valuenow="' . esc_attr( self::number( $value ) ) . '"' . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' );
			$id    = sanitize_html_class( (string) ( $item['_id'] ?? $index ) );
			$out  .= '<div class="digi-progress__item elementor-repeater-item-' . esc_attr( $id ) . '" style="--digi-progress-value:' . esc_attr( self::number( $percent ) ) . '">';
			if ( $circle ) {
				// Circunferencia de r=45 en un viewBox de 100: 282,74.
				$offset = round( 282.74 * ( 1 - $percent / 100 ), 2 );
				$out   .= '<div class="digi-progress__circle" ' . $aria . '><svg viewBox="0 0 100 100" aria-hidden="true" focusable="false"><circle class="digi-progress__ring" cx="50" cy="50" r="45"/><circle class="digi-progress__arc" cx="50" cy="50" r="45" style="stroke-dashoffset:' . esc_attr( (string) $offset ) . '"/></svg>' . ( '' !== $shown ? '<span class="digi-progress__value">' . esc_html( $shown ) . '</span>' : '' ) . '</div>';
				$out   .= '' !== $label ? '<' . $tag . ' class="digi-progress__label">' . esc_html( $label ) . '</' . $tag . '>' : '';
			} else {
				$out .= '<div class="digi-progress__head">' . ( '' !== $label ? '<' . $tag . ' class="digi-progress__label">' . esc_html( $label ) . '</' . $tag . '>' : '' ) . ( '' !== $shown ? '<span class="digi-progress__value">' . esc_html( $shown ) . '</span>' : '' ) . '</div>';
				$out .= '<div class="digi-progress__track" ' . $aria . '><span class="digi-progress__fill"></span></div>';
			}
			$out .= '' !== $text ? '<p class="digi-progress__text">' . esc_html( $text ) . '</p>' : '';
			$out .= '</div>';
		}
		if ( '' !== $out ) {
			echo '<div class="digi-progress digi-progress--' . ( $circle ? 'circle' : 'bar' ) . '">' . $out . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
		}
	}
}
