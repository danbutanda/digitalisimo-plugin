<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-edd-tabs` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_profile_editor_form label',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_profile_editor_form textarea::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form input' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_profile_editor_form .wpcf7-textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'others_type_input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form.select-state' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_profile_editor_form.select-gender' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_profile_editor_form.accept-this-1' => 'color: {{VALUE}};',
		),
		'default' => '#666666',
	), array(
		'name' => 'input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form input' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} #edd_profile_editor_form .wpcf7-textarea' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form .wpcf7-textarea' => 'height: {{SIZE}}{{UNIT}}; display: block;',
		),
		'default' => array(
			'size' => 125,
		),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form input, {{WRAPPER}} #edd_profile_editor_form .wpcf7-textarea, {{WRAPPER}} #edd_profile_editor_form .select.edd-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_profile_editor_form input, {{WRAPPER}} #edd_profile_editor_form textarea, {{WRAPPER}} #edd_profile_editor_form .select.edd-select',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} #edd_profile_editor_form textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} #edd_profile_editor_form .select.edd-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_profile_editor_submit',
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'fullwidth_input',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form input[type*="text"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_profile_editor_form input[type*="email"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_profile_editor_form input[type*="url"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_profile_editor_form input[type*="number"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_profile_editor_form input[type*="tel"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_profile_editor_form input[type*="date"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_profile_editor_form input[type*="password"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_profile_editor_form .select.edd-select' => 'width: 100%;',
		),
	), array(
		'name' => 'fullwidth_textarea',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form textarea' => 'width: 100%;',
		),
	), array(
		'name' => 'fullwidth_button',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_profile_editor_form #edd_profile_editor_submit' => 'width: 100%;',
		),
	), array(
		'name' => 'profile_fieldset_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content fieldset' => 'border-color: {{VALUE}}',
		),
	), array(
		'name' => 'profile_fieldset_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}  .digi-accordion__content fieldset' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'profile_editor_fieldset_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content fieldset' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	) );
