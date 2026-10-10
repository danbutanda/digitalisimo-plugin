<?php
/** Element Pack Pro 9.9.1 `bdt-social-share` → `share-buttons`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'share-buttons',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ss-btn, .bdt-ss-icon, .bdt-ss-btns-style-boxed, .bdt-ss-btns-style-flat, .bdt-ss-btns-style-gradient, .bdt-ss-btns-style-minimal, .bdt-ss-btns-style-framed, .bdt-social-share, .bdt-social-share-text, .bdt-social-share-title, .bdt-ss-counter
	'classes'   => array(
		'.bdt-social-share .bdt-ss-btn' => '.elementor-share-btn',
		'.bdt-ss-btn'                   => '.elementor-share-btn',
		'.bdt-ss-icon i'                => '.elementor-share-btn__icon i',
		'.bdt-social-share-title'       => '.elementor-share-btn__title',
		'.bdt-social-share'             => '.elementor-share-buttons',
	),
	'defaults'  => array(
		'view' => 'icon-text',
		'show_label' => 'yes',
		'columns_tablet' => '0',
		'columns_mobile' => '0',
		'column_gap' => array(
			'size' => 10,
		),
		'row_gap' => array(
			'size' => 10,
		),
		'share_url_type' => 'current_page',
		'style' => 'flat',
		'shape' => 'square',
		'icon_size' => array(
			'unit' => 'em',
		),
		'icon_size_tablet' => array(
			'unit' => 'em',
		),
		'icon_size_mobile' => array(
			'unit' => 'em',
		),
		'button_height' => array(
			'unit' => 'em',
		),
		'button_height_tablet' => array(
			'unit' => 'em',
		),
		'button_height_mobile' => array(
			'unit' => 'em',
		),
		'border_size' => array(
			'size' => 2,
		),
		'color_source' => 'original',
	),
	'repeaters' => array(
		'share_buttons' => array(
			'defaults'     => array(
				'button' => 'facebook',
				'copied_text' => 'Copied',
			),
			'default_rows' => array( array(
					'button' => 'facebook',
				), array(
					'button' => 'linkedin',
				), array(
					'button' => 'twitter',
				), array(
					'button' => 'pinterest',
				) ),
		),
	),
	'rename'    => array( 'style' => 'skin' ),
	'values'    => array( 'color_source' => array( 'original' => 'official' ) ),
	'drop'      => array( 'show_counter' ),
	// Element Pack rotulaba como «X» su botón de Twitter; PRO Elements lo llama x-twitter.
	'filter'    => static function ( array $out ) {
		foreach ( $out['share_buttons'] as $index => $button ) {
			if ( 'twitter' === ( $button['button'] ?? '' ) ) {
				$out['share_buttons'][ $index ]['button'] = 'x-twitter';
			}
		}
		return $out;
	},
);
