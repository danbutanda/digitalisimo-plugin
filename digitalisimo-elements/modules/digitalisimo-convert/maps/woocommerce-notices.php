<?php
/**
 * WooCommerce Notices de PRO → Avisos de la tienda (`digitalisimo-woo-notices`,
 * modules/digitalisimo-woo/store/class-notices.php).
 *
 * El widget de PRO sólo tiene avisos del panel (sin valor); los avisos los pinta WooCommerce en la envoltura propia
 * (`digi-woo-notices`, en PRO `e-woocommerce-notices-wrapper`). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-notices',
	'target'  => 'digitalisimo-woo-notices',
	'status'  => 'draft',
	'panel'   => 'hide',
	'ui'      => array( 'where_to_appear_notice', 'site_settings_notice', 'one_per_page_notice' ),
	'classes' => array(
		'.elementor-widget-woocommerce-notices' => '.elementor-widget-digitalisimo-woo-notices',
		'.e-woocommerce-notices-wrapper'        => '.digi-woo-notices',
		'.e-notices-demo-notice'                => '.digi-woo-notices__demo',
	),
);
