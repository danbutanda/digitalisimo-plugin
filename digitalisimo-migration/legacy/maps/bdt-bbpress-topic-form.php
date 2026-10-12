<?php
/** Element Pack Pro 9.9.1 `bdt-bbpress-topic-form` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'show_breadcrumb' => 'yes',
		'textarea_height' => array(
			'size' => 200,
		),
		'button_text_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'bbp-topic-form', ( '' !== (string) ( $s['bbpress_forum_id'] ?? '' ) ? array( 'forum_id' => ( $s['bbpress_forum_id'] ?? '' ) ) : array() ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'bbpress_forum_id', 'show_breadcrumb' ) );
	},
);
