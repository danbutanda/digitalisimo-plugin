<?php
/** Element Pack Pro 9.9.1 `bdt-product-grid` → `digitalisimo-product-grid`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-product-grid',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-product-grid-item, .bdt-ep-product-grid-readmore, .bdt-ep-, .bdt-ep-product-grid-image, .bdt-ep-product-grid-rating, .bdt-image-mask, .bdt-ep-product-grid-badge, .bdt-ep-product-grid-title, .bdt-ep-product-grid-price, .bdt-ep-product-grid-time, .bdt-ep-product-grid, .bdt-ep-product-grid-text, .bdt-badge, .bdt-ep-product-grid-rating-count
	'classes'   => array(
		'.bdt-ep-product-grid-readmore'  => '.digi-product-grid__link',
		'.bdt-ep-product-grid-rating'    => '.digi-product-grid__rating',
		'.bdt-ep-product-grid-badge'     => '.digi-product-grid__badge',
		'.bdt-ep-product-grid-image img' => '.digi-product-grid__image',
		'.bdt-ep-product-grid-image'     => '.digi-product-grid__visual',
		'.bdt-ep-product-grid-title'     => '.digi-product-grid__title',
		'.bdt-ep-product-grid-price'     => '.digi-product-grid__price',
		'.bdt-ep-product-grid-text'      => '.digi-product-grid__text',
		'.bdt-ep-product-grid-content'   => '.digi-product-grid__body',
		'.bdt-ep-product-grid-item'      => '.digi-product-grid__item',
		'.bdt-ep-product-grid'           => '.digi-product-grid__list',
	),
	'defaults'  => array(
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'column_gap' => array(
			'size' => 20,
		),
		'row_gap' => array(
			'size' => 20,
		),
		'show_title' => 'yes',
		'title_tag' => 'h3',
		'show_price' => 'yes',
		'show_time' => 'yes',
		'show_text' => 'yes',
		'show_rating' => 'yes',
		'rating_type' => 'number',
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
		'badge_position' => 'top-right',
		'badge_horizontal_offset' => array(
			'size' => 0,
		),
		'badge_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'badge_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'badge_vertical_offset' => array(
			'size' => 0,
		),
		'badge_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'badge_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'badge_rotate' => array(
			'size' => 0,
		),
		'badge_rotate_tablet' => array(
			'size' => 0,
		),
		'badge_rotate_mobile' => array(
			'size' => 0,
		),
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'rating_number_color' => '#FFCC00',
	),
	'repeaters' => array(
		'product_items' => array(
			'defaults'     => array(
				'image' => array(
					'url' => $placeholder_url,
				),
				'title' => 'product title here',
				'price' => '$204',
				'text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
				'readmore_link' => array(
					'url' => '#',
				),
				'rating_number' => array(
					'size' => 4.5,
				),
				'rating_count' => '(10,678)',
				'time' => '1 hour 10 mins',
				'badge_text' => 'Sale',
			),
			'default_rows' => array( array(
					'title' => 'Pizza',
				), array(
					'title' => 'Burger',
				), array(
					'title' => 'Chicken',
				), array(
					'title' => 'Milkshake',
				), array(
					'title' => 'Ice Tea',
				), array(
					'title' => 'Pasta',
				) ),
		),
	),
	// Los interruptores globales de Element Pack ocultaban partes de todas las fichas.
	'filter'    => static function ( array $out ) {
		$hide = array( 'show_title' => 'title', 'show_price' => 'price', 'show_time' => 'time', 'show_text' => 'text', 'show_image' => 'image' );
		foreach ( $out['product_items'] as $index => $item ) {
			foreach ( $hide as $flag => $key ) {
				if ( 'yes' !== ( $out[ $flag ] ?? 'yes' ) ) {
					unset( $item[ $key ] );
				}
			}
			$item['button_text']   = (string) ( $out['readmore_text'] ?? '' );
			$item['rating_number'] = 'yes' === ( $out['show_rating'] ?? 'yes' ) ? (float) ( is_array( $item['rating_number'] ?? null ) ? ( $item['rating_number']['size'] ?? 0 ) : ( $item['rating_number'] ?? 0 ) ) : 0;
			$item['rating_count']  = (int) preg_replace( '/\D/', '', (string) ( $item['rating_count'] ?? '' ) );
			if ( 'yes' !== ( $out['badge'] ?? '' ) ) {
				$item['badge_text'] = '';
			}
			$out['product_items'][ $index ] = $item;
		}
		foreach ( array_merge( array_keys( $hide ), array( 'show_rating', 'rating_type', 'badge', 'readmore_text', 'readmore_link_to', 'readmore_icon', 'icon_align', 'badge_position', 'image_mask_shape' ) ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
