<?php
/** Element Pack Pro 9.9.1 `bdt-acf-gallery` → `image-gallery`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'image-gallery',
	// Clases de Element Pack más usadas en sus selectores: .bdt-ep-advanced-image-gallery-item-link, .bdt-navigation-prev, .bdt-navigation-next, .bdt-ep-advanced-image-gallery-item-caption, .bdt-ep-advanced-image-gallery, .bdt-image-mask, .bdt-overlay, .bdt-ep-advanced-image-gallery-item, .bdt-ep-advanced-image-gallery-skin-hidden, .bdt-hidden-gallery-button, .bdt-grid, .bdt-ep-advanced-image-gallery-thumbnail, .bdt-slider-dotnav, .bdt-ep-advanced-image-gallery-inner
	'classes'   => array(),
	'defaults'  => array(
		'image_mask_shape' => 'default',
		'image_mask_shape_default' => 'shape-1',
		'image_mask_shape_position' => 'center-center',
		'image_mask_shape_size' => 'contain',
		'image_mask_shape_custom_size' => array(
			'size' => 100,
			'unit' => '%',
		),
		'image_mask_shape_repeat' => 'no-repeat',
		'grid_type' => 'normal',
		'item_ratio' => array(
			'size' => 265,
		),
		'gallery_item_height' => array(
			'size' => 260,
		),
		'columns' => '4',
		'columns_tablet' => '3',
		'columns_mobile' => '1',
		'carousel_columns' => '4',
		'carousel_columns_tablet' => '3',
		'carousel_columns_mobile' => '1',
		'item_gap' => array(
			'size' => 0,
		),
		'row_gap' => array(
			'size' => 0,
		),
		'show_lightbox' => 'yes',
		'link_type' => 'icon',
		'ep_gallery_link_text' => 'ZOOM',
		'overlay_content_alignment' => 'center',
		'overlay_content_position' => 'middle',
		'caption_position' => '',
		'lightbox_link_type' => 'simple_text',
		'link_image' => array(
			'url' => $placeholder_url,
		),
		'gallery_link_text' => 'Open Gallery',
		'gallery_link_icon' => array(
			'value' => 'fas fa-plus',
			'library' => 'fa-solid',
		),
		'lightbox_animation' => 'slide',
		'autoplay' => 'yes',
		'autoplay_interval' => 7000,
		'pause_on_hover' => 'yes',
		'loop' => 'yes',
		'navigation' => 'arrows',
		'both_position' => 'center',
		'arrows_position' => 'center',
		'dots_position' => 'bottom-center',
		'nav_arrows_icon' => '0',
		'hide_arrow_on_mobile' => 'yes',
		'overlay_animation' => 'fade',
		'overlay_blur_level' => array(
			'size' => 5,
		),
		'arrows_ncx_position' => array(
			'size' => 0,
		),
		'arrows_ncy_position' => array(
			'size' => 40,
		),
		'arrows_acx_position' => array(
			'size' => -60,
		),
		'dots_nnx_position' => array(
			'size' => 0,
		),
		'dots_nny_position' => array(
			'size' => 30,
		),
		'both_ncx_position' => array(
			'size' => 0,
		),
		'both_ncy_position' => array(
			'size' => 40,
		),
		'both_cx_position' => array(
			'size' => -60,
		),
		'both_cy_position' => array(
			'size' => 30,
		),
	),
	// Las imágenes salen del campo galería de ACF en cada render (`Legacy_Acf`).
	'rename'    => array( 'field' => 'digitalisimo_acf_field', 'columns' => 'gallery_columns' ),
	// El lightbox de Element Pack se abre con el de Elementor desde el enlace al archivo de cada imagen.
	'filter'    => static function ( array $out ) {
		$lightbox             = 'yes' === ( $out['show_lightbox'] ?? 'yes' );
		$out['gallery_link']  = $lightbox ? 'file' : 'none';
		$out['open_lightbox'] = $lightbox ? 'yes' : 'no';
		unset( $out['show_lightbox'], $out['link_type'] );
		return $out;
	},
);
