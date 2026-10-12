<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-timeline` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'button_icon[value]!' => '',
		),
	), array(
		'name' => 'item_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__item-main' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline .digi-timeline-arrow' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline__item--top .digi-timeline-content:after' => 'border-top-color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline__item--bottom .digi-timeline-content:after' => 'border-bottom-color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline--mobile .digi-timeline-content:after' => 'border-right-color: {{VALUE}};',
		),
		'default' => '#f3f3f3',
	), array(
		'name' => 'item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__item-main',
	), array(
		'name' => 'timeline_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline-divider, {{WRAPPER}} .digi-timeline .digi-timeline-line span, {{WRAPPER}} .digi-timeline:not(.digi-timeline--horizontal):before' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline .digi-timeline__item:after, {{WRAPPER}} .digi-timeline.digi-timeline-skin-default .digi-timeline__item-main-wrapper .digi-timeline-icon span' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'timeline_line_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline-divider' => 'height: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-timeline .digi-timeline-line span' => 'width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-timeline-skin-olivier .digi-timeline__item:after, {{WRAPPER}} .digi-timeline.digi-timeline-skin-default .digi-timeline__item-main-wrapper .digi-timeline-icon span' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 4,
		),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-desc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__item-main',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__item-main' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span' => 'background-color: {{VALUE}};',
		),
		'default' => '#ffffff',
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline-icon span',
	), array(
		'name' => 'icon_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span i, {{WRAPPER}} .digi-timeline .digi-timeline-icon span' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'icon_show' => 'yes',
		),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-timeline.digi-timeline-skin-default .digi-timeline__item-main-wrapper .digi-timeline-icon span',
	), array(
		'name' => 'icon_border_radius',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span' => 'border-radius: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 50,
			'unit' => '%',
		),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span i:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span svg:hover' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-icon span:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'date_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'background-color: {{VALUE}};',
		),
		'default' => '#f3f3f3;',
	), array(
		'name' => 'date_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__date span',
	), array(
		'name' => 'date_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'default' => array(
			'top' => '2',
			'right' => '2',
			'bottom' => '2',
			'left' => '2',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'date_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => '10',
			'right' => '15',
			'bottom' => '10',
			'left' => '15',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__date',
	), array(
		'name' => 'date_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__date span',
	), array(
		'name' => 'image_ratio',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-thumbnail img' => 'height: {{SIZE}}px',
		),
		'default' => array(
			'size' => 265,
		),
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-thumbnail img' => 'opacity: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-thumbnail' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => '20',
			'right' => '20',
			'bottom' => '0',
			'left' => '20',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline-thumbnail',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-thumbnail' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title a:hover' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'title_link' => 'yes',
		),
	), array(
		'name' => 'title_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__title *',
	), array(
		'name' => 'title_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title *' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title *' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__title',
	), array(
		'name' => 'meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-meta *' => 'color: {{VALUE}};',
		),
		'default' => '#bbbbbb',
	), array(
		'name' => 'meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline-meta *',
	), array(
		'name' => 'meta_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-meta' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__text' => 'color: {{VALUE}};',
		),
		'default' => '#888888',
	), array(
		'name' => 'excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__text',
	), array(
		'name' => 'excerpt_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__text' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'readmore_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline-readmore',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline-readmore',
	), array(
		'name' => 'readmore_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline-readmore',
	), array(
		'name' => 'readmore_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore:hover svg' => 'fill: {{VALUE}} !important;',
		),
	), array(
		'name' => 'readmore_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-readmore:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'navigation_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-nav-button:before' => 'border-top-color: {{VALUE}}; border-left-color: {{VALUE}};',
		),
	), array(
		'name' => 'navigation_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline-nav-button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'navigation_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-timeline-nav-button',
	), array(
		'name' => 'navigation_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline-nav-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'navigation_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-timeline-nav-button',
	), array(
		'name' => 'navigation_button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline-nav-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'navigation_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline-nav-button:hover:before' => 'border-top-color: {{VALUE}}; border-left-color: {{VALUE}};',
		),
	), array(
		'name' => 'navigation_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline-nav-button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'navigation_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline-nav-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'navigation_button_border_border!' => '',
		),
	) );
