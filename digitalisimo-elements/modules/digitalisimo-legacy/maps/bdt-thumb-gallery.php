<?php
/** Element Pack Pro 9.9.1 `bdt-thumb-gallery` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-thumb-gallery-button' => '.elementor-post__read-more',
		'.bdt-thumb-gallery-title' => '.elementor-post__title',
		'.bdt-thumb-gallery-text' => '.elementor-post__excerpt',
		'.bdt-thumb-gallery' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 5,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'content_position' => 'center',
		'content_align' => 'center',
		'show_title' => 'yes',
		'title_tag' => 'h3',
		'title_link_option' => 'yes',
		'show_text' => 'yes',
		'excerpt_length' => 25,
		'strip_shortcode' => 'yes',
		'show_button' => 'yes',
		'link' => array(
			'url' => '#',
		),
		'button_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'navigation' => 'thumbnavs',
		'nav_arrows_icon' => '5',
		'arrows_position' => 'center',
		'thumbnavs_position' => 'bottom-center',
		'thumbnavs_width' => array(
			'size' => 110,
		),
		'thumbnavs_height' => array(
			'size' => 80,
		),
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'slider_animations' => 'slide',
		'content_transition' => 'fade',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => 20,
		),
		'thumbnavs_x_position' => array(
			'size' => 0,
		),
		'thumbnavs_y_position' => array(
			'size' => -30,
		),
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 5 ),
			'columns' => '1',
			'title' => 'yes',
			'title_tag' => 'h3',
			'excerpt' => 'yes',
			'excerpt_length' => array( 'excerpt_length', 25 ),
			'read_more' => 'yes',
			'read_more_text' => 'Read More',
		), array( 'slider_size_ratio', 'slider_min_height', 'enable_height', 'slideshow_fullscreen', 'content_position', 'content_align', 'show_title', 'title_tag', 'title_link_option', 'show_text', 'excerpt_length', 'strip_shortcode', 'show_button', 'gallery', 'link', 'button_text', 'thumb_gallery_icon', 'icon_align', 'navigation', 'nav_arrows_icon', 'arrows_position', 'thumbnavs_position', 'thumbnavs_outside', 'autoplay', 'autoplay_interval', 'pause_on_hover', 'velocity', 'slider_animations', 'kenburns_animation', 'kenburns_reverse', 'content_transition', 'button_hover_animation', 'arrows_ncx_position', 'thumbnavs_x_position' ) );
	},
);
