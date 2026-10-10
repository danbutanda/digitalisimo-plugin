<?php
/** Element Pack Pro 9.9.1 `fooevents-calendar` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'fooevents_calendar_list' => '',
		'fooevents_calendar_startday' => '0',
		'fooevents_calendar_weekends' => 'yes',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'fooevents_calendar_startday' => array(
			'fooevents_calendar_list!' => array( 'listYear', 'listMonth', 'listWeek', 'listDay' ),
		),
		'fooevents_calendar_default_date' => array(
			'fooevents_calendar_list!' => array( 'listYear', 'listMonth', 'listWeek', 'listDay' ),
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$parts = array();
		$map   = array( 'fooevents_calendar_list' => 'defaultView', 'fooevents_calendar_include_cat' => 'include_cat', 'fooevents_calendar_num' => 'num', 'fooevents_calendar_post' => 'post', 'fooevents_calendar_id' => 'id', 'fooevents_calendar_time_format' => 'timeFormat' );
		if ( '' !== (string) ( $s['fooevents_calendar_list'] ?? '' ) ) {
			$parts[] = 'defaultView="' . esc_attr( $s['fooevents_calendar_list'] ) . '"';
		}
		// Element Pack compara con «0» sin convertir: un ajuste anulado (null) también imprime firstDay.
		if ( '0' !== ( array_key_exists( 'fooevents_calendar_startday', $s ) ? $s['fooevents_calendar_startday'] : '0' ) ) {
			$parts[] = 'firstDay="' . esc_attr( (string) $s['fooevents_calendar_startday'] ) . '"';
		}
		if ( '' !== (string) ( $s['fooevents_calendar_default_date'] ?? '' ) ) {
			$parts[] = 'defaultDate="' . esc_attr( gmdate( 'Y-m-d', strtotime( (string) $s['fooevents_calendar_default_date'] ) ) ) . '"';
		}
		foreach ( array_slice( $map, 1 ) as $key => $attribute ) {
			if ( '' !== (string) ( $s[ $key ] ?? '' ) ) {
				$parts[] = $attribute . '="' . esc_attr( $s[ $key ] ) . '"';
			}
		}
		if ( 'yes' !== ( $s['fooevents_calendar_weekends'] ?? '' ) ) {
			$parts[] = 'weekends="false"';
		}
		$shortcode = '[fooevents_calendar' . ( $parts ? ' ' . implode( ' ', $parts ) : '' ) . ']';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'fooevents_calendar_list', 'fooevents_calendar_startday', 'fooevents_calendar_default_date', 'fooevents_calendar_include_cat', 'fooevents_calendar_num', 'fooevents_calendar_post', 'fooevents_calendar_id', 'fooevents_calendar_time_format', 'fooevents_calendar_weekends' ) );
	},
);
