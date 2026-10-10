<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-creative-button` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'shape_alignment',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--elon:before' => '{{VALUE}}',
		),
		'default' => 'left',
		'selectors_dictionary' => array(
			'left' => 'left: 0; right: auto; transform: translateX(0px);',
			'right' => 'left: auto; right: 0; transform: translateX(0px);',
			'center' => 'left: 50%; transform: translateX(-50%);',
		),
		'condition' => array(
			'button_style' => array( 'elon' ),
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
		),
	), array(
		'name' => 'gooey_direction',
		'type' => 'choose',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--gooey .c-button__blobs div' => '{{VALUE}}',
		),
		'default' => 'top',
		'selectors_dictionary' => array(
			'top' => 'transform: scale(1.4) translateY(125%) translateZ(0);',
			'bottom' => 'transform: scale(1.4) translateY(-125%) translateZ(0);',
		),
		'condition' => array(
			'button_style' => array( 'gooey' ),
		),
		'options' => array(
			'top' => array(
				'title' => 'To Top',
				'icon' => 'eicon-v-align-top',
			),
			'bottom' => array(
				'title' => 'To Bottom',
				'icon' => 'eicon-v-align-bottom',
			),
		),
	), array(
		'name' => 'creative_button_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button, {{WRAPPER}} .digi-creative-button--dione span' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--glitch::after' => 'color: {{VALUE}}; text-shadow: -2px -3px 0 {{VALUE}}, 2px 3px 0 {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'surtur' ),
		),
	), array(
		'name' => 'creative_button_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--fenrir .progress__circle, {{WRAPPER}} .digi-creative-button--fenrir .progress__path' => 'stroke: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--janus::after' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'fenrir', 'janus' ),
		),
	), array(
		'name' => 'creative_button_stroke_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--surtur svg *' => 'stroke: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'surtur' ),
		),
	), array(
		'name' => 'creative_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button, {{WRAPPER}} .digi-creative-button--anthe::before, {{WRAPPER}} .digi-creative-button--bestia .digi-creative-button__bg, {{WRAPPER}} .digi-creative-button--dione::before, {{WRAPPER}} .digi-creative-button--greip::before, {{WRAPPER}} .digi-creative-button--hyperion::before, {{WRAPPER}} .digi-creative-button--janus::before, {{WRAPPER}} .digi-creative-button--mimas::before, {{WRAPPER}} .digi-creative-button--narvi::before, {{WRAPPER}} .digi-creative-button--pan::before, {{WRAPPER}} .digi-creative-button--pandora span, {{WRAPPER}} .digi-creative-button--rhea::before, {{WRAPPER}} .digi-creative-button--skoll::before' => 'background: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--dione::after' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--elon::before' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--reveal:hover' => 'background: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-creative-button--glitch, {{WRAPPER}} .digi-creative-button--glitch::after' => 'background: linear-gradient(45deg, transparent 5%, {{VALUE}} 5%);',
			'{{WRAPPER}} .digi-creative-button--gooey:hover' => 'background: {{VALUE}} !important;',
			'{{WRAPPER}} .digi-creative-button--aura:before' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'hati', 'surtur', 'reklo' ),
		),
	), array(
		'name' => 'secondary_creative_button_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button.digi-creative-button--pandora' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'pandora' ),
		),
	), array(
		'name' => 'creative_button_helene_shadow_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--helene::before' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'helene', 'glitch' ),
		),
	), array(
		'name' => 'creative_button_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-creative-button, {{WRAPPER}} .digi-creative-button--bestia .digi-creative-button__bg, {{WRAPPER}} .digi-creative-button--elon:before',
	), array(
		'name' => 'creative_button_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button, {{WRAPPER}} .digi-creative-button--bestia .digi-creative-button__bg, {{WRAPPER}} .digi-creative-button--pandora span, {{WRAPPER}} .digi-creative-button--dione::before, {{WRAPPER}} .digi-creative-button--dione::after, {{WRAPPER}} .digi-creative-button--elon::before' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'janus', 'surtur', 'narvi', 'reklo', 'glitch' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'creative_button_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button, {{WRAPPER}} .digi-creative-button--bestia .digi-creative-button__bg span, {{WRAPPER}} .digi-creative-button-marquee span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'janus', 'surtur', 'pandora', 'rhea', 'reklo' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'creative_button_pandora_padding',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--pandora span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_style' => array( 'pandora' ),
		),
		'size_units' => array( 'px', 'em', '%' ),
	), array(
		'name' => 'creative_button_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-creative-button, {{WRAPPER}} .digi-creative-button--bestia .digi-creative-button__bg',
	), array(
		'name' => 'creative_button_typography',
		'group' => 'typography',
		'selector' => '{{WRAPPER}} .digi-creative-button, {{WRAPPER}} .digi-creative-button--glitch::after',
	), array(
		'name' => 'creative_button_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--surtur .textcircle' => 'width: {{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-creative-button--elon:before' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'button_style' => array( 'surtur', 'elon' ),
		),
	), array(
		'name' => 'creative_button_aura_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--aura>span:nth-child(1):before, {{WRAPPER}} .digi-creative-button--aura>span:nth-child(1):after, {{WRAPPER}} .digi-creative-button--aura>span:nth-child(2):before, {{WRAPPER}} .digi-creative-button--aura>span:nth-child(2):after' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'aura' ),
			'creative_button_aura_line_toggle' => 'yes',
		),
	), array(
		'name' => 'creative_button_aura_line_width',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--aura>span:before, {{WRAPPER}} .digi-creative-button--aura>span:after' => 'width: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'button_style' => array( 'aura' ),
			'creative_button_aura_line_toggle' => 'yes',
		),
	), array(
		'name' => 'creative_button_aura_line_height',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--aura>span:before, {{WRAPPER}} .digi-creative-button--aura>span:after' => 'height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'button_style' => array( 'aura' ),
			'creative_button_aura_line_toggle' => 'yes',
		),
	), array(
		'name' => 'creative_button_aura_line_offset',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--aura>span:nth-child(1):after' => 'left: calc(100% - {{SIZE}}{{UNIT}}); top: calc(100% - {{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-creative-button--aura>span:nth-child(1):before' => 'left: {{SIZE}}{{UNIT}}; top:{{SIZE}}{{UNIT}};',
			'{{WRAPPER}} .digi-creative-button--aura>span:nth-child(2):before' => 'top: {{SIZE}}{{UNIT}}; left: calc(100% - {{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-creative-button--aura>span:nth-child(2):after' => 'left: {{SIZE}}{{UNIT}}; top: calc(100% - {{SIZE}}{{UNIT}});',
		),
		'condition' => array(
			'button_style' => array( 'aura' ),
			'creative_button_aura_line_toggle' => 'yes',
		),
	), array(
		'name' => 'creative_button_aura_clip_path',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--aura:before' => 'clip-path: polygon({{SIZE}}{{UNIT}} 0, 100% 0, 100% 0, 100% calc(100% - {{SIZE}}{{UNIT}}), calc(100% - {{SIZE}}{{UNIT}}) 100%, 0 100%, 0 100%, 0 {{SIZE}}{{UNIT}});',
			'{{WRAPPER}} .digi-creative-button--aura:hover:before' => 'clip-path: polygon(0 0, calc(100% - {{SIZE}}{{UNIT}}) 0, 100% {{SIZE}}{{UNIT}}, 100% 100%, 100% 100%, {{SIZE}}{{UNIT}} 100%, 0 calc(100% - {{SIZE}}{{UNIT}}), 0 0);',
		),
		'condition' => array(
			'button_style' => array( 'aura' ),
			'creative_button_aura_line_toggle' => 'yes',
		),
	), array(
		'name' => 'creative_button_hover_text_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button:hover, {{WRAPPER}} .digi-creative-button--dione:hover span' => 'color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--glitch:hover::after' => 'color: {{VALUE}}; text-shadow: -2px -3px 0 {{VALUE}}, 2px 3px 0 {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'surtur' ),
		),
	), array(
		'name' => 'creative_button_aura_line_hover_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--aura:hover>span:nth-child(1):before, {{WRAPPER}} .digi-creative-button--aura:hover>span:nth-child(1):after, {{WRAPPER}} .digi-creative-button--aura:hover>span:nth-child(2):before, {{WRAPPER}} .digi-creative-button--aura:hover>span:nth-child(2):after' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'aura' ),
		),
	), array(
		'name' => 'creative_button_hover_stroke_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--surtur:hover svg *' => 'stroke: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'surtur' ),
		),
	), array(
		'name' => 'creative_button_hover_line_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--fenrir .progress__path' => 'stroke: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--janus:hover::after' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'fenrir', 'janus', 'pandora', 'narvi' ),
		),
	), array(
		'name' => 'creative_button_hover_background_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button:hover, {{WRAPPER}} .digi-creative-button--anthe:hover::before, {{WRAPPER}} .digi-creative-button--bestia .digi-creative-button__bg::before, {{WRAPPER}} .digi-creative-button--bestia .digi-creative-button__bg::after, {{WRAPPER}} .digi-creative-button--calypso::before, {{WRAPPER}} .digi-creative-button--calypso::after, {{WRAPPER}} .digi-creative-button--dione:hover::before, {{WRAPPER}} .digi-creative-button--greip, {{WRAPPER}} .digi-creative-button--hyperion, {{WRAPPER}} .digi-creative-button--janus:hover::before, {{WRAPPER}} .digi-creative-button--mimas, {{WRAPPER}} .digi-creative-button--narvi:hover::before, {{WRAPPER}} .digi-creative-button--pan, {{WRAPPER}} .digi-creative-button--pandora:hover span, {{WRAPPER}} .digi-creative-button--rhea:hover::before, {{WRAPPER}} .digi-creative-button--skoll, {{WRAPPER}} .digi-creative-button--telesto::before, {{WRAPPER}} .digi-creative-button--telesto::after' => 'background: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--dione:hover::after' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--elon:hover::before' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--reveal::after' => 'background: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--glitch:hover, {{WRAPPER}} .digi-creative-button--glitch:hover::after' => 'background: linear-gradient(45deg, transparent 5%, {{VALUE}} 5%);',
			'{{WRAPPER}} .digi-creative-button--gooey .c-button__blobs div' => 'background-color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--aura:hover:before' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style!' => array( 'fenrir', 'hati', 'surtur', 'reklo' ),
		),
	), array(
		'name' => 'secondary_creative_button_background_hover',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button.digi-creative-button--pandora:hover' => 'background: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'pandora' ),
		),
	), array(
		'name' => 'button_hover_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button:hover, {{WRAPPER}} .digi-creative-button--bestia:hover .digi-creative-button__bg' => 'border-color: {{VALUE}};',
			'{{WRAPPER}} .digi-creative-button--elon:hover:before' => 'border-color: {{VALUE}};',
		),
		'condition' => array(
			'creative_button_border_border!' => '',
			'button_style!' => array( 'fenrir', 'janus', 'surtur', 'narvi', 'reklo', 'glitch' ),
		),
	), array(
		'name' => 'creative_button_hover_shadow',
		'group' => 'box-shadow',
		'selector' => '{{WRAPPER}} .digi-creative-button:hover, {{WRAPPER}} .digi-creative-button--bestia:hover .digi-creative-button__bg',
	), array(
		'name' => 'creative_button_hover_icon_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--reklo:hover i' => 'color: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'reklo' ),
		),
	), array(
		'name' => 'creative_button_hover_icon_bg_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--reklo i' => 'background-color: {{VALUE}};',
		),
		'condition' => array(
			'button_style' => array( 'reklo' ),
		),
	), array(
		'name' => 'icon_border',
		'group' => 'border',
		'selector' => '{{WRAPPER}} .digi-creative-button--reklo i',
	), array(
		'name' => 'icon_border_radius',
		'type' => 'dimensions',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--reklo i' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		),
		'condition' => array(
			'button_style' => array( 'reklo' ),
		),
		'size_units' => array( 'px', '%' ),
	), array(
		'name' => 'icon_size',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--reklo i' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'button_style' => array( 'reklo' ),
		),
	), array(
		'name' => 'icon_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} .digi-creative-button--reklo' => 'gap: {{SIZE}}{{UNIT}};',
		),
		'condition' => array(
			'button_style' => array( 'reklo' ),
		),
	) );
