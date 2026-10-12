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
			'{{WRAPPER}} .digi-video-player__now-title' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'video_title_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__now-title' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'video_title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-video-player__now-title',
	), array(
		'name' => 'video_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-video-player__now-title',
	), array(
		'name' => 'video_desc_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__now-description' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'video_desc_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__now-description' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'video_desc_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__now-description' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'video_desc_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-video-player__now-description',
	), array(
		'name' => 'poster_bg_position',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__stage' => 'background-position: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'' => 'Default',
			'center center' => 'Center Center',
			'center left' => 'Center Left',
			'center right' => 'Center Right',
			'top center' => 'Top Center',
			'top left' => 'Top Left',
			'top right' => 'Top Right',
			'bottom center' => 'Bottom Center',
			'bottom left' => 'Bottom Left',
			'bottom right' => 'Bottom Right',
		),
	), array(
		'name' => 'poster_bg_size',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__stage' => 'background-size: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'' => 'Default',
			'auto' => 'Auto',
			'cover' => 'Cover',
			'contain' => 'Contain',
		),
	), array(
		'name' => 'thumb_item_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'thumb_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumb_item_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumb_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-video-player__item',
	), array(
		'name' => 'thumb_item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumb_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-video-player__item span',
	), array(
		'name' => 'thumb_item_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'thumb_item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item:hover' => 'border-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'thumb_item_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumb_item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-video-player__item:hover',
	), array(
		'name' => 'thumb_item_active_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item[aria-pressed=true]' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'thumb_item_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item[aria-pressed=true]' => 'border-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'thumb_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item span' => 'width: {{SIZE}}{{UNIT}};margin-left: auto;margin-right: auto;',
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
			'{{WRAPPER}} .digi-video-player__item span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumbnail_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumbnail_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-video-player__item span',
	), array(
		'name' => 'thumbnail_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumbnail_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-video-player__item span',
	), array(
		'name' => 'opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item span' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-video-player__item span',
	), array(
		'name' => 'opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item span:hover' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-video-player__item span:hover',
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item span' => 'transition-duration: {{SIZE}}s',
		),
	), array(
		'name' => 'thumbnail_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item-title' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item-title:hover' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-video-player__item-title',
	), array(
		'name' => 'thumbnail_desc_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item-description' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_desc_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item-description:hover' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'thumbnail_desc_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-video-player__item-description',
	), array(
		'name' => 'thumbnail_desc_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-video-player__item-description' => 'margin-top: {{SIZE}}{{UNIT}};',
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
