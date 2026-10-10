<?php
/** Element Pack Pro 9.9.1 `bdt-post-list` → `posts`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'posts',
	'classes'   => array(
		'.bdt-post-list-header' => '.bdt-skip-header',
		'.bdt-post-list'        => '.elementor-posts-container',
		'.bdt-item-wrap'        => '.elementor-post',
		'.bdt-item'             => '.elementor-post',
		'.bdt-image'            => '.elementor-post__thumbnail',
		'.bdt-title'            => '.elementor-post__title',
		'.bdt-meta'             => '.elementor-post__meta-data',
	),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 6,
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'show_title' => 'yes',
		'title_tags' => 'h4',
		'show_image' => 'yes',
		'thumbnail_size' => 'thumbnail',
		'column' => '2',
		'column_tablet' => '2',
		'column_mobile' => '1',
		'show_date' => 'yes',
		'show_category' => 'yes',
		'show_divider' => 'yes',
		'header_title_text' => 'Trending Articles',
		'background_hover_transition' => array(
			'size' => 0.3,
		),
	),
	'filter'    => static function ( array $out ) {
		$out        = Digitalisimo\Elements\Legacy\Translator::posts_query( $out );
		$yes        = static function ( $key ) use ( $out ) {
			return 'yes' === ( $out[ $key ] ?? '' ) ? 'yes' : '';
		};
		$horizontal = 'yes' === $yes( 'show_horizontal' );
		// La lista vertical ponía la miniatura a un lado; la horizontal la repartía en columnas.
		$set = array(
			'_skin'                   => 'classic',
			'classic_posts_per_page'  => max( 1, (int) ( $out['posts_per_page'] ?? 6 ) ),
			'classic_columns'         => $horizontal ? (string) ( $out['column'] ?? '2' ) : '1',
			'classic_thumbnail'       => 'yes' !== $yes( 'show_image' ) ? 'none' : ( $horizontal ? 'top' : 'left' ),
			'classic_masonry'         => '',
			'classic_show_title'      => $yes( 'show_title' ),
			'classic_title_tag'       => (string) ( $out['title_tags'] ?? 'h4' ),
			'classic_meta_data'       => 'yes' === $yes( 'show_date' ) ? array( 'date' ) : array(),
			'classic_show_excerpt'    => '',
			'classic_show_read_more'  => '',
			'classic_open_new_tab'    => $yes( 'bdt_link_new_tab' ),
			'digitalisimo_post_terms' => 'yes' === $yes( 'show_category' ) ? array( 'category' ) : array(),
			'pagination_type'         => 'yes' === $yes( 'show_pagination' ) ? 'numbers' : '',
		);
		if ( $horizontal ) {
			foreach ( array( '_tablet', '_mobile' ) as $device ) {
				if ( isset( $out[ 'column' . $device ] ) ) {
					$set[ 'classic_columns' . $device ] = (string) $out[ 'column' . $device ];
				}
			}
		}
		foreach ( array( 'show_title', 'title_tags', 'show_image', 'show_horizontal', 'column', 'column_tablet', 'column_mobile', 'show_date', 'human_diff_time', 'human_diff_time_short', 'show_category', 'show_divider', 'show_filter_bar', 'header_title_text', 'follow_descendants', 'icon', 'icon_link_enable', 'bdt_link_new_tab', 'show_pagination', 'posts_per_page' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $set + $out;
	},
);
