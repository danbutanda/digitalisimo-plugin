<?php
/** Element Pack Pro 9.9.1 `bdt-testimonial-carousel` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-testimonial-carousel' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'bdthemes-testimonial',
		'posts_per_page' => 4,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'layout' => '1',
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 35,
		),
		'row_gap' => array(
			'size' => 35,
		),
		'show_image' => 'yes',
		'show_title' => 'yes',
		'show_designation' => '',
		'show_address' => 'yes',
		'meta_multi_line' => 'yes',
		'show_text' => 'yes',
		'text_limit' => 80,
		'strip_shortcode' => 'yes',
		'show_rating' => 'yes',
		'schema_rich_results' => 'yes',
		'schema_item_reviewed_type' => 'Organization',
		'filter_custom_text_all' => 'All',
		'filter_custom_text_filter' => 'Filter',
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'filter_alignment' => 'center',
		'layout_style' => 'style-1',
		'item_gap_tablet' => array(
			'size' => 20,
		),
		'item_gap_mobile' => array(
			'size' => 20,
		),
		'rating_position' => 'bottom',
		'item_match_height' => 'yes',
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
		'item_shadow_padding' => array(
			'size' => 10,
		),
		'quatation_horizontal_offset' => array(
			'size' => 0,
		),
		'quatation_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'quatation_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'quatation_vertical_offset' => array(
			'size' => 0,
		),
		'quatation_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'quatation_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'quatation_rotate' => array(
			'size' => 0,
		),
		'quatation_rotate_tablet' => array(
			'size' => 0,
		),
		'quatation_rotate_mobile' => array(
			'size' => 0,
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
		'thumb' => 'yes',
		'title' => 'yes',
		'company_name' => 'yes',
		'show_comma' => 'yes',
		'rating' => 'yes',
		'meta_position' => 'after',
		'meta_alignment' => 'center',
		'alignment' => 'left',
		'autoplay_interval' => 7000,
		'velocity' => 500,
		'image_size' => array(
			'size' => 300,
		),
		'quatation_rotate_x' => array(
			'size' => 0,
		),
		'quatation_rotate_x_tablet' => array(
			'size' => 0,
		),
		'quatation_rotate_x_mobile' => array(
			'size' => 0,
		),
		'quatation_rotate_y' => array(
			'size' => 35,
		),
		'quatation_rotate_y_tablet' => array(
			'size' => 35,
		),
		'quatation_rotate_y_mobile' => array(
			'size' => 35,
		),
		'thumb_opacity' => array(
			'size' => 0.8,
		),
		'horizontal_spacing' => array(
			'size' => 20,
		),
		'vertical_spacing' => array(
			'size' => 0,
		),
		'active_thumb_opacity' => array(
			'size' => 1,
		),
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 4 ),
			'columns' => '3',
			'title' => array( 'show_title', 'yes' ),
			'title_tag' => 'h4',
			'excerpt' => array( 'show_text', 'yes' ),
			'excerpt_length' => array( 'text_limit', 25 ),
			'image' => array( 'show_image', 'yes' ),
		), array( 'layout', 'show_pagination', 'show_image', 'show_title', 'show_designation', 'show_address', 'meta_multi_line', 'show_comma', 'show_text', 'text_limit', 'ellipsis', 'strip_shortcode', 'text_read_more_toggle', 'show_rating', 'show_rating_above_text', 'show_review_platform', 'item_match_height', 'item_masonry', 'schema_rich_results', 'schema_item_reviewed_name', 'schema_item_reviewed_type', 'show_filter_bar', 'filter_custom_text', 'filter_custom_text_all', 'filter_custom_text_filter', 'original_color', 'layout_style', 'columns', 'item_gap', 'rating_bullet', 'rating_position', 'navigation', 'dynamic_bullets', 'show_scrollbar', 'both_position', 'arrows_fraction_position', 'arrows_position', 'dots_position', 'progress_position', 'nav_arrows_icon', 'hide_arrow_on_mobile', 'skin', 'coverflow_rotate', 'coverflow_stretch', 'coverflow_modifier', 'coverflow_depth', 'autoplay', 'autoplay_speed', 'pauseonhover', 'slides_to_scroll', 'centered_slides', 'grab_cursor', 'free_mode', 'loop', 'speed', 'observer', 'mousewheel', 'show_hidden_item', 'shadow_mode', 'advanced_dots_size', 'thumb', 'title', 'company_name', 'rating', 'meta_position', 'meta_alignment', 'alignment', 'autoplay_interval', 'pause_on_hover', 'velocity', 'auto-height', 'hide_arrow_style' ) );
	},
);
