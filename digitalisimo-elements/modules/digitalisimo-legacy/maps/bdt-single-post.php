<?php
/** Element Pack Pro 9.9.1 `bdt-single-post` → `posts`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-single-post-title'    => '.elementor-post__title',
		'.bdt-single-post-meta'     => '.elementor-post__meta-data',
		'.bdt-single-post-excerpt'  => '.elementor-post__excerpt',
		'.bdt-single-post-tag-wrap' => '.elementor-post__terms--post_tag',
		'.bdt-single-post'          => '.elementor-posts-container',
	),
	'defaults'  => array(
		'show_tag' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'link_title' => 'yes',
		'show_date' => 'yes',
		'show_category' => 'yes',
		'excerpt_length' => 40,
		'strip_shortcode' => 'yes',
		'date_color' => '#e5e5e5',
		'category_color' => '#e5e5e5',
		'overlay_blur_level' => array(
			'size' => 5,
		),
	),
	'filter'    => static function ( array $out ) {
		$yes = static function ( $key ) use ( $out ) {
			return 'yes' === ( $out[ $key ] ?? '' ) ? 'yes' : '';
		};
		$id  = absint( $out['post_list'] ?? 0 );
		$set = array(
			// Sin entrada elegida no se muestra ninguna: «0» no coincide con ningún ID.
			'posts_post_type'         => 'by_id',
			'posts_posts_ids'         => array( (string) $id ),
			'_skin'                   => 'classic',
			'classic_posts_per_page'  => 1,
			'classic_columns'         => '1',
			'classic_thumbnail'       => 'top',
			'classic_masonry'         => '',
			'classic_show_title'      => $yes( 'show_title' ),
			'classic_title_tag'       => (string) ( $out['title_tags'] ?? 'h2' ),
			'classic_meta_data'       => 'yes' === $yes( 'show_date' ) ? array( 'date' ) : array(),
			'classic_show_excerpt'    => $yes( 'show_excerpt' ),
			'classic_excerpt_length'  => (int) ( $out['excerpt_length'] ?? 40 ),
			'classic_show_read_more'  => '',
			'digitalisimo_post_terms' => array_keys( array_filter( array( 'category' => $yes( 'show_category' ), 'post_tag' => $yes( 'show_tag' ) ) ) ),
			'pagination_type'         => '',
		);
		foreach ( array( 'post_list', 'show_tag', 'show_title', 'title_tags', 'link_title', 'show_date', 'show_category', 'show_excerpt', 'excerpt_length', 'ellipsis', 'strip_shortcode', 'overlay_blur_effect' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $set + $out;
	},
);
