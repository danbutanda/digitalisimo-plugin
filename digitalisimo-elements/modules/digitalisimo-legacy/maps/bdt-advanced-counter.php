<?php
/** Element Pack Pro 9.9.1 `bdt-advanced-counter` → `counter`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'counter',
	'classes'   => array(
		'.bdt-ep-advanced-counter-number' => '.elementor-counter-number-wrapper',
		'.bdt-ep-advanced-counter-text'   => '.elementor-counter-title',
		'.bdt-ep-advanced-counter'        => '.elementor-counter',
	),
	'defaults'  => array(
		'icon_type' => 'icon',
		'selected_icon' => array(
			'value' => 'fas fa-star',
			'library' => 'fa-solid',
		),
		'image' => array(
			'url' => $placeholder_url,
		),
		'thumbnail_size_size' => 'full',
		'count_start' => 1,
		'content_number' => 2020,
		'content_text' => 'Cool Number',
		'counter_number_size' => 'h4',
		'position' => 'top',
		'icon_vertical_alignment' => 'top',
		'top_icon_vertical_offset' => array(
			'size' => 0,
		),
		'top_icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'top_icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'language_input' => '0,1,2,3,4,5,6,7,8,9',
		'decimal_symbol' => '.',
		'decimal_places' => '0',
		'duration' => '2',
		'use_easing' => 'yes',
		'use_grouping' => 'no',
		'counter_separator' => ',',
		'indicator_horizontal_offset' => array(
			'size' => 0,
		),
		'indicator_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'indicator_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'indicator_vertical_offset' => array(
			'size' => 0,
		),
		'indicator_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'indicator_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'indicator_rotate' => array(
			'size' => 0,
		),
		'indicator_rotate_tablet' => array(
			'size' => 0,
		),
		'indicator_rotate_mobile' => array(
			'size' => 0,
		),
		'icon_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'icon_background_rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'background_hover_transition' => array(
			'size' => 0.3,
		),
		'icon_effect' => 'none',
		'icon_hover_rotate' => array(
			'unit' => 'deg',
		),
		'icon_hover_background_rotate' => array(
			'unit' => 'deg',
		),
		'counter_number_separator_type' => 'line',
		'counter_number_separator_border_style' => 'solid',
		'indicator_style' => '1',
	),
	'filter'    => static function ( array $out ) {
		$out['starting_number']         = (int) ( $out['count_start'] ?? 1 );
		$out['ending_number']           = (float) ( $out['content_number'] ?? 2020 );
		$out['prefix']                  = (string) ( $out['counter_prefix'] ?? '' );
		$out['suffix']                  = (string) ( $out['counter_suffix'] ?? '' );
		$out['duration']                = (int) round( (float) ( $out['duration'] ?? 2 ) * 1000 );
		$out['thousand_separator']      = 'yes' === ( $out['use_grouping'] ?? 'no' ) ? 'yes' : '';
		$out['thousand_separator_char'] = '.' === ( $out['counter_separator'] ?? ',' ) ? '.' : ( ' ' === ( $out['counter_separator'] ?? ',' ) ? ' ' : '' );
		$out['title']                   = (string) ( $out['content_text'] ?? '' );
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(count_start|content_number|counter_prefix|counter_suffix|use_grouping|counter_separator|content_text|show_icon|icon_type|selected_icon|image|show_separator|counter_number_size|counter_text_inline|position|icon_inline|icon_vertical_alignment|language_input|decimal_symbol|decimal_places|use_easing|indicator.*|icon_radius_advanced_show|icon_effect|counter_number_separator_type)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
