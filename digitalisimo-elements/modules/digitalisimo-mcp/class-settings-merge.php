<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Los ajustes de un documento se combinan, nunca se reemplazan, cuando los escribe una herramienta del MCP. Nada de
 * `elementor/` se edita. Detalle en `docs/ELEMENTS_MCP_CSS.md` («Ajustes del documento»).
 *
 * Causa (núcleo 4.3.5): `Update_Settings_Ability::execute()` (`elementor/update-page-settings`) llama a
 * `$document->save( [ 'settings' => $settings ] )` con lo recibido, y `Document::save_settings()` lo guarda tal cual en
 * `_elementor_page_settings`: lo que no se manda se borra, aunque su esquema diga «merged into existing settings».
 * Con `{"custom_css": "…"}` sobre el kit global, el kit perdía colores, tipografías, `__globals__`, botones,
 * formularios y puntos de corte (`Settings_Layout::before_save()` los recalcula de lo recibido), y `Kit::save()`
 * regeneraba el CSS sin las variables `--e-global-*`: el sitio entero perdía su diseño.
 *
 * Qué hace:
 * - `update-page-settings` (envoltorio de su `execute_callback` con `wp_register_ability_args`): antes de que el núcleo
 *   guarde, combina lo recibido sobre lo guardado de verdad (la meta, sin los valores por defecto de los controles):
 *   cada clave recibida reemplaza a la guardada; `null` la borra; `__globals__` se combina un nivel adentro (un global
 *   con `null` se desvincula y los demás quedan). Así todo el guardado del núcleo (las pestañas del kit, kses, el CSS
 *   del kit, `after_save`) ve el documento completo, como al guardar en el editor, que siempre manda todos los ajustes.
 *   Las pestañas del kit con `on_save` que escriben opciones de WordPress o de WooCommerce (nombre, descripción, logo e
 *   ícono del sitio; páginas de WooCommerce) sólo corren hoy si la petición trae `post_status: publish`, y entonces
 *   leerían los valores guardados en el kit: durante la herramienta esas opciones sólo pueden cambiar si su clave vino
 *   en la petición. La respuesta añade `changed_keys` y `removed_keys` (lo que de verdad cambió en la meta) y
 *   `post_fields` (las claves que van a la entrada: título, estado, plantilla…).
 *   Autoguardados: al guardar los ajustes de una publicada, el núcleo borra su autoguardado más reciente
 *   (`Page\Manager::ajax_before_save_settings()`), que en el MCP guarda los cambios pendientes de `build-composition`
 *   o `manage-elements`; durante la herramienta no se borra ningún autoguardado de ese documento, y cada uno recibe las
 *   claves pedidas y las que cambiaron, porque `publish-document` lo promueve entero y desharía la actualización.
 *   kses: para quien no tiene `unfiltered_html`, el núcleo pasa kses por todos los ajustes; una clave que no vino y que
 *   sólo cambió por kses vuelve a su valor guardado (la escribió alguien con permiso).
 * - Cualquier otra herramienta del MCP (`elementor/…`, `digitalisimo-…`, también por el proxy del editor) que guarde un
 *   documento con `settings` (`manage-component` manda sólo `overridable_props`; al publicar, sólo el estado y el
 *   título, y el núcleo borraba los demás): `elementor/document/save/data` combina esos ajustes sobre los guardados del
 *   documento (o del autoguardado) que se guarda, sin borrar nada.
 * En las dos combinaciones, `overridable_props` y `variants` guardados no se vuelven a mandar si no vienen: son la
 * copia vieja de las metas propias de un componente, y el `after_save` del núcleo las reescribiría con ella.
 */
final class Settings_Merge {
	const ABILITY  = 'elementor/update-page-settings';
	const META_KEY = '_elementor_page_settings';
	const GLOBALS  = '__globals__';
	/** Prefijos de las herramientas del MCP cuyo guardado de documentos se combina. */
	const PREFIXES = array( 'elementor/', 'digitalisimo-' );
	/** Claves que un `after_save` del núcleo copia a metas propias (componentes): sólo viajan si vienen. */
	const SIDE_KEYS = array( 'overridable_props', 'variants' );
	/** La ruta del proxy del editor, que llama a las herramientas sin el `execute_callback` registrado. */
	const PROXY_ROUTE = '/elementor/v1/mcp-proxy';
	/** Claves que el núcleo aplica a la entrada y no guarda en la meta (`Page\Manager::get_special_settings_names()`). */
	const POST_FIELDS = array( 'id', 'post_title', 'post_status', 'template', 'post_excerpt', 'post_featured_image', 'menu_order', 'comment_status' );
	/**
	 * Claves del kit cuyo `on_save` escribe fuera del documento (`Settings_Site_Identity`, `Settings_Woocommerce`):
	 * clave => [ 'option' | 'theme_mod', nombre ].
	 */
	const OUTSIDE = array(
		'site_name'                            => array( 'option', 'blogname' ),
		'site_description'                     => array( 'option', 'blogdescription' ),
		'site_favicon'                         => array( 'option', 'site_icon' ),
		'site_logo'                            => array( 'theme_mod', 'custom_logo' ),
		'woocommerce_cart_page_id'             => array( 'option', 'woocommerce_cart_page_id' ),
		'woocommerce_checkout_page_id'         => array( 'option', 'woocommerce_checkout_page_id' ),
		'woocommerce_myaccount_page_id'        => array( 'option', 'woocommerce_myaccount_page_id' ),
		'woocommerce_terms_page_id'            => array( 'option', 'woocommerce_terms_page_id' ),
		'woocommerce_purchase_summary_page_id' => array( 'option', 'elementor_woocommerce_purchase_summary_page_id' ),
		'woocommerce_shop_page_id'             => array( 'option', 'woocommerce_shop_page_id' ),
	);
	const DESCRIPTION = 'Partial update of Elementor document-level settings for a post (page layout, title visibility, background, custom CSS, or the global kit: colors, typography, buttons, forms). Send only the keys to change: every other saved setting is kept. A key set to null is deleted. "__globals__" is merged one level deep: {"__globals__": {"button_text_color": null}} unlinks only that global and keeps the others. Any other value (a typography group, a background image, a color list such as system_colors) replaces that key as a whole, so send the full list when you change one item. Kit keys that mirror WordPress or WooCommerce options (site_name, site_description, site_logo, site_favicon, woocommerce_*_page_id) only update those options when you send them together with post_status "publish"; otherwise only the kit setting is saved, and other options are never touched. The response lists changed_keys and removed_keys. Ask the user for the URL or post ID. Use get-page-structure when you also need the element tree. Requires permission to edit the target post.';
	const SETTINGS_DESCRIPTION = 'Partial document settings object, merged into the saved settings: keys not sent are kept, a key with null is deleted, and "__globals__" is merged one level deep (a global with null is unlinked). Other values replace their key whole. Schema enforcement is delegated to document->save().';

	/** @var bool */
	private static $initialized = false;

	/** @var string[] Herramientas del MCP en curso (pila). */
	private static $stack = array();

	/** @var array<string, true> Claves que trae la petición de `update-page-settings` en curso. */
	private static $sent = array();

	/** @var int Petición del proxy del editor en curso (`spl_object_id`), o 0. */
	private static $proxy = 0;

	/** @var int Documento de `update-page-settings` en curso: sus autoguardados no se borran. */
	private static $keep = 0;

	/** Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'wrap_ability' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/document/save/data', array( __CLASS__, 'complete_settings' ), PHP_INT_MIN, 2 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_rest' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_rest' ), 10, 3 );
		add_action( 'shutdown', array( __CLASS__, 'after_rest' ), 0, 0 );
	}

	/**
	 * `rest_request_before_callbacks`: una herramienta por el proxy del editor abre el ámbito (no pasa por el
	 * envoltorio), ligado a esa petición. Cualquier otra petición REST lo cierra: si un `\Error` escapó del proxy sin
	 * pasar por `after_rest`, en un proceso que sigue vivo no queda abierto.
	 */
	public static function before_rest( $response, $handler = null, $request = null ) {
		self::$proxy = self::is_proxy( $request ) ? spl_object_id( $request ) : 0;
		return $response;
	}

	/** `rest_request_after_callbacks` (y `shutdown`): lo cierra. */
	public static function after_rest( $response = null, $handler = null, $request = null ) {
		self::$proxy = 0;
		return $response;
	}

	/** `wp_register_ability_args`: envuelve `update-page-settings` y abre el ámbito de las demás herramientas del MCP. */
	public static function wrap_ability( $args, $name = '' ) {
		if ( ! is_string( $name ) || ! self::is_mcp_ability( $name ) || ! is_array( $args ) || empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
			return $args;
		}
		$inner = $args['execute_callback'];
		if ( self::ABILITY === $name ) {
			$args                     = self::describe( $args );
			$args['execute_callback'] = static function ( ...$params ) use ( $inner ) {
				return self::update_settings( $inner, $params );
			};
			return $args;
		}
		$args['execute_callback'] = static function ( ...$params ) use ( $inner, $name ) {
			self::$stack[] = $name;
			try {
				return call_user_func_array( $inner, $params );
			} finally {
				array_pop( self::$stack );
			}
		};
		return $args;
	}

	/** La descripción y el esquema dicen cómo se combina, cómo se borra y qué devuelve. */
	public static function describe( array $args ): array {
		$args['description'] = self::DESCRIPTION;
		if ( isset( $args['input_schema']['properties']['settings'] ) && is_array( $args['input_schema']['properties']['settings'] ) ) {
			$args['input_schema']['properties']['settings']['description'] = self::SETTINGS_DESCRIPTION;
		}
		if ( isset( $args['output_schema']['properties'] ) && is_array( $args['output_schema']['properties'] ) ) {
			$list = array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			);
			$args['output_schema']['properties']['changed_keys'] = $list + array( 'description' => 'Saved settings that changed (added, changed or deleted); a global as "__globals__.<key>".' );
			$args['output_schema']['properties']['removed_keys'] = $list + array( 'description' => 'Saved settings that were deleted.' );
			$args['output_schema']['properties']['post_fields']  = $list + array( 'description' => 'Keys applied to the post itself (title, status, template…), not saved as settings.' );
		}
		return $args;
	}

	/**
	 * El `execute_callback` de `update-page-settings`: combina, guarda con el núcleo, protege las opciones de fuera e
	 * informa. Lo que no es una petición válida sobre un documento editable va al núcleo tal cual (sus errores).
	 */
	public static function update_settings( callable $inner, array $params ) {
		$input    = $params[0] ?? array();
		$document = self::document( $input );
		if ( null === $document ) {
			return call_user_func_array( $inner, $params );
		}
		$id      = (int) $document->get_post()->ID;
		$changes = $input['settings'];
		if ( array() === $changes ) {
			// Nada que cambiar: el núcleo no guarda unos ajustes vacíos; no se vuelve a guardar todo.
			$result = call_user_func_array( $inner, $params );
			return self::succeeded( $result ) ? $result + array( 'changed_keys' => array(), 'removed_keys' => array(), 'post_fields' => array() ) : $result;
		}
		$before = self::stored( $id );
		$merged = self::merge( self::without_side_keys( $before, $changes ), $changes, true );

		$params[0]             = $input;
		$params[0]['settings'] = $merged;
		self::$sent            = array_fill_keys( array_map( 'strval', array_keys( $changes ) ), true );
		self::$stack[]         = self::ABILITY;
		self::$keep            = $id;
		self::guard( true );
		try {
			$result = call_user_func_array( $inner, $params );
			if ( self::succeeded( $result ) && array() === self::own( $merged ) && array() !== $before ) {
				// Se borró la última clave: el núcleo no guarda unos ajustes vacíos (`! empty( $data['settings'] )`).
				delete_metadata( 'post', $id, self::META_KEY );
			}
		} finally {
			self::guard( false );
			self::$keep = 0;
			array_pop( self::$stack );
			self::$sent = array();
		}
		if ( ! self::succeeded( $result ) ) {
			return $result;
		}
		$after = self::undo_kses( $id, $before, self::stored( $id ), $changes );
		// Las copias de metas propias que no vinieron se dejan de guardar sin perder nada: no se informan.
		list( $changed, $removed ) = self::diff( self::without_side_keys( $before, $changes ), $after );
		self::sync_autosaves( $id, $after, array_unique( array_merge( $changed, self::paths( $changes ) ) ) );
		$result['changed_keys'] = $changed;
		$result['removed_keys'] = $removed;
		$result['post_fields']  = array_values( array_intersect( self::POST_FIELDS, array_map( 'strval', array_keys( $changes ) ) ) );
		return $result;
	}

	/**
	 * `elementor/document/save/data` (la primera): dentro de otra herramienta del MCP, unos `settings` parciales se
	 * combinan sobre los guardados del documento que se guarda (sin borrar nada: `null` se guarda como `null`).
	 */
	public static function complete_settings( $data, $document = null ) {
		if ( ( ! self::$stack && ! self::$proxy ) || ( self::$stack && self::ABILITY === end( self::$stack ) ) ) {
			return $data;
		}
		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) || ! is_object( $document ) || ! method_exists( $document, 'get_post' ) ) {
			return $data;
		}
		$post = $document->get_post();
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return $data;
		}
		$stored = self::stored( (int) $post->ID );
		if ( $stored ) {
			$data['settings'] = self::merge( self::without_side_keys( $stored, $data['settings'] ), $data['settings'], false );
		}
		return $data;
	}

	/**
	 * Combina `$changes` sobre `$stored`: cada clave reemplaza a la guardada; con `$nulls`, `null` la borra;
	 * `__globals__` se combina un nivel adentro (y se quita si queda vacío).
	 */
	public static function merge( array $stored, array $changes, bool $nulls = true ): array {
		foreach ( $changes as $key => $value ) {
			if ( null === $value && $nulls ) {
				unset( $stored[ $key ] );
				continue;
			}
			if ( self::GLOBALS === (string) $key && is_array( $value ) ) {
				$globals = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();
				foreach ( $value as $name => $link ) {
					if ( null === $link && $nulls ) {
						unset( $globals[ $name ] );
					} else {
						$globals[ $name ] = $link;
					}
				}
				if ( $globals ) {
					$stored[ $key ] = $globals;
				} else {
					unset( $stored[ $key ] );
				}
				continue;
			}
			$stored[ $key ] = $value;
		}
		return $stored;
	}

	/**
	 * Lo que cambió entre dos lecturas de la meta: [ cambiadas, borradas ], cada global de `__globals__` como
	 * `__globals__.<clave>`.
	 */
	public static function diff( array $before, array $after ): array {
		$changed = array();
		$removed = array();
		$keys    = array_unique( array_merge( array_map( 'strval', array_keys( $before ) ), array_map( 'strval', array_keys( $after ) ) ) );
		foreach ( $keys as $key ) {
			$had = array_key_exists( $key, $before );
			$has = array_key_exists( $key, $after );
			if ( self::GLOBALS === $key && ( ! $had || is_array( $before[ $key ] ) ) && ( ! $has || is_array( $after[ $key ] ) ) ) {
				list( $sub_changed, $sub_removed ) = self::diff( $had ? $before[ $key ] : array(), $has ? $after[ $key ] : array() );
				foreach ( $sub_changed as $sub ) {
					$changed[] = $key . '.' . $sub;
				}
				foreach ( $sub_removed as $sub ) {
					$removed[] = $key . '.' . $sub;
				}
				continue;
			}
			if ( $had !== $has || ( $had && $before[ $key ] !== $after[ $key ] ) ) {
				$changed[] = $key;
				if ( ! $has ) {
					$removed[] = $key;
				}
			}
		}
		return array( $changed, $removed );
	}

	/** Los ajustes guardados de verdad de una entrada (o revisión): la meta, sin valores por defecto. */
	public static function stored( int $id ): array {
		$value = get_metadata( 'post', $id, self::META_KEY, true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * `pre_update_option_<opción>` y `pre_set_theme_mod_<nombre>` durante `update-page-settings`: la opción de una clave
	 * del kit que no vino en la petición conserva su valor.
	 */
	public static function keep_outside( $value, $old_value = null ) {
		return $old_value;
	}

	/**
	 * `pre_delete_post` durante `update-page-settings`: ningún autoguardado del documento se borra (el núcleo borra el
	 * más reciente al guardar los ajustes de una publicada, con los cambios pendientes del MCP dentro).
	 */
	public static function keep_autosave( $delete, $post = null ) {
		if ( self::$keep && is_object( $post ) && 'revision' === ( $post->post_type ?? '' ) && self::$keep === (int) ( $post->post_parent ?? 0 )
			&& false !== strpos( (string) ( $post->post_name ?? '' ), '-autosave-v' ) ) {
			return false;
		}
		return $delete;
	}

	/** Para las pruebas: vuelve al estado anterior a `init()`. */
	public static function reset(): void {
		self::$initialized = false;
		self::$stack       = array();
		self::$sent        = array();
		self::$keep        = 0;
		self::$proxy       = 0;
	}

	/** Para las pruebas. */
	public static function state(): array {
		return array(
			'stack' => self::$stack,
			'proxy' => self::$proxy,
			'sent'  => array_keys( self::$sent ),
		);
	}

	/** El documento de una petición válida que quien llama puede editar; si no, `null` (el núcleo responde). */
	private static function document( $input ) {
		if ( ! is_array( $input ) || empty( $input['post_id'] ) || ! isset( $input['settings'] ) || ! is_array( $input['settings'] ) ) {
			return null;
		}
		if ( ! class_exists( 'Elementor\\Plugin', false ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return null;
		}
		$document = \Elementor\Plugin::$instance->documents->get( absint( $input['post_id'] ) );
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_post' ) || ! is_object( $document->get_post() ) ) {
			return null;
		}
		if ( method_exists( $document, 'is_editable_by_current_user' ) && ! $document->is_editable_by_current_user() ) {
			return null;
		}
		return $document;
	}

	/** Lo que queda en la meta (sin las claves de la entrada). */
	private static function own( array $settings ): array {
		return array_diff_key( $settings, array_flip( self::POST_FIELDS ) );
	}

	private static function succeeded( $result ): bool {
		return is_array( $result ) && ! empty( $result['success'] );
	}

	/** Pone o quita la protección de las opciones de fuera de las claves que no vinieron. */
	private static function guard( bool $on ): void {
		if ( $on ) {
			add_filter( 'pre_delete_post', array( __CLASS__, 'keep_autosave' ), PHP_INT_MAX, 2 );
		} else {
			remove_filter( 'pre_delete_post', array( __CLASS__, 'keep_autosave' ), PHP_INT_MAX );
		}
		foreach ( self::OUTSIDE as $key => $target ) {
			if ( $on && isset( self::$sent[ $key ] ) ) {
				continue;
			}
			$hook = 'option' === $target[0] ? 'pre_update_option_' . $target[1] : 'pre_set_theme_mod_' . $target[1];
			if ( $on ) {
				add_filter( $hook, array( __CLASS__, 'keep_outside' ), PHP_INT_MAX, 2 );
			} else {
				remove_filter( $hook, array( __CLASS__, 'keep_outside' ), PHP_INT_MAX );
			}
		}
	}

	/** Lo guardado sin las copias de metas propias (`SIDE_KEYS`) que la petición no trae. */
	private static function without_side_keys( array $stored, array $changes ): array {
		foreach ( self::SIDE_KEYS as $key ) {
			if ( ! array_key_exists( $key, $changes ) ) {
				unset( $stored[ $key ] );
			}
		}
		return $stored;
	}

	/** Las rutas que pide la petición (sin las claves de la entrada), un global como `__globals__.<clave>`. */
	private static function paths( array $changes ): array {
		$paths = array();
		foreach ( $changes as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, self::POST_FIELDS, true ) ) {
				continue;
			}
			if ( self::GLOBALS === $key && is_array( $value ) ) {
				foreach ( array_keys( $value ) as $name ) {
					$paths[] = $key . '.' . $name;
				}
				continue;
			}
			$paths[] = $key;
		}
		return $paths;
	}

	/**
	 * Para quien no tiene `unfiltered_html`, `Document::save()` pasa kses por todos los ajustes: una clave que no vino y
	 * que sólo cambió por kses (`a > b` → `a &gt; b` en un CSS) vuelve a su valor guardado. Devuelve la meta final.
	 */
	private static function undo_kses( int $id, array $before, array $after, array $changes ): array {
		if ( current_user_can( 'unfiltered_html' ) || ! class_exists( 'Elementor\\Utils', false ) || ! method_exists( 'Elementor\\Utils', 'kses_post_deep' ) ) {
			return $after;
		}
		$restored = false;
		foreach ( $before as $key => $value ) {
			if ( array_key_exists( $key, $changes ) || ! array_key_exists( $key, $after ) || $after[ $key ] === $value ) {
				continue;
			}
			if ( \Elementor\Utils::kses_post_deep( $value ) === $after[ $key ] ) {
				$after[ $key ] = $value;
				$restored      = true;
			}
		}
		if ( $restored ) {
			update_metadata( 'post', $id, self::META_KEY, wp_slash( $after ) );
		}
		return $after;
	}

	/**
	 * Cada autoguardado del documento (`publish-document` promueve entero el de quien llama) recibe las rutas pedidas y
	 * las que cambiaron, con su valor final; lo demás de ese autoguardado queda como estaba.
	 */
	private static function sync_autosaves( int $id, array $after, array $paths ): void {
		if ( ! function_exists( 'get_posts' ) ) {
			return;
		}
		// Los autoguardados de una entrada se llaman todos `<id>-autosave-v1` (uno por usuario).
		$autosaves = get_posts(
			array(
				'post_type'        => 'revision',
				'post_status'      => 'inherit',
				'post_parent'      => $id,
				'name'             => $id . '-autosave-v1',
				'posts_per_page'   => -1,
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
		foreach ( (array) $autosaves as $revision ) {
			if ( ! is_object( $revision ) || empty( $revision->ID ) || (int) $revision->post_parent !== $id ) {
				continue;
			}
			self::newer_than_post( $id, (int) $revision->ID );
			if ( ! $paths ) {
				continue;
			}
			$pending = self::stored( (int) $revision->ID );
			foreach ( $paths as $path ) {
				$parts = explode( '.', $path, 2 );
				if ( 2 === count( $parts ) && self::GLOBALS === $parts[0] ) {
					$globals = isset( $pending[ self::GLOBALS ] ) && is_array( $pending[ self::GLOBALS ] ) ? $pending[ self::GLOBALS ] : array();
					if ( isset( $after[ self::GLOBALS ] ) && is_array( $after[ self::GLOBALS ] ) && array_key_exists( $parts[1], $after[ self::GLOBALS ] ) ) {
						$globals[ $parts[1] ] = $after[ self::GLOBALS ][ $parts[1] ];
					} else {
						unset( $globals[ $parts[1] ] );
					}
					if ( $globals ) {
						$pending[ self::GLOBALS ] = $globals;
					} else {
						unset( $pending[ self::GLOBALS ] );
					}
				} elseif ( array_key_exists( $path, $after ) ) {
					$pending[ $path ] = $after[ $path ];
				} else {
					unset( $pending[ $path ] );
				}
			}
			if ( $pending ) {
				update_metadata( 'post', (int) $revision->ID, self::META_KEY, wp_slash( $pending ) );
			} else {
				delete_metadata( 'post', (int) $revision->ID, self::META_KEY );
			}
		}
	}

	/**
	 * El guardado de los ajustes actualizó la fecha de la entrada (`wp_update_post()`): el autoguardado conservado pasa a
	 * ser un segundo más nuevo, o el editor (`Document::get_newer_autosave()`) y la publicación de componentes dejarían
	 * de verlo como cambios pendientes.
	 */
	private static function newer_than_post( int $id, int $autosave ): void {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! function_exists( 'get_post' ) ) {
			return;
		}
		clean_post_cache( $id );
		$post = get_post( $id );
		$auto = get_post( $autosave );
		if ( ! $post || ! $auto ) {
			return;
		}
		$main = strtotime( $post->post_modified_gmt . ' UTC' );
		if ( ! $main || strtotime( $auto->post_modified_gmt . ' UTC' ) > $main ) {
			return;
		}
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => gmdate( 'Y-m-d H:i:s', strtotime( $post->post_modified . ' UTC' ) + 1 ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $main + 1 ),
			),
			array( 'ID' => $autosave )
		);
		clean_post_cache( $autosave );
	}

	private static function is_proxy( $request ): bool {
		return is_object( $request ) && method_exists( $request, 'get_route' ) && self::PROXY_ROUTE === $request->get_route()
			&& ( ! method_exists( $request, 'get_method' ) || 'POST' === $request->get_method() );
	}

	private static function is_mcp_ability( string $name ): bool {
		foreach ( self::PREFIXES as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
