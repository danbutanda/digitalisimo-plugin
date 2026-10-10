<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-content-switcher` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'content_switcher_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher' => 'text-align: {{VALUE}};',
		),
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
		'name' => 'badge_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher-badge' => '{{VALUE}};',
		),
		'default' => 'left',
		'selectors_dictionary' => array(
			'left' => 'left: 0;',
			'right' => 'right: 0;',
		),
		'condition' => array(
			'badge' => 'yes',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'badge_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-content-switcher-badge-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
			'badge' => 'yes',
		),
	), array(
		'name' => 'badge_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-content-switcher-badge-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => -40,
		),
		'tablet_default' => array(
			'size' => -40,
		),
		'mobile_default' => array(
			'size' => -40,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
			'badge' => 'yes',
		),
	), array(
		'name' => 'badge_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-content-switcher-badge-rotate: {{SIZE}}deg;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
			'badge' => 'yes',
		),
	), array(
		'name' => 'arrows_horizontal_offset_left',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-content-switcher-arrows-h-offset-left: {{SIZE}}px;',
		),
		'default' => array(
			'size' => -35,
		),
		'tablet_default' => array(
			'size' => -35,
		),
		'mobile_default' => array(
			'size' => -35,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
			'badge' => 'yes',
			'badge_align' => 'left',
		),
	), array(
		'name' => 'arrows_horizontal_offset_right',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-content-switcher-arrows-h-offset-right: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 40,
		),
		'tablet_default' => array(
			'size' => 40,
		),
		'mobile_default' => array(
			'size' => 40,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
			'badge' => 'yes',
			'badge_align' => 'right',
		),
	), array(
		'name' => 'arrows_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-content-switcher-arrows-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => -26,
		),
		'tablet_default' => array(
			'size' => -26,
		),
		'mobile_default' => array(
			'size' => -26,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
			'badge' => 'yes',
		),
	), array(
		'name' => 'arrows_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-content-switcher-arrows-rotate: {{SIZE}}deg;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
			'badge' => 'yes',
		),
	), array(
		'name' => 'switch_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tabs' => 'gap: {{SIZE}}px;',
		),
	), array(
		'name' => 'switch_icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tab' => 'gap: {{SIZE}}px;',
		),
	), array(
		'name' => 'switch_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-content-switcher-icon, {{WRAPPER}} .digi-content-switcher__tab',
	), array(
		'name' => 'switch_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher-icon i, {{WRAPPER}} .digi-content-switcher__tab' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-content-switcher-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'switcher_button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab',
	), array(
		'name' => 'switcher_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab',
	), array(
		'name' => 'switcher_button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'switcher_style' => 'button',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'switcher_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'switcher_style' => 'button',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'switch_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab',
	), array(
		'name' => 'switcher_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab',
	), array(
		'name' => 'switch_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab',
	), array(
		'name' => 'switch_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"] .digi-content-switcher-icon i, {{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'switcher_button_active_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]',
	), array(
		'name' => 'switcher_button_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'switcher_style' => 'button',
			'switcher_button_border_border!' => '',
		),
	), array(
		'name' => 'switch_active_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]',
	), array(
		'name' => 'switcher_button_shadow_active',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]',
	), array(
		'name' => 'switch_active_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]',
	), array(
		'name' => 'switcher_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher .button' => 'width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'switcher_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher .button' => 'height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'switcher_knob_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-knob-size: {{SIZE}}px;',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'switcher_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'switcher_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher .button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'switcher_bar_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tabs',
	), array(
		'name' => 'switcher_bar_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tabs',
	), array(
		'name' => 'switcher_bar_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tabs' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'switcher_bar_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tabs' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'switcher_bar_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__tabs' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'switcher_bar_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-content-switcher__tabs',
	), array(
		'name' => 'switcher_content_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__panel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'switcher_content_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-content-switcher__panel',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'switcher_content_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-content-switcher__panel',
	), array(
		'name' => 'switcher_content_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'switcher_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'switcher_content_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher__panel-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'switcher_content_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-content-switcher__panel',
	), array(
		'name' => 'badge_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher-badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher-badge' => 'background: {{VALUE}};',
			'{{WRAPPER}} .digi-content-switcher-badge:before' => 'border-top-color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-content-switcher-badge',
	), array(
		'name' => 'badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-content-switcher-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'badge_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-content-switcher-badge',
	), array(
		'name' => 'badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-content-switcher-badge',
	), array(
		'name' => 'original_price_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-pt-original-price-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
	), array(
		'name' => 'original_price_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-pt-original-price-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
	), array(
		'name' => 'original_price_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-pt-original-price-rotate: {{SIZE}}deg;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
	) );
