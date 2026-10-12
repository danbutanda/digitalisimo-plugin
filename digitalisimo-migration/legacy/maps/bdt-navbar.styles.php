<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-navbar` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu' => 'justify-content: {{VALUE}};',
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
		'name' => 'menu_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu' => 'transform: translateX({{SIZE}}{{UNIT}});',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'text_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'menu_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'min-height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'dropdown_link_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item' => 'text-align: {{VALUE}};',
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
		'name' => 'dropdown_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dropdown_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'width: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'menu_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_link_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu' => 'margin-left: -{{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li' => 'margin-left: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'menu_bottom_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu' => 'margin-bottom: -{{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'menu_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
	), array(
		'name' => 'menu_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'menu_typography_normal',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item',
	), array(
		'name' => 'menu_parent_arrow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li.menu-item-has-children > a .sub-arrow' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'menu_parent_arrow' => 'yes',
		),
	), array(
		'name' => 'navbar_hover_style_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li:hover > .elementor-item:before' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li:hover > .elementor-item:after' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'navbar_style!' => '',
		),
	), array(
		'name' => 'menu_link_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'link_background_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_border_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_border_radius_hover',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'menu_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item:hover',
	), array(
		'name' => 'menu_parent_arrow_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li.menu-item-has-children > a:hover .sub-arrow' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'menu_parent_arrow' => 'yes',
		),
	), array(
		'name' => 'navbar_active_style_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active:before' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active:after' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'navbar_style!' => '',
		),
	), array(
		'name' => 'menu_hover_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_hover_background_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'menu_border_active',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active',
	), array(
		'name' => 'menu_border_radius_active',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'menu_typography_active',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-item.elementor-item-active',
	), array(
		'name' => 'menu_parent_arrow_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li.menu-item-has-children > .elementor-item-active .sub-arrow' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'menu_parent_arrow' => 'yes',
		),
	), array(
		'name' => 'dropdown_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_link_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_link_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li + li' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'dropdown_link_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'dropdown_link_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item',
	), array(
		'name' => 'dropdown_link_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dropdown_link_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item',
	), array(
		'name' => 'dropdown_parent_arrow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li.menu-item-has-children > a .sub-arrow' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'menu_parent_arrow' => 'yes',
		),
	), array(
		'name' => 'dropdown_link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_link_hover_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_border_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item:hover' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_radius_hover',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dropdown_typography_hover',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item:hover',
	), array(
		'name' => 'dropdown_parent_arrow_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li.menu-item-has-children > a:hover .sub-arrow' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'menu_parent_arrow' => 'yes',
		),
	), array(
		'name' => 'dropdown_active_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item.elementor-item-active' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_active_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item.elementor-item-active' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'dropdown_active_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item.elementor-item-active',
	), array(
		'name' => 'dropdown_active_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item.elementor-item-active' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'dropdown_typography_active',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li > .elementor-sub-item.elementor-item-active',
	), array(
		'name' => 'dropdown_parent_arrow_color_active',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-nav-menu--main .sub-menu > li.menu-item-has-children > .elementor-item-active .sub-arrow' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'menu_parent_arrow' => 'yes',
		),
	) );
