<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-cloud` (bdthemes-prime-slider/modules/cloud/widgets/cloud.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-cloud',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'slide_height' => array(
			'size' => 550,
			'unit' => 'px',
		),
		'thumbnail_size_size' => 'full',
		'show_title' => 'yes',
		'title_html_tag' => 'h2',
		'show_text' => 'yes',
		'apply_link_to_slide' => '',
		'show_navigation_arrows' => 'yes',
		'hide_arrow_on_mobile' => '',
		'show_navigation_dots' => 'yes',
		'opacity_step' => array(
			'size' => 0.33,
			'unit' => 'px',
		),
		'scale_step' => array(
			'size' => 0.2,
			'unit' => 'px',
		),
		'side_slides' => '2',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 300,
		),
	),
	'repeaters' => array(
		'slides' => array(
			'fields' => array(
				'image' => array(
					'url' => $placeholder,
				),
				'link_url' => array(
					'url' => '',
				),
				'description' => array(
					'__t' => 'Add your slide description here.',
					'd' => 'bdthemes-prime-slider',
				),
			),
			'rows' => array( array(
					'title' => array(
						'__t' => 'Guardians Of The Galaxy',
						'd' => 'bdthemes-prime-slider',
					),
					'description' => array(
						'__t' => 'A group of intergalactic outlaws unite to stop a fanatical warrior threatening the universe.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'title' => array(
						'__t' => 'Justice League',
						'd' => 'bdthemes-prime-slider',
					),
					'description' => array(
						'__t' => 'Bruce Wayne and Diana Prince assemble a team of metahumans to face an approaching global threat.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'title' => array(
						'__t' => 'Spider-Man: Far From Home',
						'd' => 'bdthemes-prime-slider',
					),
					'description' => array(
						'__t' => 'Spider-Man steps up to face new threats in a world forever changed after Endgame.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'title' => array(
						'__t' => 'The Dark Knight',
						'd' => 'bdthemes-prime-slider',
					),
					'description' => array(
						'__t' => 'Batman faces powerful new enemies while risking everything to protect Gotham.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'title' => array(
						'__t' => 'Guardians Of The Galaxy',
						'd' => 'bdthemes-prime-slider',
					),
					'description' => array(
						'__t' => 'Outlaw heroes band together to stop a cosmic threat to the universe.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => $placeholder,
					),
				), array(
					'title' => array(
						'__t' => 'Thor: Ragnarok',
						'd' => 'bdthemes-prime-slider',
					),
					'description' => array(
						'__t' => 'Thor races to save Asgard from destruction at the hands of the ruthless Hela.',
						'd' => 'bdthemes-prime-slider',
					),
					'image' => array(
						'url' => $placeholder,
					),
				) ),
		),
	),
	'styles' => array(
		'slide_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--bdt-cloud-slide-height: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--bdt-cloud-item-height: {{SIZE}}{{UNIT}};',
			),
		),
		'slide_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-slide .swiper-cloud-animate-opacity',
		),
		'slide_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-slide .swiper-cloud-animate-opacity',
		),
		'slide_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-slide' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-cloud-animate-opacity' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-slide .swiper-cloud-animate-opacity' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'slide_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-slide .swiper-cloud-animate-opacity',
		),
		'slide_image_object_fit' => array(
			'type' => 'select',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-slide .swiper-cloud-animate-opacity img' => 'object-fit: {{VALUE}};',
			),
		),
		'slide_border_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-slide.swiper-slide-active .swiper-cloud-animate-opacity' => 'border-color: {{VALUE}};',
			),
		),
		'title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title, {{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title a',
		),
		'title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title a' => 'color: {{VALUE}};',
			),
		),
		'title_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'title_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title' => 'text-align: {{VALUE}};',
			),
		),
		'title_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'title_text_shadow' => array(
			'type' => 'text-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title, {{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title a',
		),
		'title_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title:hover' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-title a:hover' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud a.bdt-ps-cloud-slide-link:hover .bdt-ps-cloud-title' => 'color: {{VALUE}};',
			),
		),
		'description_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc p' => 'color: {{VALUE}};',
			),
		),
		'description_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc, {{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc p',
		),
		'description_spacing' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc' => 'margin-top: {{SIZE}}{{UNIT}};',
			),
		),
		'description_alignment' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc' => 'text-align: {{VALUE}};',
			),
		),
		'description_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'description_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc' => 'opacity: calc({{SIZE}} / 100);',
			),
		),
		'description_max_lines' => array(
			'type' => 'number',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .slide-content .bdt-ps-cloud-desc' => '-webkit-line-clamp: {{VALUE}};',
			),
		),
		'nav_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--swiper-navigation-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev:after' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next:after' => 'color: {{VALUE}};',
			),
		),
		'nav_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev' => 'opacity: calc({{SIZE}} / 100);',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next' => 'opacity: calc({{SIZE}} / 100);',
			),
		),
		'nav_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev:after' => 'font-size: {{SIZE}}px;',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next:after' => 'font-size: {{SIZE}}px;',
			),
		),
		'nav_offset_sides' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev' => 'left: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next' => 'right: {{SIZE}}{{UNIT}};',
			),
		),
		'nav_arrows_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev, {{WRAPPER}} .bdt-ps-cloud .swiper-button-next',
		),
		'nav_arrows_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev, {{WRAPPER}} .bdt-ps-cloud .swiper-button-next',
		),
		'nav_arrows_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'nav_arrows_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'nav_arrows_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev, {{WRAPPER}} .bdt-ps-cloud .swiper-button-next',
		),
		'nav_hover_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev:hover:after' => 'color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next:hover:after' => 'color: {{VALUE}};',
			),
		),
		'nav_hover_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev:hover' => 'opacity: calc({{SIZE}} / 100);',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-button-next:hover' => 'opacity: calc({{SIZE}} / 100);',
			),
		),
		'nav_arrows_hover_background' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev:hover, {{WRAPPER}} .bdt-ps-cloud .swiper-button-next:hover',
		),
		'nav_arrows_hover_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev:hover, {{WRAPPER}} .bdt-ps-cloud .swiper-button-next:hover',
		),
		'nav_arrows_hover_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-button-prev:hover, {{WRAPPER}} .bdt-ps-cloud .swiper-button-next:hover',
		),
		'pagination_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination' => 'display: flex; justify-content: {{VALUE}}; width: 100%; left: 0; transform: none;',
			),
		),
		'pagination_vertical_offset' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--bdt-cloud-pagination-bottom: {{SIZE}}{{UNIT}};',
			),
		),
		'pagination_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--swiper-pagination-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet-active' => 'background-color: {{VALUE}};',
			),
		),
		'pagination_inactive_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--swiper-pagination-bullet-inactive-color: {{VALUE}};',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet:not(.swiper-pagination-bullet-active)' => 'background-color: {{VALUE}};',
			),
		),
		'pagination_inactive_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--swiper-pagination-bullet-inactive-opacity: calc({{SIZE}} / 100);',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet:not(.swiper-pagination-bullet-active)' => 'opacity: calc({{SIZE}} / 100);',
			),
		),
		'pagination_active_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud' => '--swiper-pagination-bullet-active-opacity: calc({{SIZE}} / 100);',
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet-active' => 'opacity: calc({{SIZE}} / 100);',
			),
		),
		'pagination_bullet_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet' => 'width: {{SIZE}}px; height: {{SIZE}}px;',
			),
		),
		'pagination_bullet_gap' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet' => 'margin-left: {{SIZE}}px; margin-right: {{SIZE}}px;',
			),
		),
		'pagination_bullet_radius' => array(
			'type' => 'dimensions',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'pagination_bullet_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet',
		),
		'pagination_bullet_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .bdt-ps-cloud .swiper-pagination-bullet',
		),
	),
);
