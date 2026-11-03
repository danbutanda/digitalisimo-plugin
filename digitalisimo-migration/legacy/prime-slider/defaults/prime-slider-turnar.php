<?php
/**
 * Valores por defecto de Prime Slider Pro 4.7.0 `prime-slider-turnar` (bdthemes-prime-slider/modules/turnar/widgets/turnar.php), leídos de su ZIP sin ejecutarlo.
 * Datos de Prime Slider (GPL), sólo para rellenar lo que un documento no guardó; los limpia la conversión.
 * `{{ps_assets}}` = carpeta assets/ de Prime Slider; {"__t", "d"} = texto traducible; $placeholder = imagen de relleno.
 * Generado con scripts/prime-slider-defaults.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'id' => 'prime-slider-turnar',
	'plugin' => 'pro',
	'version' => '4.7.0',
	'skins' => array(),
	'controls' => array(
		'turnar_center_image' => array(
			'url' => '{{ps_assets}}images/gallery/item-2.svg',
		),
		'turnar_button_text' => array(
			'__t' => 'Read more',
			'd' => 'bdthemes-prime-slider',
		),
		'turnar_icon_position' => 'before',
		'turnar_rotation_speed' => array(
			'unit' => 's',
			'size' => 30,
		),
		'turnar_rotation_direction' => 'clockwise',
		'turnar_rotation_control' => 'loop',
		'turnar_center_image_object_fit' => '',
		'turnar_center_image_object_position' => 'center center',
		'turnar_item_normal_scale' => 1,
		'turnar_item_hover_scale' => 1.1,
		'turnar_modal_content_direction' => 'row',
		'turnar_modal_content_direction_tablet' => 'column',
		'turnar_modal_content_direction_mobile' => 'column',
		'turnar_arrows_icon' => '0',
	),
	'repeaters' => array(
		'turnar_items' => array(
			'fields' => array(),
			'rows' => array( array(
					'turnar_title' => array(
						'__t' => 'Title #1',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-1.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				), array(
					'turnar_title' => array(
						'__t' => 'Title #2',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-2.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				), array(
					'turnar_title' => array(
						'__t' => 'Title #3',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-3.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				), array(
					'turnar_title' => array(
						'__t' => 'Title #4',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-4.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				), array(
					'turnar_title' => array(
						'__t' => 'Title #5',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-5.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				), array(
					'turnar_title' => array(
						'__t' => 'Title #6',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-6.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				), array(
					'turnar_title' => array(
						'__t' => 'Title #7',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-7.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				), array(
					'turnar_title' => array(
						'__t' => 'Title #8',
						'd' => 'bdthemes-prime-slider',
					),
					'turnar_image' => array(
						'url' => '{{ps_assets}}images/gallery/item-8.svg',
					),
					'turnar_url' => array(
						'url' => '#',
						'is_external' => false,
						'nofollow' => false,
					),
				) ),
		),
	),
	'styles' => array(
		'turnar_center_image_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_center_image_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_center_image_object_fit' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image img' => 'object-fit: {{VALUE}};',
			),
		),
		'turnar_center_image_object_position' => array(
			'type' => 'select',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image img' => 'object-position: {{VALUE}};',
			),
		),
		'turnar_center_image_opacity' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image' => 'opacity: {{SIZE}};',
			),
		),
		'css_filters' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-center-image',
		),
		'turnar_center_image_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-center-image',
		),
		'turnar_center_image_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_center_image_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-center-image',
		),
		'turnar_center_image_opacity_hover' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image:hover' => 'opacity: {{SIZE}};',
			),
		),
		'css_filters_hover' => array(
			'type' => 'css-filter',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-center-image:hover',
		),
		'turnar_center_image_border_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-center-image:hover' => 'border-color: {{VALUE}};',
			),
		),
		'turnar_center_image_box_shadow_hover' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-center-image:hover',
		),
		'turnar_item_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-slider .ps-turnar-item' => 'width: {{SIZE}}{{UNIT}}; left: 50%; margin-left: calc(-{{SIZE}}{{UNIT}} / 2);',
			),
		),
		'turnar_item_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-slider .ps-turnar-item' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_item_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-slider .ps-turnar-item',
		),
		'turnar_item_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-slider .ps-turnar-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_item_normal_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-slider .ps-turnar-item',
		),
		'turnar_item_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-slider .ps-turnar-item:hover' => 'border-color: {{VALUE}};',
			),
		),
		'turnar_item_hover_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-slider .ps-turnar-item:hover',
		),
		'turnar_modal_bg' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-content',
		),
		'turnar_modal_content_direction' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content-wrapper' => '{{VALUE}};',
			),
		),
		'turnar_modal_content_space_between' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_modal_content_image_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content .ps-turnar-modal-image' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_modal_content_image_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content .ps-turnar-modal-image' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_modal_content_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-content .ps-turnar-modal-image',
		),
		'turnar_modal_content_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content .ps-turnar-modal-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_modal_content_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-content .ps-turnar-modal-image',
		),
		'turnar_modal_content_title_align' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content-wrapper .ps-turnar-modal-right' => 'text-align: {{VALUE}};',
			),
		),
		'turnar_modal_content_position' => array(
			'type' => 'choose',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content-wrapper .ps-turnar-modal-right' => 'align-self: {{VALUE}};',
			),
		),
		'turnar_modal_content_max_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-content-wrapper .ps-turnar-modal-right' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_modal_content_title_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-right .ps-turnar-modal-title' => 'color: {{VALUE}};',
			),
		),
		'turnar_modal_content_title_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-right .ps-turnar-modal-title' => 'margin-bottom: {{BOTTOM}}{{UNIT}};',
			),
		),
		'turnar_modal_content_title_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-right .ps-turnar-modal-title',
		),
		'turnar_modal_content_description_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-right .ps-turnar-modal-description' => 'color: {{VALUE}};',
			),
		),
		'turnar_modal_content_description_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-right .ps-turnar-modal-description' => 'margin-bottom: {{BOTTOM}}{{UNIT}};',
			),
		),
		'turnar_modal_content_description_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-right .ps-turnar-modal-description',
		),
		'turnar_modal_button_text_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-btn' => 'color: {{VALUE}};',
				'{{WRAPPER}} .ps-turnar-modal-btn svg' => 'fill: {{VALUE}};',
			),
		),
		'turnar_modal_button_bg' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-btn',
		),
		'turnar_modal_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-btn',
		),
		'turnar_modal_button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_modal_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_modal_button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-btn',
		),
		'icon_indent' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-btn .ps-turnar-modal-btn-info' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_modal_button_typography' => array(
			'type' => 'typography',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-btn span, {{WRAPPER}} .ps-turnar-modal-btn svg',
		),
		'turnar_modal_button_text_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-btn:hover' => 'color: {{VALUE}};',
				'{{WRAPPER}} .ps-turnar-modal-btn:hover svg' => 'fill: {{VALUE}};',
			),
		),
		'turnar_modal_button_bg_hover' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-btn:hover',
		),
		'turnar_modal_button_bg_hover_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-modal-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'turnar_modal_button_box_shadow_hover' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-modal-btn:hover',
		),
		'turnar_modal_close_button_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-close-btn' => 'color: {{VALUE}};',
			),
		),
		'turnar_modal_close_button_bg' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-close-btn',
		),
		'turnar_modal_close_button_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-close-btn',
		),
		'turnar_modal_close_button_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-close-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_modal_close_button_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-close-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_modal_close_button_margin' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-close-btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_modal_close_button_size' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-close-btn i' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_modal_close_button_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-close-btn',
		),
		'turnar_modal_close_button_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-close-btn:hover' => 'color: {{VALUE}};',
			),
		),
		'turnar_modal_close_button_bg_hover' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-close-btn:hover',
		),
		'turnar_modal_close_button_border_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-close-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'turnar_modal_close_button_box_shadow_hover' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-close-btn:hover',
		),
		'turnar_nav_btn_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn' => 'color: {{VALUE}};',
			),
		),
		'turnar_nav_btn_bg_color' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn',
		),
		'turnar_nav_btn_border' => array(
			'type' => 'border',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn',
		),
		'turnar_nav_btn_border_radius_adv' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_nav_btn_padding' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_arrows_size' => array(
			'type' => 'slider',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn i' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_nav_btn_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn',
		),
		'turnar_nav_btn_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn:hover' => 'color: {{VALUE}};',
			),
		),
		'turnar_nav_btn_bg_color_hover' => array(
			'type' => 'background',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn:hover',
		),
		'turnar_nav_btn_border_color_hover' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn:hover' => 'border-color: {{VALUE}};',
			),
		),
		'turnar_nav_btn_box_shadow_hover' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-navigation-buttons .ps-turnar-nav-btn:hover',
		),
		'turnar_pagination_dot_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot' => 'background-color: {{VALUE}};',
			),
		),
		'turnar_pagination_dot_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_pagination_dot_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_dots_space' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots' => 'gap: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_pagination_dot_border_radius' => array(
			'type' => 'dimensions',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		),
		'turnar_pagination_dot_box_shadow' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot',
		),
		'turnar_pagination_dot_translate_x' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot' => '--ps-turnar-dot-translate-x: {{SIZE}}{{UNIT}}; transform: translate(var(--ps-turnar-dot-translate-x, 0), var(--ps-turnar-dot-translate-y, 0));',
			),
		),
		'turnar_pagination_dot_translate_y' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot' => '--ps-turnar-dot-translate-y: {{SIZE}}{{UNIT}}; transform: translate(var(--ps-turnar-dot-translate-x, 0), var(--ps-turnar-dot-translate-y, 0));',
			),
		),
		'turnar_pagination_dot_active_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot.active' => 'background-color: {{VALUE}};',
			),
		),
		'turnar_pagination_dot_active_width' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot.active' => 'width: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_pagination_dot_active_height' => array(
			'type' => 'slider',
			'responsive' => true,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot.active' => 'height: {{SIZE}}{{UNIT}};',
			),
		),
		'turnar_pagination_dot_active_border_color' => array(
			'type' => 'color',
			'responsive' => false,
			'selectors' => array(
				'{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot.active' => 'border-color: {{VALUE}};',
			),
		),
		'turnar_pagination_dot_box_shadow_hover' => array(
			'type' => 'box-shadow',
			'group' => true,
			'selector' => '{{WRAPPER}} .ps-turnar-pagination-dots .ps-turnar-pagination-dot.active',
		),
	),
);
