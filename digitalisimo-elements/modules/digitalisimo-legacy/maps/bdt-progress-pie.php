<?php
/** Element Pack Pro 9.9.1 `bdt-progress-pie` → `digitalisimo-progress-bars`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-progress-bars',
	'classes'   => array( '.bdt-progress-pie-title' => '.digi-progress__label', '.bdt-progress-pie' => '.digi-progress__circle' ),
	'defaults'  => array(
		'percent' => 75,
		'duration' => 1,
		'title' => 'Progress Pie Title',
		'line_width' => 8,
		'line_cap' => 'round',
	),
	'filter'    => static function ( array $out ) {
		// Texto central de Element Pack: «antes», el texto (o el porcentaje) y «después».
		$text  = trim( wp_strip_all_tags( (string) ( $out['before'] ?? '' ) . ' ' . (string) ( $out['text'] ?? '' ) . ' ' . (string) ( $out['after'] ?? '' ) ) );
		$out['items']      = array( array( '_id' => 'pie', 'label' => trim( wp_strip_all_tags( (string) ( $out['title'] ?? '' ) ) ), 'value' => (float) ( $out['percent'] ?? 75 ), 'max' => 100, 'text' => '' !== trim( (string) ( $out['text'] ?? '' ) ) ? $text : '' ) );
		$out['layout']     = 'circle';
		$out['show_value'] = '' === trim( (string) ( $out['text'] ?? '' ) ) ? 'yes' : '';
		$out['bar_height'] = array( 'unit' => 'px', 'size' => (int) ( $out['line_width'] ?? 8 ) );
		foreach ( array( 'percent', 'duration', 'title', 'hide_title_divider', 'before', 'text', 'after', 'line_width', 'line_cap' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
