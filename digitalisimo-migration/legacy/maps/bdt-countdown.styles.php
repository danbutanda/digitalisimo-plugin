<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-countdown` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item' => '--ep-countdown-glass-blur: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'glassmorphism_effect' => 'yes',
		),
	), array(
		'name' => 'count_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item',
	), array(
		'name' => 'count_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item',
	), array(
		'name' => 'count_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'individual_style' => '',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'count_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'individual_style' => '',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'count_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item',
	), array(
		'name' => 'number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-digits' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'number_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-digits',
	), array(
		'name' => 'number_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-digits',
	), array(
		'name' => 'number_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-digits' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'number_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-digits' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'text_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-digits',
	), array(
		'name' => 'number_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-digits',
	), array(
		'name' => 'number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-digits',
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-label' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'label_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-label',
	), array(
		'name' => 'label_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-label',
	), array(
		'name' => 'label_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'label_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'label_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-label',
	), array(
		'name' => 'label_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-label',
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-label',
	), array(
		'name' => 'days_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-days)',
	), array(
		'name' => 'days_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-days)',
	), array(
		'name' => 'days_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-days)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'days_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-days)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'days_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-days)',
	), array(
		'name' => 'days_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'days_number_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits',
	), array(
		'name' => 'days_number_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits',
	), array(
		'name' => 'days_number_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'days_number_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'days_number_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits',
	), array(
		'name' => 'days_number_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits',
	), array(
		'name' => 'days_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-digits',
	), array(
		'name' => 'days_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
	), array(
		'name' => 'days_label_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label',
	), array(
		'name' => 'days_label_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label',
	), array(
		'name' => 'days_label_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'days_label_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'days_label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'days_label_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label',
	), array(
		'name' => 'days_label_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label',
	), array(
		'name' => 'days_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-days) .elementor-countdown-label',
	), array(
		'name' => 'hours_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-hours)',
	), array(
		'name' => 'hours_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-hours)',
	), array(
		'name' => 'hours_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-hours)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'hours_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-hours)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'hours_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-hours)',
	), array(
		'name' => 'hours_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'hours_number_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits',
	), array(
		'name' => 'hours_number_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits',
	), array(
		'name' => 'hours_number_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'hours_number_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'hours_number_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits',
	), array(
		'name' => 'hours_number_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits',
	), array(
		'name' => 'hours_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-digits',
	), array(
		'name' => 'hours_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
	), array(
		'name' => 'hours_label_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label',
	), array(
		'name' => 'hours_label_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label',
	), array(
		'name' => 'hours_label_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'hours_label_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'hours_label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'hours_label_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label',
	), array(
		'name' => 'hours_label_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label',
	), array(
		'name' => 'hours_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-hours) .elementor-countdown-label',
	), array(
		'name' => 'minutes_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-minutes)',
	), array(
		'name' => 'minutes_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-minutes)',
	), array(
		'name' => 'minutes_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-minutes)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'minutes_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-minutes)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'minutes_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-minutes)',
	), array(
		'name' => 'minutes_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'minutes_number_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits',
	), array(
		'name' => 'minutes_number_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits',
	), array(
		'name' => 'minutes_number_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'minutes_number_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'minutes_number_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits',
	), array(
		'name' => 'minutes_number_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits',
	), array(
		'name' => 'minutes_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-digits',
	), array(
		'name' => 'minutes_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
	), array(
		'name' => 'minutes_label_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label',
	), array(
		'name' => 'minutes_label_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label',
	), array(
		'name' => 'minutes_label_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'minutes_label_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'minutes_label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'minutes_label_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label',
	), array(
		'name' => 'minutes_label_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label',
	), array(
		'name' => 'minutes_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-minutes) .elementor-countdown-label',
	), array(
		'name' => 'seconds_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-seconds)',
	), array(
		'name' => 'seconds_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-seconds)',
	), array(
		'name' => 'seconds_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-seconds)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'seconds_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-seconds)' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'seconds_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-wrapper .elementor-countdown-item:has(.elementor-countdown-seconds)',
	), array(
		'name' => 'seconds_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'seconds_number_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits',
	), array(
		'name' => 'seconds_number_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits',
	), array(
		'name' => 'seconds_number_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'seconds_number_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'seconds_number_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits',
	), array(
		'name' => 'seconds_number_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits',
	), array(
		'name' => 'seconds_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-digits',
	), array(
		'name' => 'seconds_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
	), array(
		'name' => 'seconds_label_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label',
	), array(
		'name' => 'seconds_label_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label',
	), array(
		'name' => 'seconds_label_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'seconds_label_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'seconds_label_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_labels' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'seconds_label_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label',
	), array(
		'name' => 'seconds_label_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label',
	), array(
		'name' => 'seconds_label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-item:has(.elementor-countdown-seconds) .elementor-countdown-label',
	), array(
		'name' => 'divider_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-countdown-separator-h-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'divider_offset_popover' => 'yes',
		),
	), array(
		'name' => 'divider_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-countdown-separator-v-offset: {{SIZE}}px;',
		),
		'condition' => array(
			'divider_offset_popover' => 'yes',
		),
	), array(
		'name' => 'divider_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-countdown-separator-rotate: {{SIZE}}deg;',
		),
		'condition' => array(
			'divider_offset_popover' => 'yes',
		),
	), array(
		'name' => 'end_message_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-expire--message' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'end_message_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-countdown-expire--message',
	), array(
		'name' => 'end_message_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-countdown-expire--message',
	), array(
		'name' => 'end_message_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-expire--message' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'end_message_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-expire--message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'end_message_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-countdown-expire--message',
	), array(
		'name' => 'end_message_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-countdown-expire--message',
	), array(
		'name' => 'end_message_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-countdown-expire--message' => 'text-align: {{VALUE}};',
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
	) );
