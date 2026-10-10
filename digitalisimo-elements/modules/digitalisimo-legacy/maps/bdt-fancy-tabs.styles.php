<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fancy-tabs` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'tabs_content_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs-height-fixed .digi-fancy-tabs__panel' => 'max-height: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-fancy-tabs-height-fixed' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'tabs_content_height_show' => 'yes',
		),
	), array(
		'name' => 'tabs_content_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'text-align: {{VALUE}};',
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
		'name' => 'tabs_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'glassmorphism_effect' => 'yes',
		),
	), array(
		'name' => 'tabs_item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab',
	), array(
		'name' => 'tabs_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'tabs_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab',
	), array(
		'name' => 'tabs_item_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'condition' => array(
			'tabs_item_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tabs_item_radius_advanced',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'tabs_item_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tabs_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab',
	), array(
		'name' => 'tabs_item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover',
	), array(
		'name' => 'tabs_item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tabs_item_border_border!' => '',
		),
	), array(
		'name' => 'tabs_item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover',
	), array(
		'name' => 'tabs_item_active_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab.active',
	), array(
		'name' => 'tabs_item_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tabs_item_border_border!' => '',
		),
	), array(
		'name' => 'tabs_item_active_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab.active',
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-tabs__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'vh', 'vw' ),
	), array(
		'name' => 'icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
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
			'{{WRAPPER}} .digi-fancy-tabs__icon' => 'border-radius: {{VALUE}}; overflow: hidden;',
			'{{WRAPPER}} .digi-fancy-tabs__icon img' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'icon_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__icon',
	), array(
		'name' => 'rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon i, {{WRAPPER}} .digi-fancy-tabs__icon svg, {{WRAPPER}} .digi-fancy-tabs__icon img' => 'transform: rotate({{SIZE}}{{UNIT}});',
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
			'{{WRAPPER}} .digi-fancy-tabs__icon' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
			'unit' => 'deg',
		),
	), array(
		'name' => 'image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon img' => 'width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'vh', 'vw' ),
	), array(
		'name' => 'image_fullwidth',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon' => 'width: 100%;box-sizing: border-box;',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'fancy_tabs_event' => 'click',
		),
	), array(
		'name' => 'icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_hover_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_hover_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon i, {{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon svg, {{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon img' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'icon_hover_background_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'unit' => 'deg',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'icon_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-tabs__tab.active .digi-fancy-tabs__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label',
	), array(
		'name' => 'sub_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs-sub-title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs-sub-title' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs-sub-title',
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs-text' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs-text',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'border_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'border_radius_advanced',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'condition' => array(
			'border_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button a:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	) );
