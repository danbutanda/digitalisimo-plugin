<?php
/** Element Pack Pro 9.9.1 `bdt-charitable-donors` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-charitable-donors' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'campaign' => 'all',
		'custom_orientation' => 'horizontal',
		'show_name' => 'yes',
		'show_location' => 'yes',
		'show_amount' => 'yes',
		'show_avatar' => 'yes',
		'number' => 12,
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'items_gap' => array(
			'size' => 20,
		),
		'order' => 'DESC',
		'orderby' => 'date',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['campaign'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'charitable_donors', array( 'campaign' => ( $s['campaign'] ?? '' ), 'orderby' => ( $s['orderby'] ?? '' ), 'order' => ( $s['order'] ?? '' ), 'number' => ( $s['number'] ?? '' ), 'show_name' => ( $s['show_name'] ?? '' ), 'show_location' => ( $s['show_location'] ?? '' ), 'show_amount' => ( $s['show_amount'] ?? '' ), 'show_avatar' => ( $s['show_avatar'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'campaign', 'custom_orientation', 'show_name', 'show_location', 'show_amount', 'show_avatar', 'number', 'order', 'orderby' ) );
	},
);
