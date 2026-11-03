<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-timeline` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'timeline_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__item:after' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'date_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'background-color: {{VALUE}};',
		),
		'default' => '#f3f3f3;',
	), array(
		'name' => 'date_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__date span',
	), array(
		'name' => 'date_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'default' => array(
			'top' => '2',
			'right' => '2',
			'bottom' => '2',
			'left' => '2',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'date_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__date span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'default' => array(
			'top' => '10',
			'right' => '15',
			'bottom' => '10',
			'left' => '15',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__date',
	), array(
		'name' => 'date_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__date span',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title *' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title a:hover' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'title_link' => 'yes',
		),
	), array(
		'name' => 'title_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__title *',
	), array(
		'name' => 'title_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title *' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__title *' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__title',
	), array(
		'name' => 'excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__text' => 'color: {{VALUE}};',
		),
		'default' => '#888888',
	), array(
		'name' => 'excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-timeline .digi-timeline__text',
	), array(
		'name' => 'excerpt_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-timeline .digi-timeline__text' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	) );
