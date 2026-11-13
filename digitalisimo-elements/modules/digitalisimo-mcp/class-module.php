<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-css-sync.php';
require_once __DIR__ . '/class-font-stack.php';

/**
 * El MCP del constructor (núcleo incluido) con el CSS al día: cada escritura de una herramienta `elementor/…` deja
 * regenerables los estilos del documento que cambió (`Css_Sync`) y una lista de familias en `font-family` sale como
 * CSS válido (`Font_Stack`). Nada de `elementor/` se edita. Detalle en `docs/ELEMENTS_MCP_CSS.md`.
 */
final class Module {
	public static function init(): void {
		Css_Sync::init();
		Font_Stack::init();
	}
}
