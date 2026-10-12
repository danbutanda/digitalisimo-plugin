<?php
/** Element Pack Pro 9.9.1 `bdt-countdown` → `countdown`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'countdown',
	'classes'   => array(
		'.bdt-countdown-item-wrapper' => '.elementor-countdown-wrapper',
		'.bdt-countdown-item'         => '.elementor-countdown-item',
		'.bdt-countdown-number'       => '.elementor-countdown-digits',
		'.bdt-countdown-label'        => '.elementor-countdown-label',
		'.bdt-countdown-end-message'  => '.elementor-countdown-expire--message',
		'.bdt-days-wrapper'           => '.elementor-countdown-item:has(.elementor-countdown-days)',
		'.bdt-hours-wrapper'          => '.elementor-countdown-item:has(.elementor-countdown-hours)',
		'.bdt-minutes-wrapper'        => '.elementor-countdown-item:has(.elementor-countdown-minutes)',
		'.bdt-seconds-wrapper'        => '.elementor-countdown-item:has(.elementor-countdown-seconds)',
	),
	'defaults'  => array(
		'loop_hours' => '3',
		'columns' => '4',
		'columns_tablet' => '2',
		'columns_mobile' => '2',
		'gap' => array(
			'size' => 20,
		),
		'number_label_gap' => array(
			'unit' => 'px',
			'size' => 10,
		),
		'tiny_item_spacing' => array(
			'unit' => 'px',
			'size' => 10,
		),
		'tiny_number_label_gap' => array(
			'unit' => 'px',
			'size' => 10,
		),
		'alignment' => 'center',
		'tiny_alignment' => 'center',
		'container_width' => array(
			'unit' => '%',
		),
		'container_width_tablet' => array(
			'unit' => '%',
		),
		'container_width_mobile' => array(
			'unit' => '%',
		),
		'label_display' => 'block',
		'show_days' => 'yes',
		'show_hours' => 'yes',
		'show_minutes' => 'yes',
		'show_seconds' => 'yes',
		'show_labels' => 'yes',
		'label_days' => 'Days',
		'label_hours' => 'Hours',
		'label_minutes' => 'Minutes',
		'label_seconds' => 'Seconds',
		'separator' => ':',
		'end_action_type' => 'none',
		'end_message' => 'Countdown End!',
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'end_message_alignment' => 'center',
	),
	// Default no literal, revisar: due_date: gmdate( 'Y-m-d H:i', strtotime( '+1 month' ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECO
	'filter'    => static function ( array $out ) {
		// La cuenta «en bucle» de Element Pack se reinicia cada X horas: la más cercana es la evergreen.
		if ( 'yes' === ( $out['loop_time'] ?? '' ) ) {
			$out['countdown_type']          = 'evergreen';
			$out['evergreen_counter_hours'] = (int) ( $out['loop_hours'] ?? 3 );
		} else {
			$out['countdown_type'] = 'due_date';
		}
		$action                     = (string) ( $out['end_action_type'] ?? 'none' );
		$out['expire_actions']      = 'message' === $action ? array( 'message' ) : ( 'url' === $action ? array( 'redirect' ) : array() );
		$out['message_after_expire'] = (string) ( $out['end_message'] ?? '' );
		$out['expire_redirect_url']  = is_array( $out['end_redirect_link'] ?? null ) ? $out['end_redirect_link'] : array( 'url' => '' );
		foreach ( array( 'loop_time', 'loop_hours', 'end_action_type', 'end_message', 'end_redirect_link', 'link_redirect_delay', 'id_for_coupon_code', 'selector_for_trigger', 'show_separator', 'separator', 'glassmorphism_effect', 'individual_style' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
