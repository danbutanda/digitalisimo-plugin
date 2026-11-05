<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-logos.php';

/** Cuadrícula de marcas: alias oculto de Logotipos en cuadrícula para el contenido guardado. */
class Brand_Grid_Widget extends Logos_Widget {
	use Widget_Alias;

	const ALIAS_ID       = 'digitalisimo-brand-grid';
	const LEGACY_KEYS    = array( 'brand_items' => false, 'brand_event' => false, 'column_gap' => true, 'show_brand_name' => false, 'brand_html_tag' => false, 'show_website_link' => false );
	const LEGACY_CLASSES = array( 'prefix' => 'digi-brand-grid', 'elements' => array( 'card' => 'image', 'image' => 'logo', 'figure' => false, 'description' => false, 'link-text' => false ) );
	const DETAILS_MAP    = array( 'always' => 'always', 'hover-item' => 'overlay', 'click' => 'click' );

	public static function upgrade_settings( array $s ) {
		if ( ! isset( $s['items'] ) && ! empty( $s['brand_items'] ) ) {
			$s['items'] = Settings_Upgrade::rows( $s['brand_items'], array( 'brand_name' => 'name', 'website_link_text' => 'link_text' ), array( 'caption' => 'yes' ) );
		}
		unset( $s['brand_items'] );
		if ( ! isset( $s['details'] ) && '' !== (string) ( $s['brand_event'] ?? '' ) ) {
			$s['details'] = self::DETAILS_MAP[ (string) $s['brand_event'] ] ?? 'always';
		}
		unset( $s['brand_event'] );
		foreach ( array( 'column_gap' => 'gap', 'show_brand_name' => 'show_name', 'brand_html_tag' => 'name_tag', 'show_website_link' => 'show_link_text' ) as $from => $to ) {
			Settings_Upgrade::rename( $s, $from, $to );
		}
		Settings_Upgrade::fill( $s, array( 'layout' => 'grid', 'style' => 'plain', 'label' => 'Marcas', 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'show_link_text' => 'yes', 'show_description' => '' ) );
		return $s;
	}
}
