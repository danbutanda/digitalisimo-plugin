<?php
/**
 * Product Images de PRO → Imágenes del producto (`digitalisimo-woo-product-images`,
 * modules/digitalisimo-woo/single/class-product-images.php).
 *
 * Mismos controles con el mismo nombre, valor por defecto y selectores: todo `keep` y `groups`. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-images',
	'target'  => 'digitalisimo-woo-product-images',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'sale_flash', 'image_border_radius', 'spacing', 'thumbs_border_radius', 'spacing_thumbs' ),
	'groups'  => array(
		'image_border'  => 'image_border',
		'thumbs_border' => 'thumbs_border',
	),
	'ui'      => array( 'wc_style_warning', 'heading_thumbs_style' ),
	'classes' => array( '.elementor-widget-woocommerce-product-images' => '.elementor-widget-digitalisimo-woo-product-images' ),
);
