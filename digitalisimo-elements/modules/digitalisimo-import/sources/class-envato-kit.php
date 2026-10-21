<?php
namespace Digitalisimo\Elements\Import\Sources;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Template Kit de Envato → kit 3.0 del núcleo (§10.2). Código propio a partir del formato, descrito aquí con
 * palabras propias:
 *
 * - Un Template Kit es un ZIP con un `manifest.json` y las plantillas en JSON. El manifiesto declara la versión de
 *   su formato (`manifest_version`, que es lo que lo distingue de un kit del constructor), un título (`title`), el
 *   constructor para el que se hizo (`page_builder`, opcional), los plugins que el autor usó (`required_plugins`:
 *   nombre, versión y archivo) y una lista `templates`.
 * - Cada entrada de `templates` da el nombre visible (`name`), la ruta del archivo dentro del ZIP (`source`), el
 *   tipo de documento del constructor (`type`), una captura (`screenshot`) y un bloque `metadata`: el tipo
 *   editorial (`template_type`; `global-styles` marca la plantilla que sólo guarda colores, tipografías y demás
 *   ajustes globales), las condiciones del Theme Builder como cadenas `tipo/nombre/subnombre/id`
 *   (`elementor_pro_conditions`), si el archivo viaja en el ZIP (`include_in_zip`) y la plantilla de página de
 *   WordPress (`wp_page_template`).
 * - Cada archivo es una exportación de la biblioteca: `content` (el árbol de elementos), `page_settings` y, a
 *   veces, `type`, `title` y su propio `metadata` con las mismas claves que el manifiesto.
 *
 * Resultado: un kit 3.0 que importa el proceso del núcleo, siempre en «sólo agregar» (D1). Los estilos globales pasan
 * a `site-settings.json` (se aplican con «Aplicar los ajustes del kit»); cada plantilla, a `templates/<clave>.json`;
 * las condiciones completas, a `digitalisimo/theme-builder.json` (V-24), y el manifiesto sólo conserva la primera
 * `include` sin `sub_id`. Nada del formato queda en `metadata`, que el núcleo guardaría como metas (CC-21).
 *
 * Las dos lecturas (CV-13): `template_type` y las condiciones se leen del manifiesto y del archivo. Manda el
 * manifiesto y, si no coinciden, el informe lo dice (D17 lo confirmará con kits reales).
 */
final class Envato_Kit {
	const FORMAT = 'envato';

	/** Versión del formato de kit del núcleo que se produce. */
	const KIT_VERSION = '3.0';

	const GLOBAL_STYLES = 'global-styles';

	/** Tipo de documento que recibe una plantilla cuyo tipo no existe en este sitio (CC-4). */
	const FALLBACK_TYPE = 'section';

	/** Claves del formato en `metadata`: se traducen o se descartan, nunca llegan a ser metas. */
	const META_TEMPLATE_TYPE = 'template_type';
	const META_CONDITIONS    = 'elementor_pro_conditions';
	const META_INCLUDE       = 'include_in_zip';
	const META_PAGE_TEMPLATE = 'wp_page_template';

	/** Plantillas de página que entiende el constructor (`settings.template`). A confirmar con kits reales (D17). */
	const PAGE_TEMPLATES = array( 'default', 'elementor_canvas', 'elementor_header_footer', 'elementor_theme' );

	/** Identidad del sitio: nunca viaja (§4.1, punto 7). */
	const IDENTITY_KEYS = array( 'site_name', 'site_description', 'site_logo', 'site_favicon' );

	/** Ubicación del Theme Builder por tipo de documento, si el núcleo no puede decirla. */
	const LOCATIONS = array(
		'header'          => 'header',
		'footer'          => 'footer',
		'single'          => 'single',
		'single-post'     => 'single',
		'single-page'     => 'single',
		'error-404'       => 'single',
		'product'         => 'single',
		'archive'         => 'archive',
		'search-results'  => 'archive',
		'product-archive' => 'archive',
		'popup'           => 'popup',
	);

	/** Tipo de documento a partir del tipo editorial, sólo cuando la entrada no trae `type`. */
	const EDITORIAL_TYPES = array(
		'section-header'   => 'header',
		'section-footer'   => 'footer',
		'section-popup'    => 'popup',
		'single-404'       => 'error-404',
		'single-post'      => 'single-post',
		'single-page'      => 'page',
		'single-home'      => 'page',
		'archive-blog'     => 'archive',
		'archive-search'   => 'search-results',
		'single-product'   => 'product',
		'archive-product'  => 'product-archive',
	);

	/** Ruta de una plantilla dentro del kit: relativa, sin `..`, terminada en `.json`. */
	const SOURCE_PATTERN = '#^(?:[A-Za-z0-9_][A-Za-z0-9_. -]*/){0,4}[A-Za-z0-9_][A-Za-z0-9_. -]*\.json$#';

	/** Topes: plantillas, textos, avisos que viajan en el manifiesto y largo de la clave (con sufijo, ≤ 64). */
	const MAX_TEMPLATES = 500;
	const MAX_TEXT      = 200;
	const MAX_REPORT    = 100;
	const MAX_KEY       = 56;

	/** ¿Es el manifiesto de un Template Kit? */
	public static function is_envato( array $manifest ): bool {
		return isset( $manifest['manifest_version'] ) && '' !== $manifest['manifest_version'] && null !== $manifest['manifest_version'];
	}

	/**
	 * Contrato del normalizador (§7.2, paso 1): recibe los JSON del kit por ruta, con `manifest.json`, y devuelve
	 * los del kit 3.0, también por ruta. Lo que la conversión tiene que contar viaja en el manifiesto:
	 * `plugins` (lo que pedía el kit, en la forma 3.0 y sin enlaces) y `digitalisimo.conversion` (tipos que este
	 * sitio no tiene, plantillas omitidas y avisos).
	 *
	 * @param array $files Ruta => JSON decodificado (o en texto), relativa a la raíz del kit.
	 * @param array $env   Ver `convert()`.
	 *
	 * @return array Ruta => datos: `manifest.json`, `templates/<clave>.json`, `site-settings.json` y
	 *               `digitalisimo/theme-builder.json` (sólo los que tienen datos).
	 *
	 * @throws \RuntimeException Si no es un Template Kit o es de otro constructor.
	 */
	public static function to_kit3( array $files, array $env = array() ): array {
		$manifest = isset( $files['manifest.json'] ) ? self::decoded( $files['manifest.json'] ) : null;
		if ( null === $manifest ) {
			throw new \RuntimeException( 'El Template Kit no tiene un manifest.json válido.' );
		}
		$result   = self::convert( $manifest, $files, $env );
		$manifest = $result['manifest'];
		$plugins  = array();
		foreach ( $result['analysis']['requested']['plugins'] as $plugin ) {
			$plugins[] = array(
				'name'      => $plugin['name'],
				'plugin'    => $plugin['file'],
				'pluginUri' => '',
				'version'   => $plugin['version'],
			);
		}
		if ( $plugins ) {
			$manifest['plugins'] = $plugins;
		}
		$manifest[ Module::MANIFEST_KEY ]['conversion'] = array(
			'templates'     => $result['analysis']['templates'],
			'global_styles' => $result['analysis']['global_styles'],
			'report'        => array_slice( $result['report'], 0, self::MAX_REPORT ),
		);
		return array( 'manifest.json' => $manifest ) + $result['files'];
	}

	/**
	 * Convierte un Template Kit en un kit 3.0. Función pura: no escribe nada y no llama al núcleo salvo, por
	 * defecto, para saber qué tipos de documento existen.
	 *
	 * @param array          $manifest `manifest.json` del Template Kit, ya decodificado.
	 * @param array|callable $files    Archivos JSON del kit, por ruta relativa a su raíz: un array `ruta => array|string`
	 *                                 (decodificado o JSON) o un callable `( string $ruta ): ?array`.
	 * @param array          $env      `registered( string $doc_type ): bool` y `location( string $doc_type ): string`.
	 *                                 Por defecto, lo registrado en el núcleo.
	 *
	 * @return array {
	 *     @type array $manifest Manifiesto 3.0 (`version` 3.0, sin `manifest_version`; `digitalisimo.format = envato`).
	 *     @type array $files    Ruta => datos: `templates/<clave>.json`, `site-settings.json` y
	 *                           `digitalisimo/theme-builder.json` (sólo los que tienen datos).
	 *     @type array $analysis `format`, `kit`, `title`, `requested`, `templates` y `global_styles`.
	 *     @type array $report   Avisos `{ code, level, data }`.
	 * }
	 *
	 * @throws \RuntimeException Si no es un Template Kit o es de otro constructor.
	 */
	public static function convert( array $manifest, $files, array $env = array() ): array {
		if ( ! self::is_envato( $manifest ) ) {
			throw new \RuntimeException( 'El archivo no es un Template Kit: falta su versión de formato.' );
		}
		if ( isset( $manifest['page_builder'] ) && is_string( $manifest['page_builder'] ) && '' !== $manifest['page_builder'] && 'elementor' !== strtolower( trim( $manifest['page_builder'] ) ) ) {
			throw new \RuntimeException( 'Este Template Kit se hizo para otro constructor y no se puede importar.' );
		}
		$env     = self::env( $env );
		$read    = self::reader( $files );
		$report  = array();
		$title   = self::text( isset( $manifest['title'] ) ? $manifest['title'] : ( isset( $manifest['name'] ) ? $manifest['name'] : '' ) );
		$title   = '' !== $title ? $title : 'Template Kit';
		$entries = isset( $manifest['templates'] ) && is_array( $manifest['templates'] ) ? $manifest['templates'] : array();

		$templates     = array();
		$out_files     = array();
		$theme_builder = array();
		$sources       = array();
		$used_keys     = array();
		$by_type       = array();
		$unknown_types = array();
		$skipped       = 0;
		$global        = null;
		$count         = 0;

		foreach ( $entries as $index => $entry ) {
			if ( ++$count > self::MAX_TEMPLATES ) {
				$report[] = self::note( 'envato.too-many', array( 'max' => self::MAX_TEMPLATES ), 'warning' );
				break;
			}
			if ( ! is_array( $entry ) ) {
				++$skipped;
				$report[] = self::note( 'envato.invalid-entry', array( 'index' => $index ), 'warning' );
				continue;
			}
			if ( isset( $entry['source'] ) && is_string( $entry['source'] ) ) {
				// La huella cuenta todas las fuentes declaradas, como el normalizador (`origin.kit`).
				$sources[] = $entry['source'];
			}
			$source = isset( $entry['source'] ) && is_string( $entry['source'] ) ? self::clean_path( $entry['source'] ) : '';
			if ( '' === $source ) {
				++$skipped;
				$report[] = self::note( 'envato.invalid-source', array( 'index' => $index ), 'warning' );
				continue;
			}
			$file      = $read( $source );
			$m_meta    = isset( $entry['metadata'] ) && is_array( $entry['metadata'] ) ? $entry['metadata'] : array();
			$f_meta    = is_array( $file ) && isset( $file['metadata'] ) && is_array( $file['metadata'] ) ? $file['metadata'] : array();
			$editorial = self::reading( self::META_TEMPLATE_TYPE, $m_meta, $f_meta, $source, $report );
			$type      = self::slug( isset( $entry['type'] ) ? $entry['type'] : ( is_array( $file ) && isset( $file['type'] ) ? $file['type'] : '' ) );
			$editorial = is_string( $editorial ) ? self::slug( $editorial ) : '';

			if ( self::GLOBAL_STYLES === $editorial || self::GLOBAL_STYLES === $type ) {
				if ( null !== $global ) {
					$report[] = self::note( 'envato.global-styles-extra', array( 'source' => $source ), 'warning' );
					continue;
				}
				if ( ! is_array( $file ) ) {
					$report[] = self::note( 'envato.missing-source', array( 'source' => $source ), 'warning' );
					continue;
				}
				$global = self::settings_of( $file );
				continue;
			}

			if ( ! is_array( $file ) ) {
				++$skipped;
				$excluded = array_key_exists( self::META_INCLUDE, $m_meta ) && ! self::truthy( $m_meta[ self::META_INCLUDE ] );
				$report[] = self::note( $excluded ? 'envato.not-included' : 'envato.missing-source', array( 'source' => $source ), $excluded ? 'info' : 'warning' );
				continue;
			}
			if ( ! self::valid_content( isset( $file['content'] ) ? $file['content'] : null ) ) {
				++$skipped;
				$report[] = self::note( 'envato.invalid-content', array( 'source' => $source ), 'error' );
				continue;
			}

			list( $doc_type, $original ) = self::document_type( $type, $editorial, $env['registered'] );
			if ( '' !== $original ) {
				$unknown_types[ $original ] = ( isset( $unknown_types[ $original ] ) ? $unknown_types[ $original ] : 0 ) + 1;
				$report[] = self::note( 'envato.unknown-type', array( 'source' => $source, 'type' => $original ), 'warning' );
			}

			$key               = self::template_key( $source, $used_keys );
			$used_keys[ $key ] = true;
			$by_type[ $doc_type ] = ( isset( $by_type[ $doc_type ] ) ? $by_type[ $doc_type ] : 0 ) + 1;

			$name = self::text( isset( $entry['name'] ) ? $entry['name'] : ( isset( $file['title'] ) ? $file['title'] : '' ) );
			$item = array(
				'title'     => '' !== $name ? $name : $key,
				'doc_type'  => $doc_type,
				'thumbnail' => '',
			);
			if ( '' !== $original ) {
				$item['original_type'] = $original;
			}

			$settings = self::settings_of( $file );
			$page     = self::reading( self::META_PAGE_TEMPLATE, $m_meta, $f_meta, $source, $report );
			if ( is_string( $page ) && in_array( $page, self::PAGE_TEMPLATES, true ) && ! isset( $settings['template'] ) ) {
				$settings['template'] = $page;
				$report[]             = self::note( 'envato.page-template', array( 'source' => $source, 'template' => $page ), 'info' );
			}

			$location = '' === $original ? (string) call_user_func( $env['location'], $doc_type ) : '';
			$raw      = self::reading( self::META_CONDITIONS, $m_meta, $f_meta, $source, $report );
			if ( '' !== $location ) {
				$item['location'] = $location;
				$conditions       = self::conditions( $raw, $source, $report );
				if ( $conditions ) {
					$first = self::first_general( $conditions );
					if ( null !== $first ) {
						$item['conditions'] = array( $first );
					}
					$theme_builder[ $key ] = array(
						'conditions' => $conditions,
						'fields'     => array(),
					);
				}
			} elseif ( ! empty( $raw ) ) {
				$report[] = self::note( 'envato.conditions-ignored', array( 'source' => $source, 'type' => '' !== $original ? $original : $doc_type ), 'warning' );
			}

			$dropped = self::dropped_keys( $m_meta, $f_meta );
			if ( $dropped ) {
				$report[] = self::note( 'envato.metadata-dropped', array( 'source' => $source, 'keys' => $dropped ), 'info' );
			}

			$templates[ $key ]                        = $item;
			$out_files[ 'templates/' . $key . '.json' ] = array(
				'content'  => array_values( $file['content'] ),
				'settings' => $settings,
				'metadata' => array(),
			);
		}

		$kit = self::kit_hash( isset( $manifest['title'] ) && is_string( $manifest['title'] ) ? $manifest['title'] : '', $sources );

		$out_manifest = array(
			'name'              => self::name( $title ),
			'title'             => $title,
			'description'       => '',
			'author'            => '',
			'version'           => self::KIT_VERSION,
			'elementor_version' => '',
			'created'           => '',
			'thumbnail'         => '',
			'site'              => '',
			'templates'         => $templates,
		);
		if ( null !== $global ) {
			foreach ( self::IDENTITY_KEYS as $identity ) {
				unset( $global[ $identity ] );
			}
			$out_files['site-settings.json'] = array( 'settings' => $global );
			$out_manifest['site-settings']   = array(
				'theme'              => false,
				'globalColors'       => true,
				'globalFonts'        => true,
				'themeStyleSettings' => true,
				'generalSettings'    => true,
				'experiments'        => false,
			);
		}
		$out_manifest[ Module::MANIFEST_KEY ] = array(
			'v'      => Module::FORMAT,
			'format' => self::FORMAT,
			'kit'    => $kit,
			'parts'  => $theme_builder ? array( 'theme-builder' => 1 ) : array(),
		);
		if ( $theme_builder ) {
			$out_files[ Module::KIT_FOLDER . 'theme-builder.json' ] = $theme_builder;
		}
		ksort( $by_type, SORT_STRING );
		ksort( $unknown_types, SORT_STRING );

		return array(
			'manifest' => $out_manifest,
			'files'    => $out_files,
			'analysis' => array(
				'format'        => self::FORMAT,
				'kit'           => $kit,
				'title'         => $title,
				'requested'     => array(
					'plugins'     => self::plugins( isset( $manifest['required_plugins'] ) ? $manifest['required_plugins'] : array() ),
					'theme'       => '',
					'experiments' => array(),
				),
				'templates'     => array(
					'total'         => count( $templates ),
					'by_type'       => $by_type,
					'unknown_types' => $unknown_types,
					'skipped'       => $skipped,
				),
				'global_styles' => null !== $global,
			),
			'report'   => $report,
		);
	}

	/**
	 * Huella del kit (§9.6, D7): `sha1( título | nombres de archivo ordenados )`, la misma que calcula el
	 * normalizador para el origen. No depende del orden de las plantillas.
	 *
	 * @param string   $title   Título del kit, tal como viene en el manifiesto.
	 * @param string[] $sources Rutas de las plantillas.
	 */
	public static function kit_hash( string $title, array $sources ): string {
		$names = array_map(
			function ( $source ) {
				return basename( (string) $source );
			},
			$sources
		);
		sort( $names, SORT_STRING );
		return sha1( $title . '|' . implode( ',', $names ) );
	}

	/**
	 * Una condición `tipo/nombre/subnombre/id` (o ya como array) en la forma del Theme Builder, o null si no es
	 * válida: `tipo` es `include` o `exclude`, los nombres son claves y el `sub_id`, vacío o un número.
	 *
	 * @param mixed $condition Cadena o array `{ type, name, sub_name, sub_id }`.
	 */
	public static function parse_condition( $condition ): ?array {
		if ( is_array( $condition ) ) {
			$parts = array(
				isset( $condition['type'] ) ? $condition['type'] : '',
				isset( $condition['name'] ) ? $condition['name'] : '',
				isset( $condition['sub_name'] ) ? $condition['sub_name'] : '',
				isset( $condition['sub_id'] ) ? $condition['sub_id'] : '',
			);
		} elseif ( is_string( $condition ) ) {
			$parts = array_pad( explode( '/', trim( $condition, " \t/" ), 4 ), 4, '' );
		} else {
			return null;
		}
		list( $type, $name, $sub_name, $sub_id ) = array_map(
			function ( $part ) {
				return is_scalar( $part ) ? trim( (string) $part ) : "\0";
			},
			$parts
		);
		if ( ! in_array( $type, array( 'include', 'exclude' ), true ) || 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/', $name ) ) {
			return null;
		}
		if ( '' !== $sub_name && 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/', $sub_name ) ) {
			return null;
		}
		if ( '' !== $sub_id && 1 !== preg_match( '/^[0-9]{1,20}$/', $sub_id ) ) {
			return null;
		}
		return array(
			'type'     => $type,
			'name'     => $name,
			'sub_name' => $sub_name,
			'sub_id'   => $sub_id,
		);
	}

	/**
	 * Clave de una plantilla: el nombre del archivo como clave (`sanitize_key`), con `-2`, `-3`… si se repite.
	 *
	 * @param string $source Ruta del archivo.
	 * @param array  $used   Claves ya usadas (clave => true).
	 */
	public static function template_key( string $source, array $used = array() ): string {
		$base = substr( self::key( basename( $source, '.json' ) ), 0, self::MAX_KEY );
		$base = '' !== $base ? $base : 'plantilla';
		$key  = $base;
		for ( $n = 2; isset( $used[ $key ] ); ++$n ) {
			$key = $base . '-' . $n;
		}
		return $key;
	}

	/** Plugins pedidos por el kit, para el análisis: nombre, versión y archivo, sin enlaces ni autores. */
	public static function plugins( $required ): array {
		$out = array();
		if ( ! is_array( $required ) ) {
			return $out;
		}
		foreach ( $required as $plugin ) {
			if ( is_string( $plugin ) ) {
				$plugin = array( 'name' => $plugin );
			}
			if ( ! is_array( $plugin ) ) {
				continue;
			}
			$name = self::text( isset( $plugin['name'] ) ? $plugin['name'] : '' );
			$file = isset( $plugin['file'] ) && is_string( $plugin['file'] ) && 1 === preg_match( '#^[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)?\.php$#', $plugin['file'] ) ? $plugin['file'] : '';
			if ( '' === $name && '' === $file ) {
				continue;
			}
			$out[] = array(
				'name'    => $name,
				'version' => self::text( isset( $plugin['version'] ) ? $plugin['version'] : '' ),
				'file'    => $file,
			);
			if ( count( $out ) >= 100 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Lee una clave del formato en las dos fuentes. Manda el manifiesto; si las dos existen y difieren, se anota.
	 *
	 * @return mixed Null si no está en ninguna.
	 */
	private static function reading( string $key, array $m_meta, array $f_meta, string $source, array &$report ) {
		$in_manifest = array_key_exists( $key, $m_meta ) && null !== $m_meta[ $key ] && '' !== $m_meta[ $key ];
		$in_file     = array_key_exists( $key, $f_meta ) && null !== $f_meta[ $key ] && '' !== $f_meta[ $key ];
		if ( $in_manifest && $in_file && self::canonical( $m_meta[ $key ] ) !== self::canonical( $f_meta[ $key ] ) ) {
			$report[] = self::note( 'envato.metadata-mismatch', array( 'source' => $source, 'key' => $key ), 'warning' );
		}
		if ( $in_manifest ) {
			return $m_meta[ $key ];
		}
		return $in_file ? $f_meta[ $key ] : null;
	}

	/** Condiciones válidas, sin repetir; las inválidas se anotan. */
	private static function conditions( $raw, string $source, array &$report ): array {
		if ( is_string( $raw ) ) {
			$raw = array( $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out  = array();
		$seen = array();
		foreach ( $raw as $condition ) {
			$parsed = self::parse_condition( $condition );
			if ( null === $parsed ) {
				$report[] = self::note( 'envato.condition-invalid', array( 'source' => $source ), 'warning' );
				continue;
			}
			$id = implode( '/', $parsed );
			if ( ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$out[]       = $parsed;
			}
		}
		return $out;
	}

	/** La primera `include` sin `sub_id`: es lo único que el manifiesto nativo puede llevar sin traducir (V-24). */
	private static function first_general( array $conditions ): ?array {
		foreach ( $conditions as $condition ) {
			if ( 'include' === $condition['type'] && '' === $condition['sub_id'] ) {
				return $condition;
			}
		}
		return null;
	}

	/** Claves de `metadata` que no se traducen (para que D17 las revise); las del formato no se anotan. */
	private static function dropped_keys( array $m_meta, array $f_meta ): array {
		$known = array( self::META_TEMPLATE_TYPE, self::META_CONDITIONS, self::META_INCLUDE, self::META_PAGE_TEMPLATE );
		$keys  = array();
		foreach ( array_merge( array_keys( $m_meta ), array_keys( $f_meta ) ) as $key ) {
			$key = (string) $key;
			if ( ! in_array( $key, $known, true ) && 1 === preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $key ) ) {
				$keys[ $key ] = true;
			}
		}
		$keys = array_keys( $keys );
		sort( $keys, SORT_STRING );
		return array_slice( $keys, 0, 50 );
	}

	/** Ajustes de página de un archivo (`page_settings` de la biblioteca o `settings`), siempre como array. */
	private static function settings_of( array $file ): array {
		foreach ( array( 'page_settings', 'settings' ) as $key ) {
			if ( isset( $file[ $key ] ) && is_array( $file[ $key ] ) ) {
				return $file[ $key ];
			}
		}
		return array();
	}

	/** `content` debe ser una lista de elementos con `elType` (un tipo erróneo rompería la petición final, CV-22). */
	private static function valid_content( $content ): bool {
		if ( ! is_array( $content ) ) {
			return false;
		}
		$i = 0;
		foreach ( $content as $key => $element ) {
			if ( $key !== $i++ || ! is_array( $element ) || ! isset( $element['elType'] ) || ! is_string( $element['elType'] ) || '' === $element['elType'] ) {
				return false;
			}
		}
		return true;
	}

	/** Ruta relativa válida (sin `..`, sin raíz, sin `\`), o ''. */
	private static function clean_path( string $path ): string {
		$path = trim( $path );
		if ( false !== strpos( $path, '\\' ) || false !== strpos( $path, "\0" ) || 0 === strpos( $path, '/' ) ) {
			return '';
		}
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return '';
			}
		}
		return 1 === preg_match( self::SOURCE_PATTERN, $path ) ? $path : '';
	}

	/** Lector de archivos: por ruta exacta y, si no, sin distinguir mayúsculas. */
	private static function reader( $files ): callable {
		if ( is_callable( $files ) ) {
			return function ( string $path ) use ( $files ) {
				$data = call_user_func( $files, $path );
				return self::decoded( $data );
			};
		}
		$files = is_array( $files ) ? $files : array();
		$lower = array();
		foreach ( array_keys( $files ) as $name ) {
			$lower[ strtolower( (string) $name ) ] = $name;
		}
		return function ( string $path ) use ( $files, $lower ) {
			if ( array_key_exists( $path, $files ) ) {
				return self::decoded( $files[ $path ] );
			}
			$key = strtolower( $path );
			return isset( $lower[ $key ] ) ? self::decoded( $files[ $lower[ $key ] ] ) : null;
		};
	}

	/** @return array|null */
	private static function decoded( $data ) {
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true, 256 );
		}
		return is_array( $data ) ? $data : null;
	}

	private static function env( array $env ): array {
		if ( ! isset( $env['registered'] ) || ! is_callable( $env['registered'] ) ) {
			$env['registered'] = array( __CLASS__, 'is_registered' );
		}
		if ( ! isset( $env['location'] ) || ! is_callable( $env['location'] ) ) {
			$env['location'] = array( __CLASS__, 'location_of' );
		}
		return $env;
	}

	/**
	 * ¿Existe el tipo de documento en este sitio? Sin núcleo cargado se responde que sí y decide el runner de
	 * plantillas, que trata igual un tipo que no existe.
	 */
	public static function is_registered( string $doc_type ): bool {
		$manager = self::documents_manager();
		return null === $manager ? true : false !== $manager->get_document_type( $doc_type, false );
	}

	/** Ubicación del Theme Builder de un tipo de documento, o '' si no tiene. */
	public static function location_of( string $doc_type ): string {
		$manager = self::documents_manager();
		if ( null !== $manager ) {
			$class = $manager->get_document_type( $doc_type, false );
			if ( is_string( $class ) && is_callable( array( $class, 'get_property' ) ) ) {
				$location = call_user_func( array( $class, 'get_property' ), 'location' );
				if ( is_string( $location ) && '' !== $location ) {
					return $location;
				}
			}
		}
		return isset( self::LOCATIONS[ $doc_type ] ) ? self::LOCATIONS[ $doc_type ] : '';
	}

	/** @return object|null */
	private static function documents_manager() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->documents ) ) {
			return null;
		}
		return \Elementor\Plugin::$instance->documents;
	}

	/** Clave como `sanitize_key()`: minúsculas, letras, números, `_` y `-`. */
	private static function key( string $value ): string {
		return (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
	}

	/** Slug de un tipo: clave sin espacios ni otros caracteres. */
	/**
	 * Tipo de documento de una plantilla y el tipo original que se conserva (CC-4).
	 *
	 * Manda `type`, como en la lectura del núcleo (D17). El editorial (`template_type`) sólo decide si la entrada no
	 * trae `type` (directamente o por `EDITORIAL_TYPES`). Un editorial que este sitio no conoce queda como tipo
	 * original aunque `type` exista (`section`): el informe lo dice y la plantilla no se toma por una sección más.
	 *
	 * @param string   $type       `type` de la entrada (slug).
	 * @param string   $editorial  `template_type` (slug), sin `global-styles`.
	 * @param callable $registered `( string $doc_type ): bool`.
	 *
	 * @return array{0: string, 1: string} `[ doc_type, tipo original o '' ]`.
	 */
	public static function document_type( string $type, string $editorial, callable $registered ): array {
		$known = function ( string $candidate ) use ( $registered ): string {
			if ( '' === $candidate ) {
				return '';
			}
			if ( call_user_func( $registered, $candidate ) ) {
				return $candidate;
			}
			$mapped = isset( self::EDITORIAL_TYPES[ $candidate ] ) ? self::EDITORIAL_TYPES[ $candidate ] : '';
			return '' !== $mapped && call_user_func( $registered, $mapped ) ? $mapped : '';
		};
		if ( '' !== $type ) {
			if ( ! call_user_func( $registered, $type ) ) {
				return array( self::FALLBACK_TYPE, $type );
			}
			return array( $type, '' !== $editorial && '' === $known( $editorial ) ? $editorial : '' );
		}
		$doc_type = $known( $editorial );
		if ( '' !== $doc_type ) {
			return array( $doc_type, '' );
		}
		return array( self::FALLBACK_TYPE, '' !== $editorial ? $editorial : 'desconocido' );
	}

	private static function slug( $value ): string {
		return is_string( $value ) ? self::key( trim( $value ) ) : '';
	}

	/** Nombre del kit (`manifest.name`): minúsculas y guiones. */
	private static function name( string $title ): string {
		$name = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $title ) ), '-' );
		return '' !== $name ? substr( $name, 0, 100 ) : 'template-kit';
	}

	/** Texto plano corto: sin etiquetas, sin saltos y con un tope. */
	private static function text( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( (string) $value ) ) );
		if ( strlen( $text ) <= self::MAX_TEXT ) {
			return $text;
		}
		return function_exists( 'mb_strcut' ) ? mb_strcut( $text, 0, self::MAX_TEXT, 'UTF-8' ) : substr( $text, 0, self::MAX_TEXT );
	}

	private static function truthy( $value ): bool {
		return ! in_array( is_string( $value ) ? strtolower( trim( $value ) ) : $value, array( false, 0, '0', '', 'false', 'no', null ), true );
	}

	private static function canonical( $value ): string {
		if ( is_array( $value ) ) {
			return (string) json_encode( array_values( $value ) );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function note( string $code, array $data, string $level ): array {
		return array(
			'code'  => $code,
			'level' => $level,
			'data'  => $data,
		);
	}
}
