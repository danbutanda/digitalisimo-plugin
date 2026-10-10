<?php
/** Element Pack Pro 9.9.1 `bdt-charitable-campaigns` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-charitable-campaigns' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'number' => 6,
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'items_gap' => array(
			'size' => 20,
		),
		'button' => 'donate',
		'order' => 'DESC',
		'orderby' => 'post_date',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['campaigns'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'campaigns', array( 'orderby' => ( $s['orderby'] ?? '' ), 'order' => ( $s['order'] ?? '' ), 'number' => ( $s['number'] ?? '' ), 'button' => ( $s['button'] ?? '' ) ) + ( in_array( 'all', (array) ( $s['campaigns'] ?? array() ), true ) ? array() : array( 'id' => implode( ',', (array) ( $s['campaigns'] ?? array() ) ) ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'campaigns', 'number', 'button', 'order', 'orderby', 'match_height' ) );
	},
);
