<?php
/**
 * WooCommerce Breadcrumbs de PRO → Migas de la tienda (`digitalisimo-woo-breadcrumb`,
 * modules/digitalisimo-woo/store/class-breadcrumb.php).
 *
 * Mismos controles con el mismo nombre y la misma salida (`woocommerce_breadcrumb()`): todo `keep`. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-breadcrumb',
	'target'  => 'digitalisimo-woo-breadcrumb',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => array( 'text_color', 'link_color', 'alignment' ),
	'groups'  => array( 'text_typography' => 'text_typography' ),
	'ui'      => array( 'wc_style_warning' ),
	'classes' => array( '.elementor-widget-woocommerce-breadcrumb' => '.elementor-widget-digitalisimo-woo-breadcrumb' ),
);
