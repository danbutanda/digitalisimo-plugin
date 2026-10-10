<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-grid-tab` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_ratio',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__thumbnail img' => 'height: {{SIZE}}px',
		),
	), array(
		'name' => 'contant_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__text' => 'text-align: {{VALUE}}',
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
				'title' => 'Justify',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'icon_indent',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 8,
		),
		'condition' => array(
			'post_grid_tab_icon[value]!' => '',
		),
	), array(
		'name' => 'tab_text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab > dt' => 'text-align: {{VALUE}};',
		),
		'default' => 'center',
		'condition' => array(
			'grid_tab_item' => 'title',
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
		'name' => 'tab_text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .gridtab > dt',
	), array(
		'name' => 'tab_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab > dt, {{WRAPPER}} .elementor-posts-container .gridtab > dd' => 'border-color: {{VALUE}};',
		),
		'default' => '#ddd',
	), array(
		'name' => 'active_tab_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab > dt.is-active' => 'background-color: {{VALUE}};',
		),
		'default' => '#fff',
	), array(
		'name' => 'active_tab_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab > dt.is-active' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'grid_tab_item' => 'title',
		),
	), array(
		'name' => 'content_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab > dd' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 5,
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__title',
	), array(
		'name' => 'meta_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data *' => 'color: {{VALUE}};',
		),
		'default' => '#adb5bd',
	), array(
		'name' => 'meta_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data *',
	), array(
		'name' => 'excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'excerpt_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 15,
		),
	), array(
		'name' => 'excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt',
	), array(
		'name' => 'readmore_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'readmore_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'readmore_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'readmore_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'readmore_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'margin-top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'readmore_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'readmore_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'readmore_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'readmore_border_border!' => '',
		),
	), array(
		'name' => 'close_button_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab__close:before, {{WRAPPER}} .elementor-posts-container .gridtab__close:after' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab__close' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .gridtab__close',
	), array(
		'name' => 'close_button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab__close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'close_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .gridtab__close',
	), array(
		'name' => 'close_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab__close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'close_button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab__close:hover::before, {{WRAPPER}} .elementor-posts-container .gridtab__close:hover::after' => 'background: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab__close:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'close_button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .gridtab__close:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'close_button_border_border!' => '',
		),
	) );
