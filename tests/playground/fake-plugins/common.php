<?php
/**
 * Shortcodes de prueba para el A/B de integraciones: imprimen etiqueta y atributos, de modo que
 * la fase A (Element Pack) y la B (adaptador) se comparan por el shortcode exacto que arman.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'digi_ab_fake_shortcode' ) ) {
	function digi_ab_fake_shortcode( $tag, $atts ) {
		$parts = array( $tag );
		foreach ( (array) $atts as $key => $value ) {
			$parts[] = $key . '=' . $value;
		}
		return '<div class="digi-ab-fake">' . esc_html( implode( ' | ', $parts ) ) . '</div>';
	}
	function digi_ab_fake_register( array $tags ) {
		foreach ( $tags as $tag ) {
			add_shortcode( $tag, static function ( $atts ) use ( $tag ) {
				return digi_ab_fake_shortcode( $tag, $atts );
			} );
		}
	}
}
