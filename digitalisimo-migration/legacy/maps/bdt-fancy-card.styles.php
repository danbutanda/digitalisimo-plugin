<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fancy-card` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'icon_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'justify-content: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'flux',
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
			'flex-end' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'top_icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'margin-top: -{{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'position' => 'top',
		),
	), array(
		'name' => 'top_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'transform: translateX({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'position' => 'top',
		),
	), array(
		'name' => 'left_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'margin-left: -{{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'position' => 'left',
		),
	), array(
		'name' => 'right_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'margin-right: -{{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 0,
		),
		'condition' => array(
			'position' => 'right',
		),
	), array(
		'name' => 'left_right_icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__visual' => 'transform: translateY({{SIZE}}{{UNIT}});',
		),
		'condition' => array(
			'position' => array( 'left', 'right' ),
		),
	), array(
		'name' => 'readmore_icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__link' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'advanced_readmore_icon[value]!' => '',
			'readmore_text!' => '',
		),
	), array(
		'name' => 'indicator_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-indicator-h-offset: {{SIZE}}px;',
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
			'indicator_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'indicator_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-indicator-v-offset: {{SIZE}}px;',
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
			'indicator_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'indicator_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-indicator-rotate: {{SIZE}}deg;',
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
			'indicator_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-h-offset: {{SIZE}}px;',
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
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-v-offset: {{SIZE}}px;',
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
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-rotate: {{SIZE}}deg;',
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
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'_skin' => '',
			'glassmorphism_effect' => 'yes',
			'fancy_card_icon_position' => array( 'left', 'right' ),
		),
	), array(
		'name' => 'icon_primary_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'icon_primary_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-card img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual:before' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => array( 'batty' ),
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card__visual:before',
	), array(
		'name' => 'icon_primary_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'icon_primary_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'label_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual:before' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'_skin!' => 'batty',
		),
	), array(
		'name' => 'label_hover_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card__visual:before',
	), array(
		'name' => 'title_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'badge_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'badge_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'content_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card',
	), array(
		'name' => 'content_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card',
	), array(
		'name' => 'content_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card',
	), array(
		'name' => 'content_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover',
	), array(
		'name' => 'content_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'content_border_border!' => '',
		),
	), array(
		'name' => 'content_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover',
	) );
