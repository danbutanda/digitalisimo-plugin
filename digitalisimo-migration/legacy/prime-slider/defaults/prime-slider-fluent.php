<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-fluent` (bdthemes-prime-slider/modules/fluent/widgets/fluent.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-fluent',
	'plugin' => 'pro',
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
		'show_admin_info' => 'yes',
		'show_avatar' => 'yes',
		'show_category' => 'yes',
		'show_date' => 'yes',
		'show_social_icon' => 'yes',
		'show_thumb_scroller' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_scroll_button' => 'yes',
		'scroll_button_text' => array(
			'__t' => 'Scroll Down',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'section_id' => 'my-header',
		'follow_us_text' => array(
			'__t' => 'Follow Us',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'posts_source' => 'post',
		'posts_limit' => 9,
		'product_show_product_type' => 'all',
		'posts_select_date' => 'anytime',
		'posts_orderby' => 'date',
		'product_orderby' => 'date',
		'posts_order' => 'desc',
		'posts_ignore_sticky_posts' => 'yes',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'finite' => 'yes',
		'animation_of' => array( '.bdt-title-tag' ),
		'overlay' => 'background',
		'blend_type' => 'multiply',
		'first_word_style' => 'yes',
		'avatar_size' => '42',
		'social_icon_tooltip' => 'yes',
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
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-prime-slider-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content' => 'text-align: {{VALUE}};',
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
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_left_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-prime-slider-content' => 'margin-left: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-scroll-down-wrapper' => 'left: calc({{SIZE}}{{UNIT}} + 15px);',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-title-tag a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-title-tag a:hover, {{WRAPPER}} .bdt-prime-slider .bdt-title-tag a:hover .frist-word' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-title-tag',
		),
		'title_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-title-tag',
		),
		'title_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-title-tag',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-main-title' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-title-tag .frist-word' => 'color: {{VALUE}};',
			),
		),
		'first_word_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .frist-word',
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-blog-text' => 'color: {{VALUE}};',
			),
		),
		'excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-blog-text' => 'padding-top: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-blog-text',
		),
		'category_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-ps-category a' => 'color: {{VALUE}}',
			),
		),
		'category_icon_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-ps-category a' => 'background: {{VALUE}}',
			),
		),
		'category_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-ps-category a',
		),
		'category_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-ps-category a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-ps-category a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'category_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-ps-category a',
		),
		'ps_category_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-ps-category a + a' => 'margin-left: {{SIZE}}{{UNIT}};',
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-post-slider-author' => 'margin-right: {{SIZE}}{{UNIT}} !important;',
			),
		),
		'avatar_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-post-slider-author, {{WRAPPER}} .bdt-prime-slider .bdt-post-slider-author img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'date_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-prime-slider-date' => 'color: {{VALUE}}',
			),
		),
		'date_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-fluent .bdt-prime-slider-date',
		),
		'thumb_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-thumbnav-wrap' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'thumb_scroller_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav .bdt-thumb-content h3' => 'color: {{VALUE}};',
			),
		),
		'thumbs_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-thumbnav-wrap' => 'background-color: {{VALUE}};',
			),
		),
		'thumbs_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav' => 'border-top-color: {{VALUE}};',
			),
		),
		'thumbs_item_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav .bdt-thumb-content:before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'thumbs_item_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav .bdt-thumb-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'thumbs_item_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav .bdt-thumb-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'thumb_scroller_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav .bdt-thumb-content h3',
		),
		'thumbs_title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav:hover .bdt-thumb-content h3' => 'color: {{VALUE}};',
			),
		),
		'thumbs_background_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav:hover .bdt-thumb-content:before' => 'background-color: {{VALUE}};',
			),
		),
		'thumbs_title_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav.bdt-active .bdt-thumb-content h3' => 'color: {{VALUE}};',
			),
		),
		'thumb_scroller_item_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-ps-thumbnav.bdt-active .bdt-thumb-content:before' => 'background: {{VALUE}}',
			),
		),
		'social_line_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-fluent.bdt-ps-icon .bdt-prime-slider-content:before',
		),
		'social_line_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent.bdt-ps-icon .bdt-prime-slider-content:before' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon svg' => 'fill: {{VALUE}};',
			),
		),
		'social_icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'margin-bottom: {{SIZE}}{{UNIT}}; margin-top: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_position' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-prime-slider-social-icon' => 'right: {{SIZE}}{{UNIT}};',
			),
		),
		'social_tooltip_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'body:has({{WRAPPER}} .bdt-prime-slider-social-icon a:hover) .bdt-tooltip, body:has({{WRAPPER}} .bdt-prime-slider-social-icon a:hover) .bdt-tooltip .bdt-tooltip-inner' => 'color: {{VALUE}};',
			),
		),
		'social_tooltip_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => 'body:has({{WRAPPER}} .bdt-prime-slider-social-icon a:hover) .bdt-tooltip',
		),
		'social_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'social_icon_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover',
		),
		'icon_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'scroll_down_primary_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-scroll-down-wrapper .bdt-scroll-down-content-wrapper span' => '--primary-border-color: {{VALUE}};',
			),
		),
		'scroll_down_secondary_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-scroll-down-wrapper .bdt-scroll-down-content-wrapper span' => '--secondary-border-color: {{VALUE}};',
			),
		),
		'scroll_down_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-scroll-down-wrapper .bdt-scroll-down-content-wrapper span' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next svg' => 'color: {{VALUE}}',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-prime-slider-next svg, {{WRAPPER}} .bdt-prime-slider-fluent .bdt-prime-slider-previous svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-fluent .bdt-navigation-arrows' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover svg' => 'color: {{VALUE}}',
			),
		),
	),
);
