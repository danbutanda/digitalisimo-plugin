<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-give-form-grid` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-grid' => 'display: grid;
					grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'default' => '3',
		'tablet_default' => '2',
		'mobile_default' => '1',
		'options' => array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		),
	), array(
		'name' => 'items_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-grid' => 'grid-column-gap: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'items_row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-grid' => 'grid-row-gap: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'tab_item_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tab_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-wrap .give-card',
	), array(
		'name' => 'tab_item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'tab_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'tab_item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-wrap .give-card',
	), array(
		'name' => 'tab_item_bg_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'tab_item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'tab_item_border_border!' => '',
		),
	), array(
		'name' => 'tab_item_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover',
	), array(
		'name' => 'section_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__media img',
	), array(
		'name' => 'iamge_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__media img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'iamge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__media img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'img_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__media img',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__title' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__title',
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__text' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'text_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__text' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-wrap .give-card__text',
	), array(
		'name' => 'income_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.income' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'income_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.income' => 'margin-right: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'income_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode span.income',
	), array(
		'name' => 'goal_amount_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .raised' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'goal_amount_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .raised',
	), array(
		'name' => 'progress_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar>span' => 'background-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'progress_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'progress_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'progress_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-progress-bar' => 'margin-top: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'pagi_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers' => 'text-align: {{VALUE}}',
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
		'name' => 'pagi_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagi_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers a' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'pagi_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-page-numbers a, {{WRAPPER}} .elementor-shortcode .give-page-numbers span.current',
	), array(
		'name' => 'pagi_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers a, {{WRAPPER}} .elementor-shortcode .give-page-numbers span.current' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'pagi_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers a, {{WRAPPER}} .elementor-shortcode .give-page-numbers span.current' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'pagi_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers' => 'margin-top: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'pagi_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-page-numbers a, {{WRAPPER}} .give-page-numbers span',
	), array(
		'name' => 'pagi_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagi_bg_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers a:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'pagi_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'pagi_border_border!' => '',
		),
	), array(
		'name' => 'pagi_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers span.current' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'pagi_bg_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers span.current' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'pagi_active_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-page-numbers span.current' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'pagi_border_border!' => '',
		),
	) );
