<?php
/** Element Pack Pro 9.9.1 `bdt-brand-grid` → `digitalisimo-brand-grid`. */
defined( 'ABSPATH' ) || exit;

$placeholder = array( 'url' => class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '' );
$sizes       = array( 'medium_large' => 'large', '1536x1536' => 'full', '2048x2048' => 'full', 'custom' => 'large', 'woocommerce_thumbnail' => 'medium', 'woocommerce_single' => 'large', 'woocommerce_gallery_thumbnail' => 'thumbnail' );

return array(
	'target'    => 'digitalisimo-brand-grid',
	'classes'   => array(
		'.bdt-ep-brand-grid-image img' => '.digi-brand-grid__logo',
		'.bdt-ep-brand-grid-image'     => '.digi-brand-grid__image',
		'.bdt-ep-brand-grid-item'      => '.digi-brand-grid__item',
		'.bdt-ep-brand-grid-name'      => '.digi-brand-grid__name',
		'.bdt-ep-brand-grid-link'      => '.digi-brand-grid__link',
		'.bdt-ep-brand-grid-icon'      => '.digi-brand-grid__summary',
		'.bdt-ep-brand-grid-text'      => '.digi-brand-grid__content',
		'.bdt-ep-brand-grid'           => '.digi-brand-grid__list',
	),
	'defaults'  => array(
		'thumbnail_size'    => 'medium',
		'columns'           => 3,
		'columns_tablet'    => 2,
		'columns_mobile'    => 1,
		'column_gap'        => array( 'size' => 20 ),
		'row_gap'           => array( 'size' => 20 ),
		'show_brand_name'   => 'yes',
		'brand_html_tag'    => 'h3',
		'show_website_link' => 'yes',
		'brand_event'       => 'hover-icon',
	),
	'rename'    => array( 'thumbnail_size' => 'image_size' ),
	// El icono que revelaba los datos al apuntar pasa a revelarlos al apuntar o enfocar la marca.
	'values'    => array( 'brand_event' => array( 'hover-icon' => 'hover-item' ), 'image_size' => $sizes, 'brand_html_tag' => array( 'h1' => 'h2', 'p' => 'div' ) ),
	'drop'      => array( 'icon_position', 'thumbnail_custom_dimension' ),
	'filter'    => static function ( array $out ) {
		// Element Pack sólo enlazaba el texto del sitio web, y sólo si estaba visible.
		if ( 'yes' !== ( $out['show_website_link'] ?? '' ) ) {
			foreach ( $out['brand_items'] as $index => $item ) {
				unset( $out['brand_items'][ $index ]['link'] );
			}
		}
		return $out;
	},
	'repeaters' => array(
		'brand_items' => array(
			'defaults'     => array( 'image' => $placeholder, 'brand_name' => 'Brand Name', 'link' => array( 'url' => '#', 'is_external' => true, 'nofollow' => true ), 'website_link_text' => 'www.example.com' ),
			'default_rows' => array_fill( 0, 6, array( 'image' => $placeholder ) ),
		),
	),
);
