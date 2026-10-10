<?php
/** Element Pack Pro 9.9.1 `bdt-give-form` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-give-form' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'form_id' => 0,
		'display_style' => 'onpage',
		'continue_button_title' => 'Continue to Donate',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'continue_button_title' => array(
			'display_style' => 'button',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['form_id'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'give_form', array( 'id' => ( $s['form_id'] ?? '' ), 'display_style' => ( $s['display_style'] ?? '' ), 'continue_button_title' => ( $s['continue_button_title'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'form_id', 'display_style', 'continue_button_title' ) );
	},
);
