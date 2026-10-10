<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-wp-forms` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field-label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field-label',
	), array(
		'name' => 'sub_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field-sublabel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field-sublabel',
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field input' => 'color: {{VALUE}};',
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field select' => 'color: {{VALUE}};',
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'others_type_input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-field-label-inline' => 'color: {{VALUE}};',
		),
		'default' => '#666666',
	), array(
		'name' => 'input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field input' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field textarea' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field textarea' => 'height: {{SIZE}}{{UNIT}}; display: block;',
		),
		'default' => array(
			'size' => 125,
		),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field input, {{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field textarea, {{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field + .wpforms-field' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 25,
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field input, {{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field textarea, {{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field select',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-field select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wpforms-container .wpforms-submit',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpforms-container .wpforms-submit',
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpforms-container .wpforms-submit',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'error_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-error' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'error_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpforms-container .wpforms-form .wpforms-error',
	), array(
		'name' => 'fullwidth_button',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpforms-container .wpforms-submit' => 'width: 100%;',
		),
	) );
