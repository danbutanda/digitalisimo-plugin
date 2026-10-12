<?php
/** Element Pack Pro 9.9.1 `bdt-slideshow` → `digitalisimo-fancy-slider`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-slider',
	'classes'   => array(),
	'defaults'  => array(
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'content_position' => 'center',
		'content_align' => 'center',
		'show_pre_title' => 'yes',
		'show_title' => 'yes',
		'title_tag' => 'h1',
		'show_text' => 'yes',
		'show_button' => 'yes',
		'slideshow_scroll_to_section_icon' => array(
			'value' => 'fas fa-angle-double-down',
			'library' => 'fa-solid',
		),
		'button_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'navigation' => 'arrows',
		'nav_arrows_icon' => '0',
		'both_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'thumbnavs_position' => 'bottom-center',
		'thumbnavs_width' => array(
			'size' => 110,
		),
		'thumbnavs_height' => array(
			'size' => 80,
		),
		'hide_arrow_on_mobile' => 'yes',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'slider_animations' => 'slide',
		'parallax_pre_title_x_start' => array(
			'size' => 200,
		),
		'parallax_pre_title_x_end' => array(
			'size' => -200,
		),
		'parallax_pre_title_y_start' => array(
			'size' => 0,
		),
		'parallax_pre_title_y_end' => array(
			'size' => 0,
		),
		'parallax_title_x_start' => array(
			'size' => 300,
		),
		'parallax_title_x_end' => array(
			'size' => -300,
		),
		'parallax_title_y_start' => array(
			'size' => 0,
		),
		'parallax_title_y_end' => array(
			'size' => 0,
		),
		'parallax_post_title_x_start' => array(
			'size' => 350,
		),
		'parallax_post_title_x_end' => array(
			'size' => -350,
		),
		'parallax_post_title_y_start' => array(
			'size' => 0,
		),
		'parallax_post_title_y_end' => array(
			'size' => 0,
		),
		'parallax_text_x_start' => array(
			'size' => 500,
		),
		'parallax_text_x_end' => array(
			'size' => -500,
		),
		'parallax_text_y_start' => array(
			'size' => 0,
		),
		'parallax_text_y_end' => array(
			'size' => 0,
		),
		'parallax_button_x_start' => array(
			'size' => -150,
		),
		'parallax_button_x_end' => array(
			'size' => 150,
		),
		'parallax_button_y_start' => array(
			'size' => 0,
		),
		'parallax_button_y_end' => array(
			'size' => 0,
		),
		'overlay' => 'none',
		'blend_type' => 'multiply',
		'arrows_size' => array(
			'size' => 28,
		),
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => 20,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'thumbnavs_x_position' => array(
			'size' => 15,
		),
		'thumbnavs_y_position' => array(
			'size' => 15,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => 20,
		),
		'both_cy_position' => array(
			'size' => -40,
		),
	),
	'repeaters' => array(
		'slides' => array(
			'defaults'     => array(
				'pre_title' => 'Slide Pre Title',
				'title' => 'Slide Title',
				'background' => 'color',
				'color' => '#14ABF4',
				'image' => array(
					'url' => $placeholder_url,
				),
				'video_link' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4',
				'youtube_link' => 'https://youtu.be/YE7VzlLtp-4',
				'text' => 'I am slideshow description text, you can edit this text from slider items of slideshow content.',
				'marker_invisible_height' => array(
					'size' => 20,
				),
			),
			'default_rows' => array( array(
					'title' => 'Slide Item 1',
					'button_link' => array(
						'url' => '#',
					),
				), array(
					'title' => 'Slide Item 2',
					'button_link' => array(
						'url' => '#',
					),
				), array(
					'title' => 'Slide Item 3',
					'button_link' => array(
						'url' => '#',
					),
				), array(
					'title' => 'Slide Item 4',
					'button_link' => array(
						'url' => '#',
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$slides = array();
		foreach ( is_array( $out['slides'] ?? null ) ? $out['slides'] : array() as $row ) {
			$slides[] = array(
				'_id'          => (string) ( $row['_id'] ?? '' ),
				'sub_title'    => trim( wp_strip_all_tags( (string) ( $row['pre_title'] ?? '' ) ) ),
				'title'        => trim( trim( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ) ) . ' ' . trim( wp_strip_all_tags( (string) ( $row['post_title'] ?? '' ) ) ) ),
				'description'  => (string) ( $row['text'] ?? '' ),
				'slide_image'  => is_array( $row['image'] ?? null ) ? $row['image'] : array( 'url' => '' ),
				'slide_button' => ! empty( $row['button_link']['url'] ) ? 'Read More' : '',
				'button_link'  => is_array( $row['button_link'] ?? null ) ? $row['button_link'] : array( 'url' => '' ),
			);
		}
		$out['slides'] = $slides;
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(never-matches)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
