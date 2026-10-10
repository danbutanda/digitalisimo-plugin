<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-marquee` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'marquee_vertical_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee.marquee-vertical' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'px',
			'size' => 500,
		),
		'condition' => array(
			'marquee_direction' => array( 'top', 'bottom' ),
		),
		'size_units' => array( 'px', 'vh', '%' ),
	), array(
		'name' => 'marquee_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--bdt-marquee-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'marquee_advanced' => 'yes',
		),
	), array(
		'name' => 'marquee_rotate_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--bdt-marquee-offset: -{{SIZE}}px;',
		),
		'condition' => array(
			'marquee_advanced' => 'yes',
		),
	), array(
		'name' => 'marquee_rotate_adjustment',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--bdt-marquee-adjustment: {{SIZE}}px;',
		),
		'condition' => array(
			'marquee_advanced' => 'yes',
		),
	), array(
		'name' => 'skin_shadow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-shadow-color: {{VALUE}};',
		),
		'condition' => array(
			'skin_shadow_mode' => 'yes',
		),
	), array(
		'name' => 'marquee_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content .marquee-title' => 'color: {{VALUE}} !important',
		),
	), array(
		'name' => 'marquee_title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'marquee_title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content',
	), array(
		'name' => 'marquee_title_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'marquee_title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'marquee_title_typogrphy',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content',
	), array(
		'name' => 'marquee_title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content',
	), array(
		'name' => 'marquee_title_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content:hover .marquee-title' => 'color: {{VALUE}} !important',
		),
	), array(
		'name' => 'marquee_title_h_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'marquee_title_border_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'marquee_title_border_border!' => '',
		),
	), array(
		'name' => 'marquee_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content .marquee-icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'rem' ),
	), array(
		'name' => 'marquee_icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content .marquee-title' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'marquee_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content .marquee-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;',
		),
		'default' => '#fff',
	), array(
		'name' => 'marquee_icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content:hover .marquee-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;',
		),
	), array(
		'name' => 'marquee_image_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content' => 'height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'vh', '%' ),
	), array(
		'name' => 'marquee_image_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'marquee_image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content',
	), array(
		'name' => 'marquee_image_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'marquee_image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-image img',
	), array(
		'name' => 'marquee_image_background_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-content:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'marquee_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .marquee-content:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'marquee_image_border_border!' => '',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-marquee .marquee-image:hover img',
	) );
