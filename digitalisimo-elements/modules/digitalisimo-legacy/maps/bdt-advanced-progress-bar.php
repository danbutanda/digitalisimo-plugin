<?php
/** Element Pack Pro 9.9.1 `bdt-advanced-progress-bar` → `digitalisimo-progress-bars`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-progress-bars',
	'classes'   => array(
		'.bdt-ep-advanced-progress-bar-fill' => '.digi-progress__fill',
		'.bdt-ep-advanced-progress-bar-bar'  => '.digi-progress__track',
		'.bdt-ep-advanced-progress-bar-name' => '.digi-progress__label',
		'.bdt-ep-advanced-progress-bar-percentage' => '.digi-progress__value',
		'.bdt-ep-advanced-progress-bar-item' => '.digi-progress__item',
		'.bdt-ep-advanced-progress-bar'      => '.digi-progress',
	),
	'defaults'  => array(
		'skills_style' => 'default',
		'text_position' => 'outside-top',
		'skills_extra_style' => 'null',
		'show_perc' => 'yes',
		'show_max_value' => 'no',
		'show_progress_fill' => 'yes',
		'rainbow_first_color' => 'red, orange, yellow, blue, indigo, violet',
	),
	'repeaters' => array(
		'progress_bars' => array(
			'defaults'     => array(
				'name' => 'Design',
				'max_level' => array(
					'unit' => '%',
					'size' => 100,
				),
				'level' => array(
					'unit' => '%',
					'size' => 95,
				),
			),
			'default_rows' => array( array(
					'name' => 'Design',
					'level' => array(
						'size' => 97,
						'unit' => '%',
					),
				), array(
					'name' => 'UX',
					'level' => array(
						'size' => 88,
						'unit' => '%',
					),
				), array(
					'name' => 'Coding',
					'level' => array(
						'size' => 92,
						'unit' => '%',
					),
				), array(
					'name' => 'Speed',
					'level' => array(
						'size' => 95,
						'unit' => '%',
					),
				), array(
					'name' => 'Passion',
					'level' => array(
						'size' => 100,
						'unit' => '%',
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$items = array();
		foreach ( is_array( $out['progress_bars'] ?? null ) ? $out['progress_bars'] : array() as $row ) {
			$items[] = array(
				'_id'   => (string) ( $row['_id'] ?? '' ),
				'label' => trim( wp_strip_all_tags( (string) ( $row['name'] ?? '' ) ) ),
				'value' => (float) ( $row['level']['size'] ?? 0 ),
				'max'   => (float) ( $row['max_level']['size'] ?? 100 ),
			);
		}
		$out['items']      = $items;
		$out['layout']     = 'bar';
		$out['suffix']     = ' %';
		$out['show_value'] = 'yes' === ( $out['show_perc'] ?? '' ) ? 'yes' : '';
		$out['show_max']   = 'yes' === ( $out['show_max_value'] ?? '' ) ? 'yes' : '';
		foreach ( array( 'progress_bars', 'animation_delay', 'skills_style', 'text_position', 'skills_extra_style', 'show_perc', 'show_max_value', 'show_progress_fill' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
