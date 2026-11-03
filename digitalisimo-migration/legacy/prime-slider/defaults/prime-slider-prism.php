<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-prism` (bdthemes-prime-slider/modules/prism/widgets/prism.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-prism',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_count' => 'yes',
		'show_preview_title' => 'yes',
		'preview_title_html_tag' => 'div',
		'show_text' => 'yes',
		'button_previous_text' => array(
			'__t' => 'Prev',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'button_next_text' => array(
			'__t' => 'Next',
			'd' => 'bdthemes-prime-slider-lite',
		),
		'thumbnail_size_size' => 'full',
		'background_image_position' => '',
		'background_image_repeat' => '',
		'background_image_size' => '',
		'background_image_width' => array(
			'size' => 100,
			'unit' => '%',
		),
		'overlay_type' => 'background',
		'overlay_color_background' => 'gradient',
		'overlay_color_color' => 'rgba(0, 0, 0, 0)',
		'overlay_color_color_b' => 'rgba(3, 4, 16, 0.3)',
		'blend_type' => 'multiply',
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'title_link' => array(
					'url' => '',
				),
				'text' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
				),
				'image' => array(
					'url' => $placeholder,
				),
			),
			'rows' => array( array(
					'sub_title' => array(
						'__t' => 'by Paul Clement',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Item One',
						'd' => 'bdthemes-prime-slider',
					),
					'text' => array(
						'__t' => 'Prime Slider Addons for Elementor is a page builder extension that allows you to build sliders with drag and drop. It lets you create an amazing slider without touching no code at all!',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
					'image_caption' => array(
						'__t' => 'Caption 1',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'sub_title' => array(
						'__t' => 'by Martin Quartz',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Item Two',
						'd' => 'bdthemes-prime-slider',
					),
					'text' => array(
						'__t' => 'prism slider is a premium plugin for Elementor that helps you to create awesome sliders for your website. It comes with the perfect design and drag & drop builder tools.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
					'image_caption' => array(
						'__t' => 'Caption 2',
						'd' => 'bdthemes-prime-slider',
					),
				), array(
					'sub_title' => array(
						'__t' => 'by John Tatin',
						'd' => 'bdthemes-prime-slider',
					),
					'title' => array(
						'__t' => 'Item Three',
						'd' => 'bdthemes-prime-slider',
					),
					'text' => array(
						'__t' => 'The prism Slider is a creative slider that helps you to design a modern website. With the help of this slider, you can create a unique and memorable experience for your users.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
					'image_caption' => array(
						'__t' => 'Caption 3',
						'd' => 'bdthemes-prime-slider',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--bdt-prism-slider-height: {{SIZE}}{{UNIT}};',
			),
		),
		'background_image_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__img' => 'background-position: {{VALUE}};',
			),
		),
		'background_image_repeat' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__img' => 'background-repeat: {{VALUE}};',
			),
		),
		'background_image_size' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__img' => 'background-size: {{VALUE}};',
			),
		),
		'background_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__img' => 'background-size: {{SIZE}}{{UNIT}} auto',
			),
		),
		'slider_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider',
		),
		'slide_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__img-wrap' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_image_overlay_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slide__img-wrap::before',
		),
		'slide_image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slide__img-wrap',
		),
		'slide_image_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__img-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_count_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__number' => 'color: {{VALUE}};',
			),
		),
		'slide_count_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slide__number',
		),
		'slide_count_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slide__number',
		),
		'slide_count_top_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__number' => 'top: {{SIZE}}px;',
			),
		),
		'slide_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slide__title' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prism-slider .slide__title a' => 'color: {{VALUE}};',
			),
		),
		'slide_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slide__title',
		),
		'slide_title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slide__title',
		),
		'slide_title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slide__title',
		),
		'overlay_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .preview__img-wrap::before',
		),
		'blend_type' => array(
			'type' => 'select',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .preview__img-wrap::before' => 'mix-blend-mode: {{VALUE}};',
			),
		),
		'preview_title_bottom_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .preview__title' => 'margin-bottom: {{SIZE}}px;',
			),
		),
		'preview_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .preview__title' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-prism-slider .preview__title a' => 'color: {{VALUE}};',
			),
		),
		'preview_title_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .preview__title',
		),
		'preview_title_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .preview__title',
		),
		'preview_title_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .preview__title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'preview_title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .preview__title ' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'preview_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .preview__title',
		),
		'preview_title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .preview__title',
		),
		'preview_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .preview__content' => 'color: {{VALUE}};',
			),
		),
		'preview_text_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .preview__content' => 'max-width: {{SIZE}}px;',
			),
		),
		'preview_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .preview__content',
		),
		'nav_preview_button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__preview' => 'color: {{VALUE}};',
			),
		),
		'nav_preview_button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slidenav__preview',
		),
		'nav_preview_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slidenav__preview',
		),
		'nav_preview_button_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__preview' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'nav_preview_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__preview' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'nav_preview_button_font_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__preview' => 'font-size: {{SIZE}}px;',
			),
		),
		'nav_preview_button_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__preview' => 'margin-bottom: {{SIZE}}px;',
			),
		),
		'nav_preview_button_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slidenav__preview',
		),
		'nav_preview_button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__preview:hover' => 'color: {{VALUE}};',
			),
		),
		'nav_preview_button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slidenav__preview:hover',
		),
		'nav_preview_button_border_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__preview:hover' => 'border-color: {{VALUE}};',
			),
		),
		'navigation_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__item' => 'color: {{VALUE}};',
			),
		),
		'navigation_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__item:hover' => 'color: {{VALUE}};',
			),
		),
		'navigation_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prism-slider .slidenav__item ' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slidenav__item',
		),
		'navigation_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prism-slider .slidenav__item',
		),
	),
);
