<?php
/** Element Pack Pro 9.9.1 `bdt-logo-carousel` → `digitalisimo-logo-carousel`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-logo-carousel',
	'classes'   => array(
		'.bdt-logo-carousel-wrapper' => '.digi-logo-carousel',
		'.bdt-logo-carousel-item'    => '.digi-logo-carousel__slide',
		'.bdt-logo-carousel-figure'  => '.digi-logo-carousel__card',
		'.bdt-logo-carousel-img'     => '.digi-logo-carousel__image',
		'.bdt-navigation-prev'       => '.digi-carousel__button',
		'.bdt-navigation-next'       => '.digi-carousel__button',
	),
	'defaults'  => array(
		'columns' => '4',
		'columns_tablet' => '3',
		'columns_mobile' => '1',
		'item_gap' => array(
			'size' => 10,
		),
		'thumbnail_size' => 'large',
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'logo_tooltip_x_offset' => array(
			'size' => 0,
		),
		'logo_tooltip_y_offset' => array(
			'size' => 0,
		),
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'pause_on_hover' => 'yes',
		'loop' => 'yes',
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'nav_arrows_icon' => '5',
		'logo_tooltip_text_align' => 'center',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => -60,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => -60,
		),
		'both_cy_position' => array(
			'size' => 30,
		),
	),
	'repeaters' => array(
		'logo_list' => array(
			'defaults'     => array(
				'image' => array(
					'url' => $placeholder_url,
				),
				'name' => 'Brand Name',
				'description' => 'Brand Short Description Type Here.',
				'logo_tooltip_placement' => 'top',
			),
			// Las imágenes de ejemplo eran recursos de Element Pack: se usa la de Elementor.
			'default_rows' => array_fill( 0, 8, array( 'image' => array( 'url' => $placeholder_url ) ) ),
		),
	),
	// Default no literal, revisar: logo_list (filas): $args['default_items']
	'filter'    => static function ( array $out ) {
		$items = array();
		foreach ( is_array( $out['logo_list'] ?? null ) ? $out['logo_list'] : array() as $row ) {
			$items[] = array(
				'_id'   => (string) ( $row['_id'] ?? '' ),
				'image' => is_array( $row['image'] ?? null ) ? $row['image'] : array(),
				'name'  => trim( wp_strip_all_tags( (string) ( $row['name'] ?? '' ) ) ),
				'link'  => is_array( $row['link'] ?? null ) ? $row['link'] : array( 'url' => '' ),
			);
		}
		$out['logo_items'] = $items;
		$out['navigation'] = in_array( $out['navigation'] ?? '', array( 'arrows', 'both', 'arrows-fraction' ), true ) ? 'arrows' : 'none';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(logo_list|image_mask_shape.*|logo_tooltip.*|autoplay.*|pause_on_hover|loop|center_slide|both_position|arrows_position|dots_position|nav_arrows_icon|hide_arrow_on_mobile|hover_animation|show_.*tooltip.*)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
