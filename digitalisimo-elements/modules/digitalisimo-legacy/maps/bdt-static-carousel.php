<?php
/** Element Pack Pro 9.9.1 `bdt-static-carousel` → `digitalisimo-fancy-slider`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-slider',
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
		'show_title' => 'yes',
		'title_tag' => 'h2',
		'show_sub_title' => 'yes',
		'sub_title_tag' => 'h3',
		'show_text' => 'yes',
		'readmore_link_to' => 'button',
		'show_image' => 'yes',
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
		'readmore_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
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
		'title_style' => '',
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
		'carousel_items' => array(
			'defaults'     => array(
				'image' => array(
					'url' => $placeholder_url,
				),
				'text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
				'readmore_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'title' => 'This is a title',
					'sub_title' => 'Sub Title',
				), array(
					'title' => 'This is a title',
					'sub_title' => 'Sub Title',
				), array(
					'title' => 'This is a title',
					'sub_title' => 'Sub Title',
				), array(
					'title' => 'This is a title',
					'sub_title' => 'Sub Title',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$slides = array();
		foreach ( is_array( $out['carousel_items'] ?? null ) ? $out['carousel_items'] : array() as $row ) {
			$slides[] = array(
				'_id'          => (string) ( $row['_id'] ?? '' ),
				'sub_title'    => trim( wp_strip_all_tags( (string) ( $row['sub_title'] ?? '' ) ) ),
				'title'        => trim( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ) ),
				'description'  => (string) ( $row['text'] ?? '' ),
				'slide_image'  => is_array( $row['image'] ?? null ) ? $row['image'] : array( 'url' => '' ),
				'slide_button' => 'yes' === ( $out['show_readmore'] ?? 'yes' ) ? (string) ( $out['readmore_text'] ?? 'Read More' ) : '',
				'button_link'  => is_array( $row['readmore_link'] ?? null ) ? $row['readmore_link'] : array( 'url' => '' ),
			);
		}
		$out['slides'] = $slides;
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(carousel_items|show_readmore|readmore_text)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
