<?php
/** Element Pack Pro 9.9.1 `bdt-vertical-menu` → `digitalisimo-vertical-menu`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

$mode = 'collapse';

return array(
	'target'    => 'digitalisimo-vertical-menu',
	'classes'   => array(
		'.bdt-vertical-menu .sidebar-nav .metismenu' => '.digi-vertical-menu > .digi-vertical-menu__list',
		'.bdt-vertical-menu .metismenu'              => '.digi-vertical-menu > .digi-vertical-menu__list',
		'.bdt-vertical-menu .sidebar-nav'            => '.digi-vertical-menu',
		'.bdt-vertical-menu'                         => '.digi-vertical-menu',
		'{{WRAPPER}}.bdt-submenu-type-inner'         => '{{WRAPPER}}',
		' .metismenu'                                => ' .digi-vertical-menu > .digi-vertical-menu__list',
		'> .bdt-menu-arrow::after'                   => '> .digi-vertical-menu__row > .digi-vertical-menu__toggle .digi-vertical-menu__arrow',
		'li>.bdt-menu-arrow'                         => 'li > .digi-vertical-menu__row > .digi-vertical-menu__toggle',
		'li.mm-active > .has-arrow:after'            => 'li.is-open > .digi-vertical-menu__row .digi-vertical-menu__arrow',
		'li.mm-active .has-arrow:after'              => 'li.is-open .digi-vertical-menu__arrow',
		'li > .has-arrow:hover:after'                => 'li > .digi-vertical-menu__row:hover .digi-vertical-menu__arrow',
		'li .has-arrow:hover:after'                  => 'li .digi-vertical-menu__row:hover .digi-vertical-menu__arrow',
		'li > .has-arrow::after'                     => 'li > .digi-vertical-menu__row .digi-vertical-menu__arrow',
		'li .has-arrow::after'                       => 'li .digi-vertical-menu__arrow',
		'li.mm-active > a'                           => 'li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link',
		'li.mm-active a'                             => 'li.is-open .digi-vertical-menu__link',
		'li.mm-active'                               => 'li.is-open',
		' > li > ul'                                 => ' > li > .digi-vertical-menu__sub',
		' > li > a'                                  => ' > li > .digi-vertical-menu__row > .digi-vertical-menu__link',
		'li  a'                                      => 'li .digi-vertical-menu__link',
		'.bdt-menu-icon'                             => '.digi-vertical-menu__icon',
	),
	'defaults'  => array(
		'navbar' => 0,
		'submenu_type' => 'outer',
		'columns' => '1',
		'sticky_offset' => array(
			'size' => 0,
		),
	),
	'repeaters' => array(
		'menus' => array(
			'defaults'     => array(
				'menu_type' => 'item',
				'menu_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'menu_title' => 'About',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Gallery',
					'menu_link' => '#',
					'menu_type' => 'child_start',
				), array(
					'menu_title' => 'Gallery 01',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Gallery 02',
					'menu_link' => '#',
					'menu_type' => 'child_start',
				), array(
					'menu_title' => 'Sub Gallery 01',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Sub Gallery 02',
					'menu_link' => '#',
				), array(
					'menu_title' => 'Sub Gallery 03',
					'menu_link' => '#',
				), array(
					'menu_type' => 'child_end',
				), array(
					'menu_title' => 'Gallery 03',
					'menu_link' => '#',
				), array(
					'menu_type' => 'child_end',
				), array(
					'menu_title' => 'Contacts',
					'menu_link' => '#',
				) ),
		),
	),
	'filter'    => static function ( array $out ) use ( $mode ) {
		// Los elementos con «child_start»/«child_end» pasan a niveles: lo que hay entre ambos cuelga del primero.
		$items = array();
		$level = 0;
		foreach ( is_array( $out['menus'] ?? null ) ? $out['menus'] : array() as $row ) {
			$type = (string) ( $row['menu_type'] ?? 'item' );
			if ( 'child_end' === $type ) {
				$level = max( 0, $level - 1 );
				continue;
			}
			$items[] = array(
				'_id'        => (string) ( $row['_id'] ?? '' ),
				'item_title' => trim( wp_strip_all_tags( (string) ( $row['menu_title'] ?? '' ) ) ),
				// Las filas de ejemplo guardan la URL como texto («#»).
				'item_link'  => is_array( $row['menu_link'] ?? null ) ? $row['menu_link'] : ( is_string( $row['menu_link'] ?? null ) && '' !== $row['menu_link'] ? array( 'url' => $row['menu_link'] ) : array() ),
				'item_icon'  => is_array( $row['menu_icon'] ?? null ) ? $row['menu_icon'] : array(),
				'item_level' => (string) min( 3, $level ),
			);
			if ( 'child_start' === $type ) {
				++$level;
			}
		}
		$out['source']         = 'yes' === ( $out['dynamic_menu'] ?? '' ) ? 'menu' : 'static';
		$out['menu']           = '0' === (string) ( $out['navbar'] ?? '' ) ? '' : (string) ( $out['navbar'] ?? '' );
		$out['items']          = $items;
		$out['mode']           = $mode;
		$out['open_current']   = '';
		// metisMenu abría el submenú al pulsar un padre «outer»; con «inner», y en Slinky, el padre conservaba su enlace.
		$out['parent_toggles'] = 'collapse' === $mode && ( 'outer' === ( $out['submenu_type'] ?? '' ) || 'yes' === ( $out['remove_parent_link'] ?? '' ) ) ? 'yes' : '';
		foreach ( array( 'dynamic_menu', 'navbar', 'menus', 'submenu_type', 'columns', 'remove_parent_link', 'show_sticky', 'sticky_offset', 'sticky_on_scroll_up', 'sticky_edge', 'deprecated_widget_note' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
