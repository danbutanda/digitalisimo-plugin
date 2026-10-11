<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-wc-add-to-cart` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button-content-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'selected_icon[value]!' => '',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-button svg' => 'fill: {{VALUE}};',
		),
		'default' => '#fff',
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button' => 'background-color: {{VALUE}};',
		),
		'default' => '#1E87F0',
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-button',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-button',
	), array(
		'name' => 'typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-button',
	), array(
		'name' => 'text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-button',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button:hover, {{WRAPPER}} .elementor-button:focus' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-button:hover svg, {{WRAPPER}} .elementor-button:focus svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button:hover, {{WRAPPER}} .elementor-button:focus' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button:hover, {{WRAPPER}} .elementor-button:focus' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'qty_fields_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quantity' => 'width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'qty_fields_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quantity input[type=number]' => 'color: {{VALUE}} ',
			'{{WRAPPER}} .quantity input[type=number]::placeholder' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'qty_fields_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
	), array(
		'name' => 'qty_fields_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
	), array(
		'name' => 'qty_fields_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
	), array(
		'name' => 'qty_fields_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .quantity input[type=number]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ;',
		),
	), array(
		'name' => 'qty_fields_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
	), array(
		'name' => 'qty_fields_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .quantity input[type=number]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	) );
