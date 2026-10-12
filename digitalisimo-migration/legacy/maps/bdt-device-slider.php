<?php
/**
 * Element Pack Pro 9.9.1 `bdt-device-slider` → `digitalisimo-device-slider`.
 * Los marcos de Element Pack pasan al tipo de dispositivo propio; cada diapositiva conserva su contenido
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
		'device_type' => array( 'chrome' => 'browser', 'chrome-dark' => 'browser', 'edge' => 'browser', 'edge-dark' => 'browser', 'firefox' => 'browser', 'safari' => 'browser', 'desktop' => 'desktop', 'imac' => 'desktop', 'macbookpro' => 'desktop', 'macbookair' => 'desktop', 'iphonex' => 'mobile', 'mobile' => 'mobile', 'tablet' => 'tablet', 'custom' => 'mobile' ),
		'navigation'  => static function ( $value ) {
			return 'none' === $value ? 'none' : 'arrows';
		},
	),
	'repeaters' => array(
		'slides' => array(
			'defaults'     => array( 'title' => 'Slide Title', 'title_link' => array( 'url' => '' ), 'background' => 'color', 'color' => '#14ABF4', 'image' => $placeholder, 'video_link' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4', 'youtube_link' => 'https://youtu.be/YE7VzlLtp-4' ),
			'default_rows' => array( array( 'title' => 'Slide Item 1' ), array( 'title' => 'Slide Item 2' ), array( 'title' => 'Slide Item 3' ), array( 'title' => 'Slide Item 4' ) ),
			'rename'       => array( 'title_link' => 'link' ),
		),
	),
	'filter'    => static function ( array $out ) {
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
