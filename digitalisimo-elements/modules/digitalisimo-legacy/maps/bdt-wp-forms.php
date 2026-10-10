<?php
/** Element Pack Pro 9.9.1 `bdt-wp-forms` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'others_type_input_text_color' => '#666666',
		'textarea_height' => array(
			'size' => 125,
		),
		'input_space' => array(
			'size' => 25,
		),
		'input_border_show' => 'no',
		'button_text_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['contact_form'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'wpforms', array( 'id' => ( $s['contact_form'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'contact_form', 'input_border_show' ) );
	},
);
