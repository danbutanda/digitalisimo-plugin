<?php
/** Element Pack Pro 9.9.1 `bdt-price-table` → `price-table`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'price-table',
	// Clases de Element Pack más usadas en sus selectores: .bdt-price-table, .bdt-price-table-features-list, .bdt-read-more-features, .bdt-price-table-feature-icon, .bdt-price-table-button, .bdt-price-table-price, .bdt-price-table-ribbon-inner, .bdt-ep-price-header-wrap, .bdt-price-table-header, .bdt-price-table-subheading, .bdt-price-table-currency, .bdt-price-table-heading, .bdt-price-table-fractional-part, .bdt-price-table-additional_info
	'classes'   => array(
		'.bdt-price-table-features-list' => '.elementor-price-table__features-list',
		'.bdt-price-table-feature-icon'  => '.elementor-price-table__features-list i',
		'.bdt-price-table-button'        => '.elementor-price-table__button',
		'.bdt-price-table-price'         => '.elementor-price-table__price',
		'.bdt-price-table-header'        => '.elementor-price-table__header',
		'.bdt-price-table-ribbon-inner'  => '.elementor-price-table__ribbon-inner',
		'.bdt-price-table-heading'       => '.elementor-price-table__heading',
		'.bdt-price-table-subheading'    => '.elementor-price-table__subheading',
		'.bdt-price-table-footer'        => '.elementor-price-table__footer',
		'.bdt-price-table'               => '.elementor-price-table',
	),
	'defaults'  => array(
		'layout' => '1',
		'align' => 'center',
		'thumbnail_size' => 'thumbnail',
		'heading' => 'Service Name',
		'heading_tag' => 'h3',
		'sub_heading' => 'Service sub title',
		'currency_symbol' => 'dollar',
		'price' => '49.99',
		'original_price' => '79',
		'period' => 'Monthly',
		'sticky_pricing' => 'no',
		'active_features_index' => 3,
		'show_all_features_text' => '- Show all features',
		'less_features_text' => '- Less features',
		'button_text' => 'Select Plan',
		'edd_id' => '',
		'woo_id' => '',
		'button_css_id' => '',
		'footer_additional_info' => 'This is footer text',
		'ribbon_title' => 'Popular',
		'ribbon_align' => 'left',
		'ribbon_horizontal_position' => array(
			'size' => 0,
		),
		'ribbon_horizontal_position_tablet' => array(
			'size' => 0,
		),
		'ribbon_horizontal_position_mobile' => array(
			'size' => 0,
		),
		'ribbon_vertical_position' => array(
			'size' => 0,
		),
		'ribbon_vertical_position_tablet' => array(
			'size' => 0,
		),
		'ribbon_vertical_position_mobile' => array(
			'size' => 0,
		),
		'ribbon_rotate' => array(
			'size' => 0,
		),
		'ribbon_rotate_tablet' => array(
			'size' => 0,
		),
		'ribbon_rotate_mobile' => array(
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
		'currency_horizontal_position' => 'left',
		'currency_vertical_position' => 'top',
		'fractional_part_vertical_position' => 'top',
		'original_price_vertical_position' => 'middle',
		'original_price_horizontal_offset' => array(
			'size' => 0,
		),
		'original_price_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'original_price_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'original_price_vertical_offset' => array(
			'size' => 0,
		),
		'original_price_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'original_price_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'original_price_rotate' => array(
			'size' => 0,
		),
		'original_price_rotate_tablet' => array(
			'size' => 0,
		),
		'original_price_rotate_mobile' => array(
			'size' => 0,
		),
		'period_position' => 'below',
		'list_divider' => 'yes',
		'divider_style' => 'solid',
		'divider_color' => '#ddd',
		'divider_weight' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'divider_gap' => array(
			'size' => 15,
			'unit' => 'px',
		),
		'features_tooltip_text_align' => 'center',
		'button_size' => 'md',
		'button_vertical_offset' => array(
			'size' => 0,
		),
		'button_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'button_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'additional_info_margin' => array(
			'top' => 15,
			'right' => 30,
			'bottom' => 0,
			'left' => 30,
		),
		'ribbon_text_color' => '#ffffff',
		'ribbon_bg_color' => '#14ABF4',
	),
	'repeaters' => array(
		'features_list' => array(
			'defaults'     => array(
				'item_text' => 'List Item',
				'price_table_item_icon' => array(
					'value' => 'fas fa-check',
					'library' => 'fa-solid',
				),
				'tooltip_placement' => 'right',
			),
			'default_rows' => array( array(
					'item_text' => 'List Item #1',
					'price_table_item_icon' => array(
						'value' => 'fas fa-check',
						'library' => 'fa-solid',
					),
				), array(
					'item_text' => 'List Item #2',
					'price_table_item_icon' => array(
						'value' => 'fas fa-check',
						'library' => 'fa-solid',
					),
				), array(
					'item_text' => 'List Item #3',
					'price_table_item_icon' => array(
						'value' => 'fas fa-check',
						'library' => 'fa-solid',
					),
				) ),
		),
	),
	'repeaters' => array(
		'features_list' => array(
			'defaults'     => array( 'item_text' => 'List Item', 'price_table_item_icon' => array( 'value' => 'fas fa-check', 'library' => 'fa-solid' ) ),
			'default_rows' => array(
				array( 'item_text' => 'List Item #1' ), array( 'item_text' => 'List Item #2' ), array( 'item_text' => 'List Item #3' ),
			),
			'rename'       => array( 'price_table_item_icon' => 'selected_item_icon' ),
		),
	),
	'filter'    => static function ( array $out ) {
		$out['show_ribbon'] = 'yes' === ( $out['show_ribbon'] ?? '' ) ? 'yes' : '';
		foreach ( array( 'layout', 'sticky_heading', 'sticky_pricing', 'features_hide_on', 'read_more_toggle', 'active_features_index', 'show_all_features_text', 'less_features_text', 'edd_as_button', 'edd_id', 'woo_as_button', 'woo_id', 'image' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
