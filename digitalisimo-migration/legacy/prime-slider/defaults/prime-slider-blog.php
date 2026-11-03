<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-blog` (bdthemes-prime-slider-lite/modules/blog/widgets/blog.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-blog',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array( 'coral', 'folio', 'zinest' ),
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
		'show_button_text' => 'yes',
		'button_text' => array(
			'__t' => 'Read More',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'show_social_icon' => 'yes',
		'show_scroll_button' => 'yes',
		'show_category' => 'yes',
		'show_meta' => 'yes',
		'show_author' => 'yes',
		'show_admin_info' => 'yes',
		'show_date' => 'yes',
		'show_comments' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'posts_source' => 'post',
		'posts_limit' => 3,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'show_featured_post' => 'yes',
		'follow_us_text' => array(
			'__t' => 'Follow Us',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'scroll_button_text' => array(
			'__t' => 'Scroll Down',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'section_id' => 'my-header',
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
		'social_icon_tooltip' => 'yes',
		'folio_glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
	),
	'repeaters' => array(
		'social_link_list' => array(
			'fields' => array(),
			'rows' => array( array(
					'social_icon_link' => array(
						'url' => 'http://www.facebook.com/bdthemes/',
					),
					'social_icon' => array(
						'value' => 'fab fa-facebook-f',
						'library' => 'fa-brands',
					),
					'social_link_title' => array(
						'__t' => 'Facebook',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.twitter.com/bdthemes/',
					),
					'social_icon' => array(
						'value' => 'fab fa-twitter',
						'library' => 'fa-brands',
					),
					'social_link_title' => array(
						'__t' => 'Twitter',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.instagram.com/bdthemes/',
					),
					'social_icon' => array(
						'value' => 'fab fa-instagram',
						'library' => 'fa-brands',
					),
					'social_link_title' => array(
						'__t' => 'Instagram',
						'd' => 'bdthemes-prime-slider-lite',
					),
				) ),
		),
	),
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
				'{{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-ps-blog-container' => 'max-width: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-zinest-container' => 'max-width: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-slideshow-content-wrapper' => 'max-width: {{SIZE}}{{UNIT}} !important;',
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-container' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item .bdt-ps-slide-img' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item .bdt-ps-slide-img' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item .bdt-ps-slide-img' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item .bdt-ps-slide-img' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content *' => 'text-align: {{VALUE}} !important;',
			),
		),
		'ps_meta_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-meta .bdt-ps-meta-item' => 'justify-content: {{VALUE}};',
			),
		),
		'overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-slideshow .bdt-overlay-default' => 'background-color: {{VALUE}};',
			),
		),
		'content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-ps-blog-container' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-zinest-container' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-slideshow-content-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-container' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag a' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag a span' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag a',
		),
		'title_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_style_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-prime-slider-desc .bdt-main-title:before, {{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-prime-slider-desc .bdt-main-title:after' => 'background: {{VALUE}};',
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
		'slide_button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn svg *' => 'stroke: {{VALUE}};',
			),
		),
		'slide_button_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'background-color: {{VALUE}};',
			),
		),
		'slide_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn',
		),
		'slide_button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_button_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_button_text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn',
		),
		'slide_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn',
		),
		'slide_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover svg *' => 'stroke: {{VALUE}};',
			),
		),
		'slide_button_background_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:before' => 'background-color: {{VALUE}};',
			),
		),
		'slide_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon svg' => 'fill: {{VALUE}};',
			),
		),
		'social_icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a',
		),
		'social_icon_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a',
		),
		'social_icon_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a',
		),
		'social_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-social-icon' => 'gap: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-social-icon' => 'gap: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-social-icon' => 'gap: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-social-icon .bdt-social-icon-item-wrap' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'folio_social_icon_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon h3' => 'color: {{VALUE}};',
			),
		),
		'social_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-social-icon h3',
		),
		'social_tooltip_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'body:has({{WRAPPER}} .bdt-social-icon a:hover) .bdt-tooltip, body:has({{WRAPPER}} .bdt-social-icon a:hover) .bdt-tooltip .bdt-tooltip-inner' => 'color: {{VALUE}};',
			),
		),
		'social_tooltip_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => 'body:has({{WRAPPER}} .bdt-social-icon a:hover) .bdt-tooltip',
		),
		'social_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:hover i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'social_icon_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:hover, {{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-social-icon a::before',
		),
		'icon_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'scroll_button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span svg *' => 'fill: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-scroll-down .bdt-scroll-icon' => 'border-color: {{VALUE}};',
			),
		),
		'scroll_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span',
		),
		'scroll_button_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-scroll-down .bdt-scroll-icon svg' => 'margin-bottom: calc(25px + {{SIZE}}{{UNIT}});',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-scroll-down .bdt-scroll-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'scroll_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down:hover span' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down:hover span svg *' => 'fill: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-scroll-down:hover .bdt-scroll-icon' => 'border-color: {{VALUE}};',
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
		'category_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a',
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a',
		),
		'category_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a:hover' => 'color: {{VALUE}};',
			),
		),
		'category_background_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a:hover' => 'background-color: {{VALUE}};',
			),
		),
		'category_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-category a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'meta_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'folio_glassmorphism_blur_level' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-ps-meta-wrap' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
			),
		),
		'meta_content_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-ps-meta-wrap',
		),
		'meta_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon svg' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon' => 'border-color: {{VALUE}}',
			),
		),
		'meta_icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon, {{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-post-slider-author',
		),
		'meta_icon_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon, {{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-post-slider-author',
		),
		'meta_icon_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon, {{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-post-slider-author' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'meta_icon_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon, {{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-post-slider-author',
		),
		'meta_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-post-slider-author img' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
			),
		),
		'meta_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-post-slider-author' => 'margin-right: {{SIZE}}{{UNIT}} !important;',
			),
		),
		'meta_item_gap' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-ps-meta-wrap' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'meta_wrap_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-ps-meta-content' => 'padding: 0 {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-ps-meta-wrap' => 'padding: 0 {{SIZE}}{{UNIT}};',
			),
		),
		'meta_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-text *' => 'color: {{VALUE}}',
			),
		),
		'admin_meta_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-prime-slider-meta *' => 'color: {{VALUE}}',
			),
		),
		'meta_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-ps-meta .bdt-meta-text *, {{WRAPPER}} .bdt-prime-slider-skin-folio .bdt-prime-slider-meta *',
		),
		'glassmorphism_blur_level' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-blog-featured' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
			),
		),
		'featured_post_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-blog-featured',
		),
		'featured_post_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-featured .bdt-ps-content .bdt-ps-title a' => 'color: {{VALUE}}',
			),
		),
		'featured_post_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-featured .bdt-ps-content .bdt-ps-title a',
		),
		'featured_post_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-featured .bdt-ps-content .bdt-ps-title' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'featured_post_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-featured .bdt-ps-content .bdt-ps-desc *' => 'color: {{VALUE}}',
			),
		),
		'featured_post_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-featured .bdt-ps-content .bdt-ps-desc *',
		),
		'featured_post_background_hover_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-blog-featured:hover',
		),
		'featured_post_title__hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-featured:hover .bdt-ps-content .bdt-ps-title a' => 'color: {{VALUE}}',
			),
		),
		'featured_post_text_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-ps-featured:hover .bdt-ps-content .bdt-ps-desc *' => 'color: {{VALUE}}',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next svg' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous',
		),
		'arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous',
		),
		'arrows_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'inactive_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-dotnav li:not(.bdt-active) a:before' => 'background-color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-dotnav li:not(.bdt-active) a:before' => 'background-color: {{VALUE}}',
			),
		),
		'active_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li.bdt-active a' => 'border-color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li.bdt-active a:after' => 'border-color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li.bdt-active a:before' => 'background-color: {{VALUE}}',
			),
		),
		'dot_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-ps-dotnav span, {{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-ps-dotnav li a' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-ps-dotnav span:before' => 'background: {{VALUE}}',
			),
		),
		'active_dot_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-counter:after' => 'color: {{VALUE}}',
			),
		),
		'dots_x_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-slideshow-nav' => 'right: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover svg' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '
                    {{WRAPPER}} .bdt-prime-slider-skin-blog .bdt-slidenav::before,
                    {{WRAPPER}} .bdt-prime-slider-skin-zinest .bdt-slidenav::before,
                    {{WRAPPER}} .bdt-prime-slider-skin-coral .bdt-slidenav::before',
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover' => 'border-color: {{VALUE}};',
			),
		),
	),
);
