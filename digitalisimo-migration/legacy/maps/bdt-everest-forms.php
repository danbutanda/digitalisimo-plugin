<?php
/** Element Pack Pro 9.9.1 `bdt-everest-forms` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-custom-radio-checkbox' => '.elementor-shortcode',
		'.bdt-everest-forms' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'box_border' => 'no',
		'border_style' => 'solid',
		'box_border_width' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'box_border_color' => '#252525',
		'box_border_hover_color' => '',
		'ta_box_border' => 'no',
		'ta_border_style' => 'solid',
		'ta_box_border_width' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'ta_box_border_hover_color' => '',
		'check_box_border' => 'no',
		'check_box_border_style' => 'solid',
		'check_box_border_width' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'radio_border' => 'no',
		'radio_border_style' => 'solid',
		'radio_border_width' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'button_box_border' => 'no',
		'button_border_style' => 'solid',
		'button_box_border_width' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'button_box_border_hover_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['everest_form'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'everest_form', array( 'id' => ( $s['everest_form'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'everest_form', 'box_border', 'ta_box_border', 'check_box_border', 'radio_border', 'button_box_border' ) );
	},
);
