<?php
/**
 * Product Price de PRO → Precio del producto (`digitalisimo-woo-product-price`,
 * modules/digitalisimo-woo/single/class-product-price.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, valor por defecto y selectores: todo `keep` y
 * `groups`. «Apilado» cambia la clase de PRO por la propia. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-price',
	'target'  => 'digitalisimo-woo-product-price',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'text_align', 'price_color', 'sale_price_color', 'price_block', 'sale_price_spacing' ),
	'groups'  => array(
		'typography'            => 'typography',
		'sale_price_typography' => 'sale_price_typography',
	),
	// Controles sin valor (aviso y encabezado).
	'ui'      => array( 'wc_style_warning', 'sale_heading' ),
	'classes' => array(
		'.elementor-widget-woocommerce-product-price' => '.elementor-widget-digitalisimo-woo-product-price',
		'.elementor-product-price-block-yes'          => '.digi-product-price--block-yes',
	),
);
