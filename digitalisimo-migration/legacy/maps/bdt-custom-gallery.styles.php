<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-custom-gallery` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__list' => 'margin-left: -{{SIZE}}px',
			'{{WRAPPER}} .digi-custom-gallery__item' => 'padding-left: {{SIZE}}px',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__list' => 'margin-top: -{{SIZE}}px',
			'{{WRAPPER}} .digi-custom-gallery__item' => 'margin-top: {{SIZE}}px',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-custom-gallery .digi-custom-gallery-inner',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__image, {{WRAPPER}} .digi-custom-gallery .digi-custom-gallery-inner' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_skin_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .digi-custom-gallery__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} 0 0;',
		),
		'condition' => array(
			'_skin!' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link i, {{WRAPPER}} .digi-custom-gallery__item-link span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-custom-gallery__item-link',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-custom-gallery__item-link',
	), array(
		'name' => 'typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-custom-gallery__item-link i',
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link:hover span, {{WRAPPER}} .digi-custom-gallery__item-link:hover i' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-custom-gallery__item-link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	) );
