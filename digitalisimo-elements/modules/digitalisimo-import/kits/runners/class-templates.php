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
 * Runner `templates` de K (§7.5): el nativo, con procedencia (D7) y sin perder plantillas.
 *
 * - Antes de crear, busca lo que ya importó el mismo kit (`digitalisimo_elements_kit_existing_document`): si ni el
 *   origen ni la copia local cambiaron, reutiliza; si cambió algo, crea un borrador «actualización» o «conflicto».
 * - Un `doc_type` que el sitio no tiene (hoy se omitía sin aviso) entra como plantilla `section` con
 *   `_digitalisimo_original_type`; sus elementos desconocidos los envuelve el marcador al guardar (CC-4).
 * Los estados los decide la regla de D8 del contexto. Los delegados de PRO crean a través de este runner.
 */
class Templates extends \Elementor\App\Modules\ImportExportCustomization\Runners\Import\Templates {
	/**
	 * Plantillas del grupo de sitio (las del nativo) y, además, las de tipos que el sitio no tiene.
	 *
	 * @param array $data           Datos del proceso.
	 * @param array $template_types Tipos que importa el nativo.
	 */
	public function process_templates_import( array $data, array $template_types ) {
		$result = parent::process_templates_import( $data, $template_types );
		$path   = (string) ( $data['extracted_directory_path'] ?? '' ) . 'templates/';
		foreach ( isset( $data['manifest']['templates'] ) && is_array( $data['manifest']['templates'] ) ? $data['manifest']['templates'] : array() as $id => $settings ) {
			$doc_type = is_array( $settings ) && isset( $settings['doc_type'] ) ? (string) $settings['doc_type'] : '';
			if ( '' === $doc_type || Plugin::$instance->documents->get_document_type( $doc_type, false ) ) {
				continue;
			}
			try {
				$template_data        = ImportExportUtils::read_json_file( $path . $id );
				$settings['doc_type'] = 'section';
				$new                  = (int) $this->import_template( $id, $settings, is_array( $template_data ) ? $template_data : array() );
				if ( $new > 0 && ! metadata_exists( 'post', $new, Module::META_ORIGINAL_TYPE ) ) {
					update_post_meta( $new, Module::META_ORIGINAL_TYPE, $doc_type );
				}
				$result['templates']['succeed'][ $id ]               = $new;
				$result['templates']['succeed_summary']['section'] = ( $result['templates']['succeed_summary']['section'] ?? 0 ) + 1;
				Context::report(
					'templates.unknown_type',
					array(
						'id'   => (string) $id,
						'type' => $doc_type,
						'post' => $new,
					),
					Part_Context::WARNING
				);
			} catch ( \Exception $error ) {
				$result['templates']['failed'][ $id ] = $error->getMessage();
			}
		}
		return $result;
	}

	/**
	 * Crea una plantilla, o devuelve la que ya importó el mismo kit sin cambios (D7).
	 *
	 * @param int|string $id                ID de origen.
	 * @param array      $template_settings Entrada del manifiesto.
	 * @param array      $template_data     JSON de la plantilla.
	 */
	public function import_template( $id, array $template_settings, array $template_data ) {
		$doc_type = (string) ( $template_settings['doc_type'] ?? '' );
		$found    = apply_filters( Module::FILTER_EXISTING_DOCUMENT, null, $doc_type, $id, $template_data + array( 'import_settings' => $template_settings ) );
		if ( is_int( $found ) && $found > 0 ) {
			Context::report(
				'origin.reuse',
				array(
					'post' => $found,
					'type' => $doc_type,
				)
			);
			return $found;
		}
		list( $template_settings, $thumbnail ) = Media::reuse_thumbnail( $template_settings );
		$new = (int) parent::import_template( $id, $template_settings, $template_data );
		Media::set_thumbnail( $new, $thumbnail );
		Context::created( $new, $doc_type, $id, Session::document_hash( $template_data ) );
		// El `original_type` de un kit convertido lo anota `Finish` para cualquier runner (también los delegados de PRO).
		return $new;
	}
}
