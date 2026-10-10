<?php
/** Element Pack Pro 9.9.1 `bdt-featured-box` → `digitalisimo-featured-box`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-featured-box',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-featured-box, .bdt-ep-featured-box-content, .bdt-ep-featured-box-readmore, .bdt-ep-featured-box-button, .bdt-ep-featured-box-image, .bdt-ep-featured-box-badge, .bdt-image-mask, .bdt-ep-featured-box-title, .bdt-ep-featured-box-sub-title, .bdt-ep-featured-box-text, .bdt-position-small
	'classes'   => array(
		'.bdt-ep-featured-box-title'       => '.digi-featured-box__title',
		'.bdt-ep-featured-box-sub-title'   => '.digi-featured-box__subtitle',
		'.bdt-ep-featured-box-text'        => '.digi-featured-box__description',
		'.bdt-ep-featured-box-readmore'    => '.digi-featured-box__button',
		'.bdt-ep-featured-box-badge span'  => '.digi-featured-box__badge',
		'.bdt-ep-featured-box-badge'       => '.digi-featured-box__badge',
		'.bdt-ep-featured-box-content'     => '.digi-featured-box__content',
		'.bdt-ep-featured-box-image'       => '.digi-featured-box__media',
		'.bdt-ep-featured-box'             => '.digi-featured-box',
	),
	'defaults'  => array(
		'image' => array(
			'url' => $placeholder_url,
		),
		'thumbnail_size_size' => 'full',
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'title_text' => 'Featured Box Title',
		'sub_title_text' => 'This is a Label',
		'description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
		'readmore' => 'yes',
		'title_size' => 'h3',
		'content_position' => 'center-left',
		'skin_content_position' => 'left',
		'readmore_text' => 'Read More',
		'readmore_link' => array(
			'url' => '#',
		),
		'readmore_icon_align' => 'right',
		'readmore_icon_indent' => array(
			'size' => 8,
		),
		'button_css_id' => '',
		'badge_text' => 'POPULAR',
		'badge_position' => 'top-right',
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
		'image_hover_transition' => array(
			'size' => 0.3,
		),
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
	),
	'strip'     => array( 'title_text', 'sub_title_text' ),
	'rename'    => array( 'thumbnail_size_size' => 'image_size', '_skin' => 'layout' ),
	'values'    => array(
		'layout'     => array( '' => 'overlay', 'split' => 'split' ),
		'image_size' => array( 'thumbnail' => 'medium', 'medium_large' => 'large', '1536x1536' => 'full', '2048x2048' => 'full', 'custom' => 'large' ),
		'title_size' => array( 'h1' => 'h2', 'p' => 'div', 'span' => 'div' ),
	),
	'set'       => array(),
	'filter'    => static function ( array $out ) {
		$out['layout'] = $out['layout'] ?? 'overlay';
		if ( 'yes' !== ( $out['title_link'] ?? '' ) ) {
			unset( $out['title_link_url'] );
		}
		unset( $out['title_link'], $out['column_reverse'], $out['badge_position'], $out['readmore_attention'], $out['image_mask_shape'] );
		return $out;
	},
);
