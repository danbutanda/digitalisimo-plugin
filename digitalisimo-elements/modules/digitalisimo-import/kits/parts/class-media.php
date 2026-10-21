<?php
namespace Digitalisimo\Elements\Import\Kits\Parts;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\References;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Export\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `media`: los medios dentro del kit (D6, B6, B19, CC-23).
 *
 * `digitalisimo/media.json` es `{ "<url original>": { file, sha256, mime, bytes, id } }` y cada archivo viaja en
 * `digitalisimo/media/<sha256>.<ext>`. Con él, la importación no pide nada al sitio de origen: el proxy de K sirve
 * esas URL desde el paquete al importador del núcleo (que anota `_elementor_source_image_hash`), y esta parte crea
 * los adjuntos que el núcleo no importa (controles URL, CSS, metas de término).
 *
 * Al exportar sólo se incrustan **adjuntos del sitio actual** (CR-2): `attachment_url_to_postid()` →
 * `get_attached_file()` → el archivo dentro de las carpetas del contexto (`Export_Context::add_file()` excluye otros
 * sitios de la red, `woocommerce_uploads` y los temporales), con extensión y MIME permitidos y dentro de los topes.
 * Nada se descarga por HTTP. Se recogen:
 * - los controles de medios y galerías (`{ url, id }` a cualquier profundidad de los ajustes);
 * - los controles URL con un archivo bajo `uploads` del origen (`media-url`, CC-6);
 * - los `url(…)` del CSS personalizado;
 * - la miniatura de cada documento y de cada entrada del WXR en tamaño completo (CC-23) y los adjuntos del WXR;
 * - los medios de los ajustes del kit (fondos, B19) y de los valores por defecto, salvo la identidad del sitio;
 * - los adjuntos de las metas de término con referencia `attachment` (imagen de categoría, B18).
 *
 * Al importar, cada medio se busca por su huella del núcleo (en cualquier estado) y, si no está, se crea desde el
 * archivo preparado. Publica los mapas `attachment` (URL vieja e ID viejo → ID nuevo) y `media_url` (URL vieja →
 * nueva). Un SVG con la subida sin filtrar apagada no se importa (D15).
 */
final class Media implements Part {
	const KEY     = 'media';
	const VERSION = 1;

	const HASH_META    = '_elementor_source_image_hash';
	const SESSION_META = '_elementor_import_session_id';

	/** Extensiones de adjuntos que pueden viajar (las de `Module::MEDIA_FILE_PATTERN` salvo `css` y `eot`). */
	const EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'mp4', 'webm', 'mp3', 'ogg', 'wav', 'woff', 'woff2', 'ttf', 'otf', 'csv', 'pdf', 'json' );

	/**
	 * Carpeta de los conjuntos de iconos personalizados, relativa a `uploads`. Sus archivos no son adjuntos: al subir
	 * un conjunto, el constructor extrae el ZIP en su carpeta. Viajan para que el importador de iconos los pida por
	 * su URL y el proxy los sirva desde el paquete; esta parte nunca crea adjuntos con ellos.
	 */
	const ICON_SETS = 'elementor/custom-icons/';

	/** Extensiones de un conjunto de iconos (las que reconstruye el importador de iconos). */
	const ICON_SET_EXTENSIONS = array( 'css', 'json', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg' );

	/**
	 * Tipo MIME de lo que puede viajar cuando WordPress no lo conoce (fuentes y SVG no están en la lista de subida
	 * del núcleo, pero los adjuntos del sitio sí los tienen: los subió el constructor con su propio permiso).
	 */
	const MIMES = array(
		'svg'   => 'image/svg+xml',
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
		'eot'   => 'application/vnd.ms-fontobject',
		'json'  => 'application/json',
		'css'   => 'text/css',
		'csv'   => 'text/csv',
		'avif'  => 'image/avif',
		'webp'  => 'image/webp',
	);

	/** Ajustes del kit que son la identidad del sitio: no viajan (§4.1.7). */
	const IDENTITY = array( 'site_logo', 'site_favicon' );

	/** Segundos de importación de medios por petición antes de dejar el resto para después (A13). */
	const TIME_BUDGET = 15;

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
		$uploads = $context->origin( 'uploads' );
		if ( '' === $uploads ) {
			return null;
		}
		$urls = self::collect_urls( $context );
		if ( ! $urls ) {
			return null;
		}
		$context->add_exported( 'media_url', array_keys( $urls ) );
		$out   = array();
		$bytes = 0;
		foreach ( $urls as $url => $hint ) {
			if ( ! $context->embeds_media() ) {
				$context->tally( 'media.linked' );
				continue;
			}
			$file = self::local_file( $url, $uploads, (int) $hint );
			if ( null === $file ) {
				$context->tally( 'media.linked' );
				$context->report( 'media.not-local', array( 'url' => $url ) );
				continue;
			}
			$sha  = (string) hash_file( 'sha256', $file['path'] );
			$name = $sha . '.' . $file['ext'];
			if ( ! $context->add_file( 'media/' . $name, $file['path'] ) ) {
				$context->tally( 'media.linked' );
				continue;
			}
			$size        = (int) filesize( $file['path'] );
			$out[ $url ] = array(
				'file'   => $name,
				'sha256' => $sha,
				'mime'   => $file['mime'],
				'bytes'  => $size,
				'id'     => $file['id'],
			);
			// Título y texto alternativo del adjunto: sin ellos, el destino los rellenaba con el nombre del archivo.
			if ( $file['id'] > 0 ) {
				$post = function_exists( 'get_post' ) ? get_post( $file['id'] ) : null;
				$alt  = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $file['id'], '_wp_attachment_image_alt', true ) : '';
				if ( is_object( $post ) && '' !== (string) $post->post_title ) {
					$out[ $url ]['title'] = self::text( (string) $post->post_title );
				}
				if ( '' !== $alt ) {
					$out[ $url ]['alt'] = self::text( $alt );
				}
			}
			$context->tally( 'media.embedded' );
			$bytes += $size;
		}
		if ( $bytes > 0 ) {
			$context->tally( 'media.bytes', $bytes );
		}
		ksort( $out, SORT_STRING );
		return $out ? $out : null;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$summary = array(
			'reused'   => 0,
			'imported' => 0,
			'failed'   => 0,
			'skipped'  => 0,
			'deferred' => 0,
		);
		$by_url  = array();
		$by_id   = array();
		$urls    = array();
		$created = array();
		$start   = microtime( true );
		foreach ( $data as $url => $entry ) {
			if ( ! is_string( $url ) || '' === $url || ! is_array( $entry ) || ! isset( $entry['file'] ) || ! is_string( $entry['file'] ) || 1 !== preg_match( Module::MEDIA_FILE_PATTERN, $entry['file'] ) ) {
				++$summary['failed'];
				$context->report( 'media.invalid', array( 'url' => is_string( $url ) ? $url : '' ), Part_Context::WARNING );
				continue;
			}
			// Los archivos de un conjunto de iconos sólo los pide el importador de iconos (al proxy): no son adjuntos.
			if ( self::is_icon_set_url( $url, $context->origin( 'uploads' ) ) || in_array( strtolower( (string) pathinfo( $entry['file'], PATHINFO_EXTENSION ) ), array( 'css', 'eot' ), true ) ) {
				++$summary['skipped'];
				continue;
			}
			// Lo que ya importó el núcleo o el WXR (mapa `attachment` del runner) o un medio con la misma huella.
			$id = (int) $context->mapped( 'attachment', $url, 0 );
			$id = $id > 0 && function_exists( 'get_post_type' ) && 'attachment' === get_post_type( $id ) ? $id : self::find( $url );
			if ( $id > 0 ) {
				++$summary['reused'];
			} else {
				if ( microtime( true ) - $start > self::TIME_BUDGET ) {
					++$summary['deferred'];
					$context->report( 'media.deferred', array( 'url' => $url ), Part_Context::WARNING );
					continue;
				}
				$id = self::import_file( $url, $entry, $context );
				if ( $id <= 0 ) {
					++$summary[ 0 === $id ? 'failed' : 'skipped' ];
					continue;
				}
				$created[] = $id;
				++$summary['imported'];
			}
			$by_url[ $url ] = $id;
			self::describe( $id, $entry, $context );
			// El ID de origen apunta al adjunto completo, no a un tamaño intermedio del mismo adjunto.
			if ( isset( $entry['id'] ) && is_int( $entry['id'] ) && $entry['id'] > 0 && ( ! isset( $by_id[ $entry['id'] ] ) || 1 !== preg_match( '/-\d+x\d+\.[A-Za-z0-9]+$/', $url ) ) ) {
				$by_id[ $entry['id'] ] = $id;
			}
			$new = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : '';
			if ( is_string( $new ) && '' !== $new ) {
				$urls[ $url ] = $new;
			}
		}
		$context->add_map( 'attachment', $by_url + $by_id );
		$context->add_map( 'media_url', $urls );
		$context->store( array( 'created' => $created ) );
		$context->tally( 'media.reused', $summary['reused'] );
		$context->tally( 'media.imported', $summary['imported'] );
		return $summary;
	}

	/**
	 * Título y texto alternativo del kit en un adjunto que creó esta importación (el núcleo, el WXR o esta parte),
	 * sin pisar lo que ya tenga: los adjuntos que ya estaban en el sitio no se tocan.
	 */
	private static function describe( int $id, array $entry, Import_Context $context ): void {
		if ( $id <= 0 || ! $context->is_new_post( $id ) ) {
			return;
		}
		$title = isset( $entry['title'] ) && is_string( $entry['title'] ) ? self::text( $entry['title'] ) : '';
		$alt   = isset( $entry['alt'] ) && is_string( $entry['alt'] ) ? self::text( $entry['alt'] ) : '';
		$post  = get_post( $id );
		if ( '' !== $title && is_object( $post ) && $title !== (string) $post->post_title ) {
			$file = sanitize_title( (string) preg_replace( '/-\d+$/', '', (string) pathinfo( (string) get_attached_file( $id ), PATHINFO_FILENAME ) ) );
			// Sólo si el título es el que pone quien importa (el nombre del archivo), no uno que alguien escribió.
			if ( '' === (string) $post->post_title || ( '' !== $file && 0 === strpos( sanitize_title( (string) $post->post_title ), $file ) ) ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => wp_slash( $title ),
					)
				);
			}
		}
		if ( '' !== $alt && '' === (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
		}
	}

	/** Texto plano corto (título o alt de un adjunto). */
	private static function text( string $value ): string {
		$value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 200 ) : substr( $value, 0, 200 );
	}

	/** Los adjuntos creados llevan la sesión: la reversión del núcleo los borra (V-29). Nada más que hacer. */
	public function revert( array $stored, Revert_Context $context ): void {
	}

	// ── Exportación ─────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * URL de medios que usa lo exportado, con el ID de adjunto cuando se conoce (0 si no).
	 *
	 * @return int[] URL => ID de adjunto del origen.
	 */
	public static function collect_urls( Export_Context $context ): array {
		$uploads = $context->origin( 'uploads' );
		$urls    = array();
		$add     = function ( $url, $id = 0 ) use ( &$urls, $uploads ) {
			if ( ! is_string( $url ) || '' === $url || ! References::under( $url, $uploads ) || false !== strpos( $url, '/' . Files::FOLDER . '/' ) ) {
				return;
			}
			$url = (string) preg_replace( '/[?#].*$/s', '', $url );
			if ( ! isset( $urls[ $url ] ) || ( 0 === $urls[ $url ] && (int) $id > 0 ) ) {
				$urls[ $url ] = max( 0, (int) $id );
			}
		};
		foreach ( array( 'template', 'post' ) as $kind ) {
			foreach ( $context->exported( $kind ) as $id ) {
				self::scan_elements( Documents::elements_of( (int) $id ), $uploads, $add );
				$settings = function_exists( 'get_post_meta' ) ? get_post_meta( (int) $id, '_elementor_page_settings', true ) : array();
				if ( is_array( $settings ) ) {
					self::scan_settings( $settings, $add );
				}
				$add( Documents::thumbnail( (int) $id ), function_exists( 'get_post_thumbnail_id' ) ? (int) get_post_thumbnail_id( (int) $id ) : 0 );
			}
		}
		foreach ( $context->exported( 'attachment' ) as $id ) {
			$add( function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( (int) $id ) : '', (int) $id );
		}
		// URL que anunciaron los exportadores de fuentes e iconos sin adjunto (archivos de un conjunto de iconos).
		foreach ( $context->exported( 'media_url' ) as $url ) {
			$add( $url, 0 );
		}
		if ( $context->includes( 'settings' ) ) {
			$kit = self::active_kit_id();
			if ( $kit > 0 ) {
				$settings = get_post_meta( $kit, '_elementor_page_settings', true );
				if ( is_array( $settings ) ) {
					self::scan_settings( array_diff_key( $settings, array_flip( self::IDENTITY ) ), $add );
				}
			}
			foreach ( Element_Defaults::current_defaults() as $settings ) {
				if ( is_array( $settings ) ) {
					self::scan_settings( $settings, $add );
				}
			}
		}
		foreach ( Term_Meta::attachment_ids( $context ) as $attachment ) {
			$add( function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $attachment ) : '', $attachment );
		}
		ksort( $urls, SORT_STRING );
		return $urls;
	}

	/**
	 * Archivo local de una URL del sitio actual: sólo si es un adjunto (también un tamaño intermedio de uno) con
	 * extensión y MIME permitidos.
	 *
	 * @return array{path: string, ext: string, mime: string, id: int}|null
	 */
	public static function local_file( string $url, string $uploads, int $hint = 0 ): ?array {
		if ( ! References::under( $url, $uploads ) ) {
			return null;
		}
		$icon_file = self::icon_set_file( $url, $uploads );
		if ( false !== $icon_file ) {
			return $icon_file;
		}
		if ( ! function_exists( 'attachment_url_to_postid' ) ) {
			return null;
		}
		$id    = (int) attachment_url_to_postid( $url );
		$sized = false;
		if ( $id <= 0 && 1 === preg_match( '/^(.+)-\d+x\d+(\.[A-Za-z0-9]+)$/', $url, $match ) ) {
			$id    = (int) attachment_url_to_postid( $match[1] . $match[2] );
			$sized = $id > 0;
		}
		if ( $id <= 0 || ( function_exists( 'get_post_type' ) && 'attachment' !== get_post_type( $id ) ) ) {
			return null;
		}
		$attached = get_attached_file( $id );
		if ( ! is_string( $attached ) || '' === $attached ) {
			return null;
		}
		$path = $sized ? dirname( $attached ) . '/' . basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) : $attached;
		$real = realpath( $path );
		if ( false === $real || ! is_file( $real ) ) {
			return null;
		}
		$ext = strtolower( (string) pathinfo( $real, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::EXTENSIONS, true ) ) {
			return null;
		}
		$mime = self::mime( basename( $real ), $ext );
		if ( '' === $mime ) {
			return null;
		}
		return array(
			'path' => $real,
			'ext'  => $ext,
			'mime' => $mime,
			'id'   => $id,
		);
	}

	/**
	 * Archivo de un conjunto de iconos del sitio actual (`uploads/elementor/custom-icons/<conjunto>/…`), sin adjunto.
	 *
	 * @return array|null|false El archivo; null si la URL es de esa carpeta pero no vale; false si no es de esa carpeta.
	 */
	public static function icon_set_file( string $url, string $uploads ) {
		$relative = Remapper::after_prefix( (string) preg_replace( '/[?#].*$/s', '', $url ), rtrim( $uploads, '/' ) . '/' . self::ICON_SETS );
		if ( null === $relative ) {
			return false;
		}
		$relative = ltrim( rawurldecode( $relative ), '/' );
		if ( 1 !== preg_match( '#^[A-Za-z0-9 ._-]+/(?:[A-Za-z0-9 ._-]+/)?[A-Za-z0-9 ._-]+$#', $relative ) || false !== strpos( $relative, '..' ) || ! function_exists( 'wp_upload_dir' ) ) {
			return null;
		}
		$ext = strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::ICON_SET_EXTENSIONS, true ) ) {
			return null;
		}
		$dir = wp_upload_dir( null, false );
		if ( ! empty( $dir['error'] ) || empty( $dir['basedir'] ) ) {
			return null;
		}
		$root = realpath( rtrim( (string) $dir['basedir'], '/\\' ) . '/' . self::ICON_SETS );
		$real = realpath( rtrim( (string) $dir['basedir'], '/\\' ) . '/' . self::ICON_SETS . $relative );
		if ( false === $root || false === $real || ! is_file( $real ) || 0 !== strpos( str_replace( '\\', '/', $real ), rtrim( str_replace( '\\', '/', $root ), '/' ) . '/' ) ) {
			return null;
		}
		return array(
			'path' => $real,
			'ext'  => $ext,
			'mime' => self::mime( basename( $real ), $ext ),
			'id'   => 0,
		);
	}

	/** ¿Es la URL de un archivo de conjunto de iconos (los sirve el proxy; nunca son adjuntos)? */
	public static function is_icon_set_url( string $url, string $uploads ): bool {
		return '' !== $uploads && null !== Remapper::after_prefix( $url, rtrim( $uploads, '/' ) . '/' . self::ICON_SETS );
	}

	/** Tipo MIME de un archivo: el de WordPress o, si no lo conoce, el de `MIMES`; '' si no es de los que viajan. */
	private static function mime( string $name, string $ext ): string {
		$type = function_exists( 'wp_check_filetype' ) ? wp_check_filetype( $name ) : array();
		if ( ! empty( $type['type'] ) ) {
			return (string) $type['type'];
		}
		return isset( self::MIMES[ $ext ] ) ? self::MIMES[ $ext ] : '';
	}

	/** Medios de un árbol: ajustes de cada elemento (también la carga de un marcador) y sus controles URL. */
	private static function scan_elements( array $elements, string $uploads, callable $add ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			self::scan_settings( $settings, $add );
			$type = isset( $element['elType'] ) && 'widget' === $element['elType'] ? ( isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '' ) : ( isset( $element['elType'] ) ? (string) $element['elType'] : '' );
			if ( '' !== $type ) {
				foreach ( References::matching( 'element', $type ) as $ref ) {
					if ( 'url' !== $ref['kind'] && 'html' !== $ref['kind'] ) {
						continue;
					}
					foreach ( self::values_at( $settings, References::split_path( $ref['path'] ) ) as $value ) {
						if ( ! is_string( $value ) ) {
							continue;
						}
						if ( 'html' === $ref['kind'] ) {
							// Imágenes y enlaces a archivos de `uploads` dentro de un texto enriquecido.
							preg_match_all( '/\b(?:href|src)\s*=\s*(["\'])([^"\'<>]+)\1/i', $value, $found );
							foreach ( $found[2] as $url ) {
								if ( References::under( $url, $uploads ) ) {
									$add( $url, 0 );
								}
							}
						} elseif ( 'media-url' === References::kind_for( $ref, $value, $uploads ) ) {
							$add( $value, 0 );
						}
					}
				}
			}
			if ( isset( $settings['digitalisimo_unconverted']['element'] ) && is_array( $settings['digitalisimo_unconverted']['element'] ) ) {
				self::scan_elements( array( $settings['digitalisimo_unconverted']['element'] ), $uploads, $add );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::scan_elements( $element['elements'], $uploads, $add );
			}
		}
	}

	/** `{ url, id }` a cualquier profundidad y los `url(…)` de `custom_css`. */
	private static function scan_settings( array $settings, callable $add ): void {
		foreach ( $settings as $key => $value ) {
			if ( 'digitalisimo_unconverted' === $key ) {
				continue;
			}
			if ( 'custom_css' === $key && is_string( $value ) && preg_match_all( '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', $value, $matches ) ) {
				foreach ( $matches[2] as $url ) {
					$add( trim( $url ), 0 );
				}
				continue;
			}
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( isset( $value['url'] ) && is_string( $value['url'] ) && array_key_exists( 'id', $value ) ) {
				$add( $value['url'], is_numeric( $value['id'] ) ? (int) $value['id'] : 0 );
			}
			self::scan_settings( $value, $add );
		}
	}

	/** Valores en una ruta con `*`. */
	private static function values_at( $node, array $segments ): array {
		if ( ! $segments ) {
			return array( $node );
		}
		if ( ! is_array( $node ) ) {
			return array();
		}
		$key = array_shift( $segments );
		if ( '*' === $key ) {
			$out = array();
			foreach ( $node as $item ) {
				$out = array_merge( $out, self::values_at( $item, $segments ) );
			}
			return $out;
		}
		return array_key_exists( $key, $node ) ? self::values_at( $node[ $key ], $segments ) : array();
	}

	private static function active_kit_id(): int {
		if ( class_exists( '\Elementor\Plugin', false ) && ! empty( \Elementor\Plugin::$instance->kits_manager ) ) {
			return (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
		}
		return function_exists( 'get_option' ) ? (int) get_option( 'elementor_active_kit' ) : 0;
	}

	// ── Importación ─────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Miniatura de un documento del kit que ya está en el sitio con la huella del núcleo (`_elementor_source_image_hash`):
	 * `Document::import()` la pide sin ID y la volvería a descargar en cada reimportación (A8). Devuelve los ajustes
	 * sin la miniatura y el adjunto que hay que asignar después, o los mismos ajustes y 0.
	 *
	 * @param array $settings Entrada del manifiesto (`import_settings`).
	 *
	 * @return array{0: array, 1: int}
	 */
	public static function reuse_thumbnail( array $settings ): array {
		$url = isset( $settings['thumbnail'] ) && is_string( $settings['thumbnail'] ) ? $settings['thumbnail'] : '';
		$id  = '' === $url ? 0 : self::find( $url );
		if ( $id <= 0 ) {
			return array( $settings, 0 );
		}
		$settings['thumbnail'] = false;
		return array( $settings, $id );
	}

	/** Asigna la miniatura reutilizada a lo creado (`reuse_thumbnail()`). */
	public static function set_thumbnail( int $post_id, int $attachment_id ): void {
		if ( $post_id > 0 && $attachment_id > 0 && function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/** Adjunto ya importado desde esa URL (huella del núcleo), en cualquier estado, o 0. */
	public static function find( string $url ): int {
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => self::HASH_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => sha1( $url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return is_array( $ids ) && $ids ? (int) reset( $ids ) : 0;
	}

	/** Crea el adjunto desde el archivo preparado (sin HTTP): su ID, 0 si falla o -1 si se omite a propósito. */
	private static function import_file( string $url, array $entry, Import_Context $context ): int {
		$staged = $context->staged_path( 'media/' . $entry['file'] );
		if ( null === $staged ) {
			$context->report( 'media.missing', array( 'url' => $url ), Part_Context::WARNING );
			return 0;
		}
		$content = (string) file_get_contents( $staged ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$sha     = substr( $entry['file'], 0, 64 );
		$ext     = strtolower( (string) pathinfo( $entry['file'], PATHINFO_EXTENSION ) );
		// Un SVG llega a la carpeta preparada ya saneado por el normalizador (que comprobó antes su sha256), y aquí
		// se vuelve a sanear; cualquier otro archivo debe ser exactamente el del kit.
		if ( 'svg' !== $ext && ! hash_equals( $sha, hash( 'sha256', $content ) ) ) {
			$context->report( 'media.hash', array( 'url' => $url ), Part_Context::WARNING );
			return 0;
		}
		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name || strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) !== $ext ) {
			$name = $entry['file'];
		}
		$name = function_exists( 'sanitize_file_name' ) ? sanitize_file_name( $name ) : $name;
		$type = wp_check_filetype( $name );
		if ( empty( $type['ext'] ) || empty( $type['type'] ) ) {
			$context->report(
				'media.blocked-type',
				array(
					'url' => $url,
					'ext' => $ext,
				),
				Part_Context::WARNING
			);
			return -1;
		}
		if ( 'svg' === $ext ) {
			if ( ! self::unfiltered_uploads() ) {
				$context->report( 'media.svg-skipped', array( 'url' => $url ), Part_Context::WARNING );
				return -1;
			}
			$clean = self::sanitize_svg( $content );
			if ( null === $clean ) {
				$context->report( 'media.svg-invalid', array( 'url' => $url ), Part_Context::WARNING );
				return 0;
			}
			if ( $clean !== $content ) {
				$context->report( 'media.svg-sanitized', array( 'url' => $url ) );
			}
			$content = $clean;
		}
		$upload = wp_upload_bits( $name, null, $content );
		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			$context->report( 'media.write', array( 'url' => $url ), Part_Context::ERROR );
			return 0;
		}
		$id = wp_insert_attachment(
			array(
				'post_title'     => (string) pathinfo( $name, PATHINFO_FILENAME ),
				'post_mime_type' => (string) $type['type'],
				'post_status'    => 'inherit',
				'guid'           => isset( $upload['url'] ) ? (string) $upload['url'] : '',
			),
			$upload['file']
		);
		if ( ! is_int( $id ) || $id <= 0 ) {
			$context->report( 'media.write', array( 'url' => $url ), Part_Context::ERROR );
			return 0;
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/image.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		}
		update_post_meta( $id, self::HASH_META, sha1( $url ) );
		update_post_meta( $id, self::SESSION_META, $context->session() );
		if ( '' !== $context->kit() ) {
			Origin::write( $id, Origin::record( $context->kit(), 'attachment', isset( $entry['id'] ) && is_int( $entry['id'] ) && $entry['id'] > 0 ? $entry['id'] : sha1( $url ), (string) $entry['file'], $context->session() ) );
		}
		if ( function_exists( 'apply_filters' ) ) {
			apply_filters( 'elementor/template_library/import_images/new_attachment', $id );
		}
		return $id;
	}

	private static function unfiltered_uploads(): bool {
		$class = '\Elementor\Core\Files\Uploads_Manager';
		return class_exists( $class ) && method_exists( $class, 'are_unfiltered_uploads_enabled' ) && (bool) call_user_func( array( $class, 'are_unfiltered_uploads_enabled' ) );
	}

	private static function sanitize_svg( string $content ): ?string {
		$class = '\Elementor\Core\Utils\Svg\Svg_Sanitizer';
		if ( ! class_exists( $class ) ) {
			return null;
		}
		$clean = ( new $class() )->sanitize( $content );
		return is_string( $clean ) && false !== stripos( $clean, '<svg' ) ? $clean : null;
	}
}
