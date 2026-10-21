<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizador de un kit que ya pasó el guardián (§7.2).
 *
 * Deja el kit en la forma que espera el importador 3.0, sin nada que pueda cambiar el sitio sin permiso:
 * 1. formato: Template Kit de Envato → kit 3.0 (`Sources\Envato_Kit`), 1.x/2.x → adaptador nativo, 3.0 tal cual;
 * 2. lo que el kit pide (tema, experimentos, plugins) pasa al análisis y se apaga en el manifiesto;
 * 3. la identidad del sitio (`site_name`, `site_description`, `site_logo`, `site_favicon`) nunca viaja;
 * 4. `metadata` de cada documento: sin metas protegidas, sin objetos serializados y con las URL del origen en
 *    `home_url()`;
 * 5. claves del manifiesto que no son nombres seguros se descartan, y `source` se reduce a su nombre;
 * 6. miniaturas que no vienen en el paquete se quitan;
 * 7. los medios propios (`digitalisimo/media.json`) se validan aquí y se preparan con `Normalized::stage()`;
 * 8. análisis para el informe y la pantalla «Kits».
 */
final class Normalizer {
	const FORMAT_KIT3   = '3.0';
	const FORMAT_LEGACY = '2.x';
	const FORMAT_ENVATO = 'envato';

	/** Identidad del sitio: nunca viaja (§4.1, 7). */
	const IDENTITY = array( 'site_name', 'site_description', 'site_logo', 'site_favicon' );

	const KEY_PATTERN       = '/^[A-Za-z0-9_-]{1,64}$/';
	const POST_TYPE_PATTERN = '/^[a-z0-9_-]{1,40}$/';
	const MAX_LIST          = 200;

	/**
	 * @param Inspection $inspection Kit inspeccionado.
	 *
	 * @throws \RuntimeException Si el kit no se puede normalizar (por ejemplo, un formato que este sitio no lee).
	 */
	public static function run( Inspection $inspection ): Normalized {
		$files = array();
		foreach ( $inspection->entries() as $path => $entry ) {
			if ( 'json' === $entry['kind'] ) {
				$files[ (string) $path ] = $inspection->json( (string) $path );
			}
		}
		$manifest = $files['manifest.json'];
		$hostile  = array();
		$format   = self::FORMAT_KIT3;
		$kit      = '';

		if ( isset( $manifest['manifest_version'] ) ) {
			$format = self::FORMAT_ENVATO;
			$kit    = self::envato_fingerprint( $manifest );
			$class  = '\\' . Module::NAMESPACE_PREFIX . 'Sources\\Envato_Kit';
			if ( ! class_exists( $class ) || ! method_exists( $class, 'to_kit3' ) ) {
				throw new \RuntimeException( 'Este sitio todavía no puede convertir Template Kits de Envato.' );
			}
			$converted = call_user_func( array( $class, 'to_kit3' ), $files );
			if ( ! is_array( $converted ) || ! isset( $converted['manifest.json'] ) || ! is_array( $converted['manifest.json'] ) ) {
				throw new \RuntimeException( 'No se pudo convertir el Template Kit de Envato.' );
			}
			$converted = self::renumber( $converted );
			foreach ( $converted as $path => $data ) {
				if ( ! is_string( $path ) || ! is_array( $data ) || ! self::safe_path( $path ) ) {
					throw new \RuntimeException( 'La conversión del Template Kit dejó una ruta no admitida.' );
				}
				Zip_Guard::schema( $path, $data );
			}
			$files    = $converted;
			$manifest = $files['manifest.json'];
		} elseif ( ! isset( $manifest['version'] ) || version_compare( (string) $manifest['version'], '3.0', '<' ) ) {
			$format = self::FORMAT_LEGACY;
		}

		$origin = self::origin( $manifest, $format, $kit );

		// 2. Lo que el kit pide se informa y se apaga: el kit nunca cambia el tema ni los experimentos (D3, D5).
		$requests = self::requests( $manifest, $files );
		// En el formato 2.x `site-settings` es una lista de pestañas: la convierte el adaptador del núcleo, que vuelve
		// a pedir tema y experimentos (V-31); por eso el runner de ajustes de K los ignora siempre.
		if ( isset( $manifest['site-settings'] ) && is_array( $manifest['site-settings'] ) && ! Part_Context::is_list( $manifest['site-settings'] ) ) {
			$manifest['site-settings']['theme']       = false;
			$manifest['site-settings']['experiments'] = false;
		}

		// 3. Identidad fuera; el tema y los experimentos del kit tampoco llegan al importador.
		if ( isset( $files['site-settings.json'] ) ) {
			$site = $files['site-settings.json'];
			unset( $site['theme'], $site['experiments'] );
			if ( isset( $site['settings'] ) && is_array( $site['settings'] ) ) {
				foreach ( self::IDENTITY as $key ) {
					unset( $site['settings'][ $key ] );
				}
			}
			$files['site-settings.json'] = $site;
		}

		// 7. Medios propios validados (la forma ya la comprobó el guardián).
		$media = array();
		if ( isset( $files[ Module::KIT_FOLDER . 'media.json' ] ) ) {
			foreach ( $files[ Module::KIT_FOLDER . 'media.json' ] as $url => $item ) {
				$file = (string) $item['file'];
				if ( $inspection->has( Module::KIT_FOLDER . 'media/' . $file ) && 0 === strpos( $file, (string) $item['sha256'] ) && self::is_url( (string) $url ) ) {
					$media[ (string) $url ] = array(
						'file'   => $file,
						'sha256' => (string) $item['sha256'],
						'mime'   => isset( $item['mime'] ) && is_string( $item['mime'] ) ? $item['mime'] : '',
						'bytes'  => $inspection->size( Module::KIT_FOLDER . 'media/' . $file ),
					);
				} else {
					$hostile['media.invalid'] = ( $hostile['media.invalid'] ?? 0 ) + 1;
				}
			}
		}
		$shapes = array();
		if ( isset( $files[ Module::KIT_FOLDER . 'files.json' ] ) ) {
			foreach ( $files[ Module::KIT_FOLDER . 'files.json' ] as $key => $item ) {
				if ( $inspection->has( Module::KIT_FOLDER . 'files/' . $key ) && is_array( $item ) && isset( $item['sha256'] ) && is_string( $item['sha256'] ) && preg_match( '/^[0-9a-f]{64}$/', $item['sha256'] ) ) {
					$shapes[ (string) $key ] = array(
						'sha256' => $item['sha256'],
						'bytes'  => $inspection->size( Module::KIT_FOLDER . 'files/' . $key ),
					);
				}
			}
		}

		// 5 y 6. Claves seguras, `source` reducido a su nombre y miniaturas sólo si vienen en el paquete.
		$manifest = self::clean_manifest( $manifest, $files, $inspection, $media, $hostile );

		// 4. `metadata` de cada documento.
		foreach ( $files as $path => $data ) {
			if ( preg_match( '#^(?:templates/[^/]+|content/[^/]+/[^/]+)\.json$#', (string) $path ) && isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
				$files[ $path ]['metadata'] = self::metadata( $data['metadata'], $origin, $hostile );
			}
		}
		$files['manifest.json'] = $manifest;

		$analysis = self::analyze( $files, $manifest, $format, $origin, $media, $requests, $inspection );
		if ( $hostile ) {
			$analysis['hostile'] = $hostile;
		}

		return new Normalized( $inspection, $files, $format, $origin, $media, $shapes, $requests, $analysis );
	}

	/**
	 * Claves numéricas para las plantillas de un kit convertido: el núcleo usa la clave como ID de origen y
	 * `theme-builder.json` sólo admite IDs (§5.2). `templates/<clave>.json` pasa a `templates/<n>.json` y las claves de
	 * `manifest.templates` y de `theme-builder.json` siguen el mismo orden. Una clave ya numérica no cambia.
	 *
	 * @param array $files Ruta => datos del kit convertido.
	 */
	public static function renumber( array $files ): array {
		$manifest = isset( $files['manifest.json'] ) && is_array( $files['manifest.json'] ) ? $files['manifest.json'] : array();
		if ( empty( $manifest['templates'] ) || ! is_array( $manifest['templates'] ) ) {
			return $files;
		}
		$map  = array();
		$next = 1;
		foreach ( array_keys( $manifest['templates'] ) as $key ) {
			if ( ctype_digit( (string) $key ) ) {
				$next = max( $next, (int) $key + 1 );
			}
		}
		$templates = array();
		foreach ( $manifest['templates'] as $key => $item ) {
			$new                     = ctype_digit( (string) $key ) ? (string) $key : (string) $next++;
			$map[ (string) $key ]    = $new;
			$templates[ $new ]       = $item;
			$from                    = 'templates/' . $key . '.json';
			if ( isset( $files[ $from ] ) && $new !== (string) $key ) {
				$files[ 'templates/' . $new . '.json' ] = $files[ $from ];
				unset( $files[ $from ] );
			}
		}
		$manifest['templates']  = $templates;
		$files['manifest.json'] = $manifest;
		$builder                = Module::KIT_FOLDER . 'theme-builder.json';
		if ( isset( $files[ $builder ] ) && is_array( $files[ $builder ] ) ) {
			$conditions = array();
			foreach ( $files[ $builder ] as $key => $entry ) {
				if ( isset( $map[ (string) $key ] ) ) {
					$conditions[ $map[ (string) $key ] ] = $entry;
				}
			}
			$files[ $builder ] = $conditions;
		}
		return $files;
	}

	/**
	 * Condiciones del Theme Builder con sus claves en el orden del núcleo (`type`, `name`, `sub_name`, `sub_id`).
	 *
	 * Un objeto JSON no tiene orden, pero el documento de tema une los valores de cada condición en el orden en que
	 * llegan (`implode( '/', $condition )`): con las claves en otro orden, `include/general` se guardaba como
	 * `general///include` y la plantilla quedaba sin condiciones. Una condición en texto no cambia; lo que no es
	 * texto ni objeto con `type` se descarta.
	 *
	 * @param array $conditions Condiciones de `manifest.templates[*].conditions`.
	 */
	public static function ordered_conditions( array $conditions ): array {
		$out = array();
		foreach ( $conditions as $condition ) {
			if ( is_string( $condition ) ) {
				$out[] = $condition;
				continue;
			}
			if ( ! is_array( $condition ) || ! isset( $condition['type'] ) ) {
				continue;
			}
			$ordered = array();
			foreach ( array( 'type', 'name', 'sub_name', 'sub_id' ) as $key ) {
				$value           = isset( $condition[ $key ] ) ? $condition[ $key ] : '';
				$ordered[ $key ] = is_scalar( $value ) ? (string) $value : '';
			}
			$out[] = $ordered;
		}
		return $out;
	}

	/** ¿Es una ruta relativa segura para el kit 3.0? */
	public static function safe_path( string $path ): bool {
		return 1 === preg_match( '#^(?:manifest|site-settings)\.json$|^(?:templates/[A-Za-z0-9_-]{1,64}|content/[a-z0-9_-]{1,40}/[A-Za-z0-9_-]{1,64}|taxonomies/[a-z0-9_-]{1,64}|digitalisimo/[a-z0-9]+(?:-[a-z0-9]+)*|[a-z0-9-]{1,64})\.json$#', $path );
	}

	/** Huella de un Template Kit de Envato: título y fuentes ordenadas (§9.6). */
	private static function envato_fingerprint( array $manifest ): string {
		$sources = array();
		foreach ( isset( $manifest['templates'] ) && is_array( $manifest['templates'] ) ? $manifest['templates'] : array() as $template ) {
			if ( is_array( $template ) && isset( $template['source'] ) && is_string( $template['source'] ) ) {
				$sources[] = basename( $template['source'] );
			}
		}
		sort( $sources, SORT_STRING );
		return sha1( ( isset( $manifest['title'] ) && is_string( $manifest['title'] ) ? $manifest['title'] : '' ) . '|' . implode( ',', $sources ) );
	}

	/** Datos del origen: `format`, `kit`, `home`, `site` y `uploads` (§5.3). */
	private static function origin( array $manifest, string $format, string $kit ): array {
		$own     = isset( $manifest[ Module::MANIFEST_KEY ] ) && is_array( $manifest[ Module::MANIFEST_KEY ] ) ? $manifest[ Module::MANIFEST_KEY ] : array();
		$site    = isset( $manifest['site'] ) && is_string( $manifest['site'] ) && self::is_url( $manifest['site'] ) ? self::untrail( $manifest['site'] ) : '';
		$home    = isset( $own['home'] ) && is_string( $own['home'] ) && self::is_url( $own['home'] ) ? self::untrail( $own['home'] ) : $site;
		$uploads = isset( $own['uploads'] ) && is_string( $own['uploads'] ) && self::is_url( $own['uploads'] ) ? self::untrail( $own['uploads'] ) : ( '' !== $site ? $site . '/wp-content/uploads' : '' );
		if ( '' === $kit ) {
			$kit = isset( $own['kit'] ) && is_string( $own['kit'] ) && preg_match( '/^[0-9a-f]{40}$/', $own['kit'] )
				? $own['kit']
				: sha1( $site . '|' . ( isset( $manifest['name'] ) && is_string( $manifest['name'] ) ? $manifest['name'] : '' ) );
		}
		return array(
			'format'  => $format,
			'kit'     => $kit,
			'home'    => $home,
			'site'    => $site,
			'uploads' => $uploads,
		);
	}

	/** Lo que el kit pide y K no aplica: tema (sin nombre), experimentos y plugins (CR-18). */
	private static function requests( array $manifest, array $files ): array {
		$settings = isset( $manifest['site-settings'] ) && is_array( $manifest['site-settings'] ) ? $manifest['site-settings'] : array();
		$site     = isset( $files['site-settings.json'] ) ? $files['site-settings.json'] : array();
		$requests = array(
			'theme'           => ! empty( $settings['theme'] ) || ! empty( $site['theme'] ),
			'experiments'     => array(),
			'plugins'         => array(),
			'builder_plugins' => 0,
		);
		if ( ! empty( $site['experiments'] ) && is_array( $site['experiments'] ) ) {
			foreach ( $site['experiments'] as $name => $feature ) {
				if ( is_string( $name ) && preg_match( '/^[a-z0-9_-]{1,64}$/', $name ) && count( $requests['experiments'] ) < self::MAX_LIST ) {
					$requests['experiments'][ $name ] = is_array( $feature ) && isset( $feature['state'] ) && is_string( $feature['state'] ) ? substr( $feature['state'], 0, 20 ) : '';
				}
			}
		}
		foreach ( isset( $manifest['plugins'] ) && is_array( $manifest['plugins'] ) ? $manifest['plugins'] : array() as $plugin ) {
			$name = is_array( $plugin ) && isset( $plugin['name'] ) && is_string( $plugin['name'] ) ? $plugin['name'] : '';
			$slug = is_array( $plugin ) && isset( $plugin['plugin'] ) && is_string( $plugin['plugin'] ) ? $plugin['plugin'] : '';
			if ( self::is_builder_plugin( $name, $slug ) ) {
				++$requests['builder_plugins'];
				continue;
			}
			$label = trim( (string) preg_replace( '/[^\p{L}\p{N} ._()\-–&+]/u', '', $name ) );
			if ( '' !== $label && count( $requests['plugins'] ) < self::MAX_LIST ) {
				$requests['plugins'][] = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 80 ) : substr( $label, 0, 80 );
			}
		}
		$requests['plugins'] = array_values( array_unique( $requests['plugins'] ) );
		return $requests;
	}

	/** Los plugins de la familia del constructor nunca se nombran: son funciones incluidas en DIGITALÍSIMO Elements. */
	public static function is_builder_plugin( string $name, string $slug ): bool {
		$haystack = strtolower( $name . ' ' . $slug );
		return 1 === preg_match( '/elementor|pro[ -]?elements|digitalisimo/', $haystack );
	}

	/**
	 * Manifiesto con claves seguras (5) y miniaturas sólo si vienen en el paquete (6).
	 */
	private static function clean_manifest( array $manifest, array $files, Inspection $inspection, array $media, array &$hostile ): array {
		$drop = function ( $code ) use ( &$hostile ) {
			$hostile[ $code ] = ( $hostile[ $code ] ?? 0 ) + 1;
		};
		$thumbnail = function ( $url ) use ( $media ) {
			return is_string( $url ) && isset( $media[ $url ] ) ? $url : '';
		};
		if ( array_key_exists( 'thumbnail', $manifest ) ) {
			$manifest['thumbnail'] = $thumbnail( $manifest['thumbnail'] );
		}
		if ( isset( $manifest['templates'] ) && is_array( $manifest['templates'] ) ) {
			$templates = array();
			foreach ( $manifest['templates'] as $id => $template ) {
				$id = (string) $id;
				if ( 1 !== preg_match( self::KEY_PATTERN, $id ) || self::key( $id ) !== strtolower( $id ) || ! isset( $files[ 'templates/' . $id . '.json' ] ) || ! is_array( $template ) ) {
					$drop( 'manifest.template' );
					continue;
				}
				if ( isset( $template['source'] ) ) {
					$template['source'] = basename( (string) $template['source'] );
				}
				$template['thumbnail'] = $thumbnail( $template['thumbnail'] ?? '' );
				if ( isset( $template['conditions'] ) && is_array( $template['conditions'] ) ) {
					$template['conditions'] = self::ordered_conditions( $template['conditions'] );
				}
				$templates[ $id ]      = $template;
			}
			$manifest['templates'] = $templates;
		}
		if ( isset( $manifest['content'] ) && is_array( $manifest['content'] ) ) {
			$content = array();
			foreach ( $manifest['content'] as $type => $posts ) {
				$type = (string) $type;
				if ( 1 !== preg_match( self::POST_TYPE_PATTERN, $type ) || ! is_array( $posts ) ) {
					$drop( 'manifest.content' );
					continue;
				}
				foreach ( $posts as $id => $post ) {
					$id = (string) $id;
					if ( 1 !== preg_match( self::KEY_PATTERN, $id ) || ! isset( $files[ 'content/' . $type . '/' . $id . '.json' ] ) || ! is_array( $post ) ) {
						$drop( 'manifest.content' );
						continue;
					}
					$post['thumbnail']        = $thumbnail( $post['thumbnail'] ?? '' );
					$content[ $type ][ $id ] = $post;
				}
			}
			$manifest['content'] = $content;
		}
		if ( isset( $manifest['wp-content'] ) && is_array( $manifest['wp-content'] ) ) {
			$wp = array();
			foreach ( $manifest['wp-content'] as $type => $ids ) {
				$type = (string) $type;
				if ( 1 !== preg_match( self::POST_TYPE_PATTERN, $type ) || ! $inspection->has( 'wp-content/' . $type . '/' . $type . '.xml' ) ) {
					$drop( 'manifest.wp-content' );
					continue;
				}
				$wp[ $type ] = $ids;
			}
			$manifest['wp-content'] = $wp;
		}
		if ( isset( $manifest['taxonomies'] ) && is_array( $manifest['taxonomies'] ) ) {
			$taxonomies = array();
			foreach ( $manifest['taxonomies'] as $type => $list ) {
				$type = (string) $type;
				if ( 1 !== preg_match( self::POST_TYPE_PATTERN, $type ) || ! is_array( $list ) ) {
					$drop( 'manifest.taxonomies' );
					continue;
				}
				foreach ( $list as $taxonomy ) {
					$name = is_array( $taxonomy ) ? ( $taxonomy['name'] ?? '' ) : $taxonomy;
					if ( ! is_string( $name ) || 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/', $name ) || ! isset( $files[ 'taxonomies/' . $name . '.json' ] ) ) {
						$drop( 'manifest.taxonomies' );
						continue;
					}
					$taxonomies[ $type ][] = $taxonomy;
				}
			}
			$manifest['taxonomies'] = $taxonomies;
		}
		return $manifest;
	}

	/**
	 * `metadata` de un documento (paso 4): sin metas protegidas (un kit legítimo nunca las trae: `get_export_metadata()`
	 * no las exporta), sin objetos serializados y con el prefijo del origen en `home_url()`.
	 */
	public static function metadata( array $metadata, array $origin, array &$hostile ): array {
		$clean = array();
		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key || ( function_exists( 'is_protected_meta' ) ? is_protected_meta( $key, 'post' ) : '_' === $key[0] ) ) {
				$hostile['metadata.protected'] = ( $hostile['metadata.protected'] ?? 0 ) + 1;
				continue;
			}
			if ( is_string( $value ) && self::is_serialized( $value ) ) {
				$value = self::unserialize( $value );
				if ( null === $value ) {
					$hostile['metadata.object'] = ( $hostile['metadata.object'] ?? 0 ) + 1;
					continue;
				}
			} elseif ( self::has_object( $value ) ) {
				$hostile['metadata.object'] = ( $hostile['metadata.object'] ?? 0 ) + 1;
				continue;
			}
			$clean[ $key ] = self::rewrite_urls( $value, $origin );
		}
		return $clean;
	}

	/** Un serializado sin objetos, o null si tiene objetos o no se puede leer. */
	public static function unserialize( string $value ) {
		if ( 'b:0;' === trim( $value ) ) {
			return false;
		}
		if ( preg_match( '/(?:^|[;{])\s*[OC]:\d+:"/', $value ) ) {
			return null;
		}
		$result = @unserialize( trim( $value ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- sin clases.
		if ( false === $result || self::has_object( $result ) ) {
			return null;
		}
		return $result;
	}

	/** ¿Hay un objeto (también `__PHP_Incomplete_Class`) en el valor? */
	public static function has_object( $value ): bool {
		if ( is_object( $value ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::has_object( $item ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function is_serialized( string $value ): bool {
		if ( function_exists( 'is_serialized' ) ) {
			return (bool) is_serialized( $value );
		}
		$value = trim( $value );
		return 'N;' === $value || 1 === preg_match( '/^(?:[abdisOC]):/', $value ) && in_array( substr( $value, -1 ), array( ';', '}' ), true );
	}

	/** Cambia el prefijo `home` o `site` del origen por `home_url()` en los textos de un valor. */
	public static function rewrite_urls( $value, array $origin ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::rewrite_urls( $item, $origin );
			}
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		$home = function_exists( 'home_url' ) ? self::untrail( (string) home_url() ) : '';
		if ( '' === $home ) {
			return $value;
		}
		foreach ( array_unique( array_filter( array( $origin['home'] ?? '', $origin['site'] ?? '' ) ) ) as $prefix ) {
			if ( $prefix === $home ) {
				continue;
			}
			if ( $value === $prefix || 0 === strpos( $value, $prefix . '/' ) || 0 === strpos( $value, $prefix . '?' ) || 0 === strpos( $value, $prefix . '#' ) ) {
				return $home . substr( $value, strlen( $prefix ) );
			}
		}
		return $value;
	}

	private static function is_url( string $url ): bool {
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- también sin WordPress.
		return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true );
	}

	/**
	 * Análisis del kit (paso 8): lo que mide la pantalla «Kits» antes y después de importar.
	 */
	private static function analyze( array $files, array $manifest, string $format, array $origin, array $media, array $requests, Inspection $inspection ): array {
		$widgets_known  = null;
		$elements_known = null;
		$experiments    = null;
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && is_object( \Elementor\Plugin::$instance ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->widgets_manager ) && is_object( $plugin->widgets_manager ) ) {
				$widgets_known = array_keys( (array) $plugin->widgets_manager->get_widget_types() );
			}
			if ( isset( $plugin->elements_manager ) && is_object( $plugin->elements_manager ) ) {
				$elements_known = array_keys( (array) $plugin->elements_manager->get_element_types() );
			}
			if ( isset( $plugin->experiments ) && is_object( $plugin->experiments ) ) {
				$experiments = $plugin->experiments;
			}
		}
		$analysis = array(
			'format'             => $format,
			'title'              => isset( $manifest['title'] ) && is_string( $manifest['title'] ) ? substr( $manifest['title'], 0, 200 ) : '',
			'widgets'            => array(),
			'unknown_widgets'    => array(),
			'documents'          => array(),
			'unknown_documents'  => array(),
			'content'            => array(),
			'missing_post_types' => array(),
			'terms'              => array(),
			'missing_taxonomies' => array(),
			'experiments'        => array(),
			'components'         => 0,
			'requests'           => $requests,
			'google_fonts'       => array(),
			'hosts'              => array(),
			'forms'              => 0,
			'needs'              => isset( $manifest[ Module::MANIFEST_KEY ]['needs'] ) && is_array( $manifest[ Module::MANIFEST_KEY ]['needs'] ) ? $manifest[ Module::MANIFEST_KEY ]['needs'] : array(),
			'media'              => array(
				'embedded' => count( $media ),
				'bytes'    => array_sum( array_column( $media, 'bytes' ) ),
			),
			'blocked_types'      => array(),
			'ignored'            => $inspection->ignored(),
		);
		$documents = function ( $doc_type ) use ( &$analysis ) {
			$doc_type = is_string( $doc_type ) ? substr( $doc_type, 0, 64 ) : '';
			if ( '' === $doc_type ) {
				return;
			}
			$analysis['documents'][ $doc_type ] = ( $analysis['documents'][ $doc_type ] ?? 0 ) + 1;
			if ( function_exists( 'post_type_exists' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) && ! \Elementor\Plugin::$instance->documents->get_document_type( $doc_type, false ) ) {
				$analysis['unknown_documents'][ $doc_type ] = ( $analysis['unknown_documents'][ $doc_type ] ?? 0 ) + 1;
			}
		};
		foreach ( isset( $manifest['templates'] ) ? $manifest['templates'] : array() as $template ) {
			$documents( $template['doc_type'] ?? '' );
		}
		foreach ( isset( $manifest['content'] ) ? $manifest['content'] : array() as $type => $posts ) {
			$analysis['content'][ $type ] = count( $posts );
			if ( function_exists( 'post_type_exists' ) && ! post_type_exists( $type ) ) {
				$analysis['missing_post_types'][ $type ] = count( $posts );
			}
			foreach ( $posts as $post ) {
				$documents( $post['doc_type'] ?? '' );
			}
		}
		foreach ( isset( $manifest['wp-content'] ) ? $manifest['wp-content'] : array() as $type => $ids ) {
			$analysis['content'][ $type ] = ( $analysis['content'][ $type ] ?? 0 ) + ( is_array( $ids ) ? count( $ids ) : 0 );
			if ( function_exists( 'post_type_exists' ) && ! post_type_exists( $type ) ) {
				$analysis['missing_post_types'][ $type ] = ( $analysis['missing_post_types'][ $type ] ?? 0 ) + ( is_array( $ids ) ? count( $ids ) : 0 );
			}
		}
		foreach ( $files as $path => $data ) {
			if ( 0 === strpos( (string) $path, 'taxonomies/' ) ) {
				$taxonomy                       = substr( (string) $path, 11, -5 );
				$analysis['terms'][ $taxonomy ] = count( $data );
				if ( function_exists( 'taxonomy_exists' ) && ! taxonomy_exists( $taxonomy ) ) {
					$analysis['missing_taxonomies'][ $taxonomy ] = count( $data );
				}
			}
		}

		$fonts       = array();
		$hosts       = array();
		$origin_host = strtolower( (string) parse_url( (string) $origin['home'], PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$own_host    = function_exists( 'home_url' ) ? strtolower( (string) parse_url( (string) home_url(), PHP_URL_HOST ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$walk        = function ( array $elements ) use ( &$walk, &$analysis, $widgets_known, $elements_known, $experiments ) {
			foreach ( $elements as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				$el_type = isset( $element['elType'] ) && is_string( $element['elType'] ) ? $element['elType'] : '';
				$type    = 'widget' === $el_type && isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : $el_type;
				if ( '' !== $type ) {
					$analysis['widgets'][ $type ] = ( $analysis['widgets'][ $type ] ?? 0 ) + 1;
					$known                        = 'widget' === $el_type ? $widgets_known : $elements_known;
					if ( is_array( $known ) && ! in_array( $type, $known, true ) ) {
						$analysis['unknown_widgets'][ $type ] = ( $analysis['unknown_widgets'][ $type ] ?? 0 ) + 1;
					}
					if ( 'e-component' === $type ) {
						++$analysis['components'];
					}
					if ( 'form' === $type ) {
						++$analysis['forms'];
					}
					$feature = 'container' === $type ? 'container' : ( 0 === strpos( $type, 'nested-' ) || 0 === strpos( $type, 'n-' ) ? 'nested-elements' : ( 0 === strpos( $type, 'e-' ) ? 'e_atomic_elements' : '' ) );
					if ( '' !== $feature && $experiments && method_exists( $experiments, 'is_feature_active' ) && ! $experiments->is_feature_active( $feature ) ) {
						$analysis['experiments'][ $feature ] = ( $analysis['experiments'][ $feature ] ?? 0 ) + 1;
					}
				}
				if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$walk( $element['elements'] );
				}
			}
		};
		foreach ( $files as $path => $data ) {
			if ( preg_match( '#^(?:templates/[^/]+|content/[^/]+/[^/]+)\.json$#', (string) $path ) && isset( $data['content'] ) && is_array( $data['content'] ) ) {
				$walk( $data['content'] );
			}
			if ( preg_match( '#^(?:templates/|content/|site-settings\.json)#', (string) $path ) ) {
				self::scan_strings( $data, $fonts, $hosts, array( $origin_host, $own_host ) );
			}
		}
		arsort( $hosts );
		$analysis['google_fonts'] = array_slice( array_keys( $fonts ), 0, 50 );
		$analysis['hosts']        = array_slice( $hosts, 0, 50, true );

		foreach ( $media as $item ) {
			$ext = strtolower( (string) pathinfo( $item['file'], PATHINFO_EXTENSION ) );
			if ( ! self::upload_allowed( $ext ) ) {
				$analysis['blocked_types'][ $ext ] = ( $analysis['blocked_types'][ $ext ] ?? 0 ) + 1;
			}
		}
		foreach ( array( 'widgets', 'unknown_widgets', 'documents', 'unknown_documents', 'content', 'missing_post_types', 'terms', 'missing_taxonomies' ) as $key ) {
			$analysis[ $key ] = array_slice( $analysis[ $key ], 0, self::MAX_LIST, true );
		}
		return $analysis;
	}

	/** Fuentes de Google (`*_font_family`) y hosts externos (ni el origen ni este sitio) de los textos de un valor. */
	private static function scan_strings( $value, array &$fonts, array &$hosts, array $own, string $key = '' ): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $item ) {
				self::scan_strings( $item, $fonts, $hosts, $own, (string) $k );
			}
			return;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return;
		}
		if ( '_font_family' === substr( $key, -12 ) && count( $fonts ) < 100 && strlen( $value ) <= 80 ) {
			$fonts[ $value ] = true;
		}
		if ( count( $hosts ) < 200 && preg_match_all( '#https?://([a-z0-9.-]+)#i', $value, $matches ) ) {
			foreach ( $matches[1] as $host ) {
				$host = strtolower( $host );
				if ( ! in_array( $host, $own, true ) ) {
					$hosts[ $host ] = ( $hosts[ $host ] ?? 0 ) + 1;
				}
			}
		}
	}

	/** ¿Admite el sitio subir ese tipo de archivo (`upload_filetypes` de la red, D15)? */
	public static function upload_allowed( string $ext ): bool {
		if ( 'svg' === $ext ) {
			return class_exists( '\Elementor\Core\Files\Uploads_Manager' ) && \Elementor\Core\Files\Uploads_Manager::are_unfiltered_uploads_enabled();
		}
		if ( ! function_exists( 'get_allowed_mime_types' ) ) {
			return true;
		}
		foreach ( array_keys( get_allowed_mime_types() ) as $exts ) {
			if ( in_array( $ext, explode( '|', $exts ), true ) ) {
				return true;
			}
		}
		return in_array( $ext, array( 'woff', 'woff2', 'ttf', 'otf', 'json' ), true );
	}

	/** `untrailingslashit()` también sin WordPress. */
	private static function untrail( string $value ): string {
		return rtrim( $value, '/\\' );
	}

	/** `sanitize_key()` también sin WordPress. */
	private static function key( string $value ): string {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
	}
}
