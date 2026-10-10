<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-info` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:last-child)' => 'padding-bottom: calc({{SIZE}}{{UNIT}}/2)',
			'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:first-child)' => 'margin-top: calc({{SIZE}}{{UNIT}}/2)',
			'{{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item' => 'margin-right: calc({{SIZE}}{{UNIT}}/2); margin-left: calc({{SIZE}}{{UNIT}}/2)',
			'{{WRAPPER}} .elementor-icon-list-items.elementor-inline-items' => 'margin-right: calc(-{{SIZE}}{{UNIT}}/2); margin-left: calc(-{{SIZE}}{{UNIT}}/2)',
			'body.rtl {{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item:after' => 'left: calc(-{{SIZE}}{{UNIT}}/2)',
			'body:not(.rtl) {{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item:after' => 'right: calc(-{{SIZE}}{{UNIT}}/2)',
		),
		'size_units' => array( 'px', 'em', 'rem', 'custom' ),
	), array(
		'name' => 'icon_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-item' => 'justify-content: {{VALUE}}',
			'{{WRAPPER}} .elementor-icon-list-items.elementor-inline-items' => 'justify-content: {{VALUE}}',
		),
		'options' => array(
			'left' => array(
				'title' => 'Start',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'right' => array(
				'title' => 'End',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'divider',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'content: ""',
		),
	), array(
		'name' => 'divider_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:last-child):after' => 'border-top-style: {{VALUE}};',
			'{{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item:not(:last-child):after' => 'border-left-style: {{VALUE}}',
		),
		'default' => 'solid',
		'condition' => array(
			'divider' => 'yes',
		),
		'options' => array(
			'solid' => 'Solid',
			'double' => 'Double',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'divider_weight',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:last-child):after' => 'border-top-width: {{SIZE}}{{UNIT}}',
			'{{WRAPPER}} .elementor-inline-items .elementor-icon-list-item:not(:last-child):after' => 'border-left-width: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'size' => 1,
		),
		'condition' => array(
			'divider' => 'yes',
		),
	), array(
		'name' => 'divider_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'width: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'unit' => '%',
		),
		'condition' => array(
			'divider' => 'yes',
			'view!' => 'inline',
		),
		'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
	), array(
		'name' => 'divider_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'height: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'unit' => '%',
		),
		'condition' => array(
			'divider' => 'yes',
			'view' => 'inline',
		),
		'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
	), array(
		'name' => 'divider_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'border-color: {{VALUE}};',
		),
		'default' => '#ddd',
		'condition' => array(
			'divider' => 'yes',
		),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-icon i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-icon-list-icon svg' => 'fill: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'icon_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-icon i:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-icon-list-icon svg:hover' => 'fill: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-icon img' => 'width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-icon-list-icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 14,
		),
		'size_units' => array( 'px', 'em', 'rem', 'custom' ),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-text, {{WRAPPER}} .elementor-icon-list-text a' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'text_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-list-text:hover, {{WRAPPER}} .elementor-icon-list-text a:hover' => 'color: {{VALUE}}',
		),
		'default' => '',
	), array(
		'name' => 'text_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'body:not(.rtl) {{WRAPPER}} .elementor-icon-list-text' => 'padding-left: {{SIZE}}{{UNIT}}',
			'body.rtl {{WRAPPER}} .elementor-icon-list-text' => 'padding-right: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', 'em', 'rem', 'custom' ),
	), array(
		'name' => 'icon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-icon-list-item .elementor-post-info__item',
	) );
