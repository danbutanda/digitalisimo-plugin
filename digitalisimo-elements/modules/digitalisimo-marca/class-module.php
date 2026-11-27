<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-map.php';
require_once __DIR__ . '/class-addons.php';
require_once __DIR__ . '/class-toggle.php';
require_once __DIR__ . '/class-admin-urls.php';
require_once __DIR__ . '/class-rest-index.php';
require_once __DIR__ . '/class-settings-section.php';
require_once __DIR__ . '/class-transform.php';
require_once __DIR__ . '/class-front-layer.php';
require_once __DIR__ . '/class-package.php';

/**
 * Marca propia (docs/MARCA_PROPIA.md): cero rastro de «elementor» en lo que sale, sin editar `elementor/`.
 *
 * Fase 1 (esta): el mapa de marca (`Map`, que también usa el MCP con nombre propio), el interruptor por sitio apagado
 * por defecto con el detector de complementos de terceros (`Toggle`, `Addons`, D-5), los alias de URL del escritorio y
 * del editor (`Admin_Urls`, D-3), el índice REST sin espacios del motor (`Rest_Index`, D-8) y su sección en
 * «Rendimiento» (`Settings_Section`). Las reglas de la capa del front (fase 2) están en `marca/reglas.json`.
 */
final class Module {
	public static function init(): void {
		Admin_Urls::init();
		Rest_Index::init();
		Settings_Section::init();
		Front_Layer::init();
	}
}
