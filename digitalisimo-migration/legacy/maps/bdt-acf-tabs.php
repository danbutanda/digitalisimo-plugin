<?php
/** Element Pack Pro 9.9.1 `bdt-acf-tabs` → `digitalisimo-fancy-tabs`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-tabs',
	// Clases de Element Pack más usadas en sus selectores: .bdt-tab, .bdt-tabs-item-title, .bdt-tabs-item, .bdt-tabs, .bdt-active, .bdt-tab-title-icon, .bdt-switcher-wrapper, .bdt-switcher-item-content, .bdt-grid-stack, .bdt-tab-wrapper, .bdt-tab-sub-title, .bdt-tabs-wrap-inside, .bdt-grid, .bdt-tab-title-icon-wrapper
	'classes'   => array(),
	'defaults'  => array(
		'tab_layout' => 'default',
		'content_spacing' => array(
			'size' => 20,
		),
		'tab_transition' => '',
		'duration' => array(
			'size' => 200,
		),
		'media' => 960,
		'nav_sticky_offset' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'swiping_on_mobile' => 'yes',
		'active_hash' => 'no',
		'hash_top_offset' => array(
			'unit' => 'px',
			'size' => 70,
		),
		'hash_scrollspy_time' => array(
			'unit' => 'px',
			'size' => 1500,
		),
		'tabs_match_height' => 'yes',
		'section_bg_anim' => 'none',
		'icon_space' => array(
			'size' => 8,
		),
	),
	'repeaters' => array(
		'section_bg_list' => array(
			'defaults'     => array(
				'section_bg' => array(
					'url' => $placeholder_url,
				),
			),
			'default_rows' => array(),
		),
	),
	// Las pestañas salen del repetidor de ACF en cada render (`Legacy_Acf`); el documento sólo guarda las claves de los campos.
	'rename'    => array( 'field' => 'digitalisimo_acf_field', 'title' => 'digitalisimo_acf_title', 'sub_title' => 'digitalisimo_acf_sub_title', 'content' => 'digitalisimo_acf_content' ),
);
