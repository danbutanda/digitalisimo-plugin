<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Resultado de `Normalizer::run()`: el kit limpio, todavía en memoria.
 *
 * - `write_zip()` escribe el ZIP que recibe el importador del núcleo: sólo `json` y `xml`, con las rutas ya
 *   normalizadas. Puede sobrescribir el temporal de la subida (§7.2, regla 3).
 * - `stage()` prepara los medios y los archivos propios en una carpeta que construye K: comprueba el sha256 y el
 *   tipo real de cada uno, y sanea los SVG. Lo que no coincide se descarta y se anota.
 * - `record()` es lo que se guarda en la sesión de K: formato, origen, pedidos y análisis.
 */
final class Normalized {
	/** Tipos MIME reales admitidos por extensión (paso 7). */
	const MIME = array(
		'jpg'   => array( 'image/jpeg' ),
		'jpeg'  => array( 'image/jpeg' ),
		'png'   => array( 'image/png' ),
		'gif'   => array( 'image/gif' ),
		'webp'  => array( 'image/webp' ),
		'avif'  => array( 'image/avif', 'application/octet-stream' ),
		'svg'   => array( 'image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html' ),
		'mp4'   => array( 'video/mp4', 'application/mp4' ),
		'webm'  => array( 'video/webm', 'audio/webm' ),
		'mp3'   => array( 'audio/mpeg', 'audio/mp3' ),
		'ogg'   => array( 'audio/ogg', 'video/ogg', 'application/ogg' ),
		'wav'   => array( 'audio/wav', 'audio/x-wav', 'audio/wave' ),
		'woff'  => array( 'font/woff', 'application/font-woff', 'application/octet-stream' ),
		'woff2' => array( 'font/woff2', 'application/font-woff2', 'application/octet-stream' ),
		'ttf'   => array( 'font/ttf', 'font/sfnt', 'application/x-font-ttf', 'application/font-sfnt', 'application/octet-stream' ),
		'otf'   => array( 'font/otf', 'font/sfnt', 'application/vnd.ms-opentype', 'application/font-sfnt', 'application/octet-stream' ),
		'csv'   => array( 'text/csv', 'text/plain', 'application/csv' ),
		'pdf'   => array( 'application/pdf' ),
		'json'  => array( 'application/json', 'text/plain' ),
	);

	/** @var Inspection */
	private $inspection;

	/** @var array[] Ruta => JSON decodificado. */
	private $files;

	/** @var string */
	private $format;

	/** @var string[] */
	private $origin;

	/** @var array[] URL original => `{ file, sha256, mime, bytes }`. */
	private $media;

	/** @var array[] `shapes/<slug>.svg` => `{ sha256, bytes }`. */
	private $shapes;

	/** @var array */
	private $requests;

	/** @var array */
	private $analysis;

	public function __construct( Inspection $inspection, array $files, string $format, array $origin, array $media, array $shapes, array $requests, array $analysis ) {
		$this->inspection = $inspection;
		$this->files      = $files;
		$this->format     = $format;
		$this->origin     = $origin;
		$this->media      = $media;
		$this->shapes     = $shapes;
		$this->requests   = $requests;
		$this->analysis   = $analysis;
	}

	public function inspection(): Inspection {
		return $this->inspection;
	}

	/** `3.0`, `2.x` o `envato`. */
	public function format(): string {
		return $this->format;
	}

	/** ¿Es un kit convertido (Envato)? Entra siempre en «agregar» (D1). */
	public function converted(): bool {
		return Normalizer::FORMAT_ENVATO === $this->format;
	}

	/** @return string[] `format`, `kit`, `home`, `site` y `uploads`. */
	public function origin(): array {
		return $this->origin;
	}

	public function kit(): string {
		return $this->origin['kit'];
	}

	public function manifest(): array {
		return $this->files['manifest.json'];
	}

	/** JSON normalizado de una ruta del kit, o null. */
	public function json( string $path ): ?array {
		return isset( $this->files[ $path ] ) ? $this->files[ $path ] : null;
	}

	/** @return string[] Rutas JSON del kit. */
	public function paths(): array {
		return array_keys( $this->files );
	}

	public function media(): array {
		return $this->media;
	}

	public function shapes(): array {
		return $this->shapes;
	}

	public function requests(): array {
		return $this->requests;
	}

	public function analysis(): array {
		return $this->analysis;
	}

	/**
	 * Escribe el ZIP limpio que recibe el importador del núcleo: sólo `json` y `xml`.
	 *
	 * @param string $target Ruta construida por K o el temporal de la subida (se sobrescribe).
	 *
	 * @throws \RuntimeException Si no se puede escribir.
	 */
	public function write_zip( string $target ): void {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new \RuntimeException( 'No se pudo preparar el kit limpio.' );
		}
		foreach ( $this->files as $path => $data ) {
			$json = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE );
			if ( ! is_string( $json ) || ! $zip->addFromString( (string) $path, $json ) ) {
				$zip->close();
				throw new \RuntimeException( 'No se pudo preparar el kit limpio.' );
			}
		}
		foreach ( $this->inspection->entries() as $path => $entry ) {
			if ( 'xml' === $entry['kind'] && ! $zip->addFromString( (string) $path, $this->inspection->read( (string) $path ) ) ) {
				$zip->close();
				throw new \RuntimeException( 'No se pudo preparar el kit limpio.' );
			}
		}
		if ( ! $zip->close() ) {
			throw new \RuntimeException( 'No se pudo preparar el kit limpio.' );
		}
	}

	/**
	 * Prepara medios y archivos propios en `$dir` (paso 7): `media/<sha256>.<ext>`, `files/(shapes|masks)/<slug>.svg`,
	 * `media.json` y `files.json` con lo que pasó la comprobación.
	 *
	 * @param string $dir Carpeta real construida por K (`Session::tmp_dir()`), con barra final o sin ella.
	 *
	 * @return array Resumen: `embedded`, `svg_sanitized`, `rejected` (motivo => número).
	 */
	public function stage( string $dir ): array {
		$real    = realpath( $dir );
		$dir     = false === $real ? '' : rtrim( str_replace( '\\', '/', $real ), '/' ) . '/';
		$summary = array(
			'embedded'      => 0,
			'bytes'         => 0,
			'svg_sanitized' => 0,
			'rejected'      => array(),
		);
		$reject  = function ( $reason ) use ( &$summary ) {
			$summary['rejected'][ $reason ] = ( $summary['rejected'][ $reason ] ?? 0 ) + 1;
		};
		$media   = array();
		foreach ( $this->media as $url => $item ) {
			$relative = 'media/' . $item['file'];
			$result   = $this->stage_file( Module::KIT_FOLDER . $relative, $dir, $relative, $item['sha256'] );
			if ( is_string( $result ) ) {
				$reject( $result );
				continue;
			}
			$item['bytes']  = $result['bytes'];
			$item['mime']   = $result['mime'];
			$media[ $url ]  = $item;
			$summary['bytes'] += $result['bytes'];
			++$summary['embedded'];
			if ( $result['sanitized'] ) {
				++$summary['svg_sanitized'];
			}
		}
		$files = array();
		foreach ( $this->shapes as $key => $item ) {
			$relative = 'files/' . $key;
			$result   = $this->stage_file( Module::KIT_FOLDER . $relative, $dir, $relative, $item['sha256'] );
			if ( is_string( $result ) ) {
				$reject( $result );
				continue;
			}
			$files[ $key ] = array(
				'sha256' => $item['sha256'],
				'bytes'  => $result['bytes'],
			);
		}
		$this->media = $media;
		if ( '' !== $dir ) {
			file_put_contents( $dir . 'media.json', (string) json_encode( $media, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			file_put_contents( $dir . 'files.json', (string) json_encode( $files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
		return $summary;
	}

	/**
	 * Lo que se guarda de la normalización en la sesión de K (§5.3).
	 */
	public function record(): array {
		return array(
			'origin'   => $this->origin,
			'format'   => $this->format,
			'requests' => $this->requests,
			'analysis' => $this->analysis,
		);
	}

	/**
	 * Copia un archivo del kit a la carpeta preparada, con su sha256 y su tipo real comprobados.
	 *
	 * @return array|string Datos del archivo o el motivo del rechazo.
	 */
	private function stage_file( string $source, string $dir, string $relative, string $sha256 ) {
		if ( '' === $dir || ! Module::is_kit_file( $relative ) || ! $this->inspection->has( $source ) ) {
			return 'path';
		}
		$target = $dir . $relative;
		$folder = dirname( $target );
		if ( ! is_dir( $folder ) && ! mkdir( $folder, 0755, true ) && ! is_dir( $folder ) ) {
			return 'write';
		}
		$real = realpath( $folder );
		if ( false === $real || 0 !== strpos( rtrim( str_replace( '\\', '/', $real ), '/' ) . '/', $dir ) ) {
			return 'path';
		}
		try {
			$data = $this->inspection->read( $source );
		} catch ( \RuntimeException $error ) {
			return 'size';
		}
		if ( ! hash_equals( $sha256, hash( 'sha256', $data ) ) ) {
			return 'sha256';
		}
		$ext       = strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) );
		$mime      = self::mime( $data );
		$sanitized = false;
		if ( 'svg' === $ext ) {
			$clean = self::sanitize_svg( $data );
			if ( null === $clean ) {
				return 'svg';
			}
			$sanitized = $clean !== $data;
			$data      = $clean;
			$mime      = 'image/svg+xml';
		} elseif ( '' !== $mime && isset( self::MIME[ $ext ] ) && ! in_array( $mime, self::MIME[ $ext ], true ) ) {
			return 'mime';
		}
		if ( false === file_put_contents( $target, $data ) ) {
			return 'write';
		}
		return array(
			'bytes'     => strlen( $data ),
			'mime'      => '' !== $mime ? $mime : ( isset( self::MIME[ $ext ] ) ? self::MIME[ $ext ][0] : 'application/octet-stream' ),
			'sanitized' => $sanitized,
		);
	}

	/** Tipo MIME real del contenido, o '' si no se puede saber. */
	private static function mime( string $data ): string {
		if ( ! class_exists( '\finfo' ) ) {
			return '';
		}
		$mime = ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $data );
		return is_string( $mime ) ? strtolower( $mime ) : '';
	}

	/**
	 * SVG saneado con el saneador del núcleo; sin él, se rechaza todo SVG con scripts, manejadores o referencias
	 * externas. Null si no es un SVG utilizable.
	 */
	public static function sanitize_svg( string $svg ): ?string {
		if ( class_exists( '\Elementor\Core\Utils\Svg\Svg_Sanitizer' ) ) {
			$clean = ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize( $svg );
			return is_string( $clean ) && '' !== $clean ? $clean : null;
		}
		if ( false === stripos( $svg, '<svg' ) ) {
			return null;
		}
		$clean = (string) preg_replace(
			array( '#<script\b[^>]*>.*?</script\s*>#is', '#<script\b[^>]*/>#is', '#<foreignObject\b.*?</foreignObject\s*>#is', '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '#(?:xlink:)?href\s*=\s*("|\')\s*(?:javascript|data):[^"\']*\1#i', '#<!(?:DOCTYPE|ENTITY)[^>]*>#i' ),
			'',
			$svg
		);
		return '' !== $clean ? $clean : null;
	}
}
