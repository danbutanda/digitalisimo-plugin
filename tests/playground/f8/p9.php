<?php
/**
 * P9 · Reversión (f8.sh, F8 frente J): el mismo sitio de P2 (f8/p2.php), migrado por el conductor; después se edita
 * una copia, se comprueba que `--revert` se niega sin el origen activo y, con el origen reactivado, que deshace la
 * plataforma y los documentos salvo lo editado (informado).
 */

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/p2.php';

if ( 'P9' === ( digi_f8_mode()['scenario'] ?? '' ) ) {
	function digi_f8_activate( array $mode ) {
		digi_f8_p2_activate( $mode );
	}
	function digi_f8_content( array $mode ) {
		if ( ! empty( $mode['woo'] ) && class_exists( 'WC_Install' ) && ! get_option( 'woocommerce_shop_page_id' ) ) {
			WC_Install::install();
			WC_Install::create_pages();
		}
		digi_f8_p2_content( $mode );
	}
	function digi_f8_ops() {
		return digi_f8_p2_ops() + array(
			// Huella de lo que la reversión debe dejar como estaba.
			'snapshot' => static function ( $arg ) {
				$docs = array();
				foreach ( get_posts( array( 'post_type' => array( 'page', 'bdt-template-builder', 'elementor_library' ), 'post_status' => 'any', 'numberposts' => -1, 'suppress_filters' => true ) ) as $p ) {
					$raw = (string) get_post_meta( $p->ID, '_elementor_data', true );
					$docs[ $p->post_type . ':' . $p->post_name ] = array( 'id' => $p->ID, 'status' => $p->post_status, 'md5' => md5( $raw ) ) + ( 'page' === $p->post_type ? array( 'raw' => substr( $raw, 0, 3000 ), 'metas' => array_values( array_filter( array_keys( (array) get_post_meta( $p->ID ) ), static function ( $k ) { return 0 === strpos( $k, '_digitalisimo' ); } ) ), 'mv' => array_map( static function ( $v ) { return substr( (string) $v[0], 0, 400 ); }, array_filter( (array) get_post_meta( $p->ID ), static function ( $k ) { return 0 === strpos( $k, '_digitalisimo' ); }, ARRAY_FILTER_USE_KEY ) ) ) : array() );
				}
				$kit = (int) get_option( 'elementor_active_kit' );
				$ps  = (array) get_post_meta( $kit, '_elementor_page_settings', true );
				ksort( $ps );
				return array(
					'docs'     => $docs,
					'kit'      => md5( wp_json_encode( $ps ) ),
					'sidebars' => md5( wp_json_encode( get_option( 'sidebars_widgets' ) ) ),
					'css'      => (string) get_option( 'ep_custom_css' ),
				);
			},
			// Una edición posterior a la migración en una copia del Theme Builder (la reversión debe omitirla).
			'edit'     => static function ( $arg ) {
				$id   = (int) $arg( 'id' );
				$data = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
				if ( ! $id || ! is_array( $data ) ) {
					return array( 'ok' => false );
				}
				$data[] = array( 'id' => 'f8edit1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Editado después' ), 'elements' => array() );
				update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
				return array( 'ok' => true, 'md5' => md5( (string) get_post_meta( $id, '_elementor_data', true ) ) );
			},
			'plugin'   => static function ( $arg ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$file = 'bdthemes-element-pack/bdthemes-element-pack.php';
				$r    = 'on' === $arg( 'state' ) ? activate_plugin( $file ) : null;
				return array( 'ok' => ! is_wp_error( $r ), 'active' => (array) get_option( 'active_plugins' ) );
			},
		);
	}
}
