<?php
/** Element Pack Pro 9.9.1 `bdt-post-block-modern` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-post-block-modern-read-more' => '.elementor-post__read-more',
		'.bdt-post-block-modern-excerpt' => '.elementor-post__excerpt',
		'.bdt-post-block-modern-title' => '.elementor-post__title',
		'.bdt-post-block-modern-item' => '.elementor-post',
		'.bdt-post-block-modern-meta' => '.elementor-post__meta-data',
		'.bdt-post-block-modern-desc' => '.elementor-post__text',
		'.bdt-post-block-modern' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 4,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'thumbnail_size' => 'large',
		'title' => 'yes',
		'title_tags' => 'h4',
		'show_meta' => 'yes',
		'show_excerpt' => 'yes',
		'excerpt_length' => 15,
		'strip_shortcode' => 'yes',
		'show_read_more' => 'yes',
		'read_more_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'overlay_blur_level' => array(
			'size' => 5,
		),
		'read_more_color' => '',
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 4 ),
			'columns' => '2',
			'title' => 'yes',
			'title_tag' => array( 'title_tags', 'h4' ),
			'date' => array( 'show_meta', 'yes' ),
			'category' => array( 'show_meta', 'yes' ),
			'excerpt' => array( 'show_excerpt', 'yes' ),
			'excerpt_length' => array( 'excerpt_length', 15 ),
			'read_more' => array( 'show_read_more', 'yes' ),
			'read_more_text' => array( 'read_more_text', 'Read More' ),
		), array( 'title', 'title_tags', 'show_meta', 'human_diff_time', 'human_diff_time_short', 'show_excerpt', 'excerpt_length', 'ellipsis', 'strip_shortcode', 'show_read_more', 'bdt_link_new_tab', 'read_more_text', 'post_block_modern_icon', 'icon_align', 'overlay_blur_effect', 'read_more_hover_animation' ) );
	},
);
