<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-breadcrumbs` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'breadcrumbs_separator',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__separator' => 'content: "{{VALUE}}";',
		),
		'default' => '/',
	), array(
		'name' => 'home_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__lists-home-icon' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'home_icon[value]!' => '',
		),
	), array(
		'name' => 'breadcrumb_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator), {{WRAPPER}} .digi-breadcrumbs__separator',
	), array(
		'name' => 'breadcrumb_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator)' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'link_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator)',
	), array(
		'name' => 'link_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'link_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator)',
	), array(
		'name' => 'link_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator):hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'link_hover_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator):hover',
	), array(
		'name' => 'link_hover_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator):hover',
	), array(
		'name' => 'active_item_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item:last-child > span',
	), array(
		'name' => 'active_item_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item:last-child > span' => 'color: {{VALUE}}!important;',
		),
		'default' => '',
	), array(
		'name' => 'active_item_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item:last-child > span',
	), array(
		'name' => 'active_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item:last-child > span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'active_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item:last-child > span',
	), array(
		'name' => 'active_item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item:last-child > span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'active_item_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__item:last-child > span:hover' => 'color: {{VALUE}}!important;',
		),
	), array(
		'name' => 'active_item_hover_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item:last-child > span:hover',
	), array(
		'name' => 'active_item_hover_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__item:last-child > span:hover',
	), array(
		'name' => 'separator_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__separator' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'separator_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__separator',
	), array(
		'name' => 'separator_size',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-breadcrumbs__separator',
	), array(
		'name' => 'separator_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__separator' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'separator_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-breadcrumbs__separator' => 'margin: 0px {{SIZE}}{{UNIT}};',
		),
	) );
