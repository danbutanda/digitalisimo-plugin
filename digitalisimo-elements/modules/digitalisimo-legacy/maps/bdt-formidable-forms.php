<?php
/** Element Pack Pro 9.9.1 `bdt-formidable-forms` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-custom-radio-checkbox' => '.elementor-shortcode',
		'.bdt-formidable-forms' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'input_alignment' => '',
		'field_bg_color' => '',
		'field_text_color' => '',
		'focus_field_bg_color' => '',
		'focus_field_text_color' => '',
		'radio_checkbox_size' => array(
			'size' => '15',
			'unit' => 'px',
		),
		'radio_checkbox_color' => '',
		'checkbox_border_color' => '',
		'radio_checkbox_color_checked' => '',
		'button_align' => '',
		'button_width_type' => 'custom',
		'button_width' => array(
			'size' => '100',
			'unit' => 'px',
		),
		'button_bg_color_normal' => '',
		'button_text_color_normal' => '',
		'button_bg_color_hover' => '',
		'button_text_color_hover' => '',
		'button_border_color_hover' => '',
		'error_label_text_color' => '',
		'error_message_text_color' => '',
		'error_message_background_color' => '',
		'confirmation_alignment' => '',
		'confirmation_text_color' => '',
		'confirmation_bg_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['formidable_form'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'formidable', array( 'id' => ( $s['formidable_form'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'formidable_form', 'custom_radio_checkbox', 'button_width_type' ) );
	},
);
