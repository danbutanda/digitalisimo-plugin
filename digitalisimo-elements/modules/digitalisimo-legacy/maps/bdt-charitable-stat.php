<?php
/** Element Pack Pro 9.9.1 `bdt-charitable-stat` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-charitable-stat' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'display' => 'total',
		'goal' => 1000,
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'goal' => array(
			'display' => 'progress',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['campaign'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'charitable_stat', array( 'campaigns' => implode( ',', (array) ( $s['campaign'] ?? array() ) ), 'display' => ( $s['display'] ?? '' ), 'goal' => ( $s['goal'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'campaign', 'display', 'goal' ) );
	},
);
