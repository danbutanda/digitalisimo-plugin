<?php
/** Element Pack Pro 9.9.1 `bdt-static-grid-tab` → `digitalisimo-fancy-tabs`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-tabs',
	'classes'   => array(
		'.bdt-ep-static-grid-tab-main-title' => '.digi-fancy-tabs__label strong',
		'.bdt-ep-static-grid-tab-readmore' => '.digi-fancy-tabs__button',
		'.bdt-ep-static-grid-tab-title' => '.digi-fancy-tabs__label strong',
		'.bdt-ep-static-grid-tab-image' => '.digi-fancy-tabs__icon',
		'.bdt-ep-static-grid-tab-item' => '.digi-fancy-tabs__tab',
		'.bdt-static-grid-tab' => '.digi-fancy-tabs',
	),
	'defaults'  => array(
		'columns' => 4,
		'columns_tablet' => 3,
		'columns_mobile' => 2,
		'layout_type' => 'grid',
		'speed' => 500,
		'show_close' => 'yes',
		'grid_tab_type' => 'image',
		'thumb_image_size_size' => 'medium',
		'tab_text_align' => 'center',
		'show_title' => 'yes',
		'title_tag' => 'h3',
		'show_text' => 'yes',
		'show_readmore' => 'yes',
		'show_image' => 'yes',
		'thumbnail_size_size' => 'medium',
		'readmore_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'item_border_width' => array(
			'size' => 2,
		),
		'tab_border_color' => '#fff',
	),
	'repeaters' => array(
		'static_tabs_item' => array(
			'target'       => 'tabs',
			'rename'       => array( 'title' => 'tab_title', 'text' => 'tab_content', 'readmore_link' => 'button_link' ),
			'defaults'     => array(
				'title' => 'This is a title',
				'text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
				'readmore_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				), array(
					'image' => array(
						'url' => $placeholder_url,
					),
				) ),
		),
	),
	'filter'    => static function ( array $out ) {
		foreach ( $out['tabs'] as $i => $row ) {
			$out['tabs'][ $i ]['icon_type']   = ! empty( $row['selected_icon']['value'] ) ? 'icon' : ( ! empty( $row['image']['url'] ) ? 'image' : 'none' );
			$out['tabs'][ $i ]['tab_title']   = trim( wp_strip_all_tags( (string) ( $row['tab_title'] ?? '' ) ) );
			$out['tabs'][ $i ]['tabs_button'] = 'yes' === ( $out['show_readmore'] ?? '' ) ? (string) ( $out['readmore_text'] ?? '' ) : '';
		}
		unset( $out['show_readmore'], $out['readmore_text'] );
		
		return $out;
	},
);
