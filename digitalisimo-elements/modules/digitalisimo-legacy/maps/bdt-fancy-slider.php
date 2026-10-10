<?php
/** Element Pack Pro 9.9.1 `bdt-fancy-slider` → `digitalisimo-fancy-slider`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-slider',
	// Clases de Element Pack más usadas en sus selectores: .bdt-navigation-prev, .bdt-navigation-next, .bdt-dots-container, .bdt-ep-fancy-slider-button, .bdt-ep-fancy-slider-img, .bdt-ep-fancy-slider-title, .bdt-ep-fancy-slider-item, .bdt-ep-fancy-slider-subtitle, .bdt-ep-fancy-slider-text, .bdt-ep-fancy-slider-content
	'classes'   => array(
		'.bdt-ep-fancy-slider-title'       => '.digi-fancy-slider__title',
		'.bdt-ep-fancy-slider-sub-title'   => '.digi-fancy-slider__subtitle',
		'.bdt-ep-fancy-slider-description' => '.digi-fancy-slider__description',
		'.bdt-ep-fancy-slider-button'      => '.digi-fancy-slider__button',
		'.bdt-ep-fancy-slider-image'       => '.digi-fancy-slider__media',
		'.bdt-navigation-prev'             => '.digi-carousel__button[data-digi-carousel-prev]',
		'.bdt-navigation-next'             => '.digi-carousel__button[data-digi-carousel-next]',
	),
	'defaults'  => array(
		'thumbnail_size_size' => 'full',
		'show_subtitle' => 'yes',
		'show_title' => 'yes',
		'show_description' => 'yes',
		'show_button' => 'yes',
		'show_slide_image' => 'yes',
		'title_tags' => 'h2',
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
		'border_radius_advanced' => '30% 70% 82% 18% / 46% 62% 38% 54%',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => -60,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nnx_position_tablet' => array(
			'size' => 0,
		),
		'dots_nnx_position_mobile' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'dots_nny_position_tablet' => array(
			'size' => 30,
		),
		'dots_nny_position_mobile' => array(
			'size' => 30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncx_position_tablet' => array(
			'size' => 0,
		),
		'both_ncx_position_mobile' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_ncy_position_tablet' => array(
			'size' => 40,
		),
		'both_ncy_position_mobile' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => -60,
		),
		'both_cy_position' => array(
			'size' => 30,
		),
		'arrows_fraction_ncx_position' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_fraction_ncy_position' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_fraction_cx_position' => array(
			'size' => -60,
		),
		'arrows_fraction_cy_position' => array(
			'size' => 30,
		),
		'progress_y_position' => array(
			'size' => 15,
		),
	),
	'repeaters' => array(
		'slides' => array(
			'defaults'     => array(
				'sub_title' => 'Subtitle Goes Here',
				'title' => 'Slide Title Here',
				'title_link' => array(
					'url' => '',
				),
				'slide_button' => 'Read More',
				'button_link' => array(
					'url' => '#',
				),
				'description' => 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. Recusandae voluptate repellendus magni illo ea animi?',
			),
			'default_rows' => array( array(
					'title' => 'Fancy Slider Item One',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'title' => 'Fancy Slider Item Two',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'title' => 'Fancy Slider Item Three',
					'slide_image' => array(
						'url' => $placeholder_url,
					),
				) ),
		),
	),
	'rename'    => array( 'thumbnail_size_size' => 'image_size' ),
	'values'    => array(
		'image_size' => array( 'thumbnail' => 'medium', 'medium_large' => 'large', '1536x1536' => 'full', '2048x2048' => 'full', 'custom' => 'large' ),
		'title_tags' => array( 'h1' => 'h2', 'p' => 'div', 'span' => 'div' ),
		'navigation' => static function ( $value ) {
			return 'none' === $value ? 'none' : 'arrows';
		},
	),
	// Los interruptores de Element Pack ocultaban partes de cada diapositiva.
	'filter'    => static function ( array $out ) {
		$hide = array( 'show_subtitle' => 'sub_title', 'show_title' => 'title', 'show_description' => 'description', 'show_button' => 'slide_button', 'show_slide_image' => 'slide_image' );
		foreach ( $out['slides'] as $index => $slide ) {
			foreach ( $hide as $flag => $key ) {
				if ( 'yes' !== ( $out[ $flag ] ?? 'yes' ) ) {
					unset( $slide[ $key ] );
				}
			}
			$out['slides'][ $index ] = $slide;
		}
		foreach ( array_keys( $hide ) as $flag ) {
			unset( $out[ $flag ] );
		}
		return $out;
	},
);
