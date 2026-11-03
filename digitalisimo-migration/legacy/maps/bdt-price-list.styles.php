<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-price-list` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'default' => '1',
		'tablet_default' => '1',
		'mobile_default' => '1',
		'options' => array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
		),
	), array(
		'name' => 'grid_column_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'columns!' => '1',
		),
	), array(
		'name' => 'row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'badge_h_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-price-list-badge-h-offset: {{SIZE}}px;',
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
			'show_badge' => 'yes',
			'badge_offset' => 'yes',
		),
	), array(
		'name' => 'badge_v_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-price-list-badge-v-offset: {{SIZE}}px;',
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
			'show_badge' => 'yes',
			'badge_offset' => 'yes',
		),
	), array(
		'name' => 'badge_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-price-list-badge-rotate: {{SIZE}}deg;',
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
			'show_badge' => 'yes',
			'badge_offset' => 'yes',
		),
	), array(
		'name' => 'item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-list-item',
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-list-item',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-list-item',
	), array(
		'name' => 'item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-price-list-item:hover',
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-list-item:hover',
	), array(
		'name' => 'heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'heading_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'heading_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-list-header',
	), array(
		'name' => 'heading_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .elementor-price-list-title',
	), array(
		'name' => 'price_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-price' => 'color: {{VALUE}};',
		),
		'default' => '#ffffff',
	), array(
		'name' => 'price_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-price' => 'background-color: {{VALUE}};',
		),
		'default' => '#4AB8F8',
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-list-price',
	), array(
		'name' => 'price_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-price' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => '50',
			'right' => '50',
			'bottom' => '50',
			'left' => '50',
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'price_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-price' => 'width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 50,
		),
	), array(
		'name' => 'price_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-price' => 'height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'price_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-list-price',
	), array(
		'name' => 'price_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-list-price',
	), array(
		'name' => 'price_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-price' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'price_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-price' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'price_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-price' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'price_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-price',
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-price-list-description',
	), array(
		'name' => 'separator_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-separator' => 'border-bottom-style: {{VALUE}};',
		),
		'default' => 'dashed',
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'double' => 'Double',
			'none' => 'None',
		),
	), array(
		'name' => 'separator_weight',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-separator' => 'border-bottom-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
		'condition' => array(
			'separator_style!' => 'none',
		),
	), array(
		'name' => 'separator_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-separator' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'separator_style!' => 'none',
		),
	), array(
		'name' => 'separator_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-separator' => 'border-bottom-color: {{VALUE}};',
		),
		'condition' => array(
			'separator_style!' => 'none',
		),
	), array(
		'name' => 'separator_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-separator' => 'border-bottom-color: {{VALUE}};',
		),
		'condition' => array(
			'separator_style!' => 'none',
		),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-price-list-image',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-image, {{WRAPPER}} .elementor-price-list-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-image' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-image' => 'width: {{SIZE}}{{UNIT}}; height: auto;',
		),
		'default' => array(
			'size' => 60,
		),
	), array(
		'name' => 'image_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-list-image',
	), array(
		'name' => 'image_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-image' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'image_border_border!' => '',
		),
	), array(
		'name' => 'image_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-price-list-item:hover .elementor-price-list-image',
	) );
