<?php
/** Element Pack Pro 9.9.1 `bdt-fancy-card` → `digitalisimo-fancy-card`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-card',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-fancy-card, .bdt-ep-fancy-card-icon-inner, .bdt-ep-fancy-card-content, .bdt-ep-fancy-card-climax, .bdt-ep-fancy-card-icon, .bdt-ep-fancy-card-toggole, .bdt-ep-fancy-card-readmore, .bdt-ep-fancy-card-badge, .bdt-ep-fancy-card-title, .bdt-ep-fancy-card-text, .bdt-ep-fancy-card-batty, .bdt-indicator-svg, .bdt-batty-face, .bdt-batty-face2
	'classes'   => array(
		'.bdt-ep-fancy-card-icon'        => '.digi-fancy-card__visual',
		'.bdt-ep-fancy-card-title'       => '.digi-fancy-card__title',
		'.bdt-ep-fancy-card-description' => '.digi-fancy-card__description',
		'.bdt-ep-fancy-card-readmore'    => '.digi-fancy-card__link',
		'.bdt-ep-fancy-card-badge span'  => '.digi-fancy-card__badge',
		'.bdt-ep-fancy-card-badge'       => '.digi-fancy-card__badge',
		'.bdt-ep-fancy-card'             => '.digi-fancy-card',
	),
	'defaults'  => array(
		'icon_type' => 'icon',
		'selected_icon' => array(
			'value' => 'far fa-laugh',
			'library' => 'fa-regular',
		),
		'image' => array(
			'url' => $placeholder_url,
		),
		'thumbnail_size' => 'full',
		'title_text' => 'Laugh',
		'description_text' => 'Click edit button to change this text. If you are going to use you need to be sure there text.',
		'fancy_card_icon_position' => '',
		'fancy_card_icon_style' => 'style1',
		'top_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'left_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'right_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'title_size' => 'h3',
		'readmore' => 'yes',
		'toggle_icon' => array(
			'value' => 'fas fa-plus',
			'library' => 'fa-solid',
		),
		'toggle_position' => 'bottom-right',
		'show_data_label' => 'yes',
		'readmore_text' => 'Read More',
		'readmore_link' => array(
			'url' => '#',
		),
		'readmore_icon_align' => 'right',
		'readmore_icon_indent' => array(
			'size' => 8,
		),
		'indicator_horizontal_offset' => array(
			'size' => 0,
		),
		'indicator_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'indicator_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'indicator_vertical_offset' => array(
			'size' => 0,
		),
		'indicator_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'indicator_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'indicator_rotate' => array(
			'size' => 0,
		),
		'indicator_rotate_tablet' => array(
			'size' => 0,
		),
		'indicator_rotate_mobile' => array(
			'size' => 0,
		),
		'badge_text' => 'POPULAR',
		'badge_position' => 'top-right',
		'badge_horizontal_offset' => array(
			'size' => 0,
		),
		'badge_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'badge_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'badge_vertical_offset' => array(
			'size' => 0,
		),
		'badge_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'badge_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'badge_rotate' => array(
			'size' => 0,
		),
		'badge_rotate_tablet' => array(
			'size' => 0,
		),
		'badge_rotate_mobile' => array(
			'size' => 0,
		),
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'icon_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'icon_background_rotate' => array(
			'size' => 0,
			'unit' => 'deg',
		),
		'background_hover_transition' => array(
			'size' => 0.3,
		),
		'icon_hover_rotate' => array(
			'unit' => 'deg',
		),
		'icon_hover_background_rotate' => array(
			'unit' => 'deg',
		),
		'indicator_style' => '1',
	),
	'strip'     => array( 'title_text', 'description_text' ),
	'values'    => array( 'title_size' => array( 'h1' => 'h2', 'p' => 'div', 'span' => 'div' ) ),
	// Un solo enlace propio: el botón «leer más» o, sin él, el enlace global de la tarjeta.
	'filter'    => static function ( array $out ) {
		$readmore           = 'yes' === ( $out['readmore'] ?? '' );
		$global             = 'yes' === ( $out['global_link'] ?? '' ) ? ( $out['global_link_url'] ?? array() ) : array();
		$out['button_text'] = $readmore ? (string) ( $out['readmore_text'] ?? '' ) : '';
		$out['link']        = $readmore ? ( $out['readmore_link'] ?? array() ) : $global;
		if ( 'yes' !== ( $out['badge'] ?? '' ) ) {
			$out['badge_text'] = '';
		}
		foreach ( array( 'readmore', 'readmore_text', 'readmore_link', 'global_link', 'global_link_url', 'title_link', 'title_link_url', 'badge', 'badge_position', 'toggle_icon', 'toggle_position', 'show_data_label', 'indicator', 'indicator_style', 'fancy_card_icon_position', 'fancy_card_icon_style', 'advanced_readmore_icon', 'readmore_icon_align', 'thumbnail_size', 'thumbnail_custom_dimension', '_skin' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
