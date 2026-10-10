<?php
/** Element Pack Pro 9.9.1 `bdt-charitable-login` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-charitable-login' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'logged_in_message' => 'You are already logged in!',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'charitable_login', array( 'logged_in_message' => '' !== (string) ( $s['logged_in_message'] ?? '' ) ? ( $s['logged_in_message'] ?? '' ) : 'You are already logged in!', 'registration_link_text' => '' !== (string) ( $s['registration_link_text'] ?? '' ) ? ( $s['registration_link_text'] ?? '' ) : 'Register', 'redirect' => ( is_array( $s['redirect'] ?? null ) ? (string) ( $s['redirect']['url'] ?? '' ) : '' ) ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'logged_in_message', 'registration_link_text', 'redirect' ) );
	},
);
