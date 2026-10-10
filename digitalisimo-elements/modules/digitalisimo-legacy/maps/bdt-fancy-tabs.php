<?php
/** Element Pack Pro 9.9.1 `bdt-fancy-tabs` → `digitalisimo-fancy-tabs`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-tabs',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-fancy-tabs-icon, .bdt-ep-fancy-tabs-item, .bdt-ep-fancy-tabs-button, .bdt-ep-fancy-tabs, .bdt-ep-fancy-tabs-title, .bdt-ep-fancy-tabs-content, .bdt-ep-fancy-tabs-sub-title, .bdt-ep-fancy-tabs-text, .bdt-ep-fancy-tabs-height-fixed, .bdt-custom-width, .bdt-width-1-2
	'classes'   => array(
		'.bdt-ep-fancy-tabs-item'      => '.digi-fancy-tabs__tab',
		'.bdt-ep-fancy-tabs-icon'      => '.digi-fancy-tabs__icon',
		'.bdt-ep-fancy-tabs-title'     => '.digi-fancy-tabs__label',
		'.bdt-ep-fancy-tabs-button'    => '.digi-fancy-tabs__button',
		'.bdt-ep-fancy-tabs-content'   => '.digi-fancy-tabs__panel',
		'.bdt-ep-fancy-tabs'           => '.digi-fancy-tabs',
	),
	'defaults'  => array(
		'columns' => '2',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'column_gap' => 'medium',
		'fancy_tabs_event' => 'mouseover',
		'fancy_tabs_position' => 'left',
		'show_sub_title' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'div',
		'show_content' => 'yes',
		'show_button' => 'yes',
		'fancy_tab_active_item' => 1,
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'tabs_item_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'icon_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'icon_background_rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'background_hover_transition' => array(
			'size' => 0.3,
		),
		'icon_hover_rotate' => array(
			'unit' => 'deg',
		),
		'icon_hover_background_rotate' => array(
			'unit' => 'deg',
		),
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
	),
	'repeaters' => array(
		'tabs' => array(
			'defaults'     => array(
				'icon_type' => 'icon',
				'selected_icon' => array(
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'image' => array(
					'url' => $placeholder_url,
				),
				'tab_title' => 'Tab Title',
				'tabs_button' => 'Read More',
				'button_link' => array(
					'url' => '#',
				),
				'tab_content' => 'Tab Content',
			),
			'default_rows' => array( array(
					'tab_sub_title' => 'Subtitle Goes Here',
					'tab_title' => 'Fancy Tabs Item One',
					'tab_content' => 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. Recusandae voluptate repellendus magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'far fa-laugh',
						'library' => 'fa-regular',
					),
				), array(
					'tab_sub_title' => 'Subtitle Goes Here',
					'tab_title' => 'Fancy Tabs Item Two',
					'tab_content' => 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. Recusandae voluptate repellendus magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-cog',
						'library' => 'fa-solid',
					),
				), array(
					'tab_sub_title' => 'Subtitle Goes Here',
					'tab_title' => 'Fancy Tabs Item Three',
					'tab_content' => 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. Recusandae voluptate repellendus magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-dice-d6',
						'library' => 'fa-solid',
					),
				), array(
					'tab_sub_title' => 'Subtitle Goes Here',
					'tab_title' => 'Fancy Tabs Item Four',
					'tab_content' => 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. Recusandae voluptate repellendus magni illo ea animi.',
					'selected_icon' => array(
						'value' => 'fas fa-ring',
						'library' => 'fa-solid',
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$hide = array( 'show_sub_title' => 'tab_sub_title', 'show_title' => 'tab_title', 'show_content' => 'tab_content', 'show_button' => 'tabs_button' );
		foreach ( $out['tabs'] as $index => $tab ) {
			foreach ( $hide as $flag => $key ) {
				if ( 'yes' !== ( $out[ $flag ] ?? 'yes' ) ) {
					$tab[ $key ] = '';
				}
			}
			$out['tabs'][ $index ] = $tab;
		}
		foreach ( array_merge( array_keys( $hide ), array( 'column_gap', 'fancy_tabs_event', 'fancy_tabs_position', 'title_tags', 'fancy_tab_active_item' ) ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
