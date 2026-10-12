<?php
/** Element Pack Pro 9.9.1 `bdt-video-player` → `digitalisimo-video-player`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-video-player',
	// Clases de Element Pack más usadas en sus selectores: .bdt-video
	'classes'   => array( '.bdt-video-player' => '.digi-video-player' ),
	'defaults'  => array(
		'title' => 'Big Buck Bunny',
		'source' => 'https://www.elementpack.pro/demo/wp-content/uploads/2025/11/BigBuckBunny.mp4',
		'poster' => array(
			'url' => 'https://www.elementpack.pro/demo/wp-content/uploads/2025/11/BigBuckBunny.jpg',
		),
		'seek_bar' => 'yes',
		'time_duration' => 'both',
		'volume_mute' => 'yes',
		'volume_bar' => 'yes',
		'fullscreen' => 'yes',
		'smooth_show' => 'yes',
		'keyboard_enable' => 'yes',
		'volume_level' => array(
			'size' => 0.8,
		),
		'play_button_border' => '',
		'time_color' => 'rgba(51, 51, 51, 0.6)',
		'volume_button_border' => '',
		'fullscreen_button_border' => '',
	),
	// Element Pack guardaba la dirección del video en `source`; el widget propio la usa como URL externa.
	'filter'    => static function ( array $out ) {
		$out['video_url'] = (string) ( $out['source'] ?? '' );
		$out['source']    = 'url';
		if ( 'yes' === ( $out['title_hide'] ?? '' ) ) {
			$out['title'] = '';
		}
		unset( $out['title_hide'] );
		return $out;
	},
);
