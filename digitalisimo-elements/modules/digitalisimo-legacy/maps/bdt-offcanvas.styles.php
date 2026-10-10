<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-offcanvas` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'button_close_icon_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close' => '{{VALUE}};',
		),
		'selectors_dictionary' => array(
			'left' => 'left: 10px; right: auto;',
			'center' => 'left: 50%; right: auto; transform: translateX(-50%);',
			'right' => 'right: 10px; left: auto;',
		),
		'condition' => array(
			'offcanvas_close_button' => 'yes',
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
		),
	), array(
		'name' => 'button_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-offcanvas-h-offset: {{SIZE}}px;',
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
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-offcanvas-v-offset: {{SIZE}}px;',
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
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-offcanvas-rotate: {{SIZE}}deg;',
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
			'button_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'button_icon_align',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-button-content-wrapper' => '{{VALUE}};',
		),
		'default' => 'left',
		'selectors_dictionary' => array(
			'left' => 'align-items: center;',
			'right' => 'align-items: center;',
			'top' => 'flex-direction: column; align-items: center;',
			'bottom' => 'flex-direction: column-reverse; align-items: center;',
		),
		'condition' => array(
			'offcanvas_button_icon[value]!' => '',
		),
		'options' => array(
			'left' => 'Left',
			'right' => 'Right',
			'top' => 'Top',
			'bottom' => 'Bottom',
		),
	), array(
		'name' => 'offcanvas_content_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'offcanvas_content_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar a' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-offcanvas__bar a *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'offcanvas_content_link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar a:hover' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'offcanvas_content_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar' => 'background-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'offcanvas_overlay_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__overlay' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'offcanvas_overlay' => 'yes',
		),
	), array(
		'name' => 'offcanvas_content_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-offcanvas__bar',
	), array(
		'name' => 'offcanvas_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'offcanvas_widget_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-offcanvas__bar .widget',
	), array(
		'name' => 'widget_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar .widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'offcanvas_widget_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar .widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'offcanvas_vertical_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__bar .widget:not(:first-child)' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'offcanvas_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-offcanvas__button svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'offcanvas_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'offcanvas_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-offcanvas__button',
	), array(
		'name' => 'offcanvas_button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'offcanvas_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'offcanvas_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-offcanvas__button',
	), array(
		'name' => 'offcanvas_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-offcanvas__button',
	), array(
		'name' => 'offcanvas_button_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__icon' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'offcanvas_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-offcanvas__button:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'offcanvas_button_background_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'offcanvas_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__button:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'offcanvas_button_border_border!' => '',
		),
	), array(
		'name' => 'close_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-offcanvas__close *' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'close_button_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-offcanvas__close',
	), array(
		'name' => 'close_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'close_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'close_button_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'close_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-offcanvas__close',
	), array(
		'name' => 'close_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-offcanvas__close',
	), array(
		'name' => 'close_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-offcanvas__close:hover *' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'close_button_hover_bg',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-offcanvas__close:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'close_button_border_border!' => '',
		),
	) );
