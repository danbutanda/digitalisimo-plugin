<?php
/** Element Pack Pro 9.9.1 `bdt-edd-tabs` → `digitalisimo-accordion`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-accordion',
	'classes'   => array(
		'.bdt-switcher-item-content' => '.digi-accordion__content',
		'.bdt-tabs-item-title' => '.digi-accordion__title',
		'.bdt-tabs-item' => '.digi-accordion__item',
		'.bdt-edd-tabs' => '.digi-accordion',
	),
	'defaults'  => array(
		'tab_layout' => 'default',
		'content_spacing' => array(
			'size' => 20,
		),
		'tab_transition' => '',
		'duration' => array(
			'size' => 200,
		),
		'media' => 960,
		'nav_sticky_offset' => array(
			'size' => 1,
		),
		'swiping_on_mobile' => 'yes',
		'active_hash' => 'no',
		'hash_top_offset' => array(
			'unit' => 'px',
			'size' => 70,
		),
		'hash_scrollspy_time' => array(
			'unit' => 'px',
			'size' => 1500,
		),
		'tabs_match_height' => 'yes',
		'icon_space' => array(
			'size' => 8,
		),
		'others_type_input_text_color' => '#666666',
		'textarea_height' => array(
			'size' => 125,
		),
	),
	'repeaters' => array(
		'tabs' => array(
			'defaults'     => array(
				'tab_title' => 'Tab Title',
				'tab_content' => 'Tab Content',
			),
			'default_rows' => array( array(
					'tab_title' => 'Purchase History',
					'tab_content' => 'purchase_history',
				), array(
					'tab_title' => 'Download History',
					'tab_content' => 'download_history',
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		// Cada pestaña mostraba el shortcode de EDD escrito sin corchetes.
		foreach ( $out['tabs'] as $i => $row ) {
			$code = trim( (string) ( $row['tab_content'] ?? '' ), "[] \t\n" );
			$out['tabs'][ $i ]['source']      = 'custom';
			$out['tabs'][ $i ]['tab_content'] = '' !== $code ? '[' . $code . ']' : '';
		}
		$out['active_item'] = (int) ( $out['active_item'] ?? 1 );
		unset( $out['tabs_match_height'] );
		return $out;
	},
);
