<?php
/**
 * WooCommerce Pages de PRO → Páginas de la tienda (`digitalisimo-woo-pages`, modules/digitalisimo-woo/store/class-pages.php).
 *
 * Mismos controles (`element`, `product_id`) y la misma salida: el shortcode de WooCommerce de la página elegida. Sin
 * clases propias que traducir: el marcado es el de WooCommerce. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'wc-elements',
	'target'  => 'digitalisimo-woo-pages',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'element', 'product_id' ),
	'classes' => array(
		'.elementor-widget-wc-elements' => '.elementor-widget-digitalisimo-woo-pages',
	),
);
