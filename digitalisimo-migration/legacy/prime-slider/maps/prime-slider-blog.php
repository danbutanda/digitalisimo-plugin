<?php
/**
 * Prime Slider Lite 4.7.0 `prime-slider-blog` (Blog, familia E) → `loop-carousel` (respaldo `posts`).
 *
 * Carrusel de entradas: el Loop Carousel de PRO pinta una plantilla «Loop Item» que Migración genera (imagen destacada de fondo, términos, título, metadatos, extracto y botón); sin contenedores o sin los widgets del creador de temas, la rejilla del widget Entradas. Se pierden las skins coral, zinest y folio, la animación de UIkit y el paralaje.
 *
 * Origen: L/modules/blog/widgets/blog.php (controles :84-500, `posts_limit` 3 en :363, render_slideshows_settings(440) en :2224) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'E',
	'plugin'   => 'lite',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Carrusel de entradas: el Loop Carousel de PRO pinta una plantilla «Loop Item» que Migración genera (imagen destacada de fondo, términos, título, metadatos, extracto y botón); sin contenedores o sin los widgets del creador de temas, la rejilla del widget Entradas. Se pierden las skins coral, zinest y folio, la animación de UIkit y el paralaje.',
	'dynamic'  => array(
		'kind'       => 'posts',
		'engine'     => 'uikit',
		'per_view'   => 1,
		'height'     => 'ratio',
		'min_height' => 440,
		'limit'      => 3,
		'nav'        => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
		'parts'      => array(
			'title'   => array( 'show_title', 'title_html_tag', 'h1' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_length' ),
			'terms'   => 'show_category',
			'meta'    => array(
				'switch'   => 'show_meta',
				'author'   => array( 'show_author' ),
				'date'     => array( 'show_date' ),
				'comments' => array( 'show_comments' ),
			),
			'button'  => array(
				'show_button_text',
				'button_text',
				array( '__t' => 'Read More', 'd' => 'bdthemes-prime-slider-lite' ),
			),
		),
		'skins'      => array(
			'folio'  => array(
				'parts' => array(
					'meta' => array(
						'switch'   => 'show_meta',
						'author'   => array( 'show_admin_info' ),
						'date'     => array( 'show_date' ),
						'comments' => array( 'show_comments' ),
					),
				),
				'nav'   => array( 'arrows' => false, 'dots' => false ),
			),
			'zinest' => array(
				'parts' => array( 'button' => null ),
				'nav'   => array( 'arrows' => 'show_navigation_arrows', 'dots' => false ),
				'loss'  => array( 'ps.dyn.loss.featured' => 'show_featured_post' ),
			),
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
		'loss'       => array( 'ps.dyn.loss.skin' => '_skin' ),
		'defaults'   => array(
			'posts_source'              => 'post',
			'posts_limit'               => 3,
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
			'show_button_text'          => 'yes',
			'button_text'               => array( '__t' => 'Read More', 'd' => 'bdthemes-prime-slider-lite' ),
			'show_category'             => 'yes',
			'show_meta'                 => 'yes',
			'show_author'               => 'yes',
			'show_admin_info'           => 'yes',
			'show_date'                 => 'yes',
			'show_comments'             => 'yes',
			'show_navigation_arrows'    => 'yes',
			'show_navigation_dots'      => 'yes',
			'show_social_icon'          => 'yes',
			'show_scroll_button'        => 'yes',
			'scroll_button_text'        => array( '__t' => 'Scroll Down', 'd' => 'bdthemes-prime-slider-lite' ),
			'section_id'                => 'my-header',
			'show_featured_post'        => 'yes',
			'overlay'                   => 'background',
			'enable_height'             => '',
		),
	),
);
