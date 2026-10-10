<?php
/** Element Pack Pro 9.9.1 `bdt-navbar` → `nav-menu`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'nav-menu',
	'classes'   => array(
		'{{WRAPPER}}.bdt-navbar-parent-indicator-yes'    => '{{WRAPPER}}',
		'li.bdt-parent.bdt-active a:after'               => 'li.menu-item-has-children > .elementor-item-active .sub-arrow',
		'li.bdt-parent a:hover::after'                   => 'li.menu-item-has-children > a:hover .sub-arrow',
		'li.bdt-parent a:after'                          => 'li.menu-item-has-children > a .sub-arrow',
		'.bdt-navbar-dropdown-nav > li.bdt-active > a'   => '.elementor-nav-menu--main .sub-menu > li > .elementor-sub-item.elementor-item-active',
		'.bdt-navbar-dropdown-nav > li > a'              => '.elementor-nav-menu--main .sub-menu > li > .elementor-sub-item',
		'.bdt-navbar-dropdown-nav > li'                  => '.elementor-nav-menu--main .sub-menu > li',
		'.bdt-navbar-dropdown-nav'                       => '.elementor-nav-menu--main .sub-menu',
		'.bdt-navbar-dropdown'                           => '.elementor-nav-menu--main .sub-menu',
		'.bdt-navbar-nav > li.bdt-active > a'            => '.elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active',
		'.bdt-navbar-nav > li:hover > a'                 => '.elementor-nav-menu--main > .elementor-nav-menu > li:hover > .elementor-item',
		'.bdt-navbar-nav > li > a'                       => '.elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
		'.bdt-navbar-nav'                                => '.elementor-nav-menu--main > .elementor-nav-menu',
		'.bdt-navbar-container'                          => '.elementor-nav-menu--main > .elementor-nav-menu',
	),
	'defaults'  => array(
		'navbar' => 0,
		'dropdown_delay_hide' => array(
			'size' => 800,
		),
		'dropdown_duration' => array(
			'size' => 200,
		),
		'navbar_style' => '',
	),
	'repeaters' => array(
		'repeater_field' => array(
			'defaults'     => array(
				'menu_number' => 3,
			),
			'default_rows' => array( array(
					'menu_color' => '#08AEEC',
				) ),
		),
	),
	'rename'    => array( 'navbar' => 'menu', 'align' => 'align_items' ),
	'set'       => array( 'layout' => 'horizontal', 'pointer' => 'none', 'dropdown' => 'none' ),
	'filter'    => static function ( array $out, array $saved ) {
		$out['menu'] = '0' === (string) ( $out['menu'] ?? '' ) ? '' : (string) ( $out['menu'] ?? '' );
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			$align = (string) ( $out[ 'align_items' . $device ] ?? '' );
			$map   = array( 'flex-start' => 'start', 'center' => 'center', 'flex-end' => 'end' );
			if ( isset( $map[ $align ] ) ) {
				$out[ 'align_items' . $device ] = $map[ $align ];
			} else {
				unset( $out[ 'align_items' . $device ] );
			}
		}
		// Element Pack sólo dibujaba la flecha del padre con «Parent Indicator».
		if ( 'yes' !== ( $out['menu_parent_arrow'] ?? '' ) ) {
			$out['submenu_icon'] = array( 'value' => '', 'library' => '' );
		}
		// «Individual Menu Style» coloreaba cada elemento por su posición: pasa al CSS propio del elemento.
		$css = '';
		if ( 'yes' === ( $out['individuL_menu_style'] ?? '' ) ) {
			foreach ( is_array( $out['repeater_field'] ?? null ) ? $out['repeater_field'] : array() as $index => $row ) {
				$nth   = max( 1, (int) ( $row['menu_number'] ?? $index + 1 ) );
				$rules = array( '' => array( 'menu_color', 'menu_background', 'menu_border_color' ), ':hover' => array( 'menu_hover_color', 'menu_hover_background', 'menu_hover_border_color' ) );
				foreach ( $rules as $state => $keys ) {
					$decl = '';
					foreach ( array_combine( array( 'color', 'background-color', 'border-color' ), $keys ) as $prop => $key ) {
						$value = (string) ( $row[ $key ] ?? '' );
						if ( '' === $value && 'menu_color' === $key ) {
							$value = '#08AEEC';
						}
						if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9.,\s%]+\)|hsla?\([0-9.,\s%deg]+\)|var\(--[a-zA-Z0-9_-]+\))$/', $value ) ) {
							$decl .= $prop . ':' . $value . ';';
						}
					}
					if ( '' !== $decl ) {
						$css .= 'selector .elementor-nav-menu--main > .elementor-nav-menu > li:nth-child(' . $nth . ')' . $state . ' > .elementor-item{' . $decl . '}';
					}
				}
			}
		}
		if ( '' !== $css ) {
			$out['custom_css'] = trim( (string) ( $saved['custom_css'] ?? '' ) . "\n" . $css );
		}
		foreach ( array( 'menu_parent_arrow', 'auto_hiding_menu', 'dropdown_align', 'dropdown_delay_show', 'dropdown_delay_hide', 'dropdown_duration', 'dropdown_offset', 'navbar_style', 'individuL_menu_style', 'repeater_field' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
