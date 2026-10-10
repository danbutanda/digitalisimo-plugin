<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-image-stack` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'item_effect_transx_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-trans-x-hover: {{SIZE}}px;',
		),
		'condition' => array(
			'item_translate_toggle_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_transy_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-trans-y-hover: {{SIZE}}px;',
		),
		'condition' => array(
			'item_translate_toggle_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_rotatex_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-rotate-x-hover: {{SIZE||0}}deg;',
		),
		'condition' => array(
			'item_rotate_toggle_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_rotatey_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-rotate-y-hover: {{SIZE||0}}deg;',
		),
		'condition' => array(
			'item_rotate_toggle_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_rotatez_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-rotate-z-hover: {{SIZE||0}}deg;',
		),
		'condition' => array(
			'item_rotate_toggle_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_scalex_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-scale-x-hover: {{SIZE}};',
		),
		'condition' => array(
			'item_scale_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_scaley_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-scale-y-hover: {{SIZE}};',
		),
		'condition' => array(
			'item_scale_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_skewx_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-skew-x-hover: {{SIZE}}deg;',
		),
		'condition' => array(
			'item_skew_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_skewy_hover',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-skew-y-hover: {{SIZE}}deg;',
		),
		'condition' => array(
			'item_skew_hover' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'item_effect_transition_duration',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-transition-duration: {{VALUE}}ms;',
		),
		'default' => '300',
		'condition' => array(
			'item_effect_transition' => 'yes',
		),
	), array(
		'name' => 'item_effect_transition_delay',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-transition-delay: {{VALUE}}ms;',
		),
		'condition' => array(
			'item_effect_transition' => 'yes',
		),
	), array(
		'name' => 'item_effect_transition_easing',
		'type' => 'text',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-item-transition-easing: {{VALUE}};',
		),
		'default' => 'ease-out',
		'condition' => array(
			'item_effect_transition' => 'yes',
		),
	), array(
		'name' => 'stack_effect_transx_normal',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-stack-trans-x-normal: {{SIZE}}px;',
		),
		'condition' => array(
			'stack_translate_toggle_normal' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'stack_effect_transy_normal',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-stack-trans-y-normal: {{SIZE}}px;',
		),
		'condition' => array(
			'stack_translate_toggle_normal' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'stack_effect_rotatex_normal',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-stack-rotate-x-normal: {{SIZE||0}}deg;',
		),
		'condition' => array(
			'stack_rotate_toggle_normal' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'stack_effect_rotatey_normal',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-stack-rotate-y-normal: {{SIZE||0}}deg;',
		),
		'condition' => array(
			'stack_rotate_toggle_normal' => 'yes',
		),
		'size_units' => array( 'px' ),
	), array(
		'name' => 'stack_effect_rotatez_normal',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}}' => '--ep-stack-rotate-z-normal: {{SIZE||0}}deg;',
		),
		'condition' => array(
			'stack_rotate_toggle_normal' => 'yes',
		),
		'size_units' => array( 'px' ),
	) );
