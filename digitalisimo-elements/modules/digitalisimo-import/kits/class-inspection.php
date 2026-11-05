<?php
namespace Digitalisimo\Elements\Import\Kits;

defined( 'ABSPATH' ) || exit;

/**
 * Resultado de `Zip_Guard::inspect()`: un kit que ya pasó el guardián, todavía dentro de su ZIP.
 *
 * No extrae nada: guarda el ZIP que contiene el kit (el subido o, si venía anidado, una copia temporal del ZIP
 * interior), la subcarpeta raíz del kit y las entradas admitidas, con su ruta relativa a esa raíz. Las lecturas
 * comprueban otra vez el tamaño real: un tamaño falso en el directorio central nunca cuela (CV-22).
 */
final class Inspection {
	/** @var string ZIP que contiene el kit. */
	private $zip;

	/** @var string Subcarpeta raíz del kit dentro del ZIP, con barra final, o ''. */
	private $prefix;

	/** @var array[] Ruta relativa => `{ name, index, size, ext }`. */
	private $entries;

	/** @var int Entradas fuera del kit que se ignoran (documentación de un ZIP de marketplace, capturas…). */
	private $ignored;

	/** @var int Bytes descomprimidos declarados del kit. */
	private $bytes;

	/** @var string Copia temporal del ZIP interior, que se borra al terminar; '' si no hubo ZIP anidado. */
	private $temporary;

	/** @var array JSON ya decodificados por el guardián (sólo los pequeños), por ruta. */
	private $decoded = array();

	/** @var \ZipArchive|null */
	private $archive = null;

	public function __construct( string $zip, string $prefix, array $entries, int $ignored, int $bytes, string $temporary = '' ) {
		$this->zip       = $zip;
		$this->prefix    = $prefix;
		$this->entries   = $entries;
		$this->ignored   = $ignored;
		$this->bytes     = $bytes;
		$this->temporary = $temporary;
	}

	public function __destruct() {
		$this->cleanup();
	}

	/** ZIP que contiene el kit. */
	public function zip(): string {
		return $this->zip;
	}

	/** Subcarpeta raíz del kit dentro del ZIP, con barra final, o ''. */
	public function prefix(): string {
		return $this->prefix;
	}

	/** ¿El kit venía dentro de otro ZIP? */
	public function nested(): bool {
		return '' !== $this->temporary;
	}

	/** @return array[] Ruta relativa => `{ name, index, size, ext }`. */
	public function entries(): array {
		return $this->entries;
	}

	/** @return string[] Rutas relativas que empiezan por `$prefix`. */
	public function paths( string $prefix = '' ): array {
		$paths = array();
		foreach ( array_keys( $this->entries ) as $path ) {
			if ( '' === $prefix || 0 === strpos( (string) $path, $prefix ) ) {
				$paths[] = (string) $path;
			}
		}
		return $paths;
	}

	public function has( string $path ): bool {
		return isset( $this->entries[ $path ] );
	}

	public function size( string $path ): int {
		return isset( $this->entries[ $path ] ) ? (int) $this->entries[ $path ]['size'] : 0;
	}

	public function bytes(): int {
		return $this->bytes;
	}

	public function ignored(): int {
		return $this->ignored;
	}

	/**
	 * Contenido de una entrada, con el tamaño real comprobado.
	 *
	 * @throws \RuntimeException Si no existe o su tamaño no coincide con el declarado.
	 */
	public function read( string $path ): string {
		if ( ! isset( $this->entries[ $path ] ) ) {
			throw new \RuntimeException( 'El kit no contiene ' . $path . '.' );
		}
		$entry = $this->entries[ $path ];
		$data  = $this->archive()->getFromIndex( (int) $entry['index'], (int) $entry['size'] + 1 );
		if ( false === $data || strlen( $data ) !== (int) $entry['size'] ) {
			throw new \RuntimeException( 'El tamaño real de ' . $path . ' no coincide con el que declara el ZIP.' );
		}
		return $data;
	}

	/**
	 * JSON de una entrada como array.
	 *
	 * @throws \RuntimeException Si no se puede leer o no es un JSON válido.
	 */
	public function json( string $path ): array {
		if ( isset( $this->decoded[ $path ] ) ) {
			return $this->decoded[ $path ];
		}
		return Zip_Guard::decode( $this->read( $path ), $path );
	}

	/** El guardián guarda aquí lo que ya decodificó, para no hacerlo dos veces (sólo JSON pequeños). */
	public function remember( string $path, array $data ): void {
		if ( strlen( (string) json_encode( $data ) ) <= 1048576 ) {
			$this->decoded[ $path ] = $data;
		}
	}

	/**
	 * Copia una entrada a un archivo, comprobando el tamaño real. Nunca decide la ruta: la recibe ya construida.
	 *
	 * @return string El sha256 de lo escrito.
	 *
	 * @throws \RuntimeException Si no se puede leer o escribir.
	 */
	public function copy_to( string $path, string $target ): string {
		$data = $this->read( $path );
		if ( false === file_put_contents( $target, $data ) ) {
			throw new \RuntimeException( 'No se pudo escribir un archivo preparado del kit.' );
		}
		return hash( 'sha256', $data );
	}

	/** Cierra el ZIP y borra la copia temporal del ZIP interior. */
	public function cleanup(): void {
		if ( null !== $this->archive ) {
			$this->archive->close();
			$this->archive = null;
		}
		if ( '' !== $this->temporary && is_file( $this->temporary ) ) {
			@unlink( $this->temporary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- temporal propio.
		}
		$this->temporary = '';
	}

	private function archive(): \ZipArchive {
		if ( null === $this->archive ) {
			$archive = new \ZipArchive();
			if ( true !== $archive->open( $this->zip ) ) {
				throw new \RuntimeException( 'No se pudo abrir el ZIP del kit.' );
			}
			$this->archive = $archive;
		}
		return $this->archive;
	}
}
