<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-charitable-login` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'charitable_login_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'charitable_login_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form',
	), array(
		'name' => 'charitable_login_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'charitable_login_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'charitable_login_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form label',
	), array(
		'name' => 'input_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="password"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="password"]' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="password"]',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="password"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="password"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-login-form input[type="password"]',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_text_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form .login-submit .button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'password_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form p > a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'password_divider_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-login-form>p>a:nth-last-child(1):before' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'password_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-login-form p > a',
	) );
