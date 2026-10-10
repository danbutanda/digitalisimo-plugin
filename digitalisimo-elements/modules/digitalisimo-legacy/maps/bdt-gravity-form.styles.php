<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-gravity-form` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'validation_error_field_input_border_color',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .gform_wrapper li.gfield_error textarea' => 'border-color: {{VALUE}}',
		),
	), array(
		'name' => 'validation_error_field_input_border_width',
		'type' => 'number',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .gform_wrapper li.gfield_error textarea' => 'border-width: {{VALUE}}px',
		),
		'default' => 1,
	) );
