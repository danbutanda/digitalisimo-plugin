<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-user-register` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'align',
		'type' => 'choose',
		'responsive' => true,
		'selectors' => array(
			'#modal{{ID}} .elementor-field-type-submit' => 'justify-content: {{VALUE}};',
		),
		'selectors_dictionary' => array(
			'start' => 'justify-content: start;',
			'center' => 'justify-content: center;',
			'end' => 'justify-content: end;',
			'stretch' => 'width: 100%;',
		),
		'options' => array(
			'start' => array(
				'title' => 'Left',
				'icon' => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => 'Center',
				'icon' => 'eicon-text-align-center',
			),
			'end' => array(
				'title' => 'Right',
				'icon' => 'eicon-text-align-right',
			),
			'stretch' => array(
				'title' => 'Justified',
				'icon' => 'eicon-text-align-justify',
			),
		),
	) );
