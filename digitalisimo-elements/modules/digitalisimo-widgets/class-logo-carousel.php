<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-logos.php';

/** Carrusel de logotipos: alias oculto de Logotipos en carrusel para el contenido guardado. */
class Logo_Carousel_Widget extends Logos_Widget {
	use Widget_Alias;

	const ALIAS_ID       = 'digitalisimo-logo-carousel';
	const LEGACY_KEYS    = array( 'logo_items' => false, 'item_gap' => true );
	const LEGACY_CLASSES = array( 'prefix' => 'digi-logo-carousel', 'elements' => array( 'item' => 'slide', 'name' => false, 'description' => false, 'link-text' => false, 'list' => false, 'figure' => false, 'content' => false, 'link' => false, 'details' => false, 'summary' => false ) );

	public static function upgrade_settings( array $s ) {
		if ( ! isset( $s['items'] ) && ! empty( $s['logo_items'] ) ) {
			$s['items'] = Settings_Upgrade::rows( $s['logo_items'], array(), array( 'caption' => 'yes' ) );
		}
		unset( $s['logo_items'] );
		Settings_Upgrade::rename( $s, 'item_gap', 'gap' );
		Settings_Upgrade::fill( $s, array( 'layout' => 'carousel', 'label' => 'Carrusel de logotipos', 'columns' => '4', 'columns_tablet' => '3', 'columns_mobile' => '1', 'show_name' => '', 'show_description' => '', 'show_link_text' => '' ) );
		return $s;
	}
}
