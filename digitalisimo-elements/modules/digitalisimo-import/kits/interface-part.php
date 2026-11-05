<?php
namespace Digitalisimo\Elements\Import\Kits;

defined( 'ABSPATH' ) || exit;

/**
 * Una parte de datos propios del kit (§5.5).
 *
 * Cada parte viaja en `digitalisimo/<key>.json` y anota su versión en `manifest.digitalisimo.parts`. Se registra
 * con el filtro `digitalisimo_elements_kit_parts` (ver `Parts`) sin tocar el código de K: así añaden datos F7a,
 * F7b y F4-Woo. Las partes de K son `origin`, `element-defaults`, `kit-settings`, `files`, `media`,
 * `theme-builder` y `term-meta`.
 *
 * Reglas:
 * - nada del kit decide una ruta de disco: los archivos se piden con `Export_Context::add_file()` y se leen con
 *   `Import_Context::staged_path()`;
 * - una referencia sin destino nunca amplía quién ve algo (regla de cierre de §5.5);
 * - `import()` nunca actualiza en el sitio lo que ya existe: reutiliza o crea (D7);
 * - `version()` sube cuando cambia el formato de `export()`.
 */
interface Part {
	/** Clave estable: digitalisimo/<key>.json y manifest.digitalisimo.parts. [a-z0-9-]+ */
	public function key(): string;

	/** Versión del formato. import() acepta cualquier versión ≤ la suya. */
	public function version(): int;

	/** Metas del kit que deben sobrevivir si el núcleo crea un kit nuevo. */
	public function kit_meta_keys(): array;

	/**
	 * Datos para el kit, o null. Puede pedir archivos con $context->add_file().
	 *
	 * Null o un array vacío: la parte no escribe archivo ni entra en `manifest.digitalisimo.parts`. Los datos se
	 * guardan como JSON: sólo arrays y valores escalares.
	 */
	public function export( Export_Context $context ): ?array;

	/**
	 * Petición final, antes de guardar los documentos. Puede publicar mapas con add_map().
	 *
	 * Lo que necesite `revert()` se guarda con `$context->store()`. Los avisos van a `$context->report()`.
	 *
	 * @param array          $data    Contenido de `digitalisimo/<key>.json`, ya validado por el guardián.
	 * @param int            $version Versión con que se exportó (≤ `version()`; si no, la parte no se llama).
	 * @param Import_Context $context Contexto de la petición.
	 *
	 * @return array Resumen de la parte para el informe (contadores y listas cortas de escalares).
	 */
	public function import( array $data, int $version, Import_Context $context ): array;

	/** Deshace import() con lo que guardó en store(). Respeta los hashes («no pisar»). */
	public function revert( array $stored, Revert_Context $context ): void;
}
