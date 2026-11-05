<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportUtils;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `floating-elements` de K, sólo en «agregar» (§7.5).
 *
 * El nativo borra las condiciones de **todos** los botones flotantes del sitio y reescribe la caché de condiciones.
 * K sólo da `include/general` a los importados, que quedan en borrador (D8) y se publican desde el Theme Builder.
 */
class Floating_Elements extends Import_Runner_Base {
	const CPT = 'e-floating-buttons';

	public static function get_name(): string {
		return 'floating-elements';
	}

	public function should_import( array $data ): bool {
		return isset( $data['include'] ) && is_array( $data['include'] ) && in_array( 'content', $data['include'], true )
			&& ! empty( $data['manifest']['content'][ self::CPT ] ) && ! empty( $data['extracted_directory_path'] );
	}

	public function import( array $data, array $imported_data ) {
		$imported = isset( $imported_data['content'][ self::CPT ]['succeed'] ) && is_array( $imported_data['content'][ self::CPT ]['succeed'] ) ? $imported_data['content'][ self::CPT ]['succeed'] : array();
		$record   = Context::record();
		$max      = null !== $record && isset( $record['max_post_id'] ) ? (int) $record['max_post_id'] : PHP_INT_MAX;
		$path     = (string) $data['extracted_directory_path'] . 'content/' . self::CPT . '/';
		$meta_key = defined( '\Elementor\Modules\FloatingButtons\Module::FLOATING_ELEMENTS_TYPE_META_KEY' ) ? \Elementor\Modules\FloatingButtons\Module::FLOATING_ELEMENTS_TYPE_META_KEY : '';
		foreach ( $data['manifest']['content'][ self::CPT ] as $id => $settings ) {
			$post_id = isset( $imported[ $id ] ) ? (int) $imported[ $id ] : 0;
			if ( $post_id <= $max ) {
				continue;
			}
			$post_data = ImportExportUtils::read_json_file( $path . $id );
			$widget    = (string) ( $post_data['content'][0]['elements'][0]['widgetType'] ?? '' );
			if ( '' !== $meta_key && 0 === strpos( $widget, 'floating-bars' ) ) {
				update_post_meta( $post_id, $meta_key, 'floating-bars' );
			}
			update_post_meta( $post_id, '_elementor_conditions', array( 'include/general' ) );
			Context::report( 'floating.draft', array( 'post' => $post_id ), Part_Context::INFO );
		}
		return array();
	}
}
