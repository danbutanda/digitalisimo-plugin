<?php

namespace ElementorPro\Core\App\Modules\ImportExportCustomization;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Utils {
	/**
	 * DIGITALÍSIMO (§7.8 #10, D3): la personalización fina de la importación y la exportación de kits (elegir
	 * páginas, plantillas, taxonomías y ajustes) no depende de un plan del proveedor: siempre está disponible.
	 */
	public static function is_high_tier(): bool {
		return true;
	}
}
