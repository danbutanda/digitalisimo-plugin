<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-profile-card` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'profile_card_header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-author-box .elementor-author-box-header',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'profile_card_header_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'profile_card_header_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-header' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => '',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'profile_card_skin_header_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-header' => 'padding-right: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => 'heline',
		),
		'size_units' => array( 'px', 'em' ),
	), array(
		'name' => 'profile_badge_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box-pro span' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'profile_badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-author-box-pro span',
	), array(
		'name' => 'profile_badge_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box-pro span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'profile_badge_text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box-pro span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'profile_badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-author-box-pro span',
	), array(
		'name' => 'settings_menu_size',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box-settings i' => 'font-size: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'settings_icon_color',
		'type' => 'color',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box-settings i' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'profile_card_user_menu_left_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box.skin-heline .elementor-author-box-settings' => 'left: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => '',
		),
	), array(
		'name' => 'profile_card_user_menu_top_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box.skin-heline .elementor-author-box-settings' => 'top: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => '',
		),
	), array(
		'name' => 'profile_card_inner_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-author-box .elementor-author-box-inner',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'profile_card_inner_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-author-box .elementor-author-box-inner',
	), array(
		'name' => 'profile_card_inner_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-inner' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'profile_card_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'profile_card_inner_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-inner' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'profile_inner_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-author-box .elementor-author-box-inner',
	), array(
		'name' => 'profile_card_image_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; margin-left: auto;margin-right: auto;',
		),
		'default' => array(
			'unit' => 'px',
		),
		'tablet_default' => array(
			'unit' => 'px',
		),
		'mobile_default' => array(
			'unit' => 'px',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'image_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-author-box__avatar img',
	), array(
		'name' => 'image_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__avatar img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'profile_card_image_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__avatar img' => 'margin-top: -{{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .elementor-author-box.skin-heline .elementor-author-box-image' => 'left: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'profile_card_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__bio' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-author-box__bio',
	), array(
		'name' => 'profile_card_text_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__bio' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'profile_card_label_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__bio' => 'padding-bottom: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'follow_button_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-button' => 'margin-top: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__button' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-author-box__button',
		'types' => array( 'classic', 'gradient', 'video' ),
	), array(
		'name' => 'button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-author-box__button',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-author-box__button',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-author-box__button',
	), array(
		'name' => 'button_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__button:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'button_background_hover_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-author-box__button:hover',
		'types' => array( 'classic', 'gradient', 'video' ),
	), array(
		'name' => 'button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-author-box__button:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__button:hover' => 'border-color: {{VALUE}};',
		),
	), array(
		'name' => 'social_icon_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-button' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;',
		),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a' => 'color: {{VALUE}}',
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a svg' => 'fill: {{VALUE}}',
		),
	), array(
		'name' => 'icon_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'social_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a',
	), array(
		'name' => 'social_icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'social_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'social_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'social_icon_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a + a' => 'margin-left: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'social_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link:before, {{WRAPPER}} .elementor-author-box .elementor-author-box-share-link:after' => 'background: {{VALUE}}',
		),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a:hover' => 'color: {{VALUE}}',
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a:hover svg' => 'fill: {{VALUE}}',
		),
	), array(
		'name' => 'icon_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a:hover' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box .elementor-author-box-share-link a:hover' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'social_icon_border_border!' => '',
		),
	) );
