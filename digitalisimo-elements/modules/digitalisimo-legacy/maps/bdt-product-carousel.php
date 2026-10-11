<?php
/** Element Pack Pro 9.9.1 `bdt-product-carousel` → `digitalisimo-fancy-slider`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-slider',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-product-carousel-item, .bdt-navigation-prev, .bdt-navigation-next, .bdt-ep-product-carousel-readmore, .bdt-dots-container, .bdt-ep-, .bdt-ep-product-carousel-image, .bdt-ep-product-carousel-rating, .bdt-ep-product-carousel-badge, .bdt-image-mask, .bdt-ep-product-carousel-title, .bdt-ep-product-carousel-price, .bdt-ep-product-carousel-time, .bdt-ep-product-carousel-text
	'classes'   => array(),
	'defaults'  => array(
		'columns' => 3,
		'columns_tablet' => 2,
		'columns_mobile' => 1,
		'item_gap' => array(
			'size' => 35,
		),
		'item_gap_tablet' => array(
			'size' => 20,
		),
		'item_gap_mobile' => array(
			'size' => 20,
		),
		'item_match_height' => 'yes',
		'show_title' => 'yes',
		'title_tag' => 'h3',
		'show_price' => 'yes',
		'show_time' => 'yes',
		'show_text' => 'yes',
		'readmore_link_to' => 'button',
		'show_rating' => 'yes',
		'rating_type' => 'number',
		'show_image' => 'yes',
		'thumbnail_size_size' => 'medium',
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'readmore_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
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
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'skin' => 'carousel',
		'coverflow_rotate' => array(
			'size' => 50,
		),
		'coverflow_stretch' => array(
			'size' => 0,
		),
		'coverflow_modifier' => array(
			'size' => 1,
		),
		'coverflow_depth' => array(
			'size' => 100,
		),
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'slides_to_scroll' => 1,
		'slides_to_scroll_tablet' => 1,
		'slides_to_scroll_mobile' => 1,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
		'rating_color' => '#e7e7e7',
		'active_rating_color' => '#FFCC00',
		'rating_number_color' => '#FFCC00',
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => -60,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nnx_position_tablet' => array(
			'size' => 0,
		),
		'dots_nnx_position_mobile' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'dots_nny_position_tablet' => array(
			'size' => 30,
		),
		'dots_nny_position_mobile' => array(
			'size' => 30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncx_position_tablet' => array(
			'size' => 0,
		),
		'both_ncx_position_mobile' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_ncy_position_tablet' => array(
			'size' => 40,
		),
		'both_ncy_position_mobile' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => -60,
		),
		'both_cy_position' => array(
			'size' => 30,
		),
		'arrows_fraction_ncx_position' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_tablet' => array(
			'size' => 0,
		),
		'arrows_fraction_ncx_position_mobile' => array(
			'size' => 0,
		),
		'arrows_fraction_ncy_position' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_tablet' => array(
			'size' => 40,
		),
		'arrows_fraction_ncy_position_mobile' => array(
			'size' => 40,
		),
		'arrows_fraction_cx_position' => array(
			'size' => -60,
		),
		'arrows_fraction_cy_position' => array(
			'size' => 30,
		),
		'progress_y_position' => array(
			'size' => 15,
		),
	),
	'repeaters' => array(
		'product_items' => array(
			'defaults'     => array(
				'image' => array(
					'url' => $placeholder_url,
				),
				'title' => 'product title here',
				'price' => '$204',
				'text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
				'readmore_link' => array(
					'url' => '#',
				),
				'rating_number' => array(
					'size' => 4.5,
				),
				'rating_count' => '(10,678)',
				'time' => '1 hour 10 mins',
				'badge_text' => 'Sale',
			),
			'default_rows' => array( array(
					'title' => 'Pizza',
				), array(
					'title' => 'Burger',
				), array(
					'title' => 'Chicken',
				), array(
					'title' => 'Milkshake',
				), array(
					'title' => 'Ice Tea',
				), array(
					'title' => 'Pasta',
				) ),
		),
	),
	// Cada producto es una diapositiva: precio, texto, valoración y tiempo en la descripción; la insignia como subtítulo.
	'filter'    => static function ( array $out ) {
		$slides = array();
		$link   = (string) ( $out['readmore_link_to'] ?? '' );
		foreach ( is_array( $out['product_items'] ?? null ) ? $out['product_items'] : array() as $row ) {
			$url         = is_array( $row['readmore_link'] ?? null ) ? $row['readmore_link'] : array( 'url' => '' );
			$description = '';
			if ( 'yes' === ( $out['show_price'] ?? '' ) && '' !== (string) ( $row['price'] ?? '' ) ) {
				$description .= '<p class="digi-legacy-price"><strong>' . esc_html( (string) $row['price'] ) . '</strong></p>';
			}
			if ( 'yes' === ( $out['show_text'] ?? '' ) ) {
				$description .= (string) ( $row['text'] ?? '' );
			}
			$meta = array();
			if ( 'yes' === ( $out['show_rating'] ?? '' ) ) {
				$meta[] = trim( ( 'number' === ( $out['rating_type'] ?? 'number' ) ? (string) ( $row['rating_number']['size'] ?? '' ) . ' ★ ' : '' ) . (string) ( $row['rating_count'] ?? '' ) );
			}
			if ( 'yes' === ( $out['show_time'] ?? '' ) && '' !== (string) ( $row['time'] ?? '' ) ) {
				$meta[] = (string) $row['time'];
			}
			$meta         = array_filter( $meta, 'strlen' );
			$description .= $meta ? '<p class="digi-legacy-meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>' : '';
			$slides[]     = array(
				'_id'          => (string) ( $row['_id'] ?? '' ),
				'sub_title'    => 'yes' === ( $out['badge'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $row['badge_text'] ?? '' ) ) ) : '',
				'title'        => 'yes' === ( $out['show_title'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ) ) : '',
				'title_link'   => in_array( $link, array( 'title', 'item' ), true ) ? $url : array( 'url' => '' ),
				'description'  => $description,
				'slide_image'  => 'yes' === ( $out['show_image'] ?? '' ) && is_array( $row['image'] ?? null ) ? $row['image'] : array( 'url' => '' ),
				'slide_button' => 'button' === $link ? (string) ( $out['readmore_text'] ?? 'Read More' ) : '',
				'button_link'  => 'button' === $link ? $url : array( 'url' => '' ),
			);
		}
		$out['slides']         = $slides;
		$out['image_position'] = 'top';
		$out['title_tags']     = (string) ( $out['title_tag'] ?? 'h3' );
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(product_items|show_(title|price|time|text|rating|image)|rating_type|badge|readmore_link_to|readmore_text|title_tag)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
