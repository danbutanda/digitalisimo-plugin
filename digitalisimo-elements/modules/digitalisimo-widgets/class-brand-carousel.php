<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-logos.php';

/** Carrusel de marcas: alias oculto de Logotipos en carrusel para el contenido guardado. */
class Brand_Carousel_Widget extends Logos_Widget {
	use Widget_Alias;

	const ALIAS_ID       = 'digitalisimo-brand-carousel';
	const LEGACY_KEYS    = array( 'brand_items' => false, 'item_gap' => true, 'item_match_height' => false, 'show_brand_name' => false, 'brand_html_tag' => false, 'show_website_link' => false );
	const LEGACY_CLASSES = array( 'prefix' => 'digi-brand-carousel', 'elements' => array( 'item' => 'slide', 'link-text' => 'website', 'description' => false, 'list' => false, 'figure' => false, 'content' => false, 'link' => false, 'details' => false, 'summary' => false ) );

	public static function upgrade_settings( array $s ) {
		if ( ! isset( $s['items'] ) && ! empty( $s['brand_items'] ) ) {
			$s['items'] = Settings_Upgrade::rows( $s['brand_items'], array( 'brand_name' => 'name', 'website_link_text' => 'link_text' ), array( 'caption' => 'yes' ) );
		}
		unset( $s['brand_items'] );
		foreach ( array( 'item_gap' => 'gap', 'item_match_height' => 'match_height', 'show_brand_name' => 'show_name', 'brand_html_tag' => 'name_tag', 'show_website_link' => 'show_link_text' ) as $from => $to ) {
			Settings_Upgrade::rename( $s, $from, $to );
		}
		Settings_Upgrade::fill( $s, array( 'layout' => 'carousel', 'label' => 'Carrusel de marcas', 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'show_link_text' => 'yes', 'show_description' => '' ) );
		return $s;
	}
}
