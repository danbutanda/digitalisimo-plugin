<?php
/**
 * Controles de estilo de Element Pack Pro 9.9.1 `bdt-instagram-feed` con selectores del widget propio.
 * Generado con scripts/legacy-styles.py; no editar a mano.
 */
defined( 'ABSPATH' ) || exit;

return array( array(
		'name' => 'columns',
		'type' => 'select',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #sbi_images' => 'grid-template-columns: repeat({{SIZE}}, 1fr) !important;',
		),
		'desktop_default' => 4,
		'tablet_default' => 2,
		'mobile_default' => 1,
		'options' => array(
			1 => '1',
			2 => '2',
			3 => '3',
			4 => '4',
			5 => '5',
			6 => '6',
		),
	), array(
		'name' => 'columns_gap',
		'type' => 'slider',
		'responsive' => true,
		'selectors' => array(
			'{{WRAPPER}} #sbi_images' => 'gap: {{SIZE}}{{UNIT}} !important;',
		),
		'default' => array(
			'size' => 10,
		),
	), array(
		'name' => 'imagepadding',
		'type' => 'slider',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .sbi_header_text' => 'gap: {{SIZE}}{{UNIT}} !important;',
		),
		'default' => array(
			'size' => 20,
		),
	), array(
		'name' => 'headercolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .sbi_feedtheme_header_text > *' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'buttoncolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .sbi_load_btn' => 'background-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'buttontextcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .sbi_load_btn' => 'color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'followcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .sbi_follow_btn a' => 'background-color: {{VALUE}} !important;',
		),
	), array(
		'name' => 'followtextcolor',
		'type' => 'color',
		'responsive' => false,
		'selectors' => array(
			'{{WRAPPER}} .sbi_follow_btn a' => 'color: {{VALUE}} !important;',
		),
	) );
