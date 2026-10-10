<?php
/** Element Pack Pro 9.9.1 `bdt-icon-mobile-menu` → `digitalisimo-icon-mobile-menu`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-icon-mobile-menu',
	// Clases de Element Pack más usadas en sus selectores: .bdt-icon-mobile-menu-wrap, .bdt-icon-mobile-menu-link, .bdt-icon-mobile-menu, .bdt-text-mobile-menu, .bdt-title
	'classes'   => array(
		'.bdt-icon-mobile-menu-wrap span.bdt-text-mobile-menu' => '.digi-icon-mobile-menu__text',
		'.bdt-icon-mobile-menu-wrap span.bdt-icon-mobile-menu' => '.digi-icon-mobile-menu__icon',
		'.bdt-icon-mobile-menu-wrap .bdt-icon-mobile-menu-link' => '.digi-icon-mobile-menu__entry',
		'.bdt-icon-mobile-menu-link span.bdt-icon-mobile-menu' => '.digi-icon-mobile-menu__icon',
		'.bdt-icon-mobile-menu-link span.bdt-text-mobile-menu' => '.digi-icon-mobile-menu__text',
		'.bdt-icon-mobile-menu-wrap'                            => '.digi-icon-mobile-menu',
	),
	'defaults'  => array(
		'menu_style' => 'style-1',
		'menu_tooltip' => 'yes',
		'menu_tooltip_placement' => 'top',
		'menu_tooltip_animation' => 'shift-toward',
		'menu_tooltip_x_offset' => array(
			'size' => 0,
		),
		'menu_tooltip_y_offset' => array(
			'size' => 0,
		),
		'menu_tooltip_text_align' => 'center',
	),
	'repeaters' => array(
		'menu_items' => array(
			'defaults'     => array(
				'link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'menu_text' => 'Home',
					'menu_icon' => array(
						'value' => 'fas fa-home',
						'library' => 'fa-solid',
					),
				), array(
					'menu_text' => 'Cart',
					'menu_icon' => array(
						'value' => 'fas fa-shopping-cart',
						'library' => 'fa-solid',
					),
				), array(
					'menu_text' => 'Account',
					'menu_icon' => array(
						'value' => 'fas fa-user',
						'library' => 'fa-solid',
					),
				) ),
		),
	),
	'drop'      => array( 'menu_tooltip_placement', 'menu_tooltip_animation', 'menu_tooltip_arrow', 'menu_tooltip_trigger' ),
);
