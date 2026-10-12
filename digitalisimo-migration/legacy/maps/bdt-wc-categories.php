<?php
/** Element Pack Pro 9.9.1 `bdt-wc-categories` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(),
	'defaults'  => array(
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 20,
		),
		'row_gap' => array(
			'size' => 20,
		),
		'number' => '4',
		'categories' => array(),
		'parent' => '0',
		'orderby' => 'name',
		'order' => 'desc',
		'title_align' => 'center',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'categories' => array(
			'source' => 'by_id',
		),
		'parent' => array(
			'source' => 'by_parent',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'product_categories', array( 'number' => ( $s['number'] ?? '' ), 'hide_empty' => 'yes' === ( $s['hide_empty'] ?? '' ) ? 1 : 0, 'orderby' => ( $s['orderby'] ?? '' ), 'order' => ( $s['order'] ?? '' ) ) + ( 'by_id' === ( $s['source'] ?? '' ) ? array( 'ids' => implode( ',', (array) ( $s['categories'] ?? array() ) ) ) : ( 'by_parent' === ( $s['source'] ?? '' ) ? array( 'parent' => ( $s['parent'] ?? '' ) ) : array() ) ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'number', 'source', 'categories', 'parent', 'orderby', 'order', 'hide_empty' ) );
	},
);
