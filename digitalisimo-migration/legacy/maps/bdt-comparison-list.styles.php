<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-comparison-list` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'comparison_list_column_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-cl-column-gap: {{SIZE}}px;',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'comparison_list_item_min_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-cl-column-width: {{SIZE}}%;',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'comparison_list_wrapper_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'(mobile){{WRAPPER}} .digi-comparison-list' => 'min-width: {{SIZE}}px; max-width: {{SIZE}}px;',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'comparison_list_header_wrap_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list thead' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_header_wrap_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-comparison-list thead',
	), array(
		'name' => 'comparison_list_header_wrap_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list thead' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'comparison_list_header_wrap_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan > span-item' => 'padding: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'comparison_list_header_wrap_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan > span-item',
	), array(
		'name' => 'comparison_list_featured_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list thead th:first-child' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_featured_title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-comparison-list thead th:first-child',
	), array(
		'name' => 'comparison_list_featured_title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-comparison-list thead th:first-child',
	), array(
		'name' => 'comparison_list_featured_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list thead th:first-child' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'comparison_list_featured_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-comparison-list thead th:first-child',
	), array(
		'name' => 'comparison_list_header_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan > span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_header_title_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan > span',
	), array(
		'name' => 'comparison_list_header_title_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan > span',
	), array(
		'name' => 'comparison_list_header_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan > span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'comparison_list_header_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan > span',
	), array(
		'name' => 'comparison_list_header_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan small' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_header_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan small',
	), array(
		'name' => 'comparison_list_header_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan small',
	), array(
		'name' => 'comparison_list_header_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan small' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'comparison_list_header_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan small',
	), array(
		'name' => 'comparison_list_header_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_header_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_header_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan a',
	), array(
		'name' => 'comparison_list_header_button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'comparison_list_header_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'comparison_list_header_button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'comparison_list_header_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan a',
	), array(
		'name' => 'comparison_list_header_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan a',
	), array(
		'name' => 'comparison_list_header_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_header_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_header_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list__plan a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'comparison_list_header_button_border_border!' => '',
		),
	), array(
		'name' => 'comparison_list_header_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-comparison-list__plan a:hover',
	), array(
		'name' => 'comparison_list_item_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody tr' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_item_border',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody tr' => 'border-top-style: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'' => 'Default',
			'none' => 'None',
			'solid' => 'Solid',
			'double' => 'Double',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'comparison_list_item_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody tr' => 'border-top-color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody tr' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'comparison_list_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody th' => 'padding: {{TOP}}px {{RIGHT}}px {{BOTTOM}}px {{LEFT}}px;',
		),
	), array(
		'name' => 'comparison_list_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-comparison-list tbody tr',
	), array(
		'name' => 'item_item_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody th' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'item_item_title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody th:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_item_title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_item_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-comparison-list tbody th',
	), array(
		'name' => 'comparison_list_item_features_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_item_features_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody td' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_item_features_text_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-comparison-list tbody td' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'comparison_list_item_features_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-comparison-list tbody td',
	) );
