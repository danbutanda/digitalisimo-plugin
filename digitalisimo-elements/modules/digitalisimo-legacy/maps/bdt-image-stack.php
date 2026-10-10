<?php
/** Element Pack Pro 9.9.1 `bdt-image-stack` → `image-gallery`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'image-gallery',
	'classes'   => array(),
	'defaults'  => array(
		'thumbnail_size_size' => 'medium',
		'alignment' => 'center',
		'item_effect_transition_duration' => '300',
		'item_effect_transition_easing' => 'ease-out',
		'tooltip_text_align' => 'center',
	),
	'repeaters' => array(
		'image_stack_items' => array(
			'defaults'     => array(
				'media_type' => 'image',
				'selected_icon' => array(
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'image' => array(
					'url' => $placeholder_url,
				),
				'tooltip_placement' => 'top',
			),
			'default_rows' => array( array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				) ),
		),
	),
	// Las imágenes apiladas pasan a la galería básica; iconos, enlaces y globos no se trasladan.
	'filter'    => static function ( array $out ) {
		$images = array();
		foreach ( is_array( $out['image_stack_items'] ?? null ) ? $out['image_stack_items'] : array() as $row ) {
			if ( 'icon' !== ( $row['media_type'] ?? 'image' ) && ! empty( $row['image']['url'] ) ) {
				$images[] = array( 'id' => (int) ( $row['image']['id'] ?? 0 ), 'url' => (string) $row['image']['url'] );
			}
		}
		$out['wp_gallery']      = $images;
		$out['gallery_columns'] = (string) max( 1, min( 10, count( $images ) ) );
		$out['gallery_link']    = 'none';
		unset( $out['image_stack_items'] );
		return $out;
	},
);
