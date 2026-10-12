<?php
/** Element Pack Pro 9.9.1 `bdt-post-grid` → `posts`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-post-grid-skin-'    => '.bdt-skin-',
		'.bdt-post-grid-item'     => '.elementor-post',
		'.bdt-post-grid-readmore' => '.elementor-post__read-more',
		'.bdt-post-grid-img-wrap' => '.elementor-post__thumbnail',
		'.bdt-post-grid-title'    => '.elementor-post__title',
		'.bdt-post-grid-tags'     => '.elementor-post__terms--post_tag',
		'.bdt-post-grid-tag'      => '.elementor-post__terms--post_tag a',
		'.bdt-post-grid-category' => '.elementor-post__terms--category a',
		'.bdt-post-grid-meta'     => '.elementor-post__meta-data',
		'.bdt-post-grid-desc'     => '.elementor-post__text',
		'.bdt-post-grid-excerpt'  => '.elementor-post__excerpt',
		'.bdt-post-grid-comments' => '.elementor-post-avatar',
		'.bdt-post-grid-author'   => '.elementor-post-author',
		'.bdt-pagination'         => '.elementor-pagination',
		'.bdt-post-grid'          => '.elementor-posts-container',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 6,
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'default_item_limit' => array(
			'size' => 5,
		),
		'carmie_item_limit' => array(
			'size' => 6,
		),
		'harold_item_limit' => array(
			'size' => 4,
		),
		'trosia_item_limit' => array(
			'size' => 9,
		),
		'reverse_item_limit' => array(
			'size' => 6,
		),
		'alter_item_limit' => array(
			'size' => 6,
		),
		'paddle_item_limit' => array(
			'size' => 8,
		),
		'column_gap' => 'small',
		'odd_item_columns' => '2',
		'even_item_columns' => '3',
		'alter_skin_image_width' => array(
			'size' => 50,
		),
		'primary_thumbnail_size' => 'full',
		'secondary_columns' => '3',
		'secondary_columns_tablet' => '3',
		'secondary_columns_mobile' => '1',
		'secondary_grid_height' => 'none',
		'secondary_thumbnail_size' => 'medium',
		'thumbnail_size' => 'full',
		'post_grid_ajax_loadmore_items' => 3,
		'post_grid_show_loadmore' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_author' => 'yes',
		'show_date' => 'yes',
		'show_comments' => 'yes',
		'show_category' => 'yes',
		'tags_string' => 'Tags: ',
		'excerpt_length' => 15,
		'primary_excerpt_length' => 40,
		'secondary_excerpt_length' => 15,
		'strip_shortcode' => 'yes',
		'readmore_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'image_animation' => 'scale-up',
		'title_spacing' => array(
			'size' => 5,
		),
		'excerpt_spacing' => array(
			'size' => 15,
		),
		'readmore_spacing' => array(
			'size' => 20,
		),
		'overlay_blur_level' => array(
			'size' => 5,
		),
	),
	'filter'    => static function ( array $out ) {
		$out   = Digitalisimo\Elements\Legacy\Translator::posts_query( $out );
		$skin  = (string) ( $out['_skin'] ?? '' );
		$limit = $out[ ( '' === $skin ? 'default' : str_replace( 'bdt-', '', $skin ) ) . '_item_limit' ]['size'] ?? ( $out['default_item_limit']['size'] ?? 6 );
		$yes   = static function ( $key ) use ( $out ) {
			return 'yes' === ( $out[ $key ] ?? '' ) ? 'yes' : '';
		};
		$set = array(
			'_skin'                  => 'classic',
			'classic_posts_per_page' => max( 1, (int) $limit ),
			'classic_thumbnail'      => 'top',
			'classic_masonry'        => '',
			'classic_show_title'     => $yes( 'show_title' ),
			'classic_title_tag'      => (string) ( $out['title_tags'] ?? 'h2' ),
			'classic_meta_data'      => array_keys( array_filter( array( 'author' => $yes( 'show_author' ), 'date' => $yes( 'show_date' ), 'comments' => $yes( 'show_comments' ) ) ) ),
			'classic_meta_separator' => '|',
			'classic_show_excerpt'   => $yes( 'show_excerpt' ),
			'classic_excerpt_length' => (int) ( $out['excerpt_length'] ?? 15 ),
			'classic_show_read_more' => $yes( 'show_readmore' ),
			'classic_read_more_text' => (string) ( $out['readmore_text'] ?? 'Read More' ),
			'classic_open_new_tab'   => $yes( 'bdt_link_new_tab' ),
			'digitalisimo_post_terms' => array_keys( array_filter( array( 'category' => $yes( 'show_category' ), 'post_tag' => $yes( 'show_tags' ) ) ) ),
			'digitalisimo_tags_label' => trim( (string) ( $out['tags_string'] ?? '' ) ),
		);
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			if ( isset( $out[ 'columns' . $device ] ) ) {
				$set[ 'classic_columns' . $device ] = (string) $out[ 'columns' . $device ];
			}
		}
		// Paginación numerada, «cargar más» o desplazamiento infinito.
		if ( 'yes' === $yes( 'show_pagination' ) ) {
			$set['pagination_type'] = 'numbers';
		} elseif ( 'yes' === $yes( 'post_grid_ajax_loadmore' ) ) {
			$set['pagination_type'] = 'yes' === $yes( 'post_grid_show_infinite_scroll' ) ? 'load_more_infinite_scroll' : 'load_more_on_click';
		} else {
			$set['pagination_type'] = '';
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(columns|secondary_columns|odd_item_columns|even_item_columns)(_tablet|_mobile)?$|_item_limit$|^(column_gap|secondary_grid_height|show_pagination|post_grid_ajax_loadmore|post_grid_ajax_loadmore_items|post_grid_show_loadmore|post_grid_show_infinite_scroll|show_title|title_tags|show_author|show_date|human_diff_time|human_diff_time_short|show_comments|show_category|show_tags|tags_string|show_excerpt|excerpt_length|primary_excerpt_length|secondary_excerpt_length|ellipsis|strip_shortcode|show_readmore|readmore_text|post_grid_icon|icon_align|global_link|bdt_link_new_tab|image_animation|title_advanced_style|readmore_hover_animation|overlay_blur_effect|deprecated_post_widget_note|posts_per_page)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
