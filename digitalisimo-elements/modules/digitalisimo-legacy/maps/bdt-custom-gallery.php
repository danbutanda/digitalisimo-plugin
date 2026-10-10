<?php
/**
 * Element Pack Pro 9.9.1 `bdt-custom-gallery` → `digitalisimo-custom-gallery`.
 * Cada destino de Element Pack (imagen, sitio, video, YouTube, Vimeo o mapa) pasa a ser el enlace
 * de la imagen. Las imágenes de ejemplo de su carpeta no existen sin el plugin: se usa la de Elementor.
 */
defined( 'ABSPATH' ) || exit;

$placeholder = array( 'url' => class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '' );
$text        = 'I am item content. Click edit button to change this text.';
$rows        = array();
for ( $i = 1; $i <= 6; $i++ ) {
	$rows[] = array( 'image_title' => 'Image #' . $i, 'image_text' => $text );
}

return array(
	'target'    => 'digitalisimo-custom-gallery',
	'classes'   => array(
		'.bdt-custom-gallery.bdt-grid > *' => '.digi-custom-gallery__item',
		'.bdt-custom-gallery.bdt-grid'     => '.digi-custom-gallery__list',
		'.bdt-custom-gallery .bdt-gallery-thumbnail' => '.digi-custom-gallery__image',
		'.bdt-custom-gallery .bdt-gallery-item'      => '.digi-custom-gallery__item',
		'.bdt-custom-gallery'              => '.digi-custom-gallery',
	),
	'defaults'  => array( 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'thumbnail_size_size' => 'medium', 'show_title' => 'yes', 'show_text' => 'yes', 'show_lightbox' => 'yes', 'item_gap' => array( 'size' => 10 ) ),
	'rename'    => array( 'thumbnail_size_size' => 'image_size', 'item_gap' => 'gap' ),
	'values'    => array( 'image_size' => array( 'thumbnail' => 'medium', 'medium_large' => 'large', '1536x1536' => 'full', '2048x2048' => 'full', 'custom' => 'large' ) ),
	'repeaters' => array(
		'gallery' => array(
			'target'       => 'gallery_items',
			'defaults'     => array( 'image_title' => 'Slide Title', 'gallery_image' => $placeholder, 'image_text' => 'Slide Content', 'image_link_type' => '', 'image_link_website' => array( 'url' => 'https://elementpack.pro' ) ),
			'default_rows' => $rows,
		),
	),
	'filter'    => static function ( array $out ) {
		$links = array( 'website' => 'image_link_website', 'video' => 'image_link_video', 'youtube' => 'image_link_youtube', 'vimeo' => 'image_link_vimeo', 'google-map' => 'image_link_google_map' );
		$lightbox = 'yes' === ( $out['show_lightbox'] ?? '' );
		$direct   = 'yes' === ( $out['direct_link'] ?? '' );
		foreach ( $out['gallery_items'] as $index => $item ) {
			$type = (string) ( $item['image_link_type'] ?? '' );
			$link = '' === $type ? array( 'url' => (string) ( $item['gallery_image']['url'] ?? '' ) ) : ( $item[ $links[ $type ] ?? '' ] ?? array() );
			if ( $lightbox || $direct ) {
				$item['image_link'] = array( 'url' => (string) ( $link['url'] ?? '' ), 'is_external' => $direct && 'yes' === ( $out['external_link'] ?? '' ) ? 'on' : '', 'nofollow' => '' );
			}
			if ( 'yes' !== ( $out['show_title'] ?? '' ) ) {
				$item['image_title'] = '';
			}
			if ( 'yes' !== ( $out['show_text'] ?? '' ) ) {
				$item['image_text'] = '';
			}
			foreach ( array_merge( array( 'image_link_type' ), array_values( $links ) ) as $key ) {
				unset( $item[ $key ] );
			}
			$out['gallery_items'][ $index ] = $item;
		}
		return $out;
	},
);
