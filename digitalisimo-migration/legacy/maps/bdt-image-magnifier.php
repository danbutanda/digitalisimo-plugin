<?php
/** Element Pack Pro 9.9.1 `bdt-image-magnifier` → `image`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'image',
	'classes'   => array(),
	'defaults'  => array(
		'type' => 'inner',
		'smooth_move' => 'yes',
		'preload' => 'yes',
		'horizontal_offset' => array(
			'size' => '10',
		),
		'vertical_offset' => array(
			'size' => '0',
		),
		'position' => 'left',
		'image_opacity' => array(
			'size' => 1,
		),
	),
	// La lupa al pasar el cursor no se traslada: se conserva la imagen.
	'filter'    => static function ( array $out ) {
		foreach ( array( 'magnify_img', 'type', 'smooth_move', 'preload', 'zoom_ratio', 'horizontal_offset', 'vertical_offset', 'position' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
