<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-total-count` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count' => 'text-align: {{VALUE}};',
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
		'name' => 'top_icon_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-top-icon-v-offset: -{{SIZE}}px;',
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
			'position' => 'top',
			'show_icon' => 'yes',
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'left_right_icon_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-left-right-icon-h-offset: {{SIZE}}px;',
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
			'position' => array( 'left', 'right' ),
			'show_icon' => 'yes',
			'icon_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-total-count img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count img' => 'opacity: {{SIZE}};',
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
		'condition' => array(
			'icon_type' => 'image',
		),
	), array(
		'name' => 'counter_number_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count__number' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'counter_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count__label .digi-total-count__number' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'counter_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-total-count__label .digi-total-count__number',
	), array(
		'name' => 'counter_number_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count:hover .digi-total-count__label .digi-total-count__number' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'counter_number_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-total-count:hover .digi-total-count__label .digi-total-count__number',
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-total-count .digi-total-count__label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	) );
