<?php
/** Element Pack Pro 9.9.1 `bdt-scroll-image` → `image`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'image',
	'classes'   => array(),
	'defaults'  => array(
		'image' => array(
			'url' => $placeholder_url,
		),
		'image_size_size' => 'large',
		'frame' => 'desktop',
		'min_height' => array(
			'size' => 320,
		),
		'link_to' => 'lightbox',
		'external_link' => array(
			'url' => '#',
		),
		'link_icon' => 'link',
		'link_icon_position' => 'top-left',
		'image_scroll_option' => 'bottom-top',
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
		'show_left_button_1' => 'yes',
		'show_left_button_2' => 'yes',
		'show_left_button_3' => 'yes',
		'show_right_button_1' => 'yes',
		'show_right_button_2' => 'yes',
		'show_custom_notch' => 'yes',
		'select_notch' => 'large-notch',
		'show_custom_lens' => 'yes',
		'lens_horizontal' => array(
			'size' => 50,
		),
		'lens_vertical' => array(
			'size' => 5,
		),
		'custom_device_border_width' => array(
			'top' => '20',
			'right' => '20',
			'bottom' => '20',
			'left' => '20',
			'isLinked' => true,
		),
		'custom_device_border_radius' => array(
			'top' => '40',
			'right' => '40',
			'bottom' => '40',
			'left' => '40',
			'isLinked' => true,
		),
		'custom_device_border_color_1' => '#343434',
		'transition_duration' => array(
			'size' => 2,
		),
		'caption_align' => '',
	),
	// Se conserva la imagen, su leyenda y su enlace; el desplazamiento, marcos y distintivo no se trasladan.
	'filter'    => static function ( array $out ) {
		$link = (string) ( $out['link_to'] ?? 'lightbox' );
		$out['caption_source'] = '' !== trim( (string) ( $out['caption'] ?? '' ) ) ? 'custom' : 'none';
		$out['link_to']        = 'lightbox' === $link ? 'file' : ( 'external' === $link ? 'custom' : 'none' );
		$out['open_lightbox']  = 'lightbox' === $link ? 'yes' : 'no';
		if ( 'external' === $link ) {
			// Element Pack abría siempre el enlace externo en otra pestaña.
			$out['link'] = array_merge( is_array( $out['external_link'] ?? null ) ? $out['external_link'] : array( 'url' => '' ), array( 'is_external' => 'on' ) );
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(image_framing|frame|external_link|link_icon.*|image_scroll_option|badge.*|slider_size_ratio|show_(left|right)_button_\d|show_custom_.*|select_notch)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
