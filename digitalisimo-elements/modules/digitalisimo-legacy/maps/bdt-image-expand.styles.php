<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-image-expand` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'image_expand_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'items_content_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'flex-direction: {{VALUE}};',
		),
		'default' => 'row',
		'condition' => array(
			'skin_type' => 'sliding-box',
		),
		'options' => array(
			'row-reverse' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'row' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'items_content_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-h-align-stretch',
			),
		),
	), array(
		'name' => 'items_content_vertical_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'justify-content: {{VALUE}};',
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
		'name' => 'image_expand_overlay_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:before' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'skin_type!' => 'sliding-box',
		),
	), array(
		'name' => 'content_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab',
	), array(
		'name' => 'tabs_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label strong' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label strong',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label strong',
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
		'name' => 'description_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__panel',
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
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'border_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'border_radius_advanced',
		'type' => 'text',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'condition' => array(
			'border_radius_advanced_show' => 'yes',
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
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	) );
