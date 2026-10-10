<?php
/** Element Pack Pro 9.9.1 `bdt-trailer-box` → `digitalisimo-fancy-card`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-card',
	'classes'   => array(
		'.bdt-trailer-box-title'       => '.digi-fancy-card__title',
		'.bdt-trailer-box-sub-title'   => '.digi-fancy-card__badge',
		'.bdt-trailer-box-description' => '.digi-fancy-card__description',
		'.bdt-trailer-box-readmore'    => '.digi-fancy-card__link',
		'.bdt-trailer-box-image'       => '.digi-fancy-card__visual',
		'.bdt-trailer-box-badge'       => '.digi-fancy-card__badge',
		'.bdt-trailer-box'             => '.digi-fancy-card',
	),
	'defaults'  => array(
		'title' => 'Trailer Box Title',
		'content' => 'I am Trailer Box Description Text. You can change me anytime from settings.',
		'origin' => 'bottom-left',
		'align' => '',
		'height' => array(
			'size' => 400,
		),
		'link_type' => '',
		'button' => array(
			'url' => '#',
		),
		'title_tags' => 'h3',
		'button_text' => 'View Details',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'button_position' => '',
		'button_css_id' => '',
		'item_animation' => 'content',
		'pre_title_x_position' => array(
			'size' => 0,
		),
		'pre_title_x_position_tablet' => array(
			'size' => 0,
		),
		'pre_title_x_position_mobile' => array(
			'size' => 0,
		),
		'pre_title_y_position' => array(
			'size' => 0,
		),
		'pre_title_y_position_tablet' => array(
			'size' => 0,
		),
		'pre_title_y_position_mobile' => array(
			'size' => 0,
		),
		'pre_title_rotate' => array(
			'size' => 0,
		),
		'pre_title_rotate_tablet' => array(
			'size' => 0,
		),
		'pre_title_rotate_mobile' => array(
			'size' => 0,
		),
		'pre_title_hide' => 'm',
	),
	'filter'    => static function ( array $out ) {
		$set = array(
			'icon_type'        => 'none',
			'badge_text'       => trim( wp_strip_all_tags( (string) ( $out['pre_title'] ?? '' ) ) ),
			'title_text'       => trim( wp_strip_all_tags( (string) ( $out['title'] ?? '' ) ) ),
			'title_size'       => (string) ( $out['title_tags'] ?? 'h3' ),
			'description_text' => trim( wp_strip_all_tags( (string) ( $out['content'] ?? '' ) ) ),
			'button_text'      => 'button' === ( $out['link_type'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['button_text'] ?? '' ) ) ) : '',
			'link'             => is_array( $out['button'] ?? null ) ? $out['button'] : array( 'url' => '' ),
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(pre_title|title|content|origin|align|link_type|button|title_tags|button_text|button_icon|icon_align|button_position|button_css_id|item_animation|pre_title_hide|title_advanced_style|button_hover_animation)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
