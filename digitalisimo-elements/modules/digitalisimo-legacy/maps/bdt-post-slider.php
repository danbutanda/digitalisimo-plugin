<?php
/** Element Pack Pro 9.9.1 `bdt-post-slider` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-post-slider-pagination-item' => '.elementor-post',
		'.bdt-post-slider-pagination' => '.elementor-pagination',
		'.bdt-post-slider-thumbnail' => '.elementor-post__thumbnail',
		'.bdt-post-slider-button' => '.elementor-post__read-more',
		'.bdt-post-slider-title' => '.elementor-post__title',
		'.bdt-post-slider-text' => '.elementor-post__excerpt',
		'.bdt-post-slider-date' => '.elementor-post-date',
		'.bdt-post-slider-meta' => '.elementor-post__meta-data',
		'.bdt-post-slider' => '.elementor-posts-container',
		'.bdt-author' => '.elementor-post-author',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 4,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'show_tag' => 'yes',
		'show_title' => 'yes',
		'title_tag' => 'h1',
		'thumb_title_tag' => 'h6',
		'show_text' => 'yes',
		'excerpt_length' => 35,
		'strip_shortcode' => 'yes',
		'hide_on_tablet' => 'yes',
		'hide_on_mobile' => 'yes',
		'show_meta' => 'yes',
		'show_pagination_thumb' => 'yes',
		'content_align' => 'left',
		'hazel_prev_text' => 'PREV',
		'hazel_next_text' => 'NEXT',
		'thumbnail_size' => 'full',
		'button_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'autoplay_interval' => 7000,
		'slider_animations' => 'fade',
		'overlay' => 'background',
		'overlay_color' => '#333333',
		'overlay_opacity' => array(
			'size' => 0.4,
		),
		'blend_type' => 'multiply',
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 4 ),
			'columns' => '1',
			'title' => array( 'show_title', 'yes' ),
			'title_tag' => array( 'title_tag', 'h1' ),
			'date' => array( 'show_meta', 'yes' ),
			'tags' => array( 'show_tag', 'yes' ),
			'excerpt' => array( 'show_text', 'yes' ),
			'excerpt_length' => array( 'excerpt_length', 35 ),
			'read_more' => array( 'show_button', '' ),
			'read_more_text' => 'Read More',
		), array( 'show_tag', 'show_title', 'title_tag', 'thumb_title_tag', 'show_text', 'excerpt_length', 'strip_shortcode', 'hide_on_tablet', 'hide_on_mobile', 'show_button', 'show_meta', 'human_diff_time', 'human_diff_time_short', 'show_pagination_thumb', 'slider_size_ratio', 'slider_min_height', 'slider_max_height', 'content_align', 'hazel_prev_text', 'hazel_next_text', 'bdt_link_new_tab', 'button_text', 'post_slider_icon', 'icon_align', 'autoplay', 'autoplay_interval', 'pause_on_hover', 'velocity', 'slider_animations', 'kenburns_animation', 'kenburns_reverse', 'overlay', 'blend_type', 'button_hover_animation' ) );
	},
);
