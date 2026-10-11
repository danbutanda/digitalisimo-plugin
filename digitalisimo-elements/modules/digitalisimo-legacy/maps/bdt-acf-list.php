<?php
/** Element Pack Pro 9.9.1 `bdt-acf-list` → `icon-list`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'icon-list',
	// Clases de Element Pack más usadas en sus selectores: .bdt-fancy-list-icon, .bdt-fancy-list, .bdt-fancy-list-wrap, .bdt-fancy-list-number-icon, .bdt-fancy-list-group, .bdt-fancy-list-text, .bdt-fancy-list-img, .bdt-fancy-list-title
	'classes'   => array(),
	'defaults'  => array(
		'layout_style' => 'style-1',
		'columns' => '1',
		'columns_tablet' => '1',
		'columns_mobile' => '1',
		'title_tags' => 'h4',
		'content_position' => 'left',
		'icon_color' => '#242424',
		'right_icon_bg_color' => '#fff',
	),
	// Cada fila del repetidor de ACF es un elemento de la lista de iconos, leído en cada render (`Legacy_Acf`).
	'rename'    => array( 'repeater_field' => 'digitalisimo_acf_field', 'title' => 'digitalisimo_acf_title', 'text' => 'digitalisimo_acf_text', 'image' => 'digitalisimo_acf_image', 'link' => 'digitalisimo_acf_link' ),
);
