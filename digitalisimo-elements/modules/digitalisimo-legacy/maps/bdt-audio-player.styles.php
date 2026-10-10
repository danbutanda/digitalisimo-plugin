<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-audio-player` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'player_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-poster' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 400,
		),
		'condition' => array(
			'_skin' => 'bdt-poster',
		),
	), array(
		'name' => 'player_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio' => 'max-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-showcase',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'skin_default_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-audio.skin-default',
	), array(
		'name' => 'skin_default_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-audio.skin-default',
	), array(
		'name' => 'skin_default_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-default' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'skin_default_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-default' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'skin_default_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-default' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'skin_default_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-audio.skin-default',
	), array(
		'name' => 'skin_poster_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-audio.skin-poster',
	), array(
		'name' => 'skin_poster__border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-audio.skin-poster',
	), array(
		'name' => 'skin_poster__radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-poster' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'skin_poster_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-poster' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'skin_poster_css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-audio-poster',
	), array(
		'name' => 'thumb_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-thumb' => 'width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 150,
		),
	), array(
		'name' => 'thumbnail_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-thumb img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'thumbnail_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-thumb img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-thumb img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-audio-thumb img',
	), array(
		'name' => 'opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-thumb img:hover' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-audio-thumb img:hover',
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-thumb img' => 'transition-duration: {{SIZE}}s',
		),
	), array(
		'name' => 'thumbnail_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-audio-thumb img',
	), array(
		'name' => 'thumbnail_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-thumb img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'thumbnail_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-audio-thumb img',
	), array(
		'name' => 'play_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play svg *, {{WRAPPER}} .jp-audio .jp-pause svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play, {{WRAPPER}} .jp-audio .jp-pause' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_border',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play, {{WRAPPER}} .jp-audio .jp-pause' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'options' => array(
			'' => 'None',
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'play_button_border_width',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play, {{WRAPPER}} .jp-audio .jp-pause' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => '1',
			'bottom' => '1',
			'left' => '1',
			'right' => '1',
			'unit' => 'px',
		),
		'condition' => array(
			'play_button_border!' => '',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'play_button_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play, {{WRAPPER}} .jp-audio .jp-pause' => 'border-color: {{VALUE}};',
		),
		'default' => '#d5d5d5',
		'condition' => array(
			'play_button_border!' => '',
		),
	), array(
		'name' => 'play_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play, {{WRAPPER}} .jp-audio .jp-pause' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'play_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-audio .jp-play, {{WRAPPER}} .jp-audio .jp-pause',
	), array(
		'name' => 'play_button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play, {{WRAPPER}} .jp-audio .jp-pause' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};line-height: calc({{SIZE}}{{UNIT}} - 4px);',
		),
	), array(
		'name' => 'play_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play:hover svg *, {{WRAPPER}} .jp-audio .jp-pause:hover svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play:hover, {{WRAPPER}} .jp-audio .jp-pause:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'play_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-play:hover, {{WRAPPER}} .jp-audio .jp-pause:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'play_button_border_border!' => '',
		),
	), array(
		'name' => 'play_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-audio .jp-play:hover, {{WRAPPER}} .jp-audio .jp-pause:hover',
	), array(
		'name' => 'time_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-current-time, {{WRAPPER}} .jp-audio .jp-duration' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'time_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .jp-audio .jp-current-time, {{WRAPPER}} .jp-audio .jp-duration',
	), array(
		'name' => 'seek_bar_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-seek-bar' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'seek_bar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-seek-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'seek_bar_adjust_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-seek-bar .jp-play-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'seek_bar_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-seek-bar .jp-play-bar, {{WRAPPER}} .jp-audio .jp-seek-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'volume_button_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute svg *, {{WRAPPER}} .jp-audio .jp-unmute svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute, {{WRAPPER}} .jp-audio .jp-unmute' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_border',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute, {{WRAPPER}} .jp-audio .jp-unmute' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'options' => array(
			'' => 'None',
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'volume_button_border_width',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute, {{WRAPPER}} .jp-audio .jp-unmute' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => '1',
			'bottom' => '1',
			'left' => '1',
			'right' => '1',
			'unit' => 'px',
		),
		'condition' => array(
			'volume_button_border!' => '',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'volume_button_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute, {{WRAPPER}} .jp-audio .jp-unmute' => 'border-color: {{VALUE}};',
		),
		'default' => '#d5d5d5',
		'condition' => array(
			'volume_button_border!' => '',
		),
	), array(
		'name' => 'volume_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute, {{WRAPPER}} .jp-audio .jp-unmute' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'volume_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-audio .jp-mute, {{WRAPPER}} .jp-audio .jp-unmute',
	), array(
		'name' => 'volume_button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute, {{WRAPPER}} .jp-audio .jp-unmute' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};line-height: calc({{SIZE}}{{UNIT}} - 4px);',
		),
	), array(
		'name' => 'volume_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute:hover svg *, {{WRAPPER}} .jp-audio .jp-unmute:hover svg *' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute:hover, {{WRAPPER}} .jp-audio .jp-unmute:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'volume_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-mute:hover, {{WRAPPER}} .jp-audio .jp-unmute:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'volume_button_border_border!' => '',
		),
	), array(
		'name' => 'volume_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .jp-audio .jp-mute:hover, {{WRAPPER}} .jp-audio .jp-unmute:hover',
	), array(
		'name' => 'volume_bar_height',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-volume-bar' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'volume_bar_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-volume-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'volume_bar_adjust_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .jp-audio .jp-volume-bar .jp-volume-bar-value' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'skin_audio_title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'skin_audio_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-title' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'skin_audio_title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-audio-title',
	), array(
		'name' => 'skin_audio_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-audio-title',
	), array(
		'name' => 'skin_author_name_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-artist' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'skin_author_name_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio-artist span' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'skin_author_name_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-audio-artist span',
	), array(
		'name' => 'skin_author_name_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-audio-artist span',
	), array(
		'name' => 'showcase_min_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-showcase' => 'min-height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 650,
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'vh' ),
	), array(
		'name' => 'showcase_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-showcase' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'showcase_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-audio.skin-showcase',
	), array(
		'name' => 'showcase_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-showcase' => '--active-clr: {{VALUE}};',
		),
	), array(
		'name' => 'showcase_volume_track_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-showcase' => '--volume-track-clr: {{VALUE}};',
		),
	), array(
		'name' => 'showcase_volume_fill_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-showcase' => '--volume-fill-clr: {{VALUE}};',
		),
	), array(
		'name' => 'showcase_like_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-showcase' => '--like-icon-clr: {{VALUE}};',
		),
	), array(
		'name' => 'showcase_liked_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-audio.skin-showcase' => '--like-liked-clr: {{VALUE}};',
		),
	) );
