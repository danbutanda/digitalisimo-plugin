<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-ninja-form` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .nf-field-label label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .nf-field-label label',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap>div input:not([type*="button"])::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap>div input:not([type *="button"])' => 'color: {{VALUE}};',
			'{{WRAPPER}} .field-wrap textarea' => 'color: {{VALUE}};',
			'{{WRAPPER}} .field-wrap select' => 'color: {{VALUE}};',
			'{{WRAPPER}} .field-wrap select option' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap>div input:not([type *="button"])' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .field-wrap textarea' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .field-wrap select' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .field-wrap select option' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap textarea' => 'height: {{SIZE}}{{UNIT}}; display: block;',
		),
		'default' => array(
			'size' => 125,
		),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap>div input:not([type*="button"])' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .field-wrap textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .field-wrap select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .nf-field-container' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 25,
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .field-wrap>div input:not([type*="button"]), {{WRAPPER}} .field-wrap textarea, {{WRAPPER}} .field-wrap select',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap>div input:not([type*="button"])' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .field-wrap textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .field-wrap select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]',
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap.submit-wrap .nf-field-element input[type*=submit]:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'fullwidth_input',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap>div input:not([type*="button"])' => 'width: 100%;',
			'{{WRAPPER}} .field-wrap select' => 'width: 100%;',
		),
	), array(
		'name' => 'fullwidth_textarea',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap textarea' => 'width: 100%;',
		),
	), array(
		'name' => 'fullwidth_button',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .field-wrap>div input[type="submit"], {{WRAPPER}} .field-wrap>div input[type="button"], {{WRAPPER}} .field-wrap>div button' => 'width: 100%;',
		),
	) );
