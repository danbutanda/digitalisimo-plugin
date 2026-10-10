<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-brand-grid` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__list' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'desktop_default' => 3,
		'tablet_default' => 2,
		'mobile_default' => 1,
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
			'{{WRAPPER}} .digi-brand-grid__list' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__list' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-brand-grid__item',
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-brand-grid__item',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-brand-grid__item',
	), array(
		'name' => 'item_shadow_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-carousel' => 'padding: {{SIZE}}{{UNIT}}; margin: 0 -{{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'brand_image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__logo' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; object-fit: cover;',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-brand-grid__logo',
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__item:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-brand-grid__item:hover',
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__summary' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'icon_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-brand-grid__list-checkbox, {{WRAPPER}} .digi-brand-grid__list-content',
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-brand-grid__list-checkbox, {{WRAPPER}} .digi-brand-grid__list-content',
	), array(
		'name' => 'iamge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__list-checkbox, {{WRAPPER}} .digi-brand-grid__list-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'iamge_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__list-checkbox, {{WRAPPER}} .digi-brand-grid__list-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'iamge_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__list-checkbox, {{WRAPPER}} .digi-brand-grid__list-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__list-checkbox, {{WRAPPER}} .digi-brand-grid__list-content' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'icon_font_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__summary' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'img_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-brand-grid__list-checkbox, {{WRAPPER}} .digi-brand-grid__list-content',
	), array(
		'name' => 'name_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__name' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'name_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-brand-grid__name',
	), array(
		'name' => 'name_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-brand-grid__name',
	), array(
		'name' => 'website_link_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__link' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'website_link_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__link:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'website_link_top_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-brand-grid__content' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'website_link_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-brand-grid__link',
	) );
