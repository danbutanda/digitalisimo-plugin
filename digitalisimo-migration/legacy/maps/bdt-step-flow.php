<?php
/** Element Pack Pro 9.9.1 `bdt-step-flow` → `digitalisimo-advanced-icon-box`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-advanced-icon-box',
	'classes'   => array(
		'.bdt-step-flow-readmore'      => '.digi-advanced-icon-box__more',
		'.bdt-step-flow-content'       => '.digi-advanced-icon-box__content',
		'.bdt-step-flow-badge'         => '.digi-advanced-icon-box__badge',
		'.bdt-step-flow-title'         => '.digi-advanced-icon-box__title',
		'.bdt-step-flow-description'   => '.digi-advanced-icon-box__description',
		'.bdt-step-flow-icon'          => '.digi-advanced-icon-box__visual',
		'.bdt-title-separator-wrapper' => '.digi-advanced-icon-box__separator',
		'.bdt-title-separator'         => '.digi-advanced-icon-box__separator',
		'.bdt-icon-wrapper'            => '.digi-advanced-icon-box__visual',
		'.bdt-step-flow'               => '.digi-advanced-icon-box',
	),
	'defaults'  => array(
		'icon_type' => 'icon',
		'selected_icon' => array(
			'value' => 'fas fa-directions',
			'library' => 'fa-solid',
		),
		'image' => array(
			'url' => $placeholder_url,
		),
		'thumbnail_size_size' => 'full',
		'title_text' => 'Step Flow Heading',
		'description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
		'title_size' => 'h3',
		'badge' => 'yes',
		'show_indicator' => 'yes',
		'readmore_text' => 'Read More',
		'readmore_link' => array(
			'url' => '#',
		),
		'readmore_icon_align' => 'right',
		'readmore_icon_indent' => array(
			'size' => 8,
		),
		'readmore_horizontal_offset' => array(
			'size' => -50,
		),
		'readmore_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'readmore_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'readmore_vertical_offset' => array(
			'size' => 0,
		),
		'readmore_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'readmore_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'button_css_id' => '',
		'badge_text' => 'Step 01',
		'badge_position' => 'top-center',
		'badge_horizontal_offset' => array(
			'size' => 0,
		),
		'badge_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'badge_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'badge_vertical_offset' => array(
			'size' => 0,
		),
		'badge_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'badge_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'badge_rotate' => array(
			'size' => 0,
		),
		'badge_rotate_tablet' => array(
			'size' => 0,
		),
		'badge_rotate_mobile' => array(
			'size' => 0,
		),
		'icon_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'icon_space' => array(
			'size' => 15,
		),
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
		'icon_horizontal_offset' => array(
			'size' => 0,
		),
		'icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'icon_vertical_offset' => array(
			'size' => 0,
		),
		'icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'title_separator_type' => 'line',
		'divider_align' => 'center',
		'divider_line_align' => 'center',
		'title_separator_border_style' => 'solid',
		'line_cap' => 'ep_square',
		'direction_style' => '1',
		'direction_rotate' => array(
			'size' => 0,
		),
		'direction_rotate_tablet' => array(
			'size' => 0,
		),
		'direction_rotate_mobile' => array(
			'size' => 0,
		),
	),
	// Indicador de dirección y animaciones del botón no se trasladan.
	'drop'      => array( 'show_indicator', 'direction_hide_on', 'readmore_on_hover', 'button_css_id', 'advanced_readmore_icon', 'readmore_icon_align' ),
);
