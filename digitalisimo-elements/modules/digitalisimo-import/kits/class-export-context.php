<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Contexto de una exportación: lo que reciben las partes en `Part::export()`.
 *
 * - `add_file()` pide un archivo para `digitalisimo/<ruta>`. La ruta sólo puede tener las formas de §5.2 y el
 *   origen debe ser un archivo de una de las carpetas permitidas (`roots`) y fuera de las excluidas (D6, CR-2).
 *   El runner final añade al ZIP lo que devuelve `files()`.
 * - `budget()` dice cuánto cabe todavía: tope por archivo y total (D6, filtro `digitalisimo_elements_kit_media_limits`).
 * - `exported()` lista lo que exportaron los runners de K, para que una parte exporte sólo lo suyo.
 * - `not_exported()` suma a `manifest.digitalisimo.not_exported`.
 */
final class Export_Context extends Part_Context {
	const SURFACE_APP = 'app';
	const SURFACE_CLI = 'cli';
	const MEDIA_EMBED = 'embed';
	const MEDIA_LINK  = 'link';

	/** 20 MB por archivo (D6). */
	const FILE_LIMIT = 20971520;
	/** 32 MB en total en la App, o `memory_limit / 8` si es menor (D6). WP-CLI no tiene tope total. */
	const TOTAL_LIMIT = 33554432;

	const MAX_REJECTED = 200;

	/** @var string */
	private $surface;

	/** @var string */
	private $media;

	/** @var string[] */
	private $include;

	/** @var string[] */
	private $origin;

	/** @var string[] Carpetas reales de las que puede salir un archivo, con barra final. */
	private $roots = array();

	/** @var string[] Carpetas excluidas dentro de `roots`, con barra final. */
	private $excluded = array();

	/** @var int[] `file` y `total` en bytes; 0 es sin tope. */
	private $limits;

	/** @var array[] Ruta => `{ source, bytes }`. */
	private $files = array();

	/** @var int */
	private $used = 0;

	/** @var array[] */
	private $rejected = array();

	/** @var array[] Tipo => IDs. */
	private $exported = array();

	/** @var int[] Motivo => número. */
	private $not_exported = array();

	/**
	 * @param array $args {
	 *     @type string   $surface  `app` (por defecto) o `cli`.
	 *     @type string   $media    `embed` (por defecto, D6) o `link`.
	 *     @type string[] $include  Lo que pidió exportar el administrador (`templates`, `content`, `settings`…).
	 *     @type string[] $origin   `home`, `site` y `uploads` del sitio que exporta.
	 *     @type string[] $roots    Carpetas de las que pueden salir archivos. Sin ninguna, no sale ninguno.
	 *     @type string[] $excluded Carpetas que nunca se incrustan aunque estén dentro de `roots`.
	 *     @type int[]    $limits   `file` y `total`; por defecto, `media_limits( $surface )`.
	 * }
	 */
	public function __construct( array $args = array() ) {
		$this->surface = isset( $args['surface'] ) && self::SURFACE_CLI === $args['surface'] ? self::SURFACE_CLI : self::SURFACE_APP;
		$this->media   = isset( $args['media'] ) && self::MEDIA_LINK === $args['media'] ? self::MEDIA_LINK : self::MEDIA_EMBED;
		$this->include = array();
		foreach ( isset( $args['include'] ) && is_array( $args['include'] ) ? $args['include'] : array() as $item ) {
			if ( is_string( $item ) && '' !== $item ) {
				$this->include[] = $item;
			}
		}
		$this->origin = array();
		foreach ( array( 'home', 'site', 'uploads' ) as $key ) {
			$this->origin[ $key ] = isset( $args['origin'][ $key ] ) && is_string( $args['origin'][ $key ] ) ? $args['origin'][ $key ] : '';
		}
		foreach ( isset( $args['roots'] ) && is_array( $args['roots'] ) ? $args['roots'] : array() as $root ) {
			$real = is_string( $root ) && '' !== $root ? realpath( $root ) : false;
			if ( false !== $real && is_dir( $real ) ) {
				$this->roots[] = self::dir_key( $real );
			}
		}
		foreach ( isset( $args['excluded'] ) && is_array( $args['excluded'] ) ? $args['excluded'] : array() as $dir ) {
			if ( is_string( $dir ) && '' !== $dir ) {
				$real             = realpath( $dir );
				$this->excluded[] = self::dir_key( false !== $real ? $real : $dir );
			}
		}
		$limits       = isset( $args['limits'] ) && is_array( $args['limits'] ) ? $args['limits'] : self::media_limits( $this->surface );
		$this->limits = array(
			'file'  => isset( $limits['file'] ) ? max( 0, (int) $limits['file'] ) : self::FILE_LIMIT,
			'total' => isset( $limits['total'] ) ? max( 0, (int) $limits['total'] ) : 0,
		);
	}

	/**
	 * Contexto del sitio actual: sus URL, su carpeta de archivos como única raíz y, excluidas, las carpetas que
	 * no son medios del sitio (otros sitios de la red bajo el principal, `woocommerce_uploads` y los temporales
	 * de K). `roots` de `$args` sustituye la raíz; `excluded` se suma.
	 */
	public static function for_site( array $args = array() ): self {
		$uploads  = wp_upload_dir( null, false );
		$base     = empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ? rtrim( $uploads['basedir'], '/\\' ) : '';
		$excluded = '' === $base ? array() : array( $base . '/woocommerce_uploads', $base . '/' . Module::TMP_DIR );
		if ( '' !== $base && is_multisite() && is_main_site() ) {
			$excluded[] = $base . '/sites';
		}
		if ( isset( $args['excluded'] ) && is_array( $args['excluded'] ) ) {
			$excluded = array_merge( $excluded, $args['excluded'] );
		}
		$args['excluded'] = $excluded;
		if ( ! isset( $args['roots'] ) ) {
			$args['roots'] = '' === $base ? array() : array( $base );
		}
		if ( ! isset( $args['origin'] ) ) {
			$args['origin'] = array(
				'home'    => home_url(),
				'site'    => get_site_url(),
				'uploads' => '' === $base ? '' : (string) $uploads['baseurl'],
			);
		}
		return new self( $args );
	}

	/**
	 * Topes de medios incrustados (D6): 20 MB por archivo y, en la App, min(32 MB, memory_limit / 8).
	 *
	 * Pasa por el filtro `digitalisimo_elements_kit_media_limits( array $limits, string $surface )`. 0 es sin tope.
	 *
	 * @param string $surface      `app` o `cli`.
	 * @param string $memory_limit Valor de `memory_limit`; por defecto, el de PHP.
	 */
	public static function media_limits( string $surface, string $memory_limit = '' ): array {
		$memory = self::parse_bytes( '' !== $memory_limit ? $memory_limit : (string) ini_get( 'memory_limit' ) );
		$total  = self::TOTAL_LIMIT;
		if ( $memory > 0 ) {
			$total = min( $total, intdiv( $memory, 8 ) );
		}
		$limits = array(
			'file'  => self::FILE_LIMIT,
			'total' => self::SURFACE_CLI === $surface ? 0 : $total,
		);
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( Module::FILTER_MEDIA_LIMITS, $limits, $surface );
			if ( is_array( $filtered ) ) {
				$limits = array_replace( $limits, array_intersect_key( $filtered, $limits ) );
			}
		}
		return array(
			'file'  => max( 0, (int) $limits['file'] ),
			'total' => max( 0, (int) $limits['total'] ),
		);
	}

	/** Bytes de un valor como `256M`, `1G` o `65536`. `-1` (sin límite) devuelve -1; vacío, 0. */
	public static function parse_bytes( string $value ): int {
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return 0;
		}
		if ( '-1' === $value ) {
			return -1;
		}
		$number = (float) $value;
		$unit   = substr( $value, -1 );
		if ( 'g' === $unit ) {
			$number *= 1073741824;
		} elseif ( 'm' === $unit ) {
			$number *= 1048576;
		} elseif ( 'k' === $unit ) {
			$number *= 1024;
		}
		return $number >= PHP_INT_MAX ? PHP_INT_MAX : max( 0, (int) $number );
	}

	public function surface(): string {
		return $this->surface;
	}

	/** `embed` o `link`. */
	public function media_format(): string {
		return $this->media;
	}

	public function embeds_media(): bool {
		return self::MEDIA_EMBED === $this->media;
	}

	/** @return string[] */
	public function include(): array {
		return $this->include;
	}

	public function includes( string $what ): bool {
		return in_array( $what, $this->include, true );
	}

	/**
	 * URL del sitio que exporta.
	 *
	 * @param string $key `home`, `site` o `uploads`; vacío devuelve las tres.
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
	 * Los runners de K anotan lo que exportaron, para que las partes exporten sólo eso.
	 *
	 * @param string $kind `post`, `template`, `term`, `attachment`… (`[a-z0-9_-]+`).
	 * @param array  $ids  IDs del sitio que exporta.
	 */
	public function add_exported( string $kind, array $ids ): void {
		if ( 1 !== preg_match( '/^[a-z0-9_-]+$/', $kind ) ) {
			Module::doing_it_wrong( __METHOD__, 'Tipo inválido: ' . Module::describe( $kind ) );
			return;
		}
		$list = isset( $this->exported[ $kind ] ) ? $this->exported[ $kind ] : array();
		foreach ( $ids as $id ) {
			if ( ( is_int( $id ) || is_string( $id ) ) && '' !== $id && ! in_array( $id, $list, true ) ) {
				$list[] = $id;
			}
		}
		$this->exported[ $kind ] = $list;
	}

	/** IDs exportados de un tipo, en el orden en que se anotaron. */
	public function exported( string $kind ): array {
		return isset( $this->exported[ $kind ] ) ? $this->exported[ $kind ] : array();
	}

	/**
	 * Pide un archivo para el kit.
	 *
	 * @param string $path   Ruta dentro de `digitalisimo/`: `media/<sha256>.<ext>` o `files/(shapes|masks)/<slug>.svg`.
	 *                       La construye la parte con datos validados, nunca con una cadena del contenido.
	 * @param string $source Archivo local del sitio. Debe estar dentro de `roots` y fuera de `excluded`.
	 *
	 * @return bool Si se añadió (o ya estaba). Si no, `rejected()` dice por qué y la parte decide qué hacer
	 *              (por ejemplo, dejar el medio como enlace).
	 */
	public function add_file( string $path, string $source ): bool {
		if ( ! Module::is_kit_file( $path ) ) {
			Module::doing_it_wrong( __METHOD__, 'Ruta de archivo del kit inválida: ' . Module::describe( $path ) );
			return $this->reject( $path, 'path' );
		}
		if ( isset( $this->files[ $path ] ) ) {
			return true;
		}
		$real = '' === $source ? false : realpath( $source );
		if ( false === $real || ! is_file( $real ) || ! is_readable( $real ) ) {
			return $this->reject( $path, 'missing' );
		}
		if ( ! $this->allowed( $real ) ) {
			return $this->reject( $path, 'outside' );
		}
		$bytes = filesize( $real );
		if ( false === $bytes ) {
			return $this->reject( $path, 'missing' );
		}
		if ( $this->limits['file'] > 0 && $bytes > $this->limits['file'] ) {
			return $this->reject( $path, 'file-limit', $bytes );
		}
		if ( $this->limits['total'] > 0 && $this->used + $bytes > $this->limits['total'] ) {
			return $this->reject( $path, 'total-limit', $bytes );
		}
		$this->files[ $path ] = array(
			'source' => $real,
			'bytes'  => $bytes,
		);
		$this->used          += $bytes;
		return true;
	}

	public function has_file( string $path ): bool {
		return isset( $this->files[ $path ] );
	}

	/** @return array[] Ruta dentro de `digitalisimo/` => `{ source, bytes }`. */
	public function files(): array {
		return $this->files;
	}

	/** @return array[] `{ path, reason, bytes }`; `reason` es `path`, `missing`, `outside`, `file-limit` o `total-limit`. */
	public function rejected(): array {
		return $this->rejected;
	}

	/** @return int[] `file` y `total` (0 = sin tope), `used` y `left` (-1 = sin tope). */
	public function budget(): array {
		return array(
			'file'  => $this->limits['file'],
			'total' => $this->limits['total'],
			'used'  => $this->used,
			'left'  => $this->limits['total'] > 0 ? max( 0, $this->limits['total'] - $this->used ) : -1,
		);
	}

	/** Suma a `manifest.digitalisimo.not_exported` (por ejemplo, `webhooks` o `drafts`). */
	public function not_exported( string $reason, int $count = 1 ): void {
		if ( 1 !== preg_match( '/^[a-z0-9_-]+$/', $reason ) ) {
			Module::doing_it_wrong( __METHOD__, 'Motivo inválido: ' . Module::describe( $reason ) );
			return;
		}
		$this->not_exported[ $reason ] = ( isset( $this->not_exported[ $reason ] ) ? $this->not_exported[ $reason ] : 0 ) + max( 0, $count );
	}

	/** @return int[] Motivo => número. */
	public function get_not_exported(): array {
		return $this->not_exported;
	}

	private function allowed( string $real ): bool {
		$file = str_replace( '\\', '/', $real );
		foreach ( $this->excluded as $dir ) {
			if ( 0 === strpos( $file, $dir ) ) {
				return false;
			}
		}
		foreach ( $this->roots as $dir ) {
			if ( 0 === strpos( $file, $dir ) ) {
				return true;
			}
		}
		return false;
	}

	private function reject( string $path, string $reason, int $bytes = 0 ): bool {
		$this->tally( 'files.rejected.' . $reason );
		if ( count( $this->rejected ) < self::MAX_REJECTED ) {
			$this->rejected[] = array(
				'path'   => $path,
				'reason' => $reason,
				'bytes'  => $bytes,
			);
		}
		return false;
	}

	/** Carpeta como prefijo comparable: barras normales y barra final. */
	private static function dir_key( string $dir ): string {
		return rtrim( str_replace( '\\', '/', $dir ), '/' ) . '/';
	}
}
