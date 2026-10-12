<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fancy-list` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list ul.digi-fancy-list-group' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'default' => '1',
		'tablet_default' => '1',
		'mobile_default' => '1',
		'condition' => array(
			'layout_style' => 'style-1',
		),
		'options' => array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		),
	), array(
		'name' => 'list_item_space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list ul.digi-fancy-list-group' => 'grid-gap: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'icon_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => '{{VALUE}};',
		),
		'selectors_dictionary' => array(
			'left' => 'flex-direction: row-reverse; -webkit-flex-direction: row-reverse;',
			'top' => 'flex-direction: column-reverse; -webkit-flex-direction: column-reverse;',
			'bottom' => 'flex-direction: column; -webkit-flex-direction: column;',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
			'top' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'bottom' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'content_y_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'align-items: {{VALUE}};',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-v-align-middle',
			),
			'flex-end' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'list_item_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap',
	), array(
		'name' => 'list_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap',
	), array(
		'name' => 'list_item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'list_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'list_item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap',
	), array(
		'name' => 'list_item_border',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'min-height: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'list_item_hover_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap:hover',
	), array(
		'name' => 'list_item_hover_border',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap:hover' => 'border-color: {{VALUE}} !important',
		),
		'condition' => array(
			'list_item_border_border!' => '',
		),
	), array(
		'name' => 'list_item_box_shadow_hover',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap:hover',
	), array(
		'name' => 'number_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-number-icon span' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'icon_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-number-icon' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'icon_number_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-list-number-icon',
	), array(
		'name' => 'icon_number_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-number-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_number_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-number-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_number_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-number-icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'number_icon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-list-number-icon span',
	), array(
		'name' => 'number_icon_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-group .digi-fancy-list-wrap:hover .digi-fancy-list-number-icon span' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'number_icon_bg_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-group .digi-fancy-list-wrap:hover .digi-fancy-list-number-icon' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'number_icon_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-group .digi-fancy-list-wrap:hover .digi-fancy-list-number-icon' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'icon_number_border_border!' => '',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-title' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-list-title',
	), array(
		'name' => 'des_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-text' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-list-text',
	), array(
		'name' => 'sub_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list-title' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'text_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list-text' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-fancy-list__icon svg' => 'fill: {{VALUE}} !important;',
		),
		'default' => '#242424',
	), array(
		'name' => 'right_icon_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'background: {{VALUE}} ;',
		),
		'default' => '#fff',
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-list__icon',
	), array(
		'name' => 'icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-list__icon',
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-list__icon',
	), array(
		'name' => 'icon_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list__icon' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list__icon i' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list__icon svg' => 'fill: {{VALUE}} !important;',
		),
	), array(
		'name' => 'icon_bg_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list__icon' => 'background-color: {{VALUE}} ;',
		),
	), array(
		'name' => 'icon_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list__icon' => 'border-color: {{VALUE}} ;',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_shadow_hover',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-list-wrap:hover .digi-fancy-list__icon',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-list-img img',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-img img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list-img img' => 'width: {{SIZE}}{{UNIT}};',
		),
	) );
