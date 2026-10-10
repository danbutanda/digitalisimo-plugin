<?php
/**
 * Element Pack Pro 9.9.1 `bdt-lottie-image` → `lottie` (PRO Elements).
 * El archivo de ejemplo de Element Pack desaparece con el plugin; un JSON en código no tiene equivalente.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'lottie',
	'classes'  => array(
		'.bdt-lottie-image svg' => '.e-lottie__animation svg',
		'.widget-image-caption' => '.e-lottie__caption',
		'.bdt-lottie-image'     => '.e-lottie__container',
	),
	'defaults' => array( 'lottie_json_source' => 'url', 'play_action' => 'autoplay', 'view_type' => 'pageload', 'loop' => 'yes', 'lottie_renderer' => 'svg', 'caption_source' => 'none', 'link_to' => 'none' ),
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
		return $out;
	},
);
