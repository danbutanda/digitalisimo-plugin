<?php
/** Element Pack Pro 9.9.1 `bdt-post-block` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-post-block-read-more' => '.elementor-post__read-more',
		'.bdt-post-block-thumbnail' => '.elementor-post__thumbnail',
		'.bdt-post-block-excerpt' => '.elementor-post__excerpt',
		'.bdt-post-block-title' => '.elementor-post__title',
		'.bdt-post-block-item' => '.elementor-post',
		'.bdt-post-block-meta' => '.elementor-post__meta-data',
		'.bdt-post-block' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 5,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'featured_item' => '1',
		'featured_show_tag' => 'yes',
		'featured_show_title' => 'yes',
		'featured_title_size' => 'h4',
		'featured_show_date' => 'yes',
		'featured_show_category' => 'yes',
		'featured_show_excerpt' => 'yes',
		'featured_excerpt_length' => 15,
		'strip_shortcode' => 'yes',
		'featured_show_read_more' => 'yes',
		'read_more_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'trinity_column_gap' => 'medium',
		'list_show_title' => 'yes',
		'list_title_size' => 'h4',
		'list_show_date' => 'yes',
		'show_list_divider' => 'yes',
		'tag_color' => '#fff',
		'overlay_blur_level' => array(
			'size' => 5,
		),
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 5 ),
			'columns' => '1',
			'title' => array( 'featured_show_title', 'yes' ),
			'title_tag' => 'h3',
			'date' => array( 'featured_show_date', 'yes' ),
			'category' => array( 'featured_show_category', 'yes' ),
			'tags' => array( 'featured_show_tag', 'yes' ),
			'excerpt' => array( 'featured_show_excerpt', 'yes' ),
			'excerpt_length' => array( 'featured_excerpt_length', 15 ),
			'read_more' => array( 'featured_show_read_more', 'yes' ),
			'read_more_text' => array( 'read_more_text', 'Read More' ),
		), array( 'featured_item', 'featured_show_tag', 'featured_show_title', 'featured_title_size', 'featured_show_date', 'featured_human_diff_time', 'featured_human_diff_time_short', 'featured_show_category', 'featured_show_excerpt', 'featured_excerpt_length', 'strip_shortcode', 'bdt_link_new_tab', 'featured_show_read_more', 'read_more_text', 'post_block_icon', 'icon_align', 'trinity_column_gap', 'list_show_title', 'list_title_size', 'list_show_date', 'list_human_diff_time', 'list_human_diff_time_short', 'list_show_category', 'show_list_divider', 'overlay_blur_effect', 'read_more_hover_animation' ) );
	},
);
