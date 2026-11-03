<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-sequester` (bdthemes-prime-slider-lite/modules/sequester/widgets/sequester.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-sequester',
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
		'show_button_text' => 'yes',
		'show_excerpt' => 'yes',
		'show_social_icon' => 'yes',
		'show_navigation_arrows' => 'yes',
		'show_navigation_dots' => 'yes',
		'thumbnail_size_size' => 'full',
		'show_image_match_height' => 'yes',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'finite' => 'yes',
		'overlay_opacity' => array(
			'size' => 0.1,
		),
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
						'__t' => 'This is a Label',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sequester Slide One',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/svg-img/item-01.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'This is a Label',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sequester Slide Two',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/svg-img/item-02.svg',
					),
				), array(
					'sub_title' => array(
						'__t' => 'This is a Label',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'title' => array(
						'__t' => 'Sequester Slide Three',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/svg-img/item-03.svg',
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
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content' => 'text-align: {{VALUE}};',
			),
		),
		'overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-slideshow-item .bdt-slide-image:before' => 'background: {{VALUE}};',
			),
		),
		'overlay_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-slideshow-item .bdt-slide-image:before' => 'opacity: {{SIZE}};',
			),
		),
		'title_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag a' => 'color: {{VALUE}};',
			),
		),
		'first_word_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag .frist-word' => 'color: {{VALUE}};',
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-main-title .bdt-title-tag' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-sub-title-inner' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-sub-title-inner',
		),
		'prime_slider_sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-desc .bdt-sub-title .bdt-sub-title-inner' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slider-excerpt' => 'color: {{VALUE}};',
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
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn',
		),
		'slide_button_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-slide-btn svg *' => 'stroke: {{VALUE}} !important;',
			),
		),
		'slide_button_icon_background_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-slide-btn .bdt-slide-btn-icon' => 'background-color: {{VALUE}};',
			),
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-slide-btn:hover' => 'background-color: {{VALUE}};',
			),
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
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-content .bdt-slide-btn:hover svg *' => 'stroke: {{VALUE}} !important;',
			),
		),
		'slide_button_icon_hover_bg_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-slide-btn .bdt-slide-btn-icon::after' => 'background-color: {{VALUE}};',
			),
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'color: {{VALUE}};',
			),
		),
		'social_icon_style_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-social-icon a .bdt-social-share-title:before' => 'background: {{VALUE}};',
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
		'social_icon_padding' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_spacing' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'social_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a',
		),
		'social_icon_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a:hover' => 'color: {{VALUE}};',
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
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-next svg, {{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-previous svg' => 'color: {{VALUE}}',
			),
		),
		'arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous',
		),
		'arrows_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-next::before, {{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-previous::before' => 'border-color: {{VALUE}}',
			),
		),
		'navi_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-dotnav li:before' => 'background: {{VALUE}}',
			),
		),
		'active_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-dotnav li:hover:before, {{WRAPPER}} .bdt-prime-slider-sequester .bdt-dotnav li.bdt-active:before' => 'background: {{VALUE}}',
			),
		),
		'dots_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-dotnav li a::before' => 'border: 1px solid {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-dotnav li.bdt-active a::after' => 'border-top-color: {{VALUE}}',
			),
		),
		'active_dots_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-dotnav li.bdt-active a::before' => 'border-top-color: {{VALUE}}; border-right-color: {{VALUE}}; border-bottom-color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-dotnav li.bdt-active a::after' => 'border-top-color: {{VALUE}}',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-next:hover svg, {{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-previous:hover svg' => 'color: {{VALUE}}',
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
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-next:hover:before, {{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-previous:hover:before' => 'border-top-color: {{VALUE}}; border-right-color: {{VALUE}}; border-bottom-color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-next:hover::after, {{WRAPPER}} .bdt-prime-slider-sequester .bdt-prime-slider-previous:hover::after' => 'border-top-color: {{VALUE}}',
			),
		),
	),
);
