<?php
/** Element Pack Pro 9.9.1 `bdt-wc-mini-cart` → `woocommerce-menu-cart`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'woocommerce-menu-cart',
	// Clases de Element Pack más usadas en sus selectores: .bdt-offcanvas, .bdt-mini-cart-product-item, .bdt-mini-cart-footer-buttons, .bdt-mini-cart-wrapper, .bdt-offcanvas-close, .bdt-button-view-cart, .bdt-button-checkout, .bdt-mini-cart-product-remove, .bdt-offcanvas-bar, .bdt-mini-cart-button-icon, .bdt-mini-cart-product-name, .bdt-button-text, .bdt-offcanvas-custom-content-before, .bdt-offcanvas-custom-content-after
	'classes'   => array(),
	'defaults'  => array(
		'show_price_amount' => 'yes',
		'show_cart_icon' => 'yes',
		'icon' => 'cart-medium',
		'show_cart_badge' => 'yes',
		'mini_cart_align' => 'left',
		'mini_cart_icon_indent' => array(
			'size' => 8,
		),
		'custom_widget_cart_title' => 'Shopping Cart',
		'offcanvas_animations' => 'slide',
		'offcanvas_close_button' => 'yes',
		'offcanvas_bg_close' => 'yes',
		'offcanvas_esc_close' => 'yes',
		'custom_content_before' => 'This is your custom content for before of your offcanvas.',
		'custom_content_after' => 'This is your custom content for after of your offcanvas.',
		'pc_viewcart_text_color' => '',
		'pc_checkout_text_color' => '',
		'pc_remove_text_color' => '',
	),
	// Carrito lateral de PRO Elements: Element Pack lo abría por la izquierda salvo con «flip». El título, el
	// contenido propio antes y después y las animaciones del panel no se trasladan.
	'filter'    => static function ( array $out ) {
		$set = array(
			'cart_type'               => 'side-cart',
			'open_cart'               => 'click',
			'side_cart_alignment'     => 'yes' === ( $out['offcanvas_flip'] ?? '' ) ? 'end' : 'start',
			'items_indicator'         => 'yes' === ( $out['show_cart_badge'] ?? 'yes' ) ? 'bubble' : 'none',
			'show_subtotal'           => 'yes' === ( $out['show_price_amount'] ?? 'yes' ) ? 'yes' : '',
			'alignment'               => (string) ( $out['mini_cart_align'] ?? 'left' ),
			'close_cart_button_show'  => 'yes' === ( $out['offcanvas_close_button'] ?? 'yes' ) ? 'yes' : '',
			'automatically_open_cart' => 'yes' === ( $out['trigger_on_cart_update'] ?? '' ) ? 'yes' : 'no',
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(show_price_amount|show_cart_badge|mini_cart_align|offcanvas_.*|trigger_on_cart_update|custom_widget_cart_title|custom_content_.*)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
