<?php
/** Element Pack Pro 9.9.1 `bdt-post-grid-tab` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-post-grid-tab-item-title' => '.elementor-post__title',
		'.bdt-post-grid-tab-thumbnail' => '.elementor-post__thumbnail',
		'.bdt-post-grid-tab-readmore' => '.elementor-post__read-more',
		'.bdt-post-grid-tab-excerpt' => '.elementor-post__excerpt',
		'.bdt-post-grid-tab-desc' => '.elementor-post__text',
		'.bdt-post-grid-tab-meta' => '.elementor-post__meta-data',
		'.bdt-post-grid-tab' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 8,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => 4,
		'columns_tablet' => 3,
		'columns_mobile' => 2,
		'grid_tab_item' => 'image',
		'thumbnail_size' => 'medium',
		'show_title' => 'yes',
		'title_tag' => 'h3',
		'show_author' => 'yes',
		'show_date' => 'yes',
		'show_comments' => 'yes',
		'show_category' => 'yes',
		'content_image' => 'yes',
		'content_thumbnail_size' => 'full',
		'show_excerpt' => 'yes',
		'excerpt_length' => 45,
		'strip_shortcode' => 'yes',
		'show_readmore' => 'yes',
		'readmore_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'show_close' => 'yes',
		'tab_padding' => array(
			'size' => '0',
		),
		'tab_text_align' => 'center',
		'item_border_width' => array(
			'size' => 10,
		),
		'tab_border_color' => '#ddd',
		'active_tab_no' => array(
			'size' => 0,
		),
		'active_tab_background' => '#fff',
		'title_spacing' => array(
			'size' => 5,
		),
		'meta_color' => '#adb5bd',
		'divider_color' => '#adb5bd',
		'excerpt_spacing' => array(
			'size' => 15,
		),
		'readmore_spacing' => array(
			'size' => 20,
		),
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 8 ),
			'columns' => array( 'columns', '4' ),
			'title' => array( 'show_title', 'yes' ),
			'title_tag' => array( 'title_tag', 'h3' ),
			'author' => array( 'show_author', 'yes' ),
			'date' => array( 'show_date', 'yes' ),
			'comments' => array( 'show_comments', 'yes' ),
			'category' => array( 'show_category', 'yes' ),
			'excerpt' => array( 'show_excerpt', 'yes' ),
			'excerpt_length' => array( 'excerpt_length', 45 ),
			'read_more' => array( 'show_readmore', 'yes' ),
			'read_more_text' => array( 'readmore_text', 'Read More' ),
		), array( 'columns', 'grid_tab_item', 'content_reverse', 'show_title', 'title_tag', 'show_author', 'show_date', 'human_diff_time', 'human_diff_time_short', 'show_comments', 'show_category', 'content_image', 'show_excerpt', 'excerpt_length', 'ellipsis', 'strip_shortcode', 'show_readmore', 'readmore_text', 'post_grid_tab_icon', 'icon_align', 'show_close', 'bdt_link_new_tab', 'tab_padding', 'item_border_width', 'active_tab_no', 'readmore_hover_animation' ) );
	},
);
