<?php
/**
 * P4 · Marcadores de K (f8.sh, F8 frente J). Sitio sólo con DIGITALÍSIMO Elements (Migración instalada pero inactiva, sin
 * el origen ni Prime Slider): el conductor importa el kit sintético de f8/p4.prebuild.mjs, cuyos widgets del origen el
 * sitio no puede instanciar (quedan como marcadores `digitalisimo-unconverted`); después activa Migración, abre un
 * documento en el editor real (la conversión pura del filtro de K) y pulsa «Migrar» (la etapa `markers`).
 */

defined( 'ABSPATH' ) || exit;

if ( 'P4' === ( digi_f8_mode()['scenario'] ?? '' ) ) {
	function digi_f8_activate( array $mode ) {
		digi_f8_activate_plugin( 'digitalisimo-elements/pro-elements.php' );
		switch_theme( 'digitalisimo-theme' );
	}
	function digi_f8_content( array $mode ) {
		$kit = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit && class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->kits_manager ) ) {
			update_option( 'elementor_active_kit', (int) \Elementor\Plugin::$instance->kits_manager->create_default() );
		}
	}
	function digi_f8_ops() {
		return array(
			'markers'   => static function ( $arg ) {
				$out = array();
				foreach ( get_posts( array( 'post_type' => array( 'page', 'revision' ), 'post_status' => 'any', 'numberposts' => -1, 'suppress_filters' => true ) ) as $p ) {
					$raw = (string) get_metadata( 'post', $p->ID, '_elementor_data', true );
					if ( '' === $raw ) {
						continue;
					}
					preg_match_all( '/"widgetType":"([a-z0-9_-]+)"/', $raw, $m );
					$out[] = array( 'id' => $p->ID, 'type' => $p->post_type, 'parent' => $p->post_parent, 'title' => $p->post_title, 'markers' => substr_count( $raw, '"widgetType":"digitalisimo-unconverted"' ), 'widgets' => array_count_values( $m[1] ), 'metas' => array_values( array_filter( array_keys( (array) get_post_meta( $p->ID ) ), static function ( $k ) { return 0 === strpos( $k, '_digitalisimo' ); } ) ) );
				}
				$preserve = '\\Digitalisimo\\Elements\\Import\\Kits\\Preserve';
				return array( 'docs' => $out, 'count' => class_exists( $preserve ) ? $preserve::count() : null );
			},
			'migration' => static function ( $arg ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$r = activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php' );
				return array( 'ok' => ! is_wp_error( $r ), 'error' => is_wp_error( $r ) ? $r->get_error_message() : '' );
			},
		);
	}
}
