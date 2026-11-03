<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-pagepiling` (bdthemes-prime-slider-lite/modules/pagepiling/widgets/pagepiling.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-pagepiling',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_button_text' => 'yes',
		'show_excerpt' => 'yes',
		'show_play_button' => 'yes',
		'show_social_icon' => 'yes',
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'social_main_title' => array(
			'__t' => 'Follow Us',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'scrollingSpeed' => array(
			'size' => 700,
		),
		'navigation_position' => 'left',
		'loopBottom' => 'yes',
		'loopTop' => 'yes',
		'autoplay_duration' => array(
			'size' => 1000,
		),
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'title_link' => array(
					'url' => '',
				),
				'slide_button_text' => array(
					'__t' => 'Read More',
					'd' => 'bdthemes-prime-slider-lite',
				),
				'button_link' => array(
					'url' => '#',
				),
				'excerpt' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
				),
				'slide_image' => array(
					'url' => $placeholder,
				),
				'lightbox_link' => array(
					'url' => 'https://www.youtube.com/watch?v=YE7VzlLtp-4',
				),
			),
			'rows' => array( array(
					'sub_title' => array(
						'__t' => 'Addons For Elementor',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Prime Slider',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-8.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Addons For Elementor',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Creative Layout',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Discover your Talents',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'On Prime Slider',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.png',
					),
				) ),
		),
		'social_link_list' => array(
			'fields' => array(),
			'rows' => array( array(
					'social_icon_link' => array(
						'url' => 'http://www.facebook.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'Fb.',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.twitter.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'Tw.',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.instagram.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'In.',
						'd' => 'bdthemes-prime-slider-lite',
					),
				) ),
		),
	),
	'styles' => array(
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .pp-section' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .pp-section' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .pp-section' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .pp-section' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_min_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_text_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content' => 'text-align: {{VALUE}};',
			),
		),
		'overlay_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-ps-overlay:before',
		),
		'content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag .frist-word' => 'color: {{VALUE}};',
			),
		),
		'text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-title-tag',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag',
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-title-tag',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-sub-title .bdt-sub-title-tag' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-sub-title .bdt-sub-title-tag',
		),
		'prime_slider_sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-sub-title .bdt-sub-title-tag' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slider-excerpt' => 'color: {{VALUE}};',
			),
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slider-excerpt',
		),
		'prime_slider_excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slider-excerpt' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'readmore_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn .bdt-button-text' => 'color: {{VALUE}};',
			),
		),
		'readmore_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn:hover .bdt-button-circle .icon.arrow' => 'background: {{VALUE}};',
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn .bdt-button-circle .icon.arrow:before' => 'border-top-color: {{VALUE}}; border-right-color: {{VALUE}}',
			),
		),
		'readmore_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn .bdt-button-circle',
		),
		'readmore_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn .bdt-button-circle',
		),
		'readmore_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn .bdt-button-circle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'readmore_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn .bdt-button-text' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'readmore_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn .bdt-button-text',
		),
		'readmore_hover_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn:hover .bdt-button-text' => 'color: {{VALUE}};',
			),
		),
		'readmore_hover_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn:hover .bdt-button-circle .icon.arrow' => 'background: {{VALUE}};',
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn:hover .bdt-button-circle .icon.arrow:before' => 'border-top-color: {{VALUE}}; border-right-color: {{VALUE}}',
			),
		),
		'readmore_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn:hover .bdt-button-circle',
		),
		'readmore_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-slide-btn:hover .bdt-button-circle' => 'border-color: {{VALUE}};',
			),
		),
		'social_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon a, {{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon h3' => 'color: {{VALUE}};',
			),
		),
		'social_divider_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon a:before, {{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon h3:before' => 'background: {{VALUE}};',
			),
		),
		'social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon a' => 'margin-right: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon h3' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'social_horizontal_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon' => 'right: {{SIZE}}{{UNIT}};',
			),
		),
		'social_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon a, {{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon h3',
		),
		'social_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-social-icon a:hover' => 'color: {{VALUE}};',
			),
		),
		'slide_play_button_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a svg' => 'fill: {{VALUE}};',
			),
		),
		'slide_play_button_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a' => 'background: {{VALUE}};',
			),
		),
		'slide_play_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a',
		),
		'slide_play_button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_play_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a',
		),
		'slide_play_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'play_btn_hover_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a:hover' => 'background: {{VALUE}};',
			),
		),
		'slide_play_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider .bdt-prime-slider-content .bdt-slide-play-button a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'slide_navigation_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider #pp-nav li a, {{WRAPPER}} .bdt-pagepiling-slider .pp-slidesNav li a' => 'background: {{VALUE}};',
			),
		),
		'slide_navigation_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider #pp-nav li a.active, {{WRAPPER}} .bdt-pagepiling-slider .pp-slidesNav li a.active' => 'background: {{VALUE}};',
			),
		),
		'slide_navigation_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider #pp-nav li a, {{WRAPPER}} .bdt-pagepiling-slider .pp-slidesNav li a' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_navigation_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider #pp-nav li a, {{WRAPPER}} .bdt-pagepiling-slider .pp-slidesNav li a' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_navigation_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider #pp-nav li a, {{WRAPPER}} .bdt-pagepiling-slider .pp-slidesNav li a' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_navigation_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider #pp-nav li a, {{WRAPPER}} .bdt-pagepiling-slider .pp-slidesNav li a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_navigation_left_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider #pp-nav.left' => 'left: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_navigation_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-pagepiling-slider.bdt-ps-navigation-bottom #pp-nav.left' => 'bottom: -{{SIZE}}{{UNIT}};',
			),
		),
	),
);
