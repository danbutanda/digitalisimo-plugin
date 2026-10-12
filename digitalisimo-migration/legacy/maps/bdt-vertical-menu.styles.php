<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-vertical-menu` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'menu_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu' => 'width: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'sub_menu_width',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-vertical-submenu-width: {{SIZE}}px;',
		),
		'condition' => array(
			'submenu_type' => 'outer',
		),
	), array(
		'name' => 'menu_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu' => 'justify-content: {{VALUE}}; display: flex;',
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
			'flex-end' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
		),
	), array(
		'name' => 'menu_text_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu' => 'text-align: {{VALUE}};',
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
		'name' => 'main_menu_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list',
	), array(
		'name' => 'main_menu_bg_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list',
	), array(
		'name' => 'main_menu_bg_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'main_menu_bg_link_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'menu_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'color: {{VALUE}};',
			'{{WRAPPER}}' => '--ep-menu-link-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link .digi-vertical-menu__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link .digi-vertical-menu__icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'menu_link_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_parent_arrow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__toggle .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link',
	), array(
		'name' => 'menu_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'menu_link_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list li > .digi-vertical-menu__row > .digi-vertical-menu__toggle' => 'margin-top: {{TOP}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'menu_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'menu_icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link .digi-vertical-menu__icon' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'menu_typography_normal',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link',
	), array(
		'name' => 'menu_link_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}}' => '--ep-menu-link-hover-color: {{VALUE}};',
		),
	), array(
		'name' => 'link_background_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'menu_border_border!' => '',
		),
	), array(
		'name' => 'menu_icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link:hover .digi-vertical-menu__icon' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row > .digi-vertical-menu__link:hover .digi-vertical-menu__icon svg' => 'fill: {{VALUE}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'menu_parent_arrow_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__row:hover .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li:hover > .digi-vertical-menu__row > .digi-vertical-menu__toggle .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_hover_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'color: {{VALUE}};',
			'{{WRAPPER}}' => '--ep-menu-link-active-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_hover_background_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_border_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'menu_border_border!' => '',
		),
	), array(
		'name' => 'menu_icon_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link .digi-vertical-menu__icon' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'menu_parent_arrow_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li.is-open > .digi-vertical-menu__row .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__toggle .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub',
	), array(
		'name' => 'sub_menu_bg_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub',
	), array(
		'name' => 'sub_menu_bg_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'sub_menu_bg_link_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'sub_menu_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_link_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link .digi-vertical-menu__icon' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'sub_menu_parent_arrow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li > .digi-vertical-menu__row > .digi-vertical-menu__toggle .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link',
	), array(
		'name' => 'sub_menu_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'sub_menu_link_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'sub_menu_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'sub_menu_icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link .digi-vertical-menu__icon' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'sub_menu_typography_normal',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link',
	), array(
		'name' => 'sub_menu_link_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_link_background_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'sub_menu_border_border!' => '',
		),
	), array(
		'name' => 'sub_menu_icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__link:hover .digi-vertical-menu__icon' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'sub_menu_parent_arrow_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li .digi-vertical-menu__row:hover .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li:hover > .digi-vertical-menu__row > .digi-vertical-menu__toggle .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_hover_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_hover_background_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'sub_menu_border_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__link' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'sub_menu_border_border!' => '',
		),
	), array(
		'name' => 'sub_menu_icon_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li.is-open .digi-vertical-menu__link .digi-vertical-menu__icon' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'dynamic_menu' => '',
		),
	), array(
		'name' => 'sub_menu_parent_arrow_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li.is-open .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-vertical-menu > .digi-vertical-menu__list > li > .digi-vertical-menu__sub > li.is-open > .digi-vertical-menu__row > .digi-vertical-menu__toggle .digi-vertical-menu__arrow' => 'border-color: {{VALUE}};',
		),
	) );
