<?php
namespace Digitalisimo\Elements\Design\Presets;

use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\Parts\Element_Defaults;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `presets` del kit: los presets de estilo del sitio (F7a, V.2.5 de la especificación).
 *
 * Los presets viven en la opción del sitio `digitalisimo_elements_presets` (no en una meta del kit), así que
 * `kit_meta_keys()` está vacío. La parte los lee con `Store::all()` y los escribe sólo con `Store::replace()`, que
 * vuelve a validar cada preset, conserva `hash` y `updated` si el saneado no cambia los ajustes y recalcula `inert`:
 * - **exportar:** todos, también los `inert` (de un tipo que este sitio no tiene), con su `hash` y su `updated`;
 * - **importar:** «nuevo» reemplaza; «agregar» fusiona por slug (misma huella → reutiliza; otra huella →
 *   `<slug>-k<6 hex>`, estable porque sale del contenido; slug libre → añade) y nunca cambia un preset del sitio;
 * - **mapa:** `presets` (viejo → final) para todos los slugs del kit, también los que no cambian, para que
 *   `map.miss` sólo signifique «falta» (V-I8). Lo usa la referencia `element · * · digitalisimo_preset` de `Editor`;
 * - **valores por defecto:** después de `element-defaults`, reescribe `digitalisimo_preset` en los tipos que añadió
 *   esta importación (`Import_Context::stored( 'element-defaults' )['added']`);
 * - **revertir:** antes que `element-defaults` (orden inverso), restaura esos valores por defecto y la opción si
 *   nadie los cambió; si no, quita sólo lo que añadió y sigue igual.
 *
 * Un preset del kit que no entra (saneado, tipo o tope) nunca deja que sus elementos tomen el preset del sitio con el
 * mismo slug: su vínculo pasa a un slug que no existe, `Guard` lo vacía al guardar y el elemento se ve con su estilo
 * local. Códigos del informe: `settings.presets.*` (sección «Ajustes del kit») y `map.miss`.
 */
final class Kit_Part implements Part {
	const KEY     = 'presets';
	const VERSION = 1;

	/** Mapa que publica la parte y que usa la referencia del vínculo (la declara `Editor`). */
	const MAP = Editor::MAP;

	/** Control del vínculo en cada elemento. */
	const CONTROL = Editor::CONTROL;

	/** Meta nativa de los valores por defecto del kit. */
	const DEFAULTS_META = '_elementor_elements_default_values';

	/** Intentos de `<slug>-k<hex>[-n]` antes de darse por vencido. */
	const MAX_CANDIDATES = 9;

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	/** `{ v: 1, presets: { slug: { type, label, settings, hash, updated, inert } } }`, o null sin presets. */
	public function export( Export_Context $context ): ?array {
		$out   = array();
		$inert = array();
		foreach ( self::current() as $slug => $preset ) {
			$clean = self::shape( (string) $slug, $preset );
			if ( null === $clean ) {
				continue;
			}
			$out[ $slug ] = $clean;
			if ( $clean['inert'] ) {
				$inert[] = (string) $slug;
			}
		}
		if ( $inert ) {
			$context->report( 'settings.presets.inert', array( 'slugs' => $inert ), Part_Context::INFO );
		}
		return $out ? array(
			'v'       => self::VERSION,
			'presets' => $out,
		) : null;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$incoming = isset( $data['presets'] ) && is_array( $data['presets'] ) ? $data['presets'] : array();
		if ( ! $incoming ) {
			// El kit no trae presets: los del sitio no cambian, tampoco en «nuevo».
			return array();
		}
		if ( ! self::store_ready() ) {
			$context->report( 'settings.presets.unavailable', array( 'slugs' => array_slice( array_map( 'strval', array_keys( $incoming ) ), 0, 50 ) ), Part_Context::WARNING );
			return array();
		}
		$before   = self::current();
		$remapper = new Remapper( $context );
		$limit    = (int) Store::limits()['count'];
		$result   = $context->is_fresh() ? array() : $before;
		$final    = array();
		$counts   = array_fill_keys( array( 'added', 'reused', 'renamed', 'inert', 'rejected' ), 0 );
		$added    = array();

		foreach ( $incoming as $slug => $raw ) {
			$slug   = (string) $slug;
			$preset = self::shape( $slug, $raw );
			if ( null === $preset ) {
				++$counts['rejected'];
				$context->report( 'settings.presets.invalid', array( 'slug' => $slug ), Part_Context::WARNING );
				continue;
			}
			$preset['inert'] = ! Store::type_registered( $preset['type'] );
			if ( ! $preset['inert'] ) {
				$clean = $preset['settings'] ? Store::sanitize_settings( $preset['type'], $preset['settings'] ) : array();
				if ( ! $clean ) {
					++$counts['rejected'];
					$context->report( 'settings.presets.rejected', array( 'slug' => $slug, 'type' => $preset['type'] ), Part_Context::WARNING );
					continue;
				}
				$preset['settings'] = $clean;
			}
			$preset['settings'] = $remapper->rewrite_globals( $preset['settings'] );
			$print              = self::fingerprint( $preset );
			foreach ( self::candidates( $slug, $print ) as $candidate ) {
				if ( ! isset( $result[ $candidate ] ) ) {
					if ( count( $result ) < $limit ) {
						$result[ $candidate ] = $preset;
						$added[]              = $candidate;
						$final[ $slug ]       = $candidate;
					}
					break;
				}
				if ( self::fingerprint( $result[ $candidate ] ) === $print ) {
					$final[ $slug ] = $candidate;
					++$counts['reused'];
					break;
				}
			}
			if ( ! isset( $final[ $slug ] ) ) {
				++$counts['rejected'];
				$context->report( 'settings.presets.limit', array( 'slug' => $slug, 'limit' => $limit ), Part_Context::WARNING );
			}
		}

		// Un preset del sitio que hoy no supera el saneado nunca se pierde por importar (D7).
		$fragile = $context->is_fresh() || self::keeps() ? array() : self::fragile( $before );
		if ( $fragile && $result !== $before ) {
			$context->report( 'settings.presets.site-invalid', array( 'slugs' => $fragile ), Part_Context::WARNING );
			$result = $before;
		}
		if ( $result !== $before ) {
			self::write( $result, $context->is_fresh() ? array() : array_keys( $before ) );
			foreach ( Store::dropped() as $drop ) {
				$context->report(
					'settings.presets.dropped',
					array(
						'slug'   => isset( $drop[0] ) ? (string) $drop[0] : '',
						'reason' => isset( $drop[1] ) ? (string) $drop[1] : '',
					),
					Part_Context::WARNING
				);
			}
		}
		$after = self::current();

		// Mapa con identidad (V-I8). Lo que no llegó a la opción apunta a un slug que no existe (fallar cerrado).
		$map = array();
		foreach ( array_keys( $incoming ) as $slug ) {
			$slug = (string) $slug;
			$to   = isset( $final[ $slug ] ) ? $final[ $slug ] : '';
			if ( '' !== $to && isset( $after[ $to ] ) ) {
				$map[ $slug ] = $to;
				if ( in_array( $to, $added, true ) ) {
					++$counts['added'];
					if ( ! empty( $after[ $to ]['inert'] ) ) {
						++$counts['inert'];
						$context->report( 'settings.presets.inert', array( 'slug' => $to, 'type' => (string) $after[ $to ]['type'] ), Part_Context::INFO );
					}
				}
				if ( $to !== $slug ) {
					++$counts['renamed'];
					$context->report(
						'settings.presets.renamed',
						array(
							'from' => $slug,
							'to'   => $to,
						)
					);
				}
			} elseif ( self::valid_slug( $slug ) ) {
				$map[ $slug ] = self::closed( $slug, $after );
			}
		}
		$context->add_map( self::MAP, $map );

		$added = array_values( array_filter( $added, function ( $slug ) use ( $after ) { return isset( $after[ $slug ] ); } ) );
		$store = array(
			'before' => $before,
			'after'  => Part_Context::hash( $after ),
			'added'  => $added,
			'values' => array(),
		);
		foreach ( $added as $slug ) {
			$store['values'][ $slug ] = Part_Context::hash( $after[ $slug ] );
		}
		$store['defaults']  = self::rewrite_defaults( $context );
		$counts['defaults'] = count( $store['defaults']['types'] );
		$context->store( $store );
		return array( 'presets' => count( $incoming ) ) + $counts;
	}

	/** Valores por defecto primero (`element-defaults` revierte después y espera encontrar lo suyo), luego la opción. */
	public function revert( array $stored, Revert_Context $context ): void {
		if ( isset( $stored['defaults']['types'] ) && is_array( $stored['defaults']['types'] ) && $stored['defaults']['types'] ) {
			self::revert_defaults( $stored['defaults'], $context );
		}
		if ( ! isset( $stored['before'], $stored['after'] ) || ! is_array( $stored['before'] ) || ! self::store_ready() ) {
			return;
		}
		$current = self::current();
		$before  = $stored['before'];
		if ( $context->unchanged( $current, (string) $stored['after'] ) ) {
			if ( $current !== $before ) {
				self::write( $before, array_keys( $before ) );
			}
			return;
		}
		$values = isset( $stored['values'] ) && is_array( $stored['values'] ) ? $stored['values'] : array();
		$next   = $current;
		foreach ( isset( $stored['added'] ) && is_array( $stored['added'] ) ? $stored['added'] : array() as $slug ) {
			$slug = (string) $slug;
			$hash = isset( $values[ $slug ] ) ? (string) $values[ $slug ] : '';
			if ( isset( $next[ $slug ] ) && ! isset( $before[ $slug ] ) && $context->unchanged( $next[ $slug ], $hash ) ) {
				unset( $next[ $slug ] );
			} elseif ( isset( $next[ $slug ] ) ) {
				$context->report( 'settings.presets.edited', array( 'slug' => $slug ) );
			}
		}
		// En «nuevo» el kit reemplazó los del sitio: vuelven los que nadie volvió a crear.
		if ( $context->is_fresh() ) {
			foreach ( $before as $slug => $preset ) {
				if ( ! isset( $next[ $slug ] ) ) {
					$next[ $slug ] = $preset;
				}
			}
		}
		if ( $next !== $current ) {
			self::write( $next, array_keys( $next ) );
			foreach ( Store::dropped() as $drop ) {
				$context->report( 'settings.presets.dropped', array( 'slug' => isset( $drop[0] ) ? (string) $drop[0] : '' ), Part_Context::WARNING );
			}
		}
	}

	// ── Punto de extensión de `kit-settings` ────────────────────────────────────────────────────────────────────

	/**
	 * Filtro `digitalisimo_elements_kit_used_globals_trees` (`Kit_Settings::export()`): los ajustes de cada preset,
	 * también de los inertes, para que los colores y las tipografías globales que sólo usa un preset entren en `used`
	 * y viajen (R-I9).
	 *
	 * @param mixed $trees Árboles de las demás partes.
	 * @return array
	 */
	public static function used_trees( $trees ): array {
		$trees = is_array( $trees ) ? $trees : array();
		foreach ( self::current() as $preset ) {
			if ( is_array( $preset ) && isset( $preset['settings'] ) && is_array( $preset['settings'] ) && $preset['settings'] ) {
				$trees[] = array( array( 'settings' => $preset['settings'] ) );
			}
		}
		return $trees;
	}

	// ── Utilidades ──────────────────────────────────────────────────────────────────────────────────────────────

	/** Presets del sitio, slug => preset (`Store::all()`). */
	public static function current(): array {
		if ( ! method_exists( __NAMESPACE__ . '\Store', 'all' ) ) {
			return array();
		}
		$all = Store::all();
		return is_array( $all ) ? $all : array();
	}

	/**
	 * Preset con la forma del kit, o null si no la tiene. No sanea los ajustes (eso es de `Store`): sólo comprueba
	 * slug, tipo, nombre y tipos de cada campo.
	 *
	 * @param string $slug   Slug.
	 * @param mixed  $preset Preset leído.
	 * @return array|null `{ type, label, settings, hash, updated, inert }`.
	 */
	public static function shape( string $slug, $preset ): ?array {
		if ( ! self::valid_slug( $slug ) || ! is_array( $preset ) ) {
			return null;
		}
		$type     = isset( $preset['type'] ) && is_string( $preset['type'] ) ? $preset['type'] : '';
		$label    = isset( $preset['label'] ) && is_string( $preset['label'] ) ? $preset['label'] : '';
		$settings = isset( $preset['settings'] ) && is_array( $preset['settings'] ) ? $preset['settings'] : null;
		if ( 1 !== preg_match( Store::TYPE, $type ) || null === $settings || '' === trim( $label ) ) {
			return null;
		}
		return array(
			'type'     => $type,
			'label'    => $label,
			'settings' => $settings,
			'hash'     => isset( $preset['hash'] ) && is_string( $preset['hash'] ) && 1 === preg_match( '/^[0-9a-f]{40}$/', $preset['hash'] ) ? $preset['hash'] : '',
			'updated'  => isset( $preset['updated'] ) && is_numeric( $preset['updated'] ) ? max( 0, (int) $preset['updated'] ) : 0,
			'inert'    => ! empty( $preset['inert'] ),
		);
	}

	/** Slug válido según D6 (la regla de `Store`). */
	public static function valid_slug( string $slug ): bool {
		return Store::valid_slug( $slug );
	}

	/** Huella de un preset para «misma huella → reutiliza»: el tipo y los ajustes, nada más. */
	public static function fingerprint( $preset ): string {
		$preset = is_array( $preset ) ? $preset : array();
		return Part_Context::hash(
			array(
				isset( $preset['type'] ) ? (string) $preset['type'] : '',
				isset( $preset['settings'] ) && is_array( $preset['settings'] ) ? $preset['settings'] : array(),
			)
		);
	}

	/**
	 * Slugs que se prueban en orden: el suyo, `<slug>-k<6 hex>` (hex de la huella: estable entre sesiones, así una
	 * reimportación reutiliza en vez de duplicar) y `<slug>-k<hex>-2` … `-9`. Todos cumplen D6.
	 *
	 * @return string[]
	 */
	public static function candidates( string $slug, string $print ): array {
		$hex    = substr( sha1( $print ), 0, 6 );
		$groups = explode( '-', rtrim( substr( $slug, 0, (int) Store::limits()['slug'] - 10 ), '-' ) );
		// Caben 8 grupos: el sufijo `k<hex>` ocupa uno y `-<n>`, otro.
		$out = array( $slug, implode( '-', array_slice( $groups, 0, 7 ) ) . '-k' . $hex );
		$six = implode( '-', array_slice( $groups, 0, 6 ) ) . '-k' . $hex;
		for ( $i = 2; $i <= self::MAX_CANDIDATES; $i++ ) {
			$out[] = $six . '-' . $i;
		}
		return array_values( array_filter( array_unique( $out ), array( __CLASS__, 'valid_slug' ) ) );
	}

	/**
	 * Destino de un slug que no llegó a la opción: el suyo si el sitio no tiene uno igual (no existe: nada se aplica);
	 * si lo tiene, uno que no existe, para que esos elementos no tomen el preset ajeno.
	 */
	private static function closed( string $slug, array $presets ): string {
		if ( ! isset( $presets[ $slug ] ) ) {
			return $slug;
		}
		foreach ( self::candidates( $slug, Part_Context::hash( array( 'closed', $slug ) ) ) as $candidate ) {
			if ( ! isset( $presets[ $candidate ] ) ) {
				return $candidate;
			}
		}
		return $slug;
	}

	/**
	 * Reescribe `digitalisimo_preset` en los valores por defecto que añadió esta importación.
	 *
	 * @return array `{ kit, types: { tipo: { before, after } } }`.
	 */
	private static function rewrite_defaults( Import_Context $context ): array {
		$out   = array(
			'kit'   => 0,
			'types' => array(),
		);
		$own   = $context->stored( 'element-defaults' );
		$types = isset( $own['added'] ) && is_array( $own['added'] ) ? $own['added'] : array();
		$kit   = $types ? self::kit( isset( $own['kit'] ) ? (int) $own['kit'] : $context->kit_id() ) : null;
		if ( ! $kit ) {
			return $out;
		}
		$defaults = Element_Defaults::current_defaults( $kit );
		foreach ( $types as $type ) {
			$type = (string) $type;
			if ( ! isset( $defaults[ $type ][ self::CONTROL ] ) || ! is_string( $defaults[ $type ][ self::CONTROL ] ) || '' === $defaults[ $type ][ self::CONTROL ] ) {
				continue;
			}
			$old = $defaults[ $type ][ self::CONTROL ];
			$new = $context->mapped( self::MAP, $old );
			if ( null === $new ) {
				$context->report(
					'map.miss',
					array(
						'map'   => self::MAP,
						'value' => $old,
					)
				);
				continue;
			}
			if ( $new === $old ) {
				continue;
			}
			$entry                              = $defaults[ $type ];
			$defaults[ $type ][ self::CONTROL ] = (string) $new;
			$out['types'][ $type ]              = array(
				'before' => $entry,
				'after'  => Part_Context::hash( $defaults[ $type ] ),
			);
		}
		if ( $out['types'] ) {
			$out['kit'] = self::kit_id( $kit );
			self::write_defaults( $kit, $defaults );
		}
		return $out;
	}

	/** Deshace `rewrite_defaults()` en los tipos que nadie cambió después. */
	private static function revert_defaults( array $stored, Revert_Context $context ): void {
		$kit = self::kit( isset( $stored['kit'] ) ? (int) $stored['kit'] : $context->kit_id() );
		if ( ! $kit ) {
			return;
		}
		$defaults = Element_Defaults::current_defaults( $kit );
		$changed  = false;
		foreach ( $stored['types'] as $type => $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['before'], $entry['after'] ) || ! is_array( $entry['before'] ) ) {
				continue;
			}
			if ( isset( $defaults[ $type ] ) && $context->unchanged( $defaults[ $type ], (string) $entry['after'] ) ) {
				$defaults[ $type ] = $entry['before'];
				$changed           = true;
			} elseif ( isset( $defaults[ $type ] ) ) {
				$context->report( 'settings.presets.defaults-edited', array( 'type' => (string) $type ) );
			}
		}
		if ( $changed ) {
			self::write_defaults( $kit, $defaults );
		}
	}

	/**
	 * Escribe los presets con `Store::replace()`. `$keep`: slugs que ya eran del sitio y que deben quedar tal cual,
	 * sin volver a sanearlos, si `replace()` admite esa lista (segundo parámetro).
	 */
	private static function write( array $presets, array $keep ): void {
		$data = array(
			'v'       => self::VERSION,
			'presets' => $presets,
		);
		if ( self::keeps() ) {
			Store::replace( $data, array_values( array_map( 'strval', $keep ) ) );
		} else {
			Store::replace( $data );
		}
	}

	/** ¿Admite `Store::replace()` la lista de slugs que se conservan tal cual? */
	private static function keeps(): bool {
		try {
			return ( new \ReflectionMethod( __NAMESPACE__ . '\Store', 'replace' ) )->getNumberOfParameters() > 1;
		} catch ( \ReflectionException $error ) {
			return false;
		}
	}

	/**
	 * Presets del sitio que `Store::replace()` descartaría hoy al volver a validarlos: de un tipo registrado que ya no
	 * admite presets o cuyos ajustes ya no superan el saneado. Los inertes ya pasaron su comprobación al guardarse.
	 *
	 * @return string[]
	 */
	private static function fragile( array $presets ): array {
		$out = array();
		foreach ( $presets as $slug => $preset ) {
			$type     = isset( $preset['type'] ) ? (string) $preset['type'] : '';
			$settings = isset( $preset['settings'] ) && is_array( $preset['settings'] ) ? $preset['settings'] : array();
			if ( ! Store::type_registered( $type ) ) {
				continue;
			}
			if ( ! Store::supports_type( $type ) || ( $settings && ! Store::sanitize_settings( $type, $settings ) ) ) {
				$out[] = (string) $slug;
			}
		}
		return $out;
	}

	/** ¿Están las operaciones de `Store` que usa la parte? */
	private static function store_ready(): bool {
		$store = __NAMESPACE__ . '\Store';
		foreach ( array( 'all', 'replace', 'dropped', 'sanitize_settings', 'type_registered', 'supports_type', 'valid_slug', 'limits' ) as $method ) {
			if ( ! method_exists( $store, $method ) ) {
				return false;
			}
		}
		return true;
	}

	/** Kit de ese ID, o el activo. */
	private static function kit( int $id ) {
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

	private static function write_defaults( $kit, array $defaults ): void {
		if ( is_object( $kit ) && method_exists( $kit, 'update_json_meta' ) ) {
			$kit->update_json_meta( self::DEFAULTS_META, $defaults );
		}
	}
}
