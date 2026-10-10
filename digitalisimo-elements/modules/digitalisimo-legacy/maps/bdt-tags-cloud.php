<?php
/** Element Pack Pro 9.9.1 `bdt-tags-cloud` → `digitalisimo-tags-cloud`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-tags-cloud',
	// Clases de Element Pack más usadas en sus selectores: .bdt-tags-list, .bdt-tags-cloud
	'classes'   => array( '.bdt-tags-cloud .bdt-tags-list li a' => '.digi-tags-cloud__link', 'ul.bdt-tags-list li a' => '.digi-tags-cloud__link', '.bdt-tags-cloud' => '.digi-tags-cloud' ),
	'defaults'  => array(
		'data_source' => 'dynamic',
		'cloud_style' => 'circle',
		'globe_active_cursor' => 'pointer',
		'globe_depth' => array(
			'unit' => 'px',
			'size' => 80,
		),
		'globe_animation_speed' => array(
			'unit' => 'px',
			'size' => 50,
		),
		'globe_animation_type' => 'hover',
		'globe_outline_method' => 'outline',
		'globe_fade_in' => array(
			'unit' => 'px',
			'size' => 1,
		),
		'basic_tags_bg_type' => 'random',
		'basic_tags_hover_effect' => 'scale(1.1)',
		'globe_shadow_color' => 'none',
		'globe_shadow_blur' => array(
			'unit' => 'px',
			'size' => 10,
		),
		'globe_text_bg_radius' => array(
			'unit' => 'px',
			'size' => 0,
		),
		'globe_outline_colour' => '#ddd',
		'globe_outline_thickness' => array(
			'unit' => 'px',
			'size' => 2,
		),
		'globe_bg_outline_thickness' => array(
			'unit' => 'px',
			'size' => 0,
		),
		'globe_outline_dash' => array(
			'unit' => 'px',
			'size' => 0,
		),
		'globe_outline_dash_space' => array(
			'unit' => 'px',
			'size' => 2,
		),
		'globe_outline_dash_speed' => array(
			'unit' => 'px',
			'size' => 3,
		),
		'globe_outline_increase' => array(
			'unit' => 'px',
			'size' => 5,
		),
		'globe_outline_border_radius' => array(
			'unit' => 'px',
			'size' => 2,
		),
		'cloud_color' => 'random-dark',
	),
	'repeaters' => array(
		'static_tags' => array(
			'defaults'     => array(
				'tag_text' => 'Tag',
				'tag_link' => array(
					'url' => '#',
				),
				'tag_weight' => 1,
			),
			'default_rows' => array( array(
					'tag_text' => 'Design',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 8,
				), array(
					'tag_text' => 'Development',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 6,
				), array(
					'tag_text' => 'WordPress',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 9,
				), array(
					'tag_text' => 'Elementor',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 7,
				), array(
					'tag_text' => 'JavaScript',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 5,
				), array(
					'tag_text' => 'CSS',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 4,
				), array(
					'tag_text' => 'HTML',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 3,
				), array(
					'tag_text' => 'PHP',
					'tag_link' => array(
						'url' => '#',
					),
					'tag_weight' => 6,
				) ),
		),
	),
	// Las etiquetas del sitio pasan a la taxonomía de etiquetas; las escritas en Element Pack, a etiquetas estáticas.
	'filter'    => static function ( array $out ) {
		if ( 'static' === ( $out['data_source'] ?? 'dynamic' ) ) {
			$out['source'] = 'static';
			if ( 'yes' === ( $out['static_open_new_window'] ?? '' ) ) {
				foreach ( $out['static_tags'] as $index => $tag ) {
					$out['static_tags'][ $index ]['tag_link']['is_external'] = 'on';
				}
			}
		} else {
			$out['source']   = 'taxonomy';
			$out['taxonomy'] = 'post_tag';
			unset( $out['static_tags'] );
		}
		foreach ( array( 'data_source', 'custom_post_type', 'custom_post_type_input', 'open_new_window', 'static_open_new_window', 'cloud_style', 'cloud_color', '_skin' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
