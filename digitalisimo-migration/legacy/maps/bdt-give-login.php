<?php
/** Element Pack Pro 9.9.1 `bdt-give-login` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-give-login' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'give_login_legend_align' => 'left',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'give_login', array( 'login_redirect' => ( is_array( $s['login_url'] ?? null ) ? (string) ( $s['login_url']['url'] ?? '' ) : '' ) ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'login_url' ) );
	},
);
