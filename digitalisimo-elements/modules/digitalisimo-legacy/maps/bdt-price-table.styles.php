<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-price-table` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'content_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-image' => 'text-align: {{VALUE}};',
		),
		'default' => 'center',
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'features_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'ribbon_horizontal_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-ribbon-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'show_ribbon' => 'yes',
		),
	), array(
		'name' => 'ribbon_vertical_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-ribbon-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'show_ribbon' => 'yes',
		),
	), array(
		'name' => 'ribbon_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-ribbon-rotate: {{SIZE}}deg;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'show_ribbon' => 'yes',
		),
	), array(
		'name' => 'image_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table-image',
	), array(
		'name' => 'image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-image' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table img' => 'max-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 100,
			'unit' => '%',
		),
		'tablet_default' => array(
			'unit' => '%',
		),
		'mobile_default' => array(
			'unit' => '%',
		),
		'size_units' => array( '%' ),
	), array(
		'name' => 'opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table img' => 'opacity: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'image_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table img',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-table img',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'image_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'image_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-table img',
		'exclude' => array( 'shadow_position' ),
	), array(
		'name' => 'opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table:hover img',
	), array(
		'name' => 'image_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover img' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'image_border_border!' => '',
		),
	), array(
		'name' => 'header_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table__header',
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'header_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'header_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-table__header',
	), array(
		'name' => 'heading_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__header' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__heading' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'heading_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-table__heading',
	), array(
		'name' => 'heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table__heading',
	), array(
		'name' => 'sub_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__subheading' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'sub_heading_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-table__subheading',
	), array(
		'name' => 'sub_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table__subheading',
	), array(
		'name' => 'sub_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__subheading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'heading_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__heading' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'sub_heading_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__subheading' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'pricing_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__price' => '{{VALUE}};',
		),
		'selectors_dictionary' => array(
			'left' => 'justify-content: flex-start; text-align: left;',
			'right' => 'justify-content: flex-end; text-align: right;',
			'center' => '    justify-content: center; text-align: center;',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'pricing_element_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table__price',
	), array(
		'name' => 'price_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-currency, {{WRAPPER}} .elementor-price-table-integer-part, {{WRAPPER}} .elementor-price-table-fractional-part' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'pricing_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-table__price',
	), array(
		'name' => 'readmore_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__price' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'pricing_element_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__price' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'pricing_element_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__price' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'price_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-table-currency, {{WRAPPER}} .elementor-price-table-integer-part, {{WRAPPER}} .elementor-price-table-fractional-part',
	), array(
		'name' => 'price_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table__price',
	), array(
		'name' => 'currency_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-currency' => 'font-size: calc({{SIZE}}em/100)',
		),
		'condition' => array(
			'currency_symbol!' => '',
		),
	), array(
		'name' => 'currency_vertical_position',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-currency' => 'align-self: {{VALUE}}',
		),
		'default' => 'top',
		'selectors_dictionary' => array(
			'top' => 'flex-start',
			'middle' => 'center',
			'bottom' => 'flex-end',
		),
		'condition' => array(
			'currency_symbol!' => '',
		),
		'options' => array(
			'top' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'middle' => array(
				'title' => 'Middle',
				'icon' => 'eicon-v-align-middle',
			),
			'bottom' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'fractional-part_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-fractional-part' => 'font-size: calc({{SIZE}}em/100)',
		),
		'condition' => array(
			'currency_format' => '',
		),
	), array(
		'name' => 'fractional_part_vertical_position',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-after-price' => 'justify-content: {{VALUE}}',
		),
		'default' => 'top',
		'selectors_dictionary' => array(
			'top' => 'flex-start',
			'middle' => 'center',
			'bottom' => 'flex-end',
		),
		'condition' => array(
			'currency_format' => '',
		),
		'options' => array(
			'top' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'middle' => array(
				'title' => 'Middle',
				'icon' => 'eicon-v-align-middle',
			),
			'bottom' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'original_price_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-original-price' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'sale' => 'yes',
			'original_price!' => '',
		),
	), array(
		'name' => 'original_price_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table-original-price',
	), array(
		'name' => 'original_price_vertical_position',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-original-price' => '{{VALUE}}',
		),
		'default' => 'middle',
		'selectors_dictionary' => array(
			'top' => 'top: 0;',
			'middle' => 'top: 40%;',
			'bottom' => 'bottom: 0;',
		),
		'condition' => array(
			'sale' => 'yes',
			'original_price!' => '',
		),
		'options' => array(
			'top' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'middle' => array(
				'title' => 'Middle',
				'icon' => 'eicon-v-align-middle',
			),
			'bottom' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'original_price_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-pt-original-price-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'original_price_offset_toggle' => 'yes',
			'sale' => 'yes',
			'original_price!' => '',
		),
	), array(
		'name' => 'original_price_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-pt-original-price-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'original_price_offset_toggle' => 'yes',
			'sale' => 'yes',
			'original_price!' => '',
		),
	), array(
		'name' => 'original_price_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-pt-original-price-rotate: {{SIZE}}deg;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'original_price_offset_toggle' => 'yes',
			'sale' => 'yes',
			'original_price!' => '',
		),
	), array(
		'name' => 'period_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-period' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'period!' => '',
		),
	), array(
		'name' => 'period_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table-period',
	), array(
		'name' => 'pricing_element_hover_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__price',
	), array(
		'name' => 'pricing_table_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__price' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'pricing_border_border!' => '',
		),
	), array(
		'name' => 'price_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table-currency, {{WRAPPER}} .elementor-price-table:hover .elementor-price-table-integer-part, {{WRAPPER}} .elementor-price-table:hover .elementor-price-table-fractional-part' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'period_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table-period' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'period!' => '',
		),
	), array(
		'name' => 'features_list_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table__features-list',
	), array(
		'name' => 'features_list_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-table__features-list',
	), array(
		'name' => 'features_list_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'features_list_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'features_list_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'features_list_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list, {{WRAPPER}} .edd_price_options li span, {{WRAPPER}} .elementor-price-table__features-list a' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-price-table__features-list svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'features_list_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list, {{WRAPPER}} .elementor-price-table:hover .edd_price_options li span, {{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list a, {{WRAPPER}} .elementor-price-table .elementor-price-table__features-list a:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'features_list_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table__features-list li, {{WRAPPER}} .edd_price_options li span',
	), array(
		'name' => 'features_list_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list' => 'text-align: {{VALUE}}',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'item_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-feature-inner' => 'margin-left: calc((100% - {{SIZE}}%)/2); margin-right: calc((100% - {{SIZE}}%)/2)',
		),
	), array(
		'name' => 'features_list_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list .elementor-price-table-feature-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'features_list_inner_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list .elementor-price-table-feature-inner' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'list_striped_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list li:nth-of-type(odd)' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'list_striped' => 'yes',
		),
	), array(
		'name' => 'list_striped_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list li:nth-of-type(odd)' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'list_striped' => 'yes',
		),
	), array(
		'name' => 'divider_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list li:before' => 'border-top-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'list_divider' => 'yes',
			'list_striped' => '',
		),
		'options' => array(
			'solid' => 'Solid',
			'double' => 'Double',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'divider_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list li:before' => 'border-top-color: {{VALUE}};',
		),
		'default' => '#ddd',
		'condition' => array(
			'list_divider' => 'yes',
			'list_striped' => '',
		),
	), array(
		'name' => 'divider_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list li:before' => 'border-top-color: {{VALUE}};',
		),
		'condition' => array(
			'list_divider' => 'yes',
			'list_striped' => '',
		),
	), array(
		'name' => 'divider_weight',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list li:before' => 'border-top-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'condition' => array(
			'list_divider' => 'yes',
			'list_striped' => '',
		),
	), array(
		'name' => 'divider_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list li:before' => 'margin-left: calc((100% - {{SIZE}}%)/2); margin-right: calc((100% - {{SIZE}}%)/2)',
		),
		'condition' => array(
			'list_divider' => 'yes',
			'list_striped' => '',
		),
	), array(
		'name' => 'divider_gap',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list li:before' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'size' => 15,
			'unit' => 'px',
		),
		'condition' => array(
			'list_divider' => 'yes',
			'list_striped' => '',
		),
	), array(
		'name' => 'list_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list i i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-price-table__features-list i svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'list_icon_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table__features-list i',
	), array(
		'name' => 'list_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-table__features-list i',
	), array(
		'name' => 'list_icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list i' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'list_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list i' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'list_icon_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list i' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'list_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__features-list i' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'list_icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list i i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list i svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'list_icon_hover_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list i',
	), array(
		'name' => 'list_icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table__features-list i' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'footer_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table__footer',
	), array(
		'name' => 'footer_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__footer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'footer_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__footer' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__button' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'button_text!' => '',
		),
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table__button',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-table__button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_text!' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_text!' => '',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_text!' => '',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__button' => 'width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'vw', '%' ),
	), array(
		'name' => 'button_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-btn-wrap' => 'transform: translateY({{SIZE}}px)',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
	), array(
		'name' => 'button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-table__button',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table__button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__button:hover' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'button_text!' => '',
		),
	), array(
		'name' => 'button_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-table__button:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_text!' => '',
		),
	), array(
		'name' => 'additional_info_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-additional_info' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'footer_additional_info!' => '',
			'_skin' => '',
		),
	), array(
		'name' => 'additional_info_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table:hover .elementor-price-table-additional_info' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'footer_additional_info!' => '',
			'_skin' => '',
		),
	), array(
		'name' => 'additional_info_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table-additional_info',
	), array(
		'name' => 'additional_info_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table-additional_info' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'default' => array(
			'top' => 15,
			'right' => 30,
			'bottom' => 0,
			'left' => 30,
		),
		'condition' => array(
			'footer_additional_info!' => '',
			'_skin' => '',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'ribbon_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__ribbon-inner' => 'color: {{VALUE}}',
		),
		'default' => '#ffffff',
	), array(
		'name' => 'ribbon_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__ribbon-inner' => 'background-color: {{VALUE}}',
		),
		'default' => '#14ABF4',
	), array(
		'name' => 'ribbon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-table__ribbon-inner',
	), array(
		'name' => 'ribbon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__ribbon-inner' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'ribbon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-table__ribbon-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-table__ribbon-inner',
	), array(
		'name' => 'ribbon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-table__ribbon-inner',
	) );
