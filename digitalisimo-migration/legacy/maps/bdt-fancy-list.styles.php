<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-fancy-list` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'icon_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => '{{VALUE}};',
		),
		'selectors_dictionary' => array(
			'left' => 'flex-direction: row-reverse; -webkit-flex-direction: row-reverse;',
			'top' => 'flex-direction: column-reverse; -webkit-flex-direction: column-reverse;',
			'bottom' => 'flex-direction: column; -webkit-flex-direction: column;',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
			'top' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'bottom' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'content_y_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'align-items: {{VALUE}};',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-v-align-middle',
			),
			'flex-end' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'list_item_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap',
	), array(
		'name' => 'list_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap',
	), array(
		'name' => 'list_item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'list_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'list_item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap',
	), array(
		'name' => 'list_item_border',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap' => 'min-height: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'list_item_hover_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap:hover',
	), array(
		'name' => 'list_item_hover_border',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list .flex-wrap:hover' => 'border-color: {{VALUE}} !important',
		),
		'condition' => array(
			'list_item_border_border!' => '',
		),
	), array(
		'name' => 'list_item_box_shadow_hover',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-list .flex-wrap:hover',
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-fancy-list__icon svg' => 'fill: {{VALUE}} !important;',
		),
		'default' => '#242424',
	), array(
		'name' => 'right_icon_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'background: {{VALUE}} ;',
		),
		'default' => '#fff',
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-list__icon',
	), array(
		'name' => 'icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} ;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-list__icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-list__icon',
	), array(
		'name' => 'icon_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-fancy-list__icon',
	) );
