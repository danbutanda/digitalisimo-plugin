<?php
/** Element Pack Pro 9.9.1 `bdt-we-form` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'we_form' => 0,
		'large_field_width' => array(
			'unit' => '%',
			'size' => 100,
		),
		'button_width' => array(
			'unit' => '%',
			'size' => 100,
		),
		'submit_color' => '',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$attributes = array( 'id' => $s['we_form'] ?? '' );
		foreach ( explode( "\n", (string) ( $s['custom_attributes'] ?? '' ) ) as $line ) {
			$pair = explode( '|', $line, 2 );
			$key  = trim( $pair[0] );
			if ( '' === $key || ! preg_match( '/^[A-Za-z][A-Za-z0-9_:-]*$/', $key ) || 0 === stripos( $key, 'on' ) || in_array( strtolower( $key ), array( 'style', 'class' ), true ) ) {
				continue;
			}
			$attributes[ $key ] = trim( $pair[1] ?? '' );
		}
		$shortcode = '' !== (string) ( $s['we_form'] ?? '' ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'weforms', $attributes ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'we_form', 'custom_attributes', 'submit_btn_width' ) );
	},
);
