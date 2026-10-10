<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-charitable-donation-form` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'form_fields_email_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .donor-contact-details, {{WRAPPER}} .elementor-shortcode .donor-address' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'form_fields_update_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-fields .charitable-fieldset a:not(.button)' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'form_fields_prement_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-fieldset-field-header' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'form_fields_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-fields .charitable-fieldset' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'form_fields_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-form-fields .charitable-fieldset',
	), array(
		'name' => 'form_fields_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-fields .charitable-fieldset' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_fields_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-fields .charitable-fieldset' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'form_fields_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-fields .charitable-fieldset' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'form_fields_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-form-fields .charitable-fieldset',
	), array(
		'name' => 'main_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-header' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'main_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-header' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'main_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-form-header',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field.charitable-radio-list li' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-form-field label',
	), array(
		'name' => 'donation_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form label span.amount, {{WRAPPER}} .elementor-shortcode .charitable-donation-form label span.description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'donation_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .donation-amounts .donation-amount' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'donation_active_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .donation-amount.selected' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
		),
	), array(
		'name' => 'donation_fields_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-donation-form .donation-amounts .donation-amount',
	), array(
		'name' => 'donation_fields_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .donation-amounts .donation-amount' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'donation_fields_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .donation-amounts .donation-amount' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'donation_fields_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .donation-amounts .donation-amount' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'donation_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-donation-form label span.amount, {{WRAPPER}} .elementor-shortcode .charitable-donation-form label span.description',
	), array(
		'name' => 'donation_input_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .custom-donation-input' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'donation_input_field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .custom-donation-input' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'donation_input_field_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .custom-donation-input' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'donation_infut_fields_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-donation-form .custom-donation-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="email"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="date"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="time"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="number"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="url"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="password"], {{WRAPPER}} .elementor-shortcode .charitable-form-field textarea, {{WRAPPER}} .elementor-shortcode .charitable-form-field select' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="email"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="date"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="time"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="number"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="url"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="password"], {{WRAPPER}} .elementor-shortcode .charitable-form-field textarea, {{WRAPPER}} .elementor-shortcode .charitable-form-field select' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'placeholder_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input:not([type="submit"])::-webkit-input-placeholder' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input:not([type="submit"])::-moz-placeholder' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input:not([type="submit"])::-ms-input-placeholder' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input:not([type="submit"])::-o-placeholder' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field textarea::-webkit-input-placeholder' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field textarea::-moz-placeholder' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field textarea::-ms-input-placeholder' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field textarea::-o-placeholder' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="email"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="date"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="time"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="number"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="url"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="password"], {{WRAPPER}} .elementor-shortcode .charitable-form-field textarea, {{WRAPPER}} .elementor-shortcode .charitable-form-field select',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="email"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="date"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="time"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="number"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="url"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="password"], {{WRAPPER}} .elementor-shortcode .charitable-form-field textarea, {{WRAPPER}} .elementor-shortcode .charitable-form-field select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="email"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="date"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="time"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="number"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="url"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="password"], {{WRAPPER}} .elementor-shortcode .charitable-form-field textarea, {{WRAPPER}} .elementor-shortcode .charitable-form-field select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="text"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="email"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="date"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="time"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="number"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="url"], {{WRAPPER}} .elementor-shortcode .charitable-form-field input[type="password"], {{WRAPPER}} .elementor-shortcode .charitable-form-field textarea, {{WRAPPER}} .elementor-shortcode .charitable-form-field select',
	), array(
		'name' => 'checked_normal_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-radio-list input[type="radio"]:after' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'checked_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-radio-list input[type="radio"]:checked:after' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'submit_button_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field' => 'text-align: {{VALUE}}',
		),
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
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button',
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
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .charitable-submit-field .button:hover',
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
	) );
