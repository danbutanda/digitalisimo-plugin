<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `default-styles` de K, sólo en «agregar» (CC-10, CV-18): los estilos por defecto v4 del kit reemplazarían
 * los del sitio, así que no se aplican. `Finish` los informa como «Ajustes v4 no fusionados».
 */
class Default_Styles_Skip extends Import_Runner_Base {
	public static function get_name(): string {
		return 'default-styles';
	}

	public function should_import( array $data ) {
		return false;
	}

	public function import( array $data, array $imported_data ) {
		return array();
	}
}
