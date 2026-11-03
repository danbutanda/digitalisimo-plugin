<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-flexure` (bdthemes-prime-slider/modules/flexure/widgets/flexure.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-flexure',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'columns' => '3',
		'columns_tablet' => '3',
		'columns_mobile' => '2',
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'ps_flexure_event' => 'mouseover',
		'_active_item' => 1,
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'title_position' => 'center',
		'show_content' => 'yes',
		'show_social_share' => 'yes',
		'box_image_effect_select' => 'effect-1',
		'show_navigation_arrows' => 'yes',
		'show_slider_pagination' => 'yes',
		'follow_us_text' => array(
			'__t' => 'Follow Us',
			'd' => 'bdthemes-prime-slider',
		),
		'nav_arrows_icon' => '0',
	),
	'repeaters' => array(
		'ps_slider' => array(
			'fields' => array(
				'ps_flexure_title' => array(
					'__t' => 'Tab Title',
					'd' => 'bdthemes-prime-slider',
				),
				'title_link' => array(
					'url' => '',
				),
				'ps_flexure_content' => array(
					'__t' => 'Lorem ipsum dolor sit amet consect voluptate repell endus kilo gram magni illo ea animi.',
					'd' => 'bdthemes-prime-slider',
				),
			),
			'rows' => array( array(
					'ps_flexure_title' => array(
						'__t' => 'Kanzu',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/img-1.svg',
					),
				), array(
					'ps_flexure_title' => array(
						'__t' => 'Colza',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/img-2.svg',
					),
				), array(
					'ps_flexure_title' => array(
						'__t' => 'Voxey',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/img-3.svg',
					),
				), array(
					'ps_flexure_title' => array(
						'__t' => 'Wallow',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/img-1.svg',
					),
				), array(
					'ps_flexure_title' => array(
						'__t' => 'Vibrant',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_image' => array(
						'url' => '{{ps_assets}}images/gallery/img-2.svg',
					),
				), array(
					'ps_flexure_title' => array(
						'__t' => 'Massive',
						'd' => 'bdthemes-prime-slider',
					),
					'slide_image' => array(
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
						'__t' => 'Fb',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.twitter.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'Tw',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.instagram.com/bdthemes/',
					),
					'social_link_title' => array(
						'__t' => 'In',
						'd' => 'bdthemes-prime-slider',
					),
				) ),
		),
	),
	'styles' => array(
		'skin_ps_slider_min_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure, {{WRAPPER}} .bdt-ps-flexure-item' => 'height: {{SIZE}}{{UNIT}};',
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
		'title_count_hide' => array(
			'type' => 'switcher',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item .bdt-ps-flexure-title:before' => 'display: none;',
			),
		),
		'overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-content:before' => 'background-color: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item .bdt-ps-flexure-title, {{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item .bdt-ps-flexure-title a' => '-webkit-text-stroke: 1px {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item:hover .bdt-ps-flexure-title, {{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item:hover .bdt-ps-flexure-title a' => 'color: {{VALUE}}; -webkit-text-stroke: 1px {{VALUE}};',
			),
		),
		'title_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item.active .bdt-ps-flexure-title, {{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item.active .bdt-ps-flexure-title a' => 'color: {{VALUE}}; -webkit-text-stroke: 1px {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item .bdt-ps-flexure-title, {{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item .bdt-ps-flexure-title a',
		),
		'title_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-item .bdt-ps-flexure-description' => 'top: {{SIZE}}%',
			),
		),
		'description_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text' => 'color: {{VALUE}};',
			),
		),
		'text_Width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text-wrapper' => 'width: {{SIZE}}{{UNIT}}; max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'text_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text-wrapper' => 'bottom: {{SIZE}}{{UNIT}}',
			),
		),
		'description_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text',
		),
		'description_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text-wrapper .bdt-custom-divider' => 'background: linear-gradient(90deg, rgba(255, 255, 255, 0), {{VALUE}}, rgba(255, 255, 255, 0));',
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text-wrapper .bdt-custom-divider:before' => 'background: {{VALUE}};',
			),
		),
		'description_divider_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text-wrapper .bdt-custom-divider:before' => 'color: {{VALUE}};',
			),
		),
		'description_divider_shadow_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-flexure-text-wrapper .bdt-custom-divider:before' => '--box-shadow-color: {{VALUE}};',
			),
		),
		'social_icon_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon h3' => 'color: {{VALUE}};',
			),
		),
		'social_icon_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon h3',
		),
		'social_icon_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'color: {{VALUE}};',
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
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'social_icon_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
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
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-social-icon a' => 'margin-left: {{SIZE}}{{UNIT}};',
			),
		),
		'social_icon_size' => array(
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
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-dotnav span' => 'color: {{VALUE}}',
			),
		),
		'pagination_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-dotnav li a' => 'color: {{VALUE}}',
			),
		),
		'pagination_divider_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-dotnav span:before' => 'background: linear-gradient(90deg, rgba(255, 255, 255, 0), {{VALUE}}, rgba(255, 255, 255, 0));',
			),
		),
		'pagination_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-dotnav li a, {{WRAPPER}} .bdt-prime-slider-flexure .bdt-ps-dotnav span',
		),
		'arrows_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next svg, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous i, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next i' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:before, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:before' => 'background: {{VALUE}}',
			),
		),
		'navigation_arrows_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next' => 'background: {{VALUE}};',
			),
		),
		'navigation_arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next',
		),
		'navigation_arrows_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_arrows_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous i, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next i',
		),
		'arrows_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:hover svg, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:hover svg, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous:hover i, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next:hover i' => 'color: {{VALUE}}',
				'{{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-next:before, {{WRAPPER}} .bdt-prime-slider .bdt-prime-slider-previous:before' => 'background: {{VALUE}}',
			),
		),
		'navigation_arrows_hover_background' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous:hover, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next:hover' => 'background: {{VALUE}};',
			),
		),
		'navigation_arrows_border_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-previous:hover, {{WRAPPER}} .bdt-navigation-arrows .bdt-slidenav-next:hover' => 'border-color: {{VALUE}};',
			),
		),
	),
);
// Valor no literal, sin escribir: slide_image (repetidor): [ 'url' => BDTPS_CORE_ASSETS_URL . 'images/slide/item-' . wp_rand( 1, 6 ) . '.jpg', ]
