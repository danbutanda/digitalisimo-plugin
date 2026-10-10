<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-hover-box` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'skin_hover_box_min_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => 'bdt-envelope',
		),
	), array(
		'name' => 'hover_box_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab-wrap' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-envelope',
		),
	), array(
		'name' => 'tabs_content_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'text-align: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-flexure',
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
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'box_item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab',
	), array(
		'name' => 'box_item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'condition' => array(
			'box_item_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'box_item_radius_advanced',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'box_item_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'box_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab',
	), array(
		'name' => 'box_item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover',
	), array(
		'name' => 'box_item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'box_item_border_border!' => '',
		),
	), array(
		'name' => 'box_item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover',
	), array(
		'name' => 'box_item_active_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab.active',
	), array(
		'name' => 'box_item_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'box_item_border_border!' => '',
		),
	), array(
		'name' => 'box_item_active_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab.active',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label strong, {{WRAPPER}} .digi-fancy-tabs__label strong a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__label strong, {{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__label strong a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__label strong, {{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__label strong a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label strong, {{WRAPPER}} .digi-fancy-tabs__label strong a',
	), array(
		'name' => 'sub_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label strong' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label strong',
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__panel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__panel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'padding-top: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__panel',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'icon_border_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'border_radius_advanced',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'condition' => array(
			'icon_border_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__button a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__button a' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__button a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_active_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__button a' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'button_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__button a' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button' => 'padding-top: {{SIZE}}{{UNIT}}',
		),
	) );
