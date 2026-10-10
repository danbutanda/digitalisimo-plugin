<?php
/** Element Pack Pro 9.9.1 `bdt-fancy-icons` → `digitalisimo-fancy-icons`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-icons',
	// Clases de Element Pack más usadas en sus selectores: .bdt-fancy-icons-item, .bdt-fancy-icons
	'classes'   => array(
		'.bdt-fancy-icons-item a.icon' => '.digi-fancy-icons__item .digi-fancy-icons__content',
		'.bdt-fancy-icons-item a.text' => '.digi-fancy-icons__item .digi-fancy-icons__content',
		'.bdt-fancy-icons-item a'      => '.digi-fancy-icons__item .digi-fancy-icons__content',
		'.bdt-fancy-icons-item'        => '.digi-fancy-icons__item',
		'.bdt-fancy-icons'             => '.digi-fancy-icons',
	),
	'defaults'  => array(
		'columns' => '2',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'background_type' => 'image',
		'background_image' => array(
			'url' => $placeholder_url,
		),
		'background_attachment' => 'inherit',
		'thumbnail_size_size' => 'full',
		'blend_type' => 'multiply',
		'icon_size' => array(
			'unit' => 'vw',
		),
	),
	'repeaters' => array(
		'share_items' => array(
			'defaults'     => array(
				'social_type' => 'icon',
				'social_icon' => array(
					'value' => 'fab fa-facebook-f',
					'library' => 'fa-brands',
				),
				'social_name' => 'Facebook',
				'social_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'social_name' => 'Facebook',
					'social_icon' => array(
						'value' => 'fab fa-facebook-f',
						'library' => 'fa-brands',
					),
				), array(
					'social_name' => 'X',
					'social_icon' => array(
						'value' => 'fab fa-x-twitter',
						'library' => 'fa-brands',
					),
				), array(
					'social_name' => 'Linkedin',
					'social_icon' => array(
						'value' => 'fab fa-linkedin-in',
						'library' => 'fa-brands',
					),
				), array(
					'social_name' => 'Instagram',
					'social_icon' => array(
						'value' => 'fab fa-instagram',
						'library' => 'fa-brands',
					),
				) ),
		),
	),
	'values'    => array( 'background_attachment' => array( 'inherit' => '' ) ),
	// Sólo una imagen elegida por el usuario se conserva como fondo: la de ejemplo y los videos no tienen equivalente.
	'filter'    => static function ( array $out ) use ( $placeholder_url ) {
		$image = $out['background_image']['url'] ?? '';
		if ( 'image' !== ( $out['background_type'] ?? 'image' ) || '' === $image || $image === $placeholder_url ) {
			unset( $out['background_image'], $out['background_attachment'] );
		}
		unset( $out['background_type'], $out['video_link'], $out['youtube_link'], $out['blend_type'] );
		return $out;
	},
);
