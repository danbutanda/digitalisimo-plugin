<?php
/** Element Pack Pro 9.9.1 `bdt-google-reviews` → `digitalisimo-google-reviews`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-google-reviews',
	// Clases de Element Pack más usadas en sus selectores: .bdt-google-reviews, .bdt-google-reviews-item, .bdt-navigation-prev, .bdt-navigation-next, .bdt-google-wr, .bdt-rating, .bdt-rating-item, .bdt-google-reviews-desc, .bdt-place-img, .bdt-place-name, .bdt-google-reviews-name, .bdt-google-reviews-date, .bdt-google-icon, .bdt-google-powered
	'classes'   => array(
		'.bdt-google-reviews .bdt-google-reviews-item .bdt-google-reviews-desc' => '.digi-google-reviews__item p',
		'.bdt-google-reviews .bdt-google-reviews-item' => '.digi-google-reviews__item',
		'.bdt-google-reviews'                          => '.digi-google-reviews',
	),
	'defaults'  => array(
		'max_reviews' => 5,
		'cache_reviews' => 'yes',
		'refresh_reviews' => 'day',
		'show_image' => 'yes',
		'show_time' => 'yes',
		'show_name' => 'yes',
		'show_rating' => 'yes',
		'show_excerpt' => 'yes',
		'excerpt_limit' => 200,
		'reviews_lang' => '',
		'carousel_columns' => '1',
		'carousel_columns_tablet' => '1',
		'carousel_columns_mobile' => '1',
		'match_height' => 'yes',
		'place_info_direction' => 'column',
		'place_info_align_items' => 'center',
		'flex_direction' => 'column',
		'thumb_image_direction' => 'left',
		'ep_parallax_effects_disable' => 'no',
		'navigation' => 'arrows',
		'nav_arrows_icon' => '5',
		'both_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'arrows_dots_hide_on_mobile' => 'yes',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'pause_on_hover' => 'yes',
		'loop' => 'yes',
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'google_icon_position' => 'right',
		'place_info_rating_color' => '#e7e7e7',
		'place_info_active_rating_color' => '#FFCC00',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => -60,
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
			'size' => -60,
		),
		'both_cy_position' => array(
			'size' => 30,
		),
	),
	'rename'    => array( 'carousel_columns' => 'columns' ),
	// Element Pack no mostraba un encabezado propio sobre las reseñas.
	'set'       => array( 'heading' => '' ),
	'drop'      => array( 'navigation', 'nav_arrows_icon', 'ep_parallax_effects_disable' ),
);
