<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-give-donor-wall` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-grid.give-grid--best-fit' => 'display: grid;
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
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-grid.give-grid--best-fit' => 'grid-gap: {{SIZE}}px;',
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
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		'name' => 'image_item_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover .give-donor__image' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_avatar' => 'yes',
		),
	), array(
		'name' => 'title_item_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover .give-donor__name' => 'color: {{VALUE}} !important;',
		),
		'condition' => array(
			'show_name' => 'yes',
		),
	), array(
		'name' => 'amount_item_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover span.give-donor__total' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_total' => 'yes',
		),
	), array(
		'name' => 'comment_item_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover .give-donor__content .give-donor__excerpt' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_comments' => 'yes',
		),
	), array(
		'name' => 'date_item_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-wrap .give-card:hover span.give-donor__timestamp' => 'color: {{VALUE}} !important;',
		),
		'condition' => array(
			'show_time' => 'yes',
		),
	), array(
		'name' => 'image_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__image' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'image_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__image' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-donor__image',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'image_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-donor__image',
	), array(
		'name' => 'avatar_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__image' => 'flex-basis: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'avatar_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__image' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__name' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'title_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__name' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__name' => 'padding-bottom: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'amount_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.give-donor__total' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'amount_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode span.give-donor__total',
	), array(
		'name' => 'comment_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__content .give-donor__excerpt' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comment_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__content .give-donor__excerpt .give-donor__read-more' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'comment_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__content .give-donor__excerpt' => 'padding-top: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'comment_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__content .give-donor__excerpt' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.give-donor__timestamp' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'date_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.give-donor__timestamp' => 'padding-top: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'date_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode span.give-donor__timestamp' => 'font-size: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'loadmore_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__load_more' => 'float: {{VALUE}}',
		),
		'default' => 'center',
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'inherit' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__load_more' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-donor__load_more',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-donor__load_more',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__load_more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__load_more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-donor__load_more',
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-donor__load_more',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__load_more:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-shortcode .give-donor__load_more:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-shortcode .give-donor__load_more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	) );
