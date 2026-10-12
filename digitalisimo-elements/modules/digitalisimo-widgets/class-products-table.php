<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Presentación «Tabla» del widget Productos: los mismos productos que pinta el widget (se leen de su HTML,
 * así respetan consulta, orden y límite) en una tabla con encabezados, con columnas según las partes
 * elegidas y, si se pide, buscador, orden, paginación y resumen de filas en el navegador. La columna de
 * cantidad sincroniza su valor con el botón de compra de la misma fila.
 */
final class Products_Table {
	/** Columna => [ etiqueta por defecto, control de su encabezado ]. */
	const COLUMNS = array(
		'image'    => array( 'Imagen', 'digitalisimo_wc_th_image' ),
		'title'    => array( 'Producto', 'digitalisimo_wc_th_title' ),
		'excerpt'  => array( 'Descripción', 'digitalisimo_wc_th_excerpt' ),
		'category' => array( 'Categorías', 'digitalisimo_wc_th_category' ),
		'tags'     => array( 'Etiquetas', 'digitalisimo_wc_th_tags' ),
		'rating'   => array( 'Valoración', 'digitalisimo_wc_th_rating' ),
		'price'    => array( 'Precio', 'digitalisimo_wc_th_price' ),
		'quantity' => array( 'Cantidad', 'digitalisimo_wc_th_quantity' ),
		'cart'     => array( 'Carrito', 'digitalisimo_wc_th_cart' ),
	);
	const FEATURES = array( 'search' => 'digitalisimo_wc_table_search', 'sort' => 'digitalisimo_wc_table_sort', 'paginate' => 'digitalisimo_wc_table_paginate', 'info' => 'digitalisimo_wc_table_info' );

	public static function register_controls( $widget, $c ) {
		$table = array( 'digitalisimo_wc_layout' => 'table' );
		foreach ( self::COLUMNS as $column ) {
			$widget->add_control( $column[1], array( 'label' => 'Encabezado: ' . strtolower( $column[0] ), 'type' => $c::TEXT, 'default' => $column[0], 'condition' => $table ) );
		}
		$widget->add_control( 'digitalisimo_wc_table_search', array( 'label' => 'Buscador', 'type' => $c::SWITCHER, 'condition' => $table, 'separator' => 'before' ) );
		$widget->add_control( 'digitalisimo_wc_table_sort', array( 'label' => 'Ordenar al pulsar un encabezado', 'type' => $c::SWITCHER, 'condition' => $table ) );
		$widget->add_control( 'digitalisimo_wc_table_paginate', array( 'label' => 'Paginación', 'type' => $c::SWITCHER, 'condition' => $table ) );
		$widget->add_control( 'digitalisimo_wc_table_page_size', array( 'label' => 'Filas por página', 'type' => $c::NUMBER, 'default' => 10, 'min' => 1, 'max' => 500, 'condition' => $table + array( 'digitalisimo_wc_table_paginate' => 'yes' ) ) );
		$widget->add_control( 'digitalisimo_wc_table_info', array( 'label' => 'Resumen de filas', 'type' => $c::SWITCHER, 'condition' => $table ) );
	}

	/** IDs de producto en el orden en que el widget los pintó. */
	public static function ids( $content ) {
		preg_match_all( '#<li\b[^>]*class="[^"]*\bpost-(\d+)\b[^"]*"#', (string) $content, $m );
		return array_values( array_unique( array_map( 'intval', $m[1] ) ) );
	}

	/** Partes elegidas: las del widget (lista o texto separado por comas) o, si no eligió, las de una tabla básica. */
	public static function parts( array $s ) {
		$parts = $s['digitalisimo_wc_parts'] ?? '';
		$parts = is_array( $parts ) ? $parts : array_filter( explode( ',', (string) $parts ) );
		if ( 'yes' !== ( $s['digitalisimo_wc_custom'] ?? '' ) && ( is_array( $s['digitalisimo_wc_parts'] ?? null ) || ! $parts ) ) {
			$parts = array( 'image', 'title', 'price', 'cart' );
		}
		return array_values( array_map( 'strval', $parts ) );
	}

	private static function cell_text( $html ) {
		return '' === trim( wp_strip_all_tags( (string) $html ) ) && false === strpos( (string) $html, '<img' ) && false === strpos( (string) $html, '<input' ) ? '' : (string) $html;
	}

	/** HTML de la tabla para esos productos y ajustes. */
	public static function html( array $ids, array $s ) {
		$parts   = self::parts( $s );
		$columns = array();
		foreach ( self::COLUMNS as $key => $column ) {
			$on = 'excerpt' === $key ? ( in_array( 'excerpt', $parts, true ) || in_array( 'summary', $parts, true ) ) : in_array( $key, $parts, true );
			if ( $on ) {
				$label            = trim( wp_strip_all_tags( (string) ( $s[ $column[1] ] ?? $column[0] ) ) );
				$columns[ $key ] = '' !== $label ? $label : $column[0];
			}
		}
		if ( ! $columns || ! $ids ) {
			return '';
		}
		$tag     = in_array( $s['digitalisimo_wc_title_tag'] ?? 'h2', Products_Parts::TITLE_TAGS, true ) ? (string) $s['digitalisimo_wc_title_tag'] : 'h2';
		$words   = max( 1, (int) ( $s['digitalisimo_wc_excerpt_length'] ?? 10 ) );
		$summary = in_array( 'summary', $parts, true );
		$rows    = '';
		global $post, $product;
		$saved_post    = $post;
		$saved_product = $product;
		foreach ( $ids as $id ) {
			$item = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $item ) {
				continue;
			}
			$post    = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura al terminar.
			$product = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura al terminar.
			setup_postdata( $post );
			$link  = esc_url( get_permalink( $id ) );
			$cells = array();
			foreach ( array_keys( $columns ) as $key ) {
				switch ( $key ) {
					case 'image':
						$cells[ $key ] = '<a href="' . $link . '">' . $item->get_image( 'woocommerce_thumbnail' ) . '</a>';
						break;
					case 'title':
						$sku           = in_array( 'sku', $parts, true ) ? (string) $item->get_sku() : '';
						$cells[ $key ] = '<' . $tag . ' class="digi-products-table__title"><a href="' . $link . '">' . esc_html( get_the_title( $id ) ) . '</a></' . $tag . '>' . ( '' !== $sku ? '<span class="digi-products-table__sku">' . esc_html( $sku ) . '</span>' : '' );
						break;
					case 'excerpt':
						$text          = $summary && has_excerpt( $id ) ? wp_strip_all_tags( (string) get_the_excerpt( $id ) ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) ), $words, $summary ? '…' : '' );
						$cells[ $key ] = esc_html( trim( $text ) );
						break;
					case 'category':
						$cells[ $key ] = wp_kses_post( (string) wc_get_product_category_list( $id, ', ' ) );
						break;
					case 'tags':
						$cells[ $key ] = wp_kses_post( (string) wc_get_product_tag_list( $id, ', ' ) );
						break;
					case 'rating':
						$cells[ $key ] = wc_get_rating_html( $item->get_average_rating(), $item->get_rating_count() );
						break;
					case 'price':
						$cells[ $key ] = (string) $item->get_price_html();
						break;
					case 'quantity':
						$cells[ $key ] = $item->is_type( 'simple' ) && $item->is_purchasable() && $item->is_in_stock() ? (string) woocommerce_quantity_input( array(), $item, false ) : '';
						break;
					case 'cart':
						ob_start();
						woocommerce_template_loop_add_to_cart();
						$cells[ $key ] = (string) ob_get_clean();
						break;
				}
			}
			$rows .= '<tr>';
			foreach ( $cells as $key => $cell ) {
				$rows .= '<td class="digi-products-table__' . $key . '">' . self::cell_text( $cell ) . '</td>';
			}
			$rows .= '</tr>';
		}
		$post    = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restauración.
		$product = $saved_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restauración.
		wp_reset_postdata();
		if ( '' === $rows ) {
			return '';
		}
		$features = '';
		foreach ( self::FEATURES as $feature => $key ) {
			$features .= 'yes' === ( $s[ $key ] ?? '' ) ? ' data-' . $feature : '';
		}
		if ( '' !== $features ) {
			$features = ' data-digi-table' . $features . ( false !== strpos( $features, 'data-paginate' ) ? ' data-page-size="' . esc_attr( (string) max( 1, min( 500, (int) ( $s['digitalisimo_wc_table_page_size'] ?? 10 ) ) ) ) . '"' : '' );
		}
		$head = '';
		foreach ( $columns as $key => $label ) {
			$head .= '<th scope="col" class="digi-products-table__' . $key . '">' . esc_html( $label ) . '</th>';
		}
		return '<div class="digi-table-wrap digi-products-table" role="region" aria-label="Productos" tabindex="0"' . $features . '><table class="digi-table"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
	}
}
