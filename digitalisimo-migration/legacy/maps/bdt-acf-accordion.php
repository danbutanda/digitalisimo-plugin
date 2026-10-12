<?php
/** Element Pack Pro 9.9.1 `bdt-acf-accordion` → `digitalisimo-accordion`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-accordion',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-accordion-item, .bdt-ep-accordion-icon, .bdt-ep-accordion-title, .bdt-open, .bdt-ep-accordion-custom-icon, .bdt-ep-accordion-content, .bdt-ep-accordion, .bdt-ep-title-text, .bdt-flex-align-left, .bdt-flex-align-right
	'classes'   => array(),
	'defaults'  => array(
		'title_html_tag' => 'div',
		'accordion_icon' => array(
			'value' => 'fas fa-plus',
			'library' => 'fa-solid',
		),
		'accordion_active_icon' => array(
			'value' => 'fas fa-minus',
			'library' => 'fa-solid',
		),
		'collapsible' => 'yes',
		'active_hash' => 'no',
		'active_scrollspy' => 'no',
		'hash_top_offset' => array(
			'unit' => 'px',
			'size' => 70,
		),
		'hash_scrollspy_time' => array(
			'unit' => 'px',
			'size' => 1000,
		),
		'accordion_animation' => 'yes',
		'duration' => 200,
		'transition' => 'ease-in-out',
		'custom_transition' => 'cubic-bezier(0.4, 0, 0.2, 1)',
		'item_spacing' => array(
			'size' => 2,
		),
		'title_alignment' => 'flex-start',
		'icon_align' => 'right',
	),
	// Los elementos salen del repetidor de ACF en cada render (`Legacy_Acf`); el documento sólo guarda las claves de los campos.
	'rename'    => array( 'field' => 'digitalisimo_acf_field', 'title' => 'digitalisimo_acf_title', 'content' => 'digitalisimo_acf_content', 'always_active_all_items' => 'open_all_initially' ),
	'values'    => array(
		'title_html_tag' => array( 'div' => 'span', 'p' => 'span', 'span' => 'span', 'h1' => 'h2' ),
	),
);
