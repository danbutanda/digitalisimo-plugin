<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-wc-elements` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce form .form-row label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'required_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce form .form-row .required' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce form .form-row label',
	), array(
		'name' => 'input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce select' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce textarea.input-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce select' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce textarea.input-text' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'textarea_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce textarea.input-text' => 'height: {{SIZE}}{{UNIT}}; display: block;',
		),
		'default' => array(
			'size' => 125,
		),
	), array(
		'name' => 'input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text, {{WRAPPER}} .woocommerce textarea.input-text, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .woocommerce select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .select2-container--default .select2-selection--single' => 'height: auto; min-height: 37px;',
			'{{WRAPPER}} .select2-container--default .select2-selection--single .select2-selection__rendered' => 'line-height: initial;',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'input_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce form .form-row' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 25,
		),
	), array(
		'name' => 'input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce .input-text, {{WRAPPER}} .woocommerce select, {{WRAPPER}} .select2-container--default .select2-selection--single',
	), array(
		'name' => 'input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .woocommerce select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'order_table_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table th, {{WRAPPER}} .woocommerce table.shop_table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'order_table_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce table.shop_table',
	), array(
		'name' => 'order_table_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'checkout_payment_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce-checkout #payment, {{WRAPPER}} .woocommerce-checkout #payment div.payment_box' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'checkout_payment_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce-checkout #payment' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce-checkout #payment div.payment_box' => 'opacity:0.5;',
			'{{WRAPPER}} .woocommerce-checkout #payment div.payment_box::before' => 'opacity:0.5;',
		),
	), array(
		'name' => 'payment_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'payment_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'payment_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce input.button',
	), array(
		'name' => 'payment_button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'payment_button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpcf7-submit',
	), array(
		'name' => 'payment_button_text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'payment_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce input.button',
	), array(
		'name' => 'payment_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'payment_button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'payment_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'tracking_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce form .form-row label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tracking_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce form .form-row label',
	), array(
		'name' => 'tracking_input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tracking_input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce select' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce textarea.input-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tracking_input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce select' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce textarea.input-text' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tracking_input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text, {{WRAPPER}} .woocommerce textarea.input-text, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .woocommerce select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .select2-container--default .select2-selection--single' => 'height: auto; min-height: 37px;',
			'{{WRAPPER}} .select2-container--default .select2-selection--single .select2-selection__rendered' => 'line-height: initial;',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'tracking_input_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce form .form-row' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 25,
		),
	), array(
		'name' => 'tracking_input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce .input-text, {{WRAPPER}} .woocommerce select, {{WRAPPER}} .select2-container--default .select2-selection--single',
	), array(
		'name' => 'tracking_input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .woocommerce select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'tracking_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button, {{WRAPPER}} .woocommerce button.button, {{WRAPPER}} .woocommerce a.button' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'tracking_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button, {{WRAPPER}} .woocommerce button.button, {{WRAPPER}} .woocommerce a.button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tracking_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce input.button, {{WRAPPER}} .woocommerce button.button, {{WRAPPER}} .woocommerce a.button',
	), array(
		'name' => 'tracking_button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button, {{WRAPPER}} .woocommerce button.button, {{WRAPPER}} .woocommerce a.button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'tracking_button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpcf7-submit, {{WRAPPER}} .woocommerce button.button, {{WRAPPER}} .woocommerce a.button',
	), array(
		'name' => 'tracking_button_text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button, {{WRAPPER}} .woocommerce button.button, {{WRAPPER}} .woocommerce a.button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'tracking_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce input.button, {{WRAPPER}} .woocommerce button.button, {{WRAPPER}} .woocommerce a.button',
	), array(
		'name' => 'tracking_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button:hover, {{WRAPPER}} .woocommerce button.button:hover, {{WRAPPER}} .woocommerce a.button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tracking_button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button:hover, {{WRAPPER}} .woocommerce button.button:hover, {{WRAPPER}} .woocommerce a.button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tracking_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce input.button:hover, {{WRAPPER}} .woocommerce button.button:hover, {{WRAPPER}} .woocommerce a.button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tracking_button_border_border!' => '',
		),
	), array(
		'name' => 'cart_table_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table.cart th' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_table_heading_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table.cart th' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_table_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table.cart td *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_table_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table.cart' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_table_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table.cart td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'cart_table_border_width',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table.cart' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .woocommerce table.shop_table.cart td' => 'border-top-width: {{TOP}}{{UNIT}};',
		),
		'condition' => array(
			'cart_table_border_show' => array( 'yes' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'cart_table_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table.shop_table.cart' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce table.shop_table.cart td' => 'border-top-color: {{VALUE}};',
		),
		'condition' => array(
			'cart_table_border_show' => array( 'yes' ),
		),
	), array(
		'name' => 'cart_table_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .input-text, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .woocommerce select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'cart_input_placeholder_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} table.cart .input-text::placeholder' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_input_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} table.cart .input-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_input_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} table.cart .input-text' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_input_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} table.cart .input-text, {{WRAPPER}} table.cart td.actions .coupon .input-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; box-sizing: content-box;',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'cart_input_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} table.cart .input-text, {{WRAPPER}} table.cart td.actions .coupon .input-text',
	), array(
		'name' => 'cart_input_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} table.cart .input-text, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .woocommerce select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'cart_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table tr td button.button, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'cart_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table tr td button.button, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce table tr td button.button, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button',
	), array(
		'name' => 'cart_button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table tr td button.button, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'cart_button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .woocommerce table tr td button.button, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button',
	), array(
		'name' => 'cart_button_text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table tr td button.button, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'cart_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce table tr td button.button',
	), array(
		'name' => 'cart_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table tr td button.button:hover, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table tr td button.button:hover, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce table tr td button.button:hover, {{WRAPPER}} .woocommerce table.cart td.actions .coupon .wp-element-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'cart_checkout_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'cart_checkout_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_checkout_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button',
	), array(
		'name' => 'cart_checkout_button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'cart_checkout_button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .wpcf7-submit',
	), array(
		'name' => 'cart_checkout_button_text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'cart_checkout_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button',
	), array(
		'name' => 'cart_checkout_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_checkout_button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'cart_checkout_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .wc-proceed-to-checkout a.checkout-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .product_title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .product_title',
	), array(
		'name' => 'regular_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product p.price del, {{WRAPPER}} .woocommerce div.product span.price del' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'price_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product p.price, {{WRAPPER}} .woocommerce div.product span.price' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce div.product p.price ins, {{WRAPPER}} .woocommerce div.product span.price ins' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'price_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product p.price, {{WRAPPER}} .woocommerce div.product span.price',
	), array(
		'name' => '_ep_wc_rating_row',
		'type' => 'hidden',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating' => 'display: flex; flex-wrap: wrap; align-items: center;',
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .star-rating' => 'float: none; margin-top: 0;',
		),
		'default' => '1',
	), array(
		'name' => 'product_rating_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating' => 'justify-content: {{VALUE}};',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'flex-end' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
			'space-between' => array(
				'title' => 'Space Between',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'product_rating_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'rem', '%' ),
	), array(
		'name' => 'product_rating_star_fill_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .star-rating span::before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_rating_star_empty_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .star-rating::before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_rating_star_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .woocommerce-product-rating .star-rating',
	), array(
		'name' => 'product_rating_star_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .star-rating' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'em',
		),
		'size_units' => array( 'px', 'em', 'rem' ),
	), array(
		'name' => 'product_rating_star_review_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .star-rating' => 'margin-inline-end: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'rem' ),
	), array(
		'name' => 'product_rating_review_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .woocommerce-review-link' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_rating_review_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .woocommerce-review-link:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_rating_review_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .woocommerce-product-rating .woocommerce-review-link',
	), array(
		'name' => 'product_rating_count_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .woocommerce-product-rating .woocommerce-review-link .count' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_rating_count_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .woocommerce-product-rating .woocommerce-review-link .count',
	), array(
		'name' => '_ep_wc_product_meta_stack',
		'type' => 'hidden',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta' => 'display: flex; flex-direction: column;',
		),
		'default' => '1',
	), array(
		'name' => 'product_meta_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'rem' ),
	), array(
		'name' => 'product_meta_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%', 'rem' ),
	), array(
		'name' => 'product_meta_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%', 'rem' ),
	), array(
		'name' => 'product_meta_sku_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .sku_wrapper' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_sku_value_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .sku_wrapper .sku' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_sku_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .product_meta .sku_wrapper',
	), array(
		'name' => 'product_meta_sku_value_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .product_meta .sku_wrapper .sku',
	), array(
		'name' => 'product_meta_category_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .posted_in' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_category_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .posted_in a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_category_link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .posted_in a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .product_meta .posted_in',
	), array(
		'name' => 'product_meta_category_link_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .product_meta .posted_in a',
	), array(
		'name' => 'product_meta_tags_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .tagged_as' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_tags_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .tagged_as a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_tags_link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product_meta .tagged_as a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'product_meta_tags_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .product_meta .tagged_as',
	), array(
		'name' => 'product_meta_tags_link_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .product_meta .tagged_as a',
	), array(
		'name' => 'sd_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce-product-details__short-description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sd_color_typo',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce-product-details__short-description',
	), array(
		'name' => 'add_to_cart_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product form.cart .button' => 'color: {{VALUE}}; cursor:pointer;',
		),
		'default' => '#fff',
	), array(
		'name' => 'add_to_cart_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .woocommerce div.product form.cart .button',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'add_to_cart_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce div.product form.cart .button',
	), array(
		'name' => 'add_to_cart_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product form.cart .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'add_to_cart_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product form.cart .button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'qty_fields_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product form.cart .button' => 'margin-left: {{SIZE}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'add_to_cart_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product form.cart .button',
	), array(
		'name' => 'add_to_cart_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .woocommerce div.product form.cart .button',
	), array(
		'name' => 'add_to_cart_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product form.cart .button:hover, {{WRAPPER}} .woocommerce div.product form.cart .button:focus' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'add_to_cart_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product form.cart .button:hover, {{WRAPPER}} .woocommerce div.product form.cart .button:focus' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'add_to_cart_border_border!' => '',
		),
	), array(
		'name' => 'add_to_cart_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .woocommerce div.product form.cart .button:hover, {{WRAPPER}} .elementor-button:focus',
		'exclude' => array( 'image' ),
	), array(
		'name' => 'qty_fields_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quantity input[type=number]' => 'color: {{VALUE}} ',
			'{{WRAPPER}} .quantity input[type=number]::placeholder' => 'color: {{VALUE}} ',
		),
	), array(
		'name' => 'qty_fields_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
		'exclude' => array( 'image' ),
	), array(
		'name' => 'qty_fields_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
	), array(
		'name' => 'qty_fields_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .quantity input[type=number]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => '3',
			'right' => '3',
			'bottom' => '3',
			'left' => '3',
			'isLinked' => false,
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'qty_fields_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .quantity input[type=number]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ;',
		),
	), array(
		'name' => 'qty_fields_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
	), array(
		'name' => 'qty_fields_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .quantity input[type=number]',
	), array(
		'name' => 'tabs_nav_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'tabs_nav_item_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'tabs_nav_item_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a',
	), array(
		'name' => 'tabs_nav_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a',
	), array(
		'name' => 'tabs_nav_item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'tabs_nav_item_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_nav_item_bg',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'tabs_nav_item_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs::before' => 'border-bottom-color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_nav_item_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_nav_item_bg_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'tabs_nav_item_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tabs_nav_item_border_border!' => '',
		),
	), array(
		'name' => 'tabs_nav_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li.active a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_nav_bg_active',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li.active a',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'tabs_nav_item_border_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs ul.tabs li.active a' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tabs_nav_item_border_border!' => '',
		),
	), array(
		'name' => 'tabs_content_panel_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--description > h2' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information > h2' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_panel_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--description > h2, {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information > h2',
	), array(
		'name' => 'tabs_content_panel_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--description > h2' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information > h2' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'tabs_content_description_body_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_description_body_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--description > *:not(h2)',
	), array(
		'name' => 'tabs_content_attributes_th_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information table.shop_attributes th' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_attributes_th_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information table.shop_attributes th',
	), array(
		'name' => 'tabs_content_attributes_td_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information table.shop_attributes td' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_attributes_td_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information table.shop_attributes td',
	), array(
		'name' => 'tabs_content_attributes_cell_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information table.shop_attributes th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--additional_information table.shop_attributes td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'tabs_content_reviews_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .woocommerce-Reviews-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_reviews_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .woocommerce-Reviews-title',
	), array(
		'name' => 'tabs_content_reviews_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .woocommerce-Reviews-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'tabs_content_reviews_author_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .woocommerce-review__author' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_reviews_meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .comment-text .meta' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_reviews_meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .comment-text .meta',
	), array(
		'name' => 'tabs_content_reviews_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .comment-text .description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_reviews_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .comment-text .description',
	), array(
		'name' => 'tabs_content_reviews_star_fill_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .comment-text .star-rating span::before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_reviews_star_empty_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .comment-text .star-rating::before' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_reviews_star_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews .comment-text .star-rating' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'em',
		),
		'size_units' => array( 'px', 'em', 'rem' ),
	), array(
		'name' => 'tabs_content_review_form_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper .comment-reply-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper .comment-reply-title',
	), array(
		'name' => 'tabs_content_review_form_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper label',
	), array(
		'name' => 'tabs_content_review_form_field_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=text], {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=email]' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper textarea' => 'color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper select' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_field_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=text], {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=email]' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper textarea' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper select' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_field_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=text], {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=email], {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper textarea, {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper select',
	), array(
		'name' => 'tabs_content_review_form_field_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=text]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=email]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'tabs_content_review_form_field_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=text], {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper input[type=email], {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper textarea, {{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper select',
	), array(
		'name' => 'tabs_content_review_form_submit_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_submit_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_submit_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit',
	), array(
		'name' => 'tabs_content_review_form_submit_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'tabs_content_review_form_submit_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit',
	), array(
		'name' => 'tabs_content_review_form_submit_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tabs_content_review_form_submit_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_submit_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tabs_content_review_form_submit_hover_border',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce div.product .woocommerce-tabs .woocommerce-Tabs-panel--reviews #review_form_wrapper #submit:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tabs_content_review_form_submit_border_border!' => '',
		),
	), array(
		'name' => 'related_items_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .woocommerce ul.products li.product',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'related_items_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce ul.products li.product',
	), array(
		'name' => 'related_items_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'related_items_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'related_items_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .woocommerce ul.products li.product',
	), array(
		'name' => 'related_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product .woocommerce-loop-product__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'related_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce ul.products li.product .woocommerce-loop-product__title',
	), array(
		'name' => 'related_price_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product .price' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'related_price_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce ul.products li.product .price',
	), array(
		'name' => 'related_cart_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product a.button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'related_cart_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product a.button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'related_cart_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce ul.products li.product a.button',
	), array(
		'name' => 'related_cart_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product a.button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', '%' ),
	), array(
		'name' => 'related_cart_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce ul.products li.product a.button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'rem', 'em', '%' ),
	), array(
		'name' => 'related_cart_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce ul.products li.product a.button',
	) );
