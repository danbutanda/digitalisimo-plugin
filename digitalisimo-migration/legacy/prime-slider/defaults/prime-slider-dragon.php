<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-dragon` (bdthemes-prime-slider-lite/modules/dragon/widgets/dragon.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-dragon',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'viewport_height' => array(
			'unit' => 'vh',
			'size' => 70,
		),
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_excerpt' => 'yes',
		'show_button_text' => 'yes',
		'show_social_icon' => 'yes',
		'show_navigation_dots' => 'yes',
		'show_blur_effect' => 'yes',
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'finite' => 'yes',
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
		'slide_button_hover_text_color' => '',
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
				'excerpt' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
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
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/img-1.svg',
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
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/img-2.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'Discover your Talents',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'On Elementor',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/img-3.svg',
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
						'__t' => 'Facebook',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.twitter.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'Twitter',
						'd' => 'bdthemes-prime-slider-lite',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.instagram.com/bdthemes/',
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
		'css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-dragon .bdt-ps-dragon-slide-image .bdt-ps-slide-img',
		),
		'button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-wrapper .bdt-prime-slider-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-wrapper .bdt-prime-slider-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-wrapper .bdt-prime-slider-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag a' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-main-title .frist-word' => 'color: {{VALUE}}; -webkit-text-stroke-color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag',
		),
		'prime_slider_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-main-title .bdt-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-sub-title-inner' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-sub-title-inner',
		),
		'prime_slider_sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-sub-title .bdt-sub-title-inner' => 'padding-bottom: {{SIZE}}{{UNIT}};',
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-ps-dragon-button .bdt-ps-button-text' => 'color: {{VALUE}};',
			),
		),
		'slide_button_circle_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-ps-dragon-button .bdt-ps-button-border-circle' => 'border-color: {{VALUE}};',
			),
		),
		'slide_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-dragon .bdt-ps-dragon-button .bdt-ps-button-text',
		),
		'slide_button_hover_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-ps-dragon-button:hover .bdt-ps-button-text' => 'color: {{VALUE}};',
			),
		),
		'slide_button_hover_circle_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-ps-dragon-button:hover .bdt-ps-button-border-circle' => 'border-color: {{VALUE}};',
			),
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-social-icon a' => 'color: {{VALUE}};',
			),
		),
		'social_icon_cirlce_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-social-icon a:before' => 'border-color: {{VALUE}};',
			),
		),
		'social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-social-icon a' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-social-icon a',
		),
		'social_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-prime-slider-social-icon a:hover' => 'color: {{VALUE}};',
			),
		),
		'navigation_number_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-dotnav li:after' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-dotnav li:before' => 'background: {{VALUE}}',
			),
		),
		'navigation_number_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-dotnav li:hover:after' => 'color: {{VALUE}}',
			),
		),
		'navigation_number_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-dotnav li.bdt-active:after' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-dotnav li.bdt-active:before' => 'background: {{VALUE}}',
			),
		),
		'navigation_number_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-dotnav li:after' => 'font-size: {{SIZE}}px',
			),
		),
		'navigation_line_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-dragon .bdt-dotnav li.bdt-active:before' => 'width: {{SIZE}}px',
			),
		),
	),
);
// Valor no literal, sin escribir: slide_button_icon_color selectors: [ '{{WRAPPER}} .bdt-prime-slider-dragon .bdt-ps-dragon-button .bdt-ps-button-arrow, {{WRAP
