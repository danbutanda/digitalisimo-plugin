<?php
/** Element Pack Pro 9.9.1 `bdt-flip-box` → `flip-box`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'flip-box',
	'classes'   => array(
		'.bdt-flip-box-layer-overlay' => '.elementor-flip-box__layer__overlay',
		'.bdt-flip-box-layer-title'   => '.elementor-flip-box__layer__title',
		'.bdt-flip-box-layer-desc'    => '.elementor-flip-box__layer__description',
		'.bdt-flip-box-layer'         => '.elementor-flip-box__layer',
		'.bdt-flip-box-front'         => '.elementor-flip-box__front',
		'.bdt-flip-box-back'          => '.elementor-flip-box__back',
		'.bdt-flip-box-button'        => '.elementor-flip-box__button',
		'.bdt-flip-box-image'         => '.elementor-flip-box__image',
		'.bdt-flip-box'               => '.elementor-flip-box',
	),
	'defaults'  => array(
		'graphic_element' => 'icon',
		'image' => array(
			'url' => $placeholder_url,
		),
		'image_size' => 'thumbnail',
		'flip_box_icon' => array(
			'value' => 'fas fa-heart',
			'library' => 'fa-solid',
		),
		'icon_view' => 'default',
		'icon_shape' => 'circle',
		'front_title_text' => 'This is the heading',
		'front_description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet consectetur adipiscing elit dolor',
		'front_title_tags' => 'h3',
		'back_title_text' => 'This is the heading',
		'back_description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet consectetur adipiscing elit dolor',
		'button_text' => 'Click Here',
		'link_click' => 'button',
		'button_size' => 'sm',
		'back_title_tags' => 'h3',
		'back_background_overlay' => '',
		'flip_effect' => 'flip',
		'flip_direction' => 'left',
		'flip_direction_content' => 'up',
		'flip_3d' => 'yes',
		'flip_transition_easing' => 'ease-out',
		'flip_trigger' => 'hover',
		'front_alignment' => 'center',
		'icon_primary_color' => '',
		'icon_secondary_color' => '',
		'icon_rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'image_width' => array(
			'unit' => '%',
			'size' => 10,
		),
		'image_opacity' => array(
			'size' => 1,
		),
		'front_description_color' => '#f5f5f5',
		'back_alignment' => 'center',
	),
	'rename'    => array( 'flip_box_icon' => 'selected_icon', 'front_title_text' => 'title_text_a', 'front_description_text' => 'description_text_a', 'back_title_text' => 'title_text_b', 'back_description_text' => 'description_text_b', 'front_title_tags' => 'title_tag' ),
	'values'    => array( 'flip_effect' => array( 'zoom-up' => 'zoom-in', 'zoom-down' => 'zoom-out' ) ),
	// El giro al hacer clic, el contenido de la cara trasera y el suavizado de Element Pack no se trasladan.
	'drop'      => array( 'flip_trigger', 'flip_direction_content', 'flip_transition_easing', 'back_title_tags', 'button_hover_animation' ),
);
