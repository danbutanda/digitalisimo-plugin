<?php
/** Element Pack Pro 9.9.1 `bdt-bbpress-single-view` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'bbpress_specific_view' => 'popular',
		'show_breadcrumb' => 'yes',
		'button_text_color' => '',
		'pagination_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['bbpress_specific_view'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'bbp-single-view', array( 'id' => ( $s['bbpress_specific_view'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'bbpress_specific_view', 'show_breadcrumb' ) );
	},
);
