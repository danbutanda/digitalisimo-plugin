<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-edd-login` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'edd_login_form_input_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form input[type*="text"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_login_form input[type*="password"]' => 'width: 100%;',
		),
	), array(
		'name' => 'edd_login_form_button_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-submit' => 'width: 100%;',
		),
	), array(
		'name' => 'form_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form legend' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'form_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_login_form legend',
	), array(
		'name' => 'links_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-lost-password a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'links_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-lost-password a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'links_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_login_form .edd-lost-password a',
	), array(
		'name' => 'label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-input' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}  #edd_login_form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_login_form label',
	), array(
		'name' => 'field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form input[type="text"], {{WRAPPER}} #edd_login_form input[type="password"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_login_form .edd-input::-moz-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-input' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'field_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_login_form .edd-input',
	), array(
		'name' => 'field_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'field_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form .edd-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; height: auto;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_login_form .edd-input',
	), array(
		'name' => 'field_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_login_form .edd-input',
	), array(
		'name' => 'field_text_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form input:focus' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_placeholder_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form input:focus::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_login_form input:focus::-moz-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_background_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form input:focus' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'field_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form input:focus' => 'border-color: {{VALUE}}; outline:none;',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form #edd_login_submit' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_login_form #edd_login_submit',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_login_form #edd_login_submit',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form #edd_login_submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form #edd_login_submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form #edd_login_submit' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_login_form #edd_login_submit',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_login_form #edd_login_submit',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form #edd_login_submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_login_form #edd_login_submit:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_login_form #edd_login_submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_login_form #edd_login_submit:hover',
	) );
