<?php
/**
 * Operaciones comunes de la sonda de f7b.sh que no caben en `probe.php` (cada grupo de escenarios añade su archivo).
 * Cada operación recibe `$arg( clave, valor por defecto )` y el mapa de IDs, y devuelve un arreglo (JSON).
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Escribe tal cual el `_elementor_data` de un documento de cases.json (v4, o quitar y devolver interacciones).
	'save_raw'   => static function ( $arg, $map ) {
		$id       = (int) ( $map['docs'][ sanitize_key( $arg( 'doc' ) ) ] ?? 0 );
		$elements = json_decode( $arg( 'elements', '' ), true );
		if ( ! $id || ! is_array( $elements ) ) {
			return array( 'ok' => false );
		}
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		return array( 'ok' => true );
	},
	// Transición de «Rendimiento» en esta petición (puerta de PRO aplicada).
	'transition' => static function ( $arg, $map ) {
		$transition = '\\Digitalisimo\\Elements\\Front\\Transition';
		if ( ! class_exists( $transition ) ) {
			return array( 'ok' => false );
		}
		$transition::run_now();
		return array( 'ok' => true, 'applied' => $transition::applied() );
	},
);
