<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-interactive-tabs` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'space_between',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-space-between: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs-wrap' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'default' => '2',
		'tablet_default' => '1',
		'mobile_default' => '1',
		'options' => array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		),
	), array(
		'name' => 'column_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs-wrap' => 'grid-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 15,
		),
	), array(
		'name' => 'tabs_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-tabs-width: {{SIZE}}%;',
		),
		'default' => array(
			'size' => 50,
		),
		'tablet_default' => array(
			'size' => 50,
		),
		'mobile_default' => array(
			'size' => 100,
		),
	), array(
		'name' => 'tabs_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs-wrap' => 'align-self: {{VALUE}};',
		),
		'default' => 'center',
		'options' => array(
			'start' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-v-align-middle',
			),
			'end' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
			'auto' => array(
				'title' => 'Stretch',
				'icon' => 'eicon-v-align-stretch',
			),
		),
	), array(
		'name' => 'tabs_text_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab' => 'text-align: {{VALUE}};',
		),
		'default' => 'left',
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
			'justify' => array(
				'title' => 'Justify',
				'icon' => 'eicon-h-align-stretch',
			),
		),
	), array(
		'name' => 'thumbs_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-interactive-tabs-thumbs-h-offset: {{SIZE}}px;',
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
			'thumbs_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'thumbs_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-interactive-tabs-thumbs-v-offset: {{SIZE}}px;',
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
			'thumbs_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'thumbs_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-interactive-tabs-thumbs-rotate: {{SIZE}}deg;',
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
			'thumbs_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
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
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab',
	), array(
		'name' => 'tabs_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'tabs_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab',
	), array(
		'name' => 'tabs_item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
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
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'tabs_item_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tabs_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab',
	), array(
		'name' => 'tabs_item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover',
	), array(
		'name' => 'tabs_item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover' => 'border-color: {{VALUE}};',
		),
		'default' => '#4AB8F8',
		'condition' => array(
			'tabs_item_border_border!' => '',
		),
	), array(
		'name' => 'tabs_item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover',
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .swiper-slide .digi-fancy-tabs-main-img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .swiper-slide .digi-fancy-tabs-main-img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .swiper-slide .digi-fancy-tabs-main-img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
	), array(
		'name' => 'tabs_content_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .swiper-slide, {{WRAPPER}} .digi-fancy-tabs .swiper-slide .digi-fancy-tabs-main-img img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'video_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs embed, {{WRAPPER}} .digi-fancy-tabs iframe, {{WRAPPER}} .digi-fancy-tabs object, {{WRAPPER}} .digi-fancy-tabs video' => 'height: {{SIZE}}px',
		),
	), array(
		'name' => 'tabs_content_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs-main-img' => 'width: {{SIZE}}{{UNIT}}; margin: auto;',
		),
		'default' => array(
			'unit' => '%',
		),
		'tablet_default' => array(
			'unit' => '%',
		),
		'mobile_default' => array(
			'unit' => '%',
		),
		'size_units' => array( '%', 'px', 'vw' ),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .swiper-slide:hover .digi-fancy-tabs-main-img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .swiper-slide:hover .digi-fancy-tabs-main-img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', 'vh', 'vw' ),
	), array(
		'name' => 'icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
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
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'condition' => array(
			'icon_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover .digi-fancy-tabs__icon',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__label strong',
	), array(
		'name' => 'sub_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__tab:hover .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__label strong' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .digi-fancy-tabs__label strong',
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab:hover .digi-fancy-tabs__panel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__panel',
	), array(
		'name' => 'text_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__panel' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'dots_space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-dots-space-between: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'active_dots_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-dots-active-height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => '',
		),
	), array(
		'name' => 'active_advanced_dots_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-dots-active-height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
	), array(
		'name' => 'active_advanced_dots_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-dots-align: {{VALUE}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-v-align-middle',
			),
			'flex-end' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'fraction_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-fraction' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => 'arrows-fraction',
		),
	), array(
		'name' => 'active_fraction_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-current' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => 'arrows-fraction',
		),
	), array(
		'name' => 'fraction_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .swiper-pagination-fraction',
	), array(
		'name' => 'progresbar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-progressbar' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => 'progressbar',
		),
	), array(
		'name' => 'progres_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-progressbar .swiper-pagination-progressbar-fill' => 'background: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => 'progressbar',
		),
	), array(
		'name' => 'scrollbar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-scrollbar' => 'background: {{VALUE}}',
		),
		'condition' => array(
			'show_scrollbar' => 'yes',
		),
	), array(
		'name' => 'scrollbar_drag_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-scrollbar .swiper-scrollbar-drag' => 'background: {{VALUE}}',
		),
		'condition' => array(
			'show_scrollbar' => 'yes',
		),
	), array(
		'name' => 'scrollbar_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-horizontal > .swiper-scrollbar' => 'height: {{SIZE}}px;',
		),
		'condition' => array(
			'show_scrollbar' => 'yes',
		),
	), array(
		'name' => 'arrows_ncx_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-arrows-ncx: {{SIZE}}px;',
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
		'name' => 'arrows_ncy_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-arrows-ncy: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 40,
		),
		'tablet_default' => array(
			'size' => 40,
		),
		'mobile_default' => array(
			'size' => 40,
		),
	), array(
		'name' => 'dots_nnx_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-dots-nnx: {{SIZE}}px;',
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
		'name' => 'dots_nny_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-dots-nny: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 30,
		),
		'tablet_default' => array(
			'size' => 30,
		),
		'mobile_default' => array(
			'size' => 30,
		),
	), array(
		'name' => 'both_ncx_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-both-ncx: {{SIZE}}px;',
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
		'name' => 'both_ncy_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-both-ncy: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 40,
		),
		'tablet_default' => array(
			'size' => 40,
		),
		'mobile_default' => array(
			'size' => 40,
		),
	), array(
		'name' => 'arrows_fraction_ncx_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-arrows-fraction-ncx: {{SIZE}}px;',
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
		'name' => 'arrows_fraction_ncy_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-swiper-carousel-arrows-fraction-ncy: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 40,
		),
		'tablet_default' => array(
			'size' => 40,
		),
		'mobile_default' => array(
			'size' => 40,
		),
	), array(
		'name' => 'arrows_fraction_cy_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-fraction' => 'transform: translateY({{SIZE}}px);',
		),
		'default' => array(
			'size' => 30,
		),
	), array(
		'name' => 'progress_y_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-progressbar' => 'transform: translateY({{SIZE}}px);',
		),
		'default' => array(
			'size' => 15,
		),
		'condition' => array(
			'navigation' => 'progressbar',
		),
	), array(
		'name' => 'scrollbar_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-horizontal > .swiper-scrollbar' => 'bottom: {{SIZE}}px;',
		),
		'condition' => array(
			'show_scrollbar' => 'yes',
		),
	) );
