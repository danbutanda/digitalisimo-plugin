<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-total-count` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count' => 'text-align: {{VALUE}};',
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
			'{{WRAPPER}} .digi-total-count-icon' => '-webkit-transform: translate({{SIZE}}px, var(--ep-top-icon-v-offset, 0)); transform: translate({{SIZE}}px, var(--ep-top-icon-v-offset, 0));',
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
			'{{WRAPPER}} .digi-total-count-icon' => '-webkit-transform: translate(var(--ep-left-right-icon-h-offset, 0), {{SIZE}}px); transform: translate(var(--ep-left-right-icon-h-offset, 0), {{SIZE}}px);',
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
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper',
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper',
	), array(
		'name' => 'icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'condition' => array(
			'icon_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_radius_advanced',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper' => 'border-radius: {{VALUE}}; overflow: hidden;',
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper img' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'icon_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper',
	), array(
		'name' => 'icon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper',
	), array(
		'name' => 'icon_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}.elementor-position-right .digi-total-count-icon' => 'margin-left: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}}.elementor-position-left .digi-total-count-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}}.elementor-position-top .digi-total-count-icon' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			'(mobile){{WRAPPER}} .digi-total-count-icon' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'image_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper' => 'width: 100%;box-sizing: border-box;',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'vh', 'vw' ),
	), array(
		'name' => 'rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper i' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper img' => 'transform: rotate({{SIZE}}{{UNIT}});',
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
			'{{WRAPPER}} .digi-total-count .digi-total-count-icon-wrapper' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-total-count img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count img' => 'transition-duration: {{SIZE}}s',
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
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper:after',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_hover_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper',
	), array(
		'name' => 'icon_hover_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper i' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper img' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'icon_hover_background_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count-icon-wrapper img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'counter_number_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count__number' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'counter_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count__label .digi-total-count__number' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'counter_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-total-count__label .digi-total-count__number',
	), array(
		'name' => 'counter_number_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count__label .digi-total-count__number' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'counter_number_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-total-count:hover .digi-total-count__label .digi-total-count__number',
	), array(
		'name' => 'content_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count__label .digi-total-count__label-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'content_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-total-count__label .digi-total-count__label-text',
	), array(
		'name' => 'content_text_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count__label .digi-total-count__label-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'content_text_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-total-count:hover .digi-total-count__label .digi-total-count__label-text',
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count__label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	) );
