<?php
/** Element Pack Pro 9.9.1 `bdt-post-gallery` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-gallery-lightbox-item' => '.elementor-post',
		'.bdt-post-gallery-excerpt' => '.elementor-post__excerpt',
		'.bdt-gallery-item-title' => '.elementor-post__title',
		'.bdt-gallery-thumbnail' => '.elementor-post__thumbnail',
		'.bdt-post-gallery-desc' => '.elementor-post__text',
		'.bdt-gallery-item-tag' => '.elementor-post__terms--post_tag a',
		'.bdt-post-gallery' => '.elementor-posts-container',
		'.bdt-gallery-item' => '.elementor-post',
		'.bdt-pagination' => '.elementor-pagination',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 6,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'thumbnail_size_size' => 'medium',
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'item_ratio' => array(
			'size' => 250,
		),
		'active_hash' => 'no',
		'hash_top_offset' => array(
			'unit' => 'px',
			'size' => 70,
		),
		'hash_scrollspy_time' => array(
			'unit' => 'px',
			'size' => 1000,
		),
		'filter_custom_text_all' => 'All',
		'filter_custom_text_filter' => 'Filter',
		'overlay_animation' => 'fade',
		'show_title' => 'yes',
		'show_title_link' => 'yes',
		'title_tag' => 'h4',
		'excerpt_limit' => 10,
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
		'item_gap' => array(
			'size' => 30,
		),
		'row_gap' => array(
			'size' => 30,
		),
		'overlay_blur_level' => array(
			'size' => 5,
		),
		'overlay_content_alignment' => 'center',
		'overlay_content_position' => 'middle',
		'filter_alignment' => 'center',
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 6 ),
			'columns' => array( 'columns', '3' ),
			'title' => array( 'show_title', 'yes' ),
			'title_tag' => array( 'title_tag', 'h4' ),
			'category' => array( 'show_category', '' ),
			'excerpt' => array( 'show_excerpt', '' ),
			'excerpt_length' => array( 'excerpt_limit', 10 ),
			'pagination' => array( 'show_pagination', '' ),
		), array( 'columns', 'show_pagination', 'image_mask_shape', 'masonry', 'show_filter_bar', 'active_hash', 'hash_top_offset', 'hash_scrollspy_time', 'filter_custom_text', 'filter_custom_text_all', 'filter_custom_text_filter', 'overlay_animation', 'show_title', 'show_title_link', 'title_tag', 'show_excerpt', 'excerpt_limit', 'ellipsis', 'strip_shortcode', 'show_category', 'show_link', 'external_link', 'link_type', 'post_link_text', 'lightbox_link_text', 'tilt_show', 'tilt_scale', 'lightbox_animation', 'lightbox_autoplay', 'lightbox_pause', 'grid_animation_type', 'grid_anim_delay', 'overlay_blur_effect' ) );
	},
);
