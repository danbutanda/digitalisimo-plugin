<?php
/** Element Pack Pro 9.9.1 `bdt-portfolio-carousel` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-gallery-lightbox-item' => '.elementor-post',
		'.bdt-gallery-item' => '.elementor-post',
		'.bdt-portfolio-carousel' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'portfolio',
		'posts_per_page' => 9,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 30,
		),
		'item_gap_tablet' => array(
			'size' => 20,
		),
		'item_gap_mobile' => array(
			'size' => 20,
		),
		'thumbnail_size_size' => 'medium',
		'show_title' => 'yes',
		'title_tag' => 'h4',
		'strip_shortcode' => 'yes',
		'show_link' => 'both',
		'link_type' => 'icon',
		'post_link_text' => 'VIEW',
		'lightbox_link_text' => 'ZOOM',
		'lightbox_animation' => 'slide',
		'grid_animation_type' => '',
		'grid_anim_delay' => array(
			'unit' => 'ms',
			'size' => 300,
		),
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'skin' => 'carousel',
		'coverflow_rotate' => array(
			'size' => 50,
		),
		'coverflow_stretch' => array(
			'size' => 0,
		),
		'coverflow_modifier' => array(
			'size' => 1,
		),
		'coverflow_depth' => array(
			'size' => 100,
		),
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
		'row_gap' => array(
			'size' => 30,
		),
		'portfolio_content_alignment' => 'center',
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => -60,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nnx_position_tablet' => array(
			'size' => 0,
		),
		'dots_nnx_position_mobile' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'dots_nny_position_tablet' => array(
			'size' => 30,
		),
		'dots_nny_position_mobile' => array(
			'size' => 30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncx_position_tablet' => array(
			'size' => 0,
		),
		'both_ncx_position_mobile' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_ncy_position_tablet' => array(
			'size' => 40,
		),
		'both_ncy_position_mobile' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => -60,
		),
		'both_cy_position' => array(
			'size' => 30,
		),
		'arrows_fraction_ncx_position' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_fraction_ncy_position' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_fraction_cx_position' => array(
			'size' => -60,
		),
		'arrows_fraction_cy_position' => array(
			'size' => 30,
		),
		'progress_y_position' => array(
			'size' => 15,
		),
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 9 ),
			'columns' => array( 'columns', '3' ),
			'title' => array( 'show_title', 'yes' ),
			'title_tag' => array( 'title_tag', 'h4' ),
			'category' => array( 'show_category', '' ),
			'excerpt' => array( 'show_excerpt', '' ),
			'excerpt_length' => array( 'excerpt_limit', 10 ),
		), array( 'columns', 'item_gap', 'match_height', 'show_title', 'title_tag', 'show_excerpt', 'excerpt_limit', 'ellipsis', 'strip_shortcode', 'show_category', 'show_link', 'external_link', 'link_type', 'post_link_text', 'lightbox_link_text', 'tilt_show', 'tilt_scale', 'lightbox_animation', 'lightbox_autoplay', 'lightbox_pause', 'grid_animation_type', 'grid_anim_delay', 'navigation', 'dynamic_bullets', 'show_scrollbar', 'both_position', 'arrows_fraction_position', 'arrows_position', 'dots_position', 'progress_position', 'nav_arrows_icon', 'hide_arrow_on_mobile', 'skin', 'coverflow_rotate', 'coverflow_stretch', 'coverflow_modifier', 'coverflow_depth', 'autoplay', 'autoplay_speed', 'pauseonhover', 'slides_to_scroll', 'centered_slides', 'grab_cursor', 'free_mode', 'loop', 'speed', 'observer', 'mousewheel', 'show_hidden_item', 'border_radius_advanced_show', 'advanced_dots_size' ) );
	},
);
