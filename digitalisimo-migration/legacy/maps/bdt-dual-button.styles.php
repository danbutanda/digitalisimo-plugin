<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-dual-button` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button' => 'justify-content: {{VALUE}};',
		),
		'options' => array(
			'start' => array(
				'title' => 'Start',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'end' => array(
				'title' => 'End',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'button_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button' => 'width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 40,
			'unit' => '%',
		),
		'tablet_default' => array(
			'size' => 80,
			'unit' => '%',
		),
		'mobile_default' => array(
			'size' => 100,
			'unit' => '%',
		),
		'condition' => array(
			'button_width_auto' => '',
		),
		'size_units' => array( '%', 'px' ),
	), array(
		'name' => 'dual_button_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a' => 'margin-inline-end: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'show_middle_text!' => 'yes',
		),
	), array(
		'name' => 'button_a_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a, {{WRAPPER}} .digi-dual-button__action--a svg' => 'justify-content: {{VALUE}};',
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
		'name' => 'button_b_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b, {{WRAPPER}} .digi-dual-button__action--b svg' => 'justify-content: {{VALUE}};',
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
		'name' => 'button_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action' => 'border-style: {{VALUE}};',
		),
		'default' => 'none',
		'options' => array(
			'none' => 'None',
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'button_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 3,
			'right' => 3,
			'bottom' => 3,
			'left' => 3,
		),
		'condition' => array(
			'button_border_style!' => 'none',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dual_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dual_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-dual-button__action',
	), array(
		'name' => 'dual_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'dual_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-dual-button__action',
	), array(
		'name' => 'dual_button_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dual_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-dual-button__action:hover',
	), array(
		'name' => 'button_a_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_a_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--a',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_a_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a' => 'border-color: {{VALUE}};',
		),
		'default' => '#666',
		'condition' => array(
			'button_border_style!' => 'none',
		),
	), array(
		'name' => 'button_a_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_a_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_a_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--a',
	), array(
		'name' => 'button_a_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_a_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--a:after, {{WRAPPER}} .digi-dual-button__action--a:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_a_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_style!' => 'none',
		),
	), array(
		'name' => 'button_a_hover_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--a:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_a_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--a:hover',
	), array(
		'name' => 'button_b_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_b_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--b',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_b_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b' => 'border-color: {{VALUE}};',
		),
		'default' => '#666',
		'condition' => array(
			'button_border_style!' => 'none',
		),
	), array(
		'name' => 'button_b_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_b_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_b_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--b',
	), array(
		'name' => 'button_b_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_b_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--b:after, {{WRAPPER}} .digi-dual-button__action--b:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_b_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_style!' => 'none',
		),
	), array(
		'name' => 'button_b__hover_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_b_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-dual-button__action--b:hover',
	), array(
		'name' => 'button_b_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b .digi-dual-button__action--b-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-dual-button__action--b .digi-dual-button__action--b-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'button_b_icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__action--b:hover .digi-dual-button__action--b-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-dual-button__action--b:hover .digi-dual-button__action--b-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'middle_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__middle' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'middle_text_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-dual-button__middle',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'middle_text_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__middle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'middle_text_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-dual-button__middle',
	), array(
		'name' => 'middle_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-dual-button__middle',
	), array(
		'name' => 'middle_text_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-dual-button__middle' => 'left: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	) );
