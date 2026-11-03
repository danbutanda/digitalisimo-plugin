<?php
/**
 * Valores por defecto de Prime Slider Lite 4.7.0 `prime-slider-multiscroll` (bdthemes-prime-slider-lite/modules/multiscroll/widgets/multiscroll.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-multiscroll',
	'plugin' => 'lite',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'thumbnail_size_size' => 'full',
		'content_position' => 'center',
		'show_image' => 'yes',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_description' => 'yes',
		'show_button' => 'yes',
		'button_position' => 'right',
		'button_vertical_offset' => array(
			'size' => 0,
		),
		'show_shadow_title' => 'yes',
		'scrollingSpeed' => array(
			'size' => 700,
		),
		'navigation' => 'yes',
		'navigationPosition' => 'right',
		'loopBottom' => 'yes',
		'loopTop' => 'yes',
		'autoplay_speed' => 5000,
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'title_link' => array(
					'url' => '',
				),
				'description' => array(
					'__t' => 'Lorem ipsum dolor sit amet.',
					'd' => 'bdthemes-prime-slider-lite',
				),
				'slide_button' => array(
					'__t' => 'View Details',
					'd' => 'bdthemes-prime-slider-lite',
				),
				'button_link' => array(
					'url' => '#',
				),
				'left_background' => 'color',
				'left_background_image' => array(
					'url' => $placeholder,
				),
				'right_background' => 'color',
				'right_background_image' => array(
					'url' => $placeholder,
				),
			),
			'rows' => array( array(
					'title' => array(
						'__t' => 'MultiScroll',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gems-1.png',
					),
					'left_background_color' => '#ad218d',
					'right_background_color' => '#bb1f98',
				), array(
					'title' => array(
						'__t' => 'MultiScroll',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gems-2.png',
					),
					'left_background_color' => '#4287ec',
					'right_background_color' => '#498cef',
				), array(
					'title' => array(
						'__t' => 'MultiScroll',
						'd' => 'bdthemes-prime-slider-lite',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gems-3.png',
					),
					'left_background_color' => '#82007d',
					'right_background_color' => '#8a0c85',
				) ),
		),
	),
	'styles' => array(
		'content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_text_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content' => 'text-align: {{VALUE}};',
			),
		),
		'button_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider-button' => 'transform: translateY({{SIZE}}{{UNIT}});',
			),
		),
		'item_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content' => 'background-color: {{VALUE}};',
			),
		),
		'item_shadow_title' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .ms-section.shadow-title.bdt-ms-section-right:before, {{WRAPPER}} .bdt-mltiscroll-slider .ms-section.shadow-title.bdt-ms-section-left:before' => 'color: {{VALUE}};',
			),
		),
		'item_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content',
		),
		'item_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'item_content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-title' => 'color: {{VALUE}};',
			),
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-title' => 'padding-bottom: {{SIZE}}{{UNIT}}',
			),
		),
		'text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-title',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-title',
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-title',
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-subtitle' => 'color: {{VALUE}};',
			),
		),
		'sub_title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-subtitle' => 'margin-bottom: {{SIZE}}{{UNIT}}',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-subtitle',
		),
		'description_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-description' => 'color: {{VALUE}};',
			),
		),
		'description_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-description' => 'padding-bottom: {{SIZE}}{{UNIT}}',
			),
		),
		'description_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-content .bdt-mltiscroll-slider-description',
		),
		'button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a' => 'color: {{VALUE}};',
			),
		),
		'button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a',
		),
		'button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a',
		),
		'button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'border_radius_advanced' => array(
			'type' => 'text',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a' => 'border-radius: {{VALUE}}; overflow: hidden;',
			),
		),
		'button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a',
		),
		'button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a',
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a:hover' => 'color: {{VALUE}};',
			),
		),
		'button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a:hover',
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'button_hover_border_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a:before' => 'background: {{VALUE}};',
			),
		),
		'button_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider .bdt-mltiscroll-slider-button a:hover',
		),
		'navigation_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav li span' => 'background: {{VALUE}};',
			),
		),
		'navigation_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav span',
		),
		'navi_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav span' => 'border-color: {{VALUE}};',
			),
		),
		'navigation_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav span, {{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav ul li' => 'width: {{SIZE}}{{UNIT}}',
			),
		),
		'navigation_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav span, {{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav ul li' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'navigation_horizontal_offset_right' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav ul li' => 'margin-right: {{SIZE}}{{UNIT}};',
			),
		),
		'navigation_horizontal_offset_left' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav ul li' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'navigation_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav ul li' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'navigation_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav li a.active span' => 'background: {{VALUE}};',
			),
		),
		'navigation_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-mltiscroll-slider #multiscroll-nav ul li a.active span' => 'border-color: {{VALUE}};',
			),
		),
	),
);
// Valor no literal, sin escribir: slide_image (repetidor): [ 'url' => BDTPS_CORE_ASSETS_URL . 'images/gems-' . wp_rand(1, 3) . '.png', ]
