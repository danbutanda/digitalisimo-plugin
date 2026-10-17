<?php
/**
 * Sin rastro del proveedor del núcleo incluido: ni enlaces, ni vídeos, ni cuentas, ni servicios, ni peticiones.
 *
 * El núcleo se distribuye sin cambios y trae accesos a la cuenta, la biblioteca, los kits, la nube y la IA del
 * proveedor, telemetría, novedades, ayuda y promociones remotas. Con el núcleo incluido todo eso se retira en
 * tiempo de ejecución, sin editar `elementor/`:
 *  - un escudo en `pre_http_request` corta toda petición del constructor hacia los dominios del proveedor (con
 *    respuestas válidas donde el núcleo las necesita para no romper, como la información de la biblioteca, sin la
 *    cual desaparecerían también las plantillas locales);
 *  - las cachés de datos remotos se sirven vacías desde aquí, así que el núcleo nunca las pide;
 *  - telemetría, IA, cuentas, biblioteca y kits remotos, Angie, novedades, ayuda, avisos y sugerencias de productos
 *    del proveedor quedan apagados o fuera de la interfaz (assets/digitalisimo/vendor-free.js y identity.css).
 * Lo que se pierde exige una cuenta del proveedor; lo local (plantillas, kits en ZIP, Plantillas de red de
 * DIGITALÍSIMO Tools) sigue igual. Con el plugin Elementor original activo nada de esto corre: manda él.
 */
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Vendor_Free {
	const SCRIPT = 'digitalisimo-elements-vendor-free';

	/**
	 * Dominios del proveedor del núcleo y de PRO Elements (también sus subdominios). Los de los complementos que
	 * sustituye DIGITALÍSIMO Migración no se nombran aquí: Elements no los contiene ni los pide (el empaquetado aborta si
	 * su ZIP los menciona), y la auditoría de marca los vigila desde tests/playground/brand-audit/vendors.json.
	 */
	const HOSTS = array( 'elementor.com', 'elementor.cloud', 'elementor.red', 'elementor.io', 'mxpnl.com', 'proelements.org' );

	/** Vídeos promocionales del proveedor que el núcleo usa como valor por defecto o de ejemplo. */
	const VIDEOS = '#XHOmBV4js_E|235215203|x6tqhqb|ZCAOzTNk#';

	/** Telemetría que el núcleo envía a un tercero: se corta sólo si la pide el constructor. */
	const CALLER_HOSTS = array( 'mixpanel.com' );

	/** Cachés de datos remotos del núcleo (EditorAssetsAPI). Se sirven vacías: el núcleo nunca las pide. */
	const REMOTE_CACHES = array(
		'_elementor_home_screen_data',
		'_elementor_notifications_data',
		'_elementor_apps_data',
		'_elementor_free_to_pro_upsell',
		'_elementor_v4_promotions',
		'_elementor_mixpanel_config',
		'_elementor_ab_testing_data',
		'elementor_plugins_page_assets_data',
		'elementor_side_menu_assets_data',
		'elementor_conversion_banner_assets_data',
		'elementor_pointer_assets_data',
		'elementor_connect_page_assets_data',
	);

	/** Experimentos que sólo sirven al proveedor (comentarios, guía de inicio, eventos de telemetría). */
	const EXPERIMENTS_OFF = array( 'in_editor_feedback', 'launchpad-checklist', 'editor_events' );

	/** Avisos de administración del proveedor que se dan por vistos. */
	const NOTICES = array( 'api_notice', 'api_upgrade_plugin', 'tracker', 'tracker_last_update', 'rate_us_feedback', 'role_manager_promote', 'plugin_image_optimization', 'ally_pages_promotion' );

	/** Sugerencias del editor que promocionan productos del proveedor. */
	const HINTS = array( 'image-optimization-once', 'image-optimization-once-media-modal', 'image_optimizer_hint', 'image-optimization-media-modal', 'ally_heading_notice' );

	/** Pantallas ocultas que llevan a la cuenta o a servicios del proveedor. */
	const PAGES = array( 'elementor-connect', 'e-site-planner-password-generator', 'go_elementor_pro', 'elementor-one-upgrade', 'elementor-apps' );

	/** Rutas REST de conexión, comentarios y permisos para servicios del proveedor. */
	const REST_ROUTES = '#^/(elementor/v1/library/connect|elementor/v1/angie|elementor-ai/v1|elementor-one/v1/(top-bar/feedback|feedback))#';

	public static function init() {
		if ( ! class_exists( 'Digitalisimo_Elements_Core' ) || ! Digitalisimo_Elements_Core::bundled() ) {
			return;
		}
		add_filter( 'pre_http_request', array( __CLASS__, 'shield' ), 1, 3 );
		foreach ( self::REMOTE_CACHES as $option ) {
			add_filter( 'pre_option_' . $option, array( __CLASS__, 'empty_remote_cache' ) );
		}
		add_filter( 'pre_option_elementor_allow_tracking', array( __CLASS__, 'no' ) );
		add_filter( 'pre_option_elementor_tracker_notice', array( __CLASS__, 'one' ) );
		foreach ( self::EXPERIMENTS_OFF as $experiment ) {
			add_filter( 'pre_option_elementor_experiment-' . $experiment, array( __CLASS__, 'inactive' ) );
		}
		// IA del constructor: es un servicio del proveedor con su propia cuenta.
		add_filter( 'get_user_option_elementor_enable_ai', array( __CLASS__, 'zero' ) );
		add_filter( 'elementor/admin/show_beta_tester', '__return_false' );
		add_filter( 'elementor_one/upgrade_available', '__return_false', PHP_INT_MAX );
		add_filter( 'get_user_metadata', array( __CLASS__, 'user_meta' ), 10, 4 );
		add_filter( 'rest_endpoints', array( __CLASS__, 'rest_endpoints' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_pre_dispatch' ), 10, 3 );
		add_filter( 'wp_redirect', array( __CLASS__, 'redirect' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'pages' ), 0 );
		add_action( 'elementor/init', array( __CLASS__, 'core_ready' ), 20 );
		add_filter( 'elementor/finder/categories', array( __CLASS__, 'finder' ) );
		add_filter( 'elementor/editor-one/menu/excluded_level4_slugs', array( __CLASS__, 'menu_slugs' ) );
		add_filter( 'elementor/editor-one/menu/excluded_level3_slugs', array( __CLASS__, 'menu_slugs' ) );
		add_filter( 'elementor/document/config', array( __CLASS__, 'document_config' ), 20 );
		add_filter( 'elementor/editor/localize_settings', array( __CLASS__, 'editor_settings' ), 20 );
		add_filter( 'elementor/editor/v2/packages', array( __CLASS__, 'editor_packages' ), 20 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_scripts' ), 20 );
		add_action( 'elementor/editor/v2/scripts/enqueue', array( __CLASS__, 'editor_scripts' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_script' ), 100 );
		add_action( 'elementor/app/init', array( __CLASS__, 'admin_script' ) );
		add_action( 'elementor/element/video/section_video/before_section_end', array( __CLASS__, 'video_defaults' ) );
		add_filter( 'elementor/atomic-widgets/props-schema', array( __CLASS__, 'atomic_defaults' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 20, 2 );
	}

	/** ¿La URL va a un dominio del proveedor? */
	public static function vendor_url( $url ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}
		foreach ( self::HOSTS as $vendor ) {
			if ( $host === $vendor || substr( $host, -strlen( $vendor ) - 1 ) === '.' . $vendor ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ¿La pide el constructor (núcleo incluido o derivado de PRO), y no otro plugin? Decide el primer archivo de la
	 * pila que no es de WordPress ni de esta clase: quien llamó a wp_remote_*().
	 */
	private static function from_builder() {
		$dir = wp_normalize_path( dirname( DIGITALISIMO_ELEMENTS_FILE ) ) . '/';
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- sólo identifica al llamador.
			$file = isset( $frame['file'] ) ? wp_normalize_path( $frame['file'] ) : '';
			if ( '' === $file || __FILE__ === $frame['file'] || false !== strpos( $file, '/wp-includes/' ) || false !== strpos( $file, '/wp-admin/' ) ) {
				continue;
			}
			return 0 === strpos( $file, $dir );
		}
		return false;
	}

	/**
	 * Escudo: ninguna petición del constructor (núcleo incluido o derivado de PRO) sale hacia el proveedor. Donde el núcleo necesita una respuesta para
	 * seguir funcionando se la da vacía y válida; donde un error provocaría `wp_die()` (aplicaciones de cuenta) se
	 * responde 403; lo demás, error.
	 */
	public static function shield( $pre, $args, $url ) {
		if ( false !== $pre ) {
			return $pre;
		}
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		$telemetry = false;
		foreach ( self::CALLER_HOSTS as $caller_host ) {
			if ( $host === $caller_host || substr( $host, -strlen( $caller_host ) - 1 ) === '.' . $caller_host ) {
				$telemetry = true;
			}
		}
		if ( ( ! $telemetry && ! self::vendor_url( $url ) ) || ! self::from_builder() ) {
			// Un plugin del proveedor que el administrador instale por su cuenta sigue funcionando: sólo se corta lo
			// que pide el constructor.
			return $pre;
		}
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( false !== strpos( $path, '/api/v1/templates/info' ) ) {
			// Sin esta respuesta el modal de plantillas tampoco mostraría las plantillas locales; el listado REST espera
			// la lista de categorías de bloques (templates.php, reorder_categories).
			return self::response( 200, array( 'types_data' => array( 'block' => array( 'categories' => array() ) ) ) );
		}
		if ( false !== strpos( $path, '/api/v2/info' ) ) {
			return self::response( 200, array( 'digitalisimo' => true ) );
		}
		if ( preg_match( '#/(connect|api/connect|api/v1/kits-library|api/v2/ai|api/v1/cloud|cloud-library)#', $path ) || 0 === strpos( $host, 'cloud-library.' ) || 0 === strpos( $host, 'ms-' ) ) {
			// Las aplicaciones de cuenta del núcleo hacen wp_die() ante un WP_Error; un 401 borraría datos y relanzaría
			// la autorización. Un 403 las deja en «sin acceso».
			return self::response( 403, array( 'message' => 'not_available' ) );
		}
		return new WP_Error( 'digitalisimo_vendor_free', 'DIGITALÍSIMO Elements no se conecta con servicios externos del constructor.' );
	}

	private static function response( $code, $body ) {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array( 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Forbidden' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/** Caché remota vigente y vacía: el núcleo la lee y no pide nada (EditorAssetsAPI::get_transient). */
	public static function empty_remote_cache() {
		return array( 'timeout' => 4102444800, 'value' => '[]' );
	}

	public static function no() {
		return 'no';
	}

	public static function one() {
		return '1';
	}

	public static function zero() {
		return '0';
	}

	public static function inactive() {
		return 'inactive';
	}

	/** Avisos y sugerencias del proveedor: vistos y descartados para todo usuario. */
	public static function user_meta( $value, $object_id, $meta_key, $single ) {
		if ( 'elementor_admin_notices' !== $meta_key && 'elementor_dismissed_editor_notices' !== $meta_key ) {
			return $value;
		}
		remove_filter( 'get_user_metadata', array( __CLASS__, 'user_meta' ), 10 );
		$stored = get_user_meta( $object_id, $meta_key, true );
		add_filter( 'get_user_metadata', array( __CLASS__, 'user_meta' ), 10, 4 );
		$stored = is_array( $stored ) ? $stored : array();
		if ( 'elementor_admin_notices' === $meta_key ) {
			foreach ( self::NOTICES as $notice ) {
				$stored[ $notice ] = array( 'is_viewed' => true, 'meta' => array() );
			}
		} else {
			$stored = array_values( array_unique( array_merge( $stored, self::HINTS ) ) );
		}
		return $single ? array( $stored ) : array( $stored );
	}

	/** Rutas REST de conexión con la cuenta, comentarios y permisos de servicios del proveedor. */
	public static function rest_endpoints( $endpoints ) {
		foreach ( array_keys( (array) $endpoints ) as $route ) {
			if ( preg_match( self::REST_ROUTES, (string) $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/** Las novedades de la barra superior se piden al proveedor en cada carga: aquí se responden vacías. */
	public static function rest_pre_dispatch( $result, $server, $request ) {
		if ( null === $result && is_object( $request ) && method_exists( $request, 'get_route' ) && 0 === strpos( (string) $request->get_route(), '/elementor-one/v1/top-bar/notifications' ) ) {
			return new WP_REST_Response( array(), 200 );
		}
		return $result;
	}

	/** Una redirección del constructor hacia el proveedor lleva a la página propia. */
	public static function redirect( $location ) {
		return self::vendor_url( $location ) && function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=digitalisimo-elements-about' ) : $location;
	}

	/** Pantallas ocultas de cuenta, Site Planner, mejoras y apps del proveedor: a la página propia. */
	public static function pages() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide adónde ir.
		if ( in_array( $page, self::PAGES, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-elements-about' ) );
			exit;
		}
	}

	/**
	 * Con el núcleo listo: fuera las fuentes remota y en la nube de la biblioteca (quedan «Mis plantillas»), la
	 * telemetría programada y las promociones del módulo de promociones.
	 */
	public static function core_ready() {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->templates_manager ) ) {
			\Elementor\Plugin::$instance->templates_manager->unregister_source( 'remote' );
			\Elementor\Plugin::$instance->templates_manager->unregister_source( 'cloud' );
		}
		$creation = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->modules_manager ) ? \Elementor\Plugin::$instance->modules_manager->get_modules( 'widget-creation' ) : null;
		if ( $creation ) {
			remove_action( 'elementor/elements/categories_registered', array( $creation, 'maybe_register_custom_widgets_category_fallback' ), 100 );
		}
		if ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( 'elementor/tracker/send_event' ) ) {
			wp_clear_scheduled_hook( 'elementor/tracker/send_event' );
		}
		self::forget_vendor_data();
	}

	/** Una sola vez por versión: cachés remotas guardadas y tokens de cuenta del proveedor fuera de la base de datos. */
	private static function forget_vendor_data() {
		if ( DIGITALISIMO_ELEMENTS_VERSION === get_option( 'digitalisimo_elements_vendor_free' ) ) {
			return;
		}
		global $wpdb;
		foreach ( array_merge( self::REMOTE_CACHES, array( 'elementor_remote_info_library', 'elementor_remote_info_feed_data' ) ) as $option ) {
			delete_option( $option );
		}
		foreach ( array( 'elementor_remote_kits', 'elementor_remote_kits_taxonomies', 'elementor_migrations_manifest' ) as $transient ) {
			delete_transient( $transient );
		}
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_elementor\\_remote\\_info\\_api\\_data\\_%' OR option_name LIKE '\\_transient\\_timeout\\_elementor\\_remote\\_info\\_api\\_data\\_%' OR option_name LIKE '\\_elementor\\_onboarding\\_strings\\_%' OR option_name LIKE 'elementor\\_one\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- limpieza única de datos del proveedor.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key = %s", $wpdb->esc_like( $wpdb->get_blog_prefix() . 'elementor_connect_' ) . '%', 'elementor_connect_common_data' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tokens de cuenta del proveedor.
		update_option( 'digitalisimo_elements_vendor_free', DIGITALISIMO_ELEMENTS_VERSION, true );
	}

	/** Buscador del editor: sin «Base de conocimientos», apps ni kits del proveedor. */
	public static function finder( $categories ) {
		if ( is_array( $categories ) ) {
			unset( $categories['general']['items']['knowledge-base'], $categories['general']['items']['kit-library'], $categories['site']['items']['apps'] );
		}
		return $categories;
	}

	/** Menú del constructor: sin la biblioteca de kits del proveedor (importar kits en ZIP sigue en Herramientas). */
	public static function menu_slugs( $slugs ) {
		$slugs   = (array) $slugs;
		$slugs[] = 'elementor-app#/kit-library';
		if ( class_exists( '\Elementor\Plugin' ) && ! empty( \Elementor\Plugin::$instance->app ) ) {
			// Mismo cálculo que el elemento de menú del núcleo (editor-one-website-templates-menu.php), en sus dos formas.
			$full    = add_query_arg( array( 'return_to' => esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), 'source' => 'wp_db_templates_menu' ), \Elementor\Plugin::$instance->app->get_base_url() ) . '#/kit-library'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- se sanea igual que el núcleo.
			$slugs[] = $full;
			$parts   = wp_parse_url( $full );
			$slugs[] = basename( $parts['path'] ?? '' ) . ( empty( $parts['query'] ) ? '' : '?' . $parts['query'] ) . ( empty( $parts['fragment'] ) ? '' : '#' . $parts['fragment'] );
		}
		return $slugs;
	}

	/**
	 * Documento del editor (configuración adicional que el núcleo fusiona): biblioteca abierta en «Mis plantillas» y
	 * sin el enlace de ayuda del proveedor. Los «Need help» de cada widget los retira vendor-free.js.
	 */
	public static function document_config( $config ) {
		$config                                    = is_array( $config ) ? $config : array();
		$config['remoteLibrary']['default_route'] = 'templates/my-templates';
		$config['panel']['needHelpUrl']           = '';
		return $config;
	}

	/** Ajustes del editor: sin accesos a la cuenta, ayuda ni documentación del proveedor. */
	public static function editor_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		// La barra superior del editor lee estas claves sin comprobar que existan: se conservan, vacías.
		if ( isset( $settings['user']['top_bar'] ) && is_array( $settings['user']['top_bar'] ) ) {
			foreach ( array_keys( $settings['user']['top_bar'] ) as $key ) {
				$settings['user']['top_bar'][ $key ] = '';
			}
		}
		return self::strip_vendor_urls( $settings );
	}

	/** Cualquier cadena que sea una URL del proveedor queda vacía (los identificadores internos no cambian). */
	private static function strip_vendor_urls( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::strip_vendor_urls( $item );
			}
			return $value;
		}
		return is_string( $value ) && preg_match( '#^(https?:)?//#i', $value ) && self::vendor_url( $value ) ? '' : $value;
	}

	/** El paquete «Widget personalizado» del editor promociona Angie, el agente de IA del proveedor. */
	public static function editor_packages( $packages ) {
		return is_array( $packages ) ? array_values( array_diff( $packages, array( 'editor-widget-creation' ) ) ) : $packages;
	}

	/** Editor: fuera las novedades remotas y, con el script propio, los accesos del proveedor en sus menús. */
	public static function editor_scripts() {
		wp_dequeue_script( 'e-editor-notifications' );
		self::enqueue_script( 'editor' );
	}

	/** Administración y App: el script propio retira enlaces y vídeos del proveedor que pinta el núcleo. */
	public static function admin_script() {
		self::enqueue_script( did_action( 'elementor/app/init' ) && ! did_action( 'admin_enqueue_scripts' ) ? 'app' : 'admin' );
	}

	private static function enqueue_script( $context ) {
		if ( wp_script_is( self::SCRIPT, 'enqueued' ) ) {
			return;
		}
		$deps = 'editor' === $context && wp_script_is( 'elementor-v2-editor-app-bar', 'registered' ) ? array( 'elementor-v2-editor-app-bar' ) : array();
		wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/digitalisimo/vendor-free.js', DIGITALISIMO_ELEMENTS_FILE ), $deps, DIGITALISIMO_ELEMENTS_VERSION, true );
		wp_add_inline_script( self::SCRIPT, 'window.digitalisimoVendorFree=' . wp_json_encode( array(
			'hosts'   => self::HOSTS,
			'context' => $context,
			'about'   => admin_url( 'admin.php?page=digitalisimo-elements-about' ),
		) ) . ';', 'before' );
	}

	/**
	 * Widget Vídeo: el núcleo trae por defecto vídeos promocionales del proveedor y, como los valores por defecto no
	 * se guardan, también se ven en las páginas que nunca cambiaron la URL. Sin valor por defecto, un vídeo sin URL
	 * no se pinta en el sitio y el editor pide elegir uno.
	 */
	public static function video_defaults( $element ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'update_control' ) ) {
			return;
		}
		foreach ( array( 'youtube_url', 'vimeo_url', 'dailymotion_url', 'videopress_url' ) as $control ) {
			$element->update_control( $control, array( 'default' => '' ) );
		}
	}

	/** Elementos atómicos (YouTube): sin el vídeo del proveedor como valor por defecto. */
	public static function atomic_defaults( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		foreach ( $schema as $prop ) {
			if ( ! is_object( $prop ) || ! method_exists( $prop, 'get_default' ) || ! method_exists( $prop, 'default' ) ) {
				continue;
			}
			$default = $prop->get_default();
			$value   = is_array( $default ) ? ( $default['value'] ?? null ) : $default;
			if ( is_string( $value ) && ( preg_match( self::VIDEOS, $value ) || self::vendor_url( $value ) ) ) {
				$prop->default( '' );
			}
		}
		return $schema;
	}

	/** El mu-plugin de modo seguro que crea el núcleo no enlaza al proveedor desde la lista de plugins. */
	public static function plugin_row_meta( $links, $file ) {
		if ( false === strpos( (string) $file, 'elementor-safe-mode' ) ) {
			return $links;
		}
		return array_values( array_filter( (array) $links, static function ( $link ) {
			return ! preg_match( '#https?://[^"\']*(elementor\.com)#i', (string) $link );
		} ) );
	}
}
