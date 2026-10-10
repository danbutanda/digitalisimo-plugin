<?php
/** Element Pack Pro 9.9.1 `bdt-post-info` → `post-info`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'post-info',
	'classes'   => array( '.bdt-icon-list-item' => '.elementor-icon-list-item', '.bdt-post-info__item' => '.elementor-post-info__item' ),
	'defaults'  => array(
		'view' => 'inline',
		'divider_style' => 'solid',
		'divider_weight' => array(
			'size' => 1,
		),
		'divider_width' => array(
			'unit' => '%',
		),
		'divider_height' => array(
			'unit' => '%',
		),
		'divider_color' => '#ddd',
		'icon_color' => '',
		'icon_color_hover' => '',
		'icon_size' => array(
			'size' => 14,
		),
		'text_color' => '',
		'text_color_hover' => '',
	),
	'repeaters' => array(
		'icon_list' => array(
			'defaults'     => array(
				'type' => 'date',
				'date_format' => 'default',
				'custom_date_format' => 'F j, Y',
				'time_format' => 'default',
				'custom_time_format' => 'g:i a',
				'taxonomy' => array(),
				'comments_custom_strings' => false,
				'link' => 'yes',
				'show_icon' => 'custom',
			),
			'default_rows' => array( array(
					'type' => 'author',
					'selected_icon' => array(
						'value' => 'far fa-user-circle',
						'library' => 'fa-regular',
					),
				), array(
					'type' => 'date',
					'selected_icon' => array(
						'value' => 'fas fa-calendar',
						'library' => 'fa-solid',
					),
				), array(
					'type' => 'time',
					'selected_icon' => array(
						'value' => 'far fa-clock',
						'library' => 'fa-regular',
					),
				), array(
					'type' => 'comments',
					'selected_icon' => array(
						'value' => 'far fa-comment-dots',
						'library' => 'fa-regular',
					),
				) ),
		),
	),
);
