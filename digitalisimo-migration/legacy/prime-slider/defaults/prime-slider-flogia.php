<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-flogia` (bdthemes-prime-slider-lite/modules/flogia/widgets/flogia.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-flogia',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_excerpt' => 'yes',
		'excerpt_length' => 30,
		'strip_shortcode' => 'yes',
		'show_category' => 'yes',
		'show_admin_info' => 'yes',
		'published_by' => 'yes',
		'show_thumbnav' => 'yes',
		'show_navigation_arrows_dots' => 'yes',
		'navigation_position' => 'center-left',
		'posts_source' => 'post',
		'posts_limit' => 5,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'finite' => 'yes',
		'overlay' => 'background',
		'blend_type' => 'multiply',
		'excerpt_width' => array(
			'unit' => 'px',
		),
		'excerpt_width_tablet' => array(
			'unit' => 'px',
		),
		'excerpt_width_mobile' => array(
			'unit' => 'px',
		),
		'avatar_size' => '42',
		'featured_post_alignment' => 'center',
	),
	'repeaters' => array(),
	'styles' => array(
		'viewport_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-slideshow .bdt-slideshow-items' => 'min-height: {{SIZE}}{{UNIT}} !important;',
			),
		),
		'content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-ps-container' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_position' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item' => 'align-items: {{VALUE}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-content' => 'text-align: {{VALUE}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item>.bdt-ps-slide-img' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item>.bdt-ps-slide-img' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item>.bdt-ps-slide-img' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item>.bdt-ps-slide-img' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-slideshow .bdt-overlay-default' => 'background-color: {{VALUE}};',
			),
		),
		'ps_content_innner_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag a:hover, {{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag a:hover span' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag a span' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag a',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag a',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-content .bdt-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-main-title' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-blog-text' => 'color: {{VALUE}};',
			),
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-blog-text',
		),
		'excerpt_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-blog-text' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'prime_slider_excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-blog-text' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'category_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a' => 'color: {{VALUE}}',
			),
		),
		'category_icon_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a' => 'background: {{VALUE}}',
			),
		),
		'category_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a',
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a',
		),
		'ps_category_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category-wrapper' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'ps_category_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a + a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a:hover' => 'color: {{VALUE}}',
			),
		),
		'category_hover_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a:hover' => 'background: {{VALUE}}',
			),
		),
		'category_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'meta_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-meta .bdt-author, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-meta .bdt-author a' => 'color: {{VALUE}}',
			),
		),
		'meta_text_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-meta .bdt-author a:hover' => 'color: {{VALUE}}',
			),
		),
		'meta_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-meta .bdt-author',
		),
		'avatar_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-post-slider-author' => 'margin-right: {{SIZE}}{{UNIT}} !important;',
			),
		),
		'featured_post_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-thumbnav>a span' => 'color: {{VALUE}}',
			),
		),
		'featured_post_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-thumbnav>a span' => 'background-color: {{VALUE}}',
			),
		),
		'featured_post_overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-thumbnav .bdt-thumb-content:before' => 'background: {{VALUE}}',
			),
		),
		'featured_thumbs_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-thumbnav .bdt-thumb-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'featured_thumbs_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-ps-thumbnav>a span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'featured_thumbs_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-ps-thumbnav>a span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'featured_thumbs_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-thumb-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'featured_thumbs_gap' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-thumbnav-scroller .bdt-slider-items' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'featured_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-thumbnav>a span',
		),
		'thumbs_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-ps-thumbnav .bdt-thumb-content' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'thumbs_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-ps-thumbnav .bdt-thumb-content' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'featured_post_title_color_active' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-thumbnav.bdt-active>a span' => 'color: {{VALUE}}',
			),
		),
		'featured_post_background_color_active' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-thumbnav.bdt-active>a span' => 'background-color: {{VALUE}}',
			),
		),
		'featured_post_overlay_color_active' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-thumbnav.bdt-active .bdt-thumb-content:before' => 'background: {{VALUE}}',
			),
		),
		'featured_thumbs_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-thumbnav.bdt-active .bdt-thumb-content',
		),
		'nav_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-navigation-arrows' => 'margin-right: {{SIZE}}{{UNIT}}; margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'nav_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flogia .bdt-navigation-arrows' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next svg' => 'color: {{VALUE}}',
			),
		),
		'dots_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-dotnav li a:before' => 'background: {{VALUE}}',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover svg' => 'color: {{VALUE}}',
			),
		),
		'dots_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-dotnav li:hover a:before' => 'background: {{VALUE}}',
			),
		),
		'dots_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-dotnav li.bdt-active a:before' => 'background: {{VALUE}}',
			),
		),
		'dots_active_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-dotnav li.bdt-active a:after' => 'border-color:{{VALUE}}',
			),
		),
	),
);
