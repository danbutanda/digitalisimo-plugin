<?php
/** Element Pack Pro 9.9.1 `bdt-fancy-list` → `digitalisimo-fancy-list`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-list',
	// Clases de Element Pack más usadas en sus selectores: .bdt-fancy-list-icon, .bdt-fancy-list, .bdt-fancy-list-wrap, .bdt-fancy-list-number-icon, .bdt-fancy-list-group, .bdt-fancy-list-text, .bdt-fancy-list-img, .bdt-fancy-list-title
	'classes'   => array(
		'.bdt-fancy-list-icon' => '.digi-fancy-list__icon',
		'.bdt-fancy-list'      => '.digi-fancy-list',
	),
	'defaults'  => array(
		'layout_style' => 'style-1',
		'columns' => '1',
		'columns_tablet' => '1',
		'columns_mobile' => '1',
		'title_tags' => 'h4',
		'content_position' => 'left',
		'icon_color' => '#242424',
		'right_icon_bg_color' => '#fff',
	),
	'repeaters' => array(
		'icon_list' => array(
			'defaults'     => array(
				'text' => 'List Item',
			),
			'default_rows' => array( array(
					'text' => 'List Item #1',
				), array(
					'text' => 'List Item #2',
				), array(
					'text' => 'List Item #3',
				) ),
		),
	),
	'drop'      => array( 'list_item_align' ),
);
