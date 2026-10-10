<?php
/** Element Pack Pro 9.9.1 `bdt-svg-image` → `image`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'image',
	'classes'   => array(),
	'defaults'  => array(
		'image' => array(
			'url' => $placeholder_url,
		),
		'caption_source' => 'none',
		'caption' => '',
		'link_to' => 'none',
		'view' => 'traditional',
		'svg_image_drawer_type' => 'hover',
		'svg_image_animate_trigger' => 'center',
		'svg_image_anim_rev' => 'yes',
		'svg_image_animate_offset' => array(
			'size' => 50,
			'unit' => '%',
		),
		'svg_image_repeat' => 'yes',
		'svg_image_yoyo' => 'yes',
		'svg_image_animation_duration' => array(
			'unit' => 'px',
			'size' => 100,
		),
		'svg_image_easing' => 'power2.out',
		'svg_image_stagger' => array(
			'unit' => 'px',
			'size' => 0.1,
		),
		'svg_image_stagger_from' => 'start',
		'svg_image_draw_live' => 'no',
		'svg_image_scrub' => array(
			'unit' => 'px',
			'size' => 1,
		),
		'svg_image_scroll_length' => array(
			'unit' => 'px',
			'size' => 600,
		),
		'svg_image_animation_start_point' => array(
			'unit' => '%',
			'size' => 0,
		),
		'svg_image_animation_end_point' => array(
			'unit' => '%',
			'size' => 100,
		),
		'parallax_effects_stroke_value' => array(
			'unit' => '%',
			'size' => 0,
		),
		'parallax_effects_viewport_value' => array(
			'unit' => 'px',
			'size' => 0.7,
		),
		'width' => array(
			'unit' => '%',
		),
		'width_tablet' => array(
			'unit' => '%',
		),
		'width_mobile' => array(
			'unit' => '%',
		),
		'space' => array(
			'unit' => '%',
		),
		'space_tablet' => array(
			'unit' => '%',
		),
		'space_mobile' => array(
			'unit' => '%',
		),
		'caption_align' => '',
		'text_color' => '',
	),
	// La animación de trazo no se traslada; imagen, leyenda y enlace usan los mismos ajustes.
	'filter'    => static function ( array $out ) {
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(view|svg_image_.*)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
