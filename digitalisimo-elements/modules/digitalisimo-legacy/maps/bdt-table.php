<?php
/** Element Pack Pro 9.9.1 `bdt-table` → `digitalisimo-table`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-table',
	// Clases de Element Pack más usadas en sus selectores: .bdt-table, .bdt-static-table, .bdt-static-body-row-cell-icon, .bdt-static-column-cell, .bdt-static-column-cell-icon, .bdt-static-body-row-cell, .bdt-static-body-row-cell-text
	'classes'   => array(
		'.bdt-table table thead th' => '.digi-table thead th',
		'.bdt-table table tbody td' => '.digi-table tbody td',
		'.bdt-table table'          => '.digi-table',
		'.bdt-table'                => '.digi-table-wrap',
	),
	'defaults'  => array(
		'source' => 'custom',
		'content' => '<table><thead><tr><th>Name</th><th>Age</th><th>Phone</th></tr></thead><tbody><tr><td>Tom</td><td>5</td><td>010281065</td></tr><tr><td>Jerry</td><td>4</td><td>012540515</td></tr><tr><td>Halum</td><td>12</td><td>011511441</td></tr></tbody></table>',
		'file' => array(
			'url' => $placeholder_url,
		),
		'delimiter' => ';',
		'header_align' => 'center',
		'body_align' => 'center',
		'use_data_table' => 'yes',
		'hide_entries_on_mobile' => 'yes',
		'table_responsive_control' => 'table_responsive_2',
		'cache_refresh' => '1',
		'meta_post_id' => '',
		'meta_repeater_field_name' => '',
		'static_row_starts' => 'Row Starts',
		'show_searching' => 'yes',
		'show_ordering' => 'yes',
		'show_pagination' => 'yes',
		'show_info' => 'yes',
		'table_border_style' => 'solid',
		'table_border_width' => array(
			'size' => 1,
		),
		'table_border_color' => '#ccc',
		'header_background' => '#e7ebef',
		'header_color' => '#333',
		'header_border_style' => 'solid',
		'header_border_width' => array(
			'size' => 1,
		),
		'header_border_color' => '#ccc',
		'header_padding' => array(
			'top' => 1,
			'bottom' => 1,
			'left' => 1,
			'right' => 2,
			'unit' => 'em',
		),
		'normal_background' => '#fff',
		'cell_border_style' => 'solid',
		'cell_border_width' => array(
			'size' => 1,
		),
		'normal_border_color' => '#ccc',
		'cell_padding' => array(
			'top' => 0.5,
			'bottom' => 0.5,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'stripe_background' => '#f5f5f5',
		'leading_column_border_style' => 'solid',
		'leading_column_border_width' => array(
			'size' => 1,
		),
		'leading_column_padding' => array(
			'top' => 0.5,
			'bottom' => 0.5,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'leading_column_normal_background' => '#fff',
		'leading_column_normal_border_color' => '#ccc',
		'datatable_header_space' => array(
			'size' => 1,
		),
		'datatable_footer_space' => array(
			'size' => 1,
		),
	),
	'repeaters' => array(
		'meta_table_columns' => array(
			'defaults'     => array(
				'meta_field_name' => '',
			),
			'default_rows' => array( array(
					'meta_field_name' => 'name',
				), array(
					'meta_field_name' => 'age',
				) ),
		),
		'static_columns_data' => array(
			'defaults'     => array(
				'static_column_name' => 'Column One',
				'static_column_media' => 'none',
				'static_column_image' => array(
					'url' => $placeholder_url,
				),
			),
			'default_rows' => array( array(
					'static_column_name' => 'Name',
				), array(
					'static_column_name' => 'Age',
				), array(
					'static_column_name' => 'Phone',
				) ),
		),
		'static_rows_data' => array(
			'defaults'     => array(
				'static_row_column_type' => 'row',
				'static_row_media' => 'none',
				'static_row_image' => array(
					'url' => $placeholder_url,
				),
			),
			'default_rows' => array( array(
					'static_row_column_type' => 'row',
					'static_row_starts' => 'Row Starts',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => 'Tom',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => '5',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => '012540515',
				), array(
					'static_row_column_type' => 'row',
					'static_row_starts' => 'Row Starts',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => 'Jerry',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => '4',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => '010281065',
				), array(
					'static_row_column_type' => 'row',
					'static_row_starts' => 'Row Starts',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => 'Halum',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => '12',
				), array(
					'static_row_column_type' => 'column',
					'static_cell_name' => '011511441',
				) ),
		),
	),
	/*
	 * Element Pack guardaba una tabla HTML, filas escritas en el widget o un CSV. El widget propio usa
	 * CSV: se convierten las dos primeras y un CSV del propio sitio pasa a ser el adjunto de Medios.
	 * Google Sheets y campos ACF no tienen equivalente y la tabla queda vacía.
	 */
	'filter'    => static function ( array $out ) {
		$rows   = array();
		$source = (string) ( $out['source'] ?? 'custom' );
		if ( 'custom' === $source && class_exists( 'DOMDocument' ) && '' !== trim( (string) ( $out['content'] ?? '' ) ) ) {
			$doc = new \DOMDocument();
			libxml_use_internal_errors( true );
			$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $out['content'] . '</div>' );
			libxml_clear_errors();
			foreach ( $doc->getElementsByTagName( 'tr' ) as $tr ) {
				$row = array();
				foreach ( $tr->childNodes as $cell ) {
					if ( in_array( strtolower( (string) $cell->nodeName ), array( 'td', 'th' ), true ) ) {
						$row[] = trim( preg_replace( '/\s+/u', ' ', $cell->textContent ) );
					}
				}
				if ( $row ) {
					$rows[] = $row;
				}
			}
		} elseif ( 'static' === $source ) {
			$rows[] = array_map( static function ( $column ) {
				return trim( wp_strip_all_tags( (string) ( $column['static_column_name'] ?? '' ) ) );
			}, (array) ( $out['static_columns_data'] ?? array() ) );
			$current = null;
			foreach ( (array) ( $out['static_rows_data'] ?? array() ) as $cell ) {
				if ( 'row' === ( $cell['static_row_column_type'] ?? 'row' ) ) {
					if ( null !== $current ) {
						$rows[] = $current;
					}
					$current = array();
				} else {
					$current   = $current ?? array();
					$current[] = trim( wp_strip_all_tags( (string) ( $cell['static_cell_name'] ?? '' ) ) );
				}
			}
			if ( $current ) {
				$rows[] = $current;
			}
		}
		$delimiters = array( ',' => 'comma', ';' => 'semicolon', "\t" => 'tab', '\\t' => 'tab' );
		if ( $rows ) {
			$handle = fopen( 'php://temp', 'r+' );
			foreach ( $rows as $row ) {
				fputcsv( $handle, $row, ',', '"', '\\' );
			}
			rewind( $handle );
			$out['content']   = trim( (string) stream_get_contents( $handle ) );
			$out['source']    = 'manual';
			$out['delimiter'] = 'comma';
			fclose( $handle );
		} elseif ( 'csv_file' === $source ) {
			$url = (string) ( $out['file']['url'] ?? '' );
			$id  = '' !== $url && function_exists( 'attachment_url_to_postid' ) ? attachment_url_to_postid( $url ) : 0;
			$out['source']    = $id ? 'media' : 'manual';
			$out['csv_file']  = array( 'id' => $id, 'url' => $id ? $url : '' );
			$out['content']   = '';
			$out['delimiter'] = $delimiters[ (string) ( $out['delimiter'] ?? ';' ) ] ?? 'comma';
		} else {
			$out['source']  = 'manual';
			$out['content'] = '';
		}
		foreach ( array( 'file', 'static_columns_data', 'static_rows_data', 'meta_table_columns', 'meta_post_id', 'meta_repeater_field_name', 'google_sheet_id', 'google_sheet_range', 'google_sheet_cache', 'cache_refresh', 'use_data_table' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
