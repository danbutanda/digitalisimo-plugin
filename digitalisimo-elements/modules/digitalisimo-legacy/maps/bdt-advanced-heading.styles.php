<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-advanced-heading` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => 'text-align: {{VALUE}};',
		),
		'default' => 'center',
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
		'name' => 'advanced_heading_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__decoration' => 'text-align: {{VALUE}};',
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
		'name' => 'advanced_heading_x_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-advanced-heading-pos-x: {{SIZE}}px;',
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
		'name' => 'advanced_heading_y_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-advanced-heading-pos-y: {{SIZE}}px;',
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
		'name' => 'advanced_heading_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-advanced-heading-rotate: {{SIZE}}deg;',
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
		'name' => 'sub_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__sub' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_heading_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__sub',
	), array(
		'name' => 'sub_heading_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__sub',
	), array(
		'name' => 'sub_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__sub',
	), array(
		'name' => 'sub_heading_style_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__sub .line:after' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'sub_heading_style' => 'line',
		),
	), array(
		'name' => 'sub_heading_style_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__sub .line:after' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'sub_heading_style' => 'line',
		),
	), array(
		'name' => 'sub_heading_style_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__sub .line:after' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'sub_heading_style' => 'line',
		),
	), array(
		'name' => 'sub_heading_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__sub i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-advanced-heading__sub svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'sub_heading_style' => 'icon',
		),
	), array(
		'name' => 'sub_heading_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__sub',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'sub_heading_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__sub',
	), array(
		'name' => 'sub_heading_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__sub' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'sub_heading_advanced_style_popover' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'sub_heading_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__sub' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'sub_heading_advanced_style_popover' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'main_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__main' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
		),
	), array(
		'name' => 'main_heading_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__main' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'main_heading_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__main',
	), array(
		'name' => 'main_heading_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__main' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'main_heading_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__main' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'main_heading_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'main_heading_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__main',
	), array(
		'name' => 'main_heading_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__main',
	), array(
		'name' => 'main_heading_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__main',
	), array(
		'name' => 'main_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__main',
	), array(
		'name' => 'main_heading_style_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__title .line:after' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'main_heading_style' => 'line',
		),
	), array(
		'name' => 'main_heading_style_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__title .line:after' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'main_heading_style' => 'line',
		),
	), array(
		'name' => 'main_heading_style_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__title .line:after' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'main_heading_style' => 'line',
		),
	), array(
		'name' => 'main_heading_advanced_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__main',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'main_heading_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__title > a:hover .digi-advanced-heading__main' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'main_heading_advanced_style_color!' => 'yes',
		),
	), array(
		'name' => 'mainh_split_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__split' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
		),
		'condition' => array(
			'split_main_heading' => 'yes',
			'split_text!' => '',
		),
	), array(
		'name' => 'mainh_split_text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__split' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'split_main_heading' => 'yes',
			'split_text!' => '',
		),
	), array(
		'name' => 'mainh_split_text_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__split',
	), array(
		'name' => 'mainh_split_text_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__split' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'split_main_heading' => 'yes',
			'split_text!' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'mainh_split_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__split' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'split_main_heading' => 'yes',
			'split_text!' => '',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'split_text_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__main' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 10,
		),
		'condition' => array(
			'split_main_heading' => 'yes',
			'split_text!' => '',
		),
	), array(
		'name' => 'main_heading_split_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__split',
	), array(
		'name' => 'mainh_split_text_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__split',
	), array(
		'name' => 'mainh_split_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__split',
	), array(
		'name' => 'mainh_split_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__split',
	), array(
		'name' => 'advanced_heading_advanced_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__decoration',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'advanced_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__decoration' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
		),
		'condition' => array(
			'advanced_heading_advanced_color!' => 'yes',
		),
	), array(
		'name' => 'advanced_heading_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__decoration' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'advanced_heading_advanced_color!' => 'yes',
		),
	), array(
		'name' => 'advanced_heading_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__decoration',
	), array(
		'name' => 'advanced_heading_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__decoration' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'advanced_heading_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__decoration' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'advanced_heading_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-heading__decoration' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'advanced_heading_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__decoration',
	), array(
		'name' => 'advanced_heading_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__decoration',
	), array(
		'name' => 'advanced_heading_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__decoration',
	), array(
		'name' => 'advanced_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-heading__decoration',
	) );
