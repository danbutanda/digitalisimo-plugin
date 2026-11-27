<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * Índice REST sin espacios del motor (D-8 de docs/MARCA_PROPIA.md): con la marca propia en vigor, `/wp-json/` no lista
 * los espacios `elementor*` (`elementor/v1`, `elementor-pro/v1`, `elementor-one/v1`, `elementor_preview_link_v1`…) ni
 * sus rutas, para nadie. Las rutas siguen existiendo para el editor y la administración; sólo no se anuncian. La ruta
 * vieja del MCP no se lista nunca (D-7, `Mcp\Legacy_Route`).
 */
final class Rest_Index {
	public static function init(): void {
		add_filter( 'rest_index', array( __CLASS__, 'filter' ), 30 );
	}

	public static function filter( $response ) {
		if ( ! Toggle::active() ) {
			return $response;
		}
		return self::strip( $response );
	}

	/** Quita del índice los espacios y rutas que nombran el motor. */
	public static function strip( $response ) {
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		if ( isset( $data['namespaces'] ) && is_array( $data['namespaces'] ) ) {
			$data['namespaces'] = array_values(
				array_filter(
					$data['namespaces'],
					static function ( $ns ) {
						return false === stripos( (string) $ns, 'elementor' );
					}
				)
			);
		}
		if ( isset( $data['routes'] ) && is_array( $data['routes'] ) ) {
			foreach ( $data['routes'] as $route => $info ) {
				$ns = is_array( $info ) ? (string) ( $info['namespace'] ?? '' ) : '';
				if ( false !== stripos( (string) $route, 'elementor' ) || false !== stripos( $ns, 'elementor' ) ) {
					unset( $data['routes'][ $route ] );
				}
			}
		}
		$response->set_data( $data );
		return $response;
	}
}
