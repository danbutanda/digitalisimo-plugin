<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-css-sync.php';
require_once __DIR__ . '/class-preview-link.php';
require_once __DIR__ . '/class-font-stack.php';
require_once __DIR__ . '/class-settings-merge.php';
require_once __DIR__ . '/class-page-template.php';
require_once __DIR__ . '/class-motion-hint.php';
require_once dirname( __DIR__ ) . '/digitalisimo-marca/class-map.php';
require_once __DIR__ . '/class-brand-server.php';
require_once __DIR__ . '/class-legacy-route.php';

/**
 * El MCP del constructor (núcleo incluido) con el CSS al día: cada escritura de una herramienta `elementor/…` deja
 * regenerables los estilos del documento que cambió (`Css_Sync`), un enlace firmado de `create-preview-link` pinta su
 * instantánea sin escribir en la página (`Preview_Link`), una lista de familias en `font-family` sale como CSS válido
 * (`Font_Stack`), los ajustes de un documento se combinan, nunca se reemplazan (`Settings_Merge`), y la plantilla de
 * página sólo cambia cuando se pide (`Page_Template`) y, si un estilo trae movimiento que no se guarda, la respuesta
 * dice qué usar (`Motion_Hint`). El mismo MCP se ofrece con nombre propio en `/wp-json/digitalisimo/mcp` (`Brand_Server`,
 * herramientas `digitalisimo-*`) y la ruta vieja queda como alias hasta su retiro (`Legacy_Route`, docs/MARCA_PROPIA.md).
 * Nada de `elementor/` se edita. Detalle en `docs/ELEMENTS_MCP_CSS.md`.
 */
final class Module {
	public static function init(): void {
		Css_Sync::init();
		Preview_Link::init();
		Font_Stack::init();
		Settings_Merge::init();
		Page_Template::init();
		Motion_Hint::init();
		Brand_Server::init();
		Legacy_Route::init();
	}
}
