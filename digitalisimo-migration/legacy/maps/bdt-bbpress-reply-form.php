<?php
/** Element Pack Pro 9.9.1 `bdt-bbpress-reply-form` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-inline-block' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'textarea_height' => array(
			'size' => 200,
		),
		'button_text_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = '[bbp-reply-form]';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array() );
	},
);
