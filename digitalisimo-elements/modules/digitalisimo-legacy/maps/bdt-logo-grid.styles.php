<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-logo-grid` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__list' => 'grid-template-columns: repeat({{SIZE}}, 1fr); display: grid;',
		),
		'desktop_default' => 4,
		'tablet_default' => 2,
		'mobile_default' => 2,
		'options' => array(
			1 => '1',
			2 => '2',
			3 => '3',
			4 => '4',
			5 => '5',
			6 => '6',
		),
	), array(
		'name' => 'column_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__list' => 'grid-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 15,
		),
		'condition' => array(
			'layout' => 'box',
		),
	), array(
		'name' => 'height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__item' => 'height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'grid_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__figure' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'grid_border_type',
		'type' => 'select',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__item' => 'border-style: {{VALUE}};',
		),
		'default' => 'solid',
		'options' => array(
			'none' => 'None',
			'solid' => 'Solid',
			'double' => 'Double',
			'dotted' => 'Dotted',
			'dashed' => 'Dashed',
			'groove' => 'Groove',
		),
	), array(
		'name' => 'grid_border_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-grid-border-width: {{SIZE}}{{UNIT}};',
			'(desktop){{WRAPPER}}.digi-logo-grid__item' => 'border-right-width: var(--ep-grid-border-width, 2px); border-bottom-width: var(--ep-grid-border-width, 2px);',
			'(tablet){{WRAPPER}}.digi-logo-grid__item' => 'border-right-width: var(--ep-grid-border-width, 2px); border-bottom-width: var(--ep-grid-border-width, 2px);',
			'(mobile){{WRAPPER}}.digi-logo-grid__item' => 'border-right-width: var(--ep-grid-border-width, 2px); border-bottom-width: var(--ep-grid-border-width, 2px);',
			'{{WRAPPER}}.digi-logo-grid--tictactoe .digi-logo-grid__item' => 'border-inline-start-width: 0; border-block-end-width: 0 !important; border-bottom-width: 0 !important; border-inline-end-width: var(--ep-grid-border-width, 2px); border-block-start-width: var(--ep-grid-border-width, 2px);',
			'{{WRAPPER}}.digi-logo-grid--box .digi-logo-grid__item' => 'border-width: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 2,
		),
		'tablet_default' => array(
			'size' => 2,
		),
		'mobile_default' => array(
			'size' => 2,
		),
		'condition' => array(
			'grid_border_type!' => 'none',
		),
	), array(
		'name' => 'grid_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__item' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'grid_border_type!' => 'none',
		),
	), array(
		'name' => 'grid_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-grid-border-radius-left: {{LEFT}}{{UNIT}}; --ep-grid-border-radius-right: {{RIGHT}}{{UNIT}};',
			'{{WRAPPER}}.digi-logo-grid--border .digi-logo-grid__list, {{WRAPPER}}.digi-logo-grid--box .digi-logo-grid__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}}.digi-logo-grid__item:first-child' => 'border-top-left-radius: {{TOP}}{{UNIT}};',
			'{{WRAPPER}}.digi-logo-grid__item:last-child' => 'border-bottom-right-radius: {{BOTTOM}}{{UNIT}};',
			'{{WRAPPER}}.digi-logo-grid--tictactoe .digi-logo-grid__list' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}}.digi-logo-grid--tictactoe .digi-logo-grid__item:first-child' => 'border-top-left-radius: {{TOP}}{{UNIT}};',
			'{{WRAPPER}}.digi-logo-grid--tictactoe .digi-logo-grid__item:last-child' => 'border-bottom-right-radius: {{BOTTOM}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__figure' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'grid_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}}.digi-logo-grid--tictactoe .digi-logo-grid__list, {{WRAPPER}}.digi-logo-grid--border .digi-logo-grid__list, {{WRAPPER}}.digi-logo-grid--box .digi-logo-grid__item',
		'exclude' => array( 'box_shadow_position' ),
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__image' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-logo-grid__image',
	), array(
		'name' => 'image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid-img' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; object-fit: contain;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'grid_bg_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__item:hover .digi-logo-grid__figure' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'grid_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__item:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'grid_border_type!' => 'none',
			'layout' => 'box',
		),
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__figure:hover img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'image_css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-logo-grid__figure:hover img',
	), array(
		'name' => 'image_bg_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-logo-grid__figure:hover img' => 'transition-duration: {{SIZE}}s;',
		),
	) );
