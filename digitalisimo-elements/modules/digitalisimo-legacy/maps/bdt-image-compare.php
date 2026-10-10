<?php
/** Element Pack Pro 9.9.1 `bdt-image-compare` → `digitalisimo-image-compare`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-image-compare',
	'classes'   => array( '.bdt-image-compare' => '.digi-compare' ),
	'defaults'  => array(
		'before_image' => array(
			'url' => '',
		),
		'after_image' => array(
			'url' => '',
		),
		'thumbnail_size_size' => 'full',
		'before_label' => 'Before',
		'after_label' => 'After',
		'orientation' => 'horizontal',
		'default_offset_pct' => array(
			'size' => 70,
		),
		'no_overlay' => 'yes',
		'on_hover' => 'yes',
		'smoothing' => 'yes',
		'smoothing_amount' => array(
			'size' => 400,
		),
		'bar_color' => '#fff',
	),
	'filter'    => static function ( array $out ) {
		$out['start'] = (int) ( $out['default_offset_pct']['size'] ?? 70 );
		foreach ( array( 'default_offset_pct', 'no_overlay', 'on_hover', 'move_slider_on_hover', 'add_circle', 'add_circle_blur', 'add_circle_shadow', 'smoothing', 'smoothing_amount' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
