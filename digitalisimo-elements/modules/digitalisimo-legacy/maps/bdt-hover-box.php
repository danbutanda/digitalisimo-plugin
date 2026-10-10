<?php
/** Element Pack Pro 9.9.1 `bdt-hover-box` → `digitalisimo-fancy-tabs`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-tabs',
	'classes'   => array(
		'.bdt-ep-hover-box-sub-title' => '.digi-fancy-tabs__label strong',
		'.bdt-ep-hover-box-content' => '.digi-fancy-tabs__panel',
		'.bdt-ep-hover-box-button' => '.digi-fancy-tabs__button',
		'.bdt-ep-hover-box-title' => '.digi-fancy-tabs__label strong',
		'.bdt-ep-hover-box-item' => '.digi-fancy-tabs__tab',
		'.bdt-ep-hover-box-text' => '.digi-fancy-tabs__panel',
		'.bdt-hover-box' => '.digi-fancy-tabs',
	),
	'defaults'  => array(
		'layout_style' => 'style-1',
		'default_content_position' => 'center',
		'content_gap' => 'medium',
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '2',
		'content_position' => 'bottom',
		'column_gap' => 'small',
		'hover_box_event' => 'mouseover',
		'hover_box_active_item' => '1',
		'thumbnail_size_size' => 'full',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_icon' => 'yes',
		'show_sub_title' => 'yes',
		'box_image_effect_select' => 'effect-1',
		'box_item_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'icon_border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'icon_hover_rotate' => array(
			'unit' => 'deg',
			'size' => 90,
		),
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
	),
	'repeaters' => array(
		'hover_box' => array(
			'target'       => 'tabs',
			'rename'       => array( 'hover_box_title' => 'tab_title', 'hover_box_sub_title' => 'tab_sub_title', 'hover_box_content' => 'tab_content', 'hover_box_button' => 'tabs_button', 'slide_image' => 'image' ),
			'defaults'     => array(
				'selected_icon' => array(
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'hover_box_title' => 'Tab Title',
				'hover_box_button' => 'Read More',
				'button_link' => array(
					'url' => '#',
				),
				'title_link' => array(
					'url' => '',
				),
				'hover_box_content' => 'Box Content',
			),
			'default_rows' => array( array(
					'hover_box_sub_title' => 'This is label',
					'hover_box_title' => 'Hover Box One',
					'hover_box_content' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'far fa-laugh',
						'library' => 'fa-regular',
					),
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'hover_box_sub_title' => 'This is label',
					'hover_box_title' => 'Hover Box Two',
					'hover_box_content' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-cog',
						'library' => 'fa-solid',
					),
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'hover_box_sub_title' => 'This is label',
					'hover_box_title' => 'Hover Box Three',
					'hover_box_content' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-dice-d6',
						'library' => 'fa-solid',
					),
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'hover_box_sub_title' => 'This is label',
					'hover_box_title' => 'Hover Box Four',
					'hover_box_content' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-ring',
						'library' => 'fa-solid',
					),
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'hover_box_sub_title' => 'This is label',
					'hover_box_title' => 'Hover Box Five',
					'hover_box_content' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-adjust',
						'library' => 'fa-solid',
					),
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'hover_box_sub_title' => 'This is label',
					'hover_box_title' => 'Hover Box Six',
					'hover_box_content' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-cog',
						'library' => 'fa-solid',
					),
					'slide_image' => array(
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
		unset( $out['hover_box_active_item'] );
		return $out;
	},
);
