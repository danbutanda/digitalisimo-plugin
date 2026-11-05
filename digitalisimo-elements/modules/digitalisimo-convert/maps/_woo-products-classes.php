<?php
/**
 * Clases de los Productos de PRO → propias (`modules/digitalisimo-woo/store/`), para el CSS personalizado del elemento
 * de los cuatro mapas de productos (`woocommerce-products`, `wc-products`, `wc-archive-products`,
 * `woocommerce-archive-products`). No es un mapa (`Registry` ignora `maps/_*.php`). La rejilla `elementor-grid` es la
 * del núcleo y no cambia.
 */
defined( 'ABSPATH' ) || exit;

$digi_wpc = array(
	'.elementor-widget-woocommerce-products'         => '.elementor-widget-digitalisimo-woo-products',
	'.elementor-widget-wc-products'                  => '.elementor-widget-digitalisimo-woo-products-classic',
	'.elementor-widget-wc-archive-products'          => '.elementor-widget-digitalisimo-woo-archive-products',
	'.elementor-widget-woocommerce-archive-products' => '.elementor-widget-digitalisimo-woo-archive-products-classic',
	'.elementor-products-grid'                       => '.digi-products-grid',
	'.elementor-wc-products'                         => '.digi-wc-products',
	'.products-heading-show'                         => '.digi-products--heading-show',
	'.elementor-show-pagination-border-yes'          => '.digi-products--pagination-border-yes',
	'.elementor-products-nothing-found'              => '.digi-products__nothing-found',
	'.elementor-nothing-found'                       => '.digi-products__nothing-found',
);
foreach ( array( 'left', 'center', 'right' ) as $digi_wpc_align ) {
	$digi_wpc[ '.elementor-product-loop-item--align-' . $digi_wpc_align ] = '.digi-products--align-' . $digi_wpc_align;
}
return $digi_wpc;
