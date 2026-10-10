<?php
/** Element Pack Pro 9.9.1 `bdt-audio-player` → `digitalisimo-audio-player`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-audio-player',
	'classes'   => array( '.bdt-audio-player' => '.digi-audio' ),
	'defaults'  => array(
		'poster' => array(
			'url' => '',
		),
		'showcase_bg_image' => array(
			'url' => '',
		),
		'layout_style' => 'style-1',
		'player_height' => array(
			'size' => 400,
		),
		'source_type' => 'hosted_url',
		'hosted_url' => array(
			'url' => '',
		),
		'remote_url' => array(
			'url' => '',
		),
		'audio_title' => 'tooltip',
		'title' => 'Audio Title',
		'author_name' => 'John Duo',
		'player_align' => 'start',
		'showcase_initial_song' => 3,
		'showcase_volume_level' => array(
			'size' => 0.8,
		),
		'showcase_show_shuffle' => 'yes',
		'showcase_show_like' => 'yes',
		'showcase_show_duration' => 'yes',
		'showcase_show_progress_time' => 'yes',
		'showcase_slider_width' => array(
			'size' => 300,
			'unit' => 'px',
		),
		'showcase_swiper_speed' => 700,
		'seek_bar' => 'yes',
		'time_duration' => 'both',
		'restrict_duration' => array(
			'size' => 10,
		),
		'volume_mute' => 'yes',
		'volume_bar' => 'yes',
		'smooth_show' => 'yes',
		'keyboard_enable' => 'yes',
		'volume_level' => array(
			'size' => 0.8,
		),
		'skin_poster_align' => 'center',
		'thumb_width' => array(
			'size' => 150,
		),
		'play_button_border' => 'solid',
		'play_button_border_width' => array(
			'top' => '1',
			'bottom' => '1',
			'left' => '1',
			'right' => '1',
			'unit' => 'px',
		),
		'play_button_border_color' => '#d5d5d5',
		'volume_button_border' => 'solid',
		'volume_button_border_width' => array(
			'top' => '1',
			'bottom' => '1',
			'left' => '1',
			'right' => '1',
			'unit' => 'px',
		),
		'volume_button_border_color' => '#d5d5d5',
		'showcase_min_height' => array(
			'size' => 650,
			'unit' => 'px',
		),
		'showcase_overlay_color' => '',
		'showcase_playlist_item_spacing' => array(
			'size' => 15,
			'unit' => 'px',
		),
		'showcase_controls_icon_size' => array(
			'size' => 28,
			'unit' => 'px',
		),
		'showcase_volume_range_width' => array(
			'size' => 72,
			'unit' => 'px',
		),
		'showcase_volume_range_height' => array(
			'size' => 4,
			'unit' => 'px',
		),
		'showcase_play_btn_icon_size' => array(
			'size' => 22,
			'unit' => 'px',
		),
		'showcase_progress_width' => array(
			'size' => 90,
			'unit' => '%',
		),
		'showcase_progress_height' => array(
			'size' => 5,
			'unit' => 'px',
		),
		'showcase_progress_thumb_size' => array(
			'size' => 15,
			'unit' => 'px',
		),
	),
	'repeaters' => array(
		'showcase_playlist' => array(
			'defaults'     => array(
				'cover' => array(
					'url' => '',
				),
				'artist' => 'Artist Name',
				'song_title' => 'Song Title',
				'song_url' => array(
					'url' => '',
				),
			),
			'default_rows' => array(),
		),
	),
	'filter'    => static function ( array $out ) {
		$tracks = array();
		if ( 'showcase' === ( $out['_skin'] ?? '' ) || ! empty( $out['showcase_playlist'] ) && 'showcase' === ( $out['layout_style'] ?? '' ) ) {
			foreach ( is_array( $out['showcase_playlist'] ?? null ) ? $out['showcase_playlist'] : array() as $row ) {
				$tracks[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'audio_url' => is_array( $row['song_url'] ?? null ) ? $row['song_url'] : array( 'url' => '' ), 'title' => (string) ( $row['song_title'] ?? '' ), 'artist' => (string) ( $row['artist'] ?? '' ), 'cover' => is_array( $row['cover'] ?? null ) ? $row['cover'] : array() );
			}
		} else {
			$hosted   = 'hosted_url' === ( $out['source_type'] ?? 'hosted_url' );
			$tracks[] = array( '_id' => 'track', 'audio' => $hosted && is_array( $out['hosted_url'] ?? null ) ? $out['hosted_url'] : array( 'url' => '' ), 'audio_url' => ! $hosted && is_array( $out['remote_url'] ?? null ) ? $out['remote_url'] : array( 'url' => '' ), 'title' => (string) ( $out['title'] ?? '' ), 'artist' => (string) ( $out['author_name'] ?? '' ) );
		}
		$out['tracks'] = $tracks;
		$out['loop']   = 'yes' === ( $out['loop'] ?? '' ) || 'yes' === ( $out['showcase_loop'] ?? '' ) ? 'yes' : '';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(layout_style|source_type|hosted_url|remote_url|audio_title|title|author_name|fixed_player|showcase_.*|seek_bar|time_.*|restrict_duration|volume_.*|smooth_show|keyboard_enable|autoplay|thumb_style|skin_poster_align)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
