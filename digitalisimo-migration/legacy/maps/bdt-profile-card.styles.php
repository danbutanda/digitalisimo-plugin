<?php
/**
 * Controles de estilo de Element Pack Pro 10.0.1 `bdt-profile-card` con selectores del widget propio, más los que sólo
 * tenía 9.9.1 y siguen vivos. Generado con scripts/legacy-styles.py --merge; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'profile_card_header_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-author-box__header',
		'types' => array( 'gradient' ),
	), array(
		'name' => 'profile_card_header_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'profile_card_header_padding',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__header' => 'padding-bottom: {{SIZE}}{{UNIT}};',
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
			'{{WRAPPER}} .digi-author-box__header' => 'padding-right: {{SIZE}}{{UNIT}};',
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
			'{{WRAPPER}} .digi-author-box__badge' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'profile_badge_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-author-box__badge',
	), array(
		'name' => 'profile_badge_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'profile_badge_text_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'profile_badge_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-author-box__badge',
	), array(
		'name' => 'profile_card_inner_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .elementor-author-box',
		'types' => array( 'classic', 'gradient' ),
	), array(
		'name' => 'profile_card_inner_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .elementor-author-box',
	), array(
		'name' => 'profile_card_inner_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'profile_card_inner_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'profile_card_inner_margin',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'profile_inner_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .elementor-author-box',
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
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'icon_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'social_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-author-box__social-link',
	), array(
		'name' => 'social_icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'social_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'social_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link:hover' => 'color: {{VALUE}}',
		),
	), array(
		'name' => 'icon_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link:hover' => 'background-color: {{VALUE}}',
		),
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__social-link:hover' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'social_icon_border_border!' => '',
		),
	), array(
		'name' => 'settings_dropdown_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__menu-link' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'settings_dropdown_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__menu-link:hover' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'profile_card_name_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__name' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'name_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .elementor-author-box__name',
	), array(
		'name' => 'profile_card_name_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .elementor-author-box__name' => 'padding-top: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'profile_card_username_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__username' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'username_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-author-box__username',
	), array(
		'name' => 'profile_card_stat_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__stat' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'stat_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-author-box__stat',
	), array(
		'name' => 'profile_card_label_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-author-box__stat-label' => 'color: {{VALUE}};',
		),
		'default' => '',
	), array(
		'name' => 'label_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-author-box__stat-label',
	) );
