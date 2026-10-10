<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-edd-register` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'edd_register_form_input_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form input[type*="text"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_register_form input[type*="email"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_register_form input[type*="url"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_register_form input[type*="number"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_register_form input[type*="tel"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_register_form input[type*="date"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_register_form input[type*="password"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_register_form .select.edd-select' => 'width: 100%;',
		),
	), array(
		'name' => 'edd_register_form_button_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-submit' => 'width: 100%;',
		),
	), array(
		'name' => 'form_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form legend' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'form_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_register_form legend',
	), array(
		'name' => 'label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form input[type*="text"]' => 'margin-top: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} #edd_register_form input[type*="email"]' => 'margin-top: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} #edd_register_form input[type*="password"]' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}  #edd_register_form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_register_form label',
	), array(
		'name' => 'field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_register_form .edd-input::-moz-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'field_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'field_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; height: auto;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-input',
	), array(
		'name' => 'field_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-input',
	), array(
		'name' => 'field_text_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input:focus' => 'color: {{VALUE}}; outline:none;',
		),
	), array(
		'name' => 'field_placeholder_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input:focus::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_register_form .edd-input:focus::-moz-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_background_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input:focus' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'field_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-input:focus' => 'border-color: {{VALUE}}; outline:none;',
		),
		'condition' => array(
			'field_border_border!' => '',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-submit' => 'color: {{VALUE}}; outline:none',
		),
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-submit',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-submit',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-submit' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-submit',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-submit',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-submit:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form .edd-submit:hover' => 'border-color: {{VALUE}}; outline:none;',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_register_form .edd-submit:hover',
	) );
