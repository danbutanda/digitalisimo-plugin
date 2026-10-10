<?php
/** Element Pack Pro 9.9.1 `bdt-portfolio-list` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-gallery-thumbnail' => '.elementor-post__thumbnail',
		'.bdt-pagination' => '.elementor-pagination',
		'.bdt-portfolio-list' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'portfolio',
		'posts_per_page' => 9,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => '2',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
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
		'item_gap' => array(
			'size' => 30,
		),
		'row_gap' => array(
			'size' => 30,
		),
		'portfolio_content_alignment' => 'center',
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 9 ),
			'columns' => array( 'columns', '2' ),
			'title' => array( 'show_title', 'yes' ),
			'title_tag' => array( 'title_tag', 'h4' ),
			'category' => array( 'show_category', '' ),
			'excerpt' => array( 'show_excerpt', '' ),
			'excerpt_length' => array( 'excerpt_limit', 10 ),
			'pagination' => array( 'show_pagination', '' ),
		), array( 'show_horizontal_layout', 'columns', 'show_pagination', 'show_title', 'title_tag', 'show_excerpt', 'excerpt_limit', 'ellipsis', 'strip_shortcode', 'show_category', 'show_link', 'external_link', 'link_type', 'post_link_text', 'lightbox_link_text', 'tilt_show', 'tilt_scale', 'lightbox_animation', 'lightbox_autoplay', 'lightbox_pause', 'grid_animation_type', 'grid_anim_delay', 'border_radius_advanced_show' ) );
	},
);
