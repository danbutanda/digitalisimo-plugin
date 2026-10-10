<?php
/** Element Pack Pro 9.9.1 `bdt-open-street-map` → `digitalisimo-map`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-map',
	'classes'   => array(),
	'defaults'  => array(
		'zoom_control' => 'yes',
		'zoom' => array(
			'size' => 15,
		),
	),
	'repeaters' => array(
		'markers' => array(
			'defaults'     => array(
				'marker_title' => 'Marker #1',
				'marker_lat' => '24.82391',
				'marker_lng' => '89.38414',
				'marker_content' => 'Your Business Address Here',
			),
			'default_rows' => array( array(
					'marker_lat' => '24.82391',
					'marker_lng' => '89.38414',
					'marker_title' => 'Marker #1',
					'marker_content' => '<strong>BdThemes Limited</strong>,<br>Latifpur, Bogra - 5800,<br>Bangladesh',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$markers = array();
		foreach ( is_array( $out['markers'] ?? null ) ? $out['markers'] : array() as $row ) {
			$markers[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'title' => (string) ( $row['marker_title'] ?? '' ), 'lat' => (string) ( $row['marker_lat'] ?? '' ), 'lng' => (string) ( $row['marker_lng'] ?? '' ), 'content' => (string) ( $row['marker_content'] ?? '' ) );
		}
		$out['markers'] = $markers;
		unset( $out['zoom_control'] );
		return $out;
	},
);
