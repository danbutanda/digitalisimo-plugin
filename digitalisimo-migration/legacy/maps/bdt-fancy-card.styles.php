<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fancy-card` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content' => 'text-align: {{VALUE}};',
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
		'name' => 'icon_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'justify-content: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'flux',
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
			'flex-end' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'top_icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'margin-top: -{{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'position' => 'top',
		),
	), array(
		'name' => 'top_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'transform: translateX({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'position' => 'top',
		),
	), array(
		'name' => 'left_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'margin-left: -{{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'position' => 'left',
		),
	), array(
		'name' => 'right_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'margin-right: -{{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'position' => 'right',
		),
	), array(
		'name' => 'left_right_icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'transform: translateY({{SIZE}}{{UNIT}});',
		),
		'condition' => array(
			'position' => array( 'left', 'right' ),
		),
	), array(
		'name' => 'readmore_icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__link' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'advanced_readmore_icon[value]!' => '',
			'readmore_text!' => '',
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
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'_skin' => '',
			'glassmorphism_effect' => 'yes',
			'fancy_card_icon_position' => array( 'left', 'right' ),
		),
	), array(
		'name' => 'icon_primary_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'icon_primary_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner',
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner',
	), array(
		'name' => 'icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
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
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner' => 'border-radius: {{VALUE}}; overflow: hidden;',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner img' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'icon_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner',
	), array(
		'name' => 'icon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner',
	), array(
		'name' => 'image_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner' => 'width: 100%; box-sizing: border-box;',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'card_image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner img' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'image_fullwidth' => '',
			'icon_type' => 'image',
		),
	), array(
		'name' => 'rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner i, {{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner svg, {{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner img' => 'transform: rotate({{SIZE}}{{UNIT}});',
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
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual-inner' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-card img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual:before' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => array( 'batty' ),
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual:before',
	), array(
		'name' => 'icon_primary_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'icon_primary_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner, {{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner',
	), array(
		'name' => 'icon_hover_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner',
	), array(
		'name' => 'icon_hover_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner i, {{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner svg, {{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner img' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'icon_hover_background_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual-inner img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'label_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual:before' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'label_hover_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual:before',
	), array(
		'name' => 'title_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-content .digi-fancy-card__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card-content .digi-fancy-card__title',
	), array(
		'name' => 'title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-content .digi-fancy-card__title' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'title_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-content .digi-fancy-card__title',
	), array(
		'name' => 'description_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-content .digi-fancy-card-text' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-content .digi-fancy-card-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card-content .digi-fancy-card-text',
	), array(
		'name' => 'description_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-content .digi-fancy-card-text' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'description_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-content .digi-fancy-card-text',
	), array(
		'name' => 'readmore_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__link-wrap' => 'text-align: {{VALUE}};',
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
		'name' => 'readmore_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link',
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link',
	), array(
		'name' => 'readmore_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link',
	), array(
		'name' => 'readmore_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link:hover',
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'readmore_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content .digi-fancy-card__link:hover',
	), array(
		'name' => 'icon_active_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax input:checked~.digi-fancy-card-toggole' => 'box-shadow: 0 0 0 1920px {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole' => 'box-shadow: 0 0 0 0 {{VALUE}};',
		),
	), array(
		'name' => 'toggle_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'toggle_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole',
	), array(
		'name' => 'toggle_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole',
	), array(
		'name' => 'toggle_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'toggle_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax input[type="checkbox"], {{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'toggle_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole',
	), array(
		'name' => 'toggle_active_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax input:checked~.digi-fancy-card-toggole' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax input:checked~.digi-fancy-card-toggole svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'toggle_active_shadow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax .digi-fancy-card-toggole' => 'box-shadow: 0 0 0 0 {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax input:checked~.digi-fancy-card-toggole' => 'box-shadow: 0 0 0 1920px {{VALUE}};',
		),
	), array(
		'name' => 'toggle_active_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax input:checked~.digi-fancy-card-toggole',
	), array(
		'name' => 'toggle_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-climax input:checked~.digi-fancy-card-toggole' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'toggle_border_border!' => '',
		),
	), array(
		'name' => 'badge_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'badge_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'content_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card',
	), array(
		'name' => 'skin_content_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-stack .digi-fancy-card-content-overlay:before' => 'background: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card.digi-fancy-card-batty' => 'background: {{VALUE}} !important;',
		),
		'condition' => array(
			'_skin!' => array( '', 'climax', 'flux' ),
		),
	), array(
		'name' => 'content_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card',
	), array(
		'name' => 'content_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card, {{WRAPPER}} .digi-fancy-card-batty',
	), array(
		'name' => 'content_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover',
	), array(
		'name' => 'content_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'content_border_border!' => '',
		),
	), array(
		'name' => 'content_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover',
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	) );
