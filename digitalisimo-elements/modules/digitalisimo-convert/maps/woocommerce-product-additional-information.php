<?php
/**
 * Additional Information de PRO → Información adicional del producto
 * (`digitalisimo-woo-product-additional-information`, modules/digitalisimo-woo/single/class-product-additional-information.php).
 *
 * Mismos controles con el mismo nombre, valor por defecto y selectores: todo `keep` y `groups`. «Encabezado» cambia la
 * clase de PRO por la propia. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-additional-information',
	'target'  => 'digitalisimo-woo-product-additional-information',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'show_heading', 'heading_color', 'content_color' ),
	'groups'  => array(
		'heading_typography' => 'heading_typography',
		'content_typography' => 'content_typography',
	),
	'classes' => array(
		'.elementor-widget-woocommerce-product-additional-information' => '.elementor-widget-digitalisimo-woo-product-additional-information',
		'.elementor-show-heading-yes' => '.digi-product-additional-information--heading-yes',
	),
);
