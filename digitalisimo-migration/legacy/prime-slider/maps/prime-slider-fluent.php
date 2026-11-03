<?php
/**
 * Prime Slider Pro 4.7.0 `prime-slider-fluent` (Fluent, familia E) → `loop-carousel` (respaldo `posts`).
 *
 * Carrusel de entradas: el Loop Carousel de PRO pinta una plantilla «Loop Item» que Migración genera (imagen destacada de fondo, términos, título, metadatos, extracto y botón); sin contenedores o sin los widgets del creador de temas, la rejilla del widget Entradas. Se pierden el desplazador de miniaturas, el avatar del autor y la animación avanzada.
 *
 * Origen: P/modules/fluent/widgets/fluent.php (controles :120-300, `posts_limit` 9 en :228, render_slideshows_settings(460) en :1416) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'E',
	'plugin'   => 'pro',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Carrusel de entradas: el Loop Carousel de PRO pinta una plantilla «Loop Item» que Migración genera (imagen destacada de fondo, términos, título, metadatos, extracto y botón); sin contenedores o sin los widgets del creador de temas, la rejilla del widget Entradas. Se pierden el desplazador de miniaturas, el avatar del autor y la animación avanzada.',
	'dynamic'  => array(
		'kind'       => 'posts',
		'engine'     => 'uikit',
		'per_view'   => 1,
		'height'     => 'ratio',
		'min_height' => 460,
		'limit'      => 9,
		'nav'        => array( 'arrows' => 'show_navigation_arrows', 'dots' => false ),
		'parts'      => array(
			'title'   => array( 'show_title', 'title_html_tag', 'h1' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_length' ),
			'terms'   => 'show_category',
			'meta'    => array( 'author' => array( 'show_admin_info' ), 'date' => array( 'show_date' ) ),
		),
		'social'     => true,
		'scroll'     => true,
		'colors'     => array(
			'title'     => array( 'title_color' ),
			'text'      => array( 'excerpt_color', 'text_color' ),
			'meta'      => array( 'meta_text_color' ),
			'button'    => array( 'slide_button_text_color', 'button_text_color' ),
			'button_bg' => array( 'slide_button_background_color', 'button_background_color' ),
		),
		'loss'       => array( 'ps.dyn.loss.thumbs' => 'show_thumb_scroller', 'ps.dyn.loss.avatar' => 'show_avatar' ),
		'defaults'   => array(
			'posts_source'              => 'post',
			'posts_limit'               => 9,
			'posts_orderby'             => 'date',
			'posts_order'               => 'desc',
			'posts_ignore_sticky_posts' => 'yes',
			'posts_select_date'         => 'anytime',
			'autoplay'                  => 'yes',
			'autoplay_interval'         => 7000,
			'pause_on_hover'            => '',
			'finite'                    => 'yes',
			'show_title'                => 'yes',
			'title_html_tag'            => 'h1',
			'show_excerpt'              => 'yes',
			'excerpt_length'            => 30,
			'strip_shortcode'           => 'yes',
			'show_admin_info'           => 'yes',
			'show_avatar'               => 'yes',
			'show_category'             => 'yes',
			'show_date'                 => 'yes',
			'show_social_icon'          => 'yes',
			'show_thumb_scroller'       => 'yes',
			'show_navigation_arrows'    => 'yes',
			'show_scroll_button'        => 'yes',
			'scroll_button_text'        => array( '__t' => 'Scroll Down', 'd' => 'bdthemes-prime-slider' ),
			'section_id'                => 'my-header',
			'overlay'                   => 'background',
			'enable_height'             => '',
		),
	),
);
