<?php
/** Element Pack Pro 9.9.1 `bdt-give-goal` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-give-goal' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'form_id' => 0,
		'show_text' => 'yes',
		'show_bar' => 'yes',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['form_id'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'give_goal', array( 'id' => ( $s['form_id'] ?? '' ), 'show_text' => ( $s['show_text'] ?? '' ), 'show_bar' => ( $s['show_bar'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'form_id', 'show_text', 'show_bar' ) );
	},
);
