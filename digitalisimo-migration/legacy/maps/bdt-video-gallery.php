<?php
/**
 * Element Pack Pro 9.9.1 `bdt-video-gallery` → `digitalisimo-video-player`: la galería pasa a la lista de
 * vídeos del reproductor, con título y descripción de cada vídeo y del vídeo elegido.
 */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-video-player',
	'classes'   => array(
		'.rvs-nav-container .rvs-nav-item-credits' => '.digi-video-player__item-description',
		'.rvs-nav-container .rvs-nav-item-title'   => '.digi-video-player__item-title',
		'.rvs-nav-container a.rvs-nav-item'        => '.digi-video-player__item',
		'.bdt-video-gallery .rvs-item'             => '.digi-video-player__stage',
		'.rvs-nav-item.rvs-active'                 => '.digi-video-player__item[aria-pressed=true]',
		'.rvs-nav-item-credits'                    => '.digi-video-player__item-description',
		'.rvs-nav-item-title'                      => '.digi-video-player__item-title',
		'.rvs-item-text h2'                        => '.digi-video-player__now-title',
		'.bdt-vg-video-desc'                       => '.digi-video-player__now-description',
		'.rvs-nav-item span'                       => '.digi-video-player__item span',
		'.rvs-nav-item'                            => '.digi-video-player__item',
	),
	'defaults'  => array(
		'thumb_layout' => 'vertical',
		'show_video_title' => 'yes',
		'show_video_title_tags' => 'h2',
		'show_video_desc' => 'yes',
		'show_thumbnail_thumb' => 'yes',
		'show_thumbnail_title' => 'yes',
		'thumbnail_title_tags' => 'h4',
		'show_thumbnail_desc' => 'yes',
		'youtube_autoplay_muted' => 'yes',
		'poster_bg_position' => '',
		'poster_bg_size' => '',
		'thumb_width' => array(
			'unit' => '%',
		),
		'thumb_width_tablet' => array(
			'unit' => '%',
		),
		'thumb_width_mobile' => array(
			'unit' => '%',
		),
	),
	'repeaters' => array(
		'video_gallery' => array(
			'defaults'     => array(
				'source_type' => 'remote_url',
				'remote_url' => array(
					'url' => '',
				),
				'title' => 'Video Title',
				'desc' => 'Women typing keyboard',
			),
			// Las filas de ejemplo eran vídeos de terceros: una galería que nunca se editó queda vacía.
			'default_rows' => array(),
		),
	),
	// Cada vídeo remoto (YouTube, Vimeo) pasa a una diapositiva de vídeo con su póster.
	'filter'    => static function ( array $out ) {
		$playlist = array();
		foreach ( is_array( $out['video_gallery'] ?? null ) ? $out['video_gallery'] : array() as $row ) {
			$hosted = 'hosted_url' === ( $row['source_type'] ?? 'remote_url' );
			$url    = $hosted ? (string) ( $row['hosted_url']['url'] ?? '' ) : (string) ( $row['remote_url']['url'] ?? '' );
			// Los vídeos de ejemplo guardados dentro del plugin heredado desaparecen con él.
			if ( '' === $url || false !== strpos( $url, '/bdthemes-element-pack/' ) ) {
				continue;
			}
			$item = array( '_id' => (string) ( $row['_id'] ?? '' ), 'title' => trim( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ) ), 'description' => trim( wp_strip_all_tags( (string) ( $row['desc'] ?? '' ) ) ), 'poster' => is_array( $row['poster'] ?? null ) ? $row['poster'] : array( 'url' => '' ) );
			if ( $hosted ) {
				$item += array( 'source' => 'media', 'video' => $row['hosted_url'] );
			} else {
				$item += array( 'source' => 'url', 'video_url' => array( 'url' => 0 === strpos( $url, '//' ) ? 'https:' . $url : $url ) );
			}
			$playlist[] = $item;
		}
		$first                   = $playlist[0] ?? array();
		$out['playlist']         = $playlist;
		$out['playlist_trigger'] = 'click';
		$out['playlist_caption'] = 'yes' === ( $out['show_video_title'] ?? 'yes' ) || 'yes' === ( $out['show_video_desc'] ?? 'yes' ) ? 'yes' : '';
		$out['source']           = $first['source'] ?? 'url';
		$out['video']            = $first['video'] ?? array( 'url' => '' );
		$out['video_url']        = $first['video_url'] ?? array( 'url' => '' );
		$out['poster']           = $first['poster'] ?? array( 'url' => '' );
		$out['title']            = $first['title'] ?? '';
		unset( $out['video_gallery'] );
		return $out;
	},
);
