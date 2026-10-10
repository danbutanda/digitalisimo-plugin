<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-carousel` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'image_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'width: {{SIZE}}{{UNIT}};margin-left: auto;margin-right: auto;',
		),
		'default' => array(
			'size' => 100,
			'unit' => '%',
		),
		'tablet_default' => array(
			'unit' => '%',
		),
		'mobile_default' => array(
			'unit' => '%',
		),
		'condition' => array(
			'thumbnail_show' => 'yes',
			'_skin' => '',
		),
		'size_units' => array( '%' ),
	), array(
		'name' => 'image_ratio',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'padding-bottom: calc( {{SIZE}} * 100% ); top: 0; left: 0; right: 0; bottom: 0;',
			'{{WRAPPER}} .elementor-post__thumbnail:after' => 'content: "{{SIZE}}"; position: absolute; color: transparent;',
			'{{WRAPPER}} .elementor-post__thumbnail img' => 'height: 100%; width: auto; position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: {{SIZE}};',
		),
		'condition' => array(
			'thumbnail_show' => 'yes',
			'_skin!' => 'bdt-ramble',
		),
	), array(
		'name' => 'ramble_image_ratio',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'padding-bottom: calc( {{SIZE}} * 100% ); top: 0; left: 0; right: 0; bottom: 0;',
			'{{WRAPPER}} .elementor-post__thumbnail:after' => 'content: "{{SIZE}}"; position: absolute; color: transparent;',
			'{{WRAPPER}} .elementor-post__thumbnail img' => 'height: 100%; width: auto; position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'condition' => array(
			'thumbnail_show' => 'yes',
			'_skin' => 'bdt-ramble',
		),
	), array(
		'name' => 'hidden_item_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .swiper-slide:not(.swiper-slide-visible)' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'show_hidden_item' => 'yes',
		),
	), array(
		'name' => 'content_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__text' => 'text-align: {{VALUE}}',
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
		'name' => 'item_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post' => 'background-color: {{VALUE}};',
		),
		'default' => '#fff',
		'condition' => array(
			'_skin!' => 'bdt-ramble',
		),
	), array(
		'name' => 'item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-post',
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-post',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post, {{WRAPPER}} .swiper-carousel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'item_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'content_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-carousel-spacing: {{SIZE}}px;',
		),
		'condition' => array(
			'_skin' => 'bdt-ramble',
		),
	), array(
		'name' => 'item_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post:hover' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-ramble',
		),
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-post:hover',
	), array(
		'name' => 'item_shadow_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-carousel' => 'padding: {{SIZE}}{{UNIT}}; margin: 0 -{{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'item_active_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post.swiper-slide-active' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-ramble',
		),
	), array(
		'name' => 'item_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post.swiper-slide-active' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_active_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-post.swiper-slide-active',
	), array(
		'name' => 'item_active_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post.swiper-slide-active' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'condition' => array(
			'_skin!' => 'bdt-ramble',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'image_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			'{{WRAPPER}} .elementor-post__thumbnail img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail img' => 'opacity: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'image_hover_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post:hover .elementor-post__thumbnail img' => 'opacity: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'image_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'_skin' => '',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__title a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__title a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-post__title a',
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__title' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post__title',
	), array(
		'name' => 'date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post-date' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'date_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post-date' => 'margin-top: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post-date',
	), array(
		'name' => 'meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__meta-data span *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'meta_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__meta-data span:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-post__meta-data span:hover a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'meta_divider_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__meta-data span:after' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'meta_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__meta-data' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post__meta-data span',
	), array(
		'name' => 'excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'excerpt_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__excerpt' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post__excerpt',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-post__read-more svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-post__read-more',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-post__read-more',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-post__read-more',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post__read-more',
	), array(
		'name' => 'carousel_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-post__read-more-icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'carousel_icon[value]!' => '',
		),
	), array(
		'name' => 'carousel_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more-icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'carousel_icon[value]!' => '',
		),
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-post__read-more:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-post__read-more:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-post__read-more:hover',
	), array(
		'name' => 'carousel_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more:hover .elementor-post__read-more-icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-post__read-more:hover .elementor-post__read-more-icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'carousel_icon[value]!' => '',
		),
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
