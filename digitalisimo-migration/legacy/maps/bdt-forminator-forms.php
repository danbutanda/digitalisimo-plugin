<?php
/** Element Pack Pro 9.9.1 `bdt-forminator-forms` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-custom-radio-checkbox' => '.elementor-shortcode',
		'.bdt-forminator-forms' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'field_bg_color' => '',
		'field_text_color' => '',
		'field_bg__hover_color' => '',
		'hover_field_text_color' => '',
		'focus_field_bg_color' => '',
		'focus_field_text_color' => '',
		'radio_checkbox_size' => array(
			'size' => '15',
			'unit' => 'px',
		),
		'radio_checkbox_color' => '',
		'checkbox_border_color' => '',
		'radio_checkbox_color_checked' => '',
		'button_width_type' => 'custom',
		'button_text_color_normal' => '',
		'button_bg_color_normal' => '',
		'button_bg_color_hover' => '',
		'button_text_color_hover' => '',
		'button_border_color_hover' => '',
		'error_message_text_color' => '',
		'error_message_background_color' => '',
		'confirmation_text_color' => '',
		'confirmation_bg_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['forminator_form'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'forminator_form', array( 'id' => ( $s['forminator_form'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'forminator_form', 'hide_label', 'custom_radio_checkbox', 'button_width_type' ) );
	},
);
