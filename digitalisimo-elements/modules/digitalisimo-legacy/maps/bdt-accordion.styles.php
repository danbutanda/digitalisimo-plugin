<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-accordion` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'accordion_item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__item',
	), array(
		'name' => 'accordion_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-accordion__item',
	), array(
		'name' => 'accordion_item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'accordion_item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-accordion__item',
	), array(
		'name' => 'item_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item + .digi-accordion__item' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 2,
		),
	), array(
		'name' => 'remove_item_border',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item + .digi-accordion__item' => 'border-block-start: none;',
		),
	), array(
		'name' => 'title_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__title' => 'justify-content: {{VALUE}};',
		),
		'default' => 'flex-start',
		'options' => array(
			'flex-start' => array(
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
			'justify' => array(
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__summary' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion__custom-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__summary',
	), array(
		'name' => 'title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-accordion__summary',
	), array(
		'name' => 'title_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-accordion__summary',
	), array(
		'name' => 'text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-accordion__summary',
	), array(
		'name' => 'title_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-accordion__summary',
	), array(
		'name' => 'hover_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__summary' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__custom-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'hover_title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__summary',
	), array(
		'name' => 'title_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__summary' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'title_border_border!' => '',
		),
	), array(
		'name' => 'active_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__summary' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__custom-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'active_title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__summary',
	), array(
		'name' => 'active_title_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__summary',
	), array(
		'name' => 'active_title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__summary',
	), array(
		'name' => 'active_title_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion .digi-accordion__item .digi-accordion__custom-icon i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion .digi-accordion__item .digi-accordion__custom-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'title_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__custom-icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__custom-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'title_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion .digi-accordion__item:hover .digi-accordion__custom-icon i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion .digi-accordion__item:hover .digi-accordion__custom-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'title_active_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion .digi-accordion__item[open] .digi-accordion__custom-icon i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion .digi-accordion__item[open] .digi-accordion__custom-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__marker' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion__marker svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__marker',
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-accordion__marker',
	), array(
		'name' => 'icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__marker' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__marker' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__summary .digi-accordion__marker' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'icon_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-accordion__marker',
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__marker' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__marker svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_hover_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__marker',
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:hover .digi-accordion__marker' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'icon_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__marker' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__marker svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_active_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__marker',
	), array(
		'name' => 'icon_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item[open] .digi-accordion__marker' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'icon_border_border!' => '',
		),
	), array(
		'name' => 'content_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'content_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__content',
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-accordion__content',
	), array(
		'name' => 'content_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'content_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'content_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-accordion__content',
	), array(
		'name' => 'content_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-accordion__content',
	), array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'text-align: {{VALUE}};',
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
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	) );
