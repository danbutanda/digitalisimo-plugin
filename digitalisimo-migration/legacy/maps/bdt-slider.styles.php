<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-slider` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 600,
		),
	), array(
		'name' => 'content_max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .swiper-slide-contents' => 'width: {{SIZE}}{{UNIT}}; max-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'slider_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide' => 'background-color: {{VALUE}};',
		),
		'default' => '#14ABF4',
	), array(
		'name' => 'overlay_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .swiper-slide-bg:before',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'wrapper_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper' => 'overflow: hidden; border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .swiper-slide-contents' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'content_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .swiper-slide-contents' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-heading' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-heading' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-heading' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-heading',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-heading',
	), array(
		'name' => 'title_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-description' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-description' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-description',
	), array(
		'name' => 'text_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button',
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button',
	), array(
		'name' => 'typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-slide .elementor-slide-button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'arrows_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev i, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next i' => 'color: {{VALUE}}',
		),
		'default' => '#fff',
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'nav_arrows_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next',
	), array(
		'name' => 'nav_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'arrows_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev i, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next i' => 'font-size: {{SIZE || 36}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_space',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev' => 'margin-right: {{SIZE}}px;',
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next' => 'margin-left: {{SIZE}}px;',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev:hover i, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next:hover i' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'arrows_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev:hover, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next:hover' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'nav_arrows_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-prev:hover, {{WRAPPER}} .elementor-slides-wrapper .elementor-swiper-button-next:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'nav_arrows_border_border!' => '',
			'navigation!' => array( 'dots', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'dots_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-bullet' => 'background-color: {{VALUE}}',
		),
		'default' => '#fff',
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
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
		'name' => 'dots_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => '',
		),
	), array(
		'name' => 'advanced_dots_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
	), array(
		'name' => 'advanced_dots_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
	), array(
		'name' => 'advanced_dots_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dots_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .swiper-pagination-bullet',
	), array(
		'name' => 'active_dot_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-bullet-active' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
		),
	), array(
		'name' => 'active_dots_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet-active' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}}' => '--ep-swiper-dots-active-height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => '',
		),
	), array(
		'name' => 'active_advanced_dots_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet-active' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
	), array(
		'name' => 'active_advanced_dots_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet-active' => 'height: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}}' => '--ep-swiper-dots-active-height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
	), array(
		'name' => 'active_advanced_dots_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-pagination-bullet-active' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'navigation!' => array( 'arrows', 'arrows-fraction', 'progressbar', 'none' ),
			'advanced_dots_size' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
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
		'name' => 'dots_active_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .swiper-pagination-bullet-active',
	), array(
		'name' => 'fraction_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-fraction' => 'color: {{VALUE}}',
		),
		'default' => '#fff',
		'condition' => array(
			'navigation' => 'arrows-fraction',
		),
	), array(
		'name' => 'active_fraction_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-current' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => 'arrows-fraction',
		),
	), array(
		'name' => 'fraction_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-fraction',
	), array(
		'name' => 'progresbar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-progressbar' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => 'progressbar',
		),
	), array(
		'name' => 'progres_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-progressbar .swiper-pagination-progressbar-fill' => 'background: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => 'progressbar',
		),
	), array(
		'name' => 'scrollbar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-scrollbar' => 'background: {{VALUE}}',
		),
		'condition' => array(
			'show_scrollbar' => 'yes',
		),
	), array(
		'name' => 'scrollbar_drag_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-scrollbar .swiper-scrollbar-drag' => 'background: {{VALUE}}',
		),
		'condition' => array(
			'show_scrollbar' => 'yes',
		),
	), array(
		'name' => 'scrollbar_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-carousel-horizontal > .swiper-scrollbar' => 'height: {{SIZE}}px;',
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
			'{{WRAPPER}}' => '--ep-swiper-carousel-dots-nny: -{{SIZE}}px;',
		),
		'default' => array(
			'size' => -30,
		),
		'tablet_default' => array(
			'size' => -30,
		),
		'mobile_default' => array(
			'size' => -30,
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
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-fraction' => 'transform: translateY({{SIZE}}px);',
		),
		'default' => array(
			'size' => -55,
		),
	), array(
		'name' => 'progress_y_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-pagination-progressbar' => 'transform: translateY({{SIZE}}px);',
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
			'{{WRAPPER}} .elementor-slides-wrapper .swiper-carousel-horizontal > .swiper-scrollbar' => 'bottom: {{SIZE}}px;',
		),
		'condition' => array(
			'show_scrollbar' => 'yes',
		),
	) );
