<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * Mapa de marca (docs/MARCA_PROPIA.md, D-6): cómo sale con nombre propio lo que el motor llama «elementor» y cómo se
 * acepta de vuelta. Lo usan el MCP con nombre propio (`Digitalisimo\Elements\Mcp\Brand_Server`), los alias de URL del
 * escritorio (`Admin_Urls`) y el Asistente de IA Tools si Elements está cargado.
 *
 * Salida (`out()`), en este orden:
 * - `elementor://` → `digitalisimo://` (recursos);
 * - `elementor/<herramienta>` y `elementor-<herramienta>` → `digitalisimo-<herramienta>`, sólo con las herramientas,
 *   recursos y prompts del registro del MCP (nunca una clase CSS ni un gancho que no lo sean);
 * - tipos de contenido y taxonomías registrados `elementor_<x>` → `digitalisimo_<x>`, sólo si ese nombre no existe;
 * - metas `_elementor_<x>` → `_dg_<x>`;
 * - en URL: `action=elementor` → `action=digitalisimo` y `page=elementor…` → `page=digitalisimo-motor…`;
 * - el nombre «Elementor» en un texto → «DIGITALÍSIMO Elements» (las mismas reglas que la identidad).
 *
 * Entrada (`in()`): deshace sólo lo que la salida produce (`digitalisimo://`, los `digitalisimo_<x>` cuyo original está
 * registrado, `_dg_<x>`, `action=digitalisimo`, `page=digitalisimo-motor…`), así que los nombres propios de verdad
 * (`digitalisimo_query`, `_digitalisimo_actions`…) nunca cambian y los nombres viejos siguen valiendo tal cual.
 *
 * Las rutas de archivos (`/digitalisimo-elements/elementor/…`, `uploads/elementor/…`) no se tocan aquí: las resuelven la
 * carpeta neutra del ZIP (D-9) y la capa de la fase 2.
 */
final class Map {
	const NAME          = 'DIGITALÍSIMO Elements';
	const META_OUT      = '_dg_';
	const PAGE_OUT      = 'digitalisimo-motor';
	const ACTION_OUT    = 'digitalisimo';
	const TYPE_FALLBACK = array( 'elementor_library', 'elementor_library_type', 'elementor_library_category', 'elementor_snippet', 'elementor_font', 'elementor_font_type', 'elementor_icons', 'elementor_component' );

	/** @var array<string,string>|null tipos y taxonomías: viejo => nuevo */
	private static $types = null;
	/** @var array<string,true>|null nombres de herramienta del motor (sin el prefijo), p. ej. `create-page` */
	private static $tools = null;

	/** Para pruebas y para quien ya conoce la lista: nombres de herramienta del motor sin prefijo. */
	public static function set_tools( ?array $slugs ): void {
		self::$tools = null === $slugs ? null : array_fill_keys( array_map( 'strval', $slugs ), true );
	}

	/** Para pruebas: tipos y taxonomías (viejo => nuevo); null vuelve a leerlos de WordPress. */
	public static function set_types( ?array $types ): void {
		self::$types = $types;
	}

	/** @return array<string,true> */
	public static function tools(): array {
		if ( null !== self::$tools ) {
			return self::$tools;
		}
		$names = array();
		$registry = 'Elementor\\MCP\\Composer\\Mcp\\Registry';
		if ( class_exists( $registry ) ) {
			$reg   = call_user_func( array( $registry, 'instance' ) );
			$names = array_merge( (array) $reg->get_tools(), (array) $reg->get_resources(), (array) $reg->get_prompts() );
		}
		if ( function_exists( 'wp_get_abilities' ) ) {
			foreach ( (array) wp_get_abilities() as $ability ) {
				if ( is_object( $ability ) && method_exists( $ability, 'get_name' ) ) {
					$names[] = (string) $ability->get_name();
				}
			}
		}
		$slugs = array();
		foreach ( $names as $name ) {
			if ( is_string( $name ) && 0 === strpos( $name, 'elementor/' ) ) {
				$slugs[ substr( $name, 10 ) ] = true;
			}
		}
		// Sin abilities cargadas (p. ej. antes de wp_abilities_api_init) no se memoriza una lista vacía.
		if ( $slugs ) {
			self::$tools = $slugs;
		}
		return $slugs;
	}

	/** @return array<string,string> tipos y taxonomías `elementor_*` registrados => `digitalisimo_*`. */
	public static function types(): array {
		if ( null !== self::$types ) {
			return self::$types;
		}
		$names = self::TYPE_FALLBACK;
		$taken = array();
		if ( function_exists( 'get_post_types' ) ) {
			$names = array_merge( $names, array_values( (array) get_post_types() ) );
			$taken = array_merge( $taken, array_values( (array) get_post_types() ) );
		}
		if ( function_exists( 'get_taxonomies' ) ) {
			$names = array_merge( $names, array_values( (array) get_taxonomies() ) );
			$taken = array_merge( $taken, array_values( (array) get_taxonomies() ) );
		}
		$taken = array_fill_keys( $taken, true );
		$map   = array();
		foreach ( array_unique( $names ) as $name ) {
			if ( is_string( $name ) && 0 === strpos( $name, 'elementor_' ) ) {
				$new = 'digitalisimo_' . substr( $name, 10 );
				if ( ! isset( $taken[ $new ] ) ) {
					$map[ $name ] = $new;
				}
			}
		}
		// Primero los más largos: `elementor_library_type` antes que `elementor_library`.
		uksort(
			$map,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		if ( did_action( 'init' ) ) {
			self::$types = $map;
		}
		return $map;
	}

	/** Nombre de herramienta MCP con marca propia: `elementor-x` → `digitalisimo-x`; lo demás, igual. */
	public static function tool_name( string $name ): string {
		return 0 === strpos( $name, 'elementor-' ) ? 'digitalisimo-' . substr( $name, 10 ) : $name;
	}

	/** URI de recurso con marca propia. */
	public static function uri( string $uri ): string {
		return 0 === stripos( $uri, 'elementor://' ) ? 'digitalisimo://' . substr( $uri, 12 ) : $uri;
	}

	/** Texto de salida (ver la cabecera). */
	public static function out( string $text ): string {
		if ( false === stripos( $text, 'elementor' ) ) {
			return $text;
		}
		$text = (string) preg_replace( '#\belementor://#i', 'digitalisimo://', $text );
		$tools = self::tools();
		if ( $tools ) {
			$text = (string) preg_replace_callback(
				'#(?<![\w/.-])elementor[/-]([a-z0-9][a-z0-9-]*)(?![\w-])#',
				static function ( $m ) use ( $tools ) {
					return isset( $tools[ $m[1] ] ) ? 'digitalisimo-' . $m[1] : $m[0];
				},
				$text
			);
		}
		$types = self::types();
		if ( $types ) {
			$text = (string) preg_replace_callback(
				'#(?<![A-Za-z0-9_])(elementor_[a-z0-9_]+)(?![A-Za-z0-9_])#',
				static function ( $m ) use ( $types ) {
					return $types[ $m[1] ] ?? $m[0];
				},
				$text
			);
		}
		$text = (string) preg_replace( '#(?<![A-Za-z0-9])_elementor_([a-z0-9_]+)#', self::META_OUT . '$1', $text );
		$text = (string) preg_replace( '#([?&]|&amp;|\\\\u0026)action=elementor(?=$|[&\#"\'\s<\\\\])#', '$1action=' . self::ACTION_OUT, $text );
		$text = (string) preg_replace( '#([?&]|&amp;|\\\\u0026)page=elementor(?=[\w-]|$|[&\#"\'\s<\\\\])#', '$1page=' . self::PAGE_OUT, $text );
		return self::brand( $text );
	}

	/** Texto de entrada: deshace lo que produce out(); un nombre viejo pasa igual. */
	public static function in( string $text ): string {
		if ( false === stripos( $text, 'digitalisimo' ) && false === strpos( $text, self::META_OUT ) ) {
			return $text;
		}
		$text  = (string) preg_replace( '#\bdigitalisimo://#i', 'elementor://', $text );
		$types = array_flip( self::types() );
		if ( $types ) {
			$text = (string) preg_replace_callback(
				'#(?<![A-Za-z0-9_])(digitalisimo_[a-z0-9_]+)(?![A-Za-z0-9_])#',
				static function ( $m ) use ( $types ) {
					return $types[ $m[1] ] ?? $m[0];
				},
				$text
			);
		}
		$text = (string) preg_replace( '#(?<![A-Za-z0-9_])' . preg_quote( self::META_OUT, '#' ) . '([a-z0-9_]+)#', '_elementor_$1', $text );
		$text = (string) preg_replace( '#([?&]|&amp;)action=' . self::ACTION_OUT . '(?=$|[&\#"\'\s<])#', '$1action=elementor', $text );
		$text = (string) preg_replace( '#([?&]|&amp;)page=' . self::PAGE_OUT . '#', '$1page=elementor', $text );
		return $text;
	}

	/** «Elementor» en un texto, con las reglas de la identidad (o las mismas, si la identidad no está cargada). */
	public static function brand( string $text ): string {
		if ( false === strpos( $text, 'lementor' ) ) {
			return $text;
		}
		if ( class_exists( 'Digitalisimo_Elements_Identity', false ) ) {
			return (string) \Digitalisimo_Elements_Identity::brand( $text );
		}
		return (string) preg_replace( array( '/\bElementor Pro\b/u', '/\bElementor\b/u' ), self::NAME, $text );
	}

	/**
	 * out() en profundidad: valores y claves de arreglos y objetos simples. Un WP_Error sale con sus mensajes mapeados.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function out_deep( $value, int $depth = 0 ) {
		return self::deep( $value, array( __CLASS__, 'out' ), $depth );
	}

	/**
	 * in() en profundidad.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function in_deep( $value, int $depth = 0 ) {
		return self::deep( $value, array( __CLASS__, 'in' ), $depth );
	}

	/**
	 * @param mixed    $value
	 * @param callable $fn
	 * @return mixed
	 */
	private static function deep( $value, $fn, int $depth ) {
		if ( $depth > 64 ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return $fn( $value );
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ is_string( $k ) ? $fn( $k ) : $k ] = self::deep( $v, $fn, $depth + 1 );
			}
			return $out;
		}
		if ( $value instanceof \stdClass ) {
			$out = new \stdClass();
			foreach ( get_object_vars( $value ) as $k => $v ) {
				$out->{ $fn( (string) $k ) } = self::deep( $v, $fn, $depth + 1 );
			}
			return $out;
		}
		if ( class_exists( 'WP_Error', false ) && $value instanceof \WP_Error ) {
			$error = new \WP_Error();
			foreach ( $value->get_error_codes() as $code ) {
				foreach ( $value->get_error_messages( $code ) as $message ) {
					$error->add( $code, $fn( (string) $message ), self::deep( $value->get_error_data( $code ), $fn, $depth + 1 ) );
				}
			}
			return $error;
		}
		return $value;
	}
}
