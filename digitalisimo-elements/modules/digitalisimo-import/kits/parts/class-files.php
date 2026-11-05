<?php
namespace Digitalisimo\Elements\Import\Kits\Parts;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Export\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `files`: los separadores propios y las máscaras de `uploads/digitalisimo-elements/` (B4, B5).
 *
 * El contenido sólo guarda el nombre (`digi-<slug>`) o la URL del archivo; el archivo vive en los archivos de cada
 * sitio. Al exportar viajan sólo los usados, con su `-negative` si existe. Al importar:
 * - nada del kit decide la ruta: el destino lo construye K con el slug validado y se comprueba con `realpath`
 *   (CR-3);
 * - se escriben saneados por la vía de K, sin pasar por la biblioteca de medios (D15);
 * - un archivo igual se reutiliza; un choque con otro contenido da `<slug>-k<6 hex>` (hex del contenido, estable
 *   entre reimportaciones) y se publica en los mapas `shapes` (`digi-<slug>` → `digi-<nuevo>`) y `files`
 *   (`shapes/<slug>.svg` → `shapes/<nuevo>.svg`) que usa el remapeo.
 */
final class Files implements Part {
	const KEY     = 'files';
	const VERSION = 1;

	const FOLDER   = 'digitalisimo-elements';
	const KINDS    = array( 'shapes', 'masks' );
	const PREFIX   = 'digi-';
	const NEGATIVE = '-negative';
	const SLUG     = '[a-z0-9]+(?:-[a-z0-9]+)*';
	const MAX_BYTES = 1048576;

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	/** `{ "shapes/<slug>.svg": { sha256, bytes }, … }` de los archivos que usa el contenido exportado. */
	public function export( Export_Context $context ): ?array {
		$base = self::base_dir();
		if ( '' === $base ) {
			return null;
		}
		$wanted = array();
		foreach ( array( 'template', 'post' ) as $kind ) {
			foreach ( $context->exported( $kind ) as $id ) {
				self::collect( Documents::elements_of( (int) $id ), $wanted );
				$settings = function_exists( 'get_post_meta' ) ? get_post_meta( (int) $id, '_elementor_page_settings', true ) : array();
				if ( is_array( $settings ) ) {
					self::collect_strings( $settings, $wanted );
				}
			}
		}
		foreach ( Element_Defaults::current_defaults() as $settings ) {
			if ( is_array( $settings ) ) {
				self::collect( array( array( 'settings' => $settings ) ), $wanted );
			}
		}
		$out = array();
		ksort( $wanted, SORT_STRING );
		foreach ( array_keys( $wanted ) as $relative ) {
			$source = $base . '/' . $relative;
			if ( ! is_file( $source ) ) {
				if ( empty( $wanted[ $relative ]['optional'] ) ) {
					$context->report( 'files.missing', array( 'file' => $relative ), Part_Context::WARNING );
				}
				continue;
			}
			if ( ! $context->add_file( 'files/' . $relative, $source ) ) {
				$context->report( 'files.rejected', array( 'file' => $relative ), Part_Context::WARNING );
				continue;
			}
			$out[ $relative ] = array(
				'sha256' => (string) hash_file( 'sha256', $source ),
				'bytes'  => (int) filesize( $source ),
			);
		}
		return $out ? $out : null;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$summary = array(
			'written' => 0,
			'reused'  => 0,
			'renamed' => 0,
			'failed'  => 0,
		);
		$base = self::base_dir();
		if ( '' === $base ) {
			$context->report( 'files.no-uploads', array(), Part_Context::ERROR );
			return $summary;
		}
		$groups = array();
		foreach ( $data as $relative => $entry ) {
			if ( ! is_string( $relative ) || 1 !== preg_match( Module::FILES_KEY_PATTERN, $relative ) || ! is_array( $entry ) ) {
				$context->report( 'files.invalid', array( 'file' => is_scalar( $relative ) ? (string) $relative : '' ), Part_Context::WARNING );
				++$summary['failed'];
				continue;
			}
			$content = self::staged( $context, $relative, $entry );
			if ( null === $content ) {
				++$summary['failed'];
				continue;
			}
			list( $kind, $name ) = explode( '/', substr( $relative, 0, -4 ), 2 );
			$slug                = 'shapes' === $kind && self::NEGATIVE === substr( $name, -9 ) && strlen( $name ) > 9 ? substr( $name, 0, -9 ) : $name;
			$groups[ $kind . '/' . $slug ][ $name ] = $content;
		}

		$written = array();
		$files   = array();
		$shapes  = array();
		ksort( $groups, SORT_STRING );
		foreach ( $groups as $group => $members ) {
			list( $kind, $slug ) = explode( '/', $group, 2 );
			$dir                 = self::target_dir( $base, $kind );
			if ( null === $dir ) {
				$context->report( 'files.target', array( 'kind' => $kind ), Part_Context::ERROR );
				$summary['failed'] += count( $members );
				continue;
			}
			ksort( $members, SORT_STRING );
			$final = self::choose_slug( $dir, $slug, $members );
			if ( null === $final ) {
				$context->report( 'files.collision', array( 'file' => $group ), Part_Context::ERROR );
				$summary['failed'] += count( $members );
				continue;
			}
			$renamed = $final !== $slug;
			foreach ( $members as $name => $content ) {
				$new_name = $final . substr( $name, strlen( $slug ) );
				$target   = $dir . $new_name . '.svg';
				if ( is_file( $target ) ) {
					++$summary['reused'];
				} elseif ( false !== file_put_contents( $target, $content ) ) {
					$written[ $kind . '/' . $new_name . '.svg' ] = hash( 'sha256', $content );
					++$summary['written'];
				} else {
					$context->report( 'files.write', array( 'file' => $kind . '/' . $new_name . '.svg' ), Part_Context::ERROR );
					++$summary['failed'];
					continue;
				}
				$files[ $kind . '/' . $name . '.svg' ] = $kind . '/' . $new_name . '.svg';
			}
			if ( 'shapes' === $kind ) {
				$shapes[ self::PREFIX . $slug ] = self::PREFIX . $final;
			}
			if ( $renamed ) {
				++$summary['renamed'];
				$context->report(
					'files.renamed',
					array(
						'from' => $group,
						'to'   => $kind . '/' . $final,
					)
				);
			}
		}
		$context->add_map( 'files', $files );
		$context->add_map( 'shapes', $shapes );
		$context->store( array( 'written' => $written ) );
		return $summary;
	}

	/** Borra lo que escribió la importación si sigue igual; lo reutilizado no es de la sesión. */
	public function revert( array $stored, Revert_Context $context ): void {
		$base = self::base_dir();
		if ( '' === $base ) {
			return;
		}
		foreach ( isset( $stored['written'] ) && is_array( $stored['written'] ) ? $stored['written'] : array() as $relative => $sha ) {
			if ( ! is_string( $relative ) || 1 !== preg_match( Module::FILES_KEY_PATTERN, $relative ) ) {
				continue;
			}
			list( $kind ) = explode( '/', $relative, 2 );
			$dir          = self::target_dir( $base, $kind, false );
			$file         = null === $dir ? false : realpath( $dir . substr( $relative, strlen( $kind ) + 1 ) );
			if ( false === $file || 0 !== strpos( str_replace( '\\', '/', $file ), $dir ) || ! is_file( $file ) ) {
				continue;
			}
			if ( hash_equals( (string) $sha, (string) hash_file( 'sha256', $file ) ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			} else {
				$context->report( 'files.edited', array( 'file' => $relative ) );
			}
		}
		// Las carpetas que quedan vacías no se quedan en `uploads` (la importación las creó).
		foreach ( array( 'shapes', 'masks' ) as $kind ) {
			$dir = self::target_dir( $base, $kind, false );
			if ( null !== $dir && is_dir( $dir ) && ! is_link( rtrim( $dir, '/' ) ) && ! array_diff( (array) scandir( $dir ), array( '.', '..' ) ) ) {
				@rmdir( rtrim( $dir, '/' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}
		if ( is_dir( $base ) && ! is_link( $base ) && ! array_diff( (array) scandir( $base ), array( '.', '..' ) ) ) {
			@rmdir( $base ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}

	// ── Utilidades ──────────────────────────────────────────────────────────────────────────────────────────────

	/** Carpeta `uploads/digitalisimo-elements` real del sitio actual, sin barra final, o ''. */
	public static function base_dir(): string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		return rtrim( str_replace( '\\', '/', (string) $uploads['basedir'] ), '/' ) . '/' . self::FOLDER;
	}

	/**
	 * Archivos propios que usa un árbol: `digi-<slug>` de los separadores (con su `-negative`) y cualquier
	 * referencia a `digitalisimo-elements/(shapes|masks)/<slug>.svg` en los ajustes (CSS, imágenes).
	 *
	 * @param array $elements Árbol de elementos.
	 * @param array $wanted   Acumula `ruta relativa => { optional }`.
	 */
	public static function collect( array $elements, array &$wanted ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			foreach ( array( 'shape_divider_top', 'shape_divider_bottom' ) as $key ) {
				$value = isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ? $settings[ $key ] : '';
				if ( 1 === preg_match( '/^' . preg_quote( self::PREFIX, '/' ) . '(' . self::SLUG . ')$/', $value, $match ) ) {
					$wanted[ 'shapes/' . $match[1] . '.svg' ] = array( 'optional' => false );
					if ( ! isset( $wanted[ 'shapes/' . $match[1] . self::NEGATIVE . '.svg' ] ) ) {
						$wanted[ 'shapes/' . $match[1] . self::NEGATIVE . '.svg' ] = array( 'optional' => true );
					}
				}
			}
			self::collect_strings( $settings, $wanted );
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect( $element['elements'], $wanted );
			}
		}
	}

	/** Referencias por ruta a los archivos propios, en cualquier cadena de los ajustes (también en un marcador). */
	public static function collect_strings( array $settings, array &$wanted ): void {
		array_walk_recursive(
			$settings,
			function ( $value ) use ( &$wanted ) {
				if ( ! is_string( $value ) || false === strpos( $value, self::FOLDER . '/' ) ) {
					return;
				}
				if ( preg_match_all( '#' . preg_quote( self::FOLDER, '#' ) . '/(shapes|masks)/(' . self::SLUG . ')\.svg#', $value, $matches, PREG_SET_ORDER ) ) {
					foreach ( $matches as $match ) {
						$wanted[ $match[1] . '/' . $match[2] . '.svg' ] = array( 'optional' => false );
					}
				}
			}
		);
		if ( isset( $settings['digitalisimo_unconverted']['element'] ) && is_array( $settings['digitalisimo_unconverted']['element'] ) ) {
			self::collect( array( $settings['digitalisimo_unconverted']['element'] ), $wanted );
		}
	}

	/** Contenido saneado de un archivo preparado, si su huella y tamaño coinciden; null si no. */
	private static function staged( Import_Context $context, string $relative, array $entry ): ?string {
		$path = $context->staged_path( 'files/' . $relative );
		if ( null === $path ) {
			$context->report( 'files.missing', array( 'file' => $relative ), Part_Context::WARNING );
			return null;
		}
		$bytes = filesize( $path );
		if ( false === $bytes || $bytes > self::MAX_BYTES ) {
			$context->report( 'files.size', array( 'file' => $relative ), Part_Context::WARNING );
			return null;
		}
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$sha = isset( $entry['sha256'] ) && is_string( $entry['sha256'] ) ? strtolower( $entry['sha256'] ) : '';
		if ( 1 !== preg_match( '/^[0-9a-f]{64}$/', $sha ) ) {
			$context->report( 'files.hash', array( 'file' => $relative ), Part_Context::WARNING );
			return null;
		}
		if ( ! hash_equals( $sha, hash( 'sha256', $raw ) ) ) {
			// El normalizador comprueba el sha256 del kit y después guarda el SVG saneado: aquí ya no coincide. Sólo
			// la carpeta preparada de K llega hasta aquí (`staged_path()`), y se vuelve a sanear abajo.
			$context->report( 'files.staged-sanitized', array( 'file' => $relative ) );
		}
		$clean = self::sanitize_svg( $raw );
		if ( null === $clean ) {
			$context->report( 'files.svg', array( 'file' => $relative ), Part_Context::WARNING );
			return null;
		}
		if ( $clean !== $raw ) {
			$context->report( 'files.sanitized', array( 'file' => $relative ) );
		}
		return $clean;
	}

	/** SVG saneado con el saneador del núcleo, o null si no hay saneador o no es un SVG (fallar cerrado). */
	private static function sanitize_svg( string $content ): ?string {
		$class = '\Elementor\Core\Utils\Svg\Svg_Sanitizer';
		if ( ! class_exists( $class ) ) {
			return null;
		}
		$clean = ( new $class() )->sanitize( $content );
		return is_string( $clean ) && false !== stripos( $clean, '<svg' ) ? $clean : null;
	}

	/**
	 * Carpeta real de destino de un tipo, con barra final, comprobada dentro de `uploads/digitalisimo-elements/`.
	 *
	 * @param string $base   `base_dir()`.
	 * @param string $kind   `shapes` o `masks`.
	 * @param bool   $create Crearla si no existe.
	 */
	private static function target_dir( string $base, string $kind, bool $create = true ): ?string {
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return null;
		}
		$dir = $base . '/' . $kind;
		if ( $create && ! is_dir( $dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $dir );
			} else {
				@mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		$real_base = realpath( $base );
		$real      = realpath( $dir );
		if ( false === $real_base || false === $real || ! is_dir( $real ) ) {
			return null;
		}
		$real_base = rtrim( str_replace( '\\', '/', $real_base ), '/' ) . '/';
		$real      = rtrim( str_replace( '\\', '/', $real ), '/' ) . '/';
		return $real === $real_base . $kind . '/' ? $real : null;
	}

	/**
	 * Slug final de un grupo (separador y su negativo, o una máscara): el suyo si sus archivos no existen o son
	 * iguales; si no, `<slug>-k<6 hex>` del contenido; null si también choca.
	 */
	private static function choose_slug( string $dir, string $slug, array $members ): ?string {
		$hex = substr( hash( 'sha256', implode( "\n", $members ) ), 0, 6 );
		foreach ( array( $slug, $slug . '-k' . $hex ) as $candidate ) {
			$free = true;
			foreach ( $members as $name => $content ) {
				$file = $dir . $candidate . substr( $name, strlen( $slug ) ) . '.svg';
				if ( is_link( $file ) || ( file_exists( $file ) && ( ! is_file( $file ) || ! hash_equals( hash( 'sha256', $content ), (string) hash_file( 'sha256', $file ) ) ) ) ) {
					$free = false;
					break;
				}
			}
			if ( $free ) {
				return $candidate;
			}
		}
		return null;
	}
}
