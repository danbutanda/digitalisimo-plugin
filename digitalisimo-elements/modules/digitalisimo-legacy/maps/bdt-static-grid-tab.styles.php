<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-static-grid-tab` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'tab_text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label strong' => 'text-align: {{VALUE}};',
		),
		'default' => 'center',
		'condition' => array(
			'grid_tab_type' => 'title',
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
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'text-align: {{VALUE}};',
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
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'readmore_icon[value]!' => '',
		),
	), array(
		'name' => 'item_tab_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .gridtab > dt .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'grid_tab_type' => 'title',
		),
	), array(
		'name' => 'item_tab_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .gridtab > dt',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'tab_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab > dt, {{WRAPPER}} .digi-fancy-tabs .gridtab > dd' => 'border-color: {{VALUE}};',
		),
		'default' => '#fff',
	), array(
		'name' => 'tab_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab > dt' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'tab_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label strong',
	), array(
		'name' => 'active_tab_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .gridtab > dt.is-active .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'grid_tab_type' => 'title',
		),
	), array(
		'name' => 'item_tab_active_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .gridtab > dt.is-active',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'content_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .gridtab > dd',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab > dd' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__tab' => 'grid-gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__icon img',
	), array(
		'name' => 'image_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__icon img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__icon img',
	), array(
		'name' => 'img_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__icon img',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label strong' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__label strong' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label strong',
	), array(
		'name' => 'title_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__label strong',
	), array(
		'name' => 'readmore_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-tabs__button svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button',
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button',
	), array(
		'name' => 'readmore_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button',
	), array(
		'name' => 'readmore_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-tabs__button:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs__button:hover',
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs__button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'close_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab__close:before, {{WRAPPER}} .digi-fancy-tabs .gridtab__close:after' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .gridtab__close',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'close_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .gridtab__close',
	), array(
		'name' => 'close_button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab__close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'close_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab__close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'close_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .gridtab__close',
	), array(
		'name' => 'close_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab__close:hover::before, {{WRAPPER}} .digi-fancy-tabs .gridtab__close:hover::after' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-tabs .gridtab__close:hover',
		'types' => array( 'classic', 'gradient' ),
		'exclude' => array( 'image' ),
	), array(
		'name' => 'close_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-tabs .gridtab__close:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'close_button_border_border!' => '',
		),
	) );
