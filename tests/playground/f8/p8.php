<?php
/**
 * P8 · Vuelta atrás (f8.sh, F8 frente J). El sitio de P2 sin la plataforma completa (cabecera y pie del origen y dos
 * widgets heredados) más una página con los rasgos nuevos de Elements (información emergente, distintivo y contador
 * del slider anidado, con los ajustes de los casos de front-budget). El conductor sustituye en caliente la copia montada
 * de Migración (1.0.20 ↔ 1.0.21) o de Elements (4.3.0.169) por los ZIP publicados, como una actualización in situ.
 */

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/p2.php';

if ( 'P8' === ( digi_f8_mode()['scenario'] ?? '' ) ) {
	function digi_f8_activate( array $mode ) {
		foreach ( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php', 'bdthemes-element-pack/bdthemes-element-pack.php' ) as $plugin ) {
			digi_f8_activate_plugin( $plugin );
		}
		switch_theme( 'digitalisimo-theme' );
	}
	function digi_f8_content( array $mode ) {
		digi_f8_p2_content( $mode, false );
		$slider = array(
			'id' => 'f8ns001', 'elType' => 'widget', 'widgetType' => 'digitalisimo-nested-slider',
			'settings' => array( 'effect' => 'fade', 'carousel_label' => 'Medición', 'slides' => array( array( '_id' => 's1', 'slide_title' => 'Uno' ), array( '_id' => 's2', 'slide_title' => 'Dos' ), array( '_id' => 's3', 'slide_title' => 'Tres' ) ), 'navigation' => 'both', 'counter' => 'yes' ),
			'elements' => array(),
		);
		foreach ( array( 'uno', 'dos', 'tres' ) as $i => $n ) {
			$slider['elements'][] = array( 'id' => 'f8nsc0' . $i, 'elType' => 'container', 'isInner' => true, 'settings' => array( 'content_width' => 'full' ), 'elements' => array( array( 'id' => 'f8nsh0' . $i, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Diapositiva ' . $n ), 'elements' => array() ) ) );
		}
		digi_f8_document(
			'digi-f8-rasgos',
			array(
				array( 'id' => 'f8rs001', 'elType' => 'container', 'settings' => array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => 'Contenedor', 'digitalisimo_badge_corner' => 'bottom-end' ), 'elements' => array(
					array( 'id' => 'f8tt001', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Ayuda', 'link' => array( 'url' => '#' ), 'digitalisimo_tooltip' => 'yes', 'digitalisimo_tooltip_text' => 'Texto <strong>breve</strong> de ayuda', 'digitalisimo_tooltip_placement' => 'top' ), 'elements' => array() ),
					array( 'id' => 'f8bd001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Con distintivo', 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => 'Nuevo', 'digitalisimo_badge_corner' => 'top-start' ), 'elements' => array() ),
					$slider,
					array( 'id' => 'f8end01', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Después de los rasgos' ), 'elements' => array() ),
				) ),
			)
		);
	}
	function digi_f8_ops() {
		return digi_f8_p2_ops() + array(
			'plugin' => static function ( $arg ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$file = 'bdthemes-element-pack/bdthemes-element-pack.php';
				$r    = 'on' === $arg( 'state' ) ? activate_plugin( $file ) : deactivate_plugins( $file );
				return array( 'ok' => ! is_wp_error( $r ), 'active' => (array) get_option( 'active_plugins' ) );
			},
		);
	}
}
