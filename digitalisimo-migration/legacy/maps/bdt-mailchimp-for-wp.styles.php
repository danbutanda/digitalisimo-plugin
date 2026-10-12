<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-mailchimp-for-wp` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="text"]::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} .mc4wp-form input[type*="email"]::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} .mc4wp-form select[name*="_mc4wp_lists"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="text"]' => 'color: {{VALUE}};',
			'{{WRAPPER}} .mc4wp-form input[type*="email"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type  *="text"]' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .mc4wp-form input[type  *="email"]' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .mc4wp-form select[name *="_mc4wp_lists"]' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .mc4wp-form input[type*="text"], {{WRAPPER}} .mc4wp-form input[type*="email"], {{WRAPPER}} .mc4wp-form select[name*="_mc4wp_lists"]',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="text"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .mc4wp-form input[type*="email"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .mc4wp-form select[name*="_mc4wp_lists"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="text"], {{WRAPPER}} .mc4wp-form input[type*="email"], {{WRAPPER}} .mc4wp-form select[name*="_mc4wp_lists"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fullwidth_input',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="email"]' => 'width: 100%;',
			'{{WRAPPER}} .mc4wp-form input[type*="text"]' => 'width: 100%;',
			'{{WRAPPER}} .mc4wp-form select[name*="_mc4wp_lists"]' => 'width: 100%;',
		),
	), array(
		'name' => 'label_position_top',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form label' => 'display: block;',
		),
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .mc4wp-form label',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .mc4wp-form input[type*="submit"]',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .mc4wp-form input[type*="submit"]',
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'fullwidth_button',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]' => 'width: 100%;',
		),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .mc4wp-form input[type*="submit"]',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form input[type*="submit"]:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'others_type_input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .mc4wp-form label span' => 'color: {{VALUE}};',
			'{{WRAPPER}} .mc4wp-form p' => 'color: {{VALUE}};',
		),
		'default' => '#666666',
	), array(
		'name' => 'others_type_input_text_color_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .mc4wp-form label span',
	) );
