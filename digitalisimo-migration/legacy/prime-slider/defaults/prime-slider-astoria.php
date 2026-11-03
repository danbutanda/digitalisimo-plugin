<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-astoria` (bdthemes-prime-slider/modules/astoria/widgets/astoria.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-astoria',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_text' => 'yes',
		'readmore_type' => 'static',
		'show_social_icon' => 'yes',
		'show_scroll_button' => 'yes',
		'show_navigation_arrows' => 'yes',
		'autoplay_interval' => 5000,
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'follow_us_text' => array(
			'__t' => 'Follow Us',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'scroll_button_text' => array(
			'__t' => 'Scroll Down',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'section_id' => 'my-header',
		'overlay_type' => 'background',
		'overlay_color_background' => 'classic',
		'overlay_color_color' => 'rgba(3, 4, 16, 0.3)',
		'blend_type' => 'multiply',
		'scroll_down_text_color' => '',
		'scroll_down_hover_text_color' => '',
		'nav_arrows_icon' => '2',
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'slide_button_text' => array(
					'__t' => 'Read More',
					'd' => 'bdthemes-prime-slider-lite',
				),
				'button_link' => array(
					'url' => '#',
				),
				'image' => array(
					'url' => $placeholder,
				),
				'title_link' => array(
					'url' => '',
				),
				'text' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
				),
			),
			'rows' => array( array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Massive',
						'd' => 'bdthemes-prime-slider',
					),
					'text' => array(
						'__t' => 'Prime Slider Addons for Elementor is a page builder extension that allows you to build sliders with drag and drop. It lets you create an amazing slider without touching no code at all!',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Vibrant',
						'd' => 'bdthemes-prime-slider',
					),
					'text' => array(
						'__t' => 'Astoria slider is a premium plugin for Elementor that helps you to create awesome sliders for your website. It comes with the perfect design and drag & drop builder tools.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Explore',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Wallow',
						'd' => 'bdthemes-prime-slider',
					),
					'text' => array(
						'__t' => 'The Astoria Slider is a creative slider that helps you to design a modern website. With the help of this slider, you can create a unique and memorable experience for your users.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				) ),
		),
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
		'slider_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-content' => 'text-align: {{VALUE}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slide-img' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slide-img' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slide-img' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slide-img' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'overlay_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-slide-img:before',
		),
		'blend_type' => array(
			'type' => 'select',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slide-img:before' => 'mix-blend-mode: {{VALUE}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'shape_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .shape' => 'fill: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-title, {{WRAPPER}} .bdt-astoria-slider .bdt-title a, {{WRAPPER}} .bdt-astoria-slider .bdt-title span' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-title:hover, {{WRAPPER}} .bdt-astoria-slider .bdt-title a:hover, {{WRAPPER}} .bdt-astoria-slider .bdt-title a:hover span' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
			),
		),
		'title_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-title',
		),
		'title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-title',
		),
		'first_word_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-title .frist-word' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
			),
		),
		'first_word_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-title .frist-word',
		),
		'title_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-title' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'stroke_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-title, {{WRAPPER}} .bdt-astoria-slider .bdt-title a, {{WRAPPER}} .bdt-astoria-slider .bdt-title .frist-word' => '-webkit-text-stroke-width: {{SIZE}}px;',
			),
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-sub-title' => 'color: {{VALUE}};',
			),
		),
		'sub_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-sub-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-sub-title',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-text' => 'color: {{VALUE}};',
			),
		),
		'text_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-text' => 'max-width: {{SIZE}}px;',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-text',
		),
		'button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore' => 'color: {{VALUE}};',
			),
		),
		'button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore:before',
		),
		'button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore',
		),
		'button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore',
		),
		'button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore',
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore:hover' => 'color: {{VALUE}};',
			),
		),
		'button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore:hover',
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore:hover' => 'border-color: {{VALUE}};',
			),
		),
		'button_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-static-readmore:hover',
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon svg *' => 'fill: {{VALUE}};',
			),
		),
		'social_icon_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:before',
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon' => 'grid-gap: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_horizontal_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon' => 'left: {{SIZE}}{{UNIT}};',
			),
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:hover svg *' => 'fill: {{VALUE}};',
			),
		),
		'social_icon_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:hover',
		),
		'icon_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-social-icon a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'scroll_down_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down .bdt-ps-button-text' => 'color: {{VALUE}};',
			),
		),
		'scroll_down_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down .bdt-ps-button-arrow, {{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down .bdt-ps-button-small-circle' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down .bdt-ps-button-arrow:after' => 'border-left-color: {{VALUE}};',
			),
		),
		'scroll_down_circle_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down .bdt-ps-button-border-circle' => 'border-color: {{VALUE}};',
			),
		),
		'scroll_down_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down .bdt-ps-button-text',
		),
		'scroll_down_hover_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down:hover .bdt-ps-button-text' => 'color: {{VALUE}};',
			),
		),
		'scroll_down_hover_circle_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-ps-scroll-down:hover .bdt-ps-button-border-circle' => 'border-color: {{VALUE}};',
			),
		),
		'arrow_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'navigation_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-wrap' => 'grid-gap: {{SIZE}}px;',
			),
		),
		'navigation_horizontal_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-wrap' => 'right: {{SIZE}}px;',
			),
		),
		'navigation_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-wrap' => 'bottom: {{SIZE}}px;',
			),
		),
		'navigation_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item' => 'color: {{VALUE}};',
			),
		),
		'navigation_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item:before',
		),
		'navigation_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item',
		),
		'navigation_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item',
		),
		'navigation_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item:hover' => 'color: {{VALUE}};',
			),
		),
		'navigation_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item:hover',
		),
		'navigation_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item:hover' => 'border-color: {{VALUE}};',
			),
		),
		'navigation_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-astoria-slider .bdt-slidenav-item:hover',
		),
	),
);
