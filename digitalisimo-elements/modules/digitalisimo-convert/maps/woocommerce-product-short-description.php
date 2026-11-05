<?php
/**
 * Short Description de PRO → Descripción corta del producto (`digitalisimo-woo-product-short-description`,
 * modules/digitalisimo-woo/single/class-product-short-description.php).
 *
 * Mismos controles con el mismo nombre, valor por defecto y selectores: todo `keep` y `groups`. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-short-description',
	'target'  => 'digitalisimo-woo-product-short-description',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'text_align', 'text_color' ),
	'groups'  => array(
		'text_typography' => 'text_typography',
	),
	'ui'      => array( 'wc_style_warning' ),
	'classes' => array(
		'.elementor-widget-woocommerce-product-short-description' => '.elementor-widget-digitalisimo-woo-product-short-description',
	),
);
