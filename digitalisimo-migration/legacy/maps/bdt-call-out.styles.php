<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-call-out` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out *' => 'text-align: {{VALUE}};',
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
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-call-out__title',
	), array(
		'name' => 'text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-call-out__title',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-call-out__title',
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-call-out__description',
	), array(
		'name' => 'description_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__description' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_adv_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-call-out__button',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-call-out__button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-call-out__button',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-call-out__button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_adv_background_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-call-out__button:hover',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-call-out__button:hover',
	), array(
		'name' => 'callout_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button svg' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-call-out__button svg svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'callout_icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-call-out__button svg',
	), array(
		'name' => 'callout_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-call-out__button svg',
	), array(
		'name' => 'callout_icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button svg' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'callout_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button svg' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'callout_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button svg' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'callout_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button:hover .digi-call-out__button svg' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-call-out__button:hover .digi-call-out__button svg svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'callout_icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-call-out__button:hover .digi-call-out__button svg',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-call-out__button:hover .digi-call-out__button svg' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	) );
