<?php
/** Element Pack Pro 9.9.1 `bdt-give-totals` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-give-totals' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'total_goal' => '1000',
		'message' => 'Hey! We\'ve raised {total} of the {total_goal} we are trying to raise for this campaign!',
		'link' => array(
			'url' => 'https://example.org',
			'is_external' => false,
			'nofollow' => false,
		),
		'link_text' => 'Donate Now',
		'show_progress' => 'yes',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['forms'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'give_totals', array( 'ids' => ( $s['forms'] ?? '' ), 'total_goal' => ( $s['total_goal'] ?? '' ), 'message' => esc_html( (string) ( $s['message'] ?? '' ) ), 'link' => esc_url( ( is_array( $s['link'] ?? null ) ? (string) ( $s['link']['url'] ?? '' ) : '' ) ), 'link_text' => esc_html( (string) ( $s['link_text'] ?? '' ) ), 'progress_bar' => ( $s['show_progress'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'forms', 'total_goal', 'message', 'link', 'link_text', 'show_progress' ) );
	},
);
