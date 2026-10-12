<?php
/** Element Pack Pro 9.9.1 `bdt-image-accordion` → `digitalisimo-accordion`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-accordion',
	'classes'   => array(
		'.bdt-ep-image-accordion-sub-title' => '.digi-accordion__title',
		'.bdt-ep-image-accordion-content' => '.digi-accordion__content',
		'.bdt-ep-image-accordion-button' => '.digi-accordion__content a',
		'.bdt-ep-image-accordion-title' => '.digi-accordion__title',
		'.bdt-ep-image-accordion-text' => '.digi-accordion__content',
		'.bdt-ep-image-accordion-item' => '.digi-accordion__item',
		'.bdt-text' => '.digi-accordion__content',
		'.bdt-image-accordion' => '.digi-accordion',
	),
	'defaults'  => array(
		'skin_type' => 'default',
		'skin_type_tablet' => 'default',
		'skin_type_mobile' => 'default',
		'thumbnail_size_size' => 'full',
		'image_accordion_event' => 'mouseover',
		'items_content_position' => 'row',
		'active_item_number' => 1,
		'active_item_expand' => array(
			'size' => 6,
		),
		'active_item_expand_tablet' => array(
			'size' => 6,
		),
		'active_item_expand_mobile' => array(
			'size' => 10,
		),
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_sub_title' => 'yes',
		'show_text' => 'yes',
		'show_button' => 'yes',
		'link_type' => 'icon',
		'icon' => 'plus',
		'link_text' => 'ZOOM',
		'lightbox_animation' => 'slide',
		'lightbox_placement' => 'top-right',
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
	),
	'repeaters' => array(
		'image_accordion_items' => array(
			'target'       => 'tabs',
			'rename'       => array( 'image_accordion_title' => 'tab_title', 'image_accordion_text' => 'tab_content' ),
			'defaults'     => array(
				'image_accordion_title' => 'Tab Title',
				'image_accordion_button' => 'Read More',
				'button_link' => array(
					'url' => '#',
				),
				'title_link' => array(
					'url' => '',
				),
				'image_accordion_text' => 'Box Content',
			),
			'default_rows' => array( array(
					'image_accordion_sub_title' => 'This is a label',
					'image_accordion_title' => 'Image Accordion One',
					'image_accordion_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image_accordion_sub_title' => 'This is a label',
					'image_accordion_title' => 'Image Accordion Two',
					'image_accordion_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image_accordion_sub_title' => 'This is a label',
					'image_accordion_title' => 'Image Accordion Three',
					'image_accordion_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image_accordion_sub_title' => 'This is a label',
					'image_accordion_title' => 'Image Accordion Four',
					'image_accordion_text' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		foreach ( $out['tabs'] as $i => $row ) {
			$sub = trim( wp_strip_all_tags( (string) ( $row['image_accordion_sub_title'] ?? '' ) ) );
			$out['tabs'][ $i ] = array(
				'_id'         => (string) ( $row['_id'] ?? '' ),
				'source'      => 'custom',
				'tab_title'   => trim( wp_strip_all_tags( (string) ( $row['tab_title'] ?? '' ) ) ),
				'tab_content' => ( '' !== $sub ? '<p><strong>' . esc_html( $sub ) . '</strong></p>' : '' ) . (string) ( $row['tab_content'] ?? '' )
					. ( '' !== trim( (string) ( $row['image_accordion_button'] ?? '' ) ) && ! empty( $row['button_link']['url'] ) ? '<p><a href="' . esc_url( $row['button_link']['url'] ) . '">' . esc_html( $row['image_accordion_button'] ) . '</a></p>' : '' ),
			);
		}
		$out['active_item'] = 'yes' === ( $out['active_item'] ?? '' ) ? (int) ( $out['active_item_number'] ?? 1 ) : 0;
		foreach ( array( 'active_item_number', 'inactive_item_overlay', 'enable_item_style' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
