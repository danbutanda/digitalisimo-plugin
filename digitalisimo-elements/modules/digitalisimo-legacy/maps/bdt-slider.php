<?php
/** Element Pack Pro 9.9.1 `bdt-slider` → `slides`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'slides',
	'classes'   => array(
		'.bdt-slider-image-wrapper' => '.swiper-slide-bg',
		'.bdt-slider-dotnav a'      => '.swiper-pagination-bullet',
		'.bdt-slider'               => '.elementor-slides-wrapper',
		'.bdt-slide-item'           => '.swiper-slide',
		'.bdt-slide-link'           => '.elementor-slide-button',
		'.bdt-slide-title'          => '.elementor-slide-heading',
		'.bdt-slide-text'           => '.elementor-slide-description',
		'.bdt-slide-desc'           => '.swiper-slide-contents',
		'.bdt-navigation-prev'      => '.elementor-swiper-button-prev',
		'.bdt-navigation-next'      => '.elementor-swiper-button-next',
	),
	'defaults'  => array(
		'height' => array(
			'size' => 600,
		),
		'origin' => 'center',
		'align' => 'center',
		'show_title' => 'yes',
		'title_tags' => 'h2',
		'show_button' => 'yes',
		'slider_scroll_to_section_icon' => array(
			'value' => 'fas fa-angle-double-down',
			'library' => 'fa-solid',
		),
		'button_text' => 'Read More',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_fraction_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'progress_position' => 'bottom',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'transition' => 'slide',
		'effect' => 'left',
		'autoplay' => 'yes',
		'autoplay_speed' => 5000,
		'loop' => 'yes',
		'speed' => array(
			'size' => 500,
		),
		'slider_background_color' => '#14ABF4',
		'overlay_type' => 'none',
		'blend_type' => 'multiply',
		'arrows_color' => '#fff',
		'dots_color' => '#fff',
		'fraction_color' => '#fff',
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
			'size' => 35,
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
			'size' => -30,
		),
		'dots_nny_position_tablet' => array(
			'size' => -30,
		),
		'dots_nny_position_mobile' => array(
			'size' => -30,
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
			'size' => 35,
		),
		'both_cy_position' => array(
			'size' => -55,
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
			'size' => 35,
		),
		'arrows_fraction_cy_position' => array(
			'size' => -55,
		),
		'progress_y_position' => array(
			'size' => 15,
		),
	),
	'repeaters' => array(
		'tabs' => array(
			'defaults'     => array(
				'source' => 'custom',
				'tab_title' => 'Slide Title',
				'tab_content' => 'Slide Content',
				'tab_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'tab_title' => 'Slide #1',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				), array(
					'tab_title' => 'Slide #2',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				), array(
					'tab_title' => 'Slide #3',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				), array(
					'tab_title' => 'Slide #4',
					'tab_content' => 'I am item content. Click edit button to change this text.',
				) ),
		),
	),
	'rename'    => array( 'title_tags' => 'slides_title_tag', 'pauseonhover' => 'pause_on_hover', 'loop' => 'infinite' ),
	'filter'    => static function ( array $out ) {
		$slides    = array();
		$templates = false;
		foreach ( is_array( $out['tabs'] ?? null ) ? $out['tabs'] : array() as $row ) {
			$slide = array(
				'_id'              => (string) ( $row['_id'] ?? '' ),
				'background_color' => '',
				'background_image' => is_array( $row['tab_image'] ?? null ) ? $row['tab_image'] : array( 'url' => '' ),
				'heading'          => '',
				'description'      => '',
				'button_text'      => '',
				'link'             => array( 'url' => '' ),
				'link_click'       => 'button',
			);
			if ( 'elementor' === ( $row['source'] ?? 'custom' ) ) {
				// La plantilla se pinta en el render del adaptador; el widget propio no la conoce.
				$slide['digitalisimo_template_id'] = absint( $row['template_id'] ?? 0 );
				$slide['background_image']         = array( 'url' => '' );
				$templates                         = true;
			} else {
				$link                 = is_array( $row['tab_link'] ?? null ) ? $row['tab_link'] : array( 'url' => '' );
				$slide['heading']     = 'yes' === ( $out['show_title'] ?? '' ) ? (string) ( $row['tab_title'] ?? '' ) : '';
				$slide['description'] = (string) ( $row['tab_content'] ?? '' );
				$slide['link']        = $link;
				$slide['button_text'] = 'yes' === ( $out['show_button'] ?? '' ) && ! empty( $link['url'] ) ? esc_html( (string) ( $out['button_text'] ?? '' ) ) : '';
			}
			$slides[] = $slide;
		}
		$out['slides']                 = $slides;
		$out['slides_description_tag'] = 'div';
		$out['content_animation']      = '';
		$navigation                    = array( 'both' => 'both', 'arrows' => 'arrows', 'arrows-fraction' => 'arrows', 'dots' => 'dots' );
		$out['navigation']             = $navigation[ (string) ( $out['navigation'] ?? '' ) ] ?? 'none';
		$out['transition']             = 'fade' === ( $out['transition'] ?? '' ) ? 'fade' : 'slide';
		$out['transition_speed']       = (int) ( $out['speed']['size'] ?? 500 );
		$out['autoplay_speed']         = (int) ( $out['autoplay_speed'] ?? 5000 );
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			if ( isset( $out[ 'height' . $device ] ) ) {
				$out[ 'slides_height' . $device ] = $out[ 'height' . $device ];
			}
		}
		// «origin» une posición vertical y horizontal (top-left … bottom-right; «center» es el centro).
		$origin = explode( '-', (string) ( $out['origin'] ?? 'center' ) );
		$out['slides_vertical_position']   = array( 'top' => 'top', 'bottom' => 'bottom' )[ $origin[0] ] ?? 'middle';
		$out['slides_horizontal_position'] = array( 'left' => 'left', 'right' => 'right' )[ $origin[1] ?? '' ] ?? 'center';
		$out['slides_text_align']          = array( 'left' => 'start', 'right' => 'end', 'center' => 'center' )[ (string) ( $out['align'] ?? '' ) ] ?? 'start';
		if ( $templates ) {
			$out['digitalisimo_legacy_templates'] = 'yes';
		}
		foreach ( array( 'tabs', 'origin', 'align', 'show_title', 'show_button', 'button_text', 'scroll_to_section', 'section_id', 'slider_scroll_to_section_icon', 'slider_icon', 'icon_align', 'dynamic_bullets', 'show_scrollbar', 'both_position', 'arrows_fraction_position', 'arrows_position', 'dots_position', 'progress_position', 'nav_arrows_icon', 'hide_arrow_on_mobile', 'effect', 'keyboard', 'speed', 'observer', 'mousewheel', 'overlay_type', 'advanced_dots_size' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
