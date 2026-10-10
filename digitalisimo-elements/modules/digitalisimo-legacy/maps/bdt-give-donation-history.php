<?php
/** Element Pack Pro 9.9.1 `bdt-give-donation-history` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-give-donation-history' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'form_id' => 'yes',
		'date' => 'yes',
		'amount' => 'yes',
		'header_background' => '#e7ebef',
		'header_color' => '#333',
		'header_border_style' => 'solid',
		'header_border_width' => array(
			'size' => 1,
		),
		'header_border_color' => '#ccc',
		'header_padding' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 1,
			'right' => 2,
			'unit' => 'em',
		),
		'header_alignment' => 'center',
		'cell_border_style' => 'solid',
		'cell_border_width' => array(
			'size' => 1,
		),
		'cell_padding' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 2,
			'right' => 2,
			'unit' => 'em',
		),
		'body_alignment' => 'center',
		'normal_background' => '#fff',
		'normal_border_color' => '#ccc',
		'stripe_background' => '#f5f5f5',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['form_id'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'donation_history', array( 'id' => ( $s['form_id'] ?? '' ), 'donor' => ( $s['donor'] ?? '' ), 'date' => ( $s['date'] ?? '' ), 'amount' => ( $s['amount'] ?? '' ), 'status' => ( $s['status'] ?? '' ), 'payment_method' => ( $s['method'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'form_id', 'date', 'donor', 'amount', 'status', 'method' ) );
	},
);
