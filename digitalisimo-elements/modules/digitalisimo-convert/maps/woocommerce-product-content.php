<?php
/**
 * Product Content de PRO → Contenido del producto (`digitalisimo-woo-product-content`,
 * modules/digitalisimo-woo/single/class-product-content.php).
 *
 * Los dos heredan el contenido de la entrada (PRO el suyo; el propio, el Contenido de la entrada de C4) con los mismos
 * controles (alineación, color y tipografía), así que todo pasa con `keep`. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-content',
	'target'  => 'digitalisimo-woo-product-content',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'align', 'text_color' ),
	'groups'  => array(
		'typography' => 'typography',
	),
	'classes' => array( '.elementor-widget-woocommerce-product-content' => '.elementor-widget-digitalisimo-woo-product-content' ),
);
