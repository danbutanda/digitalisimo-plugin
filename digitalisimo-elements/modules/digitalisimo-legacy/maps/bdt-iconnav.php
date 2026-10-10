<?php
/** Element Pack Pro 9.9.1 `bdt-iconnav` → `digitalisimo-icon-nav`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-icon-nav',
	// Clases de Element Pack más usadas en sus selectores: .bdt-icon-nav, .bdt-icon-nav-icon-wrapper, .bdt-offcanvas, .bdt-icon-nav-container, .bdt-offcanvas-bar, .bdt-icon-nav-branding, .bdt-icon-nav-icon, .bdt-menu-text, .bdt-offcanvas-flip, .bdt-icon-nav-left, .bdt-icon-nav-right, .bdt-icon-nav-vertical, .bdt-active
	'classes'   => array(
		'.bdt-icon-nav .bdt-icon-nav-icon-wrapper .bdt-icon-nav-icon' => '.digi-icon-nav__icon',
		'.bdt-icon-nav .bdt-icon-nav-icon-wrapper' => '.digi-icon-nav__link',
		'.bdt-icon-nav .bdt-icon-nav-container'    => '.digi-icon-nav__rail',
		'.bdt-icon-nav .bdt-icon-nav-branding'     => '.digi-icon-nav__brand',
		'.bdt-icon-nav .bdt-menu-text'             => '.digi-icon-nav__text',
		'.bdt-icon-nav'                            => '.digi-icon-nav',
	),
	'defaults'  => array(
		'navbar' => 0,
		'navbar_level' => 1,
		'offcanvas_animations' => 'slide',
		'offcanvas_close_button' => 'yes',
		'show_branding' => 'yes',
		'brading_space' => array(
			'size' => 30,
		),
		'menu_text' => 'show_as_tooltip',
		'iconnav_width' => array(
			'size' => 48,
		),
		'iconnav_position' => 'left',
		'iconnav_vertical_offset' => array(
			'size' => 0,
		),
		'iconnav_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'iconnav_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'iconnav_horizontal_offset' => array(
			'size' => 0,
		),
		'iconnav_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'iconnav_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'iconnav_top_offset' => array(
			'size' => 80,
		),
		'iconnav_tooltip_spacing' => array(
			'size' => 5,
		),
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'iconnav_icon_size' => array(
			'size' => 16,
		),
	),
	'repeaters' => array(
		'iconnavs' => array(
			'defaults'     => array(
				'iconnav_icon' => array(
					'value' => 'fas fa-home',
					'library' => 'fa-solid',
				),
				'iconnav_title' => 'Iconnav Title',
				'iconnav_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'iconnav_title' => 'Homepage',
					'iconnav_icon' => array(
						'value' => 'fas fa-home',
						'library' => 'fa-solid',
					),
					'iconnav_link' => array(
						'url' => '#',
					),
				), array(
					'iconnav_title' => 'Product',
					'iconnav_icon' => array(
						'value' => 'fas fa-shopping-bag',
						'library' => 'fa-solid',
					),
					'iconnav_link' => array(
						'url' => '#',
					),
				), array(
					'iconnav_title' => 'Support',
					'iconnav_icon' => array(
						'value' => 'fas fa-wrench',
						'library' => 'fa-solid',
					),
					'iconnav_link' => array(
						'url' => '#',
					),
				), array(
					'iconnav_title' => 'Blog',
					'iconnav_icon' => array(
						'value' => 'fas fa-book',
						'library' => 'fa-solid',
					),
					'iconnav_link' => array(
						'url' => '#',
					),
				), array(
					'iconnav_title' => 'About Us',
					'iconnav_icon' => array(
						'value' => 'fas fa-envelope',
						'library' => 'fa-solid',
					),
					'iconnav_link' => array(
						'url' => '#',
					),
				) ),
		),
	),
	'drop'      => array( 'offcanvas_overlay', 'offcanvas_animations', 'offcanvas_flip', 'offcanvas_close_button', 'glassmorphism_effect', 'tooltip_animation', 'tooltip_size' ),
);
