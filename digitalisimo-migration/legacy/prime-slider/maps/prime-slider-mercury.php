<?php
/**
 * Prime Slider Lite 4.7.0 `prime-slider-mercury` (Mercury, familia F) → `loop-carousel` (respaldo `posts`).
 *
 * Carrusel de entradas con Swiper: el Loop Carousel de PRO (Swiper por necesidad demostrada: contenido dinámico) con una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Una entrada por vista; se pierde el efecto de transición si no es «slide».
 *
 * Origen: L/modules/mercury/widgets/mercury.php (controles :60-320) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'F',
	'plugin'   => 'lite',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Carrusel de entradas con Swiper: el Loop Carousel de PRO (Swiper por necesidad demostrada: contenido dinámico) con una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Una entrada por vista; se pierde el efecto de transición si no es «slide».',
	'dynamic'  => array(
		'kind'       => 'posts',
		'engine'     => 'swiper',
		'per_view'   => 1,
		'height'     => 'item_height',
		'min_height' => 500,
		'limit'      => 5,
		'nav'        => array( 'arrows' => 'show_navigation', 'dots' => false ),
		'parts'      => array(
			'title'   => array( 'show_title', 'title_tags', 'h3' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_length' ),
			'terms'   => 'show_category',
			'meta'    => array( 'author' => array( 'show_author' ), 'date' => array( 'show_date' ) ),
		),
		'colors'     => array(
			'title'     => array( 'title_color' ),
			'text'      => array( 'excerpt_color', 'text_color' ),
			'meta'      => array( 'meta_color', 'author_color', 'date_color' ),
			'button'    => array( 'read_more_color', 'button_text_color' ),
			'button_bg' => array( 'read_more_background_color', 'button_background_color' ),
		),
		'loss'       => array( 'ps.dyn.loss.layout' => 'content_reverse' ),
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
			'effect'                    => 'slide',
			'show_title'                => 'yes',
			'title_tags'                => 'h3',
			'show_excerpt'              => 'yes',
			'excerpt_length'            => 30,
			'show_category'             => 'yes',
			'show_author'               => 'yes',
			'show_date'                 => 'yes',
			'show_navigation'           => 'yes',
			'rewind'                    => 'yes',
		),
	),
);
