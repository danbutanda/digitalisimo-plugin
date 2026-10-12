<?php
/** Element Pack Pro 9.9.1 `bdt-layer-slider` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'firstslide' => 1,
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'layerslider', array( 'id' => ( $s['slider_name'] ?? '' ), 'firstslide' => ( $s['firstslide'] ?? '' ) ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'slider_name', 'firstslide' ) );
	},
);
