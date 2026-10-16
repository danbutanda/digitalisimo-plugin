<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-logos.php';

/** Cuadrícula de logotipos: alias oculto de Logotipos en cuadrícula para el contenido guardado. */
class Logo_Grid_Widget extends Logos_Widget {
	use Widget_Alias;

	const ALIAS_ID       = 'digitalisimo-logo-grid';
	const LEGACY_KEYS    = array( 'logo_list' => false, 'thumbnail_size' => false, 'column_gap' => true, 'logo_size_cover' => false, 'grid_bg_color' => false, 'grid_border_color' => false );
	const LEGACY_CLASSES = array( 'prefix' => 'digi-logo-grid', 'elements' => array( 'content' => 'caption', 'name' => false, 'description' => false, 'link' => false, 'link-text' => false, 'details' => false, 'summary' => false ) );

	public static function upgrade_settings( array $s ) {
		if ( ! isset( $s['items'] ) && ! empty( $s['logo_list'] ) ) {
			$rows = array();
			foreach ( Settings_Upgrade::rows( $s['logo_list'], array() ) as $row ) {
				$row['caption'] = 'yes' === ( $row['logo_tooltip'] ?? '' ) ? 'yes' : '';
				unset( $row['logo_tooltip'] );
				$rows[] = $row;
			}
			$s['items'] = $rows;
		}
		unset( $s['logo_list'] );
		foreach ( array( 'thumbnail_size' => 'image_size', 'layout' => 'style', 'column_gap' => 'gap', 'logo_size_cover' => 'cover', 'grid_bg_color' => 'item_background', 'grid_border_color' => 'border_color' ) as $from => $to ) {
			Settings_Upgrade::rename( $s, $from, $to );
		}
		Settings_Upgrade::fill( $s, array( 'layout' => 'grid', 'style' => 'box', 'details' => 'tooltip', 'label' => 'Logotipos', 'columns' => '4', 'columns_tablet' => '2', 'columns_mobile' => '2', 'name_tag' => 'strong', 'show_link_text' => '' ) );
		return $s;
	}
}
