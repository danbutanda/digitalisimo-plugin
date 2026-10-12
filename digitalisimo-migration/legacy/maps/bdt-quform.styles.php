<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-quform` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .quform-form .quform-label',
	), array(
		'name' => 'others_type_input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-sub-label' => 'color: {{VALUE}};',
		),
		'default' => '#666666',
	), array(
		'name' => 'sublabel_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .quform-form .quform-sub-label',
	), array(
		'name' => 'sub_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .quform-form .quform-description',
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-input input' => 'color: {{VALUE}};',
			'{{WRAPPER}} .quform-form .quform-input select' => 'color: {{VALUE}};',
			'{{WRAPPER}} .quform-form .quform-input textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-input input' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .quform-form .quform-input select' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .quform-form .quform-input textarea' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-input textarea' => 'height: {{SIZE}}{{UNIT}}; display: block;',
		),
		'default' => array(
			'size' => 125,
		),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-input input, {{WRAPPER}} .quform-form .quform-input textarea, {{WRAPPER}} .quform-form .quform-input select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-element:not(.quform-element-column) + .quform-element' => 'padding-top: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .quform-form .quform-element .quform-spacer' => 'padding-bottom: 0; margin-bottom: 0;',
		),
		'default' => array(
			'size' => 25,
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .quform-form .quform-input input, {{WRAPPER}} .quform-form .quform-input textarea, {{WRAPPER}} .quform-form .quform-input select',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-input input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .quform-form .quform-input textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .quform-form .quform-input select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit .quform-submit' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit .quform-submit' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .quform-form .quform-button-submit .quform-submit',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit .quform-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .quform-form .quform-button-submit .quform-submit',
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit .quform-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .quform-form .quform-button-submit .quform-submit',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit .quform-submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit .quform-submit:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit .quform-submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'error_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-error-inner' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'error_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-error-inner' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'error_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .quform-form .quform-error-inner',
	), array(
		'name' => 'fullwidth_button',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quform-form .quform-button-submit, {{WRAPPER}} .quform-form .quform-button-submit .quform-submit' => 'width: 100%;',
		),
	) );
