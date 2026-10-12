<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-edd-checkout` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'edd_register_form_input_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input[type*="text"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_checkout_form_wrap input[type*="email"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_checkout_form_wrap input[type*="url"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_checkout_form_wrap input[type*="number"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_checkout_form_wrap input[type*="tel"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_checkout_form_wrap input[type*="date"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_checkout_form_wrap input[type*="password"]' => 'width: 100%;',
			'{{WRAPPER}} #edd_checkout_form_wrap .select.edd-select' => 'width: 100%;',
		),
	), array(
		'name' => 'edd_register_form_button_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap .edd-submit' => 'width: 100%;',
		),
	), array(
		'name' => 'checkout_header_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_header_row th' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'checkout_header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_checkout_cart .edd_cart_header_row th',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_header_row th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_checkout_cart .edd_cart_header_row th',
	), array(
		'name' => 'checkout_header_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_header_row th:hover' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'checkout_header_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_checkout_cart .edd_cart_header_row th:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'checkout_cell_border_style',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_checkout_cart .edd_cart_item td',
	), array(
		'name' => 'cell_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_item td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 0.5,
			'bottom' => 0.5,
			'left' => 1,
			'right' => 1,
			'unit' => 'em',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'normal_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_item td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_action_btn_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_item td a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_item td' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'row_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_item td:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'row_action_btn_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_item td a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'row_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart .edd_cart_item td:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'checkout_total_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart th.edd_cart_total' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'checkout_total_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_checkout_cart th.edd_cart_total',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'checkout_total_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_cart th.edd_cart_total' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'checkout_total_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_checkout_cart th.edd_cart_total',
	), array(
		'name' => 'checkout_total_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_checkout_cart th.edd_cart_total',
	), array(
		'name' => 'profile_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}  .elementor-shortcode #edd_checkout_form_wrap legend' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'profile_label_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode #edd_checkout_form_wrap fieldset' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'profile_label_border_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode #edd_checkout_form_wrap fieldset' => 'border-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'profile_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode #edd_checkout_form_wrap legend',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}  #edd_checkout_form_wrap label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap label',
	), array(
		'name' => 'sub_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}  #edd_checkout_form_wrap span.edd-description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap span.edd-description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'sub_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap span.edd-description',
	), array(
		'name' => 'field_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input::-moz-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'field_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'field_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; height: auto;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'field_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap input.edd-input',
	), array(
		'name' => 'field_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap input.edd-input',
	), array(
		'name' => 'field_text_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input:focus' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_placeholder_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input:focus::placeholder' => 'color: {{VALUE}};',
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input:focus::-moz-placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'field_background_color_focus',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input:focus' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'field_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap input.edd-input:focus' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'field_border_border!' => '',
		),
	), array(
		'name' => 'checkout_purchase_total_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd_final_total_wrap strong' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'checkout_purchase_total_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd_final_total_wrap' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'checkout_purchase_total_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap #edd_final_total_wrap',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'checkout_purchase_total_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd_final_total_wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'checkout_purchase_total_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap #edd_final_total_wrap',
	), array(
		'name' => 'checkout_purchase_total_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap #edd_final_total_wrap > *',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_checkout_form_wrap #edd-purchase-button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} #edd_register_form #edd-purchase-button:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} #edd_register_form #edd-purchase-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} #edd_register_form #edd-purchase-button:hover',
	) );
