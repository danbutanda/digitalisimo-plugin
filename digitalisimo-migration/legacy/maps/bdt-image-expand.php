<?php
/** Element Pack Pro 9.9.1 `bdt-image-expand` → `digitalisimo-fancy-tabs`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-tabs',
	'classes'   => array(
		'.bdt-ep-image-expand-sub-title' => '.digi-fancy-tabs__label strong',
		'.bdt-ep-image-expand-content' => '.digi-fancy-tabs__panel',
		'.bdt-ep-image-expand-button' => '.digi-fancy-tabs__button',
		'.bdt-ep-image-expand-title' => '.digi-fancy-tabs__label strong',
		'.bdt-ep-image-expand-item' => '.digi-fancy-tabs__tab',
		'.bdt-ep-image-expand-text' => '.digi-fancy-tabs__panel',
		'.bdt-text' => '.digi-fancy-tabs__panel',
		'.bdt-image-expand' => '.digi-fancy-tabs',
	),
	'defaults'  => array(
		'skin_type' => 'default',
		'skin_type_tablet' => 'default',
		'skin_type_mobile' => 'default',
		'thumbnail_size_size' => 'full',
		'items_content_position' => 'row',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_sub_title' => 'yes',
		'show_button' => 'yes',
		'show_text' => 'yes',
		'link_type' => 'icon',
		'icon' => 'plus',
		'link_text' => 'ZOOM',
		'lightbox_animation' => 'slide',
		'lightbox_placement' => 'top-right',
		'default_animation_type' => 'fade',
		'animation_status' => 'no',
		'animation_of' => array( '.bdt-ep-image-expand-sub-title', '.bdt-ep-image-expand-title', '.bdt-ep-image-expand-text' ),
		'animation_on' => 'words',
		'anim_transform_origin' => '0% 50% -50',
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
	),
	'repeaters' => array(
		'image_expand_items' => array(
			'target'       => 'tabs',
			'rename'       => array( 'image_expand_title' => 'tab_title', 'image_expand_sub_title' => 'tab_sub_title', 'image_expand_text' => 'tab_content', 'image_expand_button' => 'tabs_button', 'slide_image' => 'image' ),
			'defaults'     => array(
				'image_expand_title' => 'Tab Title',
				'image_expand_button' => 'Read More',
				'button_link' => array(
					'url' => '#',
				),
				'title_link' => array(
					'url' => '',
				),
				'image_expand_text' => 'Box Content',
			),
			'default_rows' => array( array(
					'image_expand_sub_title' => 'This is a label',
					'image_expand_title' => 'Image Expand Item One',
					'image_expand_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni.',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image_expand_sub_title' => 'This is a label',
					'image_expand_title' => 'Image Expand Item Two',
					'image_expand_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni.',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image_expand_sub_title' => 'This is a label',
					'image_expand_title' => 'Image Expand Item Three',
					'image_expand_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni.',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image_expand_sub_title' => 'This is a label',
					'image_expand_title' => 'Image Expand Item Four',
					'image_expand_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni.',
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
		
		return $out;
	},
);
