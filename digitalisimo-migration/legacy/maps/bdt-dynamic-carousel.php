<?php
/** Element Pack Pro 9.9.1 `bdt-dynamic-carousel` → `loop-carousel`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'loop-carousel',
	// Clases de Element Pack más usadas en sus selectores: .bdt-navigation-prev, .bdt-navigation-next, .bdt-dots-container, .bdt-ep-dynamic-carousel-item
	'classes'   => array(),
	'defaults'  => array(
		'posts_source' => 'post',
		'posts_per_page' => 6,
		'posts_orderby' => 'date',
		'posts_order' => 'desc',
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 35,
		),
		'item_gap_tablet' => array(
			'size' => 20,
		),
		'item_gap_mobile' => array(
			'size' => 20,
		),
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'skin' => 'carousel',
		'coverflow_rotate' => array(
			'size' => 50,
		),
		'coverflow_stretch' => array(
			'size' => 0,
		),
		'coverflow_modifier' => array(
			'size' => 1,
		),
		'coverflow_depth' => array(
			'size' => 100,
		),
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
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
	// Una plantilla de Elementor por entrada, en el carrusel del Loop Carousel de PRO Elements.
	'filter'    => static function ( array $out ) {
		$out = Digitalisimo\Elements\Legacy\Translator::loop_query( $out );
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			if ( isset( $out[ 'columns' . $device ] ) ) {
				$out[ 'slides_to_show' . $device ] = (string) $out[ 'columns' . $device ];
			}
			if ( isset( $out[ 'item_gap' . $device ]['size'] ) ) {
				$out[ 'image_spacing_custom' . $device ] = array( 'size' => (int) $out[ 'item_gap' . $device ]['size'], 'unit' => 'px' );
			}
		}
		$navigation               = (string) ( $out['navigation'] ?? 'arrows' );
		$out['arrows']            = in_array( $navigation, array( 'arrows', 'both', 'arrows-fraction', 'arrows-progress' ), true ) ? 'yes' : '';
		$out['pagination']        = in_array( $navigation, array( 'dots', 'both' ), true ) ? 'bullets' : ( in_array( $navigation, array( 'fraction', 'arrows-fraction' ), true ) ? 'fraction' : ( in_array( $navigation, array( 'progressbar', 'arrows-progress' ), true ) ? 'progressbar' : '' ) );
		$out['autoplay']          = 'yes' === ( $out['autoplay'] ?? '' ) ? 'yes' : 'no';
		$out['autoplay_speed']    = (int) ( $out['autoplay_speed'] ?? 5000 );
		$out['infinite']          = 'yes' === ( $out['loop'] ?? '' ) ? 'yes' : '';
		$out['speed']             = (int) ( $out['speed']['size'] ?? 500 );
		$out['pause_on_hover']    = 'yes' === ( $out['pauseonhover'] ?? '' ) ? 'yes' : '';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(columns|item_gap|navigation|loop|pauseonhover|skin|coverflow_.*|both_position|arrows_fraction_position|arrows_position|dots_position|progress_position|nav_arrows_icon|hide_arrow_on_mobile|arrows_nc[xy]_position|slides_to_scroll|centered_slides|grab_cursor|free_mode|observer|mousewheel|show_hidden_item|dynamic_bullets|show_scrollbar)(_tablet|_mobile)?$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
