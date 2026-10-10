<?php
/** Element Pack Pro 9.9.1 `bdt-easy-digital-profile-editor` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'fieldset_border_style' => 'solid',
		'others_type_input_text_color' => '#666666',
		'textarea_height' => array(
			'size' => 125,
		),
		'input_space' => array(
			'size' => 25,
		),
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = '[edd_profile_editor]';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'input_border_show' ) );
	},
);
