<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-featured-box` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content' => 'text-align: {{VALUE}} !important;',
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
		'name' => 'readmore_icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box__button' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'advanced_readmore_icon[value]!' => '',
			'readmore_text!' => '',
		),
	), array(
		'name' => 'badge_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-h-offset: {{SIZE}}px;',
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
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-v-offset: {{SIZE}}px;',
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
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-rotate: {{SIZE}}deg;',
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
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'image_spacing',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__media' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__media img',
	), array(
		'name' => 'img_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__media img',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__media img',
	), array(
		'name' => 'iamge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__media img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__media img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__media img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__media img',
	), array(
		'name' => 'icon_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__media img',
	), array(
		'name' => 'image_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__media img' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'image_border_border!' => '',
		),
	), array(
		'name' => 'icon_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__media img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__media img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'title_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__title' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__title',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__title',
	), array(
		'name' => 'title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__title',
	), array(
		'name' => 'title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content .digi-featured-box__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_text_shadow_hover',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content .digi-featured-box__title',
	), array(
		'name' => 'sub_title_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__subtitle' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'sub_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__subtitle' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__subtitle',
	), array(
		'name' => 'sub_title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content .digi-featured-box__subtitle' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content .digi-featured-box__subtitle',
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box__description',
	), array(
		'name' => 'description_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content .digi-featured-box__description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content .digi-featured-box__description',
	), array(
		'name' => 'button_top_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'readmore_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button',
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button',
	), array(
		'name' => 'readmore_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button',
	), array(
		'name' => 'readmore_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button:hover',
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'readmore_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content .digi-featured-box-button .digi-featured-box__button:hover',
	), array(
		'name' => 'badge_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box__badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-featured-box__badge',
	), array(
		'name' => 'badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-featured-box__badge',
	), array(
		'name' => 'badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'badge_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box__badge',
	), array(
		'name' => 'badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-featured-box__badge',
	), array(
		'name' => 'content_max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content' => 'max-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'glassmorphism_effect' => 'yes',
		),
	), array(
		'name' => 'content_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content',
	), array(
		'name' => 'content_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content',
	), array(
		'name' => 'content_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box .digi-featured-box__content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box .digi-featured-box__content',
	), array(
		'name' => 'content_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content',
	), array(
		'name' => 'content_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'content_border_border!' => '',
		),
	), array(
		'name' => 'content_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-featured-box:hover .digi-featured-box__content',
	) );
