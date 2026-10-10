<?php
/** Element Pack Pro 9.9.1 `bdt-post-content` → `theme-post-excerpt`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'theme-post-excerpt',
	'classes'   => array(),
	'defaults'  => array(
		'content_type' => 'excerpt',
		'limit' => array(
			'size' => 10,
		),
	),
	'filter'    => static function ( array $out ) {
		// Extracto con su número de palabras; el modo «contenido completo» también se muestra como extracto.
		$words = (int) ( $out['limit']['size'] ?? 10 );
		$out['__dynamic__']['excerpt'] = '[elementor-tag id="' . substr( md5( 'post-excerpt' ), 0, 7 ) . '" name="post-excerpt" settings="' . rawurlencode( wp_json_encode( array( 'max_length' => $words ) ) ) . '"]';
		unset( $out['content_type'], $out['limit'] );
		return $out;
	},
);
