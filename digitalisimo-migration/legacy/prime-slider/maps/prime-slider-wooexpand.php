<?php
/**
 * Prime Slider Pro 4.7.0 `prime-slider-wooexpand` (WooExpand, familia G) → `woocommerce-products`.
 *
 * Fichas de producto: el widget Productos de PRO en la presentación «Carrusel» propia (sin Swiper), con las partes del producto que mostraba el origen. Se pierden los paneles que se expanden al pulsar o al pasar el puntero.
 *
 * Origen: P/modules/wooexpand/widgets/wooexpand.php (controles :60-280, `posts_limit` 4 en :267) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'G',
	'plugin'   => 'pro',
	'decision' => 'APROXIMAR',
	'target'   => 'woocommerce-products',
	'fallback' => '',
	'reason'   => 'Fichas de producto: el widget Productos de PRO en la presentación «Carrusel» propia (sin Swiper), con las partes del producto que mostraba el origen. Se pierden los paneles que se expanden al pulsar o al pasar el puntero.',
	'dynamic'  => array(
		'kind'           => 'wc',
		'engine'         => 'swiper',
		'per_view'       => 'fixed',
		'per_view_fixed' => array( 4, 2, 1 ),
		'limit'          => 4,
		'nav'            => array( 'arrows' => false, 'dots' => false ),
		'parts'          => array(
			'title'   => array( 'show_title', 'title_html_tag', 'h1' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_length' ),
			'terms'   => 'show_meta',
			'rating'  => 'show_rating',
			'price'   => 'show_price',
			'cart'    => 'show_cart',
			'badge'   => 'show_badge',
		),
		'loss'           => array( 'ps.dyn.loss.expand' => true ),
		'defaults'       => array(
			'posts_source'              => 'product',
			'posts_limit'               => 4,
			'product_show_product_type' => 'all',
			'product_orderby'           => 'date',
			'posts_order'               => 'desc',
			'posts_select_date'         => 'anytime',
			'autoplay'                  => '',
			'show_title'                => 'yes',
			'title_html_tag'            => 'h1',
			'show_rating'               => 'yes',
			'show_excerpt'              => 'yes',
			'excerpt_length'            => 30,
			'show_price'                => 'yes',
			'show_cart'                 => 'yes',
			'show_meta'                 => 'yes',
			'show_badge'                => 'yes',
		),
	),
);
