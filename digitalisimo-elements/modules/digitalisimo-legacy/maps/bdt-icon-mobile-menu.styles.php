<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-icon-mobile-menu` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__entry',
	), array(
		'name' => 'item_border_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-border-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'item_border_type!' => array( 'none' ),
			'menu_style' => 'style-2',
		),
	), array(
		'name' => 'item_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__entry' => 'border-color: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'item_border_type!' => array( 'none' ),
			'menu_style' => 'style-2',
		),
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__entry' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__entry' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__entry' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__entry',
	), array(
		'name' => 'item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__entry:hover',
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__entry:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_type!' => array( 'none' ),
		),
	), array(
		'name' => 'item_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__entry:hover',
	), array(
		'name' => 'item_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-icon-mobile-menu__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'item_icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__icon',
	), array(
		'name' => 'item_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__icon',
	), array(
		'name' => 'item_icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_icon_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__icon',
	), array(
		'name' => 'item_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'item_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'item_text_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__text',
	), array(
		'name' => 'item_text_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__text',
	), array(
		'name' => 'item_text_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-icon-mobile-menu__text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_text_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__text',
	), array(
		'name' => 'item_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-icon-mobile-menu__text',
	) );
