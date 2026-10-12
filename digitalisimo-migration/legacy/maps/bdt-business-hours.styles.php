<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-business-hours` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'bs_header_text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__now' => 'text-align: {{VALUE}};',
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
		'name' => 'background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-hours__now',
		'types' => array( 'classic', 'gradient', 'video' ),
	), array(
		'name' => 'bs_header_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__now' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'current_time_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__clock' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_current_time' => 'yes',
		),
	), array(
		'name' => 'current_time_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-hours__clock',
	), array(
		'name' => 'current_time_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-hours__clock',
	), array(
		'name' => 'bs_current_time_sapcing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__clock' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'show_current_time' => 'yes',
		),
	), array(
		'name' => 'current_date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__date' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_current_date' => 'yes',
		),
	), array(
		'name' => 'current_date_text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-hours__date',
	), array(
		'name' => 'current_date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-hours__date',
	), array(
		'name' => 'bs_current_date_sapcing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__date' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'show_current_date' => 'yes',
		),
	), array(
		'name' => 'section_bs_list_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} div.digi-hours__list div' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => 5,
			'right' => 5,
			'bottom' => 5,
			'left' => 5,
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'bs_genarel_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__list' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'day_divider_style',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__list div.digi-hours__row:not(:first-child)' => 'border-top-style: {{VALUE}};',
		),
		'default' => 'solid',
		'condition' => array(
			'day_divider' => 'yes',
		),
		'options' => array(
			'solid' => 'Solid',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
		),
	), array(
		'name' => 'day_divider_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__list div.digi-hours__row:not(:first-child)' => 'border-top-color: {{VALUE}};',
		),
		'default' => '#e8e8e8',
		'condition' => array(
			'day_divider' => 'yes',
		),
	), array(
		'name' => 'day_divider_weight',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__list div.digi-hours__row:not(:first-child)' => 'border-top-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'condition' => array(
			'day_divider' => 'yes',
		),
	), array(
		'name' => 'bs_day_cutom_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__row dt' => 'width: {{SIZE}}%;',
		),
	), array(
		'name' => 'business_hours_day_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__list .digi-hours__row dt' => 'text-align: {{VALUE}};',
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
		'name' => 'business_hours_time_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__list .digi-hours__row dd' => 'text-align: {{VALUE}};',
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
		'name' => 'business_day_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__row dt' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'business_day_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-hours__row dt',
	), array(
		'name' => 'business_timing_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__row dd' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'business_timings_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-hours__row dd',
	), array(
		'name' => 'business_hours_striped_odd_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__row:nth-child(odd)' => 'background: {{VALUE}};',
		),
		'default' => '#eaeaea',
		'condition' => array(
			'business_hours_striped' => 'yes',
		),
	), array(
		'name' => 'striped_effect_even',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__row:nth-child(even)' => 'background: {{VALUE}};',
		),
		'default' => '#FFFFFF',
		'condition' => array(
			'business_hours_striped' => 'yes',
		),
	), array(
		'name' => 'dynamic_separator_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .dynamic-separator' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'business_dynamic_msg_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-hours__status',
	), array(
		'name' => 'dynamic_business_msg_sapcing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-hours__status' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
	) );
