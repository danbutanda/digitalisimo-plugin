<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-iconnav` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'brading_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__brand' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 30,
		),
		'condition' => array(
			'show_branding' => 'yes',
		),
	), array(
		'name' => 'iconnav_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__rail' => 'block-size: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'vh', '%' ),
	), array(
		'name' => 'iconnav_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--iconnav-v-offset: {{SIZE}}px;',
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
			'iconnav_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'iconnav_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--iconnav-h-offset: {{SIZE}}px;',
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
			'iconnav_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'iconnav_top_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__rail' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 80,
		),
	), array(
		'name' => 'iconnav_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav-container ul.digi-icon-nav.digi-icon-nav-vertical li + li' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__rail' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'glassmorphism_effect' => 'yes',
		),
	), array(
		'name' => 'iconnav_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__rail' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'iconnav_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-icon-nav__rail',
	), array(
		'name' => 'iconnav_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__rail' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'iconnav_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-icon-nav__rail',
	), array(
		'name' => 'iconnav_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 16,
		),
	), array(
		'name' => 'iconnav_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-icon-nav__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'iconnav_icon_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'iconnav_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-icon-nav__link',
	), array(
		'name' => 'iconnav_icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'iconnav_icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-icon-nav__link',
	), array(
		'name' => 'iconnav_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link' => '--bdt-iconnav-pt: {{TOP}}{{UNIT}}; --bdt-iconnav-pr: {{RIGHT}}{{UNIT}}; --bdt-iconnav-pb: {{BOTTOM}}{{UNIT}}; --bdt-iconnav-pl: {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'iconnav_icon_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'iconnav_icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link:hover .digi-icon-nav-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-icon-nav__link:hover .digi-icon-nav-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'iconnav_icon_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'iconnav_icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'iconnav_icon_border_border!' => '',
		),
	), array(
		'name' => 'iconnav_icon_active_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link:focus' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'iconnav_icon_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__link:focus' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'iconnav_icon_border_border!' => '',
		),
	), array(
		'name' => 'menu_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_text_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__text' => 'margin-top: {{SIZE}}px;',
		),
	), array(
		'name' => 'menu_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-icon-nav__text',
	), array(
		'name' => 'branding_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__brand' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'branding_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__brand' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'branding_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-icon-nav__brand',
	), array(
		'name' => 'branding_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-nav__brand' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'branding_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-icon-nav__brand',
	), array(
		'name' => 'branding_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-icon-nav__brand',
	) );
