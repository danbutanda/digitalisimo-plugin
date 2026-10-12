<?php
/** Element Pack Pro 9.9.1 `bdt-hover-video` → `digitalisimo-video-player`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-video-player',
	'classes'   => array(),
	'defaults'  => array(
		'aspect_ratio' => '',
		'progress_visibility' => 'yes',
		'video_preload' => 'yes',
		'mask_content_align' => 'left',
		'mask_alignment' => 'top',
		'hover_button_align' => 'center',
	),
	'repeaters' => array(
		'hover_video_list' => array(
			'defaults'     => array(
				'source_type' => 'hosted_url',
				'remote_url' => array(
					'url' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4',
				),
				'hover_video_poster' => array(
					'url' => $placeholder_url,
				),
				'hover_video_title' => 'Title Item',
				'hover_item_icon_type' => 'icon',
				'hover_item_icon' => array(
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'hover_selected_image' => array(
					'url' => $placeholder_url,
				),
			),
			'default_rows' => array( array(
					'hover_video_title' => 'Hover Video 01',
					'hover_item_icon' => array(
						'value' => 'far fa-laugh',
						'library' => 'fa-regular',
					),
				), array(
					'hover_video_title' => 'Hover Video 02',
					'hover_item_icon' => array(
						'value' => 'far fa-laugh',
						'library' => 'fa-regular',
					),
				) ),
		),
	),
	// Se muestra el primer vídeo de la lista con su póster y título; la reproducción al pasar el cursor no se traslada.
	'filter'    => static function ( array $out ) {
		$first = is_array( $out['hover_video_list'][0] ?? null ) ? $out['hover_video_list'][0] : array();
		$hosted = 'hosted_url' === ( $first['source_type'] ?? 'hosted_url' );
		$out['source']    = $hosted ? 'media' : 'url';
		$out['video']     = $hosted && is_array( $first['hosted_url'] ?? null ) ? $first['hosted_url'] : array( 'url' => '' );
		$out['video_url'] = ! $hosted && is_array( $first['remote_url'] ?? null ) ? $first['remote_url'] : array( 'url' => '' );
		$out['poster']    = is_array( $first['hover_video_poster'] ?? null ) ? $first['hover_video_poster'] : array( 'url' => '' );
		$out['title']     = trim( wp_strip_all_tags( (string) ( $first['hover_video_title'] ?? '' ) ) );
		foreach ( array( 'hover_video_list', 'aspect_ratio', 'progress_visibility', 'icon_visibility', 'video_preload', 'video_autoplay', 'video_replay', 'poster_show_again', 'btn_progress_visibility', 'mask_alignment' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
