<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-logo-carousel` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__slide' => 'height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'carousel_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__card' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-logo-carousel__card',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__card img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-logo-carousel__card img',
	), array(
		'name' => 'image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__image' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; object-fit: contain;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'carousel_bg_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__slide:hover .digi-logo-carousel__card' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'carousel_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__slide:hover .digi-logo-carousel__card' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__card:hover img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-logo-carousel__card:hover img',
	), array(
		'name' => 'image_bg_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-carousel__card:hover img' => 'transition-duration: {{SIZE}}s;',
		),
	), array(
		'name' => 'arrows_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button i, {{WRAPPER}} .digi-carousel__button i' => 'font-size: {{SIZE || 24}}{{UNIT}};',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'both' ),
		),
	), array(
		'name' => 'arrows_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button, {{WRAPPER}} .digi-carousel__button' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'both' ),
		),
	), array(
		'name' => 'arrows_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button:hover, {{WRAPPER}} .digi-carousel__button:hover' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'both' ),
		),
	), array(
		'name' => 'arrows_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button i, {{WRAPPER}} .digi-carousel__button i' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'both' ),
		),
	), array(
		'name' => 'arrows_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button:hover i, {{WRAPPER}} .digi-carousel__button:hover i' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'both' ),
		),
	), array(
		'name' => 'arrows_space',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button' => 'margin-left: {{SIZE}}px;',
		),
	), array(
		'name' => 'arrows_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-carousel__button, {{WRAPPER}} .digi-carousel__button',
	), array(
		'name' => 'arrows_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button:hover, {{WRAPPER}} .digi-carousel__button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'arrows_border_border!' => '',
		),
	), array(
		'name' => 'border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button, {{WRAPPER}} .digi-carousel__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'navigation' => array( 'arrows', 'both' ),
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'arrows_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-carousel__button, {{WRAPPER}} .digi-carousel__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'arrows_ncx_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-arrows-ncx-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
	), array(
		'name' => 'arrows_ncy_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-arrows-ncx-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 40,
		),
	), array(
		'name' => 'dots_nnx_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-dots-nnx-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
	), array(
		'name' => 'dots_nny_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-dots-nnx-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 30,
		),
	), array(
		'name' => 'both_ncx_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-both-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
	), array(
		'name' => 'both_ncy_position',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-both-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 40,
		),
	) );
