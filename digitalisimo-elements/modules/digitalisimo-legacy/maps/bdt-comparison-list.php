<?php
/** Element Pack Pro 9.9.1 `bdt-comparison-list` → `digitalisimo-comparison-list` (tabla semántica, mismos nombres de datos). */
defined( 'ABSPATH' ) || exit;

$lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

return array(
	'target'    => 'digitalisimo-comparison-list',
	'classes'   => array(
		'.bdt-comparison-head-title-item .bdt-comparison-head-button' => '.digi-comparison-list__plan a',
		'.bdt-comparison-head-title-item.bdt-comparison-head-feature-title' => '.digi-comparison-list thead th:first-child',
		'.bdt-comparison-list-wrap .bdt-comparison-item-title' => '.digi-comparison-list tbody th',
		'.bdt-comparison-list-wrap .bdt-comparison-item-text'  => '.digi-comparison-list tbody td',
		'.bdt-comparison-list-wrap .bdt-comparison-sub-title'  => '.digi-comparison-list__plan small',
		'.bdt-comparison-list-wrap .bdt-comparison-item'       => '.digi-comparison-list tbody tr',
		'.bdt-comparison-head-title'  => '.digi-comparison-list__plan > span',
		'.bdt-compatison-header'      => '.digi-comparison-list thead',
		'.bdt-comparison-list-wrap'   => '.digi-comparison-list',
	),
	'strip'     => array( 'comparison_list_title' ),
	'defaults'  => array( 'comparison_list_title' => 'Feature list' ),
	'repeaters' => array(
		'comparison_header_list' => array(
			'defaults'     => array(),
			'default_rows' => array( array( 'header_title' => 'Free', 'header_active' => 'no' ), array( 'header_title' => 'Pro', 'header_active' => 'yes' ) ),
		),
		'comparison_list'        => array(
			'defaults'     => array( 'title' => 'Title', 'feature_ability' => '0|0' ),
			'default_rows' => array(
				array( 'title' => 'Feature Title #1', 'description' => '#1 ' . $lorem, 'feature_ability' => '0|1' ),
				array( 'title' => 'Feature Title #2', 'description' => '#2 ' . $lorem, 'feature_ability' => '0|1' ),
				array( 'title' => 'Feature Title #3', 'description' => '#3 ' . $lorem, 'feature_ability' => '1|1' ),
			),
		),
	),
	'drop'      => array( 'comparison_notice' ),
);
