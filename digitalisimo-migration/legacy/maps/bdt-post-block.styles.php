<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-post-block` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; selectores muertos podados con scripts/legacy-styles.py --prune
 * (F8; ver tests/fixtures/f8-legacy-styles-dead.json). No editar a mano.
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
			'post_block_icon[value]!' => '',
		),
	), array(
		'name' => 'featured_item_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .featured-part .elementor-post__thumbnail img' => 'height: {{SIZE}}px',
		),
	), array(
		'name' => 'list_space_between',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-genesis .list-part ul li' => 'margin-top: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .skin-genesis .list-part ul li > div' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'featured_title_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .featured-part .elementor-post__title a' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'featured_show_title' => 'yes',
		),
	), array(
		'name' => 'featured_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .featured-part .elementor-post__title a',
	), array(
		'name' => 'featured_date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .featured-part .elementor-post__meta-data span' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'featured_show_date' => 'yes',
		),
	), array(
		'name' => 'featured_date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .featured-part .elementor-post__meta-data span',
	), array(
		'name' => 'featured_category_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .featured-part .elementor-post__meta-data a' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'featured_show_category' => 'yes',
		),
	), array(
		'name' => 'featured_category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .featured-part .elementor-post__meta-data a',
	), array(
		'name' => 'featured_excerpt_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .featured-part .elementor-post__excerpt' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'featured_show_excerpt' => 'yes',
			'_skin' => array( '', 'genesis' ),
		),
	), array(
		'name' => 'featured_excerpt_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .featured-part .elementor-post__excerpt',
	), array(
		'name' => 'list_layout_image_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .list-part .elementor-post__thumbnail img' => 'width: {{SIZE}}{{UNIT}}; height: auto;',
		),
	), array(
		'name' => 'list_layout_thumbnail_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .list-part .elementor-post__thumbnail img',
	), array(
		'name' => 'list_layout_image_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .list-part .elementor-post__thumbnail img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'list_layout_date_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .list-part .elementor-post__meta-data span' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'list_show_date' => 'yes',
		),
	), array(
		'name' => 'list_layout_date_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .list-part .elementor-post__meta-data span',
	), array(
		'name' => 'list_layout_category_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .list-part .elementor-post__meta-data a' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'list_show_category' => 'yes',
		),
	), array(
		'name' => 'list_layout_category_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .list-part .elementor-post__meta-data a',
	), array(
		'name' => 'read_more_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-post__read-more svg' => 'fill: {{VALUE}} !important;',
		),
	), array(
		'name' => 'read_more_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'read_more_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-post__read-more',
	), array(
		'name' => 'read_more_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'read_more_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-post__read-more',
	), array(
		'name' => 'read_more_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'read_more_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-post__read-more',
	), array(
		'name' => 'read_more_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more:hover' => 'color: {{VALUE}} !important;',
			'{{WRAPPER}} .elementor-post__read-more:hover svg' => 'fill: {{VALUE}} !important;',
		),
	), array(
		'name' => 'read_more_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more:hover' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'read_more_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-post__read-more:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	) );
