<?php
/** Element Pack Pro 9.9.1 `bdt-animated-link` → `digitalisimo-animated-link`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-animated-link',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-animated-link
	'classes'   => array( '.bdt-ep-animated-link' => '.digi-animated-link' ),
	'defaults'  => array(
		'link_style' => 'metis',
		'link_text' => 'Animated Link',
		'link_url' => array(
			'url' => '',
		),
	),
);
