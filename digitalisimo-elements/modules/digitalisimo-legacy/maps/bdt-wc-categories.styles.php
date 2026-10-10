<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-wc-categories` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .products ul, {{WRAPPER}} .woocommerce ul.products' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'desktop_default' => 4,
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
		'name' => 'item_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .products ul, {{WRAPPER}} .woocommerce ul.products' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .products ul, {{WRAPPER}} .woocommerce ul.products' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'hide_category_count',
		'type' => 'switcher',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce-loop-category__title mark.count' => 'display: none;',
		),
	), array(
		'name' => 'item_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce .product-category a',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .woocommerce .product-category a',
	), array(
		'name' => 'item_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a:hover' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'item_hover_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .woocommerce .product-category a:hover',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .woocommerce .product-category a img',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a:hover img' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'image_border_border!' => '',
		),
	), array(
		'name' => 'image_hover_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a:hover img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category .woocommerce-loop-category__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category .woocommerce-loop-category__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category .woocommerce-loop-category__title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category .woocommerce-loop-category__title' => 'text-align: {{VALUE}};',
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
			'justified' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .woocommerce .product-category .woocommerce-loop-category__title',
	), array(
		'name' => 'hover_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .woocommerce .product-category a:hover .woocommerce-loop-category__title' => 'color: {{VALUE}};',
		),
	) );
