<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-pacific` (bdthemes-prime-slider-lite/modules/pacific/widgets/pacific.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-pacific',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'layout_style' => 1,
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 5,
		),
		'item_gap_tablet' => array(
			'size' => 5,
		),
		'item_gap_mobile' => array(
			'size' => 0,
		),
		'primary_thumbnail_size' => 'full',
		'posts_source' => 'post',
		'posts_limit' => 5,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h3',
		'modal_show_title' => 'yes',
		'show_excerpt' => 'yes',
		'excerpt_length' => 30,
		'strip_shortcode' => 'yes',
		'show_category' => 'yes',
		'show_author' => 'yes',
		'meta_separator' => '//',
		'show_date' => 'yes',
		'show_read_more' => 'yes',
		'read_more_text' => array(
			'__t' => 'Read More',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'show_navigation' => 'yes',
		'previous_text' => array(
			'__t' => 'Prev',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'next_text' => array(
			'__t' => 'Next',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'show_pagination' => 'yes',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'centered_slides' => 'yes',
		'loop' => 'yes',
		'speed' => array(
			'size' => 900,
		),
	),
	'repeaters' => array(),
	'styles' => array(
		'item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-item' => 'text-align: {{VALUE}};',
			),
		),
		'item_overlay' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item .bdt-image-wrap:before' => 'background: {{VALUE}};',
			),
		),
		'item_overlay_active' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-item.swiper-slide-active .bdt-image-wrap:before' => 'background: {{VALUE}};',
			),
		),
		'item_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-content-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-title',
		),
		'title_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-title a',
		),
		'meta_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-meta-box, {{WRAPPER}} .bdt-pacific-slider .bdt-meta-box .bdt-author-wrap a' => 'color: {{VALUE}};',
			),
		),
		'meta_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-meta-box .bdt-author-wrap  a:hover' => 'color: {{VALUE}};',
			),
		),
		'meta_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-meta-box .bdt-meta-separator' => 'margin: 0 {{SIZE}}{{UNIT}};',
			),
		),
		'meta_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-meta-box' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'meta_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-meta-box',
		),
		'category_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-category' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-category a' => 'color: {{VALUE}};',
			),
		),
		'category_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-category a',
		),
		'category_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-category a',
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-category a+a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'category_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-category a',
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-category a',
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-category a:hover' => 'color: {{VALUE}};',
			),
		),
		'category_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-category a:hover',
		),
		'category_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-category a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'read_more_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-item .bdt-button' => 'color: {{VALUE}}',
			),
		),
		'read_more_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-item .bdt-button',
		),
		'read_more_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-item .bdt-button:hover' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-pacific-slider .bdt-item .bdt-btn-text::before' => 'border-bottom-color: {{VALUE}}',
			),
		),
		'navigation_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-navigation-wrap' => 'color: {{VALUE}}',
			),
		),
		'navigation_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-navigation-wrap:hover' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-pacific-slider .bdt-n-p-text::before' => 'border-bottom-color: {{VALUE}}',
			),
		),
		'navigation_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-navigation-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .bdt-navigation-wrap',
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .swiper-pagination-bullet' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-pacific-slider .swiper-pagination-bullet:before' => 'border-top-color: {{VALUE}}',
			),
		),
		'pagination_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .swiper-pagination-bullet:not(.swiper-pagination-bullet-active):hover' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-pacific-slider .swiper-pagination-bullet:not(.swiper-pagination-bullet-active):hover:before' => 'border-top-color: {{VALUE}}',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .swiper-pagination-bullet-active' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-pacific-slider .swiper-pagination-bullet-active.swiper-pagination-bullet:before' => 'border-top-color: {{VALUE}}',
			),
		),
		'pagination_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pacific-slider .bdt-pagi-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'pafination_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pacific-slider .swiper-pagination-bullet',
		),
		'modal_cross_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-modal-close-full' => 'color: {{VALUE}};',
			),
		),
		'modal_cross_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-modal-close-full:hover' => 'color: {{VALUE}};',
			),
		),
		'item_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '.bdt-pacific-{{ID}}.bdt-modal-dialog',
		),
		'modal_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-title a' => 'color: {{VALUE}};',
			),
		),
		'modal_title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-title a:hover' => 'color: {{VALUE}};',
			),
		),
		'modal_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-title',
		),
		'modal_title_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'modal_title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-title a',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-text' => 'color: {{VALUE}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-text',
		),
		'modal_image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-background-cover',
		),
		'modal_image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-background-cover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'modal_image_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '.bdt-pacific-{{ID}}.bdt-modal-dialog .bdt-background-cover',
		),
	),
);
