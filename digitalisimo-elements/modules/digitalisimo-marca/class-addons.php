<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * Complementos de terceros que extienden el motor (D-5 de docs/MARCA_PROPIA.md): con alguno activo, la marca propia se
 * bloquea por defecto, porque pueden construir nombres del motor en su JS (`window['elementor'+'Frontend']`), leer clases
 * desde JSON o pintar marcado propio con las clases viejas. El administrador puede forzarla bajo su responsabilidad.
 *
 * Se detecta por plugin activo (en el sitio y en la red, también los mu-plugins no; nunca los propios `digitalisimo*` ni
 * el Elementor original, que tiene su propio estado):
 * - cabeceras `Elementor tested up to`, `Elementor Pro tested up to` o `Requires Plugins` con `elementor`;
 * - funciones de ese plugin enganchadas a los ganchos de extensión del motor (`HOOKS`), vistas por reflexión.
 * Y el tema activo (o su padre) cuya carpeta nombra el motor (Hello Elementor y sus hijos): su marcado y sus rutas lo
 * llevan en el nombre; se resuelve pasando al tema DIGITALÍSIMO (D-11, «Migrar» lo hace).
 *
 * El resultado se guarda en un transitorio del sitio con la huella de los plugins activos y del tema, así que sólo se
 * recalcula cuando cambian.
 */
final class Addons {
	const CACHE   = 'digitalisimo_elements_marca_addons';
	const HEADERS = array(
		'tested'     => 'Elementor tested up to',
		'pro_tested' => 'Elementor Pro tested up to',
		'requires'   => 'Requires Plugins',
	);
	const HOOKS = array(
		'elementor/widgets/register',
		'elementor/widgets/widgets_registered',
		'elementor/elements/categories_registered',
		'elementor/controls/register',
		'elementor/dynamic_tags/register',
		'elementor/init',
		'elementor/loaded',
		'elementor/frontend/after_register_scripts',
		'elementor/frontend/after_enqueue_styles',
		'elementor/frontend/after_register_styles',
		'elementor/editor/after_enqueue_scripts',
	);
	/** Plugins que no cuentan: los propios y el núcleo original (con estado propio). */
	const OWN = '#^(digitalisimo[^/]*|elementor|elementor-pro|pro-elements)/#i';

	/**
	 * @return array<int,array{file:string,name:string,why:string}>
	 */
	public static function detect( bool $fresh = false ): array {
		$key = self::fingerprint();
		if ( ! $fresh && function_exists( 'get_transient' ) ) {
			$cached = get_transient( self::CACHE );
			if ( is_array( $cached ) && ( $cached['key'] ?? '' ) === $key && is_array( $cached['list'] ?? null ) ) {
				return $cached['list'];
			}
		}
		$list = array_merge( self::by_headers(), self::by_hooks(), self::by_theme() );
		$seen = array();
		$out  = array();
		foreach ( $list as $row ) {
			if ( isset( $seen[ $row['file'] ] ) ) {
				continue;
			}
			$seen[ $row['file'] ] = true;
			$out[]                = $row;
		}
		// Los ganchos sólo se conocen cuando todo cargó: antes de `init` no se memoriza nada.
		if ( function_exists( 'set_transient' ) && function_exists( 'did_action' ) && did_action( 'init' ) ) {
			set_transient( self::CACHE, array( 'key' => $key, 'list' => $out ), 12 * HOUR_IN_SECONDS );
		}
		return $out;
	}

	/**
	 * La lista en caché si sigue valiendo (mismos plugins y tema), o null. Antes de `init` es lo único fiable: los ganchos
	 * de los plugins que cargan después todavía no existen.
	 *
	 * @return array<int,array{file:string,name:string,why:string}>|null
	 */
	public static function cached(): ?array {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$cached = get_transient( self::CACHE );
		return is_array( $cached ) && ( $cached['key'] ?? '' ) === self::fingerprint() && is_array( $cached['list'] ?? null ) ? $cached['list'] : null;
	}

	/** @return string[] plugins activos en el sitio y en la red (archivo relativo a plugins/). */
	public static function active_plugins(): array {
		$active = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		return array_values( array_unique( array_filter( array_map( 'strval', $active ) ) ) );
	}

	private static function fingerprint(): string {
		$theme = function_exists( 'get_template' ) ? get_template() . '|' . get_stylesheet() : '';
		return md5( implode( ',', self::active_plugins() ) . '#' . $theme );
	}

	/** @return array<int,array{file:string,name:string,why:string}> */
	private static function by_headers(): array {
		$out = array();
		if ( ! defined( 'WP_PLUGIN_DIR' ) || ! function_exists( 'get_file_data' ) ) {
			return $out;
		}
		foreach ( self::active_plugins() as $file ) {
			if ( preg_match( self::OWN, $file ) ) {
				continue;
			}
			$path = WP_PLUGIN_DIR . '/' . $file;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$data = get_file_data( $path, array_merge( array( 'name' => 'Plugin Name' ), self::HEADERS ), 'plugin' );
			$why  = '';
			if ( '' !== trim( (string) $data['tested'] ) || '' !== trim( (string) $data['pro_tested'] ) ) {
				$why = 'declara compatibilidad con el constructor en su cabecera';
			} elseif ( preg_match( '/(^|[\s,])elementor(-pro)?($|[\s,])/i', (string) $data['requires'] ) ) {
				$why = 'requiere el constructor (Requires Plugins)';
			}
			if ( '' !== $why ) {
				$out[] = array( 'file' => $file, 'name' => '' !== trim( (string) $data['name'] ) ? trim( (string) $data['name'] ) : $file, 'why' => $why );
			}
		}
		return $out;
	}

	/** @return array<int,array{file:string,name:string,why:string}> */
	private static function by_hooks(): array {
		global $wp_filter;
		$out = array();
		if ( ! defined( 'WP_PLUGIN_DIR' ) || ! is_array( $wp_filter ) ) {
			return $out;
		}
		$plugins = wp_normalize_path_compat( WP_PLUGIN_DIR ) . '/';
		$names   = array();
		foreach ( self::active_plugins() as $file ) {
			$names[ dirname( $file ) ] = $file;
		}
		foreach ( self::HOOKS as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			$callbacks = is_object( $wp_filter[ $hook ] ) && isset( $wp_filter[ $hook ]->callbacks ) ? $wp_filter[ $hook ]->callbacks : (array) $wp_filter[ $hook ];
			foreach ( (array) $callbacks as $priority ) {
				foreach ( (array) $priority as $cb ) {
					$path = self::callback_file( $cb['function'] ?? null );
					if ( '' === $path || 0 !== strpos( $path, $plugins ) ) {
						continue;
					}
					$dir = strtok( substr( $path, strlen( $plugins ) ), '/' );
					if ( ! is_string( $dir ) || preg_match( self::OWN, $dir . '/' ) || ! isset( $names[ $dir ] ) ) {
						continue;
					}
					$file  = $names[ $dir ];
					$out[] = array( 'file' => $file, 'name' => self::plugin_name( $file ), 'why' => 'se engancha a ' . $hook );
				}
			}
		}
		return $out;
	}

	/** @return array<int,array{file:string,name:string,why:string}> */
	private static function by_theme(): array {
		if ( ! function_exists( 'get_template' ) ) {
			return array();
		}
		$out = array();
		foreach ( array_unique( array( get_stylesheet(), get_template() ) ) as $slug ) {
			if ( is_string( $slug ) && false !== stripos( $slug, 'elementor' ) ) {
				$out[] = array( 'file' => 'themes/' . $slug, 'name' => 'Tema ' . $slug, 'why' => 'el tema activo lleva el nombre del motor en sus rutas y clases: pásalo al tema DIGITALÍSIMO con «Migrar»' );
			}
		}
		return $out;
	}

	private static function plugin_name( string $file ): string {
		if ( defined( 'WP_PLUGIN_DIR' ) && function_exists( 'get_file_data' ) && is_readable( WP_PLUGIN_DIR . '/' . $file ) ) {
			$name = trim( (string) ( get_file_data( WP_PLUGIN_DIR . '/' . $file, array( 'name' => 'Plugin Name' ), 'plugin' )['name'] ?? '' ) );
			if ( '' !== $name ) {
				return $name;
			}
		}
		return $file;
	}

	/** Archivo donde vive una función enganchada ('' si no se sabe). */
	private static function callback_file( $fn ): string {
		try {
			if ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
				$fn = explode( '::', $fn, 2 );
			}
			if ( is_array( $fn ) && 2 === count( $fn ) ) {
				$ref = new \ReflectionMethod( is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0], (string) $fn[1] );
			} elseif ( $fn instanceof \Closure || ( is_string( $fn ) && function_exists( $fn ) ) ) {
				$ref = new \ReflectionFunction( $fn );
			} elseif ( is_object( $fn ) && method_exists( $fn, '__invoke' ) ) {
				$ref = new \ReflectionMethod( $fn, '__invoke' );
			} else {
				return '';
			}
			$file = $ref->getFileName();
			return is_string( $file ) ? wp_normalize_path_compat( $file ) : '';
		} catch ( \Throwable $e ) {
			return '';
		}
	}
}

/** `wp_normalize_path()` si existe; si no, barras normales. */
function wp_normalize_path_compat( string $path ): string {
	return function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $path ) : str_replace( '\\', '/', $path );
}
