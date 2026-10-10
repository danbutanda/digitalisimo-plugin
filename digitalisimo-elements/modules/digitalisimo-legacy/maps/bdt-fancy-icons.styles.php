<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fancy-icons` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'item_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item:hover' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'fancy_ixons_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-icons',
	), array(
		'name' => 'fancy_ixons_item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_icon_text_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item:hover a' => 'transform: rotate({{SIZE}}deg);',
		),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item .digi-fancy-icons__content' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-icons__item .digi-fancy-icons__content svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'item_icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item:hover a.icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-icons__item:hover a.icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item .digi-fancy-icons__content' => 'font-size: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => 'vw',
		),
		'size_units' => array( 'vw' ),
	), array(
		'name' => 'item_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-fancy-icons__item .digi-fancy-icons__content',
	), array(
		'name' => 'item_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item .digi-fancy-icons__content' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'item_text_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-icons__item:hover a.text' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
		),
	), array(
		'name' => 'text_size',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-icons__item .digi-fancy-icons__content',
	) );
