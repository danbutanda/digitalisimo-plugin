<?php
namespace Digitalisimo\Elements\Import\Kits\Parts;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Remapper;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `element-defaults`: los valores por defecto de los elementos del kit (B1).
 *
 * En REST el núcleo no los exporta ni los importa (su runner sólo se registra con `is_admin()`,
 * `N:modules/kit-elements-defaults/module.php:51-54`), descarta los tipos que no conoce y no remapea `__globals__`.
 * Esta parte usa el mismo formato que el archivo nativo `kit-elements-defaults.json` (tipo => ajustes) y su mismo
 * saneador (`Settings_Sanitizer`), y además:
 * - conserva los tipos desconocidos en la meta del kit `_digitalisimo_unconverted_defaults`, los reexporta y los
 *   informa;
 * - reescribe `__globals__` con los mapas de colores y tipografías (§9.4);
 * - en «nuevo» reemplaza; en «sólo agregar» añade los tipos que el sitio no tiene y conserva los suyos.
 *
 * `import()` acepta el formato de la parte (`{ defaults, unconverted }`) y el nativo, para que el runner
 * `elements-default-values` de K le pase un `kit-elements-defaults.json` de terceros.
 */
final class Element_Defaults implements Part {
	const KEY     = 'element-defaults';
	const VERSION = 1;

	/** Meta nativa del kit y nombre del archivo nativo. */
	const META_KEY    = '_elementor_elements_default_values';
	const NATIVE_FILE = 'kit-elements-defaults';

	const TYPE_PATTERN = '/^[A-Za-z0-9_.-]{1,191}$/';

	const SANITIZER = '\Elementor\Modules\KitElementsDefaults\Utils\Settings_Sanitizer';

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array( Module::KIT_META_UNCONVERTED_DEFAULTS );
	}

	/** `{ defaults: { tipo: ajustes }, unconverted: { tipo: ajustes } }`, o null si el kit no tiene. */
	public function export( Export_Context $context ): ?array {
		$kit = self::kit();
		if ( ! $kit ) {
			return null;
		}
		$defaults    = self::current_defaults( $kit );
		$unconverted = self::meta_array( $kit, Module::KIT_META_UNCONVERTED_DEFAULTS );
		$known       = self::known_types();
		$out         = array(
			'defaults'    => array(),
			'unconverted' => array(),
		);
		foreach ( $unconverted + $defaults as $type => $settings ) {
			if ( ! is_string( $type ) || 1 !== preg_match( self::TYPE_PATTERN, $type ) || ! is_array( $settings ) ) {
				continue;
			}
			if ( null !== $known && in_array( $type, $known, true ) ) {
				$out['defaults'][ $type ] = self::sanitize( $type, $settings, $kit, 'export' );
			} else {
				$out['unconverted'][ $type ] = $settings;
			}
		}
		if ( $out['unconverted'] ) {
			$context->report( 'element-defaults.unconverted', array( 'types' => array_keys( $out['unconverted'] ) ), Part_Context::INFO );
		}
		return $out['defaults'] || $out['unconverted'] ? $out : null;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$kit = self::kit( $context->kit_id() );
		if ( ! $kit ) {
			$context->report( 'element-defaults.no-kit', array(), Part_Context::WARNING );
			return array();
		}
		if ( isset( $data['defaults'] ) || isset( $data['unconverted'] ) ) {
			$incoming = array_merge(
				isset( $data['unconverted'] ) && is_array( $data['unconverted'] ) ? $data['unconverted'] : array(),
				isset( $data['defaults'] ) && is_array( $data['defaults'] ) ? $data['defaults'] : array()
			);
		} else {
			$incoming = $data;
		}
		if ( ! $incoming ) {
			// El kit no trae valores por defecto: los del sitio no cambian, tampoco en «nuevo».
			return array();
		}
		$known    = self::known_types();
		$remapper = new Remapper( $context );
		$new      = array();
		$unknown  = array();
		foreach ( $incoming as $type => $settings ) {
			if ( ! is_string( $type ) || 1 !== preg_match( self::TYPE_PATTERN, $type ) || ! is_array( $settings ) ) {
				continue;
			}
			if ( null !== $known && in_array( $type, $known, true ) ) {
				$clean = self::sanitize( $type, $settings, $kit, 'import' );
				if ( $clean ) {
					$new[ $type ] = $remapper->rewrite_globals( $clean );
				}
			} else {
				$unknown[ $type ] = self::kses( $settings );
			}
		}

		$before_defaults    = self::current_defaults( $kit );
		$before_unconverted = self::meta_array( $kit, Module::KIT_META_UNCONVERTED_DEFAULTS );
		if ( $context->is_fresh() ) {
			$defaults    = $new;
			$unconverted = $unknown;
			$added       = array_keys( $new + $unknown );
			$kept        = array();
		} else {
			list( $defaults, $added_known, $kept_known )           = self::merge( $before_defaults, $new );
			list( $unconverted, $added_unknown, $kept_unknown )    = self::merge( $before_unconverted, $unknown );
			$added                                                  = array_merge( $added_known, $added_unknown );
			$kept                                                   = array_merge( $kept_known, $kept_unknown );
		}

		if ( $defaults !== $before_defaults ) {
			self::write_defaults( $kit, $defaults );
		}
		if ( $unconverted !== $before_unconverted ) {
			self::write_unconverted( $kit, $unconverted );
		}
		$context->store(
			array(
				'kit'                => self::kit_id( $kit ),
				'before_defaults'    => $before_defaults,
				'before_unconverted' => $before_unconverted,
				'after'              => Part_Context::hash( array( $defaults, $unconverted ) ),
				'added'              => $added,
				'values'             => array_map(
					function ( $type ) use ( $defaults, $unconverted ) {
						return Part_Context::hash( isset( $defaults[ $type ] ) ? $defaults[ $type ] : ( isset( $unconverted[ $type ] ) ? $unconverted[ $type ] : null ) );
					},
					array_combine( $added, $added ) ?: array()
				),
			)
		);
		if ( $unknown ) {
			$context->report( 'element-defaults.unconverted', array( 'types' => array_keys( $unknown ) ), Part_Context::INFO );
		}
		foreach ( $kept as $type ) {
			$context->report( 'element-defaults.kept', array( 'type' => $type ) );
		}
		return array(
			'types'       => count( $new ),
			'unconverted' => count( $unknown ),
			'added'       => count( $added ),
			'kept'        => count( $kept ),
		);
	}

	/** Restaura lo de antes si nadie tocó los valores; si no, quita sólo los tipos añadidos que siguen igual. */
	public function revert( array $stored, Revert_Context $context ): void {
		$kit = self::kit( isset( $stored['kit'] ) ? (int) $stored['kit'] : $context->kit_id() );
		if ( ! $kit ) {
			return;
		}
		$defaults    = self::current_defaults( $kit );
		$unconverted = self::meta_array( $kit, Module::KIT_META_UNCONVERTED_DEFAULTS );
		$before_d    = isset( $stored['before_defaults'] ) && is_array( $stored['before_defaults'] ) ? $stored['before_defaults'] : array();
		$before_u    = isset( $stored['before_unconverted'] ) && is_array( $stored['before_unconverted'] ) ? $stored['before_unconverted'] : array();
		if ( isset( $stored['after'] ) && $context->unchanged( array( $defaults, $unconverted ), (string) $stored['after'] ) ) {
			self::write_defaults( $kit, $before_d );
			self::write_unconverted( $kit, $before_u );
			return;
		}
		$values = isset( $stored['values'] ) && is_array( $stored['values'] ) ? $stored['values'] : array();
		foreach ( isset( $stored['added'] ) && is_array( $stored['added'] ) ? $stored['added'] : array() as $type ) {
			$hash = isset( $values[ $type ] ) ? (string) $values[ $type ] : '';
			if ( isset( $defaults[ $type ] ) && ! isset( $before_d[ $type ] ) && $context->unchanged( $defaults[ $type ], $hash ) ) {
				unset( $defaults[ $type ] );
			} elseif ( isset( $unconverted[ $type ] ) && ! isset( $before_u[ $type ] ) && $context->unchanged( $unconverted[ $type ], $hash ) ) {
				unset( $unconverted[ $type ] );
			} else {
				$context->report( 'element-defaults.edited', array( 'type' => $type ) );
			}
		}
		self::write_defaults( $kit, $defaults );
		self::write_unconverted( $kit, $unconverted );
	}

	// ── Utilidades ──────────────────────────────────────────────────────────────────────────────────────────────

	/** Valores por defecto del kit activo (o del dado), tipo => ajustes. */
	public static function current_defaults( $kit = null ): array {
		$kit = null === $kit ? self::kit() : $kit;
		if ( ! $kit ) {
			return array();
		}
		$value = method_exists( $kit, 'get_json_meta' ) ? $kit->get_json_meta( self::META_KEY ) : array();
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Fusión por tipo de «sólo agregar»: se añaden los tipos que el sitio no tiene; los que tiene se conservan.
	 *
	 * @return array{0: array, 1: string[], 2: string[]} `[ resultado, añadidos, conservados con otro valor ]`.
	 */
	public static function merge( array $current, array $incoming ): array {
		$added = array();
		$kept  = array();
		foreach ( $incoming as $type => $settings ) {
			if ( ! array_key_exists( $type, $current ) ) {
				$current[ $type ] = $settings;
				$added[]          = (string) $type;
			} elseif ( Part_Context::hash( $current[ $type ] ) !== Part_Context::hash( $settings ) ) {
				$kept[] = (string) $type;
			}
		}
		return array( $current, $added, $kept );
	}

	/** Tipos de elemento y widget registrados (más `inner-section`), o null si el núcleo no está cargado. */
	private static function known_types(): ?array {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance->elements_manager ) || empty( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return null;
		}
		$plugin = \Elementor\Plugin::$instance;
		return array_merge(
			array_keys( (array) $plugin->elements_manager->get_element_types() ),
			array_keys( (array) $plugin->widgets_manager->get_widget_types() ),
			array( 'inner-section' )
		);
	}

	/** El saneador nativo: ajustes válidos, kses y `on_import`/`on_export` de cada control. */
	private static function sanitize( string $type, array $settings, $kit, string $direction ): array {
		$class = self::SANITIZER;
		if ( ! class_exists( $class ) ) {
			return self::kses( $settings );
		}
		$plugin    = \Elementor\Plugin::$instance;
		$sanitizer = new $class( $plugin->elements_manager, array_keys( (array) $plugin->widgets_manager->get_widget_types() ) );
		$sanitizer = $sanitizer->for( $type )->using( $settings )->remove_invalid_settings()->kses_deep();
		$sanitizer = 'export' === $direction ? $sanitizer->prepare_for_export( $kit ) : $sanitizer->prepare_for_import( $kit );
		$clean     = $sanitizer->get();
		return is_array( $clean ) ? $clean : array();
	}

	/** kses de las cadenas, como el saneador nativo, para los ajustes de un tipo desconocido. */
	private static function kses( array $settings ): array {
		if ( class_exists( '\Elementor\Utils' ) && method_exists( '\Elementor\Utils', 'kses_post_deep' ) ) {
			$clean = \Elementor\Utils::kses_post_deep( $settings );
			return is_array( $clean ) ? $clean : array();
		}
		if ( function_exists( 'wp_kses_post' ) ) {
			array_walk_recursive(
				$settings,
				function ( &$value ) {
					if ( is_string( $value ) ) {
						$value = wp_kses_post( $value );
					}
				}
			);
		}
		return $settings;
	}

	/** Kit activo, o el de ese ID. */
	private static function kit( int $id = 0 ) {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) ) {
			return null;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( $id > 0 && ! empty( $plugin->documents ) ) {
			$kit = $plugin->documents->get( $id );
			if ( is_object( $kit ) ) {
				return $kit;
			}
		}
		if ( ! empty( $plugin->kits_manager ) ) {
			$kit = $plugin->kits_manager->get_active_kit();
			return is_object( $kit ) ? $kit : null;
		}
		return null;
	}

	private static function kit_id( $kit ): int {
		return is_object( $kit ) && method_exists( $kit, 'get_main_id' ) ? (int) $kit->get_main_id() : 0;
	}

	private static function meta_array( $kit, string $key ): array {
		$value = function_exists( 'get_post_meta' ) ? get_post_meta( self::kit_id( $kit ), $key, true ) : array();
		return is_array( $value ) ? $value : array();
	}

	private static function write_defaults( $kit, array $defaults ): void {
		// Sin valores por defecto la meta no existe (como antes de importar): `[]` dejaría una meta nueva en el kit.
		if ( ! $defaults ) {
			$id = self::kit_id( $kit );
			if ( $id > 0 && function_exists( 'delete_post_meta' ) ) {
				delete_post_meta( $id, self::META_KEY );
			}
			return;
		}
		if ( method_exists( $kit, 'update_json_meta' ) ) {
			$kit->update_json_meta( self::META_KEY, $defaults );
		}
	}

	private static function write_unconverted( $kit, array $unconverted ): void {
		$id = self::kit_id( $kit );
		if ( $id <= 0 ) {
			return;
		}
		if ( $unconverted ) {
			update_post_meta( $id, Module::KIT_META_UNCONVERTED_DEFAULTS, $unconverted );
		} else {
			delete_post_meta( $id, Module::KIT_META_UNCONVERTED_DEFAULTS );
		}
	}
}
