<?php
/**
 * Product Rating de PRO → Valoración del producto (`digitalisimo-woo-product-rating`,
 * modules/digitalisimo-woo/single/class-product-rating.php).
 *
 * Mismos controles con el mismo nombre, valor por defecto y selectores: todo `keep` y `groups`. La alineación cambia la
 * clase de PRO por la propia. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-rating',
	'target'  => 'digitalisimo-woo-product-rating',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'star_color', 'empty_star_color', 'link_color', 'star_size', 'space_between', 'alignment' ),
	'groups'  => array(
		'text_typography' => 'text_typography',
	),
	'ui'      => array( 'wc_style_warning' ),
	'classes' => array(
		'.elementor-widget-woocommerce-product-rating' => '.elementor-widget-digitalisimo-woo-product-rating',
		'.elementor-product-rating--align-left'        => '.digi-product-rating--align-left',
		'.elementor-product-rating--align-center'      => '.digi-product-rating--align-center',
		'.elementor-product-rating--align-right'       => '.digi-product-rating--align-right',
		'.elementor-product-rating--align-justify'     => '.digi-product-rating--align-justify',
	),
);
