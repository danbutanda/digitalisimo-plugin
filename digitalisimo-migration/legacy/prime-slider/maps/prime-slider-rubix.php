<?php
/**
 * Prime Slider Lite 4.7.0 `prime-slider-rubix` (Rubix, familia F) → `loop-carousel` (respaldo `posts`).
 *
 * Carrusel de varias entradas por vista: el Loop Carousel de PRO con tantas diapositivas por vista como columnas tenía el origen y una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Se pierden las miniaturas de navegación y las diapositivas centradas.
 *
 * Origen: L/modules/rubix/widgets/rubix.php (controles :70-270, columnas 2/1/1 en :78-80) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'F',
	'plugin'   => 'lite',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Carrusel de varias entradas por vista: el Loop Carousel de PRO con tantas diapositivas por vista como columnas tenía el origen y una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Se pierden las miniaturas de navegación y las diapositivas centradas.',
	'dynamic'  => array(
		'kind'       => 'posts',
		'engine'     => 'swiper',
		'per_view'   => 'columns',
		'columns'    => array( 2, 1, 1 ),
		'gap'        => array( 0, 0, 0 ),
		'height'     => 'item_height',
		'min_height' => 420,
		'limit'      => 5,
		'nav'        => array( 'arrows' => true, 'dots' => false ),
		'parts'      => array(
			'title'   => array( 'show_title', 'title_tags', 'h3' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_length' ),
			'terms'   => 'show_category',
			'meta'    => array( 'author' => array( 'show_author' ), 'date' => array( 'show_date' ) ),
			'button'  => array(
				'show_read_more',
				'read_more_text',
				array( '__t' => 'Read More', 'd' => 'bdthemes-prime-slider-lite' ),
			),
		),
		'colors'     => array(
			'title'     => array( 'title_color' ),
			'text'      => array( 'excerpt_color', 'text_color' ),
			'meta'      => array( 'meta_color', 'author_color', 'date_color' ),
			'button'    => array( 'read_more_color', 'button_text_color' ),
			'button_bg' => array( 'read_more_background_color', 'button_background_color' ),
		),
		'loss'       => array( 'ps.dyn.loss.thumbs' => true, 'ps.dyn.loss.centered' => 'centered_slides' ),
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
			'speed'                     => array( 'size' => 900 ),
			'slides_to_scroll'          => 1,
			'columns'                   => 2,
			'columns_tablet'            => 1,
			'columns_mobile'            => 1,
			'show_title'                => 'yes',
			'title_tags'                => 'h3',
			'show_excerpt'              => 'yes',
			'excerpt_length'            => 30,
			'show_category'             => 'yes',
			'show_author'               => 'yes',
			'show_date'                 => 'yes',
			'show_read_more'            => 'yes',
			'read_more_text'            => array( '__t' => 'Read More', 'd' => 'bdthemes-prime-slider-lite' ),
			'centered_slides'           => 'yes',
		),
	),
);
