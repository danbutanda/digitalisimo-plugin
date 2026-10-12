<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-product-grid` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__list' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
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
			'{{WRAPPER}} .digi-product-grid__list' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'row_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__list' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item' => 'text-align: {{VALUE}};',
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
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__link' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'readmore_icon[value]!' => '',
		),
	), array(
		'name' => 'badge_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-h-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-v-offset: {{SIZE}}px;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'badge_rotate',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-badge-rotate: {{SIZE}}deg;',
		),
		'default' => array(
			'size' => 0,
		),
		'tablet_default' => array(
			'size' => 0,
		),
		'mobile_default' => array(
			'size' => 0,
		),
		'condition' => array(
			'badge_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-product-grid__item',
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-product-grid__item',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-product-grid__item',
	), array(
		'name' => 'item_shadow_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-carousel' => 'padding: {{SIZE}}{{UNIT}}; margin: 0 -{{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-product-grid__item:hover',
	), array(
		'name' => 'item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'item_border_border!' => '',
		),
	), array(
		'name' => 'item_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-product-grid__item:hover',
	), array(
		'name' => 'image_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-product-grid__image',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-product-grid__image',
	), array(
		'name' => 'iamge_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'iamge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__image' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__visual' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-product-grid__image',
	), array(
		'name' => 'img_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-product-grid__image',
	), array(
		'name' => 'image_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__image',
	), array(
		'name' => 'image_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__image' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'image_border_border!' => '',
		),
	), array(
		'name' => 'image_css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__image',
	), array(
		'name' => 'image_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__image',
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-product-grid__title',
	), array(
		'name' => 'title_shadow',
		'group' => 'text-shadow',
		'selector' => '{{WRAPPER}} .digi-product-grid__title',
	), array(
		'name' => 'price_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__price' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'price_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__price' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'price_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__price' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'price_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-product-grid__price',
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-product-grid__text',
	), array(
		'name' => 'readmore_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__link' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-product-grid__link svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-product-grid__link',
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-product-grid__link',
	), array(
		'name' => 'readmore_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-product-grid__link',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-product-grid__link',
	), array(
		'name' => 'readmore_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__link:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-product-grid__link:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-product-grid__link:hover',
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__link:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'rating_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .epsc-rating-item' => 'color: {{VALUE}};',
		),
		'default' => '#e7e7e7',
		'condition' => array(
			'rating_type' => 'star',
		),
	), array(
		'name' => 'active_rating_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .epsc-rating[class*=" epsc-rating-0"] .epsc-rating-item:nth-child(1) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-1"] .epsc-rating-item:nth-child(-n+1) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-2"] .epsc-rating-item:nth-child(-n+2) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-3"] .epsc-rating-item:nth-child(-n+3) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-4"] .epsc-rating-item:nth-child(-n+4) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-5"] .epsc-rating-item:nth-child(-n+5) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-1-5 .epsc-rating-item:nth-child(2) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-2-5 .epsc-rating-item:nth-child(3) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-3-5 .epsc-rating-item:nth-child(4) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-4-5 .epsc-rating-item:nth-child(5) i:after' => 'color: {{VALUE}};',
		),
		'default' => '#FFCC00',
		'condition' => array(
			'rating_type' => 'star',
		),
	), array(
		'name' => 'rating_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating' => 'color: {{VALUE}};',
		),
		'default' => '#FFCC00',
		'condition' => array(
			'rating_type' => 'number',
		),
	), array(
		'name' => 'rating_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'rating_type' => 'number',
		),
	), array(
		'name' => 'rating_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-product-grid__rating',
	), array(
		'name' => 'rating_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'rating_type' => 'number',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'rating_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'rating_type' => 'number',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'rating_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating-time' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'rating_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'rating_space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating i + i' => 'margin-left: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-product-grid__rating span' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'rating_count_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__rating-count' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'rating_count_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-product-grid__rating-count',
	), array(
		'name' => 'time_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__list-time' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'time_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__item:hover .digi-product-grid__list-time' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'time_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__list-time i' => 'margin-right: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'time_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-product-grid__list-time',
	), array(
		'name' => 'badge_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__badge span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'badge_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-product-grid__badge span',
	), array(
		'name' => 'badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-product-grid__badge span',
	), array(
		'name' => 'badge_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__badge span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'badge_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-product-grid__badge span',
	), array(
		'name' => 'badge_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-product-grid__badge span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-product-grid__badge span',
	) );
