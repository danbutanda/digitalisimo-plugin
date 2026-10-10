<?php
/** Element Pack Pro 9.9.1 `bdt-chart` → `digitalisimo-chart`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-chart',
	'classes'   => array(),
	'defaults'  => array(
		'type' => 'bar',
		'labels' => 'January; February; March; April; May',
		'single_label' => 'Polar Dataset Label',
		'single_datasets' => '10; 20; 30; 40; 50',
		'single_bg_colors' => 'rgba(255, 99, 132, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 206, 86, 0.2); rgba(75, 192, 192, 0.2); rgba(153, 102, 255, 0.2)',
		'show_grid_lines' => 'yes',
		'show_labels' => 'yes',
		'show_legend' => 'yes',
		'show_tooltip' => 'yes',
		'maintain_aspect_ratio' => 'yes',
		'show_prefix' => 'no',
		'y_custom_prefix' => '$',
		'show_suffix' => 'no',
		'y_custom_suffix' => '%',
		'value_separator' => 'no',
		'separator_symbol' => ',',
		'xAxes_separator' => 'no',
		'yAxes_separator' => 'yes',
		'font_family' => '\'Open Sans\', sans-serif',
		'grid_color' => 'rgba(0,0,0,0.05)',
	),
	'repeaters' => array(
		'datasets' => array(
			'defaults'     => array(
				'label' => 'Dataset Label',
				'data' => '2; 4; 8; 16; 32',
				'advanced_bg_color' => 'no',
				'bg_color' => 'rgba(255, 99, 132, 0.2)',
				'advanced_border_color' => 'no',
			),
			'default_rows' => array( array(
					'label' => 'Dataset Label #1',
					'data' => '2; 4; 8; 16; 32',
					'bg_color' => 'rgba(255, 99, 132, 0.2)',
					'bg_colors' => 'rgba(255, 99, 132, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 206, 86, 0.2); rgba(75, 192, 192, 0.2); rgba(153, 102, 255, 0.2)',
				), array(
					'label' => 'Dataset Label #2',
					'data' => '8; 25; 6; 8; 10',
					'bg_color' => 'rgba(54, 162, 235, 0.2)',
					'bg_colors' => 'rgba(153, 102, 255, 0.2); rgba(75, 192, 192, 0.2); rgba(255, 206, 86, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 99, 132, 0.2)',
				), array(
					'label' => 'Dataset Label #3',
					'data' => '9; 4; 30; 8; 32',
					'bg_color' => 'rgba(75, 192, 192, 0.2)',
					'bg_colors' => 'rgba(255, 99, 132, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 206, 86, 0.2); rgba(75, 192, 192, 0.2); rgba(153, 102, 255, 0.2)',
				), array(
					'label' => 'Dataset Label #4',
					'data' => '10; 15; 16; 28; 15',
					'bg_color' => 'rgba(255, 206, 86, 0.2)',
					'bg_colors' => 'rgba(153, 102, 255, 0.2); rgba(75, 192, 192, 0.2); rgba(255, 206, 86, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 99, 132, 0.2)',
				), array(
					'label' => 'Dataset Label #5',
					'data' => '32; 15; 8; 4; 2',
					'bg_color' => 'rgba(153, 102, 255, 0.2)',
					'bg_colors' => 'rgba(255, 99, 132, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 206, 86, 0.2); rgba(75, 192, 192, 0.2); rgba(153, 102, 255, 0.2)',
				) ),
		),
		'bubble_datasets' => array(
			'defaults'     => array(
				'label' => 'Bubble Dataset Label',
				'data' => '[20;30;15][40;10;10]',
				'advanced_bg_color' => 'no',
				'bg_color' => 'rgba(255, 99, 132, 0.2)',
				'advanced_border_color' => 'no',
			),
			'default_rows' => array( array(
					'label' => 'Bubble Dataset Label #1',
					'data' => '[20;30;15][40;10;10]',
					'bg_color' => 'rgba(255, 99, 132, 0.2)',
					'bg_colors' => 'rgba(255, 99, 132, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 206, 86, 0.2);',
				), array(
					'label' => 'Bubble Dataset Label #2',
					'data' => '[15;25;5][50;60;8]',
					'bg_color' => 'rgba(54, 162, 235, 0.2)',
					'bg_colors' => 'rgba(153, 102, 255, 0.2); rgba(75, 192, 192, 0.2); rgba(255, 206, 86, 0.2);',
				), array(
					'label' => 'Bubble Dataset Label #3',
					'data' => '[60;5;20][100;50;15]',
					'bg_color' => 'rgba(75, 192, 192, 0.2)',
					'bg_colors' => 'rgba(255, 99, 132, 0.2); rgba(54, 162, 235, 0.2); rgba(255, 206, 86, 0.2);',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$type   = (string) ( $out['type'] ?? 'bar' );
		$single = in_array( $type, array( 'pie', 'doughnut', 'polarArea' ), true );
		$sets   = array();
		if ( $single && '' !== trim( (string) ( $out['single_datasets'] ?? '' ) ) && 'polarArea' === $type ) {
			$sets[] = array( '_id' => 'single', 'label' => (string) ( $out['single_label'] ?? '' ), 'data' => (string) $out['single_datasets'] );
		} else {
			foreach ( is_array( $out['datasets'] ?? null ) ? $out['datasets'] : array() as $row ) {
				$sets[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'label' => (string) ( $row['label'] ?? '' ), 'data' => (string) ( $row['data'] ?? '' ), 'color' => preg_replace( '/,\s*0?\.\d+\)$/', ', 1)', (string) ( $row['bg_color'] ?? '' ) ) );
			}
		}
		$out['chart_type']  = $single ? 'pie' : ( in_array( $type, array( 'line', 'radar' ), true ) ? 'line' : 'bar' );
		$out['datasets']    = $sets;
		$out['show_legend'] = 'yes' === ( $out['show_legend'] ?? '' ) ? 'yes' : '';
		$out['prefix']      = 'yes' === ( $out['show_prefix'] ?? '' ) ? (string) ( $out['y_custom_prefix'] ?? '' ) : '';
		$out['suffix']      = 'yes' === ( $out['show_suffix'] ?? '' ) ? (string) ( $out['y_custom_suffix'] ?? '' ) : '';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(type|single_.*|bubble_datasets|show_grid_lines|show_labels|legend_align|show_tooltip|aspect_ratio|maintain_aspect_ratio|show_prefix|y_custom_prefix|show_suffix|y_custom_suffix|value_separator|separator_symbol|k_formatter|xAxes_separator|yAxes_separator)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
