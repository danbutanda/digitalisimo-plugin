<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Parts;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `plugins` de K (§7.5, D5, CC-15): nunca instala ni activa nada; sólo informa lo que el kit pedía.
 *
 * Ocupa la posición 2 de los runners por defecto, antes de taxonomías y contenido: ahí corren las partes
 * `Early_Part` (`import_early()`), por ejemplo F4-Woo registrando atributos `pa_*`.
 */
class Plugins_Report extends Import_Runner_Base {
	public static function get_name(): string {
		return 'plugins';
	}

	public function should_import( array $data ) {
		return ! empty( $data['manifest']['plugins'] ) || array() !== Parts::early();
	}

	public function import( array $data, array $imported_data ) {
		$requested = isset( $data['manifest']['plugins'] ) && is_array( $data['manifest']['plugins'] ) ? count( $data['manifest']['plugins'] ) : 0;
		if ( $requested > 0 ) {
			Context::report( 'plugins.not_installed', array( 'count' => $requested ), Part_Context::INFO );
		}
		$early = Parts::early();
		if ( $early ) {
			$session = (string) ( $data['session_id'] ?? '' );
			$context = Finish::context( $session, $imported_data, $data );
			foreach ( $early as $key => $part ) {
				$input = Finish::part_input( $data, (string) $key, $part );
				if ( null === $input ) {
					continue;
				}
				$context->begin_part( (string) $key );
				try {
					$part->import_early( $input['data'], $input['version'], $context );
				} catch ( \Throwable $error ) {
					$context->report( 'part.error', array( 'message' => $error->getMessage() ), Part_Context::ERROR );
				}
				$context->end_part();
			}
			Finish::persist( $session, $context );
		}
		return array( 'plugins' => array() );
	}

	public function get_import_session_metadata(): array {
		return array( Module::RUNNER => array( 'installed' => 0 ) );
	}
}
