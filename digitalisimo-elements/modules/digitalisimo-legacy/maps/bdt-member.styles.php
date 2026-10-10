<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-member` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'skin_partait_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-partait .digi-fancy-card-desc-wrapper' => 'text-align: {{VALUE}} !important;',
		),
		'condition' => array(
			'_skin' => 'bdt-partait',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'band_overlay_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-band .digi-fancy-card-photo:before' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'_skin' => array( 'bdt-band' ),
		),
	), array(
		'name' => 'band_item_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-band .digi-fancy-card-item-wrapper' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'_skin' => array( 'bdt-band' ),
		),
	), array(
		'name' => 'member_bg_color',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-fancy-card',
	), array(
		'name' => 'member_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card',
	), array(
		'name' => 'member_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'member_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'desc_padding',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'skin_flip_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-flip' => 'height: {{SIZE}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'vh' ),
	), array(
		'name' => 'flip_text_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-flip .digi-fancy-card-content' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	), array(
		'name' => 'photo_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-photo' => 'background-color: {{VALUE}};',
		),
	), array(
		'name' => 'photo_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-photo',
	), array(
		'name' => 'photo_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-photo' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'photo_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-photo img' => 'opacity: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'photo_spacing',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-photo' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
		'condition' => array(
			'_skin!' => array( 'bdt-band' ),
		),
	), array(
		'name' => 'photo_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-photo:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'border_border!' => '',
		),
	), array(
		'name' => 'photo_hover_opacity',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-photo:hover img' => 'opacity: {{SIZE}};',
		),
		'default' => array(
			'size' => 1,
		),
	), array(
		'name' => 'name_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-name' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'name_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-name',
	), array(
		'name' => 'name_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card-name' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-ekip',
		),
	), array(
		'name' => 'ekip_name_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-ekip:hover .digi-fancy-card-name' => 'top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => '%',
		),
		'condition' => array(
			'_skin' => 'bdt-ekip',
		),
		'size_units' => array( '%' ),
	), array(
		'name' => 'role_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-role' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'role_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-role' => 'margin-bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-ekip',
		),
	), array(
		'name' => 'ekip_role_bottom_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-ekip:hover .digi-fancy-card-role' => 'top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => '%',
		),
		'condition' => array(
			'_skin' => 'bdt-ekip',
		),
		'size_units' => array( '%' ),
	), array(
		'name' => 'role_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-role',
	), array(
		'name' => 'text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text' => 'color: {{VALUE}};',
		),
	), array(
		'name' => 'text_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-text',
	), array(
		'name' => 'icon_content_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icons' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'_skin!' => 'bdt-band',
		),
	), array(
		'name' => 'social_icon_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icons' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-band',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'icon_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'_skin!' => 'bdt-band',
		),
	), array(
		'name' => 'band_icon_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .skin-band .digi-fancy-card-icons .digi-fancy-card-icon:before' => 'background: {{VALUE}}',
		),
		'condition' => array(
			'_skin' => 'bdt-band',
		),
	), array(
		'name' => 'icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'social_icons_top_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icons' => 'border-top-color: {{VALUE}}',
		),
		'condition' => array(
			'_skin!' => 'bdt-band',
		),
	), array(
		'name' => 'social_icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon',
	), array(
		'name' => 'social_icon_border_radius',
		'type' => 'dimensions',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-band',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'social_icon_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'_skin!' => 'bdt-band',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'social_icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon i' => 'min-width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon i:before' => 'font-size: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon svg' => 'font-size: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'social_icon_box_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-band .digi-fancy-card-icon:before, {{WRAPPER}} .skin-band .digi-fancy-card-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => 'bdt-band',
		),
	), array(
		'name' => 'social_icon_indent',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon + .digi-fancy-card-icon' => 'margin-left: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'social_icon_vertical_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-band .digi-fancy-card-icons' => 'bottom: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'_skin' => 'bdt-band',
		),
	), array(
		'name' => 'ekip_icon_vertical_space',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .skin-ekip:hover .digi-fancy-card-icons' => 'top: {{SIZE}}{{UNIT}};',
		),
		'default' => array(
			'unit' => '%',
		),
		'condition' => array(
			'_skin' => 'bdt-ekip',
		),
		'size_units' => array( '%' ),
	), array(
		'name' => 'icon_hover_background',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon:hover' => 'background-color: {{VALUE}}',
		),
		'condition' => array(
			'_skin!' => 'bdt-band',
		),
	), array(
		'name' => 'icon_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon:hover i' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon:hover svg' => 'fill: {{VALUE}};',
		),
	), array(
		'name' => 'icon_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-fancy-card .digi-fancy-card-icon:hover' => 'border-color: {{VALUE}}',
		),
		'condition' => array(
			'social_icon_border_border!' => '',
			'_skin!' => 'bdt-band',
		),
	) );
