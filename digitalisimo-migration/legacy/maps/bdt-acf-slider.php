<?php
/** Element Pack Pro 9.9.1 `bdt-acf-slider` → `digitalisimo-fancy-slider`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-slider',
	// Clases de Element Pack más usadas en sus selectores: .bdt-slider, .bdt-slide-item, .bdt-navigation-prev, .bdt-navigation-next, .bdt-slide-link, .bdt-ep-scroll-to-section, .bdt-slide-title, .bdt-slide-text, .bdt-slide-desc, .bdt-slider-image-wrapper, .bdt-button-icon-align-right, .bdt-button-icon-align-left, .bdt-dots-container
	'classes'   => array(),
	'defaults'  => array(
		'height' => array(
			'size' => 600,
		),
		'origin' => 'center',
		'align' => 'center',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_button' => 'yes',
		'slider_scroll_to_section_icon' => array(
			'value' => 'fas fa-angle-double-down',
			'library' => 'fa-solid',
		),
		'button_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'transition' => 'slide',
		'effect' => 'left',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
		'slider_background_color' => '#14ABF4',
		'overlay_type' => 'none',
		'blend_type' => 'multiply',
		'arrows_color' => '#fff',
		'dots_color' => '#fff',
		'fraction_color' => '#fff',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => 35,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nnx_position_tablet' => array(
			'size' => 0,
		),
		'dots_nnx_position_mobile' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => -30,
		),
		'dots_nny_position_tablet' => array(
			'size' => -30,
		),
		'dots_nny_position_mobile' => array(
			'size' => -30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncx_position_tablet' => array(
			'size' => 0,
		),
		'both_ncx_position_mobile' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_ncy_position_tablet' => array(
			'size' => 40,
		),
		'both_ncy_position_mobile' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => 35,
		),
		'both_cy_position' => array(
			'size' => -55,
		),
		'arrows_fraction_ncx_position' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_fraction_ncy_position' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_fraction_cx_position' => array(
			'size' => 35,
		),
		'arrows_fraction_cy_position' => array(
			'size' => -55,
		),
		'progress_y_position' => array(
			'size' => 15,
		),
	),
	// Cada fila del repetidor de ACF es una diapositiva del deslizador propio, leída en cada render (`Legacy_Acf`).
	'rename'    => array( 'repeater_field' => 'digitalisimo_acf_field', 'title' => 'digitalisimo_acf_title', 'image' => 'digitalisimo_acf_image', 'content' => 'digitalisimo_acf_content', 'link' => 'digitalisimo_acf_link' ),
);
