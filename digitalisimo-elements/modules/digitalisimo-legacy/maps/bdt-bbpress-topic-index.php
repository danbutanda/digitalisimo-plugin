<?php
/** Element Pack Pro 9.9.1 `bdt-bbpress-topic-index` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'show_search_form' => 'yes',
		'show_breadcrumb' => 'yes',
		'button_text_color' => '',
		'pagination_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = '[bbp-topic-index]';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'show_search_form', 'show_breadcrumb' ) );
	},
);
