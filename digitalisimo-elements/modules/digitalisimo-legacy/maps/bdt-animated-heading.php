<?php
/**
 * Element Pack Pro 9.9.1 `bdt-animated-heading` → `digitalisimo-animated-heading`.
 * Los diseños que alternan palabras («animated», «typed») separan con comas; el texto partido o
 * revelado anima una sola frase y se conserva entera, sin rotación.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'digitalisimo-animated-heading',
	'classes'  => array(
		'.bdt-heading-tag'      => '.digi-animated-heading',
		'.bdt-pre-heading'      => '.digi-animated-heading__before',
		'.bdt-post-heading'     => '.digi-animated-heading__after',
		'.bdt-animated-heading' => '.digi-animated-heading__phrase',
		'.bdt-heading '         => '',
	),
	'strip'    => array( 'before_text', 'after_text' ),
	'defaults' => array(
		'heading_layout'          => 'animated',
		'pre_heading'             => 'Hello I am',
		'animated_heading'        => 'Animated,Morphing,Awesome',
		'post_heading'            => 'Heading',
		'header_size'             => 'h2',
		'heading_animation_delay' => 2500,
	),
	'rename'   => array( 'pre_heading' => 'before_text', 'post_heading' => 'after_text', 'header_size' => 'heading_tag', 'link' => 'heading_link' ),
	'filter'   => static function ( array $out ) {
		$rotating = in_array( $out['heading_layout'] ?? 'animated', array( 'animated', 'typed' ), true );
		$text     = (string) ( $out['animated_heading'] ?? '' );
		$out['animated_text'] = $rotating ? implode( "\n", array_filter( array_map( 'trim', explode( ',', $text ) ), 'strlen' ) ) : rtrim( $text, ',' );
		$out['rotate']        = $rotating ? 'yes' : '';
		$out['interval']      = max( 1000, (int) ( $out['heading_animation_delay'] ?? 2500 ) );
		if ( in_array( $out['heading_tag'] ?? 'h2', array( 'span', 'p' ), true ) ) {
			$out['heading_tag'] = 'div';
		}
		unset( $out['animated_heading'], $out['heading_layout'], $out['heading_animation_delay'] );
		return $out;
	},
);
