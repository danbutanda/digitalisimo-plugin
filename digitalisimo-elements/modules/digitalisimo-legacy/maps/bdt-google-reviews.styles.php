<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-google-reviews` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'place_info_direction',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews' => 'flex-direction: {{VALUE}};',
		),
		'default' => 'column',
		'condition' => array(
			'show_place_info' => 'yes',
		),
		'options' => array(
			'row' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'column' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'row-reverse' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
			'column-reverse' => array(
				'title' => 'Right',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'place_info_align_items',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews' => 'align-items: {{VALUE}};',
		),
		'default' => 'center',
		'options' => array(
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-align-center-v',
			),
			'flex-start' => array(
				'title' => 'Start',
				'icon' => 'eicon-align-start-v',
			),
			'flex-end' => array(
				'title' => 'End',
				'icon' => 'eicon-align-end-v',
			),
			'stretch' => array(
				'title' => 'stretch',
				'icon' => 'eicon-align-stretch-v',
			),
		),
	), array(
		'name' => 'flex_direction',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item' => 'flex-direction: {{VALUE}};',
		),
		'default' => 'column',
		'options' => array(
			'column' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'column-reverse' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'justify_content',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item' => 'justify-content: {{VALUE}};',
		),
		'condition' => array(
			'flex_direction' => array( 'column', 'column-reverse' ),
		),
		'options' => array(
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-align-center-h',
			),
			'flex-start' => array(
				'title' => 'Start',
				'icon' => 'eicon-align-start-v',
			),
			'flex-end' => array(
				'title' => 'End',
				'icon' => 'eicon-align-end-v',
			),
			'space-between' => array(
				'title' => 'Space Between',
				'icon' => 'eicon-justify-space-between-v',
			),
			'space-around' => array(
				'title' => 'Space Around',
				'icon' => 'eicon-justify-space-around-v',
			),
			'space-evenly' => array(
				'title' => 'Space Evenly',
				'icon' => 'eicon-justify-space-evenly-v',
			),
		),
	), array(
		'name' => 'align_items',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item' => 'align-items: {{VALUE}};',
		),
		'condition' => array(
			'flex_direction' => array( 'row', 'row-reverse' ),
		),
		'options' => array(
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-align-center-v',
			),
			'flex-start' => array(
				'title' => 'Start',
				'icon' => 'eicon-align-start-v',
			),
			'flex-end' => array(
				'title' => 'End',
				'icon' => 'eicon-align-end-v',
			),
			'stretch' => array(
				'title' => 'stretch',
				'icon' => 'eicon-align-stretch-v',
			),
		),
	), array(
		'name' => 'content_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews-desc' => 'width: {{SIZE}}{{UNIT}}; max-width: {{SIZE}}{{UNIT}};  margin: 0 auto;',
		),
	), array(
		'name' => 'google_reviews_item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item',
	), array(
		'name' => 'google_reviews_item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'google_reviews_item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item',
	), array(
		'name' => 'google_reviews_item_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'google_reviews_item_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item',
	), array(
		'name' => 'google_reviews_item_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item:hover',
	), array(
		'name' => 'google_reviews_item_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'google_reviews_item_border_border!' => '',
		),
	), array(
		'name' => 'google_reviews_item_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item:hover',
	), array(
		'name' => 'image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-img img' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'name_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-name a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'name_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-name a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'name_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-name a',
	), array(
		'name' => 'time_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-date' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'time_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-date' => 'padding-top: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'time_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-date',
	), array(
		'name' => 'rating_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item .digi-google-reviews-rating' => 'padding-top: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item p' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'excerpt_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item p' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'excerpt_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item p' => 'text-align: {{VALUE}};',
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
		'name' => 'excerpt_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews__item p' => 'margin-top: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-google-reviews__item p',
	), array(
		'name' => 'place_info_rating_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .epsc-rating-item' => 'color: {{VALUE}};',
		),
		'default' => '#e7e7e7',
	), array(
		'name' => 'place_info_active_rating_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .epsc-rating[class*=" epsc-rating-0"] .epsc-rating-item:nth-child(1) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-1"] .epsc-rating-item:nth-child(-n+1) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-2"] .epsc-rating-item:nth-child(-n+2) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-3"] .epsc-rating-item:nth-child(-n+3) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-4"] .epsc-rating-item:nth-child(-n+4) i:after, {{WRAPPER}} .epsc-rating[class*=" epsc-rating-5"] .epsc-rating-item:nth-child(-n+5) i:after, .epsc-rating.epsc-rating-0-5 .epsc-rating-item:nth-child(1) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-1-5 .epsc-rating-item:nth-child(2) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-2-5 .epsc-rating-item:nth-child(3) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-3-5 .epsc-rating-item:nth-child(4) i:after, {{WRAPPER}} .epsc-rating.epsc-rating-4-5 .epsc-rating-item:nth-child(5) i:after' => 'color: {{VALUE}};',
		),
		'default' => '#FFCC00',
	), array(
		'name' => 'rating_space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews span.epsc-rating' => 'gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'rating_number_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews span.ep-rating-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'rating_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews span.ep-rating-text' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'rating_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-google-reviews span.ep-rating-text',
	), array(
		'name' => 'rating_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews span.ep-rating-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'rating_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-google-reviews span.ep-rating-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'rating_number_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-google-reviews span.ep-rating-text',
	) );
