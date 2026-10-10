<?php
/** Element Pack Pro 9.9.1 `bdt-instagram-feed` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'columns_gap' => array(
			'size' => 10,
		),
		'imagepadding' => array(
			'size' => 20,
		),
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'instagram-feed', array() );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array() );
	},
);
