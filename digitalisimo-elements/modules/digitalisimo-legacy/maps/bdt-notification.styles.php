<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-notification` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'content_max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__content' => 'max-width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'notification_type' => 'fixed',
		),
	), array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__content' => 'text-align: {{VALUE}};',
		),
		'condition' => array(
			'notification_type' => 'fixed',
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
		'name' => 'notification_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__panel' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'notification_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-notification__panel',
	), array(
		'name' => 'notification_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-notification__panel',
	), array(
		'name' => 'notification_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'notification_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'notification_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-notification__panel',
	), array(
		'name' => 'notification_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-notification__panel',
	), array(
		'name' => 'notification_close_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__close' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'notification_close_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__close' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'notification_close_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-notification__close',
	), array(
		'name' => 'notification_close_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'notification_close_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'notification_close_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-notification__close',
	), array(
		'name' => 'notification_close_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__close' => 'right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'notification_close_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__close' => 'top: {{SIZE}}%;',
		),
	), array(
		'name' => 'notification_close_z_index',
		'type' => 'number',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-notification__close' => 'z-index: {{VALUE}};',
		),
	) );
