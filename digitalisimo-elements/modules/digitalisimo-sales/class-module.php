<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Kit de ventas de DIGITALÍSIMO Elements (docs/ELEMENTS_SALES_KIT.md): ocho plantillas de página en dos variantes
 * (contenedores y secciones), su guía de venta y tres herramientas del servidor MCP del núcleo incluido
 * (`digitalisimo-sales/list-templates`, `create-page` y `guide`), más el aviso al importar un ZIP de plantillas sin
 * la carga de archivos sin filtrar. Nada carga en el front: las herramientas sólo existen con el MCP del sitio activo
 * y el aviso sólo en la administración.
 */
final class Module {
	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		require_once __DIR__ . '/class-catalog.php';
		require_once __DIR__ . '/class-placeholders.php';
		require_once __DIR__ . '/class-builder.php';
		require_once __DIR__ . '/class-pages.php';
		require_once __DIR__ . '/class-import-notice.php';
		require_once __DIR__ . '/mcp/class-tools.php';
		require_once __DIR__ . '/mcp/class-executor.php';
		require_once __DIR__ . '/mcp/class-module.php';
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			Import_Notice::init();
		}
		Mcp\Module::init();
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
