<?php
/** Element Pack Pro 9.9.1 `bdt-marker` → `hotspot`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'hotspot',
	// Clases de Element Pack más usadas en sus selectores: .bdt-marker-wrapper, .bdt-marker, .bdt-marker-item, .bdt-marker-animated
	'classes'   => array(),
	'defaults'  => array(
		'image' => array(
			'url' => $placeholder_url,
		),
		'image_size' => 'large',
		'align' => 'center',
		'caption' => '',
		'marker_animation' => 'yes',
		'marker_tooltip_animation' => 'shift-toward',
		'marker_tooltip_x_offset' => array(
			'size' => 0,
		),
		'marker_tooltip_y_offset' => array(
			'size' => 0,
		),
		'space' => array(
			'size' => 100,
			'unit' => '%',
		),
		'space_tablet' => array(
			'unit' => '%',
		),
		'space_mobile' => array(
			'unit' => '%',
		),
		'opacity' => array(
			'size' => 1,
		),
		'caption_align' => '',
		'marker_opacity' => array(
			'size' => 1,
		),
		'marker_tooltip_text_align' => 'center',
	),
	'repeaters' => array(
		'markers' => array(
			'defaults'     => array(
				'select_type' => 'icon',
				'text' => 'Marker',
				'image' => array(
					'url' => $placeholder_url,
				),
				'marker_invisible_height' => array(
					'size' => 20,
				),
				'marker_invisible_width' => array(
					'size' => 20,
				),
				'link_to' => '',
				'marker_link' => array(
					'url' => '#',
				),
				'image_link' => array(
					'url' => $placeholder_url,
				),
				'css_classes' => '',
				'marker_tooltip' => 'yes',
				'marker_title' => 'Tooltip Text Here',
				'marker_tooltip_placement' => 'top',
			),
			'default_rows' => array( array(
					'marker_title' => 'Marker #1',
					'marker_x_position' => array(
						'size' => 50,
						'unit' => '%',
					),
					'marker_y_position' => array(
						'size' => 50,
						'unit' => '%',
					),
				), array(
					'marker_title' => 'Marker #2',
					'marker_x_position' => array(
						'size' => 30,
						'unit' => '%',
					),
					'marker_y_position' => array(
						'size' => 30,
						'unit' => '%',
					),
				), array(
					'marker_title' => 'Marker #3',
					'marker_x_position' => array(
						'size' => 80,
						'unit' => '%',
					),
					'marker_y_position' => array(
						'size' => 20,
						'unit' => '%',
					),
				) ),
		),
	),
	// Cada marcador es un punto del Hotspot de PRO Elements en la misma posición (% desde arriba y la izquierda),
	// con su icono o texto, su enlace y su tooltip. La leyenda de la imagen, el enlace al lightbox y los marcadores
	// de imagen no se trasladan.
	'filter'    => static function ( array $out ) {
		$base  = static function ( $placement ) {
			$placement = explode( '-', (string) $placement )[0];
			return in_array( $placement, array( 'top', 'bottom', 'left', 'right' ), true ) ? $placement : 'top';
		};
		$items = array();
		$first = '';
		foreach ( is_array( $out['markers'] ?? null ) ? $out['markers'] : array() as $i => $row ) {
			$type      = (string) ( $row['select_type'] ?? 'icon' );
			$placement = $base( $row['marker_tooltip_placement'] ?? 'top' );
			$first     = '' === $first ? $placement : $first;
			$items[]   = array(
				'_id'                      => (string) ( $row['_id'] ?? 'epmarker' . $i ),
				'hotspot_label'            => 'text' === $type ? trim( wp_strip_all_tags( (string) ( $row['text'] ?? '' ) ) ) : '',
				'hotspot_icon'             => 'icon' === $type && is_array( $row['marker_select_icon'] ?? null ) ? $row['marker_select_icon'] : array( 'value' => '', 'library' => '' ),
				'hotspot_link'             => 'custom' === ( $row['link_to'] ?? '' ) && is_array( $row['marker_link'] ?? null ) ? $row['marker_link'] : array( 'url' => '' ),
				'hotspot_tooltip_content'  => 'yes' === ( $row['marker_tooltip'] ?? 'yes' ) ? (string) ( $row['marker_title'] ?? '' ) : '',
				'hotspot_horizontal'       => 'left',
				'hotspot_offset_x'         => array( 'size' => (float) ( $row['marker_x_position']['size'] ?? 50 ), 'unit' => '%' ),
				'hotspot_vertical'         => 'top',
				'hotspot_offset_y'         => array( 'size' => (float) ( $row['marker_y_position']['size'] ?? 50 ), 'unit' => '%' ),
				'hotspot_tooltip_position' => 'yes',
				'hotspot_position'         => $placement,
			);
		}
		$set = array(
			'hotspot'          => $items,
			'tooltip_position' => '' !== $first ? $first : 'top',
			'tooltip_trigger'  => 'yes' === ( $out['marker_always_visible_tooltip'] ?? '' ) ? 'none' : ( 'yes' === ( $out['marker_tooltip_trigger'] ?? '' ) ? 'click' : 'mouseenter' ),
			'hotspot_animation' => 'yes' === ( $out['marker_animation'] ?? 'yes' ) ? 'e-hotspot--soft-beat' : '',
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(markers|caption|caption_align|marker_.*|space|opacity|tooltip_size|align)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
