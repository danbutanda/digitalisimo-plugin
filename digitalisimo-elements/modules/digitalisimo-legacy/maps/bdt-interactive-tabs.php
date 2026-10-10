<?php
/** Element Pack Pro 9.9.1 `bdt-interactive-tabs` → `digitalisimo-fancy-tabs`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-tabs',
	'classes'   => array(
		'.bdt-interactive-tabs-sub-title' => '.digi-fancy-tabs__label strong',
		'.bdt-interactive-tabs-title' => '.digi-fancy-tabs__label strong',
		'.bdt-interactive-tabs-text' => '.digi-fancy-tabs__panel',
		'.bdt-interactive-tabs-item' => '.digi-fancy-tabs__tab',
		'.bdt-interactive-tabs-icon' => '.digi-fancy-tabs__icon',
		'.bdt-interactive-tabs' => '.digi-fancy-tabs',
	),
	'defaults'  => array(
		'thumbnail_size_size' => 'full',
		'show_icon' => 'yes',
		'show_sub_title' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h3',
		'columns' => '2',
		'columns_tablet' => '1',
		'columns_mobile' => '1',
		'column_gap' => array(
			'size' => 15,
		),
		'tabs_width' => array(
			'size' => 50,
		),
		'tabs_width_tablet' => array(
			'size' => 50,
		),
		'tabs_width_mobile' => array(
			'size' => 100,
		),
		'tabs_position' => 'center',
		'tabs_text_alignment' => 'left',
		'thumbs_horizontal_offset' => array(
			'size' => 0,
		),
		'thumbs_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'thumbs_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'thumbs_vertical_offset' => array(
			'size' => 0,
		),
		'thumbs_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'thumbs_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'thumbs_rotate' => array(
			'size' => 0,
		),
		'thumbs_rotate_tablet' => array(
			'size' => 0,
		),
		'thumbs_rotate_mobile' => array(
			'size' => 0,
		),
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'transition' => 'fade',
		'autoplay_speed' => 5000,
		'speed' => array(
			'size' => 500,
		),
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'tabs_item_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'tabs_item_hover_border_color' => '#4AB8F8',
		'tabs_item_active_border_color' => '#4AB8F8',
		'background_hover_transition' => array(
			'size' => 0.3,
		),
		'tabs_content_width' => array(
			'unit' => '%',
		),
		'tabs_content_width_tablet' => array(
			'unit' => '%',
		),
		'tabs_content_width_mobile' => array(
			'unit' => '%',
		),
		'icon_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
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
			'target'       => 'tabs',
			'rename'       => array( 'tab_text' => 'tab_content' ),
			'defaults'     => array(
				'selected_icon' => array(
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'tab_title' => 'Tab Title',
				'tab_text' => 'Tab Content',
				'source' => 'background',
				'background' => 'image',
				'video_link' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4',
				'youtube_link' => 'https://youtu.be/YE7VzlLtp-4',
			),
			'default_rows' => array( array(
					'tab_sub_title' => 'This is a subtitle',
					'tab_title' => 'Interactive title one',
					'selected_icon' => array(
						'value' => 'fas fa-smile',
						'library' => 'fa-solid',
					),
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'tab_sub_title' => 'This is a subtitle',
					'tab_title' => 'Interactive title two',
					'selected_icon' => array(
						'value' => 'fas fa-cog',
						'library' => 'fa-solid',
					),
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'tab_sub_title' => 'This is a subtitle',
					'tab_title' => 'Interactive title three',
					'selected_icon' => array(
						'value' => 'fas fa-dice-d6',
						'library' => 'fa-solid',
					),
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'tab_sub_title' => 'This is a subtitle',
					'tab_title' => 'Interactive title four',
					'selected_icon' => array(
						'value' => 'fas fa-ring',
						'library' => 'fa-solid',
					),
					'image' => array(
						'url' => $placeholder_url,
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		foreach ( $out['tabs'] as $i => $row ) {
			$out['tabs'][ $i ]['icon_type']   = ! empty( $row['selected_icon']['value'] ) ? 'icon' : ( ! empty( $row['image']['url'] ) ? 'image' : 'none' );
			$out['tabs'][ $i ]['tab_title']   = trim( wp_strip_all_tags( (string) ( $row['tab_title'] ?? '' ) ) );
		}
		unset( $out['tabs_icon_top'] );
		return $out;
	},
);
