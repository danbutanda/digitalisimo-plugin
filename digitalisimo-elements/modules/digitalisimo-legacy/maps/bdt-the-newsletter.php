<?php
/** Element Pack Pro 9.9.1 `bdt-the-newsletter` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-the-newsletter' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'the_news_letter_type' => 'minimal',
		'firstname_show' => 'no',
		'lastname_show' => 'no',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'firstname_show' => array(
			'the_news_letter_type' => 'standard',
		),
		'lastname_show' => array(
			'the_news_letter_type' => 'standard',
		),
		'submit_button_full_width' => array(
			'the_news_letter_type' => 'standard',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'newsletter_form', array( 'type' => ( $s['the_news_letter_type'] ?? '' ) ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'the_news_letter_type', 'firstname_show', 'lastname_show', 'submit_button_full_width' ) );
	},
);
