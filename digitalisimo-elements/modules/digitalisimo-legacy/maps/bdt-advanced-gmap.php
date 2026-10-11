<?php
/** Element Pack Pro 9.9.1 `bdt-advanced-gmap` → `google_maps`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'google_maps',
	// Clases de Element Pack más usadas en sus selectores: .bdt-advanced-map, .bdt-map-tooltip-view, .bdt-search, .bdt-search-default, .bdt-search-input, .bdt-advanced-gmap, .bdt-gmap-list-content, .bdt-tooltip-title, .bdt-tooltip-place, .bdt-tooltip-content, .bdt-gmap-list-item, .bdt-title, .bdt-place, .bdt-gmap-search-wrapper
	'classes'   => array(),
	'defaults'  => array(
		'avd_google_map_zoom_control' => 'yes',
		'avd_google_map_default_zoom' => array(
			'size' => 15,
		),
		'avd_google_map_street_view' => 'yes',
		'avd_google_map_type_control' => 'yes',
		'avd_google_map_disable_scroll_zoom' => 'no',
		'avd_google_map_show_list' => 'no',
		'avd_google_map_list_position' => 'right',
		'search_placeholder_text' => 'Search...',
		'avd_google_map_style' => '',
		'tooltip_width' => array(
			'unit' => '%',
			'size' => 100,
		),
		'tooltip_image_width' => array(
			'unit' => '%',
			'size' => 100,
		),
	),
	'repeaters' => array(
		'marker' => array(
			'defaults'     => array(
				'marker_lat' => '24.8238746',
				'marker_lng' => '89.3816299',
				'marker_title' => 'Another Place',
				'marker_place' => 'Bangladesh',
				'marker_content' => 'Your Business Address Here',
				'marker_phone' => '+880123456789',
				'marker_website' => 'https://bdthemes.com',
				'marker_image' => array(
					'url' => $placeholder_url,
				),
			),
			'default_rows' => array( array(
					'marker_lat' => '24.8248746',
					'marker_lng' => '89.3826299',
					'marker_title' => 'BdThemes',
					'marker_place' => 'Bogura',
					'marker_content' => '<strong>BdThemes Limited</strong>,<br>Latifpur, Bogra - 5800,<br>Bangladesh',
					'marker_phone' => '+880123456789',
					'marker_website' => 'https://bdthemes.com',
				) ),
		),
	),
	// El mapa de Elementor muestra un punto: el primer marcador, por sus coordenadas. Los demás marcadores,
	// la lista, el buscador, los estilos JSON y los globos de información necesitaban la API de Google con clave.
	'filter'    => static function ( array $out ) {
		$markers = is_array( $out['marker'] ?? null ) ? array_values( $out['marker'] ) : array();
		$first   = $markers[0] ?? array();
		$lat     = trim( (string) ( $first['marker_lat'] ?? '' ) );
		$lng     = trim( (string) ( $first['marker_lng'] ?? '' ) );
		$set     = array(
			'address' => is_numeric( $lat ) && is_numeric( $lng ) ? $lat . ',' . $lng : trim( wp_strip_all_tags( (string) ( $first['marker_place'] ?? '' ) ) ),
			'zoom'    => array( 'size' => max( 1, min( 20, (int) ( $out['avd_google_map_default_zoom']['size'] ?? 15 ) ) ), 'unit' => 'px' ),
		);
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			if ( ! empty( $out[ 'avd_google_map_height' . $device ]['size'] ) ) {
				$set[ 'height' . $device ] = $out[ 'avd_google_map_height' . $device ];
			}
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(marker|avd_google_map_.*|gmap_geocode|search_placeholder_text|search_align)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
