<?php
/**
 * Prime Slider Lite 4.7.0 `prime-slider-woocommerce` (WooCommerce, familia G) → `loop-carousel` (respaldo `woocommerce-products`).
 *
 * Héroe de productos: el Loop Carousel de PRO con la piel de productos, una por vista, y una plantilla «Loop Item» de producto generada (imagen de fondo, título, términos, extracto, precio y el formulario de compra de PRO); sin ella, el widget Productos en carrusel. Se pierde la composición de imagen y texto en dos columnas.
 *
 * Origen: L/modules/woocommerce/widgets/woocommerce.php (controles :60-230, `posts_source` product y `posts_limit` 3 en :206-219, render_slideshows_settings(460) en :1597) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'G',
	'plugin'   => 'lite',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'woocommerce-products',
	'reason'   => 'Héroe de productos: el Loop Carousel de PRO con la piel de productos, una por vista, y una plantilla «Loop Item» de producto generada (imagen de fondo, título, términos, extracto, precio y el formulario de compra de PRO); sin ella, el widget Productos en carrusel. Se pierde la composición de imagen y texto en dos columnas.',
	'dynamic'  => array(
		'kind'       => 'products',
		'engine'     => 'uikit',
		'per_view'   => 1,
		'height'     => 'ratio',
		'min_height' => 460,
		'limit'      => 3,
		'nav'        => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ),
		'parts'      => array(
			'title'   => array( 'show_title', 'title_html_tag', 'h1' ),
			'terms'   => 'show_category',
			'excerpt' => array( 'show_excerpt', 'excerpt_word_limit' ),
			'price'   => 'show_price',
			'cart'    => 'show_cart',
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
		'loss'       => array( 'ps.dyn.loss.layout' => true ),
		'defaults'   => array(
			'posts_source'              => 'product',
			'posts_limit'               => 3,
			'product_show_product_type' => 'all',
			'product_orderby'           => 'date',
			'posts_order'               => 'desc',
			'posts_select_date'         => 'anytime',
			'autoplay'                  => 'yes',
			'autoplay_interval'         => 7000,
			'pause_on_hover'            => '',
			'finite'                    => 'yes',
			'show_title'                => 'yes',
			'title_html_tag'            => 'h1',
			'show_category'             => 'yes',
			'show_excerpt'              => 'yes',
			'show_price'                => 'yes',
			'show_cart'                 => 'yes',
			'show_social_icon'          => 'yes',
			'show_scroll_button'        => 'yes',
			'show_navigation_arrows'    => 'yes',
			'show_navigation_dots'      => 'yes',
			'scroll_button_text'        => array( '__t' => 'Scroll Down', 'd' => 'bdthemes-prime-slider-lite' ),
			'section_id'                => 'my-header',
			'overlay'                   => 'background',
			'enable_height'             => '',
		),
	),
);
