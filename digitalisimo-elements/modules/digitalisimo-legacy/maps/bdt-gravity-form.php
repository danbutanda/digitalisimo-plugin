<?php
/** Element Pack Pro 9.9.1 `bdt-gravity-form` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'gravity_form' => '0',
		'title_hide' => 'yes',
		'description_hide' => 'yes',
		'show_sub_label' => 'yes',
		'input_alignment' => '',
		'section_field_border_type' => 'solid',
		'section_field_border_height' => array(
			'size' => 1,
		),
		'radio_checkbox_size' => array(
			'unit' => 'px',
			'size' => 20,
		),
		'button_align' => '',
		'button_width_type' => 'custom',
		'button_width' => array(
			'size' => '100',
			'unit' => 'px',
		),
		'validation_error_field_input_border_width' => 1,
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['gravity_form'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'gravityform', array( 'id' => (int) ( $s['gravity_form'] ?? '' ), 'title' => 'yes' === ( $s['title_hide'] ?? '' ) ? 'true' : 'false', 'description' => '' !== (string) ( $s['description_hide'] ?? '' ) ? 'true' : 'false', 'ajax' => '' !== (string) ( $s['form_ajax'] ?? '' ) ? 'true' : 'false', 'tabindex' => '0' ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'gravity_form', 'title_hide', 'description_hide', 'form_ajax', 'custom_radio_checkbox', 'button_width_type' ) );
	},
);
