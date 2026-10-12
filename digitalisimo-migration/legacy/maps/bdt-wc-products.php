<?php
/** Element Pack Pro 9.9.1 `bdt-wc-products` → `woocommerce-products`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'woocommerce-products',
	// Clases de Element Pack más usadas en sus selectores: .bdt-wc-products, .bdt-product-quick-view, .bdt-wc-product, .bdt-ep-grid-filters, .bdt-button, .bdt-wc-add-to-cart, .bdt-wc-product-inner, .bdt-quick-view, .bdt-pagination, .bdt-loadmore-container, .bdt-wc-product-price, .bdt-wc-product-image, .bdt-badge, .bdt-active
	'classes'   => array(),
	'defaults'  => array(
		'_skin' => '',
		'columns' => '4',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'item_gap' => array(
			'size' => 30,
		),
		'row_gap' => array(
			'size' => 30,
		),
		'alignment' => 'center',
		'hide_header' => 'no',
		'image_size' => 'medium',
		'wc_products_enable_ajax_loadmore_items' => 4,
		'wc_products_show_loadmore' => 'yes',
		'active_hash' => 'no',
		'hash_top_offset' => array(
			'unit' => 'px',
			'size' => 70,
		),
		'hash_scrollspy_time' => array(
			'unit' => 'px',
			'size' => 1000,
		),
		'filter_custom_text_all' => 'All Products',
		'filter_custom_text_filter' => 'Filter',
		'show_badge' => 'yes',
		'show_change_length' => 'yes',
		'show_searching' => 'yes',
		'show_ordering' => 'yes',
		'show_thumb' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_excerpt' => 'yes',
		'excerpt_limit' => 10,
		'show_rating' => 'yes',
		'show_price' => 'yes',
		'show_cart' => 'yes',
		'grid_animation_type' => '',
		'grid_anim_delay' => array(
			'unit' => 'ms',
			'size' => 300,
		),
		'stripe' => 'yes',
		'search_field_focus_border_width' => array(
			'size' => 1,
		),
		'search_field_focus_border_radius' => array(
			'size' => 1,
		),
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'button_text_color' => '',
		'quick_view_modal_rating_color' => '#e7e7e7',
		'quick_view_modal_active_rating_color' => '#FFCC00',
		'quick_view_modal_badge_text_color' => '',
		'badge_text_color' => '',
		'badge_position' => 'left',
		'filter_alignment' => 'center',
	),
	// Como en Element Pack: el extracto, las categorías, la imagen y el precio opcionales sólo existen en la tabla; la insignia sólo en la rejilla.
	'conditions' => array(
		'show_excerpt' => array( '_skin!' => '' ),
		'show_categories' => array( '_skin!' => '' ),
		'show_thumb' => array( '_skin!' => '' ),
		'show_price' => array( '_skin!' => '' ),
		'show_badge' => array( '_skin' => '' ),
	),
	// Rejilla de productos de PRO Elements; la tabla, el filtro, la vista rápida y la carga por AJAX no se trasladan.
	// La piel de tabla pasa a la presentación «Tabla» de Productos: mismas columnas y encabezados, SKU bajo el
	// título, y buscador, orden, paginación y resumen donde DataTables los mostraba.
	'filter'    => static function ( array $out ) {
		$table = 'bdt-table' === ( $out['_skin'] ?? '' );
		$set   = Digitalisimo\Elements\Legacy\Translator::wc_products( $out, array( 'per_page' => array( 'posts_per_page', 8 ), 'columns' => array( 'columns', '4' ), 'image' => array( 'show_thumb', 'yes' ), 'title' => array( 'show_title', 'yes' ), 'title_tag' => array( 'title_tags', 'h2' ), 'excerpt' => array( 'show_excerpt', '' ), 'excerpt_length' => array( 'excerpt_limit', 10 ), 'category' => array( 'show_categories', '' ), 'rating' => array( 'show_rating', 'yes' ), 'price' => array( 'show_price', 'yes' ), 'cart' => array( 'show_cart', 'yes' ), 'badge' => array( 'show_badge', '' ), 'pagination' => array( 'show_pagination', '' ) ) );
		if ( ! $table ) {
			return $set;
		}
		$on      = static function ( $key, $fallback = '' ) use ( $out ) {
			return 'yes' === ( $out[ $key ] ?? $fallback );
		};
		$columns = array( 'image' => array( 'show_thumb', 'yes', 'Image' ), 'title' => array( 'show_title', 'yes', 'Title' ), 'excerpt' => array( 'show_excerpt', 'yes', 'Description' ), 'category' => array( 'show_categories', '', 'Categories' ), 'tags' => array( 'show_tags', '', 'Tags' ), 'rating' => array( 'show_rating', 'yes', 'Rating' ), 'price' => array( 'show_price', 'yes', 'Price' ), 'quantity' => array( 'show_quantity', '', 'Quantity' ), 'cart' => array( 'show_cart', 'yes', 'Cart' ) );
		$parts   = array();
		foreach ( $columns as $part => $column ) {
			if ( $on( $column[0], $column[1] ) ) {
				$parts[] = $part;
			}
			$set[ 'digitalisimo_wc_th_' . $part ] = $column[2];
		}
		if ( in_array( 'title', $parts, true ) ) {
			$parts[] = 'sku';
		}
		$set['digitalisimo_wc_parts']           = implode( ',', $parts );
		$set['digitalisimo_wc_layout']          = 'table';
		$set['digitalisimo_products_limit']     = $set['posts_per_page'];
		$set['paginate']                        = '';
		$set['digitalisimo_wc_table_search']    = $on( 'show_searching', 'yes' ) ? 'yes' : '';
		$set['digitalisimo_wc_table_sort']      = $on( 'show_ordering', 'yes' ) ? 'yes' : '';
		$set['digitalisimo_wc_table_paginate']  = $on( 'show_pagination' ) ? 'yes' : '';
		$set['digitalisimo_wc_table_info']      = $on( 'show_pagination' ) && $on( 'show_info' ) ? 'yes' : '';
		$set['digitalisimo_wc_table_page_size'] = max( 1, (int) ( $out['show_per_page'] ?? 10 ) );
		foreach ( array( 'show_tags', 'show_quantity', 'show_searching', 'show_ordering', 'show_info', 'show_per_page', 'show_change_length', 'show_quick_view' ) as $key ) {
			unset( $set[ $key ] );
		}
		return $set;
	},
);
