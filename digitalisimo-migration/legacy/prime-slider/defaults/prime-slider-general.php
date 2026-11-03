<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-general` (bdthemes-prime-slider-lite/modules/general/widgets/general.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-general',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array( 'crelly', 'meteor', 'slide' ),
	'controls' => array(
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_button_text' => 'yes',
		'show_button_icon' => 'yes',
		'show_excerpt' => 'yes',
		'show_otherview' => 'yes',
		'show_share_us' => 'yes',
		'show_social_icon' => 'yes',
		'show_scroll_button' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'general_follow_us_text' => array(
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
		'draggable' => 'yes',
		'slider_animations' => 'fade',
		'animation_parallax' => 'yes',
		'overlay' => 'none',
		'blend_type' => 'multiply',
		'shape_background_color' => '',
		'secondary_shape_color' => '',
		'excerpt_width' => array(
			'unit' => '%',
		),
		'excerpt_width_tablet' => array(
			'unit' => '%',
		),
		'excerpt_width_mobile' => array(
			'unit' => '%',
		),
		'slide_button_text_color' => '',
		'social_icon_tooltip' => 'yes',
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
				'background' => 'color',
				'color' => '#193d4c',
				'image' => array(
					'url' => $placeholder,
				),
				'video_link' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4',
				'youtube_link' => 'https://youtu.be/YE7VzlLtp-4',
				'title_link' => array(
					'url' => '',
				),
				'excerpt' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
				),
			),
			'rows' => array( array(
					'title' => array(
						'__t' => 'Massive',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'sub_title' => array(
						'__t' => 'Sub Title',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'title' => array(
						'__t' => 'Vibrant',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'sub_title' => array(
						'__t' => 'Sub Title',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'title' => array(
						'__t' => 'Wallow',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'sub_title' => array(
						'__t' => 'Sub Title',
						'd' => 'bdthemes-prime-slider-lite',
					),
				) ),
		),
		'social_link_list' => array(
			'fields' => array(
				'social_icon' => array(
					'value' => 'fab fa-facebook-f',
					'library' => 'fa-brands',
				),
			),
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-wrapper' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content *' => 'text-align: {{VALUE}} !important;',
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
		'overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-slideshow .bdt-overlay-default' => 'background-color: {{VALUE}};',
			),
		),
		'shape_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-slide-shape' => 'background: {{VALUE}};',
			),
		),
		'secondary_shape_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slide:after' => 'background: {{VALUE}};',
			),
		),
		'content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag' => 'color: {{VALUE}};',
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
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag',
		),
		'title_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag',
		),
		'title_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag',
		),
		'title_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-ps-sub-title' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-ps-sub-title',
		),
		'prime_slider_sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-sub-title .bdt-ps-sub-title' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'color: {{VALUE}};',
			),
		),
		'excerpt_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-meteor .bdt-prime-slider-footer-content .bdt-social-background, {{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-slide-featured' => 'background-color: {{VALUE}}',
			),
		),
		'excerpt_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt',
		),
		'excerpt_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'excerpt_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'excerpt_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'excerpt_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt-content h3' => 'color: {{VALUE}};',
			),
		),
		'excerpt_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-slider-excerpt-content' => 'margin-top: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_style_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-slider-excerpt:before' => 'background: {{VALUE}}',
			),
		),
		'excerpt_style_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-slide-featured .bdt-slider-excerpt' => 'border-color: {{VALUE}}',
			),
		),
		'excerpt_overview_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt-content h3',
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt',
		),
		'excerpt_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt p' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'prime_slider_excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'button_vertical_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-prime-slider-arrow-button' => 'align-items: {{VALUE}};',
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
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn, {{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-slide-btn:before',
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
		'slide_button_margin' => array(
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
		'slide_button_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn svg *' => 'stroke: {{VALUE}} !important;',
			),
		),
		'slide_button_icon_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn .bdt-slide-btn-icon',
		),
		'slide_icon_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn .bdt-slide-btn-icon',
		),
		'slide_button_icon_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn .bdt-slide-btn-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_button_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn .bdt-slide-btn-icon svg' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_btn_icon_vertical_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn .bdt-slide-btn-icon' => 'transform: translateY({{SIZE}}{{UNIT}});',
			),
		),
		'slide_btn_icon_horizontal_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn .bdt-slide-btn-icon' => 'transform: translate({{SIZE}}{{UNIT}}, {{slide_btn_icon_vertical_spacing.SIZE}}{{slide_btn_icon_vertical_spacing.UNIT}});',
			),
		),
		'slide_button_icon_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn .bdt-slide-btn-icon',
		),
		'slide_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover svg *' => 'stroke: {{VALUE}};',
			),
		),
		'slide_button_custom_bg_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-slide-btn:before' => 'background: {{VALUE}};',
			),
		),
		'slide_button_background_hover_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover',
		),
		'slide_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'slide_button_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover svg *' => 'stroke: {{VALUE}} !important;',
			),
		),
		'slide_button_icon_background_hover_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover .bdt-slide-btn-icon',
		),
		'slide_button_icon_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover .bdt-slide-btn-icon' => 'border-color: {{VALUE}};',
			),
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon svg' => 'fill: {{VALUE}};',
			),
		),
		'social_icon_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon h3' => 'color: {{VALUE}};',
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
		'skin_social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_left_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon' => 'left: {{SIZE}}{{UNIT}};',
			),
		),
		'social_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-meteor .bdt-prime-slider-footer-content .bdt-social-bg-color' => 'background-color: {{VALUE}}',
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover' => 'color: {{VALUE}};',
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
		'scroll_button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span svg *' => 'fill: {{VALUE}};',
			),
		),
		'scroll_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span',
		),
		'scroll_down_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down' => 'right: {{SIZE}}{{UNIT}};',
			),
		),
		'scroll_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down:hover span' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down:hover span svg *' => 'fill: {{VALUE}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next svg' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:before, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:before' => 'background: {{VALUE}}',
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
		'arrows_btn_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-general .bdt-slideshow-nav li a, {{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-dotnav li a' => 'background: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li a:before' => 'background: {{VALUE}}',
			),
		),
		'active_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-slideshow-nav li a:before' => 'border-color: {{VALUE}}',
				'{{WRAPPER}} .bdt-dotnav li.bdt-active a' => 'background: {{VALUE}}',
			),
		),
		'meteor_active_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-meteor .bdt-dotnav li.bdt-active a, {{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-dotnav li.bdt-active a:after' => 'border-color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li.bdt-active a:before' => 'background: {{VALUE}}',
			),
		),
		'dot_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li a' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'dots_vertical_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-nav' => 'transform: translateY(-{{SIZE}}px);',
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-dotnav' => 'transform: translateY(-{{SIZE}}px);',
			),
		),
		'navigation_dots_radius' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-dotnav li a' => 'border-radius: {{SIZE}}px;',
			),
		),
		'navigation_dots_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-general .bdt-slideshow-nav li' => 'margin-right: {{SIZE}}px;',
			),
		),
		'navigation_dots_h_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-general .bdt-slideshow-nav' => 'left: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-dotnav' => 'margin-left: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-prime-slider-skin-meteor .bdt-dotnav' => 'margin-right: {{SIZE}}px;',
			),
		),
		'navigation_dots_v_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-nav' => 'margin-bottom: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-dotnav' => 'margin-bottom: {{SIZE}}px;',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover svg' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:before, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:before' => 'background: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover',
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover' => 'border-color: {{VALUE}};',
			),
		),
		'active_dot_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slide .bdt-dotnav li:after, {{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-ps-counternav span, {{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-ps-counternav li a' => 'color: {{VALUE}}',
			),
		),
		'active_dot_number_color_skin' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-ps-counternav li.bdt-active a' => 'color: {{VALUE}}',
			),
		),
		'seperator_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-crelly .bdt-ps-counternav:before' => 'background-color: {{VALUE}}',
			),
		),
	),
);
