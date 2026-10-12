<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-video-player` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'video_play_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-video-play-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'video_play_button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .jp-video .jp-video-play-icon',
	), array(
		'name' => 'video_play_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .jp-video .jp-video-play-icon',
	), array(
		'name' => 'video_play_button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-video-play-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .jp-video .jp-video-play-icon:after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'video_play_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-video-play-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'video_play_button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-video-play-icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'video_play_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-video-play-icon:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'video_play_button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .jp-video .jp-video-play-icon:hover',
	), array(
		'name' => 'video_play_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-video-play-icon:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'video_play_button_border_border!' => '',
		),
	), array(
		'name' => 'video_play_button_hover_pulse_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-video-play-icon:after' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'control_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-interface, {{WRAPPER}} .jp-video .jp-player-title' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'control_area_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .jp-video .jp-interface',
	), array(
		'name' => 'control_area_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-interface' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'control_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-interface' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'control_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-interface' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'control_area_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-video .jp-interface',
	), array(
		'name' => 'play_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play svg *, {{WRAPPER}} .jp-video .jp-pause svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_border',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'border-style: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'' => 'None',
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'play_button_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'play_button_border!' => '',
		),
	), array(
		'name' => 'play_button_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'play_button_border!' => '',
		),
	), array(
		'name' => 'play_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'play_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'play_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause',
	), array(
		'name' => 'play_button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};line-height: calc({{SIZE}}{{UNIT}} - 4px);',
		),
	), array(
		'name' => 'play_button_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play, {{WRAPPER}} .jp-video .jp-pause' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'play_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play:hover svg *, {{WRAPPER}} .jp-video .jp-pause:hover svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play:hover, {{WRAPPER}} .jp-video .jp-pause:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-play:hover, {{WRAPPER}} .jp-video .jp-pause:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'play_button_border_border!' => '',
		),
	), array(
		'name' => 'play_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-video .jp-play:hover, {{WRAPPER}} .jp-video .jp-pause:hover',
	), array(
		'name' => 'time_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-current-time, {{WRAPPER}} .jp-video .jp-duration' => 'color: {{VALUE}};',
		),
		'default' => 'rgba(51, 51, 51, 0.6)',
	), array(
		'name' => 'time_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .jp-video .jp-current-time, {{WRAPPER}} .jp-video .jp-duration',
	), array(
		'name' => 'seek_bar_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-seek-bar' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'seek_bar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-seek-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'seek_bar_adjust_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-seek-bar .jp-play-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'seek_bar_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-seek-bar .jp-play-bar, {{WRAPPER}} .jp-video .jp-seek-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'volume_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute svg *, {{WRAPPER}} .jp-video .jp-unmute svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_border',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'border-style: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'' => 'None',
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'volume_button_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'volume_button_border!' => '',
		),
	), array(
		'name' => 'volume_button_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'volume_button_border!' => '',
		),
	), array(
		'name' => 'volume_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'volume_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'volume_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute',
	), array(
		'name' => 'volume_button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};line-height: calc({{SIZE}}{{UNIT}} - 4px);',
		),
	), array(
		'name' => 'volume_button_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute, {{WRAPPER}} .jp-video .jp-unmute' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'volume_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute:hover svg *, {{WRAPPER}} .jp-video .jp-unmute:hover svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute:hover, {{WRAPPER}} .jp-video .jp-unmute:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-mute:hover, {{WRAPPER}} .jp-video .jp-unmute:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'volume_button_border_border!' => '',
		),
	), array(
		'name' => 'volume_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-video .jp-mute:hover, {{WRAPPER}} .jp-video .jp-unmute:hover',
	), array(
		'name' => 'volume_bar_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-volume-bar' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'volume_bar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-volume-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'volume_bar_adjust_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-volume-bar .jp-volume-bar-value' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fullscreen_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'fullscreen_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fullscreen_button_border',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'border-style: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'' => 'None',
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'fullscreen_button_border_width',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'fullscreen_button_border!' => '',
		),
	), array(
		'name' => 'fullscreen_button_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'fullscreen_button_border!' => '',
		),
	), array(
		'name' => 'fullscreen_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'fullscreen_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'fullscreen_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-video .jp-full-screen',
	), array(
		'name' => 'fullscreen_button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};line-height: calc({{SIZE}}{{UNIT}} - 4px);',
		),
	), array(
		'name' => 'fullscreen_button_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'fullscreen_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen:hover svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'fullscreen_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'fullscreen_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-video .jp-full-screen:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'fullscreen_button_border_border!' => '',
		),
	), array(
		'name' => 'fullscreen_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-video .jp-full-screen:hover',
	) );
