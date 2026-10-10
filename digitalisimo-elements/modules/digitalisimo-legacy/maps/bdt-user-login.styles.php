<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-user-login` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'modal_avatar_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login-button-avatar img' => 'width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 24,
		),
	), array(
		'name' => 'dropdown_avatar_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login-button-avatar img' => 'width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 24,
		),
	), array(
		'name' => 'looged_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'looged_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'looged_link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'logout_button_default_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login .elementor-button' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'logout_button_default_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-login .elementor-button',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'logout_button_default_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-login .elementor-button',
	), array(
		'name' => 'logout_button_default_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-login .elementor-button',
	), array(
		'name' => 'logout_button_default_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-login .elementor-button',
	), array(
		'name' => 'logout_button_default_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login .elementor-button:hover' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'logout_button_default_hover_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-login .elementor-button:hover',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'logout_button_default_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-login .elementor-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'logout_button_default_border_border!' => '',
		),
	) );
