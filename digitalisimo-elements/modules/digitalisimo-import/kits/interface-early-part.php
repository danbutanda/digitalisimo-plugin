<?php
namespace Digitalisimo\Elements\Import\Kits;

defined( 'ABSPATH' ) || exit;

/**
 * Parte que además actúa al principio de la importación (CC-15).
 *
 * `import_early()` corre en el runner `plugins` de K (posición 2 de los runners por defecto), antes de taxonomías
 * y contenido: por ejemplo, F4-Woo registra ahí los atributos `pa_*` para que el runner de taxonomías los encuentre.
 * Corre en otra petición que `import()`: lo que guarde con `$context->store()` se conserva en la sesión y se une a
 * lo que guarde `import()`.
 */
interface Early_Part extends Part {
	/**
	 * @param array          $data    Contenido de `digitalisimo/<key>.json`.
	 * @param int            $version Versión con que se exportó (≤ `version()`).
	 * @param Import_Context $context Contexto de la petición.
	 *
	 * @return array Resumen para el informe.
	 */
	public function import_early( array $data, int $version, Import_Context $context ): array;
}
