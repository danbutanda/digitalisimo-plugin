<?php
/** Element Pack Pro 9.9.1 `bdt-advanced-heading` → `digitalisimo-advanced-heading`. */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'digitalisimo-advanced-heading',
	'classes'  => array(
		'.bdt-ep-advanced-heading-main-title-inner'      => '.digi-advanced-heading__main',
		'.bdt-ep-advanced-heading-main-title .bdt-mainh-split-text' => '.digi-advanced-heading__split',
		'.bdt-mainh-split-text'                          => '.digi-advanced-heading__split',
		'.bdt-ep-advanced-heading-content > div'         => '.digi-advanced-heading__decoration',
		'.bdt-ep-advanced-heading-content'               => '.digi-advanced-heading__decoration',
		'.bdt-ep-advanced-heading-sub-title'             => '.digi-advanced-heading__sub',
		'.bdt-ep-advanced-heading-main-title'            => '.digi-advanced-heading__title',
		'.bdt-ep-advanced-heading-title'                 => '.digi-advanced-heading__title',
		'.bdt-ep-advanced-heading'                       => '.digi-advanced-heading',
	),
	'strip'    => array( 'sub_heading', 'main_heading', 'split_text', 'advanced_heading' ),
	'defaults' => array(
		'sub_heading'                 => 'SUB HEADING HERE',
		'main_heading'                => 'I am Advanced Heading',
		'split_text'                  => 'Split Text',
		'header_size'                 => 'h2',
		'advanced_heading_visibility' => 'yes',
		'advanced_heading'            => 'Advanced Heading',
	),
	'drop'     => array( 'title_multi_color', 'advanced_heading_origin', 'advanced_heading_hide' ),
);
