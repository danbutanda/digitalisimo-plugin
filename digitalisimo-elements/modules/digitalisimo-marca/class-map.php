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
 * - metas del motor `_elementor_<x>` → `_dg_<x>` (sólo las de METAS);
 * - en URL, sólo con la marca propia en vigor (`active()`): `action=elementor` → `action=digitalisimo` y
 *   `page=elementor…` → `page=digitalisimo-motor…`;
 * - el nombre «Elementor» en un texto → «DIGITALÍSIMO Elements» (las mismas reglas que la identidad).
 *
 * Entrada (`in()`): deshace sólo lo que la salida produce (`digitalisimo://`, los `digitalisimo_<x>` cuyo original está
 * registrado, `_dg_<x>` de las metas del motor, `action=digitalisimo`, `page=digitalisimo-motor…`), así que los nombres propios de verdad
 * (`digitalisimo_query`, `_digitalisimo_actions`…) nunca cambian y los nombres viejos siguen valiendo tal cual.
 *
 * **Con la marca apagada** (N-3 de la segunda revisión) el contenido del cliente no cambia ni al salir ni al entrar, así
 * que lo que sale tampoco necesita traducirse de vuelta:
 * - `out()` (textos del motor: descripciones, esquemas, guías, mensajes) sólo cambia los nombres de herramientas y de
 *   recursos y el nombre «Elementor»; los tipos, metas, plantillas y códigos salen con su nombre de siempre;
 * - `data_out()` (respuestas) sólo pasa por `out()` los textos del motor (`engine_text_key()`) y cambia una dirección de
 *   recurso entera (`elementor://x`); lo demás sale tal cual;
 * - `in_deep()` (argumentos del MCP nuevo) sólo devuelve una dirección de recurso entera (`digitalisimo://x` →
 *   `elementor://x`, `uri_in()`): es un nombre que publica el MCP nuevo, no contenido. Los nombres de herramienta
 *   `digitalisimo-*` los resuelve el servidor (y el Asistente), no este mapa.
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

	/**
	 * Metas del motor que salen como `_dg_<x>` (sin el prefijo `_elementor_`; las que terminan en `_` son prefijos de
	 * nombres armados en el código). Sólo éstas: `_dg_` es un prefijo común de terceros (`_dg_precio`), así que la
	 * entrada nunca convierte una meta ajena en una del motor, y una `_elementor_<x>` que no sea del motor tampoco sale
	 * renombrada (no habría cómo devolverla). `tests/elements-marca.php` comprueba que cubre las del núcleo y de PRO.
	 */
	const METAS = array(
		'ab_test_', 'ab_testing_data', 'apps_data', 'assets_data', 'click_tracking', 'code', 'component_archived_at',
		'component_is_archived', 'component_overridable_props', 'component_uid', 'component_variants', 'conditions',
		'controls_usage', 'css', 'data', 'date_conditions', 'deactivate_feedback_nonce', 'default_style_data',
		'default_styles', 'default_styles_post_ids', 'default_style_tag', 'design_system_sync_css_meta', 'device_mode',
		'edit_mode', 'edit_mode_nonce', 'editor_upgrade_notice_dismissed', 'element_cache', 'element_cache_unique_id',
		'elements_default_values', 'elements_usage', 'extra_options', 'floating_elements_type', 'free_to_pro_upsell',
		'general_settings', 'global_', 'home_screen_data', 'icon_set_path', 'import_session_id', 'inline_svg',
		'installed_time', 'is_screenshot', 'local_google_fonts', 'location', 'markdown_cache', 'migrations_state',
		'mixpanel_config', 'notifications_data', 'oembed_cache', 'onboarding_features', 'onboarding_strings_',
		'page_assets', 'page_settings', 'popup_display_settings', 'post_mode', 'priority',
		'pro_announcements_license_expired', 'pro_api_requests_lock', 'pro_installed_time', 'pro_license_v2_data',
		'promotional_pointer', 'pro_version', 'screenshot', 'screenshot_failed', 'settings_update_time',
		'site_languages', 'source', 'source_image_hash', 'template_sub_type', 'template_type', 'template_widget_type',
		'time_conditions', 'url_conditions', 'used_global_class', 'used_global_class_preview', 'user_roles',
		'v4_promotions', 'version',
	);

	/** @var bool|null para pruebas: ¿la marca propia está en vigor? (null: lo decide `Toggle::active()`) */
	private static $active = null;

	/** @var array<string,string>|null tipos y taxonomías: viejo => nuevo */
	private static $types = null;
	/** @var array<string,true>|null nombres de herramienta del motor (sin el prefijo), p. ej. `create-page` */
	private static $tools = null;

	/** Para pruebas y para quien ya conoce la lista: nombres de herramienta del motor sin prefijo. */
	public static function set_tools( ?array $slugs ): void {
		self::$tools = null === $slugs ? null : array_fill_keys( array_map( 'strval', $slugs ), true );
	}

	/** Para pruebas: la marca propia en vigor o no (null vuelve a preguntarle a `Toggle`). */
	public static function set_active( ?bool $active ): void {
		self::$active = $active;
	}

	/**
	 * ¿Salen los enlaces del escritorio con los alias (`action=digitalisimo`, `page=digitalisimo-motor…`)? Sólo con la
	 * marca propia en vigor: apagada, los enlaces llevan los nombres de siempre, y con el Elementor original activo
	 * (sin núcleo incluido) nadie traduciría el alias de vuelta.
	 */
	public static function active(): bool {
		if ( null !== self::$active ) {
			return self::$active;
		}
		return class_exists( Toggle::class ) && Toggle::active();
	}

	/** ¿`_elementor_<$name>` es una meta del motor? (METAS) */
	public static function engine_meta( string $name ): bool {
		static $exact = null;
		static $prefixes = null;
		if ( null === $exact ) {
			$exact    = array();
			$prefixes = array();
			foreach ( self::METAS as $meta ) {
				if ( '_' === substr( $meta, -1 ) ) {
					$prefixes[] = $meta;
				} else {
					$exact[ $meta ] = true;
				}
			}
		}
		if ( isset( $exact[ $name ] ) ) {
			return true;
		}
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) && strlen( $name ) > strlen( $prefix ) ) {
				return true;
			}
		}
		return false;
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
		// Los tipos de todos los plugins están registrados después de `init`: antes no se memoriza.
		if ( did_action( 'wp_loaded' ) ) {
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

	/**
	 * Lo que el paquete transformado enmascara en los archivos que el servidor lee (scripts/marca-empaquetar.mjs: un
	 * separador invisible U+200D dentro de la palabra en las guías del MCP) vuelve a su forma, para que los nombres de
	 * herramientas de las guías sigan sirviendo y el mapa los encuentre.
	 */
	public static function unmask( string $text ): string {
		return false === strpos( $text, "\u{200D}" ) ? $text : (string) preg_replace( '/(e)(lemen)\x{200D}(tor)/iu', '$1$2$3', $text );
	}

	/** unmask() en profundidad (respuestas del MCP viejo y del proxy del editor). */
	public static function unmask_deep( $value, int $depth = 0 ) {
		if ( is_string( $value ) ) {
			return self::unmask( $value );
		}
		if ( $depth > 64 || ( ! is_array( $value ) && ! $value instanceof \stdClass ) ) {
			return $value;
		}
		foreach ( $value as $k => $v ) {
			if ( is_array( $value ) ) {
				$value[ $k ] = self::unmask_deep( $v, $depth + 1 );
			} else {
				$value->{ $k } = self::unmask_deep( $v, $depth + 1 );
			}
		}
		return $value;
	}

	/** Texto de salida (ver la cabecera). */
	public static function out( string $text, bool $brand = true ): string {
		$text = self::unmask( $text );
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
		// Con la marca apagada, sólo los nombres de herramientas y recursos (y la marca en el texto): los identificadores
		// salen como siempre, así lo que el cliente MCP manda de vuelta no necesita traducirse (N-3).
		$active = self::active();
		if ( ! $active ) {
			return $brand ? self::brand( $text ) : $text;
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
		// Plantillas de página del motor (reversibles con in()).
		$text = (string) preg_replace( '#(?<![A-Za-z0-9_])elementor_(canvas|header_footer|theme)(?![A-Za-z0-9_])#', 'digitalisimo_$1', $text );
		$text = (string) preg_replace_callback(
			'#(?<![A-Za-z0-9_])_elementor_([a-z0-9_]+)#',
			static function ( $m ) {
				return self::engine_meta( $m[1] ) ? self::META_OUT . $m[1] : $m[0];
			},
			$text
		);
		// En los textos del motor (descripciones, guías, mensajes y códigos de error), los demás identificadores
		// `elementor_x` (códigos como `elementor_v3_not_supported`): sólo salen, nadie los manda de vuelta.
		if ( $brand ) {
			$text = (string) preg_replace( '#(?<![A-Za-z0-9_])elementor_([a-z0-9_]+)#', 'digitalisimo_$1', $text );
		}
		// Los alias de URL del escritorio, sólo con la marca propia en vigor (active()).
		if ( $active ) {
			$text = (string) preg_replace( '#([?&]|&amp;|\\\\u0026)action=elementor(?=$|[&\#"\'\s<\\\\])#', '$1action=' . self::ACTION_OUT, $text );
			$text = (string) preg_replace( '#([?&]|&amp;|\\\\u0026)page=elementor(?=[\w-]|$|[&\#"\'\s<\\\\])#', '$1page=' . self::PAGE_OUT, $text );
		}
		return $brand ? self::brand( $text ) : $text;
	}

	/** Sólo los identificadores (reversibles con in()), sin cambiar el nombre «Elementor» en el texto. */
	public static function ids( string $text ): string {
		return self::out( $text, false );
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
		$text = (string) preg_replace( '#(?<![A-Za-z0-9_])digitalisimo_(canvas|header_footer|theme)(?![A-Za-z0-9_])#', 'elementor_$1', $text );
		$text = (string) preg_replace_callback(
			'#(?<![A-Za-z0-9_])' . preg_quote( self::META_OUT, '#' ) . '([a-z0-9_]+)#',
			static function ( $m ) {
				return self::engine_meta( $m[1] ) ? '_elementor_' . $m[1] : $m[0];
			},
			$text
		);
		$text = (string) preg_replace( '#([?&]|&amp;|\\\\u0026)action=' . self::ACTION_OUT . '(?=$|[&\#"\'\s<\\\\])#', '$1action=elementor', $text );
		$text = (string) preg_replace( '#([?&]|&amp;|\\\\u0026)page=' . self::PAGE_OUT . '#', '$1page=elementor', $text );
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
	 * Para lo que el motor escribe (descripciones, esquemas, guías): identificadores y el nombre «Elementor».
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function out_deep( $value, int $depth = 0 ) {
		return self::deep( $value, array( __CLASS__, 'out' ), $depth );
	}

	/**
	 * Para DATOS (respuestas de herramientas y recursos): los identificadores siempre (son reversibles con in()), pero
	 * el nombre «Elementor» sólo en los textos que escribe el motor (`message`, `error`, `hint`, `instructions`… y los
	 * mensajes de WP_Error), y sólo si la herramienta es del motor (`$engine`): las de la tienda, el kit o Hosting
	 * devuelven notas de pedidos y mensajes de formularios del cliente, que nunca cambian de palabras. El contenido del cliente (títulos, textos, ajustes) nunca cambia de palabras: un agente que
	 * lee «Elementor» en un artículo y lo vuelve a guardar lo guarda igual.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function data_out( $value, int $depth = 0, string $key = '', bool $engine = true ) {
		if ( $depth > 64 ) {
			return $value;
		}
		$active = self::active();
		if ( is_string( $value ) ) {
			// Con la marca apagada (N-3), el contenido sale tal cual: sólo los textos del motor y una dirección de recurso
			// entera (que el cliente usa como nombre y uri_in() devuelve).
			if ( ! $active ) {
				if ( $engine && '' !== $key && self::engine_text_key( $key ) ) {
					return self::out( $value, true );
				}
				return preg_match( '#^elementor://\S*$#i', $value ) ? self::uri( $value ) : $value;
			}
			// Un código de error del motor (`code: elementor_v3_not_supported`) sale como el de sus descripciones.
			if ( $engine && 'code' === $key && preg_match( '/^elementor_[a-z0-9_]+$/', $value ) ) {
				return self::out( $value, true );
			}
			return self::out( $value, $engine && '' !== $key && self::engine_text_key( $key ) );
		}
		if ( is_array( $value ) || $value instanceof \stdClass ) {
			$is_obj = $value instanceof \stdClass;
			$out    = $is_obj ? new \stdClass() : array();
			foreach ( ( $is_obj ? get_object_vars( $value ) : $value ) as $k => $v ) {
				$nk  = is_string( $k ) && $active ? self::ids( $k ) : $k;
				$sub = self::data_out( $v, $depth + 1, is_string( $k ) ? $k : $key, $engine );
				if ( $is_obj ) {
					$out->{ $nk } = $sub;
				} else {
					$out[ $nk ] = $sub;
				}
			}
			return $out;
		}
		if ( class_exists( 'WP_Error', false ) && $value instanceof \WP_Error ) {
			return self::deep( $value, $engine ? array( __CLASS__, 'out' ) : array( __CLASS__, 'ids' ), $depth );
		}
		return $value;
	}

	/** ¿Esta clave lleva un texto que escribe el motor (no el cliente)? */
	public static function engine_text_key( string $key ): bool {
		return (bool) preg_match( '/^(message|messages|error|errors|warning|warnings|hints?|instructions|llm_instructions|guidance|next_steps?|tip|tips|explanation)$/i', $key );
	}

	/**
	 * in() en profundidad.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function in_deep( $value, int $depth = 0 ) {
		// Con la marca apagada no se reescribe el contenido del cliente que llega (N-3): sólo una dirección de recurso.
		return self::deep( $value, self::active() ? array( __CLASS__, 'in' ) : array( __CLASS__, 'uri_in' ), $depth );
	}

	/** Una dirección de recurso entera del MCP nuevo (`digitalisimo://x`) → `elementor://x`; cualquier otro texto, igual. */
	public static function uri_in( string $text ): string {
		return preg_match( '#^digitalisimo://\S*$#i', $text ) ? 'elementor://' . substr( $text, 15 ) : $text;
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
