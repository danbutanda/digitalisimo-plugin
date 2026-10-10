<?php
/** Element Pack Pro 9.9.1 `bdt-panel-slider` → `digitalisimo-fancy-slider`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-slider',
	'classes'   => array(),
	'defaults'  => array(
		'_skin' => '',
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'skin_columns' => '4',
		'skin_columns_tablet' => '2',
		'skin_columns_mobile' => '2',
		'slider_height' => array(
			'size' => 620,
		),
		'thumbnail_size_size' => 'full',
		'show_title' => 'yes',
		'title_tags' => 'h3',
		'button' => 'yes',
		'button_text' => 'Read More',
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
		'item_shadow_padding' => array(
			'size' => 10,
		),
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
		'tabs' => array(
			'defaults'     => array(
				'tab_title' => 'Slide Title',
				'tab_image' => array(
					'url' => $placeholder_url,
				),
				'tab_content' => 'Slide Content',
				'tab_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'tab_title' => 'Slide #1',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				), array(
					'tab_title' => 'Slide #2',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				), array(
					'tab_title' => 'Slide #3',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				), array(
					'tab_title' => 'Slide #4',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$slides = array();
		foreach ( is_array( $out['tabs'] ?? null ) ? $out['tabs'] : array() as $row ) {
			$slides[] = array(
				'_id'          => (string) ( $row['_id'] ?? '' ),
				'title'        => trim( wp_strip_all_tags( (string) ( $row['tab_title'] ?? '' ) ) ),
				'title_link'   => is_array( $row['tab_link'] ?? null ) ? $row['tab_link'] : array( 'url' => '' ),
				'description'  => (string) ( $row['tab_content'] ?? '' ),
				'slide_image'  => is_array( $row['tab_image'] ?? null ) ? $row['tab_image'] : array( 'url' => '' ),
				'slide_button' => 'yes' === ( $out['button'] ?? 'yes' ) ? (string) ( $out['button_text'] ?? 'Read More' ) : '',
				'button_link'  => is_array( $row['tab_link'] ?? null ) ? $row['tab_link'] : array( 'url' => '' ),
			);
		}
		$out['slides'] = $slides;
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(tabs)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
