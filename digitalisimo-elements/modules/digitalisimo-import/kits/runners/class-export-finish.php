<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Parts;
use Elementor\App\Modules\ImportExportCustomization\Runners\Export\Export_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner final de exportación `digitalisimo` (§5.2, §8.3): el único que escribe la carpeta `digitalisimo/` y la
 * clave `digitalisimo` de `manifest.json` (que el núcleo une con `+=`).
 *
 * - Cada parte con datos va a `digitalisimo/<clave>.json` y su versión a `parts`.
 * - En la App, los archivos pedidos con `Export_Context::add_file()` se añaden aquí dentro del tope de D6; en WP-CLI
 *   los añade el comando después con `addFile`, sin tope total.
 * - `urls.json` guarda la ruta relativa de cada entrada exportada, para avisar si cambia al importar (§9.3).
 */
class Export_Finish extends Export_Runner_Base {
	/** Tipos anotados con `add_exported()` que no son entradas. */
	const NOT_POSTS = array( 'attachment', 'media_url', 'term' );

	public static function get_name(): string {
		return Module::RUNNER;
	}

	public function should_export( array $data ) {
		return null !== Context::export_context();
	}

	public function export( array $data ) {
		$context = Context::export_context();
		if ( null === $context ) {
			return array(
				'files'    => array(),
				'manifest' => array(),
			);
		}
		$files = array();
		$parts = array();
		foreach ( Parts::all() as $key => $part ) {
			$context->begin_part( (string) $key );
			try {
				$out = $part->export( $context );
			} catch ( \Throwable $error ) {
				$context->report( 'part.error', array( 'message' => $error->getMessage() ), Part_Context::ERROR );
				$out = null;
			}
			$context->end_part();
			if ( is_array( $out ) && $out ) {
				$json = wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( is_string( $json ) ) {
					$files[]       = array(
						'path' => Parts::file( (string) $key ),
						'data' => $json,
					);
					$parts[ $key ] = $part->version();
				}
			}
		}

		$urls = self::urls( $context );
		if ( $urls ) {
			$files[] = array(
				'path' => Module::KIT_FOLDER . 'urls.json',
				'data' => (string) wp_json_encode( $urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			);
		}

		$embedded = 0;
		foreach ( $context->files() as $path => $item ) {
			if ( 0 === strpos( (string) $path, 'media/' ) ) {
				++$embedded;
			}
			if ( Export_Context::SURFACE_APP === $context->surface() ) {
				$bytes = file_get_contents( $item['source'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo del sitio ya comprobado por Export_Context.
				if ( false !== $bytes ) {
					$files[] = array(
						'path' => Module::KIT_FOLDER . $path,
						'data' => $bytes,
					);
				}
			}
		}

		$info     = Context::export_args();
		$title    = isset( $info['kit_info']['title'] ) && is_string( $info['kit_info']['title'] ) && '' !== $info['kit_info']['title'] ? $info['kit_info']['title'] : 'kit';
		$uploads  = wp_upload_dir( null, false );
		$stats    = self::widgets( $context );
		$manifest = array(
			'v'            => Module::FORMAT,
			'elements'     => defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : '',
			'core'         => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
			'home'         => home_url(),
			'site'         => get_site_url(),
			'uploads'      => empty( $uploads['error'] ) ? (string) $uploads['baseurl'] : '',
			'kit'          => sha1( get_site_url() . '|' . sanitize_title( $title ) ),
			'parts'        => $parts,
			'widgets'      => $stats['widgets'],
			'needs'        => $stats['needs'],
			'media'        => array(
				'embedded' => $embedded,
				'linked'   => max( 0, count( $context->exported( 'media_url' ) ) - $embedded ),
				'bytes'    => $context->budget()['used'],
			),
			'not_exported' => $context->get_not_exported(),
		);
		Context::deactivate_export_hooks();

		return array(
			'files'    => $files,
			'manifest' => array( array( Module::MANIFEST_KEY => $manifest ) ),
		);
	}

	/** `"<tipo>:<id origen>" => "<ruta relativa del permalink>"` de las entradas exportadas. */
	private static function urls( Export_Context $context ): array {
		$urls = array();
		foreach ( self::exported_posts( $context ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || ! is_post_type_viewable( $post->post_type ) ) {
				continue;
			}
			$key = $post->post_type . ':' . $id;
			if ( 1 === preg_match( Module::URLS_KEY_PATTERN, $key ) ) {
				$urls[ $key ] = wp_make_link_relative( (string) get_permalink( $post ) );
			}
		}
		return $urls;
	}

	/** IDs de entradas exportadas por los runners de K. */
	private static function exported_posts( Export_Context $context ): array {
		$ids = array();
		foreach ( array( 'post', 'template', 'page', 'content' ) as $kind ) {
			foreach ( $context->exported( $kind ) as $id ) {
				if ( is_int( $id ) || ctype_digit( (string) $id ) ) {
					$ids[ (int) $id ] = true;
				}
			}
		}
		return array_keys( $ids );
	}

	/**
	 * Widgets por tipo y lo que el kit necesita (`needs`): WooCommerce, campos ACF y la clave de Google Places.
	 *
	 * @return array{widgets: array, needs: array}
	 */
	private static function widgets( Export_Context $context ): array {
		$widgets = array();
		$needs   = array(
			'woocommerce'   => false,
			'acf'           => array(),
			'google_places' => false,
		);
		foreach ( self::exported_posts( $context ) as $id ) {
			$type = get_post_type( $id );
			if ( in_array( $type, array( 'product', 'product_variation' ), true ) ) {
				$needs['woocommerce'] = true;
			}
			$raw = (string) get_post_meta( $id, '_elementor_data', true );
			if ( '' === $raw ) {
				continue;
			}
			if ( preg_match_all( '/"widgetType":"([a-z0-9_-]+)"/i', $raw, $matches ) ) {
				foreach ( $matches[1] as $widget ) {
					$widgets[ $widget ] = ( $widgets[ $widget ] ?? 0 ) + 1;
					if ( 0 === strpos( $widget, 'woocommerce-' ) || 0 === strpos( $widget, 'wc-' ) ) {
						$needs['woocommerce'] = true;
					}
					if ( 'digitalisimo-google-reviews' === $widget ) {
						$needs['google_places'] = true;
					}
				}
			}
			if ( false !== strpos( $raw, 'acf' ) && preg_match_all( '/field_[0-9a-f]{6,20}/', $raw, $fields ) ) {
				foreach ( $fields[0] as $field ) {
					$needs['acf'][ $field ] = true;
				}
			}
		}
		ksort( $widgets );
		$needs['acf'] = array_slice( array_keys( $needs['acf'] ), 0, 200 );
		return array(
			'widgets' => $widgets,
			'needs'   => $needs,
		);
	}
}
