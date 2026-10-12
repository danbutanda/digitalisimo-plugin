<?php
/** Element Pack Pro 9.9.1 `bdt-wc-elements` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'textarea_height' => array(
			'size' => 125,
		),
		'input_space' => array(
			'size' => 25,
		),
		'input_border_show' => 'no',
		'order_table_border_show' => 'no',
		'payment_button_text_color' => '',
		'tracking_input_space' => array(
			'size' => 25,
		),
		'tracking_input_border_show' => 'no',
		'tracking_button_text_color' => '',
		'cart_table_border_show' => 'no',
		'cart_input_border_show' => 'no',
		'cart_button_text_color' => '',
		'cart_checkout_button_text_color' => '',
		'_ep_wc_rating_row' => '1',
		'product_rating_star_size' => array(
			'unit' => 'em',
		),
		'_ep_wc_product_meta_stack' => '1',
		'add_to_cart_text_color' => '#fff',
		'qty_fields_border_radius' => array(
			'top' => '3',
			'right' => '3',
			'bottom' => '3',
			'left' => '3',
			'isLinked' => false,
		),
		'tabs_content_reviews_star_size' => array(
			'unit' => 'em',
		),
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'product_id' => array(
			'element' => array( 'product_page' ),
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$element   = (string) ( $s['element'] ?? '' );
		$shortcode = '';
		if ( in_array( $element, array( 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_order_tracking' ), true ) ) {
			$shortcode = '[' . $element . ' ]';
		} elseif ( 'product_page' === $element && absint( $s['product_id'] ?? 0 ) ) {
			$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'product_page', array( 'id' => absint( $s['product_id'] ) ) );
		}
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'element', 'product_id', 'input_border_show', 'order_table_border_show', 'tracking_input_border_show', 'cart_table_border_show', 'cart_input_border_show' ) );
	},
);
