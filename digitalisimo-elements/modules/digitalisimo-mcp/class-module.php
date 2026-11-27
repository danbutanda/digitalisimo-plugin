<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-css-sync.php';
require_once __DIR__ . '/class-preview-link.php';
require_once __DIR__ . '/class-font-stack.php';
require_once __DIR__ . '/class-settings-merge.php';
require_once __DIR__ . '/class-page-template.php';
require_once __DIR__ . '/class-motion-hint.php';
require_once __DIR__ . '/class-access.php';
require_once __DIR__ . '/class-write-log.php';

/**
 * El MCP del constructor (núcleo incluido) con el CSS al día: cada escritura de una herramienta `elementor/…` deja
 * regenerables los estilos del documento que cambió (`Css_Sync`), un enlace firmado de `create-preview-link` pinta su
 * instantánea sin escribir en la página (`Preview_Link`), una lista de familias en `font-family` sale como CSS válido
 * (`Font_Stack`), los ajustes de un documento se combinan, nunca se reemplazan (`Settings_Merge`), y la plantilla de
 * página sólo cambia cuando se pide (`Page_Template`) y, si un estilo trae movimiento que no se guarda, la respuesta
 * dice qué usar (`Motion_Hint`). Nada de `elementor/` se edita. Detalle en `docs/ELEMENTS_MCP_CSS.md`.
 *
 * Además, acceso total sin interruptores (decisión del dueño del 2026-11-27): el servidor siempre activo, la migración
 * que borra los ajustes viejos y «Mostrar el prompt para vincular» (`Access`), y la bitácora única de escrituras con
 * su herramienta de lectura (`Write_Log`).
 */
final class Module {
	public static function init(): void {
		Css_Sync::init();
		Preview_Link::init();
		Font_Stack::init();
		Settings_Merge::init();
		Page_Template::init();
		Motion_Hint::init();
		Access::init();
		Write_Log::init();
	}
}
