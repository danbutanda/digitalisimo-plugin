<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-block-modern` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
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
			'post_block_modern_icon[value]!' => '',
		),
	), array(
		'name' => 'left_part_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post.left-part',
	), array(
		'name' => 'left_part_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post.left-part' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'left_part_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post.left-part',
	), array(
		'name' => 'left_part_date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data span' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
	), array(
		'name' => 'left_part_date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data span',
	), array(
		'name' => 'left_part_category_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data a' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
	), array(
		'name' => 'left_part_category_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data a' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
	), array(
		'name' => 'left_part_category_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data a',
	), array(
		'name' => 'left_part_category_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'left_part_category_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'left_part_category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__meta-data a',
	), array(
		'name' => 'left_part_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__title' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'title' => 'yes',
		),
	), array(
		'name' => 'left_part_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__title',
	), array(
		'name' => 'left_part_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part.elementor-post .elementor-post__meta-data' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'title' => 'yes',
		),
	), array(
		'name' => 'left_part_excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_excerpt' => 'yes',
		),
	), array(
		'name' => 'left_part_excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .left-part .elementor-post__excerpt',
	), array(
		'name' => 'left_part_excerpt_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .left-part.elementor-post .elementor-post__excerpt' => 'margin-top: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'show_excerpt' => 'yes',
		),
	), array(
		'name' => 'right_part_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part-wrapper',
	), array(
		'name' => 'right_part_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part-wrapper',
	), array(
		'name' => 'right_part_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'right_part_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part-wrapper',
	), array(
		'name' => 'right_part_date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data span' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
	), array(
		'name' => 'right_part_date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data span',
	), array(
		'name' => 'right_part_category_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data a' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
	), array(
		'name' => 'right_part_category_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data a' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
	), array(
		'name' => 'right_part_category_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data a',
	), array(
		'name' => 'category_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'right_part_category_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'condition' => array(
			'show_meta' => 'yes',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'right_part_category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__meta-data a',
	), array(
		'name' => 'right_part_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__title' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'title' => 'yes',
		),
	), array(
		'name' => 'right_part_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__title',
	), array(
		'name' => 'right_part_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part.elementor-post .elementor-post__title' => 'margin-top: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'title' => 'yes',
		),
	), array(
		'name' => 'right_part_excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'show_excerpt' => 'yes',
		),
	), array(
		'name' => 'right_part_excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .right-part .elementor-post__excerpt',
	), array(
		'name' => 'right_part_excerpt_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part.elementor-post .elementor-post__excerpt' => 'margin-top: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'show_excerpt' => 'yes',
		),
	), array(
		'name' => 'read_more_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more svg' => 'fill: {{VALUE}} !important;',
		),
		'default' => '',
	), array(
		'name' => 'read_more_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'read_more_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'read_more_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'read_more_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'read_more_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'read_more_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more',
	), array(
		'name' => 'button_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post .elementor-post__read-more' => 'margin-top: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'read_more_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover svg' => 'fill: {{VALUE}} !important;',
		),
	), array(
		'name' => 'read_more_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'read_more_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'read_more_border_border!' => '',
		),
	), array(
		'name' => 'read_more_hover_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-posts-container .elementor-post__read-more:hover',
	), array(
		'name' => 'item_left_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post.left-part .elementor-post__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'wrap_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .right-part-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'item_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post.right-part' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%', 'em' ),
	), array(
		'name' => 'space_between',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__meta-data' => 'margin-bottom: {{SIZE}}{{UNIT}}',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__title' => 'margin-bottom: {{SIZE}}{{UNIT}}',
			'{{WRAPPER}} .elementor-posts-container .elementor-post__excerpt' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'item_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-posts-container .elementor-post__text' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'size_units' => array( 'px', '%' ),
	) );
