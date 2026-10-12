<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-list` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'column',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
		),
		'default' => '2',
		'tablet_default' => '2',
		'mobile_default' => '1',
		'condition' => array(
			'show_horizontal' => 'yes',
		),
		'options' => array(
			'1' => 'One',
			'2' => 'Two',
			'3' => 'Three',
			'4' => 'Four',
		),
	), array(
		'name' => 'space_between',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'content_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => '{{VALUE}}',
		),
		'selectors_dictionary' => array(
			'row' => 'flex-direction: row;',
			'row-reverse' => 'flex-direction: row-reverse; text-align: right;',
			'space-between' => 'flex-direction: row-reverse; justify-content: space-between;',
		),
		'options' => array(
			'row' => array(
				'title' => 'Left',
				'icon' => 'eicon-align-start-h',
			),
			'row-reverse' => array(
				'title' => 'Right',
				'icon' => 'eicon-align-end-h',
			),
			'space-between' => array(
				'title' => 'Space Between',
				'icon' => 'eicon-justify-space-between-h',
			),
		),
	), array(
		'name' => 'content_y_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => 'align-items: {{VALUE}};',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-v-align-middle',
			),
			'flex-end' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'item_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post',
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post',
	), array(
		'name' => 'item_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
	), array(
		'name' => 'space_between_items',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post' => 'gap: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'item_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post',
	), array(
		'name' => 'list_layout_image_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img' => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'list_layout_image_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img' => 'height: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img',
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img',
	), array(
		'name' => 'image_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'image_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img',
	), array(
		'name' => 'css_filters',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img',
	), array(
		'name' => 'image_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'background_hover_transition',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail img' => 'transition-duration: {{SIZE}}s',
		),
		'default' => array(
			'size' => 0.3,
		),
	), array(
		'name' => 'image_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail:hover img',
	), array(
		'name' => 'image_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail:hover img' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'image_border_border!' => '',
		),
	), array(
		'name' => 'css_filters_hover',
		'group' => 'css-filter',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail:hover img',
	), array(
		'name' => 'image_opacity_hover',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__thumbnail:hover img' => 'opacity: {{SIZE}};',
		),
	), array(
		'name' => 'list_layout_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title a' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'list_layout_title_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title a:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__title',
	), array(
		'name' => 'title_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data span' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_date' => 'yes',
		),
	), array(
		'name' => 'date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data span',
	), array(
		'name' => 'category_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data a' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_category' => 'yes',
		),
	), array(
		'name' => 'category_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data a:hover' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_category' => 'yes',
		),
	), array(
		'name' => 'category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data a',
	) );
