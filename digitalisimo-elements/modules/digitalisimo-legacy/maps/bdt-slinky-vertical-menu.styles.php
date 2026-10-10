<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-slinky-vertical-menu` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'menu_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu' => 'max-width: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'menu_text_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu' => 'text-align: {{VALUE}};',
		),
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
		'name' => 'menu_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu li > .digi-vertical-menu__row > .digi-vertical-menu__link span' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu li > .digi-vertical-menu__row > .digi-vertical-menu__link svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'menu_link_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu li > .digi-vertical-menu__row > .digi-vertical-menu__link',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'menu_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu li > .digi-vertical-menu__row > .digi-vertical-menu__link',
	), array(
		'name' => 'menu_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu  li > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'main_menu_bg_link_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu li > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'menu_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu li:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
		),
		'tablet_default' => array(
			'size' => 1,
		),
		'mobile_default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'menu_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu li > .digi-vertical-menu__row > .digi-vertical-menu__link',
	), array(
		'name' => 'menu_link_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu li:hover > .digi-vertical-menu__row > .digi-vertical-menu__link span' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu li:hover > .digi-vertical-menu__row > .digi-vertical-menu__link svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'link_background_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu li:hover > .digi-vertical-menu__row > .digi-vertical-menu__link',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'menu_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu li:hover > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'menu_border_border!' => '',
		),
	), array(
		'name' => 'indicator_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__arrow' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'indicator_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__arrow',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'indicator_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__arrow',
	), array(
		'name' => 'indicator_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__arrow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'indicator_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__arrow' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'indicator_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__arrow' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'indicator_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__arrow',
	), array(
		'name' => 'indicator_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button:hover::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__row:hover .digi-vertical-menu__arrow' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'indicator_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button:hover::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__row:hover .digi-vertical-menu__arrow',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'indicator_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__back-button:hover::before, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__row:hover .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'indicator_border_border!' => '',
		),
	) );
