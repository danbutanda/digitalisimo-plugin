<?php
/** Element Pack Pro 9.9.1 `bdt-marquee` → `digitalisimo-marquee`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-marquee',
	'classes'   => array( '.bdt-marquee' => '.digi-marquee' ),
	'defaults'  => array(
		'marquee_type' => 'text',
		'thumbnail_size' => 'medium',
		'marquee_speed' => 50,
		'marquee_direction' => 'left',
		'marquee_vertical_height' => array(
			'unit' => 'px',
			'size' => 500,
		),
		'marquee_shadow_width' => array(
			'unit' => '%',
		),
		'marquee_icon_color' => '#fff',
	),
	'repeaters' => array(
		'marquee_type_text' => array(
			'defaults'     => array(
				'marquee_link' => array(
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
				),
				'marquee_icon_position' => 'before',
				'marquee_color' => '',
			),
			'default_rows' => array( array(
					'marquee_content' => 'Element Pack',
				), array(
					'marquee_content' => 'Prime Slider ',
				), array(
					'marquee_content' => 'Ultimate Post Kit',
				), array(
					'marquee_content' => 'Ultimate Store Kit',
				), array(
					'marquee_content' => 'Pixel Gallery',
				), array(
					'marquee_content' => 'Live Copy Paste',
				) ),
		),
		'marquee_type_images' => array(
			'defaults'     => array(
				'marquee_image_link' => array(
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
				),
			),
			'default_rows' => array( array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'marquee_image' => array(
						'url' => $placeholder_url,
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$items = array();
		if ( 'image' === ( $out['marquee_type'] ?? 'text' ) ) {
			foreach ( is_array( $out['marquee_type_images'] ?? null ) ? $out['marquee_type_images'] : array() as $row ) {
				$items[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'text' => '', 'image' => is_array( $row['marquee_image'] ?? null ) ? $row['marquee_image'] : array(), 'link' => is_array( $row['marquee_image_link'] ?? null ) ? $row['marquee_image_link'] : array() );
			}
		} else {
			foreach ( is_array( $out['marquee_type_text'] ?? null ) ? $out['marquee_type_text'] : array() as $row ) {
				$items[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'text' => (string) ( $row['marquee_content'] ?? '' ), 'image' => array(), 'link' => is_array( $row['marquee_link'] ?? null ) ? $row['marquee_link'] : array() );
			}
		}
		$out['source']         = 'items';
		$out['items']          = $items;
		// Element Pack medía la velocidad en píxeles por segundo: a 50, una vuelta dura unos 30 s.
		$out['duration']       = max( 5, min( 300, (int) round( 1500 / max( 1, (int) ( $out['marquee_speed'] ?? 50 ) ) ) ) );
		$out['direction']      = 'right' === ( $out['marquee_direction'] ?? 'left' ) ? 'right' : 'left';
		$out['pause_on_hover'] = 'yes' === ( $out['marquee_pause_on_hover'] ?? '' ) ? 'yes' : '';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(marquee_.*|skin_shadow_.*)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
