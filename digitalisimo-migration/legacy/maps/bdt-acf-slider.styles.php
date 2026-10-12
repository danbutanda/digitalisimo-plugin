<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-acf-slider` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
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
	) );
