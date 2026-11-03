<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-isolate` (bdthemes-prime-slider-lite/modules/isolate/widgets/isolate.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-isolate',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array( 'locate', 'slice' ),
	'controls' => array(
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_button_text' => 'yes',
		'show_excerpt' => 'yes',
		'show_play_button' => 'yes',
		'show_social_icon' => 'yes',
		'show_scroll_button' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'content_position' => 'inherit',
		'content_column_position' => 'column',
		'thumbnail_size_size' => 'full',
		'image_match_height_desktop' => 'yes',
		'image_match_height_tablet' => 'yes',
		'image_match_height_mobile' => 'yes',
		'scroll_button_text' => array(
			'__t' => 'Scroll Down',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'section_id' => 'my-header',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'finite' => 'yes',
		'animation_parallax' => 'yes',
		'overlay' => 'none',
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
		'slide_button_text_color' => '',
		'play_button_position' => 'center',
		'fancy_animation' => 'shadow-pulse',
		'social_icon_tooltip' => 'yes',
		'arrows_vertical_offset' => array(
			'size' => 0,
		),
		'arrows_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'arrows_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'arrows_horizontal_offset' => array(
			'size' => 0,
		),
		'arrows_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'arrows_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'dots_position' => 'right',
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
				'image_link_type' => 'youtube',
				'image_link_video' => array(
					'url' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4',
				),
				'lightbox_link' => array(
					'url' => 'https://www.youtube.com/watch?v=YE7VzlLtp-4',
				),
				'image_link_vimeo' => array(
					'url' => 'https://vimeo.com/1084537',
				),
				'image_link_google_map' => array(
					'url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d4740.819266853735!2d9.99008871708242!3d53.550454675412404!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x3f9d24afe84a0263!2sRathaus!5e0!3m2!1sde!2sde!4v1499675200938',
				),
				'title_link' => array(
					'url' => '',
				),
				'excerpt' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
				),
			),
			'rows' => array( array(
					'sub_title' => array(
						'__t' => 'Sub Title',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Massive',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Sub Title',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Vibrant',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.png',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Sub Title',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Wallow',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
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
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc' => '{{VALUE}};',
			),
		),
		'overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-slideshow .bdt-overlay-default' => 'background-color: {{VALUE}};',
			),
		),
		'slider_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-locate, {{WRAPPER}} .bdt-prime-slider-skin-isolate, {{WRAPPER}} .bdt-prime-slider-skin-locate .bdt-prime-slider-desc' => 'background: {{VALUE}};',
			),
		),
		'slider_background_before_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-locate:before' => 'background: {{VALUE}};',
			),
		),
		'ps_slice_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-skin-slice' => 'background: {{VALUE}};',
			),
		),
		'ps_slice_before_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-skin-slice:before' => 'background: {{VALUE}};',
			),
		),
		'ps_slice_after_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-skin-slice:after' => 'background: {{VALUE}};',
			),
		),
		'slider_wrap_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-slideshow' => 'padding: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-scroll-down-wrapper' => 'left: {{SIZE}}{{UNIT}}; bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slideshow-item.bdt-active .bdt-slide-overlay:before' => 'background-color: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag a' => 'color: {{VALUE}};',
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
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag a',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag a',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'prime_slider_left_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slice .bdt-prime-slider-desc .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider-skin-slice .bdt-prime-slider-desc .bdt-sub-title-tag' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'first_word_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .frist-word' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
			),
		),
		'first_word_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .frist-word:before' => 'background: {{VALUE}};',
			),
		),
		'first_word_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .frist-word',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-sub-title-tag' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-sub-title-tag',
		),
		'prime_slider_sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-sub-title .bdt-sub-title-tag, {{WRAPPER}} .bdt-prime-slider-skin-slice .bdt-prime-slider-desc .bdt-sub-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
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
				'{{WRAPPER}} .bdt-prime-slider-skin-slice .bdt-slide-text-btn-area' => 'background: {{VALUE}};',
			),
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'prime_slider_excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_img_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-overlay',
		),
		'slide_image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-overlay',
		),
		'slide_image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-overlay' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_image_css_filter' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-overlay img',
		),
		'slice_image_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slice .bdt-slideshow-item .bdt-slide-overlay img' => 'width: {{SIZE}}%;',
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'background: {{VALUE}};',
			),
		),
		'slice_skin_button_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn',
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
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-slide-btn:before, {{WRAPPER}} .bdt-prime-slider-skin-locate .bdt-slide-btn:hover' => 'background-color: {{VALUE}};',
			),
		),
		'slice_skin_button_hover_background_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-skin-slice .bdt-slide-btn::before',
		),
		'slide_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'play_button_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button' => '{{VALUE}}',
			),
		),
		'fancy_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a:before, {{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a:after' => 'border-color: {{VALUE}};',
			),
		),
		'button_shadow_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a' => '--box-shadow-color: {{VALUE}};',
			),
		),
		'slide_play_button_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a svg' => 'fill: {{VALUE}};',
			),
		),
		'slide_play_button_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a' => 'background: {{VALUE}};',
			),
		),
		'slide_play_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a',
		),
		'slide_play_button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a, {{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-slide-play-button.bdt-line-bounce a:before, {{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-slide-play-button.bdt-line-bounce a:after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_play_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_play_button_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_play_button_font_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_play_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a',
		),
		'slide_play_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'play_btn_hover_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a:hover' => 'background: {{VALUE}};',
			),
		),
		'slide_play_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-play-button a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'social_icon_sec_bg_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon' => 'background-color: {{VALUE}};',
			),
		),
		'social_icon_line_bg_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-slice .bdt-prime-slider-social-icon a:before' => 'background: {{VALUE}};',
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
		'scroll_button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span' => 'color: {{VALUE}};',
			),
		),
		'scroll_button_text_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down-wrapper .bdt-scroll-icon' => 'background: {{VALUE}};',
			),
		),
		'scroll_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down-wrapper .bdt-scroll-icon',
		),
		'scroll_button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down-wrapper .bdt-scroll-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'scroll_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down-wrapper .bdt-scroll-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'scroll_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down span',
		),
		'scroll_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-down:hover span' => 'color: {{VALUE}};',
			),
		),
		'scroll_button_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-scroll-icon::before' => 'background: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-locate .bdt-scroll-down-wrapper:hover .bdt-scroll-icon' => 'background: {{VALUE}};',
			),
		),
		'scroll_button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover' => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-locate .bdt-scroll-down-wrapper:hover .bdt-scroll-icon' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous i, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next i, {{WRAPPER}} .bdt-prime-slider-skin-locate .bdt-prime-slider-previous, {{WRAPPER}} .bdt-prime-slider-skin-locate .bdt-prime-slider-next' => 'color: {{VALUE}}',
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
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_size' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous',
		),
		'navigation_arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-prime-slider-previous' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-navigation-arrows' => 'margin: -{{SIZE}}{{UNIT}} 0 0;',
			),
		),
		'arrows_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--ps-isolate-arrows-v-offset: {{SIZE}}px;',
			),
		),
		'arrows_horizontal_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--ps-isolate-arrows-h-offset: {{SIZE}}px;',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover i, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover i' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:before, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:before',
		),
		'locate_arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover,
				{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover',
		),
		'arrows_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover' => 'border-color: {{VALUE}};',
			),
		),
		'navi_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li a:after' => 'background: {{VALUE}}',
			),
		),
		'active_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav li:hover a:after, {{WRAPPER}} .bdt-dotnav li.bdt-active a:after' => 'background: {{VALUE}}',
			),
		),
		'border_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-dotnav li a:before' => 'border-color: {{VALUE}}',
			),
		),
		'active_dot_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-dotnav li a, {{WRAPPER}} .bdt-prime-slider .bdt-ps-dotnav span' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-ps-dotnav span:before' => 'background: {{VALUE}}',
			),
		),
		'dots_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-dotnav li a' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-dotnav li a:before' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-dotnav li a:after' => 'width: calc({{SIZE}}{{UNIT}} / 4); height: calc({{SIZE}}{{UNIT}} / 4);',
			),
		),
		'dots_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-dotnav li a:before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-prime-slider-skin-isolate .bdt-dotnav li a:after' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'dots_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav' => 'margin: 0 {{SIZE}}{{UNIT}};',
			),
		),
		'dots_position' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-dotnav' => '{{VALUE}};',
			),
		),
	),
);
