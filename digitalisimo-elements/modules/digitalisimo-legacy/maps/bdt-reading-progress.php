<?php
/** Element Pack Pro 9.9.1 `bdt-reading-progress` → `digitalisimo-reading-progress`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-reading-progress',
	'classes'   => array(),
	'defaults'  => array(
		'progress_position' => 'bottom-right',
		'reading_progress_font_area_size' => array(
			'size' => 80,
		),
		'reading_progress_size' => array(
			'size' => 90,
		),
		'horizontal_reading_progress_position' => 'top',
		'reading_progress_bg' => '#08AEEC',
		'reading_progress_font_area_bg' => '#54595F',
		'reading_progress_bg_scroll' => '#FF0000',
	),
	// El indicador circular de Element Pack pasa a la barra fija; arriba o abajo según su posición.
	'filter'    => static function ( array $out ) {
		$out['position'] = 0 === strpos( (string) ( $out['progress_position'] ?? '' ), 'top' ) || 'top' === ( $out['horizontal_reading_progress_position'] ?? '' ) ? 'top' : 'bottom';
		unset( $out['progress_position'], $out['horizontal_reading_progress_position'] );
		return $out;
	},
);
