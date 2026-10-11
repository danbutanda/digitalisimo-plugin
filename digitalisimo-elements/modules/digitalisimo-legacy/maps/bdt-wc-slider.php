<?php
/** Element Pack Pro 9.9.1 `bdt-wc-slider` → `woocommerce-products`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'woocommerce-products',
	// Clases de Element Pack más usadas en sus selectores: .bdt-wc-slider, .bdt-slideshow-items, .bdt-wc-slider-readmore, .bdt-navigation-prev, .bdt-navigation-next, .bdt-wc-add-to-cart, .bdt-wc-slider-price, .bdt-slider-skin-price, .bdt-wc-slider-title, .bdt-badge, .bdt-wc-slider-text, .bdt-dotnav, .bdt-active, .bdt-slideshow
	'classes'   => array(),
	'defaults'  => array(
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'text_align' => 'left',
		'vertical_align' => 'middle',
		'image_size' => 'full',
		'show_price' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_rating' => 'yes',
		'excerpt_length' => 25,
		'show_cart' => 'yes',
		'show_readmore' => 'yes',
		'show_badge' => 'yes',
		'readmore_text' => 'Read More',
		'readmore_icon_align' => 'right',
		'readmore_icon_indent' => array(
			'size' => 8,
		),
		'navigation' => 'arrows',
		'nav_arrows_icon' => '0',
		'both_position' => 'center',
		'arrows_position' => 'bottom-right',
		'dots_position' => 'bottom-center',
		'hide_arrow_on_mobile' => 'yes',
		'slider_animations' => 'slide',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'badge_position' => 'left',
		'arrows_ncx_position' => array(
			'size' => -30,
		),
		'arrows_ncy_position' => array(
			'size' => -30,
		),
		'arrows_acx_position' => array(
			'size' => 0,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => 20,
		),
		'both_cy_position' => array(
			'size' => -40,
		),
	),
	// Un producto por fila en la rejilla de PRO Elements; el pase automático y la navegación no se trasladan.
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::wc_products( $out, array( 'per_page' => array( 'posts_per_page', 3 ), 'columns' => '1', 'image' => 'yes', 'title' => array( 'show_title', 'yes' ), 'title_tag' => array( 'title_tags', 'h2' ), 'excerpt' => array( 'show_text', '' ), 'excerpt_length' => array( 'excerpt_length', 25 ), 'excerpt_style' => 'summary', 'readmore' => array( 'show_readmore', 'yes' ), 'readmore_text' => array( 'readmore_text', 'Read More' ), 'rating' => array( 'show_rating', 'yes' ), 'price' => array( 'show_price', 'yes' ), 'cart' => array( 'show_cart', 'yes' ), 'badge' => array( 'show_badge', 'yes' ) ) );
	},
);
