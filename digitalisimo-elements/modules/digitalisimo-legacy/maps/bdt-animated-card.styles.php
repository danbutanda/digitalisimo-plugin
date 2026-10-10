<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-animated-card` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-item' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'vh' ),
	), array(
		'name' => 'content_max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-content' => 'max-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-content' => 'text-align: {{VALUE}} !important;',
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
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'content_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card-circle::before',
	), array(
		'name' => 'content_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card-circle::before',
	), array(
		'name' => 'content_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-circle::before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'content_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-circle::before',
	), array(
		'name' => 'content_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-circle::before' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'content_border_border!' => '',
		),
	), array(
		'name' => 'image_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-img' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'vh' ),
	), array(
		'name' => 'image_object_fit',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-img' => 'object-fit: {{VALUE}};',
		),
		'default' => 'contain',
		'options' => array(
			'' => 'Default',
			'fill' => 'Fill',
			'cover' => 'Cover',
			'contain' => 'Contain',
			'scale-down' => 'Scale Down',
		),
	), array(
		'name' => 'image_object_position',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-img' => 'object-position: {{VALUE}};',
		),
		'default' => 'center center',
		'options' => array(
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
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-card-img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
	), array(
		'name' => 'image_height_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-item:hover .digi-fancy-card-img' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'vh' ),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card:hover .digi-fancy-card-img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'title_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__title' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card__title',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-fancy-card__title',
	), array(
		'name' => 'title_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card__title',
	), array(
		'name' => 'title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__title:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_text_shadow_hover',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card__title:hover',
	), array(
		'name' => 'sub_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_title_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card__badge',
	), array(
		'name' => 'sub_title_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card__badge:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'desc_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-text' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card-text',
	), array(
		'name' => 'readmore_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-btn' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card-btn svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card-btn',
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card-btn',
	), array(
		'name' => 'readmore_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card-btn',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card-btn',
	), array(
		'name' => 'readmore_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-btn:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card-btn:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card-btn:hover',
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-btn:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'readmore_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-card-btn:hover',
	) );
