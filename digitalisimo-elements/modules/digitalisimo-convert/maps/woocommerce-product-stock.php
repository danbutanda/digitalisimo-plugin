<?php
/**
 * Product Stock de PRO → Existencias del producto (`digitalisimo-woo-product-stock`,
 * modules/digitalisimo-woo/single/class-product-stock.php).
 *
 * Mismos controles con el mismo nombre y selectores: todo `keep` y `groups`. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-stock',
	'target'  => 'digitalisimo-woo-product-stock',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'text_color' ),
	'groups'  => array(
		'text_typography' => 'text_typography',
	),
	'ui'      => array( 'wc_style_warning' ),
	'classes' => array(
		'.elementor-widget-woocommerce-product-stock' => '.elementor-widget-digitalisimo-woo-product-stock',
	),
);
