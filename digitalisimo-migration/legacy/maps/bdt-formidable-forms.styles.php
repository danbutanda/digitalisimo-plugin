<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-formidable-forms` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_color_label',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field label, {{WRAPPER}} .elementor-shortcode .form-field .frm_primary_label' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'typography_label',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .form-field label',
	), array(
		'name' => 'input_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select' => 'text-align: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'fa fa-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'fa fa-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'fa fa-align-right',
			),
		),
	), array(
		'name' => 'field_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select' => 'background-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'field_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select',
	), array(
		'name' => 'field_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'text_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select' => 'text-indent: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field select' => 'width: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field select' => 'height: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'textarea_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field textarea' => 'width: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field textarea' => 'height: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select',
	), array(
		'name' => 'field_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .form-field input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea, {{WRAPPER}} .elementor-shortcode .form-field select',
	), array(
		'name' => 'focus_field_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:focus:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea:focus, {{WRAPPER}} .elementor-shortcode .form-field select:focus' => 'background-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'focus_field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:focus:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea:focus, {{WRAPPER}} .elementor-shortcode .form-field select:focus' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'focus_field_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input:focus:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea:focus, {{WRAPPER}} .elementor-shortcode .form-field select:focus' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'field_border_border!' => '',
		),
	), array(
		'name' => 'focus_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .form-field input:focus:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .form-field textarea:focus, {{WRAPPER}} .elementor-shortcode .form-field select:focus',
	), array(
		'name' => 'field_description_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field .frm_description' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'field_description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .form-field .frm_description',
	), array(
		'name' => 'field_description_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field .frm_description' => 'padding-top: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'text_color_placeholder',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-field input::-webkit-input-placeholder, {{WRAPPER}} .elementor-shortcode .form-field textarea::-webkit-input-placeholder' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'placeholder_switch' => 'yes',
		),
	), array(
		'name' => 'radio_checkbox_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="checkbox"], {{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="radio"]' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'size' => '15',
			'unit' => 'px',
		),
		'condition' => array(
			'custom_radio_checkbox' => 'yes',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'radio_checkbox_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="checkbox"], {{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="radio"]' => 'background: {{VALUE}}',
		),
		'default' => '',
		'condition' => array(
			'custom_radio_checkbox' => 'yes',
		),
	), array(
		'name' => 'checkbox_border_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="checkbox"], {{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="radio"]' => 'border-width: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'custom_radio_checkbox' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'checkbox_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="checkbox"], {{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="radio"]' => 'border-color: {{VALUE}}',
		),
		'default' => '',
		'condition' => array(
			'custom_radio_checkbox' => 'yes',
		),
	), array(
		'name' => 'checkbox_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="checkbox"], {{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="checkbox"]:before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'custom_radio_checkbox' => 'yes',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'radio_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="radio"], {{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="radio"]:before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'custom_radio_checkbox' => 'yes',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'radio_checkbox_color_checked',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="checkbox"]:checked:before, {{WRAPPER}} .elementor-shortcode.elementor-shortcode input[type="radio"]:checked:before' => 'background: {{VALUE}}',
		),
		'default' => '',
		'condition' => array(
			'custom_radio_checkbox' => 'yes',
		),
	), array(
		'name' => 'button_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit' => 'text-align: {{VALUE}};',
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit' => 'display:inline-block;',
		),
		'default' => '',
		'condition' => array(
			'button_width_type' => 'custom',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'button_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit' => 'width: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'size' => '100',
			'unit' => 'px',
		),
		'condition' => array(
			'button_width_type' => 'custom',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_bg_color_normal',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit' => 'background-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'button_text_color_normal',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'button_border_normal',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit' => 'margin-top: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit',
	), array(
		'name' => 'button_bg_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit:hover' => 'background-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'button_text_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit:hover' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'button_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_submit .frm_button_submit:hover' => 'border-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'error_label_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_error' => 'color: {{VALUE}}',
		),
		'default' => '',
		'condition' => array(
			'error_messages' => 'show',
		),
	), array(
		'name' => 'error_message_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .frm_error_style',
	), array(
		'name' => 'error_message_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_error_style' => 'color: {{VALUE}}',
		),
		'default' => '',
		'condition' => array(
			'error_messages' => 'show',
		),
	), array(
		'name' => 'error_message_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_error_style' => 'background-color: {{VALUE}}',
		),
		'default' => '',
		'condition' => array(
			'error_messages' => 'show',
		),
	), array(
		'name' => 'error_message_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .frm_error_style',
	), array(
		'name' => 'error_message_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_error_style' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'error_messages' => 'show',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'confirmation_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_message' => 'text-align: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'fa fa-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'fa fa-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'fa fa-align-right',
			),
		),
	), array(
		'name' => 'confirmation_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .frm_message',
	), array(
		'name' => 'confirmation_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_message' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'confirmation_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_message' => 'background-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'confirmation_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .frm_message',
	), array(
		'name' => 'confirmation_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .frm_message' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	) );
