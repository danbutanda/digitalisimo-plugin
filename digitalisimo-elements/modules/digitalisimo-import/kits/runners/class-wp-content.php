<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Kits\Wxr_Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `wp-content` de K: el nativo, y lo que la deduplicación del WXR reutilizó (CV-1) se suma a su resumen.
 *
 * Así el mapa de entradas del núcleo (`Utils::map_old_new_post_ids()`) y el de K cuentan lo reutilizado: el remapeo
 * del núcleo y el de K apuntan a lo que ya existía, sin crear nada.
 */
class Wp_Content extends \Elementor\App\Modules\ImportExportCustomization\Runners\Import\Wp_Content {
	public function import( array $data, array $imported_data ) {
		$result = parent::import( $data, $imported_data );
		foreach ( isset( $data['manifest']['wp-content'] ) && is_array( $data['manifest']['wp-content'] ) ? array_keys( $data['manifest']['wp-content'] ) : array() as $type ) {
			$pairs = Wxr_Guard::pairs_for( (string) $type );
			if ( ! $pairs ) {
				continue;
			}
			$current = isset( $result['wp-content'][ $type ] ) && is_array( $result['wp-content'][ $type ] ) ? $result['wp-content'][ $type ] : array();
			$current['succeed'] = ( isset( $current['succeed'] ) && is_array( $current['succeed'] ) ? $current['succeed'] : array() ) + $pairs;
			$current['failed']  = isset( $current['failed'] ) && is_array( $current['failed'] ) ? $current['failed'] : array();
			$result['wp-content'][ $type ] = $current;
		}
		return $result;
	}
}
