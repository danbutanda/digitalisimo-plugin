<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Parts;
use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `elements-default-values` de K (§7.3 paso 5, §7.5, B1).
 *
 * En REST el nativo nunca está (sólo se registra con `is_admin()`), y cuando está descarta los tipos que el sitio no
 * tiene y no remapea `__globals__`. Los valores por defecto los importa la parte `element-defaults` en la petición
 * final, con el mapa de globales (§9.4). Este runner ocupa el nombre para que el nativo no corra; sin la parte,
 * en «nuevo» delega en el nativo y en «agregar» no toca los valores del sitio.
 */
class Element_Defaults extends Import_Runner_Base {
	const NATIVE = '\Elementor\Modules\KitElementsDefaults\ImportExportCustomization\Runners\Import';

	public static function get_name(): string {
		return 'elements-default-values';
	}

	public function should_import( array $data ) {
		if ( null !== Parts::get( 'element-defaults' ) || Module::MODE_FRESH !== Context::mode() || ! class_exists( self::NATIVE ) ) {
			return false;
		}
		$class = self::NATIVE;
		return ( new $class() )->should_import( $data );
	}

	public function import( array $data, array $imported_data ) {
		$class = self::NATIVE;
		return class_exists( $class ) ? ( new $class() )->import( $data, $imported_data ) : array();
	}
}
