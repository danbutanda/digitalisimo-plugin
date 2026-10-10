<?php
/** Element Pack Pro 9.9.1 `bdt-animated-card` → `digitalisimo-fancy-card`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-card',
	'classes'   => array(
		'.bdt-ep-animated-card-title'       => '.digi-fancy-card__title',
		'.bdt-ep-animated-card-sub-title'   => '.digi-fancy-card__badge',
		'.bdt-ep-animated-card-description' => '.digi-fancy-card__description',
		'.bdt-ep-animated-card-readmore'    => '.digi-fancy-card__link',
		'.bdt-ep-animated-card-image'       => '.digi-fancy-card__visual',
		'.bdt-ep-animated-card-badge'       => '.digi-fancy-card__badge',
		'.bdt-ep-animated-card'             => '.digi-fancy-card',
	),
	'defaults'  => array(
		'image' => array(
			'url' => $placeholder_url,
		),
		'title_text' => 'Animated Card Title',
		'sub_title_text' => 'This is a Label',
		'description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
		'item_height' => array(
			'unit' => 'px',
		),
		'thumbnail_size_size' => 'full',
		'layout_direction' => 'style-1',
		'show_title' => 'yes',
		'title_size' => 'h3',
		'show_sub_title' => 'yes',
		'sub_title_size' => 'h4',
		'show_description' => 'yes',
		'readmore' => 'yes',
		'readmore_text' => 'Read More',
		'readmore_link' => array(
			'url' => '#',
		),
		'readmore_icon_align' => 'right',
		'readmore_icon_indent' => array(
			'size' => 8,
		),
		'button_css_id' => '',
		'image_height' => array(
			'unit' => 'px',
		),
		'image_object_fit' => 'contain',
		'image_object_position' => 'center center',
		'image_hover_transition' => array(
			'size' => 0.3,
		),
		'image_height_hover' => array(
			'unit' => 'px',
		),
		'image_horizontal_offset' => array(
			'unit' => '%',
			'size' => 80,
		),
	),
	'filter'    => static function ( array $out ) {
		$set = array(
			'icon_type'        => 'image',
			'image'            => is_array( $out['image'] ?? null ) ? $out['image'] : array( 'url' => '' ),
			'badge_text'       => 'yes' === ( $out['show_sub_title'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['sub_title_text'] ?? '' ) ) ) : '',
			'title_text'       => 'yes' === ( $out['show_title'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['title_text'] ?? '' ) ) ) : '',
			'description_text' => 'yes' === ( $out['show_description'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['description_text'] ?? '' ) ) ) : '',
			'button_text'      => 'yes' === ( $out['readmore'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['readmore_text'] ?? '' ) ) ) : '',
			'link'             => is_array( $out['readmore_link'] ?? null ) ? $out['readmore_link'] : array( 'url' => '' ),
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(image|sub_title_text|description_text|layout_direction|show_title|title_link_url|sub_title_size|show_sub_title|show_description|readmore|readmore_text|readmore_link|advanced_readmore_icon|readmore_icon_align|button_css_id|readmore_attention|readmore_hover_animation)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
