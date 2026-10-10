<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-image-accordion` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'image_accordion_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'width: {{SIZE}}{{UNIT}};',
		),
	), array(
		'name' => 'items_content_position',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item' => 'flex-direction: {{VALUE}};',
		),
		'default' => 'row',
		'condition' => array(
			'skin_type' => 'sliding-box',
		),
		'options' => array(
			'row-reverse' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'row' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
		),
	), array(
		'name' => 'items_content_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item' => 'text-align: {{VALUE}};',
		),
		'options' => array(
			'left' => array(
				'title' => 'Left',
				'icon' => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-h-align-center',
			),
			'right' => array(
				'title' => 'Right',
				'icon' => 'eicon-h-align-right',
			),
			'justify' => array(
				'title' => 'Justified',
				'icon' => 'eicon-h-align-stretch',
			),
		),
	), array(
		'name' => 'items_content_vertical_align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'justify-content: {{VALUE}};',
		),
		'options' => array(
			'flex-start' => array(
				'title' => 'Top',
				'icon' => 'eicon-v-align-top',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-v-align-middle',
			),
			'flex-end' => array(
				'title' => 'Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'active_item_expand',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item.active' => 'flex: {{SIZE}};',
		),
		'default' => array(
			'size' => 6,
		),
		'tablet_default' => array(
			'size' => 6,
		),
		'mobile_default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'image_accordion_overlay_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:before' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'skin_type!' => 'sliding-box',
		),
	), array(
		'name' => 'content_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__item',
	), array(
		'name' => 'tabs_content_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'image_accordion_divider_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:after' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'skin_type!' => 'sliding-box',
			'enable_item_style' => '',
		),
	), array(
		'name' => 'image_accordion_divider_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:after' => 'width: {{SIZE}}{{UNIT}}; right: calc(-{{SIZE}}{{UNIT}} / 2);',
		),
		'condition' => array(
			'skin_type' => array( 'default' ),
			'enable_item_style' => '',
		),
	), array(
		'name' => 'image_accordion_divider_width_skin',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:after' => '--ep-divider-width: {{SIZE}}{{UNIT}}; --ep-divider-bottom: -{{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'skin_type' => array( 'vertical' ),
			'enable_item_style' => '',
		),
	), array(
		'name' => 'item_column_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item:after' => 'width: 0; right: 0; --ep-divider-width: 0; --ep-divider-bottom: -0;',
		),
		'condition' => array(
			'enable_item_style' => 'yes',
		),
	), array(
		'name' => 'item_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-accordion__item',
	), array(
		'name' => 'item_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'enable_item_style' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__title' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-accordion__title',
	), array(
		'name' => 'title_text_stroke',
		'group' => 'text-stroke',
		'selector' => '{{WRAPPER}} .digi-accordion__title',
	), array(
		'name' => 'sub_title_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__title' => 'margin-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'sub_title_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-accordion__title',
	), array(
		'name' => 'description_spacing',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		),
	), array(
		'name' => 'description_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-accordion__content',
	), array(
		'name' => 'button_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__content a a',
	), array(
		'name' => 'button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-accordion__content a a',
	), array(
		'name' => 'button_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content a a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'border_radius_advanced_show!' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'border_radius_advanced',
		'type' => 'text',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content a a' => 'border-radius: {{VALUE}}; overflow: hidden;',
		),
		'default' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'condition' => array(
			'border_radius_advanced_show' => 'yes',
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content a a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-accordion__content a a',
	), array(
		'name' => 'button_box_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-accordion__content a a',
	), array(
		'name' => 'button_hover_background',
		'group' => 'background',
		'selector' => '{{WRAPPER}} .digi-accordion__content a a:hover',
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-accordion__content a a:hover' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_border_border!' => '',
		),
	) );
