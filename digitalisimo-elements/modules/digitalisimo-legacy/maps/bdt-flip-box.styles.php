<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-flip-box` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'front_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-flip-box__front',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'front_background_overlay',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__overlay' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'front_background_image[id]!' => '',
		),
	), array(
		'name' => 'back_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-flip-box__back',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'back_background_overlay',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__overlay' => 'background-color: {{VALUE}};',
		),
		'default' => '',
		'condition' => array(
			'back_background_image[id]!' => '',
		),
	), array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box' => 'height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'vh' ),
	), array(
		'name' => 'border_radius',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__layer, {{WRAPPER}} .elementor-flip-box__layer__overlay' => 'border-radius: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'flip_transiton_duration',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__layer' => 'transition-duration: {{SIZE}}s;',
		),
	), array(
		'name' => 'front_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-flip-box__front',
	), array(
		'name' => 'front_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__overlay' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'front_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__overlay' => 'text-align: {{VALUE}}',
		),
		'default' => 'center',
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
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'front_vertical_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__overlay' => 'justify-content: {{VALUE}}',
		),
		'selectors_dictionary' => array(
			'top' => 'flex-start',
			'middle' => 'center',
			'bottom' => 'flex-end',
		),
		'options' => array(
			'top' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'middle' => array(
				'title' => 'Middle',
				'icon' => 'eicon-v-align-middle',
			),
			'bottom' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon-wrapper' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'graphic_element' => 'icon',
		),
	), array(
		'name' => 'icon_primary_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-view-stacked .elementor-icon' => 'background-color: {{VALUE}}',
			'{{WRAPPER}} .elementor-view-framed .elementor-icon, {{WRAPPER}} .elementor-view-default .elementor-icon' => 'color: {{VALUE}}; border-color: {{VALUE}}',
			'{{WRAPPER}} .elementor-flip-box .elementor-icon svg' => 'fill: {{VALUE}};',
		),
		'default' => '',
		'condition' => array(
			'graphic_element' => 'icon',
		),
	), array(
		'name' => 'icon_secondary_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-view-framed .elementor-icon' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .elementor-view-stacked .elementor-icon' => 'color: {{VALUE}};',
		),
		'default' => '',
		'condition' => array(
			'graphic_element' => 'icon',
			'icon_view!' => 'default',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'graphic_element' => 'icon',
		),
	), array(
		'name' => 'icon_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon' => 'padding: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'graphic_element' => 'icon',
			'icon_view!' => 'default',
		),
	), array(
		'name' => 'icon_rotate',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon i' => 'transform: rotate({{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .elementor-icon svg' => 'transform: rotate({{SIZE}}{{UNIT}});',
		),
		'default' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'condition' => array(
			'graphic_element' => 'icon',
		),
	), array(
		'name' => 'icon_border_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon' => 'border-width: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'graphic_element' => 'icon',
			'icon_view' => 'framed',
		),
	), array(
		'name' => 'icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'graphic_element' => 'icon',
			'icon_view!' => 'default',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__image' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'graphic_element' => 'image',
		),
	), array(
		'name' => 'image_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__image img' => 'width: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'unit' => '%',
			'size' => 10,
		),
		'condition' => array(
			'graphic_element' => 'image',
		),
		'size_units' => array( '%' ),
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__image' => 'opacity: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
		),
		'condition' => array(
			'graphic_element' => 'image',
		),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-flip-box__image img',
	), array(
		'name' => 'image_border_radius',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__image img' => 'border-radius: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'graphic_element' => 'image',
		),
	), array(
		'name' => 'front_image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__image img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'front_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'front_description_text!' => '',
		),
	), array(
		'name' => 'front_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__title' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'front_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__title',
	), array(
		'name' => 'front_description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__description' => 'color: {{VALUE}}',
		),
		'default' => '#f5f5f5',
	), array(
		'name' => 'front_description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-flip-box__front .elementor-flip-box__layer__description',
	), array(
		'name' => 'back_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-flip-box__back',
	), array(
		'name' => 'back_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__overlay' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'back_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__overlay' => 'text-align: {{VALUE}}',
			'{{WRAPPER}} .elementor-flip-box__button' => 'margin-{{VALUE}}: 0',
		),
		'default' => 'center',
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
		),
	), array(
		'name' => 'back_vertical_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__overlay' => 'justify-content: {{VALUE}}',
		),
		'selectors_dictionary' => array(
			'top' => 'flex-start',
			'middle' => 'center',
			'bottom' => 'flex-end',
		),
		'options' => array(
			'top' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'middle' => array(
				'title' => 'Middle',
				'icon' => 'eicon-v-align-middle',
			),
			'bottom' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'back_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'back_title_text!' => '',
		),
	), array(
		'name' => 'back_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__title' => 'color: {{VALUE}}',
		),
		'condition' => array(
			'back_title_text!' => '',
		),
	), array(
		'name' => 'back_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__title',
	), array(
		'name' => 'back_description_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'button_text!' => '',
		),
	), array(
		'name' => 'back_description_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__description' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'description_typography_b',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-flip-box__back .elementor-flip-box__layer__description',
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-flip-box__button',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-flip-box__button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-flip-box__button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-flip-box__button:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-flip-box__button:hover' => 'border-color: {{VALUE}};',
		),
	) );
