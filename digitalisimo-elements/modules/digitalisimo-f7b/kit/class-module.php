<?php
namespace Digitalisimo\Elements\F7b\Kit;

use Digitalisimo\Elements\F7b\Module as F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Kits de F7b (frente K; D22 y V.10 de la especificación): lo que las capacidades de F7b necesitan para viajar en un kit,
 * declarado con el registro de K (`docs/fusion/k-kits.md` §10), sin tocar el código de K ni crear otro sistema de kits.
 *
 * - **Parte** `saved-queries` (`Queries_Part`, `digitalisimo_elements_kit_parts`): las consultas guardadas
 *   (`digitalisimo_query`) con su UUID y sus metas; publica el mapa `queries` (viejo → nuevo).
 * - **Referencias** (`digitalisimo_elements_kit_references`, `references_list()`):
 *   - `digitalisimo_saved_query` de cualquier elemento → `map:queries`, con `gate`: sin destino queda vacío (el widget
 *     vuelve a sus propios ajustes, que «Vincular» nunca borra: D24) y el documento queda en borrador con «revisar antes
 *     de publicar». Sin `gate`, K dejaría el ID del origen (`Remapper::map_value()`), que en el destino podría ser otra
 *     consulta;
 *   - `_digitalisimo_actions.*.target` → `element` (un destino que no está en el documento queda vacío y la acción no se
 *     registra al pintar) y `_digitalisimo_actions.*.popup_id` → `post` (sin destino queda vacío y la fila no se pinta);
 *   - `digitalisimo_overrides.*.image` → `media`, `.link.url` → `url` y `.html` → `html` del widget Plantilla (frente E):
 *     sus controles sólo se registran en el editor, así que ni el núcleo ni las derivadas de K los ven fuera de él;
 *   - `selected_element` de los cuatro filtros ampliados (frente D) → `element`.
 *   Lo que el núcleo ya traduce no se declara: el `template_id` del widget Plantilla (también con sobrescrituras, cuyas
 *   claves no son IDs) y el `template_id` de Loop Grid. Ninguna otra pieza de F7b guarda IDs: las reglas de los
 *   formularios condicionales nombran campos del mismo formulario, las etiquetas dentro del texto son texto y el slider
 *   anidado viaja con sus contenedores hijos.
 * - **Informe** (`digitalisimo_elements_kit_report_labels`): los textos de los códigos de la parte, todos con un prefijo
 *   de `Report::SECTION_PREFIXES` (`draft`, `reference`, `requested`, `revert`).
 *
 * `_digitalisimo_uses` no viaja (`F7b\Usages::skip_export()`, que `Meta_Blacklist::skip()` respeta) y se recalcula en el
 * destino al guardar cada documento importado (`elementor/document/after_save`) y con el recorrido por lotes
 * (`F7b\Scan`, en `digitalisimo_elements_kit_imported`). Coste cero fuera de una importación o una exportación: tres
 * `add_filter` que sólo corren cuando K arma sus partes, sus referencias o un informe.
 */
final class Module {
	const FILTER_PARTS      = 'digitalisimo_elements_kit_parts';
	const FILTER_REFERENCES = 'digitalisimo_elements_kit_references';
	const FILTER_LABELS     = 'digitalisimo_elements_kit_report_labels';

	/** Interfaz de las partes de K: sin ella (K ausente), la parte no se registra. */
	const PART_INTERFACE = 'Digitalisimo\\Elements\\Import\\Kits\\Part';

	/** Mapa que publica la parte `saved-queries` y que usa la referencia `digitalisimo_saved_query`. */
	const QUERIES_MAP = 'queries';

	/** Control del vínculo con una consulta guardada (frente C). */
	const QUERY_CONTROL = 'digitalisimo_saved_query';

	/** Widgets de filtro ampliados (frente D, `Filters\Registry::WIDGETS`) con `selected_element`. */
	const FILTER_WIDGETS = array( 'digitalisimo-loop-search', 'digitalisimo-loop-meta-filter', 'digitalisimo-loop-orderby', 'digitalisimo-loop-per-page' );

	/** Textos del informe de los códigos de la parte (K sólo los añade y los corta a 200 caracteres). */
	const LABELS = array(
		'draft.saved-query'                  => 'Consulta guardada en borrador: no llegó igual que en el origen (referencia sin destino, relación o tipo que el sitio no tiene). No muestra nada hasta que la revises y la publiques',
		'reference.saved-query-failed'       => 'Consulta guardada que no se pudo crear: los elementos que la usan vuelven a sus propios ajustes',
		'reference.saved-query-invalid'      => 'Consulta guardada del kit con un formato no válido: se descartó',
		'requested.saved-query'              => 'El sitio ya tenía esa consulta guardada (mismo identificador) con otros ajustes: se conservó la suya',
		'requested.saved-queries-unavailable' => 'El kit trae consultas guardadas y este sitio no puede guardarlas: no se importaron',
		'revert.saved-query-edited'          => 'Consulta guardada editada después de importar: se conservó al revertir',
		'revert.saved-query-in-use'          => 'Consulta guardada que usa contenido del sitio: se conservó al revertir',
		'revert.saved-queries'               => 'Consultas guardadas creadas por la importación: se borraron al revertir',
	);

	/** @var bool */
	private static $initialized = false;

	/** Arranque desde `F7b\Module::BOOT`. Idempotente; sin `add_filter()` no registra nada. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		if ( ! F7b::requires( array( 'add_filter()' ), 'kit' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( self::FILTER_PARTS, array( __CLASS__, 'parts' ) );
		add_filter( self::FILTER_REFERENCES, array( __CLASS__, 'references' ) );
		add_filter( self::FILTER_LABELS, array( __CLASS__, 'labels' ) );
	}

	/**
	 * `digitalisimo_elements_kit_parts`: la parte `saved-queries`, por nombre de clase (K la instancia una vez por
	 * petición). Sólo con K cargado (su interfaz) y con el almacén de consultas del frente C.
	 *
	 * @param mixed $parts Partes de K y de otras etapas.
	 * @return array
	 */
	public static function parts( $parts ): array {
		$parts = is_array( $parts ) ? $parts : array();
		if ( ! interface_exists( self::PART_INTERFACE ) || ! class_exists( 'Digitalisimo\\Elements\\F7b\\Queries\\Store' ) ) {
			return $parts;
		}
		$parts[] = Queries_Part::class;
		return $parts;
	}

	/**
	 * Referencias de F7b (V.10), posicionales: `scope`, `type`, `path`, `kind` y, en la consulta guardada, `gate` (ver la
	 * cabecera). Ninguna necesita `never`: vacío ya es lo más cerrado en cada control (consulta: los ajustes propios del
	 * widget; destino de una acción o de un filtro: no se registra; popup: la fila no se pinta; medios, URL y HTML: el
	 * valor del sitio).
	 *
	 * @return array[]
	 */
	public static function references_list(): array {
		$refs = array(
			// Consultas guardadas (frente C): select con el ID de un `digitalisimo_query`, guardado como cadena.
			array( 'element', '*', self::QUERY_CONTROL, 'map:' . self::QUERIES_MAP, true ),
			// Acciones con destino (frente B): ID de un elemento del mismo documento y popup de PRO.
			array( 'element', '*', '_digitalisimo_actions.*.target', 'element' ),
			array( 'element', '*', '_digitalisimo_actions.*.popup_id', 'post' ),
			// Sobrescrituras del widget Plantilla (frente E): imagen, enlace y HTML de cada fila.
			array( 'element', 'template', 'digitalisimo_overrides.*.image', 'media' ),
			array( 'element', 'template', 'digitalisimo_overrides.*.link.url', 'url' ),
			array( 'element', 'template', 'digitalisimo_overrides.*.html', 'html' ),
		);
		// Filtros ampliados (frente D): el Loop Grid del mismo documento.
		foreach ( self::FILTER_WIDGETS as $widget ) {
			$refs[] = array( 'element', $widget, 'selected_element', 'element' );
		}
		return $refs;
	}

	/**
	 * `digitalisimo_elements_kit_references`.
	 *
	 * @param mixed $refs Referencias de K y de otras etapas.
	 * @return array
	 */
	public static function references( $refs ): array {
		$refs = is_array( $refs ) ? $refs : array();
		foreach ( self::references_list() as $ref ) {
			$refs[] = $ref;
		}
		return $refs;
	}

	/**
	 * `digitalisimo_elements_kit_report_labels`: los textos de `LABELS` (K descarta los que ya tiene).
	 *
	 * @param mixed $labels Textos de K y de otras etapas.
	 * @return array
	 */
	public static function labels( $labels ): array {
		return ( is_array( $labels ) ? $labels : array() ) + self::LABELS;
	}

	/**
	 * Olvida el arranque.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset(): void {
		self::$initialized = false;
	}
}
