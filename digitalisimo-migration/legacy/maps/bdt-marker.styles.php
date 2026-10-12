<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-marker` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => 'text-align: {{VALUE}};',
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
	), array(
		'name' => 'caption_align',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .marker-caption-text' => 'text-align: {{VALUE}};',
		),
		'default' => '',
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
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .marker-caption-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'caption_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .marker-caption-text',
	), array(
		'name' => 'marker_tooltip_arrow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .tippy-popper[x-placement^=left] .tippy-arrow' => 'border-left-color: {{VALUE}}',
			'{{WRAPPER}} .tippy-popper[x-placement^=right] .tippy-arrow' => 'border-right-color: {{VALUE}}',
			'{{WRAPPER}} .tippy-popper[x-placement^=top] .tippy-arrow' => 'border-top-color: {{VALUE}}',
			'{{WRAPPER}} .tippy-popper[x-placement^=bottom] .tippy-arrow' => 'border-bottom-color: {{VALUE}}',
		),
	) );
