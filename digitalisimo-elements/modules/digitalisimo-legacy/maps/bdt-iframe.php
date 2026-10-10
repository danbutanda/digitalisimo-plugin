<?php
/** Element Pack Pro 9.9.1 `bdt-iframe` → `html`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'html',
	'classes'   => array( '.bdt-iframe' => '.digi-legacy-iframe' ),
	'defaults'  => array(
		'source' => array(
			'url' => 'https://example.com',
		),
		'height' => array(
			'size' => 640,
		),
		'responsive_ratio_size' => array(
			'width' => 1280,
			'height' => 720,
		),
		'lazyload' => 'yes',
		'throttle' => 300,
		'threshold' => 100,
		'live' => 'yes',
		'allowfullscreen' => 'yes',
		'scrolling' => 'yes',
		'device_type' => 'imac',
		'show_notch' => 'yes',
		'show_buttons' => 'yes',
		'slider_size_ratio' => array(
			'width' => 600,
			'height' => 1200,
		),
		'show_left_button_1' => 'yes',
		'show_left_button_2' => 'yes',
		'show_left_button_3' => 'yes',
		'show_right_button_1' => 'yes',
		'show_right_button_2' => 'yes',
		'show_custom_notch' => 'yes',
		'select_notch' => 'large-notch',
		'show_custom_lens' => 'yes',
		'lens_horizontal' => array(
			'size' => 50,
		),
		'lens_vertical' => array(
			'size' => 5,
		),
		'custom_device_border_width' => array(
			'top' => '20',
			'right' => '20',
			'bottom' => '20',
			'left' => '20',
			'isLinked' => true,
		),
		'custom_device_border_radius' => array(
			'top' => '40',
			'right' => '40',
			'bottom' => '40',
			'left' => '40',
			'isLinked' => true,
		),
		'custom_device_border_color_1' => '#343434',
	),
	// Iframe nativo con carga diferida del navegador; los marcos de dispositivo de Element Pack no se trasladan.
	'filter'    => static function ( array $out ) {
		$url  = esc_url( (string) ( $out['source']['url'] ?? '' ) );
		$html = '';
		if ( '' !== $url ) {
			$attrs = array( 'class="digi-legacy-iframe"', 'src="' . $url . '"', 'title="Contenido insertado"', 'style="width:100%;border:0"' );
			if ( 'yes' === ( $out['lazyload'] ?? '' ) ) {
				$attrs[] = 'loading="lazy"';
			}
			if ( 'yes' === ( $out['allowfullscreen'] ?? '' ) ) {
				$attrs[] = 'allowfullscreen';
			}
			if ( 'yes' !== ( $out['scrolling'] ?? '' ) ) {
				$attrs[] = 'scrolling="no"';
			}
			if ( 'yes' === ( $out['sandbox'] ?? '' ) ) {
				$allowed  = preg_replace( '/[^a-z\- ]/', '', strtolower( str_replace( ',', ' ', (string) ( $out['sandbox_allowed_attributes'] ?? '' ) ) ) );
				$attrs[]  = 'sandbox="' . esc_attr( trim( (string) $allowed ) ) . '"';
			}
			if ( 'yes' === ( $out['show_responsive_ratio'] ?? '' ) ) {
				$ratio   = (array) ( $out['responsive_ratio_size'] ?? array() );
				$attrs[] = 'style="width:100%;border:0;aspect-ratio:' . max( 1, (int) ( $ratio['width'] ?? 16 ) ) . '/' . max( 1, (int) ( $ratio['height'] ?? 9 ) ) . '"';
				array_splice( $attrs, 3, 1 );
			}
			$html = '<iframe ' . implode( ' ', $attrs ) . '></iframe>';
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(source|auto_height|show_responsive_ratio|responsive_ratio_size|align|lazyload|throttle|threshold|live|allowfullscreen|scrolling|sandbox|sandbox_allowed_attributes|custom_attributes|show_iframe_device|device_type|rotation_state|show_notch|show_buttons|slider_size_ratio|custom_device_buttons|show_(left|right)_button_\d)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		$out['html'] = $html;
		return $out;
	},
);
