<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-expo-slider` (bdthemes-prime-slider/modules/expo/widgets/expo.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-expo-slider',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'expo_direction' => 'horizontal',
		'apply_link_to' => 'title',
		'expo_show_demo_nav' => '',
		'expo_loop' => 'yes',
		'expo_autoplay' => 'no',
		'expo_autoplay_delay' => 5000,
		'expo_grab_cursor' => 'yes',
		'expo_slides_per_view' => array(
			'size' => 1.5,
		),
		'expo_slides_per_view_tablet' => array(
			'size' => 1.5,
		),
		'expo_slides_per_view_mobile' => array(
			'size' => 1.2,
		),
		'expo_space_between' => array(
			'size' => 32,
		),
		'expo_space_between_tablet' => array(
			'size' => 32,
		),
		'expo_space_between_mobile' => array(
			'size' => 16,
		),
		'expo_speed' => array(
			'size' => 600,
		),
		'expo_image_scale' => array(
			'size' => 1.1,
		),
		'expo_image_border_radius' => array(
			'size' => 32,
		),
		'expo_image_offset' => array(
			'size' => 1.2,
		),
		'expo_scale' => array(
			'size' => 1.3,
		),
		'expo_grayscale' => 'yes',
		'expo_rotate' => array(
			'size' => 0,
		),
		'expo_rotate_toggle_angle' => array(
			'size' => 30,
		),
		'expo_demo_nav_gap' => array(
			'size' => 8,
		),
	),
	'repeaters' => array(
		'expo_slides' => array(
			'fields' => array(
				'expo_title' => array(
					'__t' => 'Confidence is the best outfit. Wear it and own it!',
					'd' => 'bdthemes-prime-slider',
				),
				'expo_image' => array(
					'url' => $placeholder,
				),
			),
			'rows' => array( array(
					'expo_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
					'expo_title' => array(
						'__t' => 'Confidence is the best outfit. Wear it and own it!',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'expo_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.svg',
					),
					'expo_title' => array(
						'__t' => 'Glow differently. Shine unapologetically.',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'expo_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.svg',
					),
					'expo_title' => array(
						'__t' => 'Elegance is an attitude.',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'expo_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
					'expo_title' => array(
						'__t' => 'Fearless. Limitless. Unstoppable.',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'expo_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
					'expo_title' => array(
						'__t' => 'Your vibe attracts your tribe.',
						'd' => 'bdthemes-prime-slider',
					),
				) ),
		),
	),
	'styles' => array(
		'expo_height' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider' => '--expo-swiper-vertical-height: {{SIZE}}{{UNIT}}; --expo-swiper-vertical-aspect: auto; --expo-swiper-horizontal-height: {{SIZE}}{{UNIT}}; --expo-swiper-horizontal-aspect: auto;',
			),
		),
		'expo_image_border_radius' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-swiper' => '--expo-image-border-radius: {{SIZE}}px;',
			),
		),
		'expo_wrapper_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider',
		),
		'expo_wrapper_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_wrapper_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_wrapper_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider',
		),
		'expo_wrapper_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_wrapper_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider',
		),
		'expo_content_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .expo-content' => 'color: {{VALUE}};',
			),
		),
		'expo_content_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .expo-content, {{WRAPPER}} .bdt-expo-slider .expo-content *',
		),
		'expo_content_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .expo-content, {{WRAPPER}} .bdt-expo-slider .expo-content *',
		),
		'expo_content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .expo-content-heading' => 'max-width: {{SIZE}}{{UNIT}}; width: 100%;',
			),
		),
		'expo_content_alignment' => array(
			'type' => 'choose',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .expo-content' => 'text-align: {{VALUE}};',
			),
		),
		'expo_content_vertical_position' => array(
			'type' => 'choose',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .expo-content' => 'align-items: {{VALUE}};',
			),
		),
		'expo_content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .expo-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_content_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .expo-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_demo_nav_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav',
		),
		'expo_demo_nav_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_demo_nav_gap' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav' => 'gap: {{SIZE}}px;',
			),
		),
		'expo_demo_nav_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav',
		),
		'expo_demo_nav_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_demo_nav_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav' => 'margin-bottom: {{SIZE}}px;',
			),
		),
		'expo_demo_btn_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn' => 'color: {{VALUE}};',
			),
		),
		'expo_demo_btn_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn',
		),
		'expo_demo_btn_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn',
		),
		'expo_demo_btn_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn',
		),
		'expo_demo_btn_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_demo_btn_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'expo_demo_btn_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn',
		),
		'expo_demo_btn_text_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn:hover' => 'color: {{VALUE}};',
			),
		),
		'expo_demo_btn_background_hover' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn:hover',
		),
		'expo_demo_btn_border_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'expo_demo_btn_text_color_active' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn.bdt-active' => 'color: {{VALUE}};',
			),
		),
		'expo_demo_btn_background_active' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn.bdt-active',
		),
		'expo_demo_btn_border_color_active' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-expo-slider .bdt-expo-nav .bdt-expo-btn.bdt-active' => 'border-color: {{VALUE}};',
			),
		),
	),
);
