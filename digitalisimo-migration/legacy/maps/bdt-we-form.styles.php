<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-we-form` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'large_field_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form > li.wpuf-el.field-size-large > .wpuf-fields input:not([type=radio]):not([type=checkbox])' => 'width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .wpuf-form > li.wpuf-el.field-size-large > .wpuf-fields textarea' => 'width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => '%',
			'size' => 100,
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'field_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-el:not(.wpuf-submit)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'field_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-fields input:not(.weforms_submit_btn)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'field_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-fields input:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-fields textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'field_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:not(.weforms_submit_btn)',
	), array(
		'name' => 'field_textcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} ::-webkit-input-placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} ::-moz-placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} ::-ms-input-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea',
	), array(
		'name' => 'field_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea',
	), array(
		'name' => 'field_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'field_focus_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:focus:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea:focus',
	), array(
		'name' => 'field_focus_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:focus:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea:focus',
		'exclude' => array( 'box_shadow_position' ),
	), array(
		'name' => 'field_focus_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields input:focus:not(.weforms_submit_btn), {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-fields textarea:focus' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'label_spacing',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpuf-label label, {{WRAPPER}} .wpuf-form-sub-label',
	), array(
		'name' => 'desc_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpuf-fields .wpuf-help',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-label label, {{WRAPPER}} .wpuf-form-sub-label' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'requered_label',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-label .required' => 'color: {{VALUE}} !important',
		),
	), array(
		'name' => 'desc_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-fields .wpuf-help' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'button_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit .weforms_submit_btn' => 'display: block; width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => '%',
			'size' => 100,
		),
		'condition' => array(
			'submit_btn_width' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submit_btn_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit' => 'text-align: {{Value}};',
		),
		'desktop_default' => 'left',
		'condition' => array(
			'submit_btn_width' => '',
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
		'name' => 'submit_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submit_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'submit_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]',
	), array(
		'name' => 'submit_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]',
	), array(
		'name' => 'submit_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submit_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]',
	), array(
		'name' => 'submit_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]',
	), array(
		'name' => 'submit_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'submit_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'submit_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]:hover, {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]:focus' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'submit_hover_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]:hover, {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]:focus' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'submit_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]:hover, {{WRAPPER}} .wpuf-form-add.wpuf-style ul.wpuf-form .wpuf-submit input[type=submit]:focus' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'break_separator_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .section_break .wpuf-section-wrap' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'break_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .section_break .wpuf-section-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'break_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .section_break .wpuf-section-title',
	), array(
		'name' => 'break_description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .section_break .wpuf-section-details' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'break_description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .section_break .wpuf-section-details',
	) );
