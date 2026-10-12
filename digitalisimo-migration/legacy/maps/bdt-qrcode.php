<?php
/** Element Pack Pro 9.9.1 `bdt-qrcode` → `digitalisimo-qr-code`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-qr-code',
	// Clases de Element Pack más usadas en sus selectores: .bdt-qrcode
	'classes'   => array( '.bdt-qrcode > *' => '.digi-qr-code__canvas', '.bdt-qrcode' => '.digi-qr-code' ),
	'defaults'  => array(
		'text' => 'http://bdthemes.com',
		'site_link' => '',
		'label_type' => 'text',
		'label' => 'BDTHEMES',
		'image' => array(
			'url' => $placeholder_url,
		),
		'mode' => 2,
		'align' => 'center',
		'size' => array(
			'size' => 400,
		),
		'mSize' => array(
			'size' => 11,
		),
		'mPosX' => array(
			'size' => 50,
		),
		'mPosY' => array(
			'size' => 50,
		),
		'minVersion' => array(
			'size' => 6,
		),
		'ecLevel' => 'H',
		'fill' => '#333333',
		'fontcolor' => '#ff9818',
		'radius' => array(
			'size' => 0,
		),
	),
	'rename'    => array( 'fill' => 'foreground' ),
	'filter'    => static function ( array $out ) {
		$out['size']  = max( 64, min( 1000, (int) ( is_array( $out['size'] ?? null ) ? ( $out['size']['size'] ?? 200 ) : ( $out['size'] ?? 200 ) ) ) );
		$out['label'] = 'text' === ( $out['label_type'] ?? 'text' ) ? (string) ( $out['label'] ?? '' ) : '';
		foreach ( array( 'label_type', 'image', 'mode', 'mSize', 'mPosX', 'mPosY', 'minVersion', 'ecLevel', 'fontcolor', 'radius' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
