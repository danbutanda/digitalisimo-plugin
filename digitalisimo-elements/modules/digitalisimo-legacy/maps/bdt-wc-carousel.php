<?php
/** Element Pack Pro 9.9.1 `bdt-wc-carousel` → `woocommerce-products`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'woocommerce-products',
	// Clases de Element Pack más usadas en sus selectores: .bdt-wc-carousel, .bdt-navigation-prev, .bdt-navigation-next, .bdt-dots-container, .bdt-item-skin-hidie, .bdt-quick-view, .bdt-products-skin-price, .bdt-wc-add-to-cart, .bdt-wc-carousel-item, .bdt-badge, .bdt-wc-carousel-title, .bdt-products-skin-add-to-cart, .bdt-products-skin-title, .bdt-wc-carousel-desc
	'classes'   => array(),
	'defaults'  => array(
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 35,
		),
		'item_gap_tablet' => array(
			'size' => 20,
		),
		'item_gap_mobile' => array(
			'size' => 20,
		),
		'image_size' => 'medium',
		'show_badge' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'div',
		'show_rating' => 'yes',
		'show_price' => 'yes',
		'show_cart' => 'yes',
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'skin' => 'carousel',
		'coverflow_rotate' => array(
			'size' => 50,
		),
		'coverflow_stretch' => array(
			'size' => 0,
		),
		'coverflow_modifier' => array(
			'size' => 1,
		),
		'coverflow_depth' => array(
			'size' => 100,
		),
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
		'item_shadow_padding' => array(
			'size' => 10,
		),
		'badge_position' => 'left',
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => -60,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nnx_position_tablet' => array(
			'size' => 0,
		),
		'dots_nnx_position_mobile' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'dots_nny_position_tablet' => array(
			'size' => 30,
		),
		'dots_nny_position_mobile' => array(
			'size' => 30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncx_position_tablet' => array(
			'size' => 0,
		),
		'both_ncx_position_mobile' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_ncy_position_tablet' => array(
			'size' => 40,
		),
		'both_ncy_position_mobile' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => -60,
		),
		'both_cy_position' => array(
			'size' => 30,
		),
		'arrows_fraction_ncx_position' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_fraction_ncy_position' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_fraction_cx_position' => array(
			'size' => -60,
		),
		'arrows_fraction_cy_position' => array(
			'size' => 30,
		),
		'progress_y_position' => array(
			'size' => 15,
		),
	),
	// Los productos del carrusel se muestran como rejilla de PRO Elements, sin desplazamiento.
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::wc_products( $out, array( 'per_page' => array( 'posts_per_page', 8 ), 'columns' => array( 'columns', '3' ), 'image' => 'yes', 'title' => array( 'show_title', 'yes' ), 'title_tag' => array( 'title_tags', 'div' ), 'rating' => array( 'show_rating', 'yes' ), 'price' => array( 'show_price', 'yes' ), 'cart' => array( 'show_cart', 'yes' ), 'badge' => array( 'show_badge', 'yes' ) ) );
	},
);
