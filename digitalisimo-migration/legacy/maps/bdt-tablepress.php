<?php
/** Element Pack Pro 9.9.1 `bdt-tablepress` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-tablepress' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'table_id' => '0',
		'header_align' => 'center',
		'body_align' => 'center',
		'table_responsive' => '0',
		'table_border_style' => 'solid',
		'table_border_width' => array(
			'min' => 0,
			'max' => 20,
			'size' => 1,
		),
		'table_border_color' => '#ccc',
		'header_background' => '#dfe3e6',
		'header_active_background' => '#ccd3d8',
		'header_color' => '#333',
		'header_border_style' => 'solid',
		'header_border_width' => array(
			'min' => 0,
			'max' => 20,
			'size' => 1,
		),
		'header_border_color' => '#ccc',
		'header_padding' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'cell_border_style' => 'solid',
		'cell_border_width' => array(
			'min' => 0,
			'max' => 20,
			'size' => 1,
		),
		'cell_padding' => array(
			'top' => 0.5,
			'bottom' => 0.5,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'normal_background' => '#fff',
		'normal_border_color' => '#ccc',
		'stripe_background' => '#f7f7f7',
		'stripe_border_color' => '#ccc',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['table_id'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'table', array( 'id' => ( $s['table_id'] ?? '' ), 'responsive' => class_exists( 'TablePress_Responsive_Tables' ) ? ( $s['table_responsive'] ?? '' ) : '' ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'table_id', 'table_responsive' ) );
	},
);
