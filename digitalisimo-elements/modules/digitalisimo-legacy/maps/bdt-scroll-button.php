<?php
/** Element Pack Pro 9.9.1 `bdt-scroll-button` → `digitalisimo-scroll-button`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-scroll-button',
	'classes'   => array(
		'.bdt-scroll-button-wrapper' => '.digi-scroll-button',
		'.bdt-scroll-button'         => '.digi-scroll-button__link',
	),
	'defaults'  => array(
		'scroll_button_text' => 'Scroll Up',
		'section_id' => 'my-header',
		'scroll_button_position' => '',
		'scroll_button_align' => 'center',
		'button_icon' => array(
			'value' => 'fas fa-angle-up',
			'library' => 'fa-solid',
		),
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'fancy_animation' => 'shadow-pulse',
	),
	// La animación decorativa del contenedor, la animación al pasar el cursor y el ocultar hasta desplazar no se trasladan.
	'drop'      => array( 'hide_on_before_scrolling', 'scroll_button_hover_animation', 'show_fancy_animation', 'fancy_animation' ),
);
