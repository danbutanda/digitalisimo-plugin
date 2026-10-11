<?php
/** Element Pack Pro 9.9.1 `bdt-scrollnav` → `digitalisimo-vertical-menu`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-vertical-menu',
	'classes'   => array(),
	'defaults'  => array(
		'nav_style' => 'default',
		'alignment' => 'left',
		'nav_position' => 'center-left',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'content_offset' => array(
			'size' => 0,
		),
		'dotnav_tooltip_animation' => 'shift-toward',
		'dotnav_tooltip_placement' => 'right',
		'dotnav_tooltip_x_offset' => array(
			'size' => 0,
		),
		'dotnav_tooltip_y_offset' => array(
			'size' => 0,
		),
		'navbar_style' => '',
		'dotnav_tooltip_text_align' => 'center',
	),
	'repeaters' => array(
		'navs' => array(
			'defaults'     => array(
				'nav_title' => 'Nav Title',
				'nav_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'nav_title' => 'Nav #1',
					'nav_link' => array(
						'url' => '#section-1',
					),
				), array(
					'nav_title' => 'Nav #2',
					'nav_link' => array(
						'url' => '#section-2',
					),
				), array(
					'nav_title' => 'Nav #3',
					'nav_link' => array(
						'url' => '#section-3',
					),
				), array(
					'nav_title' => 'Nav #4',
					'nav_link' => array(
						'url' => '#section-4',
					),
				), array(
					'nav_title' => 'Nav #5',
					'nav_link' => array(
						'url' => '#section-5',
					),
				) ),
		),
	),
	// Enlaces a secciones de la página como menú; en horizontal se reparten en columnas. El fijado y los puntos no se trasladan.
	'filter'    => static function ( array $out ) {
		$items = array();
		foreach ( is_array( $out['navs'] ?? null ) ? $out['navs'] : array() as $row ) {
			$link    = $row['nav_link'] ?? '';
			$items[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'item_title' => trim( wp_strip_all_tags( (string) ( $row['nav_title'] ?? '' ) ) ), 'item_link' => is_array( $link ) ? $link : array( 'url' => (string) $link ), 'item_icon' => is_array( $row['scroll_nav_icon'] ?? null ) ? $row['scroll_nav_icon'] : array(), 'item_level' => '0' );
		}
		$out['source']    = 'static';
		$out['items']     = $items;
		$out['nav_label'] = 'Secciones de la página';
		$out['columns']   = 'yes' === ( $out['vertical_nav'] ?? '' ) ? '1' : (string) max( 1, min( 4, count( $items ) ) );
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(navs|nav_style|vertical_nav|fixed_nav|nav_position|icon_align|content_offset|dotnav_.*|navbar_style)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
