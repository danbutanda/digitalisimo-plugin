<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-give-login` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'give_login_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form > fieldset' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'give_login_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form > fieldset',
	), array(
		'name' => 'give_login_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form > fieldset' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'give_login_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form > fieldset' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'give_login_legend_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'text-align: {{VALUE}};',
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
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form legend',
	), array(
		'name' => 'title_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'give_login_legend_width_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form legend' => 'width: calc(100% - {{SIZE}}{{UNIT}}); margin: 0 auto;',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-form legend',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-form label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login label',
	), array(
		'name' => 'input_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login input[type="text"], {{WRAPPER}} .elementor-shortcode .give-login input[type="password"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login input[type="text"], {{WRAPPER}} .elementor-shortcode .give-login input[type="password"]',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login input[type="text"], {{WRAPPER}} .elementor-shortcode .give-login input[type="password"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login input[type="text"], {{WRAPPER}} .elementor-shortcode .give-login input[type="password"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login input[type="text"], {{WRAPPER}} .elementor-shortcode .give-login input[type="password"]',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login .give_submit' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login .give_submit',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login .give_submit',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login .give_submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login .give_submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login .give_submit',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login .give_submit',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login .give_submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-login .give_submit:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-login .give_submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'password_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-lost-password a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'password_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-lost-password a',
	) );
