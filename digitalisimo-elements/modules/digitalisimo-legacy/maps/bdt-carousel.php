<?php
/** Element Pack Pro 9.9.1 `bdt-carousel` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-ep-carousel-thumbnail' => '.elementor-post__thumbnail',
		'.bdt-ep-carousel-excerpt' => '.elementor-post__excerpt',
		'.bdt-ep-carousel-button' => '.elementor-post__read-more',
		'.bdt-ep-carousel-title' => '.elementor-post__title',
		'.bdt-ep-carousel-meta' => '.elementor-post__meta-data',
		'.bdt-ep-carousel-date' => '.elementor-post-date',
		'.bdt-ep-carousel-item' => '.elementor-post',
		'.bdt-ep-carousel-desc' => '.elementor-post__text',
		'.bdt-carousel' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 6,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 35,
		),
		'item_gap_tablet' => array(
			'size' => 20,
		),
		'item_gap_mobile' => array(
			'size' => 20,
		),
		'content_show' => 'onhover',
		'thumbnail_show' => 'yes',
		'thumbnail_size_size' => 'medium',
		'show_link_option' => 'yes',
		'show_caption' => 'yes',
		'image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_width_tablet' => array(
			'unit' => '%',
		),
		'image_width_mobile' => array(
			'unit' => '%',
		),
		'alice_background_height_tablet' => array(
			'unit' => 'px',
		),
		'alice_background_height_mobile' => array(
			'unit' => 'px',
		),
		'vertical_layout_image_width' => array(
			'size' => 50,
			'unit' => '%',
		),
		'vertical_layout_image_width_tablet' => array(
			'size' => 100,
			'unit' => '%',
		),
		'vertical_layout_image_width_mobile' => array(
			'size' => 100,
			'unit' => '%',
		),
		'ramble_image_ratio' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'show_title' => 'yes',
		'title_tag' => 'h4',
		'show_alice_category' => 'yes',
		'meta_data' => array( 'date', 'comments' ),
		'show_excerpt' => 'yes',
		'excerpt_length' => 15,
		'strip_shortcode' => 'yes',
		'show_read_more' => 'yes',
		'read_more_text' => 'Read More',
		'button_size' => 'sm',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
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
		'overlay_blur_level' => array(
			'size' => 5,
		),
		'skin_overlay_color' => '#000',
		'item_background' => '#fff',
		'item_shadow_padding' => array(
			'size' => 10,
		),
		'image_opacity' => array(
			'size' => 1,
		),
		'image_hover_opacity' => array(
			'size' => 1,
		),
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
			'per_page' => array( 'posts_per_page', 6 ),
			'columns' => array( 'columns', '3' ),
			'title' => array( 'show_title', 'yes' ),
			'title_tag' => array( 'title_tag', 'h4' ),
			'date' => 'yes',
			'comments' => 'yes',
			'excerpt' => array( 'show_excerpt', 'yes' ),
			'excerpt_length' => array( 'excerpt_length', 15 ),
			'read_more' => array( 'show_read_more', 'yes' ),
			'read_more_text' => array( 'read_more_text', 'Read More' ),
		), array( 'columns', 'item_gap', 'match_height', 'content_show', 'thumbnail_show', 'show_link_option', 'show_caption', 'show_title', 'title_tag', 'show_alice_category', 'meta_data', 'show_excerpt', 'excerpt_length', 'show_ellipse', 'strip_shortcode', 'show_read_more', 'read_more_text', 'button_size', 'carousel_icon', 'icon_align', 'navigation', 'dynamic_bullets', 'show_scrollbar', 'both_position', 'arrows_fraction_position', 'arrows_position', 'dots_position', 'progress_position', 'nav_arrows_icon', 'hide_arrow_on_mobile', 'skin', 'coverflow_rotate', 'coverflow_stretch', 'coverflow_modifier', 'coverflow_depth', 'autoplay', 'autoplay_speed', 'pauseonhover', 'slides_to_scroll', 'centered_slides', 'grab_cursor', 'free_mode', 'loop', 'speed', 'observer', 'mousewheel', 'show_hidden_item', 'skin_shadow_mode', 'overlay_blur_effect', 'shadow_mode', 'button_hover_animation', 'advanced_dots_size' ) );
	},
);
