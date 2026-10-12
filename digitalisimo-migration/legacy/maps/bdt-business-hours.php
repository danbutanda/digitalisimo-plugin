<?php
/** Element Pack Pro 9.9.1 `bdt-business-hours` → `digitalisimo-business-hours`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-business-hours',
	'classes'   => array(
		'.bdt-ep-business-hours .bdt-live-status'           => '.digi-hours__status',
		'.bdt-ep-business-hours .heading-date.bdt-width-1-2' => '.digi-hours__row dt',
		'.bdt-ep-business-hours-current-time'               => '.digi-hours__clock',
		'.bdt-ep-business-hours-current-date'               => '.digi-hours__date',
		'.bdt-ep-business-hours-header'                     => '.digi-hours__now',
		'.bdt-ep-business-hours-inner'                      => '.digi-hours__list',
		'.bdt-ep-business-hours'                            => '.digi-hours',
		'.bdt-business-hours'                               => '.digi-hours',
		'.bdt-live-status'                                  => '.digi-hours__status',
		'.bdt-business-day'                                 => '.digi-hours__row dt',
		'.bdt-business-time'                                => '.digi-hours__row dd',
		'.border-divider'                                   => '.digi-hours__row',
		'.heading-date'                                     => '.digi-hours__row dt',
		'.heading-time'                                     => '.digi-hours__row dd',
	),
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
	// El modo dinámico pasa al estado en vivo propio: horas estructuradas por fila, zona horaria y mensajes.
	'filter'    => static function ( array $out ) {
		$rows    = array();
		$days    = array( 'sunday' => '0', 'monday' => '1', 'tuesday' => '2', 'wednesday' => '3', 'thursday' => '4', 'friday' => '5', 'saturday' => '6' );
		$dynamic = 'dynamic' === ( $out['business_hour_style'] ?? 'default' );
		$clock   = static function ( $value ) {
			$time = strtotime( '1970-01-01 ' . trim( (string) $value ) . ' UTC' );
			return false === $time ? '' : gmdate( 'H:i', $time );
		};
		if ( $dynamic ) {
			$sep = ' ' . trim( (string) ( $out['dynamic_time_separator'] ?? '-' ) ) . ' ';
			foreach ( is_array( $out['dynamic_days_times'] ?? null ) ? $out['dynamic_days_times'] : array() as $row ) {
				$closed = 'yes' === ( $row['dynamic_close_this'] ?? '' );
				$day    = (string) ( $row['dynamic_enter_day'] ?? '' );
				$label  = trim( (string) ( $row['dynamic_enter_day_level'] ?? '' ) );
				$rows[] = array(
					'_id'       => (string) ( $row['_id'] ?? '' ),
					'day'       => '' !== $label ? $label : ucwords( $day ),
					'weekday'   => $days[ strtolower( trim( $day ) ) ] ?? '',
					'hours'     => $closed ? (string) ( $row['dynamic_close_text'] ?? '' ) : trim( (string) ( $row['dynamic_start_time'] ?? '' ) . $sep . (string) ( $row['dynamic_end_time'] ?? '' ) ),
					'opens'     => $closed ? '' : $clock( $row['dynamic_start_time'] ?? '' ),
					'closes'    => $closed ? '' : $clock( $row['dynamic_end_time'] ?? '' ),
					'highlight' => 'yes' === ( $row['dynamic_highlight_this'] ?? '' ) ? 'yes' : '',
				);
			}
			$zone = (string) ( $out['dynamic_timezone'] ?? 'default' );
			$zone = 'custom' === $zone ? (string) ( $out['custom_timezone_input'] ?? '' ) : ( 'default' === $zone ? '' : $zone );
			$out['timezone']    = is_numeric( $zone ) ? (string) ( (float) $zone + 0 ) : ''; // «-0» y «+6» pasan a «0» y «6», las claves del selector.
			$out['live_status'] = 'yes';
			$out['open_text']   = (string) ( $out['dynamic_open_day'] ?? '' );
			$out['closed_text'] = (string) ( $out['dynamic_close_day'] ?? '' );
		} else {
			foreach ( is_array( $out['business_days_times'] ?? null ) ? $out['business_days_times'] : array() as $row ) {
				$rows[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'day' => (string) ( $row['enter_day'] ?? '' ), 'hours' => (string) ( $row['enter_time'] ?? '' ), 'highlight' => 'yes' === ( $row['highlight_this'] ?? '' ) ? 'yes' : '' );
			}
		}
		$header                 = 'yes' === ( $out['show_header'] ?? '' );
		$out['show_clock']      = $header && 'yes' === ( $out['show_current_time'] ?? '' ) ? 'yes' : '';
		$out['show_date']       = $header && 'yes' === ( $out['show_current_date'] ?? '' ) ? 'yes' : '';
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
