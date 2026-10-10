<?php
/** Element Pack Pro 9.9.1 `bdt-video-gallery` → `media-carousel`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'media-carousel',
	'classes'   => array(),
	'defaults'  => array(
		'thumb_layout' => 'vertical',
		'show_video_title' => 'yes',
		'show_video_title_tags' => 'h2',
		'show_video_desc' => 'yes',
		'show_thumbnail_thumb' => 'yes',
		'show_thumbnail_title' => 'yes',
		'thumbnail_title_tags' => 'h4',
		'show_thumbnail_desc' => 'yes',
		'youtube_autoplay_muted' => 'yes',
		'poster_bg_position' => '',
		'poster_bg_size' => '',
		'thumb_width' => array(
			'unit' => '%',
		),
		'thumb_width_tablet' => array(
			'unit' => '%',
		),
		'thumb_width_mobile' => array(
			'unit' => '%',
		),
	),
	'repeaters' => array(
		'video_gallery' => array(
			'defaults'     => array(
				'source_type' => 'remote_url',
				'remote_url' => array(
					'url' => 'https://www.youtube.com/watch?v=vN9DnFiRMX0&feature=youtu.be',
				),
				'title' => 'Video Title',
				'desc' => 'Women typing keyboard',
			),
			'default_rows' => array( array(
					'title' => 'Youtube Video',
					'desc' => 'Women typing keyboard',
					'remote_url' => array(
						'url' => 'https://www.youtube.com/watch?v=vN9DnFiRMX0&feature=youtu.be',
					),
				), array(
					'title' => 'Vimeo Video',
					'desc' => 'Auto VR Concept',
					'remote_url' => array(
						'url' => 'https://vimeo.com/258349022',
					),
				), array(
					'title' => 'Wista Video',
					'desc' => 'Brendan - Make It Clap',
					'remote_url' => array(
						'url' => 'https://home.wistia.com/medias/e4a27b971d',
					),
				), array(
					'title' => 'Dailymotion Video',
					'desc' => 'Drama B - DREAMS',
					'remote_url' => array(
						'url' => 'http://www.dailymotion.com/embed/video/x2ioxee',
					),
				), array(
					'title' => 'MP4 Format Video',
					'desc' => 'BdThemes Intro',
					'remote_url' => array(
						'url' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4',
					),
				), array(
					'title' => 'WEBM Format Video',
					'desc' => 'Fish Frenzy - Oceans Clip',
					'remote_url' => array(
						'url' => 'https://s3.amazonaws.com/fooplugins/rvs/oceans-clip.webm',
					),
				), array(
					'title' => 'OGV Format Video',
					'desc' => 'Fish Frenzy - Oceans Clip',
					'remote_url' => array(
						'url' => 'https://s3.amazonaws.com/fooplugins/rvs/oceans-clip.ogv',
					),
				) ),
		),
	),
	// Cada vídeo remoto (YouTube, Vimeo) pasa a una diapositiva de vídeo con su póster.
	'filter'    => static function ( array $out ) {
		$slides = array();
		foreach ( is_array( $out['video_gallery'] ?? null ) ? $out['video_gallery'] : array() as $row ) {
			$url = 'remote_url' === ( $row['source_type'] ?? 'remote_url' ) ? (string) ( $row['remote_url']['url'] ?? '' ) : (string) ( $row['hosted_url']['url'] ?? '' );
			$slides[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'type' => 'video', 'image' => is_array( $row['poster'] ?? null ) ? $row['poster'] : array( 'url' => '' ), 'video' => array( 'url' => $url ) );
		}
		$out['slides'] = $slides;
		$out['skin']   = 'carousel';
		unset( $out['video_gallery'] );
		return $out;
	},
);
