<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-edd-cart` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'checkout_header_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart-number-of-items' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'checkout_header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart-number-of-items',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart-number-of-items' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'header_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart-number-of-items',
	), array(
		'name' => 'checkout_header_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart-number-of-items:hover' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'checkout_header_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart-number-of-items:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'normal_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_action_btn_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item span.edd-action-btn-remove-icon' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'normal_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'checkout_cell_border_style',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item',
	), array(
		'name' => 'cell_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 5,
			'bottom' => 5,
			'left' => 10,
			'right' => 10,
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'checkout_cart_items_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item span',
	), array(
		'name' => 'row_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item:hover span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'row_action_btn_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item:hover a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'row_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd-cart-item:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'checkout_cart_total_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_total' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'checkout_cart_total_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_total',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'checkout_cart_total_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_total' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'checkout_cart_total_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_total',
	), array(
		'name' => 'checkout_cart_total_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_total',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a::before',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .edd-cart .edd_checkout a:hover',
	) );
