<?php
/** Element Pack Pro 9.9.1 `bdt-review-card-carousel` → `reviews`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'reviews',
	'classes'   => array(),
	'defaults'  => array(
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 35,
		),
		'item_gap_tablet' => array(
			'size' => 20,
		),
		'item_gap_mobile' => array(
			'size' => 20,
		),
		'item_match_height' => 'yes',
		'review_name_tag' => 'h3',
		'show_rating' => 'yes',
		'rating_type' => 'star',
		'rating_position' => 'before',
		'show_reviewer_image' => 'yes',
		'thumbnail_size_size' => 'medium',
		'iamge_position' => 'top',
		'iamge_alignment' => 'flex-start',
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'text_align' => 'start',
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
		'image_spacing' => array(
			'size' => 15,
		),
		'image_horizontal_offset' => array(
			'size' => 0,
		),
		'image_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'image_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'image_vertical_offset' => array(
			'size' => 0,
		),
		'image_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'image_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'rating_number_color' => '#fff',
		'rating_background_color' => '#1e87f0',
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
	),
	'repeaters' => array(
		'review_items' => array(
			'defaults'     => array(
				'image' => array(
					'url' => $placeholder_url,
				),
				'reviewer_name' => 'Adam Smith',
				'reviewer_job_title' => 'SEO Expert',
				'rating_number' => array(
					'size' => 4.5,
				),
				'review_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
			),
			'default_rows' => array( array(
					'reviewer_name' => 'Adam Smith',
					'reviewer_job_title' => 'SEO Expert',
				), array(
					'reviewer_name' => 'Jhon Deo',
					'reviewer_job_title' => 'Web Desiger',
				), array(
					'reviewer_name' => 'Maria Mak',
					'reviewer_job_title' => 'Web Expert',
				), array(
					'reviewer_name' => 'Jackma Kalin',
					'reviewer_job_title' => 'Elementor Expert',
				), array(
					'reviewer_name' => 'Amily Moalin',
					'reviewer_job_title' => 'WP Officer',
				), array(
					'reviewer_name' => 'Enagol Ame',
					'reviewer_job_title' => 'WP Developer',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$slides = array();
		foreach ( is_array( $out['review_items'] ?? null ) ? $out['review_items'] : array() as $row ) {
			$slides[] = array(
					'_id'                  => (string) ( $row['_id'] ?? '' ),
					'image'                => 'yes' === ( $out['show_reviewer_image'] ?? 'yes' ) && is_array( $row['image'] ?? null ) ? $row['image'] : array( 'url' => '' ),
					'name'                 => 'yes' === ( $out['show_reviewer_name'] ?? 'yes' ) ? trim( wp_strip_all_tags( (string) ( $row['reviewer_name'] ?? '' ) ) ) : '',
					'title'                => 'yes' === ( $out['show_reviewer_job_title'] ?? 'yes' ) ? trim( wp_strip_all_tags( (string) ( $row['reviewer_job_title'] ?? '' ) ) ) : '',
					'rating'               => 'yes' === ( $out['show_rating'] ?? 'yes' ) ? (float) ( $row['rating_number']['size'] ?? $row['rating_number'] ?? 5 ) : '',
					'selected_social_icon' => array( 'value' => '', 'library' => '' ),
					'content'              => 'yes' === ( $out['show_review_text'] ?? 'yes' ) ? (string) ( $row['review_text'] ?? '' ) : '',
				);
		}
		$out['slides'] = $slides;
		$out['slides_per_view'] = (string) ( $out['columns'] ?? '3' );
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(review_items|image|reviewer_.*|rating_number|review_text|review_words_length|show_reviewer_.*|review_name_tag|show_rating|rating_type|rating_position|show_review_text|image_inline|image_mask_shape|text_margin|item_gap|item_match_height)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
