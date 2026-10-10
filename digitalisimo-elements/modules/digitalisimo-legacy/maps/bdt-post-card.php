<?php
/** Element Pack Pro 9.9.1 `bdt-post-card` → `posts` (piel «classic» propia de los adaptadores). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-post-card-excerpt' => '.elementor-post__excerpt',
		'.bdt-post-card-button' => '.elementor-post__read-more',
		'.bdt-post-card-title' => '.elementor-post__title',
		'.bdt-post-card-meta' => '.elementor-post__meta-data',
		'.bdt-post-card-item' => '.elementor-post',
		'.bdt-post-card-desc' => '.elementor-post__text',
		'.bdt-post-card-tag' => '.elementor-post__terms--post_tag a',
		'.bdt-post-card' => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 3,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'thumb' => 'yes',
		'thumbnail_size' => 'large',
		'title' => 'yes',
		'title_tags' => 'h4',
		'meta_date' => 'yes',
		'meta_category' => 'yes',
		'tags' => 'yes',
		'excerpt' => 'yes',
		'excerpt_length' => 15,
		'strip_shortcode' => 'yes',
		'button' => 'yes',
		'button_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
	),
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::posts_classic( $out, array(
			'per_page' => array( 'posts_per_page', 3 ),
			'columns' => '3',
			'title' => 'yes',
			'title_tag' => array( 'title_tags', 'h4' ),
			'date' => 'yes',
			'category' => 'yes',
			'tags' => 'yes',
			'excerpt' => array( 'excerpt', 'yes' ),
			'excerpt_length' => array( 'excerpt_length', 15 ),
			'read_more' => 'yes',
			'read_more_text' => 'Read More',
		), array( 'thumb', 'title', 'title_tags', 'meta_date', 'human_diff_time', 'human_diff_time_short', 'meta_category', 'tags', 'excerpt', 'excerpt_length', 'strip_shortcode', 'button', 'bdt_link_new_tab', 'button_text', 'post_card_icon', 'icon_align', 'button_hover_animation' ) );
	},
);
