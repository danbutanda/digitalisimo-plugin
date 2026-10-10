<?php
/** Element Pack Pro 9.9.1 `bdt-sub-menu` → `digitalisimo-vertical-menu`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-vertical-menu',
	'classes'   => array(
		'.ep-sub-menu .ep-advance-menu .ep-item' => '.digi-vertical-menu .digi-vertical-menu__link',
		'.ep-sub-menu .ep-sub-menu-grid'         => '.digi-vertical-menu > .digi-vertical-menu__list',
		'.ep-sub-menu'                           => '.digi-vertical-menu',
		'.ep-item'                               => '.digi-vertical-menu__link',
		'.ep-title'                              => '.digi-vertical-menu__text',
		'.ep-heading'                            => '.digi-vertical-menu__heading',
		'.ep-icon-inner'                         => '.digi-vertical-menu__icon',
		'.ep-badge'                              => '.digi-vertical-menu__badge',
		'.ep-sub-title'                          => '.digi-vertical-menu__description',
		'.ep-hover-icon'                         => '.bdt-hover-icon',
	),
	'defaults'  => array(
		'navbar' => 0,
		'submenu_style' => '1',
		'submenu_columns' => '2',
		'show_submenu_heading' => 'yes',
		'submenu_header_title' => 'Advanced Sub Menu',
		'submenu_header_alignment' => 'start',
		'show_sub_menu_sub_title' => 'yes',
		'show_sub_menu_arrows' => 'yes',
		'submenu_heading_color' => '',
		'submenu_heading_h_color' => '',
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'submenu_icon_ordering' => 'center',
		'icon_menu_icon_shape_size' => array(
			'unit' => 'px',
		),
		'icon_menu_icon_shape_border_radius' => array(
			'unit' => 'px',
		),
		'icon_shape_position_x' => array(
			'unit' => 'px',
			'size' => 0,
		),
		'icon_shape_position_y' => array(
			'unit' => 'px',
			'size' => 0,
		),
	),
	'repeaters' => array(
		'menus' => array(
			'defaults'     => array(
				'menu_link' => array(
					'url' => '#',
				),
				'menu_icon_shape' => 'no',
			),
			'default_rows' => array( array(
					'menu_title' => 'About',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Gallery 01',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'About',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Gallery 01',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'About',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Gallery 01',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'About',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Gallery 01',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'About',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Gallery 01',
					'menu_sub_title' => 'Trending Design inspire to you',
					'menu_link' => '#',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$items = array();
		foreach ( is_array( $out['menus'] ?? null ) ? $out['menus'] : array() as $row ) {
			$items[] = array(
				'_id'              => (string) ( $row['_id'] ?? '' ),
				'item_title'       => trim( wp_strip_all_tags( (string) ( $row['menu_title'] ?? '' ) ) ),
				// Las filas de ejemplo guardan la URL como texto («#»).
				'item_link'        => is_array( $row['menu_link'] ?? null ) ? $row['menu_link'] : ( is_string( $row['menu_link'] ?? null ) && '' !== $row['menu_link'] ? array( 'url' => $row['menu_link'] ) : array() ),
				'item_icon'        => is_array( $row['menu_icon'] ?? null ) ? $row['menu_icon'] : array(),
				'item_description' => trim( wp_strip_all_tags( (string) ( $row['menu_sub_title'] ?? '' ) ) ),
				'item_badge'       => trim( wp_strip_all_tags( (string) ( $row['menu_badge'] ?? '' ) ) ),
				'item_level'       => '0',
			);
		}
		$out['source']            = 'yes' === ( $out['dynamic_menu'] ?? '' ) ? 'menu' : 'static';
		$out['menu']              = '0' === (string) ( $out['navbar'] ?? '' ) ? '' : (string) ( $out['navbar'] ?? '' );
		$out['items']             = $items;
		$out['mode']              = 'collapse';
		$out['open_current']      = '';
		$out['parent_toggles']    = '';
		// Con un menú de WordPress Element Pack sólo mostraba el primer nivel.
		$out['max_depth']         = 'menu' === $out['source'] ? '1' : '';
		$out['heading']           = 'yes' === ( $out['show_submenu_heading'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['submenu_header_title'] ?? '' ) ) ) : '';
		// Element Pack no mostraba subtítulos en un menú de WordPress.
		$out['show_descriptions'] = 'menu' !== $out['source'] && 'yes' === ( $out['show_sub_menu_sub_title'] ?? '' ) ? 'yes' : '';
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			if ( isset( $out[ 'submenu_columns' . $device ] ) ) {
				$out[ 'columns' . $device ] = (string) $out[ 'submenu_columns' . $device ];
			}
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(dynamic_menu|navbar|menus|submenu_style|show_submenu_heading|submenu_header_title|show_sub_menu_sub_title|show_sub_menu_arrows|icons_hide_on|sub_title_hide_on|glassmorphism_effect|submenu_columns(_tablet|_mobile)?)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
