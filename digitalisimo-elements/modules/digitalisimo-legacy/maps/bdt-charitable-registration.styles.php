<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-charitable-registration` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'charitable_ragistration_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'charitable_ragistration_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode',
	), array(
		'name' => 'charitable_ragistration_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'charitable_ragistration_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'charitable_ragistration_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode label',
	), array(
		'name' => 'input_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="password"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="password"]' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="password"]',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="password"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="password"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="password"]',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_text_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'password_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode p > a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'password_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode p > a',
	) );
