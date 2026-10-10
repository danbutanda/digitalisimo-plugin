<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-advanced-divider` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'divider_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider' => 'text-align: {{VALUE}}; margin: 0 auto; margin-{{VALUE}}: 0;',
		),
		'default' => 'center',
		'condition' => array(
			'advanced_divider_select!' => array( 'line', 'dashed', 'line-circle', 'line-cross', 'line-dashed', 'line-star', 'slash', 'rectangle', 'triangle', 'wave', 'kiss-curl', 'jemik', 'finest', 'furrow' ),
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
		'name' => 'divider_line_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider' => 'text-align: {{VALUE}}; margin: 0 auto; margin-{{VALUE}}: 0;',
		),
		'default' => 'center',
		'condition' => array(
			'advanced_divider_select' => array( 'line', 'dashed', 'line-circle', 'line-cross', 'line-dashed', 'line-star', 'slash', 'rectangle', 'triangle', 'wave', 'kiss-curl', 'jemik', 'finest', 'furrow' ),
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
		'name' => 'max_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider' => 'max-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'divider_gap_top',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 15,
		),
	), array(
		'name' => 'divider_gap_bottom',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 15,
		),
	), array(
		'name' => 'divider_svg_stroke_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider svg *' => 'stroke-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'divider_crop',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider svg' => 'transform: scale({{SIZE}}) scale(0.01)',
		),
	), array(
		'name' => 'max_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider svg' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'divider_svg_stroke_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-advanced-divider svg *' => 'stroke: {{VALUE}};',
		),
	), array(
		'name' => 'divider_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-advanced-divider-h-offset: {{SIZE}}px;',
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
			'divider_offset_popover' => 'yes',
		),
	), array(
		'name' => 'divider_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-advanced-divider-v-offset: {{SIZE}}px;',
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
			'divider_offset_popover' => 'yes',
		),
	), array(
		'name' => 'divider_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-advanced-divider-rotate: {{SIZE}}deg;',
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
	) );
