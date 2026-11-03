<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-avatar` (bdthemes-prime-slider/modules/avatar/widgets/avatar.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-avatar',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'show_title' => 'yes',
		'title_html_tag' => 'h1',
		'show_excerpt' => 'yes',
		'show_button_text' => 'yes',
		'show_navigation_arrows' => 'yes',
		'thumbnail_size_size' => 'full',
		'autoplay' => 'no',
		'autoplay_speed' => 4000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
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
				'excerpt' => array(
					'__t' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem, totam rem aperiam, eaque ipsa quae ab illo inventore et quasi architecto beatae vitae dicta sunt explicabo.',
					'd' => 'bdthemes-prime-slider-lite',
				),
			),
			'rows' => array( array(
					'title' => array(
						'__t' => 'Avatar Slide One',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Avatar Slide Two',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Avatar Slide Three',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
				), array(
					'title' => array(
						'__t' => 'Avatar Slide Four',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.svg',
					),
				) ),
		),
	),
	'styles' => array(
		'slider_min_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-content-wrap' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'content_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-content-wrap' => 'text-align: {{VALUE}};',
			),
		),
		'image_overlay' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .content--hero::before',
		),
		'item_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-content-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'thumbs_image_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-hero-swiper' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'thumbs_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-hero-swiper' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'thumb_image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-item-box',
		),
		'thumb_image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-item-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-title, {{WRAPPER}} .bdt-prime-slider-avatar .bdt-title>a' => 'color: {{VALUE}};',
			),
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-title:hover, {{WRAPPER}} .bdt-prime-slider-avatar .bdt-title>a:hover' => 'color: {{VALUE}};',
			),
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-title',
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-title, {{WRAPPER}} .bdt-prime-slider-avatar .bdt-title>a',
		),
		'desc_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-desc' => 'color: {{VALUE}};',
			),
		),
		'desc_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-desc',
		),
		'desc_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-desc' => 'margin-bottom: {{SIZE}}{{UNIT}}',
			),
		),
		'desc_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-desc',
		),
		'link_btn_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a' => 'color: {{VALUE}}',
			),
		),
		'link_btn_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a',
		),
		'link_btn_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a',
		),
		'link_btn_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-rubix-slider .bdt-main-slider .bdt-read-more' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'link_btn_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a',
		),
		'link_btn_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a',
		),
		'link_btn_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a:hover' => 'color: {{VALUE}}',
			),
		),
		'link_btn_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a:hover',
		),
		'link_btn_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a:hover' => 'border-color: {{VALUE}};',
			),
		),
		'link-btn_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-link-btn a:hover',
		),
		'navigation_btn_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next' => 'color: {{VALUE}}',
			),
		),
		'navigation_btn_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next',
		),
		'navigation_btn_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next',
		),
		'navigation_btn_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'navigation_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			),
		),
		'navigation_btn_icon_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}}  .bdt-prime-slider-avatar .bdt-button-next svg' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
			),
		),
		'navigation_btn_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next',
		),
		'navigation_btn_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next',
		),
		'navigation_btn_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next:hover' => 'color: {{VALUE}}',
			),
		),
		'navigation_btn_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next:hover',
		),
		'navigation_btn_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next:hover' => 'border-color: {{VALUE}};',
			),
		),
		'navigation_btn_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-prime-slider-avatar .bdt-button-next:hover',
		),
	),
);
