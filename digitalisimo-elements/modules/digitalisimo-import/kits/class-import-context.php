<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Contexto de una importación: lo que reciben las partes en `Part::import()` y `Early_Part::import_early()`.
 *
 * - **Modo:** `add` («sólo agregar», el de siempre por defecto) o `fresh` («nuevo», sólo si el administrador lo
 *   pidió en un sitio vacío, D1). Un valor desconocido es `add`: se falla hacia lo que no cambia el sitio.
 * - **Mapas:** viejo → nuevo. Los del núcleo y de K (`NATIVE_MAPS`) los pone el runner al construir el contexto;
 *   las partes publican los suyos con `add_map()` y el remapeo los usa como `map:<nombre>` (§5.5).
 * - **`store()`:** lo que `Part::revert()` recibirá. Se une a lo guardado en peticiones anteriores de la sesión.
 * - **`staged_path()`:** el único modo de leer un archivo preparado del kit.
 * - **`flag_review()`:** un documento que debe quedar en borrador con «revisar antes de publicar» (regla de cierre).
 */
final class Import_Context extends Part_Context {
	const MODE_ADD   = Module::MODE_ADD;
	const MODE_FRESH = Module::MODE_FRESH;

	/** Mapas que fija el runner (§9.1). `url` lleva prefijos de URL; `attachment`, URL vieja => ID nuevo. */
	const NATIVE_MAPS = array( 'post', 'template', 'term', 'menu', 'menu_slug', 'global_color', 'global_typography', 'url', 'attachment' );

	const MAP_NAME_PATTERN = '/^[a-z0-9_-]{1,64}$/';
	const SESSION_PATTERN  = '/^[A-Za-z0-9._-]{1,64}$/';
	const MAX_REVIEW       = 50;

	/** @var string */
	private $session;

	/** @var string */
	private $mode;

	/** @var string */
	private $kit;

	/** @var string[] */
	private $origin;

	/** @var array */
	private $manifest;

	/** @var int */
	private $user;

	/** @var int */
	private $max_post_id;

	/** @var int */
	private $kit_id;

	/** @var string Carpeta preparada real, con barra final; '' si no hay. */
	private $staged_dir = '';

	/** @var array[] Nombre => `[ viejo => nuevo ]`. */
	private $maps = array();

	/** @var array[] Parte => datos para `revert()`. */
	private $stored = array();

	/** @var array[] ID de documento => avisos. */
	private $review = array();

	/**
	 * @param array $args {
	 *     @type string $session     ID de la sesión del núcleo (obligatorio).
	 *     @type string $mode        `add` (por defecto) o `fresh`.
	 *     @type string $kit         Huella del kit (§9.6).
	 *     @type array  $origin      `format`, `kit`, `home`, `site` y `uploads` del origen (§5.3).
	 *     @type array  $manifest    Clave `digitalisimo` del manifiesto, o vacío en un kit de terceros.
	 *     @type int    $user        Usuario que importa.
	 *     @type int    $max_post_id Mayor ID de entrada antes de importar: lo posterior lo creó la sesión.
	 *     @type int    $kit_id      Kit activo del sitio (D2).
	 *     @type string $staged_dir  Carpeta preparada, ya resuelta por el runner desde `staged_rel`.
	 *     @type array  $maps        Nombre => `[ viejo => nuevo ]`.
	 *     @type array  $stored      Parte => datos guardados en peticiones anteriores.
	 * }
	 *
	 * @throws \InvalidArgumentException Sin una sesión válida.
	 */
	public function __construct( array $args ) {
		$session = isset( $args['session'] ) && is_string( $args['session'] ) ? $args['session'] : '';
		if ( 1 !== preg_match( self::SESSION_PATTERN, $session ) ) {
			throw new \InvalidArgumentException( 'La importación necesita una sesión válida.' );
		}
		$this->session     = $session;
		$this->mode        = isset( $args['mode'] ) && self::MODE_FRESH === $args['mode'] ? self::MODE_FRESH : self::MODE_ADD;
		$this->kit         = isset( $args['kit'] ) && is_string( $args['kit'] ) ? $args['kit'] : '';
		$this->manifest    = isset( $args['manifest'] ) && is_array( $args['manifest'] ) ? $args['manifest'] : array();
		$this->user        = isset( $args['user'] ) ? max( 0, (int) $args['user'] ) : 0;
		$this->max_post_id = isset( $args['max_post_id'] ) ? max( 0, (int) $args['max_post_id'] ) : 0;
		$this->kit_id      = isset( $args['kit_id'] ) ? max( 0, (int) $args['kit_id'] ) : 0;
		$this->origin      = array();
		foreach ( array( 'format', 'kit', 'home', 'site', 'uploads' ) as $key ) {
			$this->origin[ $key ] = isset( $args['origin'][ $key ] ) && is_string( $args['origin'][ $key ] ) ? $args['origin'][ $key ] : '';
		}
		if ( ! empty( $args['staged_dir'] ) && is_string( $args['staged_dir'] ) ) {
			$real = realpath( $args['staged_dir'] );
			if ( false !== $real && is_dir( $real ) ) {
				$this->staged_dir = rtrim( str_replace( '\\', '/', $real ), '/' ) . '/';
			}
		}
		foreach ( isset( $args['maps'] ) && is_array( $args['maps'] ) ? $args['maps'] : array() as $name => $map ) {
			if ( is_string( $name ) && 1 === preg_match( self::MAP_NAME_PATTERN, $name ) && is_array( $map ) ) {
				$this->maps[ $name ] = self::scalar_pairs( $map );
			}
		}
		foreach ( isset( $args['stored'] ) && is_array( $args['stored'] ) ? $args['stored'] : array() as $part => $data ) {
			if ( is_string( $part ) && Parts::is_valid_key( $part ) && is_array( $data ) ) {
				$this->stored[ $part ] = $data;
			}
		}
	}

	public function session(): string {
		return $this->session;
	}

	/** `add` o `fresh`. */
	public function mode(): string {
		return $this->mode;
	}

	/** ¿Modo «nuevo»? */
	public function is_fresh(): bool {
		return self::MODE_FRESH === $this->mode;
	}

	/** ¿Modo «sólo agregar»? */
	public function is_add(): bool {
		return self::MODE_ADD === $this->mode;
	}

	/** Huella del kit (D7). */
	public function kit(): string {
		return $this->kit;
	}

	/**
	 * Datos del origen del kit.
	 *
	 * @param string $key `format`, `kit`, `home`, `site` o `uploads`; vacío devuelve todos.
	 *
	 * @return string|string[]
	 */
	public function origin( string $key = '' ) {
		if ( '' === $key ) {
			return $this->origin;
		}
		return isset( $this->origin[ $key ] ) ? $this->origin[ $key ] : '';
	}

	/**
	 * Clave `digitalisimo` del manifiesto.
	 *
	 * @param string $key Una clave de primer nivel (`parts`, `needs`, `media`…); vacío devuelve todo.
	 *
	 * @return mixed Null si no existe.
	 */
	public function manifest( string $key = '' ) {
		if ( '' === $key ) {
			return $this->manifest;
		}
		return array_key_exists( $key, $this->manifest ) ? $this->manifest[ $key ] : null;
	}

	public function user(): int {
		return $this->user;
	}

	public function max_post_id(): int {
		return $this->max_post_id;
	}

	/** ¿Lo creó esta importación? Un ID ≤ `max_post_id` ya existía y no se toca (D8). */
	public function is_new_post( int $id ): bool {
		return $id > $this->max_post_id;
	}

	/** Kit activo del sitio: K nunca crea otro (D2). */
	public function kit_id(): int {
		return $this->kit_id;
	}

	/** Carpeta preparada real, con barra final, o '' si no hay. */
	public function staged_dir(): string {
		return $this->staged_dir;
	}

	/**
	 * Archivo preparado del kit, o null.
	 *
	 * @param string $relative `media/<sha256>.<ext>` o `files/(shapes|masks)/<slug>.svg` (§5.2). Cualquier otra
	 *                         forma devuelve null, igual que un archivo que no existe o que sale de la carpeta.
	 */
	public function staged_path( string $relative ): ?string {
		if ( '' === $this->staged_dir || ! Module::is_kit_file( $relative ) ) {
			return null;
		}
		$real = realpath( $this->staged_dir . $relative );
		if ( false === $real || ! is_file( $real ) ) {
			return null;
		}
		return 0 === strpos( str_replace( '\\', '/', $real ), $this->staged_dir ) ? $real : null;
	}

	/** Un mapa (`[ viejo => nuevo ]`), vacío si no existe. */
	public function map( string $name ): array {
		return isset( $this->maps[ $name ] ) ? $this->maps[ $name ] : array();
	}

	/** @return array[] Todos los mapas, por nombre. */
	public function maps(): array {
		return $this->maps;
	}

	/**
	 * Valor nuevo de un valor viejo en un mapa.
	 *
	 * @param string     $name    Nombre del mapa.
	 * @param int|string $old     Valor viejo.
	 * @param mixed      $default Lo que se devuelve si no hay destino.
	 *
	 * @return mixed
	 */
	public function mapped( string $name, $old, $default = null ) {
		if ( ( ! is_int( $old ) && ! is_string( $old ) ) || ! isset( $this->maps[ $name ] ) ) {
			return $default;
		}
		return array_key_exists( $old, $this->maps[ $name ] ) ? $this->maps[ $name ][ $old ] : $default;
	}

	/**
	 * Publica pares viejo → nuevo en un mapa.
	 *
	 * Un valor viejo que ya tiene destino lo conserva: si el nuevo destino es otro, se anota `map-conflict` y gana
	 * el primero. Sólo se admiten claves y valores escalares (enteros o cadenas).
	 *
	 * @param string $name Nombre (`[a-z0-9_-]+`). Una referencia `map:<nombre>` lo usa (§5.5).
	 * @param array  $map  `[ viejo => nuevo ]`.
	 */
	public function add_map( string $name, array $map ): void {
		if ( 1 !== preg_match( self::MAP_NAME_PATTERN, $name ) ) {
			Module::doing_it_wrong( __METHOD__, 'Nombre de mapa inválido: ' . Module::describe( $name ) );
			return;
		}
		$current = isset( $this->maps[ $name ] ) ? $this->maps[ $name ] : array();
		foreach ( self::scalar_pairs( $map ) as $old => $new ) {
			if ( ! array_key_exists( $old, $current ) ) {
				$current[ $old ] = $new;
			} elseif ( $current[ $old ] !== $new ) {
				$this->report(
					'map-conflict',
					array(
						'map'  => $name,
						'old'  => $old,
						'kept' => $current[ $old ],
						'new'  => $new,
					),
					self::WARNING
				);
			}
		}
		$this->maps[ $name ] = $current;
	}

	/**
	 * Guarda datos de la parte en curso para su `revert()`. Une por claves de primer nivel con lo ya guardado.
	 *
	 * Sólo arrays y escalares: se guardan en la sesión. Fuera de una parte (`begin_part()`) no hace nada.
	 */
	public function store( array $data ): void {
		if ( '' === $this->part ) {
			Module::doing_it_wrong( __METHOD__, 'store() sólo se puede llamar desde una parte.' );
			return;
		}
		$this->stored[ $this->part ] = array_replace( isset( $this->stored[ $this->part ] ) ? $this->stored[ $this->part ] : array(), $data );
	}

	/** Lo guardado por una parte (por defecto, la que está corriendo). */
	public function stored( string $part = '' ): array {
		$part = '' === $part ? $this->part : $part;
		return isset( $this->stored[ $part ] ) ? $this->stored[ $part ] : array();
	}

	/** @return array[] Parte => datos, para que el runner los persista en la sesión. */
	public function stored_all(): array {
		return $this->stored;
	}

	/**
	 * Marca un documento para «revisar antes de publicar» (regla de cierre de §5.5).
	 *
	 * El runner final lo deja en borrador en los dos modos, y «Publicar» no lo publica hasta que cambie.
	 *
	 * @param int    $post_id Documento creado por la sesión.
	 * @param string $reason  Código del motivo (por ejemplo, `gate-unresolved`).
	 * @param array  $data    Regla o referencia original, para el informe.
	 */
	public function flag_review( int $post_id, string $reason, array $data = array() ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		$entries = isset( $this->review[ $post_id ] ) ? $this->review[ $post_id ] : array();
		if ( count( $entries ) < self::MAX_REVIEW ) {
			$entries[] = array(
				'part'   => $this->part,
				'reason' => $reason,
				'data'   => self::clean( $data, 1 ),
			);
		}
		$this->review[ $post_id ] = $entries;
		$this->report(
			'review',
			array(
				'post'   => $post_id,
				'reason' => $reason,
			),
			self::WARNING
		);
	}

	/** @return array[] ID de documento => avisos `{ part, reason, data }`. */
	public function review(): array {
		return $this->review;
	}

	/** IDs de los documentos marcados para revisar. */
	public function review_ids(): array {
		return array_keys( $this->review );
	}

	/** Pares con clave entera o cadena y valor entero o cadena; lo demás se descarta. */
	private static function scalar_pairs( array $map ): array {
		$out = array();
		foreach ( $map as $old => $new ) {
			if ( is_int( $new ) || ( is_string( $new ) && '' !== $new ) ) {
				$out[ $old ] = $new;
			}
		}
		return $out;
	}
}
