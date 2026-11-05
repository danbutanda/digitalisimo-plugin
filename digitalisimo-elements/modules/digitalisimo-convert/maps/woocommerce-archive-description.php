<?php
/**
 * Archive Description de PRO → Descripción del archivo de la tienda (`digitalisimo-woo-archive-description`,
 * modules/digitalisimo-woo/store/class-archive-description.php).
 *
 * Mismos controles con el mismo nombre y la misma salida (`woocommerce_archive_description`): todo `keep`. Formato:
 * cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'woocommerce-archive-description',
	'target'  => 'digitalisimo-woo-archive-description',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'text_align', 'text_color' ),
	'groups'  => array( 'text_typography' => 'text_typography' ),
	'ui'      => array( 'wc_style_warning' ),
	'classes' => array( '.elementor-widget-woocommerce-archive-description' => '.elementor-widget-digitalisimo-woo-archive-description' ),
);
