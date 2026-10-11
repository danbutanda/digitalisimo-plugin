<?php
/** Element Pack Pro 9.9.1 `bdt-dynamic-grid` → `loop-grid`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'loop-grid',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-dynamic-grid-item, .bdt-dynamic-grid
	'classes'   => array(),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 6,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'column_gap' => array(
			'size' => 20,
		),
		'row_gap' => array(
			'size' => 20,
		),
	),
	// Una plantilla de Elementor por entrada, en la rejilla del Loop Grid de PRO Elements.
	'filter'    => static function ( array $out ) {
		return Digitalisimo\Elements\Legacy\Translator::loop_query( $out );
	},
);
