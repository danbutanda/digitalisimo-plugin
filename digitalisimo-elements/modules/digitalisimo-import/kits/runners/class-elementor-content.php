<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Session;
use Digitalisimo\Elements\Import\Kits\Parts\Media;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportUtils;
use Elementor\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `elementor-content` de K (§7.5): el nativo, con procedencia (D7) y sin perder contenido (A4).
 *
 * - `read_and_import_post()` (lo usan también los delegados de PRO) reutiliza lo que ya importó el mismo kit sin
 *   cambios, o crea el documento y anota su procedencia.
 * - El contenido de tipos de entrada que el sitio no tiene (el nativo lo salta) se conserva como plantilla
 *   `section` en borrador con `_digitalisimo_original_type = post_type:<tipo>`, y se informa. No entra en el mapa
 *   de entradas: una referencia a él no apunta a otra cosa (cierre).
 */
class Elementor_Content extends \Elementor\App\Modules\ImportExportCustomization\Runners\Import\Elementor_Content {
	public function import( array $data, array $imported_data ) {
		$result  = parent::import( $data, $imported_data );
		$session = (string) ( $data['session_id'] ?? '' );
		$base    = (string) ( $data['extracted_directory_path'] ?? '' ) . 'content/';
		foreach ( isset( $data['manifest']['content'] ) && is_array( $data['manifest']['content'] ) ? $data['manifest']['content'] : array() as $post_type => $posts ) {
			if ( post_type_exists( (string) $post_type ) || ! is_array( $posts ) ) {
				continue;
			}
			foreach ( $posts as $id => $settings ) {
				$new = $this->preserve_post( $base . $post_type . '/', (string) $id, is_array( $settings ) ? $settings : array(), (string) $post_type, $session );
				Context::report(
					'content.missing_type',
					array(
						'type' => (string) $post_type,
						'id'   => (string) $id,
						'post' => $new,
					),
					Part_Context::WARNING
				);
			}
		}
		return $result;
	}

	/**
	 * Importa una entrada, o devuelve la que ya importó el mismo kit sin cambios (D7).
	 *
	 * @param string     $path           Carpeta del tipo de entrada.
	 * @param int|string $id             ID de origen.
	 * @param array      $post_settings  Entrada del manifiesto.
	 * @param string     $post_type      Tipo de entrada.
	 * @param array      $imported_terms Mapa de términos.
	 */
	public function read_and_import_post( $path, $id, $post_settings, $post_type, $imported_terms ) {
		$post_data = ImportExportUtils::read_json_file( $path . $id );
		$post_data = is_array( $post_data ) ? $post_data : array();
		$found     = apply_filters( Module::FILTER_EXISTING_DOCUMENT, null, (string) $post_type, $id, $post_data + array( 'import_settings' => is_array( $post_settings ) ? $post_settings : array() ) );
		if ( is_int( $found ) && $found > 0 ) {
			Context::report(
				'origin.reuse',
				array(
					'post' => $found,
					'type' => (string) $post_type,
				)
			);
			return array(
				'status' => static::IMPORT_STATUS_SUCCEEDED,
				'result' => $found,
			);
		}
		list( $post_settings, $thumbnail ) = Media::reuse_thumbnail( is_array( $post_settings ) ? $post_settings : array() );
		$result = parent::read_and_import_post( $path, $id, $post_settings, $post_type, $imported_terms );
		if ( isset( $result['status'], $result['result'] ) && static::IMPORT_STATUS_SUCCEEDED === $result['status'] ) {
			Media::set_thumbnail( (int) $result['result'], $thumbnail );
			Context::created( (int) $result['result'], (string) $post_type, $id, Session::document_hash( $post_data ) );
		}
		return $result;
	}

	/** Conserva una entrada de un tipo que el sitio no tiene como plantilla `section` en borrador (CC-4). */
	private function preserve_post( string $path, string $id, array $settings, string $post_type, string $session ): int {
		try {
			$post_data = ImportExportUtils::read_json_file( $path . $id );
			if ( ! is_array( $post_data ) ) {
				return 0;
			}
			$document = Plugin::$instance->documents->create(
				'section',
				array(
					'post_title'  => isset( $settings['title'] ) ? (string) $settings['title'] : $post_type . ' ' . $id,
					'post_type'   => 'elementor_library',
					'post_status' => 'draft',
				)
			);
			if ( is_wp_error( $document ) ) {
				return 0;
			}
			$settings['doc_type']         = 'section';
			$settings['thumbnail']        = '';
			$post_data['import_settings'] = $settings;
			$attachments                  = function ( $attachment_id ) use ( $session ) {
				$this->set_session_post_meta( $attachment_id, $session );
			};
			add_filter( 'elementor/template_library/import_images/new_attachment', $attachments );
			$document->import( $post_data );
			remove_filter( 'elementor/template_library/import_images/new_attachment', $attachments );
			$new = (int) $document->get_main_id();
			$this->set_session_post_meta( $new, $session );
			update_post_meta( $new, Module::META_ORIGINAL_TYPE, 'post_type:' . $post_type );
			Context::created( $new, 'post_type:' . $post_type, $id, Session::document_hash( $post_data ) );
			return $new;
		} catch ( \Exception $error ) {
			return 0;
		}
	}
}
