<?php
/** Element Pack Pro 9.9.1 `bdt-step-flow` → `digitalisimo-advanced-icon-box`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-advanced-icon-box',
	'classes'   => array(
		'.bdt-step-flow-readmore'      => '.digi-advanced-icon-box__more',
		'.bdt-step-flow-content'       => '.digi-advanced-icon-box__content',
		'.bdt-step-flow-badge'         => '.digi-advanced-icon-box__badge',
		'.bdt-step-flow-title'         => '.digi-advanced-icon-box__title',
		'.bdt-step-flow-description'   => '.digi-advanced-icon-box__description',
		'.bdt-step-flow-icon'          => '.digi-advanced-icon-box__visual',
		'.bdt-title-separator-wrapper' => '.digi-advanced-icon-box__separator',
		'.bdt-title-separator'         => '.digi-advanced-icon-box__separator',
		'.bdt-icon-wrapper'            => '.digi-advanced-icon-box__visual',
		'.bdt-step-flow'               => '.digi-advanced-icon-box',
	),
	'defaults'  => array(
		'icon_type' => 'icon',
		'selected_icon' => array(
			'value' => 'fas fa-directions',
			'library' => 'fa-solid',
		),
		'image' => array(
			'url' => $placeholder_url,
		),
		'thumbnail_size_size' => 'full',
		'title_text' => 'Step Flow Heading',
		'description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
		'title_size' => 'h3',
		'badge' => 'yes',
		'show_indicator' => 'yes',
		'readmore_text' => 'Read More',
		'readmore_link' => array(
			'url' => '#',
		),
		'readmore_icon_align' => 'right',
		'readmore_icon_indent' => array(
			'size' => 8,
		),
		'readmore_horizontal_offset' => array(
			'size' => -50,
		),
		'readmore_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'readmore_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'readmore_vertical_offset' => array(
			'size' => 0,
		),
		'readmore_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'readmore_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'button_css_id' => '',
		'badge_text' => 'Step 01',
		'badge_position' => 'top-center',
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
		'icon_radius_advanced' => '75% 25% 43% 57% / 46% 29% 71% 54%',
		'icon_space' => array(
			'size' => 15,
		),
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
		'icon_effect' => 'none',
		'icon_hover_rotate' => array(
			'unit' => 'deg',
		),
		'icon_hover_background_rotate' => array(
			'unit' => 'deg',
		),
		'icon_horizontal_offset' => array(
			'size' => 0,
		),
		'icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'icon_vertical_offset' => array(
			'size' => 0,
		),
		'icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'title_separator_type' => 'line',
		'divider_align' => 'center',
		'divider_line_align' => 'center',
		'title_separator_border_style' => 'solid',
		'line_cap' => 'ep_square',
		'direction_style' => '1',
		'direction_rotate' => array(
			'size' => 0,
		),
		'direction_rotate_tablet' => array(
			'size' => 0,
		),
		'direction_rotate_mobile' => array(
			'size' => 0,
		),
	),
	// Las animaciones del botón no se trasladan; el indicador de dirección pasa a la flecha propia de la caja.
	'drop'      => array( 'show_indicator', 'direction_hide_on', 'readmore_on_hover', 'button_css_id', 'advanced_readmore_icon', 'readmore_icon_align' ),
	'filter'    => static function ( array $out, array $settings ) {
		$styles                    = array( '1' => 'curve', '2' => 'curve-dashed', '3' => 'straight', '4' => 'straight-dashed', '5' => 'curve', '6' => 'curve-dashed', '7' => 'curve', '8' => 'curve-dashed' );
		$out['step_arrow']         = 'yes' === ( $settings['show_indicator'] ?? 'yes' ) ? 'yes' : '';
		$out['step_arrow_style']   = $styles[ (string) ( $out['direction_style'] ?? '1' ) ] ?? 'curve';
		$out['step_arrow_hide_on'] = array_values( array_intersect( array( 'desktop', 'tablet', 'mobile' ), (array) ( $settings['direction_hide_on'] ?? array() ) ) );
		// Element Pack sólo aplicaba desplazamiento y giro con «direction_offset_toggle».
		$moves = array( 'direction_width' => 'step_arrow_width' );
		if ( 'yes' === ( $out['direction_offset_toggle'] ?? '' ) ) {
			$moves += array( 'direction_offset_x' => 'step_arrow_offset_x', 'direction_offset_y' => 'step_arrow_offset_y', 'direction_rotate' => 'step_arrow_rotate' );
		}
		foreach ( $moves as $from => $to ) {
			foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
				if ( isset( $out[ $from . $device ]['size'] ) && '' !== (string) $out[ $from . $device ]['size'] ) {
					$out[ $to . $device ] = $out[ $from . $device ];
				}
			}
		}
		if ( ! empty( $out['direction_color'] ) ) {
			$out['step_arrow_color'] = $out['direction_color'];
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( 0 === strpos( $key, 'direction_' ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
