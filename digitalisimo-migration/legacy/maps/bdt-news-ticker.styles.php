<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-news-ticker` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'news_ticker_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee' => 'height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'size' => 42,
		),
	), array(
		'name' => 'navigation_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .digi-marquee-navigation svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
		),
		'default' => array(
			'size' => 14,
		),
		'condition' => array(
			'show_navigation' => 'yes',
		),
	), array(
		'name' => 'label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .digi-marquee__label-inner' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_label' => 'yes',
		),
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-marquee .digi-marquee__label-inner',
	), array(
		'name' => 'content_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .digi-marquee-content a' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-marquee .digi-marquee-content span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'content_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .digi-marquee .digi-marquee-content:before, {{WRAPPER}} .digi-marquee .digi-marquee-content:after' => 'box-shadow: 0 0 12px 12px {{VALUE}};',
		),
	), array(
		'name' => 'content_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-marquee .digi-marquee-content',
	), array(
		'name' => 'navigation_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .digi-marquee-navigation' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'navigation_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .digi-marquee-navigation button span svg' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-marquee .digi-marquee-navigation button:hover span svg' => 'color: {{VALUE}};',
		),
	) );
