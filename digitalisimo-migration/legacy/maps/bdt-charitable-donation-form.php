<?php
/** Element Pack Pro 9.9.1 `bdt-charitable-donation-form` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-charitable-donation-form' => '.elementor-shortcode',
	),
	'defaults'  => array(),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['form_id'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'charitable_donation_form', array( 'campaign_id' => ( $s['form_id'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'form_id' ) );
	},
);
