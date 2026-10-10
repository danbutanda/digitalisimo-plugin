<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-step-flow` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box' => 'text-align: {{VALUE}};',
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
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__more' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'advanced_readmore_icon[value]!' => '',
			'readmore_text!' => '',
		),
	), array(
		'name' => 'readmore_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-step-flow-readmore-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => -50,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'readmore_on_hover' => 'yes',
		),
	), array(
		'name' => 'readmore_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-step-flow-readmore-v-offset: {{SIZE}}px;',
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
			'readmore_on_hover' => 'yes',
		),
	), array(
		'name' => 'badge_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-step-flow-badge-h-offset: {{SIZE}}px;',
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
			'{{WRAPPER}}' => '--ep-step-flow-badge-v-offset: {{SIZE}}px;',
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
			'{{WRAPPER}}' => '--ep-step-flow-badge-rotate: {{SIZE}}deg;',
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
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual',
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual',
	), array(
		'name' => 'icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
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
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual' => 'border-radius: {{VALUE}}; overflow: hidden;',
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual img' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'icon_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual',
	), array(
		'name' => 'icon_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__visual' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 15,
		),
	), array(
		'name' => 'image_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual' => 'width: 100%;box-sizing: border-box;',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'vh', 'vw' ),
	), array(
		'name' => 'rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual i' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual img' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual svg' => 'transform: rotate({{SIZE}}{{UNIT}});',
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
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__visual' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box img' => 'transition-duration: {{SIZE}}s',
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
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'icon_type!' => 'image',
		),
	), array(
		'name' => 'icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual:after',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_hover_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual',
	), array(
		'name' => 'icon_hover_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual i' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual img' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'icon_hover_background_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__visual img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-step-flow-icon-h-offset: {{SIZE}}px;',
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
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-step-flow-icon-v-offset: {{SIZE}}px;',
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
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'title_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__content .digi-advanced-icon-box__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__content .digi-advanced-icon-box__title',
	), array(
		'name' => 'title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__content .digi-advanced-icon-box__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__content .digi-advanced-icon-box__title',
	), array(
		'name' => 'description_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__content .digi-advanced-icon-box__description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__content .digi-advanced-icon-box__description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__content .digi-advanced-icon-box__description',
	), array(
		'name' => 'description_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__content .digi-advanced-icon-box__description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box:hover .digi-advanced-icon-box__content .digi-advanced-icon-box__description',
	), array(
		'name' => 'divider_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'text-align: {{VALUE}}; margin: 0 auto; margin-{{VALUE}}: 0;',
		),
		'default' => 'center',
		'condition' => array(
			'title_separator_type!' => array( 'line', 'dashed', 'line-circle', 'line-cross', 'line-dashed', 'line-star', 'slash', 'rectangle', 'triangle', 'wave', 'kiss-curl', 'zemik', 'finest', 'furrow' ),
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
		'name' => 'divider_line_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'text-align: {{VALUE}}; margin: 0 auto; margin-{{VALUE}}: 0;',
		),
		'default' => 'center',
		'condition' => array(
			'title_separator_type' => array( 'line', 'dashed', 'line-circle', 'line-cross', 'line-dashed', 'line-star', 'slash', 'rectangle', 'triangle', 'wave', 'kiss-curl', 'zemik', 'finest', 'furrow' ),
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
		'name' => 'title_separator_border_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'border-top-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'title_separator_type' => 'line',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'title_separator_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'title_separator_type' => 'line',
		),
	), array(
		'name' => 'title_separator_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'border-top-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'title_separator_type' => 'line',
		),
	), array(
		'name' => 'title_separator_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'title_separator_type' => 'line',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_separator_svg_fill_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator svg *' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'title_separator_type!' => 'line',
		),
	), array(
		'name' => 'title_separator_svg_stroke_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator svg *' => 'stroke: {{VALUE}};',
		),
		'condition' => array(
			'title_separator_type!' => 'line',
		),
	), array(
		'name' => 'max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'max-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'title_separator_type!' => 'line',
		),
	), array(
		'name' => 'divider_svg_stroke_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator svg *' => 'stroke-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'title_separator_type!' => 'line',
		),
	), array(
		'name' => 'divider_crop',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator svg' => 'transform: scale({{SIZE}}) scale(0.01)',
		),
		'condition' => array(
			'title_separator_type!' => 'line',
		),
	), array(
		'name' => 'max_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator svg' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'title_separator_type!' => 'line',
		),
	), array(
		'name' => 'title_separator_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box .digi-advanced-icon-box__separator' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'readmore_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__more' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-advanced-icon-box__more svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__more',
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__more',
	), array(
		'name' => 'readmore_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__more',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__more',
	), array(
		'name' => 'readmore_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__more:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-advanced-icon-box__more:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__more:hover',
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'readmore_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__more:hover',
	), array(
		'name' => 'direction_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-step-flow-direction-rotate: {{SIZE}}deg;',
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
			'direction_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__badge span',
	), array(
		'name' => 'badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__badge span',
	), array(
		'name' => 'badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__badge span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box__badge span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'badge_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__badge span',
	), array(
		'name' => 'badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-advanced-icon-box__badge span',
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-icon-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	) );
