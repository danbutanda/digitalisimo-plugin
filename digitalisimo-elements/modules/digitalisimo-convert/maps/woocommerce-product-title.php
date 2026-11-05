<?php
/**
 * Product Title de PRO → Título del producto (`digitalisimo-woo-product-title`,
 * modules/digitalisimo-woo/single/class-product-title.php).
 *
 * Los dos heredan el Encabezado del núcleo: mismos controles con el mismo nombre (título con la etiqueta dinámica del
 * título del producto por defecto, nivel `h1`), así que todo pasa con `keep` (lista común `_theme-heading.php`). El
 * envoltorio cambia `elementor-widget-woocommerce-product-title` por el propio y conserva `elementor-widget-heading` y
 * `elementor-page-title`, como PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_th = (array) require __DIR__ . '/_theme-heading.php';

return array(
	'source'   => 'woocommerce-product-title',
	'target'   => 'digitalisimo-woo-product-title',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valor por defecto que PRO cambia (Elementor no guarda lo que coincide con él).
	'defaults' => array( 'header_size' => 'h1' ),
	'keep'     => $digi_th['keep'],
	'groups'   => $digi_th['groups'],
	'ui'       => $digi_th['ui'],
	'classes'  => array( '.elementor-widget-woocommerce-product-title' => '.elementor-widget-digitalisimo-woo-product-title' ),
);
