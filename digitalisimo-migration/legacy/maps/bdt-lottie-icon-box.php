<?php
/** Element Pack Pro 9.9.1 `bdt-lottie-icon-box` → `lottie`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'lottie',
	'classes'   => array(),
	'defaults'  => array(
		'lottie_json_source' => 'url',
		// La animación de ejemplo era un recurso de Element Pack.
		'lottie_json_path' => '',
		'play_action' => 'autoplay',
		'view_type' => 'pageload',
		'loop' => 'yes',
		'lottie_start_point' => array(
			'size' => '0',
			'unit' => '%',
		),
		'lottie_end_point' => array(
			'size' => '100',
			'unit' => '%',
		),
		'lottie_renderer' => 'svg',
		'speed' => array(
			'size' => '1',
		),
		'title_text' => 'Icon Box Heading',
		'sub_title_text' => 'Icon Box Sub Heading',
		'description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
		'position' => 'top',
		'icon_vertical_alignment' => 'top',
		'top_icon_vertical_offset' => array(
			'size' => 0,
		),
		'top_icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'top_icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'top_icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'left_right_icon_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'left_right_icon_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'title_size' => 'h3',
		'readmore' => 'yes',
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
		'background_hover_transition_image' => array(
			'size' => 0.3,
		),
		'icon_effect' => 'none',
		'icon_hover_rotate' => array(
			'unit' => 'deg',
		),
		'icon_hover_background_rotate' => array(
			'unit' => 'deg',
		),
		'title_separator_type' => 'line',
		'title_separator_border_style' => 'solid',
		'indicator_style' => '1',
		'caption_source' => 'none',
		'caption' => '',
		'link_to' => 'none',
		'width' => array(
			'unit' => '%',
		),
		'width_tablet' => array(
			'unit' => '%',
		),
		'width_mobile' => array(
			'unit' => '%',
		),
		'space' => array(
			'unit' => '%',
		),
		'space_tablet' => array(
			'unit' => '%',
		),
		'space_mobile' => array(
			'unit' => '%',
		),
		'caption_align' => '',
		'text_color' => '',
	),
	'filter'   => static function ( array $out ) {
		$source = (string) ( $out['lottie_json_source'] ?? 'url' );
		if ( 'local' === $source && is_array( $out['upload_json_file'] ?? null ) ) {
			$out['source']      = 'media_file';
			$out['source_json'] = $out['upload_json_file'];
		} else {
			$path = is_array( $out['lottie_json_path'] ?? null ) ? (string) ( $out['lottie_json_path']['url'] ?? '' ) : (string) ( $out['lottie_json_path'] ?? '' );
			$out['source']              = 'external_url';
			$out['source_external_url'] = array( 'url' => 'url' === $source && false === strpos( $path, '/bdthemes-element-pack/' ) ? $path : '' );
		}
		$captions = array( 'custom_caption' => 'custom', 'title_caption' => 'title' );
		$out['caption_source'] = $captions[ $out['caption_source'] ?? '' ] ?? 'none';
		if ( 'custom' === ( $out['link_to'] ?? '' ) ) {
			$out['custom_link'] = $out['link'] ?? array();
		} else {
			$out['link_to'] = 'none';
		}
		$triggers = array( 'hover' => 'on_hover', 'click' => 'on_click', 'scroll' => 'bind_to_scroll' );
		$out['trigger'] = $triggers[ $out['play_action'] ?? '' ] ?? ( 'scroll' === ( $out['view_type'] ?? '' ) ? 'bind_to_scroll' : 'arriving_to_viewport' );
		$out['loop']    = 'yes' === ( $out['loop'] ?? '' ) ? 'yes' : '';
		if ( isset( $out['speed']['size'] ) ) {
			$out['play_speed'] = array( 'size' => (float) $out['speed']['size'] );
		}
		foreach ( array( 'lottie_start_point' => 'start_point', 'lottie_end_point' => 'end_point' ) as $from => $to ) {
			if ( isset( $out[ $from ]['size'] ) ) {
				$out[ $to ] = array( 'size' => (float) $out[ $from ]['size'], 'unit' => '%' );
			}
		}
		$out['renderer'] = 'canvas' === ( $out['lottie_renderer'] ?? 'svg' ) ? 'canvas' : 'svg';
		foreach ( array( 'lottie_json_source', 'lottie_json_path', 'upload_json_file', 'lottie_json_code', 'play_action', 'view_type', 'speed', 'lottie_start_point', 'lottie_end_point', 'lottie_renderer', 'lottie_number_of_times', 'link' ) as $key ) {
			unset( $out[ $key ] );
		}
		// El título pasa a la leyenda; la descripción y el botón de la caja no se trasladan.
		if ( '' !== trim( (string) ( $out['title_text'] ?? '' ) ) ) {
			$out['caption_source'] = 'custom';
			$out['caption']        = trim( wp_strip_all_tags( (string) $out['title_text'] ) );
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(title_.*|description_text|readmore.*|button_.*|badge.*|global_link.*|show_separator|icon_inline|position)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
