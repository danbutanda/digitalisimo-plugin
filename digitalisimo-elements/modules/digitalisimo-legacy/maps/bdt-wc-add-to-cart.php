<?php
/** Element Pack Pro 9.9.1 `bdt-wc-add-to-cart` → `wc-add-to-cart`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'wc-add-to-cart',
	// Clases de Element Pack más usadas en sus selectores: 
	'classes'   => array(),
	'defaults'  => array(
		'product_id' => array( '0' ),
		'quantity' => 1,
		'button_type' => '',
		'text' => 'Add to Cart',
		'link' => array(
			'url' => '#',
		),
		'align' => '',
		'size' => 'sm',
		'view' => 'traditional',
		'button_css_id' => '',
		'icon_align' => 'row-reverse',
		'button_text_color' => '#fff',
		'background_color' => '#1E87F0',
	),
	// Element Pack guarda el producto como lista de un elemento; el botón de PRO Elements espera su ID.
	'filter'    => static function ( array $out ) {
		$ids               = array_values( array_filter( array_map( 'strval', (array) ( $out['product_id'] ?? array() ) ), static function ( $id ) { return '' !== $id && '0' !== $id; } ) );
		$out['product_id'] = $ids ? $ids[0] : '';
		return $out;
	},
);
