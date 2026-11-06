<?php
/**
 * Category Image de PRO → Imagen de la categoría (`digitalisimo-woo-category-image`,
 * modules/digitalisimo-woo/store/class-category-image.php).
 *
 * Las dos heredan la Imagen del núcleo (imagen con la etiqueta dinámica `woocommerce-category-image-tag` por defecto),
 * así que todo pasa con `keep`. El envoltorio cambia `elementor-widget-woocommerce-category-image` por
 * `elementor-widget-digitalisimo-woo-category-image` y conserva `elementor-widget-image`, como PRO. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_wci = (array) require __DIR__ . '/_theme-image.php';

return array(
	'source'  => 'woocommerce-category-image',
	'target'  => 'digitalisimo-woo-category-image',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => $digi_wci['keep'],
	'groups'  => $digi_wci['groups'],
	'ui'      => $digi_wci['ui'],
	'classes' => array( '.elementor-widget-woocommerce-category-image' => '.elementor-widget-digitalisimo-woo-category-image' ),
);
