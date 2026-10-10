<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-sub-menu` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'submenu_columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
		),
		'default' => '2',
		'options' => array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
		),
	), array(
		'name' => 'submenu_columns_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'submenu_rows_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'submenu_header_alignment',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading' => 'text-align:{{VALUE}}',
		),
		'default' => 'start',
		'condition' => array(
			'show_submenu_heading' => 'yes',
		),
		'options' => array(
			'start' => array(
				'title' => 'Start',
				'icon' => 'eicon-h-align-left',
			),
			'end' => array(
				'title' => 'End',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'submenu_heading_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'submenu_heading_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_heading_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'submenu_heading_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'submenu_heading_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading',
	), array(
		'name' => 'submenu_heading_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submenu_heading_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading',
	), array(
		'name' => 'submenu_heading_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading:hover' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'submenu_heading_h_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_heading_h_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__heading:hover' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'submenu_heading_border_border!' => '',
		),
	), array(
		'name' => 'submenu_items_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_items_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'submenu_items_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link',
	), array(
		'name' => 'submenu_items_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'submenu_items_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link',
	), array(
		'name' => 'submenu_items_h_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_items_h_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'submenu_items_border_border!' => array( '', 'none' ),
		),
	), array(
		'name' => 'submenu_item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover',
	), array(
		'name' => 'submenu_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__text' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'submenu_title_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__text',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submenu_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submenu_title_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__text',
	), array(
		'name' => 'submenu_title_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submenu_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__text',
	), array(
		'name' => 'submenu_title_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__text' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link.active .digi-vertical-menu__text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'submenu_title_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__text, {{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link.active .digi-vertical-menu__text',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_title_hover_border',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__text' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'submenu_title_border_border!' => '',
		),
	), array(
		'name' => 'submenu_sub_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__description' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'submenu_sub_title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submenu_sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__description',
	), array(
		'name' => 'submenu_sub_title_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__description' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'glassmorphism_blur_level',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu__icon' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
		),
		'default' => array(
			'size' => 5,
		),
		'condition' => array(
			'glassmorphism_effect' => 'yes',
		),
	), array(
		'name' => 'submenu_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon' => 'color: {{VALUE}}',
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon svg' => 'fill: {{VALUE}}',
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon svg path' => 'fill: {{VALUE}}',
		),
	), array(
		'name' => 'submenu_icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_icon_ordering',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link' => 'align-items:{{VALUE}};',
		),
		'default' => 'center',
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
		'name' => 'submenu_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submenu_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon',
	), array(
		'name' => 'submenu_icon_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'submenu_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon' => 'font-size: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'submenu_icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'submenu_icon_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__icon' => 'color: {{VALUE}}',
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__icon svg' => 'fill: {{VALUE}}',
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__icon svg path' => 'fill: {{VALUE}}',
		),
	), array(
		'name' => 'submenu_icon_h_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__icon',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_icon_h_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__icon' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'submenu_icon_border_border!' => '',
		),
	), array(
		'name' => 'submenu_badge_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__badge' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'submenu_badge_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__badge',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__badge',
	), array(
		'name' => 'submenu_badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'submenu_badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'submenu_badge_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-submenu-badge-h-offset: {{SIZE}}px;',
		),
	), array(
		'name' => 'submenu_badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__badge',
	), array(
		'name' => 'submenu_badge_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__badge' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'submenu_badge_h_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__badge',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'submenu_badge_h_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu .digi-vertical-menu__link:hover .digi-vertical-menu__badge' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'submenu_badge_border_border!' => '',
		),
	) );
