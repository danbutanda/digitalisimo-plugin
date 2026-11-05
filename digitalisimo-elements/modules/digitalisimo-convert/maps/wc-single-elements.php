<?php
/**
 * Woo - Single Elements de PRO (obsoleto) → Elementos del producto (`digitalisimo-woo-single-elements`,
 * modules/digitalisimo-woo/single/class-single-elements.php).
 *
 * Un solo control con el mismo nombre y las mismas opciones: `keep`. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'wc-single-elements',
	'target'  => 'digitalisimo-woo-single-elements',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => array( 'element' ),
	'classes' => array( '.elementor-widget-wc-single-elements' => '.elementor-widget-digitalisimo-woo-single-elements' ),
);
