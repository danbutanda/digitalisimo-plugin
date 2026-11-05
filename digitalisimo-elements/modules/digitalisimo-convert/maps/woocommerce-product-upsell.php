<?php
/**
 * Upsells de PRO → Ventas dirigidas (`digitalisimo-woo-product-upsell`, modules/digitalisimo-woo/single/class-product-upsell.php).
 *
 * Los dos heredan la cuadrícula de productos (PRO `products-base.php`; el propio `Store\Products_Base`) y repiten sus
 * controles con el mismo nombre, valor por defecto y condición: todo `keep` y `groups` (los de la base con la lista
 * común `_woo-products-base.php`). Las clases de la cuadrícula pasan a las propias con las de los Productos
 * (`_woo-products-classes.php`). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_wpb = (array) require __DIR__ . '/_woo-products-base.php';
$digi_wpc = is_file( __DIR__ . '/_woo-products-classes.php' ) ? (array) require __DIR__ . '/_woo-products-classes.php' : array();

return array(
	'source'  => 'woocommerce-product-upsell',
	'target'  => 'digitalisimo-woo-product-upsell',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array_merge( array( 'orderby', 'order', 'show_heading', 'heading_color', 'heading_text_align', 'heading_spacing' ), $digi_wpb['keep'] ),
	'groups'  => array_merge( array( 'heading_typography' => 'heading_typography' ), $digi_wpb['groups'] ),
	'ui'      => $digi_wpb['ui'],
	'classes' => array_merge( $digi_wpc, array( '.elementor-widget-woocommerce-product-upsell' => '.elementor-widget-digitalisimo-woo-product-upsell' ) ),
);
