<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-crossroad` (bdthemes-prime-slider/modules/crossroad/widgets/crossroad.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-crossroad',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'slider_height' => array(
			'size' => 80,
			'unit' => 'vh',
		),
		'slider_item_gap' => array(
			'size' => 5,
			'unit' => 'vw',
		),
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_meta_text' => 'yes',
		'show_excerpt' => 'yes',
		'show_button' => 'yes',
		'show_counter' => 'yes',
		'image_rotate' => array(
			'size' => -8,
			'unit' => 'deg',
		),
		'title_rotate' => array(
			'size' => 16,
			'unit' => 'deg',
		),
		'thumbnail_size_size' => 'medium_large',
		'slider_image_height' => array(
			'unit' => 'px',
		),
		'modal_thumbnail_size_size' => 'full',
		'cursor_color' => '#000',
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'image' => array(
					'url' => $placeholder,
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
			),
			'rows' => array( array(
					'title' => array(
						'__t' => 'Kanzu',
						'd' => 'bdthemes-prime-slider',
					),
					'meta_text' => array(
						'__t' => 'This is meta text here',
						'd' => 'bdthemes-prime-slider',
					),
					'excerpt' => array(
						'__t' => 'Prime Slider Addons for Elementor is a page builder extension that allows you to build sliders with drag and drop. It lets you create an amazing slider without touching no code at all!',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Juked',
						'd' => 'bdthemes-prime-slider',
					),
					'meta_text' => array(
						'__t' => 'This is meta text here',
						'd' => 'bdthemes-prime-slider',
					),
					'excerpt' => array(
						'__t' => 'Crossroad Slider is a modern and creative slider that helps you to design a unique website. Upload your own images, choose from more than 50 animations and create a stunning slider with Elementor.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Colza',
						'd' => 'bdthemes-prime-slider',
					),
					'meta_text' => array(
						'__t' => 'This is meta text here',
						'd' => 'bdthemes-prime-slider',
					),
					'excerpt' => array(
						'__t' => 'Moneky slider is a creative slider that helps you to design a modern website. It has advanced features and many customization options, also it\'s packed with beautiful content blocks.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Voxey',
						'd' => 'bdthemes-prime-slider',
					),
					'meta_text' => array(
						'__t' => 'This is meta text here',
						'd' => 'bdthemes-prime-slider',
					),
					'excerpt' => array(
						'__t' => 'Prime Slider Addons for Elementor - crossroad slider is a creative slider that helps you to design a modern website without any coding knowledge. It\'s unique, responsive and highly customizable.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.svg',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--ps-crossroad-slider-height: {{SIZE}}vh',
			),
		),
		'slider_item_gap' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--ps-crossroad-column-gap: {{SIZE}}vw;',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .grid--slideshow, {{WRAPPER}} .bdt-crossroad-slider .content, {{WRAPPER}} .bdt-crossroad-slider .content__item-copy' => 'text-align: {{VALUE}};',
			),
		),
		'slider_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .revealer__inner',
		),
		'image_rotate' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--ps-crossroad-image-rotate: {{SIZE}}deg',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .grid__item--title' => 'color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .grid__item--title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .grid__item--title',
		),
		'title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .grid__item--title',
		),
		'title_rotate' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}' => '--ps-crossroad-title-rotate: {{SIZE}}deg',
			),
		),
		'meta_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .caption' => 'color: {{VALUE}};',
			),
		),
		'meta_text_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .caption::before' => 'color: {{VALUE}};',
			),
		),
		'meta_text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .caption' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'meta_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .caption',
		),
		'number_stroke_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .number' => '-webkit-text-stroke-color: {{VALUE}}; text-stroke-color: {{VALUE}};',
			),
		),
		'number_fill_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .number' => '-webkit-text-fill-color: {{VALUE}}; text-fill-color: {{VALUE}};',
			),
		),
		'number_stroke_width' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .number' => '-webkit-text-stroke-width: {{SIZE}}px; text-stroke-width: {{SIZE}}px;',
			),
		),
		'number_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .number' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'number_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .number',
		),
		'image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .grid__item .img-wrap',
		),
		'image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .grid__item .img-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .grid__item .img-wrap .img',
		),
		'modal_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item',
		),
		'header_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'content_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'modal_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-header-title' => 'color: {{VALUE}};',
			),
		),
		'modal_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-header-title',
		),
		'modal_title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-header-title',
		),
		'modal_title_text_stroke' => array(
			'type' => 'text-stroke',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-header-title',
		),
		'modal_meta_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-header-meta' => 'color: {{VALUE}};',
			),
		),
		'modal_meta_text_line_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-header-meta::before' => 'color: {{VALUE}};',
			),
		),
		'modal_meta_text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-header-meta' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'modal_meta_text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-header-meta',
		),
		'text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-text' => 'color: {{VALUE}};',
			),
		),
		'text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'text_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'text_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-text',
		),
		'button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more' => 'color: {{VALUE}};',
			),
		),
		'button_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more',
		),
		'button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more',
		),
		'button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_text_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_text_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more',
		),
		'button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more',
		),
		'button_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more:hover' => 'color: {{VALUE}};',
			),
		),
		'button_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more:hover',
		),
		'button_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more:hover' => 'border-color: {{VALUE}};',
			),
		),
		'button_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .content__item-copy-more:hover',
		),
		'slider_image_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-crossroad-slider .img-wrap--content' => 'height: {{SIZE}}{{UNIT}}',
			),
		),
		'modal_css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-crossroad-slider .img--content',
		),
	),
);
