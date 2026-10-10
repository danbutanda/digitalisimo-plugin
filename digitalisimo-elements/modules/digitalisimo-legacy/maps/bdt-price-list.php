<?php
/** Element Pack Pro 9.9.1 `bdt-price-list` → `price-list`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'price-list',
	// Clases de Element Pack más usadas en sus selectores: .bdt-price-list-item, .bdt-price-list-cart-icon, .bdt-price-list-old-price, .bdt-price-list-price, .bdt-price-list-counter, .bdt-price-list-image, .bdt-price-list-badge, .bdt-price-list-separator, .bdt-price-list-title, .bdt-price-list, .bdt-price-list-description, .bdt-price-list-header
	'classes'   => array(
		'.bdt-price-list-item:hover .bdt-price-list-price' => '.elementor-price-list-item:hover .elementor-price-list-price',
		'.bdt-price-list-separator' => '.elementor-price-list-separator',
		'.bdt-price-list-price'     => '.elementor-price-list-price',
		'.bdt-price-list-image'     => '.elementor-price-list-image',
		'.bdt-price-list-title'     => '.elementor-price-list-title',
		'.bdt-price-list-description' => '.elementor-price-list-description',
		'.bdt-price-list-item'      => '.elementor-price-list-item',
		'.bdt-price-list'           => '.elementor-price-list',
	),
	'defaults'  => array(
		'thumbnail_size_size' => 'thumbnail',
		'image_hide_on' => array( 'mobile' ),
		'vertical_align' => 'middle',
		'columns' => '1',
		'columns_tablet' => '1',
		'columns_mobile' => '1',
		'cart_icon' => array(
			'value' => 'fas fa-cart-arrow-down',
			'library' => 'fa-solid',
		),
		'cart_spacing' => array(
			'size' => 14,
		),
		'old_price_spacing' => array(
			'size' => 10,
		),
		'badge_h_spacing' => array(
			'size' => 0,
		),
		'badge_h_spacing_tablet' => array(
			'size' => 0,
		),
		'badge_h_spacing_mobile' => array(
			'size' => 0,
		),
		'badge_v_spacing' => array(
			'size' => 0,
		),
		'badge_v_spacing_tablet' => array(
			'size' => 0,
		),
		'badge_v_spacing_mobile' => array(
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
		'price_color' => '#ffffff',
		'price_background_color' => '#4AB8F8',
		'price_border_radius' => array(
			'top' => '50',
			'right' => '50',
			'bottom' => '50',
			'left' => '50',
			'unit' => 'px',
		),
		'price_width' => array(
			'size' => 50,
		),
		'old_price_border_radius' => array(
			'top' => '50',
			'right' => '50',
			'bottom' => '50',
			'left' => '50',
			'unit' => 'px',
		),
		'separator_style' => 'dashed',
		'separator_weight' => array(
			'size' => 1,
		),
		'image_size' => array(
			'size' => 60,
		),
		'image_spacing' => array(
			'size' => 20,
		),
		'badge_border_radius' => array(
			'top' => '50',
			'right' => '50',
			'bottom' => '50',
			'left' => '50',
			'unit' => 'px',
		),
	),
	'repeaters' => array(
		'price_list' => array(
			'defaults'     => array(
				'title' => 'First item on the list',
				'image' => array(),
				'link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'title' => 'First item on the list',
					'price' => '$20',
					'link' => array(
						'url' => '#',
					),
				), array(
					'title' => 'Second item on the list',
					'price' => '$9',
					'link' => array(
						'url' => '#',
					),
				), array(
					'title' => 'Third item on the list',
					'price' => '$32',
					'link' => array(
						'url' => '#',
					),
				) ),
		),
	),
	'rename'    => array( 'thumbnail_size_size' => 'image_size_size', 'thumbnail_size_custom_dimension' => 'image_size_custom_dimension' ),
	'drop'      => array( 'image_hide_on', 'item_counter', 'show_cart', 'cart_icon', 'show_old_price', 'show_badge' ),
);
