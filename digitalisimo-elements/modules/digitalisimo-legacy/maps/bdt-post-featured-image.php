<?php
/** Element Pack Pro 9.9.1 `bdt-post-featured-image` → `theme-post-featured-image`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'theme-post-featured-image',
	'classes'   => array( '.bdt-post-featuerd-image' => '' ),
	'defaults'  => array(
		'thumbnail_size' => 'large',
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
		'height' => array(
			'unit' => 'px',
		),
		'height_tablet' => array(
			'unit' => 'px',
		),
		'height_mobile' => array(
			'unit' => 'px',
		),
		'object-fit' => '',
		'caption_align' => '',
		'text_color' => '',
	),
	'rename'    => array( 'image_hover_animation' => 'hover_animation', 'thumbnail_size' => 'image_size' ),
	'filter'    => static function ( array $out ) {
		// Sin imagen destacada Element Pack no mostraba nada: la etiqueta dinámica sin respaldo tampoco.
		$out['__dynamic__']['image'] = '[elementor-tag id="' . substr( md5( 'post-featured-image' ), 0, 7 ) . '" name="post-featured-image" settings="' . rawurlencode( wp_json_encode( (object) array() ) ) . '"]';
		return $out;
	},
);
