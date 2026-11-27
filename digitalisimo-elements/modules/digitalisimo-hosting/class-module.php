<?php
namespace Digitalisimo\Elements\Hosting;

defined( 'ABSPATH' ) || exit;

/**
 * DIGITALÍSIMO Hosting en el MCP de DIGITALÍSIMO Elements: las herramientas `digitalisimo-hosting/settings` y
 * `digitalisimo-hosting/domain-product` (`mcp/`). Sólo existen con el MCP del sitio activo y el plugin DIGITALÍSIMO
 * Hosting cargado; nada carga en el front ni en el escritorio.
 */
final class Module {
	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		require_once __DIR__ . '/mcp/class-tools.php';
		require_once __DIR__ . '/mcp/class-executor.php';
		require_once __DIR__ . '/mcp/class-module.php';
		Mcp\Module::init();
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
