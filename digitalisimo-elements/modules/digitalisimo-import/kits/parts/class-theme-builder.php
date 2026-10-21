<?php
namespace Digitalisimo\Elements\Import\Kits\Parts;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `theme-builder`: las condiciones completas de las plantillas del Theme Builder y de los popups (B3, §9.5).
 *
 * El manifiesto del núcleo sólo lleva la primera condición `include` sin `sub_id`, y `Theme_Document::import()`
 * guarda las del origen sin traducir (V-24). El `conditions` nativo no cambia; la lista completa viaja en
 * `digitalisimo/theme-builder.json`: `{ "<id origen>": { conditions: [ { type, name, sub_name, sub_id } ], fields } }`.
 *
 * Al importar, en la petición final, sólo para las plantillas que creó la sesión:
 * - los `sub_id` se traducen con `Remapper::theme_conditions()` (entradas, términos; los autores nunca);
 * - si alguna condición queda sin destino, la plantilla se guarda **sin condiciones** (no se aplica en ninguna
 *   parte), se marca para «revisar antes de publicar» y el informe conserva la lista original;
 * - si todas resuelven, se guardan con `save_conditions()` en el formato que espera (arrays ordenados);
 * - los campos de `digitalisimo_elements_kit_theme_builder_fields` se aplican con su callback.
 * En «sólo agregar» la plantilla es un borrador y no se imprime: las condiciones de las demás no se tocan.
 *
 * Campos: el filtro `digitalisimo_elements_kit_theme_builder_fields( array $fields, int $template_id )` devuelve
 * definiciones `nombre => { export: callable( int $id ): mixed, import: callable( int $id, mixed $valor,
 * Import_Context $c ) }`. Se pide con el ID de origen al exportar y con el nuevo al importar.
 */
final class Theme_Builder implements Part {
	const KEY     = 'theme-builder';
	const VERSION = 1;

	const META_CONDITIONS = '_elementor_conditions';
	const FIELD_PATTERN   = '/^[a-z0-9_-]{1,64}$/';

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	public function export( Export_Context $context ): ?array {
		$out = array();
		foreach ( $context->exported( 'template' ) as $id ) {
			$id         = (int) $id;
			$conditions = array();
			$saved      = function_exists( 'get_post_meta' ) ? get_post_meta( $id, self::META_CONDITIONS, true ) : array();
			foreach ( is_array( $saved ) ? $saved : array() as $condition ) {
				$parts = Remapper::parse_condition( $condition );
				if ( null !== $parts ) {
					$conditions[] = $parts;
				}
			}
			$fields = array();
			foreach ( self::field_definitions( $id ) as $name => $definition ) {
				if ( isset( $definition['export'] ) && is_callable( $definition['export'] ) ) {
					try {
						$value = call_user_func( $definition['export'], $id );
					} catch ( \Throwable $error ) {
						$context->report( 'theme-builder.field', array( 'template' => $id, 'field' => $name ), Part_Context::WARNING );
						continue;
					}
					if ( null !== $value && ( is_scalar( $value ) || is_array( $value ) ) ) {
						$fields[ $name ] = $value;
					}
				}
			}
			if ( $conditions || $fields ) {
				$out[ (string) $id ] = array(
					'conditions' => $conditions,
					'fields'     => $fields,
				);
			}
		}
		return $out ? $out : null;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$summary  = array(
			'applied' => 0,
			'closed'  => 0,
			'skipped' => 0,
		);
		$manager  = self::conditions_manager();
		$remapper = new Remapper( $context );
		$before   = array();
		foreach ( $data as $old => $entry ) {
			if ( ! is_scalar( $old ) || 1 !== preg_match( Module::THEME_BUILDER_KEY_PATTERN, (string) $old ) || ! is_array( $entry ) ) {
				$context->report( 'theme-builder.invalid', array( 'template' => is_scalar( $old ) ? (string) $old : '' ), Part_Context::WARNING );
				continue;
			}
			$new = $context->mapped( 'template', (int) $old, $context->mapped( 'post', (int) $old ) );
			if ( null === $new || ! $context->is_new_post( (int) $new ) ) {
				// No se importó o se reutilizó (D7): lo que ya estaba en el sitio no se toca.
				++$summary['skipped'];
				continue;
			}
			$new        = (int) $new;
			$original   = isset( $entry['conditions'] ) && is_array( $entry['conditions'] ) ? $entry['conditions'] : array();
			$result     = $remapper->theme_conditions( $original );
			$conditions = $result['conditions'];
			$current    = function_exists( 'get_post_meta' ) ? get_post_meta( $new, self::META_CONDITIONS, true ) : array();
			$before[ $new ] = is_array( $current ) ? $current : array();
			if ( $result['unresolved'] ) {
				$conditions = array();
				++$summary['closed'];
				$data_for_report = array(
					'template'   => $new,
					'conditions' => array_map( array( Remapper::class, 'condition_string' ), array_filter( array_map( array( Remapper::class, 'parse_condition' ), $original ) ) ),
					'unresolved' => $result['unresolved'],
				);
				$context->report( 'theme-builder.closed', $data_for_report, Part_Context::WARNING );
				$context->flag_review( $new, Remapper::REVIEW, $data_for_report );
			} else {
				++$summary['applied'];
			}
			if ( null === $manager ) {
				$context->report( 'theme-builder.unavailable', array( 'template' => $new ), Part_Context::WARNING );
			} else {
				$manager->save_conditions( $new, $conditions );
			}
			$this->apply_fields( $new, isset( $entry['fields'] ) && is_array( $entry['fields'] ) ? $entry['fields'] : array(), $context );
		}
		if ( $before ) {
			$after = array();
			foreach ( array_keys( $before ) as $id ) {
				$saved        = function_exists( 'get_post_meta' ) ? get_post_meta( $id, self::META_CONDITIONS, true ) : array();
				$after[ $id ] = Part_Context::hash( is_array( $saved ) ? $saved : array() );
			}
			$context->store(
				array(
					'before' => $before,
					'after'  => $after,
				)
			);
		}
		return $summary;
	}

	/** Restaura las condiciones que tenía cada plantilla si nadie las cambió después. */
	public function revert( array $stored, Revert_Context $context ): void {
		$manager = self::conditions_manager();
		$after   = isset( $stored['after'] ) && is_array( $stored['after'] ) ? $stored['after'] : array();
		foreach ( isset( $stored['before'] ) && is_array( $stored['before'] ) ? $stored['before'] : array() as $id => $conditions ) {
			$id = (int) $id;
			if ( $id <= 0 || null === $manager || ! function_exists( 'get_post_status' ) || in_array( get_post_status( $id ), array( false, 'trash' ), true ) ) {
				continue;
			}
			$saved = get_post_meta( $id, self::META_CONDITIONS, true );
			if ( ! isset( $after[ $id ] ) || ! $context->unchanged( is_array( $saved ) ? $saved : array(), (string) $after[ $id ] ) ) {
				$context->report( 'theme-builder.edited', array( 'template' => $id ) );
				continue;
			}
			$parsed = array();
			foreach ( is_array( $conditions ) ? $conditions : array() as $condition ) {
				$parts = Remapper::parse_condition( $condition );
				if ( null !== $parts ) {
					$parsed[] = $parts;
				}
			}
			$manager->save_conditions( $id, $parsed );
		}
	}

	/**
	 * Definiciones de campos de una plantilla (filtro de §5.6), sólo con nombre válido.
	 *
	 * @return array[] Nombre => `{ export, import }`.
	 */
	public static function field_definitions( int $template_id ): array {
		$fields = function_exists( 'apply_filters' ) ? apply_filters( Module::FILTER_THEME_BUILDER_FIELDS, array(), $template_id ) : array();
		$valid  = array();
		foreach ( is_array( $fields ) ? $fields : array() as $name => $definition ) {
			if ( is_string( $name ) && 1 === preg_match( self::FIELD_PATTERN, $name ) && is_array( $definition ) ) {
				$valid[ $name ] = $definition;
			} else {
				Module::doing_it_wrong( __METHOD__, 'Campo de plantilla inválido: ' . Module::describe( $name ) );
			}
		}
		return $valid;
	}

	private function apply_fields( int $id, array $fields, Import_Context $context ): void {
		if ( ! $fields ) {
			return;
		}
		$definitions = self::field_definitions( $id );
		foreach ( $fields as $name => $value ) {
			if ( ! isset( $definitions[ $name ]['import'] ) || ! is_callable( $definitions[ $name ]['import'] ) ) {
				$context->report( 'theme-builder.field-unknown', array( 'template' => $id, 'field' => (string) $name ) );
				continue;
			}
			try {
				call_user_func( $definitions[ $name ]['import'], $id, $value, $context );
			} catch ( \Throwable $error ) {
				$context->report( 'theme-builder.field', array( 'template' => $id, 'field' => (string) $name ), Part_Context::WARNING );
			}
		}
	}

	/** Gestor de condiciones del Theme Builder, o null si el módulo no está cargado. */
	private static function conditions_manager() {
		$class = '\ElementorPro\Modules\ThemeBuilder\Module';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'instance' ) ) {
			return null;
		}
		$module  = call_user_func( array( $class, 'instance' ) );
		$manager = is_object( $module ) && method_exists( $module, 'get_conditions_manager' ) ? $module->get_conditions_manager() : null;
		return is_object( $manager ) && method_exists( $manager, 'save_conditions' ) ? $manager : null;
	}
}
