<?php
/** Element Pack Pro 9.9.1 `bdt-logo-grid` → `digitalisimo-logo-grid`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-logo-grid',
	// Clases de Element Pack más usadas en sus selectores: .bdt-item, .bdt-logo-grid--border, .bdt-logo-grid--tictactoe, .bdt-logo-grid-figure, .bdt-image-mask, .bdt-logo-grid-wrapper, .bdt-lg-col-2, .bdt-lg-col-3, .bdt-lg-col-4, .bdt-lg-col-5, .bdt-lg-col-6, .bdt-lg-col--tablet2, .bdt-lg-col--tablet3, .bdt-lg-col--tablet4
	'classes'   => array(
		'.bdt-logo-grid--border .bdt-item' => '.digi-logo-grid__item',
		'.bdt-logo-grid-figure img'        => '.digi-logo-grid__image',
		'.bdt-logo-grid-figure'            => '.digi-logo-grid__figure',
		'.bdt-logo-grid-wrapper'           => '.digi-logo-grid__list',
		'.bdt-item'                        => '.digi-logo-grid__item',
		'.bdt-logo-grid'                   => '.digi-logo-grid',
	),
	'defaults'  => array(
		'layout' => 'box',
		'columns_tablet' => 2,
		'columns_mobile' => 2,
		'column_gap' => array(
			'size' => 15,
		),
		'thumbnail_size' => 'large',
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'grid_animation_type' => '',
		'grid_anim_delay' => array(
			'unit' => 'ms',
			'size' => 300,
		),
		'logo_tooltip_x_offset' => array(
			'size' => 0,
		),
		'logo_tooltip_y_offset' => array(
			'size' => 0,
		),
		'grid_border_type' => 'solid',
		'grid_border_width' => array(
			'size' => 2,
		),
		'grid_border_width_tablet' => array(
			'size' => 2,
		),
		'grid_border_width_mobile' => array(
			'size' => 2,
		),
		'logo_tooltip_text_align' => 'center',
	),
	'repeaters' => array(
		'logo_list' => array(
			'defaults'     => array(
				'image' => array(
					'url' => $placeholder_url,
				),
				'name' => 'Brand Name',
				'description' => 'Brand Short Description Type Here.',
				'logo_tooltip_placement' => 'top',
			),
			'default_rows' => array_fill( 0, 8, array( 'image' => array( 'url' => $placeholder_url ) ) ),
		),
	),
	'drop'      => array( 'image_mask_shape', 'grid_animation_type', 'grid_anim_delay', 'logo_tooltip_animation', 'logo_tooltip_arrow', 'logo_tooltip_trigger', 'hover_animation' ),
);
