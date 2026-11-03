<?php
/**
 * Prime Slider Lite 4.7.0 `prime-slider-woocircle` (WooCircle, familia G) → `woocommerce-products`.
 *
 * Fichas de producto: el widget Productos de PRO en la presentación «Carrusel» propia (sin Swiper), con las partes del producto que mostraba el origen. Se pierde la disposición circular animada.
 *
 * Origen: L/modules/woocircle/widgets/woocircle.php (controles :60-200, `posts_limit` 3 en :183) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'G',
	'plugin'   => 'lite',
	'decision' => 'APROXIMAR',
	'target'   => 'woocommerce-products',
	'fallback' => '',
	'reason'   => 'Fichas de producto: el widget Productos de PRO en la presentación «Carrusel» propia (sin Swiper), con las partes del producto que mostraba el origen. Se pierde la disposición circular animada.',
	'dynamic'  => array(
		'kind'           => 'wc',
		'engine'         => 'swiper',
		'per_view'       => 'fixed',
		'per_view_fixed' => array( 3, 2, 1 ),
		'limit'          => 3,
		'nav'            => array( 'arrows' => false, 'dots' => false ),
		'parts'          => array(
			'title'   => array( 'show_title', 'title_html_tag', 'h1' ),
			'excerpt' => array( 'show_excerpt', 'excerpt_word_limit' ),
			'price'   => 'show_price',
			'cart'    => 'show_cart',
		),
		'loss'           => array( 'ps.dyn.loss.circular' => true ),
		'defaults'       => array(
			'posts_source'              => 'product',
			'posts_limit'               => 3,
			'product_show_product_type' => 'all',
			'product_orderby'           => 'date',
			'posts_order'               => 'desc',
			'posts_select_date'         => 'anytime',
			'autoplay'                  => '',
			'show_title'                => 'yes',
			'title_html_tag'            => 'h1',
			'show_excerpt'              => 'yes',
			'show_price'                => 'yes',
			'show_cart'                 => 'yes',
		),
	),
);
