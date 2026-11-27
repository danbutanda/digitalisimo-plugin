<?php
/**
 * Element Pack Pro 9.9.1 `bdt-device-slider` → `digitalisimo-device-slider`.
 * Los marcos de Element Pack pasan al tipo de dispositivo propio (las MacBook, a la laptop; el iMac, al monitor; los
 * navegadores, a la ventana de navegador); la rotación horizontal de celular y tableta, a la orientación; su «Color 1»,
 * al color propio del marco, y la navegación conserva flechas, puntos o ambos. Cada diapositiva conserva su contenido
 * (imagen, color, vídeo MP4 o YouTube) con los valores por defecto de Element Pack.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = array( 'url' => class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '' );

return array(
	'target'    => 'digitalisimo-device-slider',
	'classes'   => array(
		'.bdt-device-slider-container .bdt-slideshow-items .bdt-device-slider-title' => '.digi-device-slider__title',
		'.bdt-device-slider-container .bdt-navigation-arrows' => '.digi-carousel__button',
		'.bdt-device-slider-container'                         => '.digi-device-slider',
	),
	'defaults'  => array( 'device_type' => 'desktop', 'show_title' => 'yes', 'navigation' => 'arrows' ),
	'values'    => array(
		'device_type' => array( 'chrome' => 'browser', 'chrome-dark' => 'browser', 'edge' => 'browser', 'edge-dark' => 'browser', 'firefox' => 'browser', 'safari' => 'browser', 'desktop' => 'desktop', 'imac' => 'desktop', 'macbookpro' => 'laptop', 'macbookair' => 'laptop', 'iphonex' => 'mobile', 'mobile' => 'mobile', 'tablet' => 'tablet', 'custom' => 'mobile' ),
		'navigation'  => static function ( $value ) {
			return in_array( $value, array( 'none', 'dots', 'arrows_dots' ), true ) ? $value : 'arrows';
		},
	),
	'repeaters' => array(
		'slides' => array(
			'defaults'     => array( 'title' => 'Slide Title', 'title_link' => array( 'url' => '' ), 'background' => 'color', 'color' => '#14ABF4', 'image' => $placeholder, 'video_link' => '', 'youtube_link' => '' ),
			'default_rows' => array( array( 'title' => 'Slide Item 1' ), array( 'title' => 'Slide Item 2' ), array( 'title' => 'Slide Item 3' ), array( 'title' => 'Slide Item 4' ) ),
			'rename'       => array( 'title_link' => 'link' ),
		),
	),
	'filter'    => static function ( array $out ) {
		if ( 'yes' === ( $out['rotation_state'] ?? '' ) && in_array( $out['device_type'] ?? '', array( 'mobile', 'tablet' ), true ) ) {
			$out['orientation'] = 'landscape';
		}
		if ( ! empty( $out['device_color_1'] ) && empty( $out['frame_color'] ) ) {
			$out['frame_color'] = $out['device_color_1'];
		}
		foreach ( $out['slides'] as $index => $slide ) {
			$kind                = in_array( $slide['background'] ?? 'color', array( 'image', 'color', 'video', 'youtube' ), true ) ? ( $slide['background'] ?? 'color' ) : 'color';
			$slide['background'] = $kind;
			if ( 'video' === $kind ) {
				$slide['video'] = array( 'url' => (string) ( $slide['video_link'] ?? '' ) );
			} elseif ( 'youtube' === $kind ) {
				$slide['youtube_url'] = (string) ( $slide['youtube_link'] ?? '' );
			}
			if ( 'image' !== $kind ) {
				unset( $slide['image'] );
			}
			if ( 'color' !== $kind ) {
				unset( $slide['color'] );
			}
			unset( $slide['video_link'], $slide['youtube_link'] );
			if ( 'yes' !== ( $out['show_title'] ?? 'yes' ) ) {
				$slide['title'] = '';
			}
			$out['slides'][ $index ] = $slide;
		}
		return $out;
	},
);
