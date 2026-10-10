<?php
/** Element Pack Pro 9.9.1 `bdt-calendly` → `html`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'html',
	'classes'   => array(),
	'defaults'  => array(
		'calendly_username' => '',
		'height' => array(
			'unit' => 'px',
			'size' => '680',
		),
	),
	// El mismo marcado y script oficial de Calendly que imprimía Element Pack, en el widget HTML.
	'filter'    => static function ( array $out ) {
		$path = static function ( $value ) {
			$value = (string) strtok( trim( (string) $value ), '?#' );
			$value = preg_replace( array( '#^(https?:)?//#i', '#^(www\.)?calendly\.com/#i', '#[^a-zA-Z0-9\-_/]#', '#/+#' ), array( '', '', '', '/' ), $value );
			return trim( (string) $value, '/' );
		};
		$user = $path( $out['calendly_username'] ?? '' );
		$html = '';
		if ( '' !== $user ) {
			$slug = $path( $out['calendly_time'] ?? '' );
			if ( false !== strpos( $slug, '/' ) ) {
				$parts = explode( '/', $slug );
				$slug  = end( $parts );
			}
			$params = 'yes' === ( $out['event_type_details'] ?? '' ) ? array( 'hide_event_type_details' => 1 ) : array();
			foreach ( array( 'text_color' => 'text_color', 'primary_color' => 'button_link_color', 'background_color' => 'background_color' ) as $param => $key ) {
				$color = ltrim( trim( (string) ( $out[ $key ] ?? '' ) ), '#' );
				if ( preg_match( '/^[0-9a-fA-F]{3}$/', $color ) ) {
					$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
				}
				if ( preg_match( '/^[0-9a-fA-F]{6}$/', $color ) ) {
					$params[ $param ] = strtolower( $color );
				}
			}
			$url  = 'https://calendly.com/' . $user . ( '' !== $slug && false === strpos( $user, '/' ) ? '/' . $slug : '' ) . ( $params ? '?' . http_build_query( $params ) : '' );
			$html = '<div class="calendly-inline-widget" data-url="' . esc_url( $url ) . '" style="min-width:320px;"></div><script src="https://assets.calendly.com/assets/external/widget.js" async></script>';
		}
		foreach ( array( 'calendly_username', 'calendly_time', 'event_type_details', 'text_color', 'button_link_color', 'background_color' ) as $key ) {
			unset( $out[ $key ] );
		}
		$out['html'] = $html;
		return $out;
	},
);
