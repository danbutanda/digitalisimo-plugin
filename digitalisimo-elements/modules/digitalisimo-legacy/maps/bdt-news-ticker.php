<?php
/** Element Pack Pro 9.9.1 `bdt-news-ticker` → `digitalisimo-marquee`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-marquee',
	'classes'   => array( '.bdt-news-ticker-label' => '.digi-marquee__label', '.bdt-news-ticker' => '.digi-marquee' ),
	'defaults'  => array(
		'news_ticker_custom_content' => 'dynamic',
		'show_label' => 'yes',
		'news_label' => 'LATEST NEWS',
		'news_content' => 'title',
		'news_ticker_height' => array(
			'size' => 42,
		),
		'show_navigation' => 'yes',
		'navigation_size' => array(
			'size' => 14,
		),
		'slider_animations' => 'fade',
		'autoplay' => 'yes',
		'autoplay_interval' => 5000,
		'pause_on_hover' => 'yes',
		'speed' => 500,
		'scroll_speed' => array(
			'size' => 1,
		),
	),
	'repeaters' => array(
		'news_ticker_content_list' => array(
			'defaults'     => array(),
			'default_rows' => array( array(
					'news_ticker_title' => 'News Content #1',
					'news_ticker_link' => array(
						'url' => 'https://www.example.com/',
						'is_external' => '',
						'nofollow' => '',
					),
				), array(
					'news_ticker_title' => 'News Content #2',
					'news_ticker_link' => array(
						'url' => 'https://www.example.com/',
						'is_external' => '',
						'nofollow' => '',
					),
				), array(
					'news_ticker_title' => 'News Content #3',
					'news_ticker_link' => array(
						'url' => 'https://www.example.com/',
						'is_external' => '',
						'nofollow' => '',
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$custom = 'custom' === ( $out['news_ticker_custom_content'] ?? 'dynamic' );
		$items  = array();
		foreach ( $custom && is_array( $out['news_ticker_content_list'] ?? null ) ? $out['news_ticker_content_list'] : array() as $row ) {
			$items[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'text' => (string) ( $row['news_ticker_title'] ?? '' ), 'image' => array(), 'link' => is_array( $row['news_ticker_link'] ?? null ) ? $row['news_ticker_link'] : array() );
		}
		$out['source']         = $custom ? 'items' : 'posts';
		$out['items']          = $items;
		$out['posts_count']    = (int) ( $out['posts_per_page'] ?? 5 );
		$out['label']          = 'yes' === ( $out['show_label'] ?? '' ) ? (string) ( $out['news_label'] ?? '' ) : '';
		$out['pause_on_hover'] = 'yes' === ( $out['pause_on_hover'] ?? '' ) ? 'yes' : '';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(news_.*|show_label|show_date|date_reverse|show_time|show_navigation|play_pause|slider_animations|autoplay.*|speed|scroll_speed|posts_.*)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
