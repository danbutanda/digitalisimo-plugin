<?php
/** Element Pack Pro 9.9.1 `bdt-total-count` → `digitalisimo-total-count`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-total-count',
	// Clases de Element Pack más usadas en sus selectores: .bdt-total-count, .bdt-total-count-icon-wrapper, .bdt-total-count-content, .bdt-total-count-icon, .bdt-total-count-number, .bdt-total-count-content-text, .bdt-number-separator, .bdt-number-separator-wrapper, .bdt-icon-heading
	'classes'   => array( '.bdt-total-count-number' => '.digi-total-count__number', '.bdt-total-count-content' => '.digi-total-count__label', '.bdt-total-count' => '.digi-total-count' ),
	'defaults'  => array(
		'count_type' => 'comment',
		'comment_count_type' => 'total',
		'custom_post_type' => 'post',
		'user_roles' => 'bdt-all-users',
		'icon_type' => 'icon',
		'selected_icon' => array(
			'value' => 'fas fa-star',
			'library' => 'fa-solid',
		),
		'image' => array(
			'url' => $placeholder_url,
		),
		'count_start' => 0,
		'fake_count' => 0,
		'content_text' => 'Total Count',
		'counter_number_size' => 'h4',
		'position' => 'top',
		'icon_vertical_alignment' => 'top',
		'top_icon_vertical_offset' => array(
			'size' => 0,
		),
		'top_icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'top_icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'language_input' => '0,1,2,3,4,5,6,7,8,9',
		'decimal_symbol' => '.',
		'decimal_places' => '0',
		'duration' => '2',
		'use_easing' => 'yes',
		'use_grouping' => 'no',
		'counter_separator' => ',',
		'icon_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'icon_background_rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'background_hover_transition' => array(
			'size' => 0.3,
		),
		'icon_effect' => 'none',
		'icon_hover_rotate' => array(
			'unit' => 'deg',
		),
		'icon_hover_background_rotate' => array(
			'unit' => 'deg',
		),
		'counter_number_separator_type' => 'line',
		'counter_number_separator_border_style' => 'solid',
	),
	'rename'    => array( 'count_type' => 'source', 'custom_post_type' => 'post_type', 'content_text' => 'label', 'fake_count' => 'extra_count' ),
	'drop'      => array( 'comment_count_type', 'user_roles', 'show_icon', 'icon_type', 'selected_icon', 'image', 'count_start', 'show_separator', 'counter_number_size', 'position' ),
);
