<?php
/** Element Pack Pro 9.9.1 `bdt-timeline` → `digitalisimo-timeline`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-timeline',
	'classes'   => array( '.bdt-timeline-item' => '.digi-timeline__item', '.bdt-timeline-title' => '.digi-timeline__title', '.bdt-timeline-excerpt' => '.digi-timeline__text', '.bdt-timeline-date' => '.digi-timeline__date', '.bdt-timeline' => '.digi-timeline' ),
	'defaults'  => array(
		'timeline_source' => 'post',
		'timeline_align' => 'center',
		'visible_items' => 4,
		'readmore_text' => 'Read More',
		'button_size' => 'sm',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'show_image' => 'yes',
		'show_title' => 'yes',
		'title_tags' => 'h4',
		'title_link' => 'yes',
		'show_meta' => 'yes',
		'show_excerpt' => 'yes',
		'excerpt_length' => 15,
		'strip_shortcode' => 'yes',
		'show_readmore' => 'yes',
		'item_background_color' => '#f3f3f3',
		'timeline_line_width' => array(
			'size' => 4,
		),
		'icon_background' => '#ffffff',
		'icon_show' => 'yes',
		'icon_border_radius' => array(
			'size' => 50,
			'unit' => '%',
		),
		'date_background_color' => '#f3f3f3;',
		'date_radius' => array(
			'top' => '2',
			'right' => '2',
			'bottom' => '2',
			'left' => '2',
		),
		'date_padding' => array(
			'top' => '10',
			'right' => '15',
			'bottom' => '10',
			'left' => '15',
		),
		'thumbnail_size_size' => 'medium',
		'image_ratio' => array(
			'size' => 265,
		),
		'image_opacity' => array(
			'size' => 1,
		),
		'image_padding' => array(
			'top' => '20',
			'right' => '20',
			'bottom' => '0',
			'left' => '20',
		),
		'meta_color' => '#bbbbbb',
		'meta_spacing' => array(
			'size' => 10,
		),
		'excerpt_color' => '#888888',
		'excerpt_spacing' => array(
			'size' => 20,
		),
		'readmore_spacing' => array(
			'size' => 20,
		),
	),
	'repeaters' => array(
		'timeline_items' => array(
			'defaults'     => array(
				'timeline_title' => 'This is Timeline Item 1 Title',
				'timeline_date' => '31 December 2018',
				'timeline_image' => array(
					'url' => $placeholder_url,
				),
				'timeline_text' => 'I am timeline item content. Click edit button to change this text. A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart. I am alone, and feel the charm of existence in this spot, which was created for the bliss of souls like mine.',
				'timeline_link' => 'https://bdthemes.com',
				'timeline_select_icon' => array(
					'value' => 'fas fa-file-alt',
					'library' => 'fa-solid',
				),
			),
			'default_rows' => array( array(
					'timeline_title' => 'This is Timeline Item 1 Title',
					'timeline_text' => 'I am timeline item content. Click edit button to change this text. A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart. I am alone, and feel the charm of existence in this spot, which was created for the bliss of souls like mine.',
					'timeline_select_icon' => array(
						'value' => 'fas fa-file-alt',
						'library' => 'fa-solid',
					),
				), array(
					'timeline_title' => 'This is Timeline Item 2 Title',
					'timeline_text' => 'I am timeline item content. Click edit button to change this text. A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart. I am alone, and feel the charm of existence in this spot, which was created for the bliss of souls like mine.',
					'timeline_select_icon' => array(
						'value' => 'fas fa-file-alt',
						'library' => 'fa-solid',
					),
				), array(
					'timeline_title' => 'This is Timeline Item 3 Title',
					'timeline_text' => 'I am timeline item content. Click edit button to change this text. A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart. I am alone, and feel the charm of existence in this spot, which was created for the bliss of souls like mine.',
					'timeline_select_icon' => array(
						'value' => 'fas fa-file-alt',
						'library' => 'fa-solid',
					),
				), array(
					'timeline_title' => 'This is Timeline Item 4 Title',
					'timeline_text' => 'I am timeline item content. Click edit button to change this text. A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart. I am alone, and feel the charm of existence in this spot, which was created for the bliss of souls like mine.',
					'timeline_select_icon' => array(
						'value' => 'fas fa-file-alt',
						'library' => 'fa-solid',
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		$custom = 'custom' === ( $out['timeline_source'] ?? 'post' );
		$items  = array();
		foreach ( $custom && is_array( $out['timeline_items'] ?? null ) ? $out['timeline_items'] : array() as $row ) {
			$link    = $row['timeline_link'] ?? '';
			$items[] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'date' => (string) ( $row['timeline_date'] ?? '' ), 'title' => (string) ( $row['timeline_title'] ?? '' ), 'text' => (string) ( $row['timeline_text'] ?? '' ), 'image' => is_array( $row['timeline_image'] ?? null ) ? $row['timeline_image'] : array(), 'link' => is_array( $link ) ? $link : array( 'url' => (string) $link ) );
		}
		$out['source']      = $custom ? 'items' : 'posts';
		$out['items']       = $items;
		$out['posts_count'] = (int) ( $out['posts_per_page'] ?? 4 );
		$out['align']       = in_array( $out['timeline_align'] ?? 'center', array( 'left', 'right' ), true ) ? $out['timeline_align'] : 'center';
		$out['title_tag']   = (string) ( $out['title_tags'] ?? 'h4' );
		$out['show_image']  = 'yes' === ( $out['show_image'] ?? '' ) ? 'yes' : '';
		$out['read_more']   = 'yes' === ( $out['show_readmore'] ?? '' ) ? (string) ( $out['readmore_text'] ?? '' ) : '';
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(timeline_.*|visible_items|date_always_visible_at_top|readmore_text|button_size|button_icon|icon_align|show_title|title_tags|title_link|show_meta|show_excerpt|excerpt_length|ellipsis|strip_shortcode|show_readmore|item_animation|icon_show|readmore_hover_animation|posts_.*)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
