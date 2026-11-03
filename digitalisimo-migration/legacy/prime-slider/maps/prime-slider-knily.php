<?php
/**
 * Prime Slider Pro 4.7.0 `prime-slider-knily` (Knily, familia F) → `loop-carousel` (respaldo `posts`).
 *
 * Carrusel de entradas con Swiper: el Loop Carousel de PRO (Swiper por necesidad demostrada: contenido dinámico) con una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Una entrada por vista; se pierden las miniaturas, los botones de compartir y los efectos con licencia.
 *
 * Origen: P/modules/knily/widgets/knily.php (controles :60-430, `swiper_effect` fade en :374) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'F',
	'plugin'   => 'pro',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Carrusel de entradas con Swiper: el Loop Carousel de PRO (Swiper por necesidad demostrada: contenido dinámico) con una plantilla «Loop Item» generada; sin ella, la rejilla del widget Entradas. Una entrada por vista; se pierden las miniaturas, los botones de compartir y los efectos con licencia.',
	'dynamic'  => array(
		'kind'       => 'posts',
		'engine'     => 'swiper',
		'per_view'   => 1,
		'height'     => 'item_height',
		'min_height' => 500,
		'limit'      => 5,
		'nav'        => array( 'arrows' => true, 'dots' => 'show_navigation_dots' ),
		'parts'      => array(
			'title'   => array( 'show_title', 'title_tags', 'h3' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_length' ),
			'terms'   => 'show_category',
			'meta'    => array( 'author' => array( 'show_author' ), 'date' => array( 'show_date' ) ),
			'button'  => array( 'show_button', 'button_text', array( '__t' => 'More Details', 'd' => 'bdthemes-prime-slider' ) ),
		),
		'colors'     => array(
			'title'     => array( 'title_color' ),
			'text'      => array( 'excerpt_color', 'text_color' ),
			'meta'      => array( 'meta_color', 'author_color', 'date_color' ),
			'button'    => array( 'read_more_color', 'button_text_color' ),
			'button_bg' => array( 'read_more_background_color', 'button_background_color' ),
		),
		'loss'       => array( 'ps.dyn.loss.thumbs' => '!hide_thumbs', 'ps.dyn.loss.share' => 'show_social_share' ),
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
			'swiper_effect'             => 'fade',
			'show_title'                => 'yes',
			'title_tags'                => 'h3',
			'show_excerpt'              => 'yes',
			'excerpt_length'            => 30,
			'show_category'             => 'yes',
			'show_author'               => 'yes',
			'show_date'                 => 'yes',
			'show_button'               => 'yes',
			'button_text'               => array( '__t' => 'More Details', 'd' => 'bdthemes-prime-slider' ),
			'show_social_share'         => 'yes',
			'hide_thumbs'               => '',
			'show_navigation_dots'      => 'yes',
		),
	),
);
