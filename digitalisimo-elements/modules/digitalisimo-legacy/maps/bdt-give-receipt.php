<?php
/** Element Pack Pro 9.9.1 `bdt-give-receipt` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'error' => 'You are missing the donation id to view this donation receipt.',
		'success' => 'Thank you for your donation.',
		'price' => 'yes',
		'donor' => 'yes',
		'date' => 'yes',
		'method' => 'yes',
		'payment_id' => 'yes',
		'status' => 'yes',
		'company' => 'yes',
		'status_notice' => 'yes',
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
		'normal_background' => '#fff',
		'normal_border_color' => '#ccc',
		'stripe_background' => '#f5f5f5',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'give_receipt', array( 'error' => esc_html( (string) ( $s['error'] ?? '' ) ), 'price' => ( $s['price'] ?? '' ), 'donor' => ( $s['donor'] ?? '' ), 'date' => ( $s['date'] ?? '' ), 'payment_method' => ( $s['method'] ?? '' ), 'payment_id' => ( $s['payment_id'] ?? '' ), 'payment_status' => ( $s['status'] ?? '' ), 'company_name' => ( $s['company'] ?? '' ), 'status_notice' => ( $s['status_notice'] ?? '' ) ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'error', 'success', 'price', 'donor', 'date', 'method', 'payment_id', 'status', 'company', 'status_notice' ) );
	},
);
