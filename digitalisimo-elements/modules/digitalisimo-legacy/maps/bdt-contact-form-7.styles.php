<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-contact-form-7` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form > p:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 0,
		),
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpcf7-form label',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap textarea::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input' => 'color: {{VALUE}};',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap .wpcf7-textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'others_type_input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap.select-state' => 'color: {{VALUE}};',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap.select-gender' => 'color: {{VALUE}};',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap.accept-this-1' => 'color: {{VALUE}};',
		),
		'default' => '#666666',
	), array(
		'name' => 'input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap .wpcf7-textarea' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-textarea' => 'height: {{SIZE}}{{UNIT}}; display: block;',
		),
		'default' => array(
			'size' => 125,
		),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input, {{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap .wpcf7-textarea, {{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap .wpcf7-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form-control' => 'margin-top: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .wpcf7-form' => 'margin-top: -{{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 25,
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input, {{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap textarea, {{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap .wpcf7-select',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap .wpcf7-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wpcf7-form .wpcf7-submit',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpcf7-submit',
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpcf7-form .wpcf7-submit',
	), array(
		'name' => 'button_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form p:has(.wpcf7-submit)' => 'text-align: {{VALUE}};',
		),
		'default' => 'left',
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
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'fullwidth_input',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input[type*="text"]' => 'width: 100%;',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input[type*="email"]' => 'width: 100%;',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input[type*="url"]' => 'width: 100%;',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input[type*="number"]' => 'width: 100%;',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input[type*="tel"]' => 'width: 100%;',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap input[type*="date"]' => 'width: 100%;',
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap .wpcf7-select' => 'width: 100%;',
		),
	), array(
		'name' => 'fullwidth_textarea',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-form-control-wrap textarea' => 'width: 100%;',
		),
	), array(
		'name' => 'fullwidth_button',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpcf7-form .wpcf7-submit' => 'width: 100%;',
		),
	) );
