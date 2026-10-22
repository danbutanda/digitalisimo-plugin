<?php
namespace Digitalisimo\Elements\Woo\Swatches;

defined( 'ABSPATH' ) || exit;

/**
 * Datos de las muestras de variación (V.6.1 y V.15.2 de la especificación de F4-Woo). Una sola fuente para el
 * formulario, el bucle, las pantallas, los filtros (presentación «muestras») y los kits (frente H).
 *
 * - **Por término:** las metas `color` e `image` de WooCommerce (formato de `wc-visual`, también con esa función
 *   apagada): `image` es el ID de un adjunto imagen y `color` un hexadecimal de `sanitize_hex_color()` (`#rgb` o
 *   `#rrggbb`, sin canal alfa). Son excluyentes y se leen como WooCommerce: primero la imagen, después el color
 *   (`VisualAttributeTermMeta::build_term_visual()` y `save_term_visual()` de 11.2.1). No hay metas propias: la
 *   etiqueta es el nombre del término. No se llama ninguna clase `Automattic\WooCommerce\Internal\…`.
 * - **Por atributo:** la opción del sitio `digitalisimo_elements_swatches` = `{ v: 1, attributes: { slug: { type,
 *   shape, size } } }` con carga automática. Sólo guarda los atributos con un tipo distinto de «Desplegable»; un
 *   atributo sin entrada es un desplegable, igual que hoy. Se escribe bajo el cerrojo compartido de Elements
 *   (`Lock::run( 'digitalisimo_elements_lock_swatches', …, 30 )`). `attribute_type` de WooCommerce no se toca.
 * - El front sólo lee la opción si está en `wp_load_alloptions()`: un sitio sin muestras no hace ninguna consulta.
 */
final class Data {
	const OPTION = 'digitalisimo_elements_swatches';
	const LOCK   = 'digitalisimo_elements_lock_swatches';
	const STALE  = 30;
	const MAX    = 100;

	/** Tipos por atributo: Desplegable (por defecto), «Muestra visual» (color o imagen) y «Etiqueta» (el nombre). */
	const TYPES = array( 'select', 'visual', 'label' );

	/** Formas: círculo, cuadrado redondeado y píldora. */
	const SHAPES = array( 'circle', 'rounded', 'pill' );

	const SIZE_MIN     = 24;
	const SIZE_MAX     = 64;
	const SIZE_DEFAULT = 32;

	/** @var array|null Opción normalizada de esta petición. */
	private static $cache = null;

	/** Valores de un atributo sin entrada: un desplegable. */
	public static function defaults(): array {
		return array( 'type' => 'select', 'shape' => 'circle', 'size' => self::SIZE_DEFAULT );
	}

	/**
	 * Configuración de un atributo por su taxonomía (`pa_color`): `{ type: select|visual|label, shape, size }`.
	 * Un nombre que no es de un atributo global, o un atributo sin entrada, da los valores de un desplegable.
	 *
	 * @param string $taxonomy Taxonomía del atributo.
	 */
	public static function attribute( string $taxonomy ): array {
		$slug = self::slug( $taxonomy );
		if ( '' === $slug ) {
			return self::defaults();
		}
		$entry = self::entry( $slug );
		return null === $entry ? self::defaults() : $entry;
	}

	/**
	 * Entrada guardada de un atributo por su *slug* (`color`), o `null` si no tiene (es un desplegable). Es lo que
	 * exporta la parte de kits `woo-attributes`.
	 *
	 * @param string $slug Slug del atributo, sin `pa_`.
	 */
	public static function entry( string $slug ): ?array {
		$all = self::all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/** Todas las entradas normalizadas, por *slug*. */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$raw = array();
		if ( function_exists( 'wp_load_alloptions' ) ) {
			$loaded = wp_load_alloptions();
			if ( is_array( $loaded ) && array_key_exists( self::OPTION, $loaded ) ) {
				$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
			}
		}
		self::$cache = self::normalize( $raw );
		return self::$cache;
	}

	/**
	 * Valores de un término: `{ image: int, color: string, label: string }`. `image` sólo si el adjunto es una
	 * imagen; `color` sólo sin imagen (son excluyentes, como en WooCommerce). `label` es el nombre del término.
	 *
	 * @param \WP_Term $term Término de un atributo.
	 */
	public static function term( \WP_Term $term ): array {
		$id    = (int) $term->term_id;
		$image = function_exists( 'get_term_meta' ) ? absint( get_term_meta( $id, 'image', true ) ) : 0;
		if ( $image && ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $image ) ) ) {
			$image = 0;
		}
		$color = '';
		if ( ! $image && function_exists( 'get_term_meta' ) ) {
			$color = self::color( get_term_meta( $id, 'color', true ) );
		}
		return array( 'image' => $image, 'color' => $color, 'label' => (string) $term->name );
	}

	/**
	 * Precarga las metas de los términos y los adjuntos de sus imágenes (como
	 * `VisualAttributeTermMeta::prime_term_visual_caches()`): un bucle pinta después sin consultas por producto.
	 *
	 * @param int[] $term_ids IDs de términos.
	 */
	public static function prime( array $term_ids ): void {
		$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );
		if ( ! $term_ids || ! function_exists( 'update_termmeta_cache' ) ) {
			return;
		}
		update_termmeta_cache( $term_ids );
		$images = array();
		foreach ( $term_ids as $id ) {
			$image = absint( get_term_meta( $id, 'image', true ) );
			if ( $image ) {
				$images[] = $image;
			}
		}
		if ( $images && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( array_values( array_unique( $images ) ), false, true );
		}
	}

	/**
	 * Escribe los valores de un término con el formato de WooCommerce: una imagen válida gana y borra el color; si no,
	 * un color válido borra la imagen; si no hay ninguno, borra los dos (`save_term_visual()` de 11.2.1).
	 *
	 * @param int    $term_id  Término.
	 * @param string $color    Hexadecimal o ''.
	 * @param int    $image_id Adjunto imagen o 0.
	 */
	public static function save_term( int $term_id, string $color = '', int $image_id = 0 ): void {
		if ( $image_id && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( $image_id ) ) {
			update_term_meta( $term_id, 'image', absint( $image_id ) );
			delete_term_meta( $term_id, 'color' );
			return;
		}
		$color = self::color( $color );
		if ( '' !== $color ) {
			update_term_meta( $term_id, 'color', $color );
			delete_term_meta( $term_id, 'image' );
			return;
		}
		delete_term_meta( $term_id, 'color' );
		delete_term_meta( $term_id, 'image' );
	}

	/**
	 * Hexadecimal válido para la meta `color` (la regla de `sanitize_hex_color()`: `#rgb` o `#rrggbb`), o ''.
	 *
	 * @param mixed $value Valor.
	 */
	public static function color( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( function_exists( 'sanitize_hex_color' ) ) {
			return (string) sanitize_hex_color( $value );
		}
		return 1 === preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $value ) ? $value : '';
	}

	/**
	 * Entrada saneada (`{ type, shape, size }`) o `null` si no es válida: listas cerradas y tamaño entero 24–64.
	 *
	 * @param mixed $entry Valor recibido.
	 */
	public static function sanitize_entry( $entry ): ?array {
		if ( ! is_array( $entry ) ) {
			return null;
		}
		$type = isset( $entry['type'] ) && is_string( $entry['type'] ) ? $entry['type'] : '';
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return null;
		}
		$shape = isset( $entry['shape'] ) && is_string( $entry['shape'] ) && in_array( $entry['shape'], self::SHAPES, true ) ? $entry['shape'] : ( 'label' === $type ? 'pill' : 'circle' );
		$size  = isset( $entry['size'] ) && is_numeric( $entry['size'] ) ? (int) $entry['size'] : self::SIZE_DEFAULT;
		$size  = max( self::SIZE_MIN, min( self::SIZE_MAX, $size ) );
		return array( 'type' => $type, 'shape' => $shape, 'size' => $size );
	}

	/**
	 * Guarda la entrada de un atributo bajo el cerrojo. Un tipo «Desplegable» borra la entrada (es el valor por
	 * defecto). Devuelve `false` si la entrada no es válida, el *slug* no lo es o el cerrojo está tomado.
	 *
	 * @param string $slug  Slug del atributo, sin `pa_`.
	 * @param array  $entry `{ type, shape, size }`.
	 */
	public static function put( string $slug, array $entry ): bool {
		$slug  = self::clean_slug( $slug );
		$entry = self::sanitize_entry( $entry );
		if ( '' === $slug || null === $entry ) {
			return false;
		}
		return self::write(
			static function ( array $all ) use ( $slug, $entry ) {
				if ( 'select' === $entry['type'] ) {
					unset( $all[ $slug ] );
				} elseif ( isset( $all[ $slug ] ) || count( $all ) < self::MAX ) {
					$all[ $slug ] = $entry;
				}
				return $all;
			}
		);
	}

	/**
	 * Borra la entrada de un atributo (al borrarlo en WooCommerce o al revertir un kit).
	 *
	 * @param string $slug Slug del atributo, sin `pa_`.
	 */
	public static function remove( string $slug ): bool {
		$slug = self::clean_slug( $slug );
		if ( '' === $slug ) {
			return false;
		}
		return self::write(
			static function ( array $all ) use ( $slug ) {
				unset( $all[ $slug ] );
				return $all;
			}
		);
	}

	/**
	 * Mueve la entrada de un *slug* a otro al renombrar el atributo; si el nuevo ya tenía entrada, gana la movida.
	 *
	 * @param string $old Slug anterior.
	 * @param string $new Slug nuevo.
	 */
	public static function move( string $old, string $new ): bool {
		$old = self::clean_slug( $old );
		$new = self::clean_slug( $new );
		if ( '' === $old || '' === $new || $old === $new ) {
			return false;
		}
		return self::write(
			static function ( array $all ) use ( $old, $new ) {
				if ( isset( $all[ $old ] ) ) {
					$all[ $new ] = $all[ $old ];
					unset( $all[ $old ] );
				}
				return $all;
			}
		);
	}

	/** Slug de una taxonomía de atributo (`pa_color` → `color`), o ''. */
	public static function slug( string $taxonomy ): string {
		return 0 === strpos( $taxonomy, 'pa_' ) ? self::clean_slug( substr( $taxonomy, 3 ) ) : '';
	}

	/** Vacía la memoria de la petición (pruebas y tras escribir). */
	public static function flush(): void {
		self::$cache = null;
	}

	/** Valor guardado → entradas válidas por *slug*. Acepta `[]` (opción recién creada) y la forma `{ v, attributes }`. */
	public static function normalize( $raw ): array {
		$entries = is_array( $raw ) && isset( $raw['attributes'] ) && is_array( $raw['attributes'] ) ? $raw['attributes'] : array();
		$out     = array();
		foreach ( $entries as $slug => $entry ) {
			$slug  = self::clean_slug( (string) $slug );
			$entry = self::sanitize_entry( $entry );
			if ( '' !== $slug && null !== $entry && 'select' !== $entry['type'] && count( $out ) < self::MAX ) {
				$out[ $slug ] = $entry;
			}
		}
		return $out;
	}

	private static function clean_slug( string $slug ): string {
		return 1 === preg_match( '/^[a-z0-9_\-]{1,28}$/', $slug ) ? $slug : '';
	}

	/**
	 * Lee la opción de la base (no de la caché de la petición), aplica `$change` y la guarda con carga automática,
	 * todo bajo el cerrojo de 30 s.
	 *
	 * @param callable $change Recibe y devuelve las entradas por *slug*.
	 */
	private static function write( callable $change ): bool {
		if ( ! class_exists( '\\Digitalisimo\\Elements\\Lock' ) ) {
			require_once dirname( __DIR__, 2 ) . '/digitalisimo-design/class-lock.php';
		}
		$done = \Digitalisimo\Elements\Lock::run(
			self::LOCK,
			static function () use ( $change ) {
				$all  = self::normalize( self::read_fresh() );
				$next = $change( $all );
				ksort( $next );
				self::flush();
				if ( $next === $all ) {
					return true;
				}
				update_option( self::OPTION, array( 'v' => 1, 'attributes' => $next ), true );
				self::flush();
				return true;
			},
			self::STALE,
			false
		);
		return true === $done;
	}

	/** Valor de la opción leído de la base, sin la caché de opciones de la petición. */
	private static function read_fresh() {
		global $wpdb;
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) && isset( $wpdb->options ) ) {
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return null === $value ? array() : ( function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $value ) : $value );
		}
		return get_option( self::OPTION, array() );
	}
}
