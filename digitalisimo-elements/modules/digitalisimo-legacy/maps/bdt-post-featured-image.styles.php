<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-featured-image` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}  img' => 'width: {{SIZE}}{{UNIT}};',
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
		'size_units' => array( '%', 'px', 'vw' ),
	), array(
		'name' => 'space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}  img' => 'max-width: {{SIZE}}{{UNIT}};',
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
		'size_units' => array( '%', 'px', 'vw' ),
	), array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}  img' => 'height: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'px',
		),
		'tablet_default' => array(
			'unit' => 'px',
		),
		'mobile_default' => array(
			'unit' => 'px',
		),
		'size_units' => array( 'px', 'vh' ),
	), array(
		'name' => 'object-fit',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}  img' => 'object-fit: {{VALUE}};',
		),
		'default' => '',
		'condition' => array(
			'height[size]!' => '',
		),
		'options' => array(
			'' => 'Default',
			'fill' => 'Fill',
			'cover' => 'Cover',
			'contain' => 'Contain',
		),
	), array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}  img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}}  img',
	), array(
		'name' => 'opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} :hover img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} :hover img',
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}  img' => 'transition-duration: {{SIZE}}s',
		),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}}  img',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}  img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}}  img',
		'exclude' => array( 'box_shadow_position' ),
	), array(
		'name' => 'caption_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'text-align: {{VALUE}};',
		),
		'default' => '',
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'caption_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'caption_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .widget-image-caption',
	), array(
		'name' => 'caption_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .widget-image-caption',
	), array(
		'name' => 'caption_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .widget-image-caption' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	) );
