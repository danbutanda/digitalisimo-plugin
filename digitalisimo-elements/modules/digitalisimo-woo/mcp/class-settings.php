<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que queda del ajuste «WooCommerce en el MCP» (modos apagado, sólo lectura, escritura sólo en lo elegido y lectura
 * y escritura, con herencia de red), retirado por decisión del dueño del 2026-11-27: el MCP trae acceso total de
 * lectura y escritura, sin interruptores. Ningún código lee ya estas opciones; sólo se nombran para borrarlas al
 * actualizar (`Digitalisimo\Elements\Mcp\Access::migrate()`), en cada sitio y en la red.
 */
final class Settings {
	/** Opción de cada sitio (`mode`, `own` y `write_tools`), sin carga automática. */
	const OPTION = 'digitalisimo_elements_woo_mcp';

	/** Opción de red (`sitemeta`). */
	const NETWORK_OPTION = 'digitalisimo_elements_woo_mcp_defaults';
}
