<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-review-card` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_shadow_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .swiper-carousel' => 'padding: {{SIZE}}{{UNIT}}; margin: 0 -{{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'image_horizontal_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-review-card-image-h-offset: {{SIZE}}px;',
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
			'image_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'image_vertical_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-review-card-image-v-offset: {{SIZE}}px;',
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
			'image_offset_toggle' => 'yes',
		),
	), array(
		'name' => 'rating_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .epsc-rating-item .ep-icon-star-empty' => 'color: {{VALUE}};',
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
			'{{WRAPPER}} .epsc-rating-item .ep-icon-star-full, {{WRAPPER}} .epsc-rating-item .ep-icon-star-half' => 'color: {{VALUE}};',
		),
		'default' => '#FFCC00',
		'condition' => array(
			'rating_type' => 'star',
		),
	), array(
		'name' => 'gb_read_more_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .bdt_read_more, {{WRAPPER}} .bdt_read_less',
	), array(
		'name' => 'gb_words_limit_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bdt_read_more' => 'color: {{VALUE}};',
			'{{WRAPPER}} .bdt_read_less' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'gb_words_limit_color_color_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .bdt_read_less:hover' => 'color: {{VALUE}};',
			'{{WRAPPER}} .bdt_read_more:hover' => 'color: {{VALUE}};',
		),
	) );
