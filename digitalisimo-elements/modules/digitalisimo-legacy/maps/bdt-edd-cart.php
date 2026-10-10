<?php
/** Element Pack Pro 9.9.1 `bdt-edd-cart` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-edd-cart' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'cart_action_button_type' => 'icon',
		'cart_action_button_text' => 'Remove',
		'cart_action_button_icon' => array(
			'value' => 'eicon-close',
			'library' => 'solid',
		),
		'checkout_header_color' => '',
		'checkout_header_hover_color' => '',
		'cell_padding' => array(
			'top' => 5,
			'bottom' => 5,
			'left' => 10,
			'right' => 10,
			'unit' => 'px',
		),
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'cart_action_button_text' => array(
			'cart_action_button_type' => 'text',
		),
		'cart_action_button_icon' => array(
			'cart_action_button_type' => 'icon',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = '[download_cart]';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'cart_action_button_type', 'cart_action_button_text', 'cart_action_button_icon' ) );
	},
);
