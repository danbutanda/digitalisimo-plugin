<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-trailer-box` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'content_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-desc' => 'width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 400,
		),
	), array(
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-button' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'button_icon[value]!' => '',
		),
	), array(
		'name' => 'trailer_box_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-desc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'pre_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-pre-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pre_title_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-pre-title' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'pre_title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-pre-title',
	), array(
		'name' => 'pre_title_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-pre-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'pre_title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-pre-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'tb_pre_title_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-desc-inner .digi-fancy-card-pre-title',
	), array(
		'name' => 'tb_pre_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-desc-inner .digi-fancy-card-pre-title' => 'margin-bottom: {{SIZE}}px;',
		),
	), array(
		'name' => 'tb_pre_title_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-desc-inner .digi-fancy-card-pre-title' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'pre_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-pre-title',
	), array(
		'name' => 'pre_title_x_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-pre-title-x-offset: {{SIZE}}px;',
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
			'pre_title_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'pre_title_y_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-pre-title-y-offset: {{SIZE}}px;',
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
			'pre_title_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'pre_title_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-pre-title-rotate: {{SIZE}}deg;',
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
			'pre_title_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'pre_title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-pre-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pre_title_bg__hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-pre-title' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'pre_title_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-pre-title' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'pre_title_border_border!' => '',
		),
	), array(
		'name' => 'tb_pre_title_shadow_hover',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-desc-inner .digi-fancy-card-pre-title',
	), array(
		'name' => 'tb_pre_title_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-desc-inner .digi-fancy-card-pre-title' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'pre_title_hover_x_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-pre-title-hover-x-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'pre_title_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'pre_title_hover_y_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-pre-title-hover-y-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'pre_title_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'pre_title_hover_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-pre-title-hover-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'pre_title_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'pre_title_transition_delay',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-desc-inner .digi-fancy-card-pre-title' => 'transition-delay: {{SIZE}}s;',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title',
	), array(
		'name' => 'title_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'title_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-title-x-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'title_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'title_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-title-y-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'title_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'title_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-title-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'title_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title',
	), array(
		'name' => 'title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title',
	), array(
		'name' => 'title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title',
	), array(
		'name' => 'title_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'title_advanced_style' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title',
	), array(
		'name' => 'title_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'title_advanced_style' => 'yes',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__title',
	), array(
		'name' => 'title_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-pre-title' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'title_border_border!' => '',
		),
	), array(
		'name' => 'title_hover_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__title' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'title_hover_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-title-hover-x-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'title_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'title_hover_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-title-hover-y-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'title_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'title_hover_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-title-hover-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'title_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'title_transition_delay',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-desc-inner .digi-fancy-card__title' => 'transition-delay: {{SIZE}}s;',
		),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'text_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'text_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text' => 'margin-top: {{SIZE}}px;',
		),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text',
	), array(
		'name' => 'text_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'text_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-text-x-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'text_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'text_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-text-y-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'text_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'text_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-text-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'text_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'text_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_bg_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-text' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'text_hover_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-text' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'text_hover_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-text-hover-x-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'text_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'text_hover_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-text-hover-y-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'text_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'text_hover_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-text-hover-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'text_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'text_transition_delay',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-text' => 'transition-delay: {{SIZE}}s;',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card a.digi-fancy-card-button' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card a.digi-fancy-card-button svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} a.digi-fancy-card-button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} a.digi-fancy-card-button',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} a.digi-fancy-card-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} a.digi-fancy-card-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_spacing',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} a.digi-fancy-card-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} a.digi-fancy-card-button',
	), array(
		'name' => 'typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} a.digi-fancy-card-button',
	), array(
		'name' => 'button_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-button' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'button_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-button-x-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-button-y-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-button-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_hover_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-button' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'button_hover_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-button-hover-x-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'button_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_hover_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-button-hover-y-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'button_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_hover_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-trailer-box-button-hover-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'button_hover_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_transition_delay',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-button' => 'transition-delay: {{SIZE}}s;',
		),
	), array(
		'name' => 'background_overlay',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-background-overlay',
	), array(
		'name' => 'background_overlay_hover',
		'group' => 'background',
		'selector' => '{{WRAPPER}}:hover .elementor-background-overlay',
	) );
