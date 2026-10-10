<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-everest-forms` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'label_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-label .evf-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-label .evf-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .evf-field-label .evf-label',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-label .evf-label' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'inline_help_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .form-row .everest-forms-field-label-inline, {{WRAPPER}} .elementor-shortcode .form-row .evf-field-description' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'req_symbol_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row label .required' => 'color: {{VALUE}} !important',
		),
	), array(
		'name' => 'input_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input::-webkit-input-placeholder, {{WRAPPER}} .elementor-shortcode  email::-webkit-input-placeholder, {{WRAPPER}} .elementor-shortcode  number::-webkit-input-placeholder, {{WRAPPER}} .elementor-shortcode  select::-webkit-input-placeholder, {{WRAPPER}} .elementor-shortcode  url::-webkit-input-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_inner_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'box_border' => 'yes',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'box_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'condition' => array(
			'box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'input_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_field_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'box_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select' => 'border-color: {{VALUE}};',
		),
		'default' => '#252525',
		'condition' => array(
			'box_border' => 'yes',
		),
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode input[type="text"], {{WRAPPER}} .elementor-shortcode input[type="email"], {{WRAPPER}} .elementor-shortcode input[type="number"], {{WRAPPER}} .elementor-shortcode input[type="url"], {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select',
	), array(
		'name' => 'input_field_focus_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"]:focus, {{WRAPPER}} .elementor-shortcode input[type="email"]:focus, {{WRAPPER}} .elementor-shortcode input[type="number"]:focus, {{WRAPPER}} .elementor-shortcode input[type="url"]:focus, {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select:focus' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_field_focus_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode input[type="text"]:focus, {{WRAPPER}} .elementor-shortcode input[type="email"]:focus, {{WRAPPER}} .elementor-shortcode input[type="number"]:focus, {{WRAPPER}} .elementor-shortcode input[type="url"]:focus, {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select:focus',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'box_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"]:focus, {{WRAPPER}} .elementor-shortcode input[type="email"]:focus, {{WRAPPER}} .elementor-shortcode input[type="number"]:focus, {{WRAPPER}} .elementor-shortcode input[type="url"]:focus, {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select:focus' => 'border-color: {{VALUE}};',
		),
		'default' => '',
		'condition' => array(
			'box_border' => 'yes',
		),
	), array(
		'name' => 'border_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode input[type="text"]:focus, {{WRAPPER}} .elementor-shortcode input[type="email"]:focus, {{WRAPPER}} .elementor-shortcode input[type="number"]:focus, {{WRAPPER}} .elementor-shortcode input[type="url"]:focus, {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select:focus' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'box_active_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode input[type="text"]:focus, {{WRAPPER}} .elementor-shortcode input[type="email"]:focus, {{WRAPPER}} .elementor-shortcode input[type="number"]:focus, {{WRAPPER}} .elementor-shortcode input[type="url"]:focus, {{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row select:focus',
	), array(
		'name' => 'textarea_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'textarea_inner_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'textarea_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea',
	), array(
		'name' => 'textarea_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode textarea::-webkit-input-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'ta_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'ta_box_border' => 'yes',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'ta_box_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'condition' => array(
			'ta_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'textarea_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_field_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'ta_box_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'ta_box_border' => 'yes',
		),
	), array(
		'name' => 'ta_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'ta_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'ta_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea',
	), array(
		'name' => 'textarea_field_focus_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea:focus' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_field_focus_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea:focus',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'ta_box_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea:focus' => 'border-color: {{VALUE}};',
		),
		'default' => '',
		'condition' => array(
			'ta_box_border' => 'yes',
		),
	), array(
		'name' => 'ta_border_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea:focus' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'ta_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'ta_box_active_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field-container .evf-frontend-row textarea:focus',
	), array(
		'name' => 'checkbox_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .evf-field-checkbox label.everest-forms-field-label-inline',
	), array(
		'name' => 'checked_field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox label.everest-forms-field-label-inline' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'checkbox_typography',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox .everest-forms-field-label-inline:before' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'checked_uncheck_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox .everest-forms-field-label-inline:before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'checked_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-form .evf-field-checkbox input[type=checkbox]:checked + .everest-forms-field-label-inline:before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'unchecked_field_bgcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox .everest-forms-field-label-inline:before' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'checked_field_bgcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-form .evf-field-checkbox input[type=checkbox]:checked + .everest-forms-field-label-inline:before' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'check_box_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox .everest-forms-field-label-inline:before' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'check_box_border' => 'yes',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'check_box_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox .everest-forms-field-label-inline:before' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'condition' => array(
			'check_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'unchecked_box_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox .everest-forms-field-label-inline:before' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'check_box_border' => 'yes',
		),
	), array(
		'name' => 'unchecked_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-checkbox .everest-forms-field-label-inline:before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'check_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'radio_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .evf-field-radio label.everest-forms-field-label-inline',
	), array(
		'name' => 'radio_field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio label.everest-forms-field-label-inline' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'radio_typography',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio .everest-forms-field-label-inline:before' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'radio_uncheck_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio .everest-forms-field-label-inline:before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'radio_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-form .evf-field-radio input[type=radio]:checked + .everest-forms-field-label-inline:before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'radio_unchecked_field_bgcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio .everest-forms-field-label-inline:before' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'radio_checked_field_bgcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-form .evf-field-radio input[type=radio]:checked + .everest-forms-field-label-inline:before' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'radio_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio .everest-forms-field-label-inline:before' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'radio_border' => 'yes',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'radio_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio .everest-forms-field-label-inline:before' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'condition' => array(
			'radio_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'radio_unchecked_box_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio .everest-forms-field-label-inline:before' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'radio_border' => 'yes',
		),
	), array(
		'name' => 'radio_unchecked_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .evf-field-radio .everest-forms-field-label-inline:before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'radio_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'width: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]',
	), array(
		'name' => 'button_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'button_box_border' => 'yes',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'button_box_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 1,
			'right' => 1,
			'bottom' => 1,
			'left' => 1,
		),
		'condition' => array(
			'button_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_box_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_box_border' => 'yes',
		),
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit], {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button:hover, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit]:hover, {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button:hover, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit]:hover, {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_box_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button:hover, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit]:hover, {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]:hover' => 'border-color: {{VALUE}};',
		),
		'default' => '',
		'condition' => array(
			'button_box_border' => 'yes',
		),
	), array(
		'name' => 'button_border_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button:hover, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit]:hover, {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'condition' => array(
			'button_box_border' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-part-button:hover, {{WRAPPER}} .elementor-shortcode .everest-forms button[type=submit]:hover, {{WRAPPER}} .elementor-shortcode .everest-forms input[type=submit]:hover',
	), array(
		'name' => 'oute_r_inner_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'oute_r_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'oute_r_field_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'oute_r__border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field',
	), array(
		'name' => 'oute_r_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'oute_r_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field',
	), array(
		'name' => 'oute_r_field_bg_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'oute_r__border_hover',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field:hover',
	), array(
		'name' => 'oute_r_border_radius_hover',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'oute_r_shadow_hover',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .evf-field:hover',
	), array(
		'name' => 'form_cont_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_cont_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'form_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms',
	), array(
		'name' => 'form_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms',
	), array(
		'name' => 'form_bg_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'form_border_hover',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms:hover',
	), array(
		'name' => 'form_border_radius_hover',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'form_shadow_hover',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms:hover',
	), array(
		'name' => 'response_success_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice--success' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'response_success_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice--success, {{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice::before' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'response_success_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice--success',
	), array(
		'name' => 'response_success_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice--success' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'response_success_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice--success',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'response_success_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice--success',
	), array(
		'name' => 'response_success_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms .everest-forms-notice--success' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'response_validation_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms label.evf-error' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'response_validation_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms label.evf-error' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'response_validation_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms label.evf-error',
	), array(
		'name' => 'response_validation_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms label.evf-error' => 'color: {{VALUE}};',
			'{{WRAPPER}} .everest-forms .evf-field-container .evf-frontend-row .evf-frontend-grid .evf-field.everest-forms-invalid .select2-container, {{WRAPPER}} .everest-forms .evf-field-container .evf-frontend-row .evf-frontend-grid .evf-field.everest-forms-invalid input.input-text, {{WRAPPER}} .everest-forms .evf-field-container .evf-frontend-row .evf-frontend-grid .evf-field.everest-forms-invalid select, {{WRAPPER}} .everest-forms .evf-field-container .evf-frontend-row .evf-frontend-grid .evf-field.everest-forms-invalid textarea' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'response_validation_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms label.evf-error' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'response_validation_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .everest-forms label.evf-error',
	), array(
		'name' => 'response_validation_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms label.evf-error' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .everest-forms' => 'max-width: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%' ),
	) );
