<?php
/** Element Pack Pro 9.9.1 `bdt-edd-checkout` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-edd-checkout' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'checkout_action_button_type' => 'icon',
		'checkout_action_button_text' => 'Remove',
		'checkout_action_button_icon' => array(
			'value' => 'eicon-close',
			'library' => 'solid',
		),
		'checkout_header_color' => '',
		'checkout_header_hover_color' => '',
		'cell_padding' => array(
			'top' => 0.5,
			'bottom' => 0.5,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'checkout_action_button_text' => array(
			'checkout_action_button_type' => 'text',
		),
		'checkout_action_button_icon' => array(
			'checkout_action_button_type' => 'icon',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = '[download_checkout]';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'checkout_action_button_type', 'checkout_action_button_text', 'checkout_action_button_icon' ) );
	},
);
