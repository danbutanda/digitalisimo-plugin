<?php
/** Element Pack Pro 9.9.1 `bdt-reading-timer` → `digitalisimo-reading-time`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-reading-time',
	'classes'   => array(),
	'defaults'  => array(
		'reading_timer_avg_words_per_minute' => array(
			'unit' => 'px',
			'size' => 200,
		),
		'reading_space_between' => array(
			'unit' => 'px',
			'size' => 5,
		),
	),
	// Element Pack medía en el navegador un elemento por ID; aquí se calcula sobre el contenido de la entrada.
	'filter'    => static function ( array $out ) {
		$out['words_per_minute'] = (int) ( $out['reading_timer_avg_words_per_minute']['size'] ?? 200 );
		$out['minute_text']      = '' !== trim( (string) ( $out['reading_timer_minute_text'] ?? '' ) ) ? (string) $out['reading_timer_minute_text'] : 'min read';
		foreach ( array( 'reading_timer_content_id', 'reading_timer_avg_words_per_minute', 'reading_timer_minute_text', 'reading_timer_seconds_text', 'show_icon' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
