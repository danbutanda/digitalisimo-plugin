<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-advanced-button` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button__wrap' => 'justify-content: {{VALUE}};',
		),
		'default' => '',
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
		'name' => 'icon_align_choose',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__text' => '{{VALUE}};',
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__content' => '{{VALUE}};',
		),
		'default' => 'center',
		'selectors_dictionary' => array(
			'center' => 'text-align: center;',
			'space-between' => 'justify-content: space-between;',
			'left-right' => 'flex-grow: 1;',
		),
		'condition' => array(
			'button_icon[value]!' => '',
			'icon_align' => array( 'left', 'right' ),
		),
		'options' => array(
			'center' => array(
				'title' => 'Both Center',
				'icon' => 'eicon-justify-center-h',
			),
			'space-between' => array(
				'title' => 'Space Between',
				'icon' => 'eicon-justify-space-between-h',
			),
			'left-right' => array(
				'title' => 'Icon Left/Right',
				'icon' => 'eicon-grow',
			),
		),
	), array(
		'name' => 'advanced_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-button, {{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-i .digi-advanced-button__content:after, {{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-i .digi-advanced-button__content:before, {{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-h:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
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
			'{{WRAPPER}} .digi-advanced-button' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		'name' => 'button_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button' => 'border-color: {{VALUE}};',
		),
		'default' => '#666',
		'condition' => array(
			'button_border_style!' => 'none',
		),
	), array(
		'name' => 'advanced_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'advanced_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'advanced_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-button',
	), array(
		'name' => 'advanced_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-button',
	), array(
		'name' => 'button_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button' => 'max-width: {{SIZE}}{{UNIT}}; width: 100%;',
		),
		'size_units' => array( '%', 'px' ),
	), array(
		'name' => 'advanced_button_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-button:after, {{WRAPPER}} .digi-advanced-button:hover, {{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-i, {{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-h:after',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_hover_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'options' => array(
			'none' => 'None',
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'button_hover_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 3,
			'right' => 3,
			'bottom' => 3,
			'left' => 3,
		),
		'condition' => array(
			'button_hover_border_style!' => 'none',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_hover_border_style!' => 'none',
		),
	), array(
		'name' => 'advanced_button_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'advanced_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-button:hover',
	), array(
		'name' => 'transition_duration',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button' => 'transition-duration: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-advanced-button:after' => 'transition-duration: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon i, {{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon svg' => 'transition-duration: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge' => 'transition-duration: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-g .avdbtn-text, {{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-g .avdbtn-alt-text' => 'transition-duration: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-i .digi-advanced-button__content::before, {{WRAPPER}} .digi-advanced-button.digi-advanced-button--effect-i .digi-advanced-button__content::after' => 'transition-duration: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 's',
		),
		'size_units' => array( 's', 'ms' ),
	), array(
		'name' => 'advanced_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'advanced_button_icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon .digi-advanced-button__icon',
	), array(
		'name' => 'advanced_button_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon .digi-advanced-button__icon',
	), array(
		'name' => 'advanced_button_icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon .digi-advanced-button__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'advanced_button_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon .digi-advanced-button__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'advanced_button_icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon .digi-advanced-button__icon',
	), array(
		'name' => 'advanced_button_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__icon .digi-advanced-button__icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'advanced_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover .digi-advanced-button__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-advanced-button:hover .digi-advanced-button__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'advanced_button_icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-button:hover .digi-advanced-button__icon .digi-advanced-button__icon',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover .digi-advanced-button__icon .digi-advanced-button__icon' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'advanced_button_icon_border_border!' => '',
		),
	), array(
		'name' => 'badge_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge',
	), array(
		'name' => 'badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge',
	), array(
		'name' => 'badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'badge_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge',
	), array(
		'name' => 'badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-button .digi-advanced-button__badge',
	), array(
		'name' => 'badge_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover .digi-advanced-button__badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-button:hover .digi-advanced-button__badge',
	), array(
		'name' => 'badge_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-button:hover .digi-advanced-button__badge' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'badge_border_border!' => '',
		),
	) );
