<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-video-gallery` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'video_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-item-text h2' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'video_title_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-item-text h2' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'video_title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .rvs-item-text h2',
	), array(
		'name' => 'video_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .rvs-item-text h2',
	), array(
		'name' => 'thumb_item_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'thumb_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-container a.rvs-nav-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumb_item_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumb_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .rvs-nav-item',
	), array(
		'name' => 'thumb_item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumb_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .rvs-nav-item span',
	), array(
		'name' => 'thumb_item_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'thumb_item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item:hover' => 'border-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'thumb_item_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumb_item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .rvs-nav-item:hover',
	), array(
		'name' => 'thumb_item_active_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item.rvs-active' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'thumb_item_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item.rvs-active' => 'border-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'thumb_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item span' => 'width: {{SIZE}}{{UNIT}};margin-left: auto;margin-right: auto;',
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
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumbnail_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumbnail_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumbnail_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .rvs-nav-item span',
	), array(
		'name' => 'thumbnail_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumbnail_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .rvs-nav-item span',
	), array(
		'name' => 'opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item span' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .rvs-nav-item span',
	), array(
		'name' => 'opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item span:hover' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .rvs-nav-item span:hover',
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item span' => 'transition-duration: {{SIZE}}s',
		),
	), array(
		'name' => 'thumbnail_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item-title' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item-title:hover' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .rvs-nav-container .rvs-nav-item-title',
	), array(
		'name' => 'thumbnail_desc_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item-credits' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_desc_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item-credits:hover' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_desc_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .rvs-nav-container .rvs-nav-item-credits',
	), array(
		'name' => 'thumbnail_desc_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-nav-item-credits' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'play_btn_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-play-video' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'play_btn_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-play-video' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'play_btn_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-container a.rvs-play-video' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'play_btn_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .rvs-play-video',
	), array(
		'name' => 'play_btn_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .rvs-play-video' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'play_btn_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .rvs-play-video',
	), array(
		'name' => 'play_btn_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-play-video:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'play_btn_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-play-video:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'play_btn_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .rvs-play-video:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'play_btn_border_border!' => '',
		),
	), array(
		'name' => 'play_btn_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .rvs-play-video:hover',
	) );
