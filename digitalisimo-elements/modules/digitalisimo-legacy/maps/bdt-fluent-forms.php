<?php
/** Element Pack Pro 9.9.1 `bdt-fluent-forms` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-custom-radio-checkbox' => '.elementor-shortcode',
		'.bdt-fluent-forms' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'input_alignment' => '',
		'field_bg_color' => '',
		'field_text_color' => '',
		'field_bg_color_focus' => '',
		'radio_checkbox_size' => array(
			'size' => '15',
			'unit' => 'px',
		),
		'radio_checkbox_color' => '',
		'checkbox_border_color' => '',
		'radio_checkbox_color_checked' => '',
		'section_break_label_color' => '',
		'section_break_description_color' => '',
		'address_line_label_color' => '',
		'button_align' => '',
		'button_width_type' => 'custom',
		'button_bg_color_normal' => '#409EFF',
		'button_text_color_normal' => '#ffffff',
		'button_bg_color_hover' => '',
		'button_text_color_hover' => '',
		'button_border_color_hover' => '',
		'show_label' => 'yes',
		'show_progressbar' => 'yes',
		'error_message_text_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'button_align' => array(
			'button_width_type' => 'custom',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['fluent_form'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'fluentform', array( 'id' => ( $s['fluent_form'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'fluent_form', 'custom_radio_checkbox', 'section_break_alignment', 'button_align', 'button_width_type', 'show_label', 'show_progressbar' ) );
	},
);
