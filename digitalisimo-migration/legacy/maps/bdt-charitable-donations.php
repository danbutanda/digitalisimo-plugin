<?php
/** Element Pack Pro 9.9.1 `bdt-charitable-donations` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-charitable-donations' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'header_background' => '#e7ebef',
		'header_color' => '#333',
		'header_border_style' => 'solid',
		'header_border_width' => array(
			'size' => 1,
		),
		'header_border_color' => '#ccc',
		'header_padding' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 1,
			'right' => 2,
			'unit' => 'em',
		),
		'cell_border_style' => 'solid',
		'cell_border_width' => array(
			'size' => 1,
		),
		'cell_padding' => array(
			'top' => 0.5,
			'bottom' => 0.5,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'normal_background' => '#fff',
		'normal_border_color' => '#ccc',
		'stripe_background' => '#f5f5f5',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'charitable_my_donations', array() );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array() );
	},
);
