<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fancy-slider` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__title, {{WRAPPER}} .digi-fancy-slider__title a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__title' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__title',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__title',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__button a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__button a',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__button a',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__button a',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__button a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'border_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'border_radius_advanced',
		'type' => 'text',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__button a' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'condition' => array(
			'border_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__button a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__button a',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__button a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__button a:hover',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-slider__button a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	), array(
		'name' => 'button_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-slider__button a:hover',
	), array(
		'name' => 'arrows_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev] i, {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next] i' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev], {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'nav_arrows_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev], {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev], {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'arrows_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev], {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'arrows_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev] i, {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next] i' => 'font-size: {{SIZE || 24}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev]' => 'margin-right: {{SIZE}}px;',
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]' => 'margin-left: {{SIZE}}px;',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev], {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]',
	), array(
		'name' => 'arrows_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev]:hover i, {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]:hover i' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev]:hover, {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]:hover' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'nav_arrows_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev]:hover, {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'nav_arrows_border_border!' => '',
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-carousel__button[data-digi-carousel-prev]:hover, {{WRAPPER}} .digi-carousel__button[data-digi-carousel-next]:hover',
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
