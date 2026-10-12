<?php
/** Element Pack Pro 9.9.1 `bdt-edd-login` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-edd-login' => '.elementor-shortcode',
	),
	'defaults'  => array(),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = '[edd_login]';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array() );
	},
);
