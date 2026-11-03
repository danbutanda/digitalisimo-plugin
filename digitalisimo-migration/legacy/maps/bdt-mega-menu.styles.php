<?php
/**
 * Controles de estilo de Element Pack Pro 10.0.1 `bdt-mega-menu` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'default_menu_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu' => 'justify-content: {{VALUE}}',
		),
		'default' => 'flex-start',
		'condition' => array(
			'ep_megamenu_direction' => 'horizontal',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Start',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'flex-end' => array(
				'title' => 'End',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'vertical_menu_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'vertical_menu_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'vertical_menu_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu',
	), array(
		'name' => 'vertical_menu_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'menu_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'menu_arrow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .elementor-item .sub-arrow' => 'color: {{VALUE}};',
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu .menu-item-has-children::before' => 'border-color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'menu_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'menu_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'menu_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'menu_item_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu' => 'grid-gap: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'menu_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
	), array(
		'name' => 'menu_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'menu_arrow_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .elementor-item .sub-arrow' => 'margin-left: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'ep_megamenu_direction' => 'horizontal',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'ep_megamenu_dropdown_arrows',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .elementor-item .sub-arrow' => 'display:{{VALUE}}',
		),
		'default' => 'block',
	), array(
		'name' => 'menu_item_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
	), array(
		'name' => 'menu_text_h_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover, {{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_h_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover, {{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'menu_h_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover, {{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active',
	), array(
		'name' => 'menu_item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu, {{WRAPPER}} .elementor-nav-menu--main .sub-menu',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'menu_item_dropdown_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'menu_item_dropdown_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'sub_menu_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu',
	), array(
		'name' => 'menu_item_dropdown_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'sub_menu_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu',
	), array(
		'name' => 'submenu_item_color',
		'type' => 'color',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu li > .elementor-sub-item' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'submenu_item_bg_color',
		'type' => 'color',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu li > .elementor-sub-item' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'submene_item_bg_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu li > .elementor-sub-item',
	), array(
		'name' => 'menu_item_submenu_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu li > .elementor-sub-item',
	), array(
		'name' => 'menu_item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu, :hover {{WRAPPER}} .elementor-nav-menu--main .sub-menu:hover',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'item_text_color_hover',
		'type' => 'color',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item:active' => 'color: {{VALUE}}',
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li:hover > .elementor-sub-item' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'item_text_hover_background',
		'type' => 'color',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item:active' => 'background-color: {{VALUE}}',
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li:hover > .elementor-sub-item' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'hamburger_menu_toggle_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-menu-toggle svg' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'hamburger_menu_toggle_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-menu-toggle',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'hamburger_menu_toggle_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-menu-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'hamburger_menu_toggle_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-menu-toggle' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'hamburger_menu_toggle_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-menu-toggle',
	), array(
		'name' => 'hamburger_menu_toggle_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-menu-toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'hamburger_toggle_icon_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-menu-toggle' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	) );
