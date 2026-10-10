<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Horario por día como lista de descripción; el navegador resalta el día de hoy. */
class Business_Hours_Widget extends \Elementor\Widget_Base {
	const DAYS = array( 'sunday' => 0, 'domingo' => 0, 'monday' => 1, 'lunes' => 1, 'tuesday' => 2, 'martes' => 2, 'wednesday' => 3, 'miércoles' => 3, 'miercoles' => 3, 'thursday' => 4, 'jueves' => 4, 'friday' => 5, 'viernes' => 5, 'saturday' => 6, 'sábado' => 6, 'sabado' => 6 );

	public function get_name() { return 'digitalisimo-business-hours'; }
	public function get_title() { return 'Horario'; }
	public function get_icon() { return 'eicon-clock'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'horario', 'apertura', 'negocio', 'días' ); }
	public function get_style_depends() { return array( 'digitalisimo-business-hours' ); }
	public function get_script_depends() { return array( 'digitalisimo-business-hours' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_hours', array( 'label' => 'Horario' ) );
		$this->add_control( 'heading', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => '' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'day', array( 'label' => 'Día', 'type' => $c::TEXT, 'default' => 'Lunes' ) );
		$repeater->add_control( 'hours', array( 'label' => 'Horario', 'type' => $c::TEXT, 'default' => '9:00 – 18:00' ) );
		$repeater->add_control( 'highlight', array( 'label' => 'Destacar', 'type' => $c::SWITCHER ) );
		$this->add_control( 'rows', array( 'label' => 'Días', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'day' => 'Lunes a viernes', 'hours' => '9:00 – 18:00' ), array( 'day' => 'Sábado', 'hours' => '10:00 – 14:00' ), array( 'day' => 'Domingo', 'hours' => 'Cerrado' ) ), 'title_field' => '{{{ day }}} · {{{ hours }}}' ) );
		$this->add_control( 'highlight_today', array( 'label' => 'Resaltar el día de hoy', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'striped', array( 'label' => 'Filas alternas', 'type' => $c::SWITCHER ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Filas', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'highlight_color', array( 'label' => 'Color destacado', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-hours' => '--digi-hours-accent: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'row_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .digi-hours__row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	/** Número de día (0 = domingo) si el texto nombra un solo día; -1 si no. */
	public static function weekday( $label ) {
		$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $label ) ) : strtolower( trim( (string) $label ) );
		return self::DAYS[ $key ] ?? -1;
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$rows = is_array( $s['rows'] ?? null ) ? $s['rows'] : array();
		$out  = '';
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$day   = trim( wp_strip_all_tags( (string) ( $row['day'] ?? '' ) ) );
			$hours = trim( wp_strip_all_tags( (string) ( $row['hours'] ?? '' ) ) );
			if ( '' === $day && '' === $hours ) { continue; }
			$wd    = self::weekday( $day );
			$out  .= '<div class="digi-hours__row' . ( 'yes' === ( $row['highlight'] ?? '' ) ? ' is-highlighted' : '' ) . ' elementor-repeater-item-' . esc_attr( sanitize_html_class( (string) ( $row['_id'] ?? $index ) ) ) . '"' . ( $wd >= 0 ? ' data-weekday="' . esc_attr( (string) $wd ) . '"' : '' ) . '><dt>' . esc_html( $day ) . '</dt><dd>' . esc_html( $hours ) . '</dd></div>';
		}
		if ( '' === $out ) { return; }
		$heading = trim( wp_strip_all_tags( (string) ( $s['heading'] ?? '' ) ) );
		echo '<div class="digi-hours' . ( 'yes' === ( $s['striped'] ?? '' ) ? ' digi-hours--striped' : '' ) . '"' . ( 'yes' === ( $s['highlight_today'] ?? 'yes' ) ? ' data-digi-hours-today' : '' ) . '>'
			. ( '' !== $heading ? '<p class="digi-hours__heading">' . esc_html( $heading ) . '</p>' : '' )
			. '<dl class="digi-hours__list">' . $out . '</dl></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada fila se escapa al construirse.
	}
}
