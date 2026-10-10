<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fluent-forms` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_color_label',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group label' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'typography_label',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .ff-el-group label',
	), array(
		'name' => 'input_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'text-align: {{VALUE}};',
		),
		'default' => '',
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
		'name' => 'field_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'background-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'field_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select',
	), array(
		'name' => 'field_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_text_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'text-indent: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'width: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'input_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'height: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'textarea_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group textarea' => 'width: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group textarea' => 'height: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select',
	), array(
		'name' => 'field_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]), {{WRAPPER}} .elementor-shortcode .ff-el-group textarea, {{WRAPPER}} .elementor-shortcode .ff-el-group select',
	), array(
		'name' => 'field_bg_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]):focus, {{WRAPPER}} .elementor-shortcode .ff-el-group textarea:focus' => 'background-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'focus_input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]):focus, {{WRAPPER}} .elementor-shortcode .ff-el-group textarea:focus',
	), array(
		'name' => 'focus_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode input:not([type=radio]):not([type=checkbox]):not([type=submit]):not([type=button]):not([type=image]):not([type=file]):focus, {{WRAPPER}} .elementor-shortcode .ff-el-group textarea:focus',
	), array(
		'name' => 'text_color_placeholder',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group input::-webkit-input-placeholder, {{WRAPPER}} .elementor-shortcode .ff-el-group textarea::-webkit-input-placeholder' => 'color: {{VALUE}}',
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
		'name' => 'section_break_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-section-break .ff-el-section-title' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'section_break_label_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-section-break .ff-el-section-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'section_break_label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-section-break .ff-el-section-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'section_break_description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-section-break div' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'section_break_description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .ff-el-section-break div',
	), array(
		'name' => 'section_break_description_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-section-break div' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'section_break_description_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-section-break div' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'address_line_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .fluent-address label' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'address_line_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .fluent-address label',
	), array(
		'name' => 'button_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit' => 'width: {{SIZE}}{{UNIT}}',
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
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit' => 'background-color: {{VALUE}} !important;',
		),
		'default' => '#409EFF',
	), array(
		'name' => 'button_text_color_normal',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit' => 'color: {{VALUE}} !important;',
		),
		'default' => '#ffffff',
	), array(
		'name' => 'button_border_normal',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit' => 'margin-top: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit',
	), array(
		'name' => 'button_bg_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit:hover' => 'background-color: {{VALUE}} !important;',
		),
		'default' => '',
	), array(
		'name' => 'button_text_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit:hover' => 'color: {{VALUE}} !important;',
		),
		'default' => '',
	), array(
		'name' => 'button_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-el-group .ff-btn-submit:hover' => 'border-color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ff-el-progress-status' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'show_label' => 'yes',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .ff-el-progress-status',
	), array(
		'name' => 'label_space',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ff-el-progress-status' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_label' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'progressbar_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ff-el-progress' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'show_progressbar' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'progressbar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ff-el-progress-bar span' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_progressbar' => 'yes',
		),
	), array(
		'name' => 'progressbar_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .ff-el-progress',
	), array(
		'name' => 'progressbar_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .ff-el-progress' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_progressbar' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'progressbar_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .ff-el-progress',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'progressbar_bg_filled',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .ff-el-progress-bar',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'pagination_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .step-nav button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .step-nav button',
	), array(
		'name' => 'pagination_button_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .step-nav button',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'pagination_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .step-nav button',
	), array(
		'name' => 'pagination_button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .step-nav button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'pagination_button_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .step-nav button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'pagination_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .step-nav button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagination_button_hover_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .step-nav button:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'pagination_button_border_hover_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .step-nav button:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'success_message_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-message-success' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'success_message_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .ff-message-success' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'success_message_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .ff-message-success',
	), array(
		'name' => 'success_message_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .ff-message-success',
	), array(
		'name' => 'error_message_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .error.text-danger' => 'color: {{VALUE}}',
		),
		'default' => '',
		'condition' => array(
			'error_messages' => 'show',
		),
	), array(
		'name' => 'error_message_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .error.text-danger',
	), array(
		'name' => 'error_message_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .error.text-danger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'error_message_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .error.text-danger' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	) );
