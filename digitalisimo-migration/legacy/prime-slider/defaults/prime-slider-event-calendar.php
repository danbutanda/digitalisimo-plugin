<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-event-calendar` (bdthemes-prime-slider/modules/event-calendar/widgets/event-calendar.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-event-calendar',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_date' => 'yes',
		'show_excerpt' => 'yes',
		'excerpt_length' => 30,
		'strip_shortcode' => 'yes',
		'show_buy_button' => 'yes',
		'button_link' => array(
			'url' => '#',
		),
		'show_meta_location' => 'yes',
		'image_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'show_days' => 'yes',
		'show_hours' => 'yes',
		'show_minutes' => 'yes',
		'show_seconds' => 'yes',
		'show_labels' => 'yes',
		'label_days' => array(
			'__t' => 'Days',
			'd' => 'bdthemes-prime-slider',
		),
		'label_hours' => array(
			'__t' => 'Hours',
			'd' => 'bdthemes-prime-slider',
		),
		'label_minutes' => array(
			'__t' => 'Minutes',
			'd' => 'bdthemes-prime-slider',
		),
		'label_seconds' => array(
			'__t' => 'Seconds',
			'd' => 'bdthemes-prime-slider',
		),
		'event_categories' => array(),
		'limit' => 6,
		'orderby' => 'event_date',
		'order' => 'DESC',
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_position' => 'center',
		'hide_arrows' => 'yes',
		'dots_position' => 'bottom-center',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => '1',
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 0,
		),
		'arrows_acx_position' => array(
			'size' => 20,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 0,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 0,
		),
		'both_cx_position' => array(
			'size' => 20,
		),
		'both_cy_position' => array(
			'size' => -40,
		),
	),
	'repeaters' => array(),
	'styles' => array(
		'slider_item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-item' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content' => 'text-align: {{VALUE}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-item .bdt-ps-slide-img' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-item .bdt-ps-slide-img' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-item .bdt-ps-slide-img' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-item .bdt-ps-slide-img' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'item_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-image:before' => 'background: {{VALUE}};',
			),
		),
		'slider_item_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'wrapper_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-title' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-title:hover' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-title-wrap',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-title-wrap',
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-title-wrap' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'date_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-date a' => 'color: {{VALUE}};',
			),
		),
		'day_bg_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-date a .bdt-event-day' => 'background: {{VALUE}};',
			),
		),
		'date_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-date a',
		),
		'excerpt_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-excerpt' => 'color: {{VALUE}};',
			),
		),
		'excerpt_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-excerpt',
		),
		'excerpt_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-excerpt' => 'padding-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a' => 'color: {{VALUE}};',
			),
		),
		'button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a',
		),
		'button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a',
		),
		'button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a',
		),
		'button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'border_radius_advanced' => array(
			'type' => 'text',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a' => 'border-radius: {{VALUE}}; overflow: hidden;',
			),
		),
		'button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a',
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a:hover' => 'color: {{VALUE}};',
			),
		),
		'button_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a:before' => 'background: {{VALUE}};',
			),
		),
		'button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a:hover',
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-event-price a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'location_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-address-website-icon a' => 'color: {{VALUE}};',
			),
		),
		'location_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-address-website-icon a:hover' => 'color: {{VALUE}};',
			),
		),
		'location_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-address-website-icon a',
		),
		'location_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-event-content .bdt-event-meta .bdt-address-website-icon a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'countdown_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item' => 'color: {{VALUE}};',
			),
		),
		'countdown_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item',
		),
		'countdown_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item',
		),
		'countdown_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'countdown_padding' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'countdown_number_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item .bdt-countdown-number',
		),
		'countdown_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item .bdt-countdown-label',
		),
		'countdown_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item',
		),
		'countdown_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-wrapper' => 'margin-top: {{SIZE}}{{UNIT}};',
			),
		),
		'countdown_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item:hover' => 'color: {{VALUE}};',
			),
		),
		'countdown_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item:hover',
		),
		'countdown_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-countdown-item:hover' => 'border-color: {{VALUE}};',
			),
		),
		'arrows_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev i, {{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next i' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'arrows_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev, {{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next' => 'background-color: {{VALUE}}',
			),
		),
		'arrows_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev:hover, {{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next:hover' => 'background-color: {{VALUE}}',
			),
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev i, {{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next i' => 'color: {{VALUE}}',
			),
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev:hover i, {{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next:hover i' => 'color: {{VALUE}}',
			),
		),
		'arrows_space' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev' => 'margin-right: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next' => 'margin-left: {{SIZE}}px;',
			),
		),
		'arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev, {{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'border_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev, {{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'dots_size' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};',
			),
		),
		'dots_width' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .swiper-pagination-bullet.swiper-pagination-bullet-active' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		),
		'dots_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .swiper-pagination-bullet' => 'background-color: {{VALUE}}',
			),
		),
		'active_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .swiper-pagination-bullet-active' => 'background-color: {{VALUE}}',
			),
		),
		'dots_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .swiper-pagination-bullet' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'arrows_ncy_position' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-arrows-container' => 'transform: translate({{arrows_ncx_position.size}}px, {{SIZE}}px);',
			),
		),
		'arrows_acx_position' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev' => 'left: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next' => 'right: {{SIZE}}px;',
			),
		),
		'dots_nny_position' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-dots-container' => 'transform: translate({{dots_nnx_position.size}}px, {{SIZE}}px);',
			),
		),
		'both_ncy_position' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-arrows-dots-container' => 'transform: translate({{both_ncx_position.size}}px, {{SIZE}}px);',
			),
		),
		'both_cx_position' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-prev' => 'left: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-navigation-next' => 'right: {{SIZE}}px;',
			),
		),
		'both_cy_position' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-event-calendar .bdt-dots-container' => 'transform: translateY({{SIZE}}px);',
			),
		),
	),
);
