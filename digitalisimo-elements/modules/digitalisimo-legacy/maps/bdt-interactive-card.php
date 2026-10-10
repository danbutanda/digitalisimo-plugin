<?php
/** Element Pack Pro 9.9.1 `bdt-interactive-card` → `digitalisimo-fancy-card`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-card',
	'classes'   => array(
		'.bdt-interactive-card-title'       => '.digi-fancy-card__title',
		'.bdt-interactive-card-sub-title'   => '.digi-fancy-card__badge',
		'.bdt-interactive-card-description' => '.digi-fancy-card__description',
		'.bdt-interactive-card-readmore'    => '.digi-fancy-card__link',
		'.bdt-interactive-card-image'       => '.digi-fancy-card__visual',
		'.bdt-interactive-card-badge'       => '.digi-fancy-card__badge',
		'.bdt-interactive-card'             => '.digi-fancy-card',
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
		'title_text' => 'Interactive Card Title',
		'show_sub_title' => 'yes',
		'sub_title_text' => 'This is a Label',
		'description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
		'readmore' => 'yes',
		'title_size' => 'h3',
		'content_position' => 'top',
		'wave_bones' => array(
			'size' => 3,
		),
		'wave_amplitude' => array(
			'size' => 40,
		),
		'readmore_text' => 'Read More',
		'readmore_link' => array(
			'url' => '#',
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
		'image_hover_effect' => 'yes',
	),
	'filter'    => static function ( array $out ) {
		$set = array(
			'icon_type'        => 'image',
			'image'            => is_array( $out['image'] ?? null ) ? $out['image'] : array( 'url' => '' ),
			'badge_text'       => 'yes' === ( $out['badge'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['badge_text'] ?? '' ) ) ) : ( 'yes' === ( $out['show_sub_title'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['sub_title_text'] ?? '' ) ) ) : '' ),
			'title_text'       => trim( wp_strip_all_tags( (string) ( $out['title_text'] ?? '' ) ) ),
			'description_text' => trim( wp_strip_all_tags( (string) ( $out['description_text'] ?? '' ) ) ),
			'button_text'      => 'yes' === ( $out['readmore'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['readmore_text'] ?? '' ) ) ) : '',
			'link'             => 'yes' === ( $out['global_link'] ?? '' ) && is_array( $out['global_link_url'] ?? null ) ? $out['global_link_url'] : ( is_array( $out['readmore_link'] ?? null ) ? $out['readmore_link'] : array( 'url' => '' ) ),
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(image|image_mask_shape|title_link|title_link_url|show_sub_title|sub_title_text|description_text|readmore|badge|content_position|show_wavify_effect|wave_.*|global_link|global_link_url|readmore_text|readmore_link|button_css_id|badge_text|badge_position|image_hover_effect|readmore_attention|readmore_hover_animation)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
