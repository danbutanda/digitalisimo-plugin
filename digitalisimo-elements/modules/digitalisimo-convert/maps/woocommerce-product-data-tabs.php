<?php
/**
 * Product Data Tabs de PRO → Pestañas de datos del producto (`digitalisimo-woo-product-data-tabs`,
 * modules/digitalisimo-woo/single/class-product-data-tabs.php).
 *
 * Mismos controles con el mismo nombre, valor por defecto y selectores: todo `keep` y `groups`. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-product-data-tabs',
	'target'  => 'digitalisimo-woo-product-data-tabs',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => array( 'tab_text_color', 'tab_bg_color', 'tabs_border_color', 'active_tab_text_color', 'active_tab_bg_color', 'active_tabs_border_color', 'tab_border_radius', 'text_color', 'heading_color', 'panel_border_width', 'panel_border_radius' ),
	'groups'  => array(
		'tab_typography'             => 'tab_typography',
		'content_typography'         => 'content_typography',
		'content_heading_typography' => 'content_heading_typography',
		'panel_box_shadow'           => 'panel_box_shadow',
	),
	'ui'      => array( 'wc_style_warning', 'heading_panel_heading_style' ),
	'classes' => array( '.elementor-widget-woocommerce-product-data-tabs' => '.elementor-widget-digitalisimo-woo-product-data-tabs' ),
);
