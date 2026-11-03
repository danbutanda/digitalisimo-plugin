<?php
/**
 * Prime Slider Lite 4.7.0 `prime-slider-flogia` (Flogia, familia E) → `loop-carousel` (respaldo `posts`).
 *
 * Carrusel de entradas: el Loop Carousel de PRO pinta una plantilla «Loop Item» que Migración genera (imagen destacada de fondo, términos, título, metadatos, extracto y botón); sin contenedores o sin los widgets del creador de temas, la rejilla del widget Entradas. Se pierden las miniaturas de navegación y la animación de UIkit.
 *
 * Origen: L/modules/flogia/widgets/flogia.php (controles :140-240, render_slideshows_settings(520) en :1254) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'E',
	'plugin'   => 'lite',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Carrusel de entradas: el Loop Carousel de PRO pinta una plantilla «Loop Item» que Migración genera (imagen destacada de fondo, términos, título, metadatos, extracto y botón); sin contenedores o sin los widgets del creador de temas, la rejilla del widget Entradas. Se pierden las miniaturas de navegación y la animación de UIkit.',
	'dynamic'  => array(
		'kind'       => 'posts',
		'engine'     => 'uikit',
		'per_view'   => 1,
		'height'     => 'ratio',
		'min_height' => 520,
		'limit'      => 5,
		'nav'        => array( 'both' => 'show_navigation_arrows_dots' ),
		'parts'      => array(
			'title'   => array( 'show_title', 'title_html_tag', 'h1' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_length' ),
			'terms'   => 'show_category',
			'meta'    => array( 'author' => array( 'show_admin_info' ) ),
		),
		'colors'     => array(
			'title'     => array( 'title_color' ),
			'text'      => array( 'excerpt_color', 'text_color' ),
			'meta'      => array( 'meta_text_color' ),
			'button'    => array( 'slide_button_text_color', 'button_text_color' ),
			'button_bg' => array( 'slide_button_background_color', 'button_background_color' ),
		),
		'loss'       => array( 'ps.dyn.loss.thumbs' => 'show_thumbnav', 'ps.dyn.loss.kenburns' => 'kenburns_animation' ),
		'defaults'   => array(
			'posts_source'                => 'post',
			'posts_limit'                 => 5,
			'posts_orderby'               => 'date',
			'posts_order'                 => 'desc',
			'posts_ignore_sticky_posts'   => 'yes',
			'posts_select_date'           => 'anytime',
			'autoplay'                    => 'yes',
			'autoplay_interval'           => 7000,
			'pause_on_hover'              => '',
			'finite'                      => 'yes',
			'show_title'                  => 'yes',
			'title_html_tag'              => 'h1',
			'show_excerpt'                => 'yes',
			'excerpt_length'              => 30,
			'strip_shortcode'             => 'yes',
			'show_category'               => 'yes',
			'show_admin_info'             => 'yes',
			'published_by'                => 'yes',
			'show_thumbnav'               => 'yes',
			'show_navigation_arrows_dots' => 'yes',
			'overlay'                     => 'background',
			'enable_height'               => '',
		),
	),
);
