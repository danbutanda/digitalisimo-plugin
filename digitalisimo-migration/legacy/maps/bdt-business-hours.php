<?php
/** Element Pack Pro 9.9.1 `bdt-business-hours` → `digitalisimo-business-hours`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-business-hours',
	'classes'   => array( '.bdt-business-hours' => '.digi-hours' ),
	'defaults'  => array(
		'business_hour_style' => 'default',
		'dynamic_timezone' => 'default',
		'custom_timezone_input' => '+6',
		'show_current_time' => 'yes',
		'show_current_date' => 'yes',
		'dynamic_open_day' => 'Office Open. Right now we are available for service.',
		'dynamic_close_day' => 'Office Closed. Right now we are not available.',
		'dynamic_time_separator' => '-',
		'section_bs_list_padding' => array(
			'top' => 5,
			'right' => 5,
			'bottom' => 5,
			'left' => 5,
		),
		'day_divider' => 'no',
		'day_divider_style' => 'solid',
		'day_divider_color' => '#e8e8e8',
		'day_divider_weight' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'business_hours_striped' => 'no',
		'business_hours_striped_odd_color' => '#eaeaea',
		'striped_effect_even' => '#FFFFFF',
	),
	'repeaters' => array(
		'business_days_times' => array(
			'defaults'     => array(
				'enter_day' => 'Monday',
				'enter_time' => '10:00 AM - 6:00 PM',
				'highlight_this' => 'no',
				'single_business_day_color' => '#db6159',
				'single_business_timing_color' => '#db6159',
			),
			'default_rows' => array( array(
					'enter_day' => 'Monday',
					'enter_time' => '10:00 AM - 6:00 PM',
				), array(
					'enter_day' => 'Tuesday',
					'enter_time' => '10:00 AM - 6:00 PM',
				), array(
					'enter_day' => 'Wednesday',
					'enter_time' => '10:00 AM - 6:00 PM',
				), array(
					'enter_day' => 'Thursday',
					'enter_time' => '10:00 AM - 6:00 PM',
				), array(
					'enter_day' => 'Friday',
					'enter_time' => '10:00 AM - 6:00 PM',
				), array(
					'enter_day' => 'Saturday',
					'enter_time' => '10:00 AM - 6:00 PM',
				), array(
					'enter_day' => 'Sunday',
					'enter_time' => 'Closed',
					'highlight_this' => 'yes',
				) ),
		),
		'dynamic_days_times' => array(
			'defaults'     => array(
				'dynamic_enter_day' => 'Monday',
				'dynamic_start_time' => '09:00 AM',
				'dynamic_end_time' => '05:00 PM',
				'dynamic_close_this' => 'no',
				'dynamic_close_text' => 'Closed',
				'dynamic_highlight_this' => 'no',
				'dynamic_single_business_day_color' => '#db6159',
				'dynamic_single_business_timing_color' => '#db6159',
			),
			'default_rows' => array( array(
					'dynamic_enter_day' => 'Monday',
					'dynamic_start_time' => '09:00 AM',
					'dynamic_end_time' => '05:00 PM',
				), array(
					'dynamic_enter_day' => 'Tuesday',
					'dynamic_start_time' => '09:00 AM',
					'dynamic_end_time' => '05:00 PM',
				), array(
					'dynamic_enter_day' => 'Wednesday',
					'dynamic_start_time' => '09:00 AM',
					'dynamic_end_time' => '05:00 PM',
				), array(
					'dynamic_enter_day' => 'Thursday',
					'dynamic_start_time' => '09:00 AM',
					'dynamic_end_time' => '05:00 PM',
				), array(
					'dynamic_enter_day' => 'Friday',
					'dynamic_start_time' => '09:00 AM',
					'dynamic_end_time' => '05:00 PM',
				), array(
					'dynamic_enter_day' => 'Saturday',
					'dynamic_start_time' => '09:00 AM',
					'dynamic_end_time' => '05:00 PM',
				), array(
					'dynamic_enter_day' => 'Sunday',
					'dynamic_start_time' => '09:00 AM',
					'dynamic_end_time' => '05:00 PM',
					'dynamic_close_this' => 'yes',
					'dynamic_close_text' => 'Closed',
					'dynamic_highlight_this' => 'yes',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$rows = array();
		if ( 'dynamic' === ( $out['business_hour_style'] ?? 'default' ) ) {
			$sep = ' ' . trim( (string) ( $out['dynamic_time_separator'] ?? '-' ) ) . ' ';
			foreach ( is_array( $out['dynamic_days_times'] ?? null ) ? $out['dynamic_days_times'] : array() as $row ) {
				$closed = 'yes' === ( $row['dynamic_close_this'] ?? '' );
				$rows[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'day' => (string) ( $row['dynamic_enter_day'] ?? '' ), 'hours' => $closed ? (string) ( $row['dynamic_close_text'] ?? '' ) : trim( (string) ( $row['dynamic_start_time'] ?? '' ) . $sep . (string) ( $row['dynamic_end_time'] ?? '' ) ), 'highlight' => 'yes' === ( $row['dynamic_highlight_this'] ?? '' ) ? 'yes' : '' );
			}
		} else {
			foreach ( is_array( $out['business_days_times'] ?? null ) ? $out['business_days_times'] : array() as $row ) {
				$rows[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'day' => (string) ( $row['enter_day'] ?? '' ), 'hours' => (string) ( $row['enter_time'] ?? '' ), 'highlight' => 'yes' === ( $row['highlight_this'] ?? '' ) ? 'yes' : '' );
			}
		}
		$out['rows']            = $rows;
		$out['highlight_today'] = '';
		$out['striped']         = 'yes' === ( $out['business_hours_striped'] ?? '' ) ? 'yes' : '';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(business_hour_style|dynamic_.*|custom_timezone_input|show_header|business_days_times|show_current_time|show_current_date|day_divider|business_hours_striped)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
