<?php
/**
 * Prime Slider Pro 4.7.0 `prime-slider-material` (Material, familia F) → `loop-carousel` (respaldo `posts`).
 *
 * Carrusel de varias entradas por vista: el Loop Carousel de PRO con tantas diapositivas por vista como columnas tenía el origen y una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Se pierde el efecto Material You (UI Initiative, no redistribuible).
 *
 * Origen: P/modules/material/widgets/material.php (controles :70-260; 1 por vista en móvil y 2 desde la tableta, :1234-1250) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'F',
	'plugin'   => 'pro',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Carrusel de varias entradas por vista: el Loop Carousel de PRO con tantas diapositivas por vista como columnas tenía el origen y una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Se pierde el efecto Material You (UI Initiative, no redistribuible).',
	'dynamic'  => array(
		'kind'       => 'posts',
		'engine'     => 'swiper',
		'per_view'   => 'columns',
		'columns'    => array( 2, 2, 1 ),
		'gap'        => array( 20, 20, 0 ),
		'height'     => 'slider_height',
		'min_height' => 420,
		'limit'      => 5,
		'nav'        => array( 'arrows' => 'show_navigation', 'dots' => false ),
		'parts'      => array(
			'title' => array( 'show_title', 'title_tags', 'h3' ),
			'terms' => 'show_category',
			'meta'  => array( 'author' => array( 'show_author' ), 'date' => array( 'show_date' ) ),
		),
		'colors'     => array(
			'title'     => array( 'title_color' ),
			'text'      => array( 'excerpt_color', 'text_color' ),
			'meta'      => array( 'meta_color', 'author_color', 'date_color' ),
			'button'    => array( 'read_more_color', 'button_text_color' ),
			'button_bg' => array( 'read_more_background_color', 'button_background_color' ),
		),
		'loss'       => array( 'ps.dyn.loss.effect_material' => true ),
		'defaults'   => array(
			'posts_source'              => 'post',
			'posts_limit'               => 5,
			'posts_orderby'             => 'date',
			'posts_order'               => 'desc',
			'posts_ignore_sticky_posts' => 'yes',
			'posts_select_date'         => 'anytime',
			'autoplay'                  => 'yes',
			'autoplay_speed'            => 5000,
			'pauseonhover'              => '',
			'loop'                      => 'yes',
			'speed'                     => array( 'size' => 300 ),
			'slides_to_scroll'          => 1,
			'item_gap'                  => array( 'size' => 20 ),
			'item_gap_tablet'           => array( 'size' => 20 ),
			'item_gap_mobile'           => array( 'size' => 0 ),
			'show_title'                => 'yes',
			'title_tags'                => 'h3',
			'show_category'             => 'yes',
			'show_author'               => 'yes',
			'show_date'                 => 'yes',
			'show_navigation'           => 'yes',
		),
	),
);
