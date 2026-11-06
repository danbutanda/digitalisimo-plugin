<?php
/**
 * Product Categories de PRO → Categorías de productos (`digitalisimo-woo-categories`,
 * modules/digitalisimo-woo/store/class-categories.php).
 *
 * Mismos controles con el mismo nombre (también las columnas de la base de PRO) y la misma salida (`[product_categories]`
 * de WooCommerce en la rejilla del núcleo): todo `keep`. Las clases que PRO pone en el envoltorio pasan a las propias:
 * `elementor-products-grid elementor-wc-products` → `digi-woo-grid digi-woo-wc-products` y
 * `elementor-product-loop-item--align-*` → `digi-woo-grid--align-*`. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'wc-categories',
	'target'  => 'digitalisimo-woo-categories',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => array( 'columns', 'number', 'source', 'categories', 'parent', 'hide_empty', 'orderby', 'order', 'products_class', 'column_gap', 'row_gap', 'align', 'image_border_radius', 'image_spacing', 'title_color', 'count_color' ),
	'groups'  => array(
		'image_border'     => 'image_border',
		'title_typography' => 'title_typography',
		'count_typography' => 'count_typography',
	),
	'ui'      => array( 'wc_style_warning', 'heading_image_style', 'heading_title_style', 'heading_count_style' ),
	'classes' => array(
		'.elementor-widget-wc-categories'            => '.elementor-widget-digitalisimo-woo-categories',
		'.elementor-products-grid'                   => '.digi-woo-grid',
		'.elementor-wc-products'                     => '.digi-woo-wc-products',
		'.elementor-product-loop-item--align-left'   => '.digi-woo-grid--align-left',
		'.elementor-product-loop-item--align-center' => '.digi-woo-grid--align-center',
		'.elementor-product-loop-item--align-right'  => '.digi-woo-grid--align-right',
	),
);
