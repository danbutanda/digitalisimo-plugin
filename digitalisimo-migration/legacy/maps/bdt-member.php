<?php
/** Element Pack Pro 9.9.1 `bdt-member` → `digitalisimo-fancy-card`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-card',
	'classes'   => array(
		'.bdt-member-title'       => '.digi-fancy-card__title',
		'.bdt-member-sub-title'   => '.digi-fancy-card__badge',
		'.bdt-member-description' => '.digi-fancy-card__description',
		'.bdt-member-readmore'    => '.digi-fancy-card__link',
		'.bdt-member-image'       => '.digi-fancy-card__visual',
		'.bdt-member-badge'       => '.digi-fancy-card__badge',
		'.bdt-member'             => '.digi-fancy-card',
	),
	'defaults'  => array(
		'photo' => array(
			'url' => $placeholder_url,
		),
		'alternative_photo' => array(
			'url' => $placeholder_url,
		),
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'name' => 'John Doe',
		'role' => 'Managing Director',
		'description_text' => 'Type here some info about this team member, the man very important person of our company.',
		'member_social_icon' => 'yes',
		'thumbnail_size_size' => 'full',
		'flip_effect' => 'flip',
		'flip_direction' => 'left',
		'flip_3d' => 'yes',
		'photo_opacity' => array(
			'size' => 1,
		),
		'photo_hover_opacity' => array(
			'size' => 1,
		),
		'photo_hover_animation' => '',
		'ekip_name_bottom_space' => array(
			'unit' => '%',
		),
		'ekip_role_bottom_space' => array(
			'unit' => '%',
		),
		'ekip_icon_vertical_space' => array(
			'unit' => '%',
		),
		'social_icon_tooltip' => 'yes',
	),
	'repeaters' => array(
		'social_link_list' => array(
			'defaults'     => array(
				'social_link_title' => 'Facebook',
			),
			'default_rows' => array( array(
					'social_icon_link' => array(
						'url' => 'http://www.facebook.com/sigmative/',
					),
					'social_share_icon' => array(
						'value' => 'fab fa-facebook-f',
						'library' => 'fa-brands',
					),
					'social_link_title' => 'Facebook',
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.x.com/bdthemescom/',
					),
					'social_share_icon' => array(
						'value' => 'fab fa-x-twitter',
						'library' => 'fa-brands',
					),
					'social_link_title' => 'X',
				), array(
					'social_icon_link' => array(
						'url' => 'http://www.instagram.com/sigmative/',
					),
					'social_share_icon' => array(
						'value' => 'fab fa-instagram',
						'library' => 'fa-brands',
					),
					'social_link_title' => 'Instagram',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$set = array(
			'icon_type'        => 'image',
			'image'            => is_array( $out['photo'] ?? null ) ? $out['photo'] : array( 'url' => '' ),
			'badge_text'       => trim( wp_strip_all_tags( (string) ( $out['role'] ?? '' ) ) ),
			'title_text'       => trim( wp_strip_all_tags( (string) ( $out['name'] ?? '' ) ) ),
			'description_text' => trim( wp_strip_all_tags( (string) ( $out['description_text'] ?? '' ) ) ),
			'button_text'      => '',
			'link'             => array( 'url' => '' ),
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(photo|member_alternative_photo|alternative_photo|image_mask_shape|name|role|description_text|member_social_icon|social_link_list|flip_effect|flip_direction|flip_3d|photo_hover_animation|social_icon_tooltip)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
