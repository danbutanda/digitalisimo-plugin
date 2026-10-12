<?php
/** Element Pack Pro 9.9.1 `bdt-toggle` → `digitalisimo-accordion`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-accordion',
	'classes'   => array(
		'.bdt-show-hide-content' => '.digi-accordion__content',
		'.bdt-show-hide-title' => '.digi-accordion__title',
		'.bdt-show-hide-icon' => '.digi-accordion__marker',
		'.bdt-show-hide-item' => '.digi-accordion__item',
		'.bdt-toggle' => '.digi-accordion',
	),
	'defaults'  => array(
		'toggle_title' => 'Show All',
		'toggle_open_title' => 'Collapse',
		'source' => 'custom',
		'toggle_content' => 'Toggle Content',
		'widget_visibility' => array(
			'unit' => 'px',
			'size' => 30,
		),
		'toggle_icon_show' => 'yes',
		'toggle_icon_normal' => array(
			'value' => 'fas fa-plus',
			'library' => 'fa-solid',
		),
		'toggle_icon_active' => array(
			'value' => 'fas fa-minus',
			'library' => 'fa-solid',
		),
		'toggle_icon_position' => 'right',
		'active_scrollspy' => 'no',
		'hash_location' => 'no',
		'scrollspy_top_offset' => array(
			'unit' => 'px',
			'size' => 70,
		),
		'scrollspy_time' => array(
			'unit' => 'px',
			'size' => 1000,
		),
	),
	// Un solo elemento: el texto «Show All» es su título; al abrirse no cambia a «Collapse».
	'filter'    => static function ( array $out ) {
		$source      = (string) ( $out['source'] ?? 'custom' );
		$out['tabs'] = array( array(
			'_id'         => 'toggle',
			'tab_title'   => trim( wp_strip_all_tags( (string) ( $out['toggle_title'] ?? '' ) ) ),
			'source'      => in_array( $source, array( 'elementor', 'anywhere' ), true ) ? $source : 'custom',
			'tab_content' => (string) ( $out['toggle_content'] ?? '' ),
			'template_id' => (string) ( $out['template_id'] ?? '' ),
			'anywhere_id' => (string) ( $out['anywhere_id'] ?? '' ),
		) );
		$out['active_item'] = 'yes' === ( $out['toggle_initially_open'] ?? '' ) ? 1 : 0;
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(toggle_.*|source|template_id|anywhere_id|source_selector|widget_visibility|active_scrollspy|hash_location|scrollspy_.*)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
