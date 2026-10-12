<?php
/**
 * Element Pack Pro 9.9.1 `bdt-device-slider` → `digitalisimo-device-slider`.
 * Los marcos de Element Pack pasan al tipo de dispositivo propio; sólo las diapositivas con imagen
 * tienen equivalente (fondo de color y videos no se trasladan).
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
			'defaults'     => array( 'title' => 'Slide Title', 'title_link' => array( 'url' => '' ), 'background' => 'color', 'image' => $placeholder ),
			'default_rows' => array( array( 'title' => 'Slide Item 1' ), array( 'title' => 'Slide Item 2' ), array( 'title' => 'Slide Item 3' ), array( 'title' => 'Slide Item 4' ) ),
			'rename'       => array( 'title_link' => 'link' ),
		),
	),
	'filter'    => static function ( array $out ) {
		foreach ( $out['slides'] as $index => $slide ) {
			if ( 'image' !== ( $slide['background'] ?? 'color' ) ) {
				unset( $slide['image'] );
			}
			if ( 'yes' !== ( $out['show_title'] ?? 'yes' ) ) {
				$slide['title'] = '';
			}
			$out['slides'][ $index ] = $slide;
		}
		return $out;
	},
);
