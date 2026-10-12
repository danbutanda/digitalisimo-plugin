<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-advanced-counter` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter' => 'text-align: {{VALUE}};',
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
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'top_icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-top-icon-v-offset: -{{SIZE}}px;',
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
			'position' => 'top',
			'show_icon' => 'yes',
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'top_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon-wrap' => '-webkit-transform: translate({{SIZE}}px, var(--ep-top-icon-v-offset, 0)); transform: translate({{SIZE}}px, var(--ep-top-icon-v-offset, 0));',
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
			'position' => 'top',
			'show_icon' => 'yes',
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'left_right_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-left-right-icon-h-offset: {{SIZE}}px;',
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
			'position' => array( 'left', 'right' ),
			'show_icon' => 'yes',
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'left_right_icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon-wrap' => '-webkit-transform: translate(var(--ep-left-right-icon-h-offset, 0), {{SIZE}}px); transform: translate(var(--ep-left-right-icon-h-offset, 0), {{SIZE}}px);',
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
			'position' => array( 'left', 'right' ),
			'show_icon' => 'yes',
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'indicator_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-indicator' => 'width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'indicator_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-indicator-h-offset: {{SIZE}}px;',
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
			'indicator_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'indicator_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-indicator-v-offset: {{SIZE}}px;',
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
			'indicator_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'indicator_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-indicator-rotate: {{SIZE}}deg;',
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
			'indicator_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-counter-icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-counter-icon',
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-counter-icon',
	), array(
		'name' => 'icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'condition' => array(
			'icon_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_radius_advanced',
		'type' => 'text',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon' => 'border-radius: {{VALUE}}; overflow: hidden;',
			'{{WRAPPER}} .elementor-counter-icon img' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'icon_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-counter-icon',
	), array(
		'name' => 'icon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-counter-icon',
	), array(
		'name' => 'icon_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}.elementor-position-right .elementor-counter-icon-wrap' => 'margin-left: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}}.elementor-position-left .elementor-counter-icon-wrap' => 'margin-right: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}}.elementor-position-top .elementor-counter-icon-wrap' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			'(mobile){{WRAPPER}} .elementor-counter-icon-wrap' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'image_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon' => 'width: 100%;box-sizing: border-box;',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon' => 'font-size: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-counter-icon img' => 'width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'vh', 'vw' ),
	), array(
		'name' => 'rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon i' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .elementor-counter-icon svg' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .elementor-counter-icon img' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
			'unit' => 'deg',
		),
	), array(
		'name' => 'icon_background_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .elementor-counter img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon:after',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_hover_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon',
	), array(
		'name' => 'icon_hover_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon i' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon svg' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon img' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'icon_hover_background_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-icon img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'counter_number_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-number-wrapper' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'content_number_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-number-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'counter_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-number-wrapper' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'counter_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-counter-number-wrapper',
	), array(
		'name' => 'counter_number_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-number-wrapper' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'counter_number_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-counter:hover .elementor-counter-number-wrapper',
	), array(
		'name' => 'content_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'content_text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'content_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-counter-title',
	), array(
		'name' => 'content_text_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter:hover .elementor-counter-content .elementor-counter-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'content_text_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-counter:hover .elementor-counter-content .elementor-counter-title',
	), array(
		'name' => 'counter_number_separator_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator' => 'border-top-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'counter_number_separator_type' => 'line',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'counter_number_separator_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'counter_number_separator_type' => 'line',
		),
	), array(
		'name' => 'counter_number_separator_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator' => 'border-top-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'counter_number_separator_type' => 'line',
		),
	), array(
		'name' => 'counter_number_separator_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'counter_number_separator_type' => 'line',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'counter_number_separator_svg_fill_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator-wrap svg *' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'counter_number_separator_type!' => 'line',
		),
	), array(
		'name' => 'counter_number_separator_svg_stroke_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator-wrap svg *' => 'stroke: {{VALUE}};',
		),
		'condition' => array(
			'counter_number_separator_type!' => 'line',
		),
	), array(
		'name' => 'counter_number_separator_svg_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator-wrap > *' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'counter_number_separator_type!' => 'line',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'counter_number_separator_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-separator-wrap' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'indicator_fill_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-indicator svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'indicator_stroke_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-indicator svg *' => 'stroke: {{VALUE}};',
		),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_inline_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-counter-icon-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'position' => array( 'left', 'right' ),
			'icon_inline' => 'yes',
		),
	) );
