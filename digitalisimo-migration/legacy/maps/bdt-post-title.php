<?php
/** Element Pack Pro 9.9.1 `bdt-post-title` → `theme-post-title`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'theme-post-title',
	'classes'   => array( '.ep-post-title-link' => '.elementor-heading-title a', '.ep-post-title' => '.elementor-heading-title' ),
	'defaults'  => array(
		'bdt_title_tag' => 'h2',
	),
	'rename'    => array( 'bdt_title_tag' => 'header_size' ),
	'filter'    => static function ( array $out ) {
		// El título dinámico es sólo el valor por defecto del control: en un documento heredado hay que fijarlo.
		$out['__dynamic__']['title'] = '[elementor-tag id="' . substr( md5( 'post-title' ), 0, 7 ) . '" name="post-title" settings="' . rawurlencode( wp_json_encode( (object) array() ) ) . '"]';
		if ( 'yes' === ( $out['bdt_post_link'] ?? '' ) ) {
			$out['__dynamic__']['link'] = '[elementor-tag id="' . substr( md5( 'post-url' ), 0, 7 ) . '" name="post-url" settings="' . rawurlencode( wp_json_encode( (object) array() ) ) . '"]';
		}
		unset( $out['bdt_post_link'], $out['title_advanced_style'] );
		return $out;
	},
);
