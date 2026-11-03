<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-paranoia` (bdthemes-prime-slider/modules/paranoia/widgets/paranoia.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-paranoia',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_sub_title' => 'yes',
		'sub_title_html_tag' => 'h4',
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_text' => 'yes',
		'show_readmore' => 'yes',
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'button_previous_text' => array(
			'__t' => 'Prev',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'button_next_text' => array(
			'__t' => 'Next',
			'd' => 'bdthemes-prime-slider-lite',
		),
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
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider, {{WRAPPER}} .bdt-paranoia-slider .bdt-slideshow' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-gallery-img-inner' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-gallery-img-inner' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-gallery-img-inner' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-gallery-img-inner' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'slider_overlay_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-gallery-img.bdt-slides-img:before' => 'background: {{VALUE}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-main-title, {{WRAPPER}} .bdt-paranoia-slider .bdt-main-title a' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-main-title',
		),
		'text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-main-title, {{WRAPPER}} .bdt-paranoia-slider .bdt-main-title a',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-main-title',
		),
		'title_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-main-title' => 'transform: translateY({{SIZE}}px);',
			),
		),
		'sub_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-sub-title' => 'color: {{VALUE}};',
			),
		),
		'sub_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-sub-title',
		),
		'sub_title_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-sub-title' => 'transform: translateY({{SIZE}}px);',
			),
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-text' => 'color: {{VALUE}};',
			),
		),
		'text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'text_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-text' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-text',
		),
		'text_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-text' => 'transform: translateY({{SIZE}}px);',
			),
		),
		'readmore_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn' => 'transform: translateY({{SIZE}}px);',
			),
		),
		'read_more_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn' => 'color: {{VALUE}};',
			),
		),
		'read_more_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:before',
		),
		'read_more_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:before',
		),
		'read_more_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'read_more_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'read_more_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:before' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'read_more_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:before',
		),
		'read_more_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn',
		),
		'read_more_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:hover' => 'color: {{VALUE}};',
			),
		),
		'read_more_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:hover:before',
		),
		'read_more_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .bdt-link-btn:hover:before' => 'border-color: {{VALUE}};',
			),
		),
		'navigation_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider nav.nav:hover .bdt-nav-imgwrap' => 'opacity: {{SIZE}};',
			),
		),
		'navigation_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-paranoia-slider .nav:hover .nav__text' => 'color: {{VALUE}};',
			),
		),
		'navigation_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-paranoia-slider .nav__text',
		),
	),
);
