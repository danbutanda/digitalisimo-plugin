<?php
namespace Digitalisimo\Elements\Import\Kits\Parts;

use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Export\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `kit-settings`: lo que los ajustes del kit necesitan además de `site-settings.json` (§9.4, B9, B10).
 *
 * Los ajustes viajan en el archivo del núcleo y los aplica el runner `site-settings` de K, que en «sólo agregar»
 * fusiona los globales (§9.4: reutiliza, renombra a `k<6 hex>` estable o añade) y publica los mapas
 * `global_color` y `global_typography`; el remapeo reescribe con ellos los `__globals__` del contenido y de los
 * valores por defecto. Esta parte añade:
 * - al exportar, qué colores y tipografías usa de verdad el contenido exportado (`used`), también los del
 *   sistema: en «agregar» sólo esos entran como personalizados (D11);
 * - al importar en «nuevo», los `url(…)` del CSS del kit pasan a los medios importados (§9.3): el runner
 *   `site-settings` aplica los ajustes en la primera petición, cuando aún no hay medios.
 */
final class Kit_Settings implements Part {
	const KEY     = 'kit-settings';
	const VERSION = 1;

	const SETTINGS_META = '_elementor_page_settings';

	/** Tipo de global => mapa que publica el runner `site-settings`. */
	const MAPS = array(
		'colors'     => 'global_color',
		'typography' => 'global_typography',
	);

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	/** `{ used: { colors: [_id…], typography: [_id…] } }` de los documentos exportados y los valores por defecto. */
	public function export( Export_Context $context ): ?array {
		$trees = array();
		foreach ( array( 'template', 'post' ) as $kind ) {
			foreach ( $context->exported( $kind ) as $id ) {
				$trees[]  = Documents::elements_of( (int) $id );
				$settings = function_exists( 'get_post_meta' ) ? get_post_meta( (int) $id, self::SETTINGS_META, true ) : array();
				if ( is_array( $settings ) ) {
					$trees[] = array( array( 'settings' => $settings ) );
				}
			}
		}
		foreach ( Element_Defaults::current_defaults() as $settings ) {
			if ( is_array( $settings ) ) {
				$trees[] = array( array( 'settings' => $settings ) );
			}
		}
		// Árboles de otras partes (F7a: los presets de estilo), con la forma de `used_globals()`: sólo suman.
		foreach ( (array) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_elements_kit_used_globals_trees', array(), $context ) : array() ) as $tree ) {
			if ( is_array( $tree ) ) {
				$trees[] = $tree;
			}
		}
		$used = self::used_globals( $trees );
		if ( ! $used['colors'] && ! $used['typography'] ) {
			return null;
		}
		return array( 'used' => $used );
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$used    = isset( $data['used'] ) && is_array( $data['used'] ) ? $data['used'] : array();
		$summary = array();
		foreach ( self::MAPS as $group => $map_name ) {
			$ids               = isset( $used[ $group ] ) && is_array( $used[ $group ] ) ? array_values( array_filter( $used[ $group ], 'is_string' ) ) : array();
			$summary[ $group ] = count( $ids );
			$summary[ $group . '_mapped' ] = count( array_intersect( $ids, array_map( 'strval', array_keys( $context->map( $map_name ) ) ) ) );
		}
		$summary['css'] = $context->is_fresh() ? $this->remap_css( $context ) : 0;
		return $summary;
	}

	/** Deshace el remapeo del CSS si nadie cambió los ajustes después (lo demás lo revierte `site-settings`). */
	public function revert( array $stored, Revert_Context $context ): void {
		$kit = isset( $stored['kit'] ) ? (int) $stored['kit'] : 0;
		if ( $kit <= 0 || ! isset( $stored['css_before'], $stored['after'] ) || ! is_string( $stored['css_before'] ) ) {
			return;
		}
		$settings = get_post_meta( $kit, self::SETTINGS_META, true );
		if ( ! is_array( $settings ) || ! $context->unchanged( $settings, (string) $stored['after'] ) ) {
			$context->report( 'kit-settings.edited', array( 'kit' => $kit ) );
			return;
		}
		$settings['custom_css'] = $stored['css_before'];
		update_post_meta( $kit, self::SETTINGS_META, $settings );
	}

	/**
	 * Globales que usa un conjunto de árboles: `globals/colors?id=…` y `globals/typography?id=…` en cualquier
	 * `__globals__` (también en repetidores y en la carga de un marcador).
	 *
	 * @param array[] $trees Árboles de elementos (o listas de `{ settings }`).
	 *
	 * @return array{colors: string[], typography: string[]}
	 */
	public static function used_globals( array $trees ): array {
		$used = array(
			'colors'     => array(),
			'typography' => array(),
		);
		foreach ( $trees as $tree ) {
			if ( is_array( $tree ) ) {
				self::collect_globals( $tree, $used );
			}
		}
		foreach ( $used as $group => $ids ) {
			$ids = array_map( 'strval', array_keys( $ids ) );
			sort( $ids, SORT_STRING );
			$used[ $group ] = $ids;
		}
		return $used;
	}

	/** «Nuevo»: el CSS del kit con los medios importados. Sólo escribe si algo cambia; devuelve 1 o 0. */
	private function remap_css( Import_Context $context ): int {
		$kit = $context->kit_id();
		if ( $kit <= 0 ) {
			return 0;
		}
		$settings = get_post_meta( $kit, self::SETTINGS_META, true );
		if ( ! is_array( $settings ) || ! isset( $settings['custom_css'] ) || ! is_string( $settings['custom_css'] ) || '' === $settings['custom_css'] ) {
			return 0;
		}
		$before = $settings['custom_css'];
		$after  = ( new Remapper( $context ) )->css( $before );
		if ( $after === $before ) {
			return 0;
		}
		$settings['custom_css'] = $after;
		update_post_meta( $kit, self::SETTINGS_META, $settings );
		$context->store(
			array(
				'kit'        => $kit,
				'css_before' => $before,
				'after'      => Part_Context::hash( $settings ),
			)
		);
		return 1;
	}

	private static function collect_globals( array $node, array &$used ): void {
		foreach ( $node as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( '__globals__' === $key ) {
				foreach ( $value as $global ) {
					if ( is_string( $global ) && 1 === preg_match( '/^globals\/(colors|typography)\?id=([A-Za-z0-9_-]{1,64})$/', $global, $match ) ) {
						$used[ $match[1] ][ $match[2] ] = true;
					}
				}
				continue;
			}
			self::collect_globals( $value, $used );
		}
	}
}
