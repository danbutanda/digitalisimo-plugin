<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Puerta de los paquetes globales de las funciones avanzadas (F-front, frente A; §3.1 de la especificación).
 *
 * `plugin.php` encola como siempre `elementor-pro-frontend` y `pro-elements-handlers` en `wp_footer` 10. La puerta
 * recorre los datos de cada documento impreso, decide por la **clase** de cada elemento y, en `wp_footer` 19, retira
 * los dos paquetes de la cola si nada de la página los pidió. Falla abierta: sin la transición aplicada
 * (`Transition::applied()['pro_conditional'] === true`), en el editor, en cualquier vista previa, ante una excepción o
 * ante cualquier duda, los paquetes se imprimen como hoy.
 *
 * Razones (basta una; `reasons()` las expone para diagnóstico y pruebas):
 *
 * - `doc:<id>:popup`: se imprime un documento de tipo popup (no cuenta en un extracto).
 * - `class:<tipo>`: la clase del elemento es, o extiende, la clase registrada de un nombre de `HANDLED`. Así se ven
 *   los widgets que heredan de uno con controlador sin nombrarlos.
 * - `setting:sticky`, `setting:motion`: ajustes que activan los controladores por tipo de elemento.
 * - `tag:<nombre>`, `action:<acción>`, `code:global`: cadenas de los datos (etiquetas dinámicas, enlaces de acción y
 *   código que nombra los globales). En un documento impreso se buscan en la cadena JSON guardada, sin volver a
 *   codificar; las estructurales, en los datos filtrados.
 * - `nested:<id>`: plantilla anidada (claves de `TEMPLATE_KEYS`, `templateID` del widget global o el shortcode
 *   `[elementor-template]`) cuyos datos tienen una razón; profundidad ≤ 3 y ≤ 50 documentos por petición.
 * - `third-party:<clase>`: elemento cuya clase está fuera de los tres espacios de nombres propios, salvo que el
 *   filtro `digitalisimo_elements_pro_frontend_third_party` lo declare libre.
 * - `render:<tipo>`: red de seguridad en `elementor/frontend/before_render`, mientras no haya otra razón.
 * - `snippets`: la bandera de fragmentos de código que nombran los globales (D12).
 * - `require:<motivo>`: API para otros módulos.
 * - `doubt:<motivo>`: excepción, JSON inválido, límites superados o una dependencia ajena de los paquetes.
 * - `always:<motivo>`: contextos donde la puerta no actúa.
 * - `late:<razón>`: una razón llegada después de imprimir los scripts del pie.
 *
 * Si se retiran los paquetes y el sitio tiene popups (`hasPopUps`, capturado del filtro de PRO), la réplica de
 * `setViewsAndSessions()` (`assets/popup-counter.js`) se añade en posición `before` de `elementor-frontend`: el código
 * `before` no impide el diferido que pide DIGITALÍSIMO SEO; nunca se usa `after`.
 */
final class Pro_Gate {
	/** Los 52 nombres de widget con controlador en los paquetes (`tests/elements-pro-inventory.mjs` los vigila). */
	const HANDLED = array(
		// `attachHandler("…")` en elements-handlers.js.
		'animated-headline',
		'archive-posts',
		'contact-buttons-var-10',
		'countdown',
		'facebook-button',
		'facebook-comments',
		'facebook-embed',
		'facebook-page',
		'floating-bars-var-2',
		'floating-bars-var-3',
		'form',
		'gallery',
		'hotspot',
		'loop-carousel',
		'loop-grid',
		'lottie',
		'media-carousel',
		'mega-menu',
		'nav-menu',
		'nested-carousel',
		'off-canvas',
		'portfolio',
		'posts',
		'reviews',
		'search',
		'search-form',
		'share-buttons',
		'slides',
		'subscribe',
		'table-of-contents',
		'taxonomy-filter',
		'testimonial-carousel',
		'woocommerce-cart',
		'woocommerce-checkout-page',
		'woocommerce-menu-cart',
		'woocommerce-my-account',
		'woocommerce-notices',
		'woocommerce-product-add-to-cart',
		'woocommerce-purchase-summary',
		// Arreglo recorrido con variable en elements-handlers.js.
		'contact-buttons-var-1',
		'contact-buttons-var-3',
		'contact-buttons-var-4',
		'contact-buttons-var-5',
		'contact-buttons-var-6',
		'contact-buttons-var-7',
		'contact-buttons-var-8',
		'contact-buttons-var-9',
		// `attachHandler("…")` en frontend.js.
		'code-highlight',
		'paypal-button',
		'progress-tracker',
		'stripe-button',
		// `frontend/element_ready/…` en frontend.js.
		'video-playlist',
	);

	/** Claves de ajustes que apuntan a una plantilla (también en repetidores y con sufijo, como `template_id_a`). */
	const TEMPLATE_KEYS = array( 'template_id', 'anywhere_id', 'digitalisimo_template_id' );

	/** Etiquetas dinámicas que imprimen acciones de los paquetes. */
	const TAGS = array( 'popup', 'off-canvas', 'reload-page' );

	/** Acciones de URL que registran los paquetes (`lightbox` es del núcleo y no cuenta). */
	const ACTIONS = array( 'popup', 'off_canvas', 'reload-page' );

	/** Ajustes que activan los efectos de movimiento, en cualquier elemento. */
	const MOTION_KEYS = array(
		'motion_fx_motion_fx_scrolling',
		'motion_fx_motion_fx_mouse',
		'background_motion_fx_motion_fx_scrolling',
		'background_motion_fx_motion_fx_mouse',
	);

	/** Los dos paquetes, en el orden en que `plugin.php` los encola (el segundo necesita el global del primero). */
	const HANDLES = array( 'elementor-pro-frontend', 'pro-elements-handlers' );

	/** Espacios de nombres propios: una clase fuera de ellos es de terceros. */
	const OWN_PREFIXES = array( 'Elementor\\', 'ElementorPro\\', 'Digitalisimo\\' );

	/** Bandera por sitio: algún fragmento de código publicado nombra los globales (D12). */
	const SNIPPETS_OPTION = 'digitalisimo_elements_front_snippets';

	const MAX_DEPTH = 3;
	const MAX_DOCS  = 50;

	/** `wp_footer` 19: después de los popups y del pie del núcleo (10), antes de imprimir los scripts (20). */
	const PRIORITY = 19;

	/** @var bool */
	private static $initialized = false;

	/** @var string[] Razones en el orden en que llegaron. */
	private static $reasons = array();

	/** @var string 'collect' hasta `wp_footer` 19, 'decided' hasta imprimir los scripts del pie, luego 'closed'. */
	private static $phase = 'collect';

	/** @var string|null Contexto memorizado ('' = la puerta puede actuar). */
	private static $context = null;

	/** @var array|null Datos filtrados del documento que está por imprimirse: `id` y `data`. */
	private static $pending = null;

	/** @var int Profundidad de extractos en curso: lo que se pinta en un extracto no cuenta. */
	private static $excerpt = 0;

	/** @var bool|null `hasPopUps` capturado del filtro de PRO; `null` si no corrió. */
	private static $has_popups = null;

	/** @var bool Los paquetes se retiraron de la cola. */
	private static $removed = false;

	/** @var string[] Cola antes de retirarlos, para devolverlos a su sitio. */
	private static $queue_before = array();

	/** @var bool La réplica de contadores está en `elementor-frontend`. */
	private static $counter_added = false;

	/** @var string|null Código de la réplica. */
	private static $counter = null;

	/** @var array|null Estado del recorrido de esta petición (memoria por id, límites, veredictos por clase). */
	private static $state = null;

	/** Ganchos. Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'capture_data' ), PHP_INT_MAX, 2 );
		add_action( 'elementor/frontend/before_get_builder_content', array( __CLASS__, 'before_content' ), PHP_INT_MAX, 2 );
		add_action( 'elementor/frontend/get_builder_content', array( __CLASS__, 'after_content' ), 10, 2 );
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ), 10, 1 );
		add_filter( 'elementor_pro/frontend/localize_settings', array( __CLASS__, 'capture_settings' ), PHP_INT_MAX );
		add_action( 'wp_footer', array( __CLASS__, 'decide' ), self::PRIORITY );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'close' ), 0 );
		// La bandera de fragmentos se recalcula al guardar, borrar o cambiar de estado un fragmento.
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted' ), 10, 2 );
	}

	/**
	 * Pide los paquetes en esta página. API para otros módulos (F7b: acciones que abren un popup o un panel lateral).
	 *
	 * @param string $reason Motivo corto (`[a-z0-9_:-]`).
	 */
	public static function require( string $reason ): void {
		$reason = preg_replace( '/[^a-z0-9_:\-]/i', '', $reason );
		self::add( 'require:' . ( '' !== $reason ? $reason : 'api' ) );
	}

	/** Razones recogidas en esta petición. */
	public static function reasons(): array {
		return self::$reasons;
	}

	/** ¿Necesita la página los paquetes? Verdadero también donde la puerta no actúa. */
	public static function needed(): bool {
		if ( '' !== self::context() || self::$reasons ) {
			return true;
		}
		return true === self::forced();
	}

	/**
	 * Razones de un árbol de elementos. Pura: no lee la base de datos ni el estado de la petición.
	 *
	 * Se detiene en la primera razón y devuelve como mucho una. `$load` puede devolver el arreglo de elementos o la
	 * cadena JSON guardada (las razones de cadena se buscan entonces en ella); `null` si el documento no existe.
	 *
	 * @param array    $elements Árbol de elementos.
	 * @param callable $class_of `( elType, widgetType )` → clase registrada o ''.
	 * @param callable $load     `( id )` → datos de una plantilla o `null`.
	 * @param int      $depth    Profundidad de anidado del árbol.
	 */
	public static function walk( array $elements, callable $class_of, callable $load, int $depth = 0 ): array {
		$state = self::new_state( $class_of, $load, false );
		try {
			$reason = self::tree( $elements, $state, $depth, true );
		} catch ( \Throwable $e ) {
			$reason = 'doubt:exception';
		}
		return '' === $reason ? array() : array( $reason );
	}

	/**
	 * `elementor/frontend/builder_content_data` (último): guarda los datos filtrados del documento que se va a pintar.
	 * Si quedaban datos de otra llamada que no pintó un documento, se recorren entonces.
	 *
	 * @param mixed $data    Árbol de elementos.
	 * @param mixed $post_id Documento.
	 *
	 * @return mixed Los mismos datos.
	 */
	public static function capture_data( $data, $post_id = 0 ) {
		try {
			self::flush_pending();
			if ( '' === self::context() ) {
				self::$pending = array(
					'id'   => (int) $post_id,
					'data' => $data,
				);
			}
		} catch ( \Throwable $e ) {
			self::add( 'doubt:exception' );
		}
		return $data;
	}

	/**
	 * `elementor/frontend/before_get_builder_content`: recorre el documento que se imprime.
	 *
	 * @param mixed $document   Documento.
	 * @param mixed $is_excerpt Se pinta para un extracto.
	 */
	public static function before_content( $document, $is_excerpt = false ): void {
		$pending       = self::$pending;
		self::$pending = null;
		try {
			if ( $is_excerpt ) {
				// Con datos vacíos el núcleo sale sin la acción final: sólo cuenta lo que se cerrará.
				if ( is_array( $pending ) && ! empty( $pending['data'] ) ) {
					self::$excerpt++;
				}
				return;
			}
			if ( '' !== self::context() ) {
				return;
			}
			$id = self::document_id( $document );
			if ( self::is_popup( $document ) ) {
				self::add( 'doc:' . $id . ':popup' );
				return;
			}
			$data = null;
			if ( is_array( $pending ) && ( 0 === $id || (int) $pending['id'] === $id || self::main_id( $document ) === (int) $pending['id'] ) ) {
				$data = $pending['data'];
				$id   = $id ? $id : (int) $pending['id'];
			}
			self::walk_printed( $id, $data );
		} catch ( \Throwable $e ) {
			self::add( 'doubt:exception' );
		}
	}

	/**
	 * `elementor/frontend/get_builder_content`: cierra un extracto.
	 *
	 * @param mixed $document   Documento.
	 * @param mixed $is_excerpt Se pintó para un extracto.
	 */
	public static function after_content( $document = null, $is_excerpt = false ): void {
		if ( $is_excerpt && self::$excerpt > 0 ) {
			self::$excerpt--;
		}
	}

	/**
	 * `elementor/frontend/before_render`: red de seguridad para elementos pintados fuera de un documento. Sólo
	 * mientras no haya razón; el widget global se juzga por su instancia original.
	 *
	 * @param mixed $element Elemento.
	 */
	public static function before_render( $element ): void {
		if ( self::$reasons || self::$excerpt > 0 || ! is_object( $element ) || '' !== self::context() ) {
			return;
		}
		try {
			if ( method_exists( $element, 'get_original_element_instance' ) ) {
				$original = $element->get_original_element_instance();
				if ( is_object( $original ) ) {
					$element = $original;
				}
			}
			$type   = method_exists( $element, 'get_name' ) ? (string) $element->get_name() : '';
			$state  = &self::state();
			$reason = self::verdict( get_class( $element ), $type, $state );
			if ( '' === $reason && method_exists( $element, 'get_data' ) ) {
				$settings = $element->get_data( 'settings' );
				$reason   = self::settings_reason( is_array( $settings ) ? $settings : array() );
			}
			if ( '' !== $reason ) {
				self::add( 'render:' . $type );
			}
		} catch ( \Throwable $e ) {
			self::add( 'doubt:render' );
		}
	}

	/**
	 * `elementor_pro/frontend/localize_settings` (último): captura `hasPopUps` sin consultas nuevas.
	 *
	 * @param mixed $settings Configuración del front de PRO.
	 *
	 * @return mixed La misma configuración.
	 */
	public static function capture_settings( $settings ) {
		if ( is_array( $settings ) ) {
			self::$has_popups = ! empty( $settings['popup']['hasPopUps'] );
		}
		return $settings;
	}

	/** `wp_footer` 19: retira los dos paquetes si nada los pidió. */
	public static function decide(): void {
		if ( 'collect' !== self::$phase ) {
			return;
		}
		self::$phase = 'decided';
		try {
			self::decide_now();
		} catch ( \Throwable $e ) {
			self::add( 'doubt:exception' );
		}
	}

	/** `wp_print_footer_scripts` 0: último momento para devolver los paquetes; después, las razones son tardías. */
	public static function close(): void {
		if ( 'closed' === self::$phase ) {
			return;
		}
		try {
			self::flush_pending();
			if ( self::$removed && self::any_enqueued() ) {
				self::add( 'doubt:dependency' );
			}
		} catch ( \Throwable $e ) {
			self::add( 'doubt:exception' );
		}
		self::$phase = 'closed';
	}

	/**
	 * Recalcula la bandera de fragmentos: algún fragmento publicado nombra `elementorProFrontend` o
	 * `ElementorProFrontendConfig`. La llaman los ganchos de los fragmentos y la transición.
	 */
	public static function refresh_snippets(): bool {
		$found = false;
		if ( function_exists( 'get_posts' ) ) {
			$ids = get_posts(
				array(
					'post_type'              => 'elementor_snippet',
					'post_status'            => 'publish',
					'numberposts'            => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
				)
			);
			$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
			if ( $ids && function_exists( 'update_meta_cache' ) ) {
				update_meta_cache( 'post', $ids );
			}
			foreach ( $ids as $id ) {
				$code = get_post_meta( $id, '_elementor_code', true );
				if ( is_string( $code ) && false !== stripos( $code, 'elementorProFrontend' ) ) {
					$found = true;
					break;
				}
			}
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::SNIPPETS_OPTION, $found ? '1' : '0', true );
		}
		return $found;
	}

	/**
	 * `added_post_meta`, `updated_post_meta`, `deleted_post_meta`: el código de un fragmento cambió.
	 *
	 * @param mixed $meta_id   Id de la meta.
	 * @param mixed $object_id Entrada.
	 * @param mixed $meta_key  Clave.
	 */
	public static function on_meta( $meta_id, $object_id = 0, $meta_key = '' ): void {
		if ( '_elementor_code' === $meta_key && function_exists( 'get_post_type' ) && 'elementor_snippet' === get_post_type( (int) $object_id ) ) {
			self::refresh_snippets();
		}
	}

	/**
	 * `transition_post_status`: un fragmento se publica o deja de estarlo.
	 *
	 * @param mixed $new_status Estado nuevo.
	 * @param mixed $old_status Estado anterior.
	 * @param mixed $post       Entrada.
	 */
	public static function on_status( $new_status, $old_status = '', $post = null ): void {
		if ( $new_status !== $old_status && ( 'publish' === $new_status || 'publish' === $old_status ) && is_object( $post ) && isset( $post->post_type ) && 'elementor_snippet' === $post->post_type ) {
			self::refresh_snippets();
		}
	}

	/**
	 * `deleted_post`: un fragmento se borró.
	 *
	 * @param mixed $post_id Entrada.
	 * @param mixed $post    Entrada borrada.
	 */
	public static function on_deleted( $post_id, $post = null ): void {
		if ( is_object( $post ) && isset( $post->post_type ) && 'elementor_snippet' === $post->post_type ) {
			self::refresh_snippets();
		}
	}

	/** @internal Vuelve al estado inicial de una petición (pruebas y procesos largos). */
	public static function reset(): void {
		self::$reasons       = array();
		self::$phase         = 'collect';
		self::$context       = null;
		self::$pending       = null;
		self::$excerpt       = 0;
		self::$has_popups    = null;
		self::$removed       = false;
		self::$queue_before  = array();
		self::$counter_added = false;
		self::$state         = null;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Decisión.

	private static function decide_now(): void {
		self::flush_pending();
		if ( '' !== self::context() ) {
			return;
		}
		if ( true === self::forced() ) {
			self::add( 'always:filter' );
			return;
		}
		if ( self::snippets() ) {
			self::add( 'snippets' );
		}
		if ( self::$reasons || ! self::any_enqueued() ) {
			return;
		}
		// Sólo se retira lo que encoló `plugin.php`: su filtro de configuración corrió.
		if ( null === self::$has_popups ) {
			self::add( 'doubt:config' );
			return;
		}
		$counter = self::$has_popups ? self::counter() : '';
		if ( self::$has_popups && '' === $counter ) {
			self::add( 'doubt:counter' );
			return;
		}
		$scripts            = function_exists( 'wp_scripts' ) ? wp_scripts() : null;
		self::$queue_before = is_object( $scripts ) && isset( $scripts->queue ) && is_array( $scripts->queue ) ? $scripts->queue : array();
		foreach ( array_reverse( self::HANDLES ) as $handle ) {
			wp_dequeue_script( $handle );
		}
		self::$removed = true;
		// Un script ajeno que depende de un paquete los trae igual: entonces viajan los dos, como hoy.
		if ( self::any_enqueued() ) {
			self::add( 'doubt:dependency' );
			return;
		}
		// D19: en una página sólo v4, `elementor-frontend` llegaba por los paquetes.
		if ( ! wp_script_is( 'elementor-frontend', 'enqueued' ) && self::has_content() ) {
			wp_enqueue_script( 'elementor-frontend' );
		}
		if ( '' !== $counter ) {
			wp_add_inline_script( 'elementor-frontend', $counter, 'before' );
			self::$counter_added = true;
		}
	}

	/** Filtro `digitalisimo_elements_pro_frontend_needed`: `true` fuerza la carga; cualquier otro valor no cambia nada. */
	private static function forced() {
		return function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_elements_pro_frontend_needed', null, self::$reasons ) : null;
	}

	/** Bandera de fragmentos; si falta, se calcula una vez. */
	private static function snippets(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$flag = get_option( self::SNIPPETS_OPTION, null );
		if ( null === $flag || false === $flag ) {
			return self::refresh_snippets();
		}
		return '1' === (string) $flag;
	}

	/** ¿Está en la cola alguno de los dos paquetes, directamente o como dependencia? */
	private static function any_enqueued(): bool {
		foreach ( self::HANDLES as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Devuelve los dos paquetes a la cola, en sus posiciones, y retira la réplica. */
	private static function restore(): void {
		if ( ! self::$removed ) {
			return;
		}
		self::$removed = false;
		foreach ( self::HANDLES as $handle ) {
			wp_enqueue_script( $handle );
		}
		$scripts = function_exists( 'wp_scripts' ) ? wp_scripts() : null;
		if ( is_object( $scripts ) && isset( $scripts->queue ) && is_array( $scripts->queue ) && self::$queue_before ) {
			$queue     = array_values( array_diff( $scripts->queue, self::HANDLES ) );
			$positions = array();
			foreach ( self::HANDLES as $handle ) {
				$index                = array_search( $handle, self::$queue_before, true );
				$positions[ $handle ] = false === $index ? PHP_INT_MAX : (int) $index;
			}
			asort( $positions );
			foreach ( $positions as $handle => $index ) {
				array_splice( $queue, min( $index, count( $queue ) ), 0, array( $handle ) );
			}
			$scripts->queue = $queue;
		}
		if ( self::$counter_added && is_object( $scripts ) && method_exists( $scripts, 'get_data' ) ) {
			$before = $scripts->get_data( 'elementor-frontend', 'before' );
			if ( is_array( $before ) ) {
				$counter = self::counter();
				$scripts->add_data( 'elementor-frontend', 'before', array_values( array_filter( $before, function ( $code ) use ( $counter ) { return $code !== $counter; } ) ) );
			}
		}
		self::$counter_added = false;
	}

	/** Réplica de contadores; '' si el archivo no está. */
	private static function counter(): string {
		if ( null === self::$counter ) {
			$file          = __DIR__ . '/assets/popup-counter.js';
			$code          = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Archivo propio del plugin.
			self::$counter = false === stripos( $code, '</script' ) ? trim( $code ) : '';
		}
		return self::$counter;
	}

	/** ¿Pintó la página contenido del constructor? */
	private static function has_content(): bool {
		$plugin = self::elementor();
		if ( $plugin && isset( $plugin->frontend ) && is_object( $plugin->frontend ) && method_exists( $plugin->frontend, 'has_elementor_in_page' ) ) {
			return (bool) $plugin->frontend->has_elementor_in_page();
		}
		return true;
	}

	/**
	 * Añade una razón. Después de decidir, devuelve los paquetes; después de imprimir los scripts, sólo la anota.
	 *
	 * @param string $reason Razón.
	 */
	private static function add( string $reason ): void {
		if ( 'closed' === self::$phase ) {
			$reason = 'late:' . $reason;
		}
		if ( ! in_array( $reason, self::$reasons, true ) ) {
			self::$reasons[] = $reason;
		}
		if ( self::$removed && 'closed' !== self::$phase ) {
			self::restore();
		}
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Contexto.

	/** '' si la puerta puede actuar; si no, la razón `always:` (que también queda anotada). */
	private static function context(): string {
		if ( null !== self::$context ) {
			return self::$context;
		}
		$context = self::compute_context();
		if ( '' !== $context && ! in_array( $context, self::$reasons, true ) ) {
			self::$reasons[] = $context;
		}
		// Las etiquetas condicionales sólo valen después de la consulta principal.
		if ( ! function_exists( 'did_action' ) || did_action( 'wp' ) ) {
			self::$context = $context;
		}
		return $context;
	}

	private static function compute_context(): string {
		if ( ! function_exists( 'wp_dequeue_script' ) || ! function_exists( 'wp_script_is' ) ) {
			return 'always:wordpress';
		}
		$transition = __NAMESPACE__ . '\\Transition';
		if ( ! class_exists( $transition ) || ! method_exists( $transition, 'applied' ) ) {
			return 'always:pro_conditional';
		}
		$applied = call_user_func( array( $transition, 'applied' ) );
		if ( ! is_array( $applied ) || ! isset( $applied['pro_conditional'] ) || true !== $applied['pro_conditional'] ) {
			return 'always:pro_conditional';
		}
		if ( ( function_exists( 'is_admin' ) && is_admin() ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return 'always:request';
		}
		$plugin = self::elementor();
		if ( ! $plugin ) {
			return 'always:builder';
		}
		if ( isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
			return 'always:editor';
		}
		if ( isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
			return 'always:preview';
		}
		if ( function_exists( 'is_preview' ) && is_preview() ) {
			return 'always:wp-preview';
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return 'always:customizer';
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Sólo se comprueba la presencia.
		if ( isset( $_GET['render_mode'] ) ) {
			return 'always:render_mode';
		}
		if ( isset( $_GET['theme_template_id'] ) ) {
			return 'always:theme_template_id';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return '';
	}

	/** Instancia del núcleo del constructor, o `null`. */
	private static function elementor() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return null;
		}
		$plugin = \Elementor\Plugin::$instance;
		return is_object( $plugin ) ? $plugin : null;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Documentos impresos.

	/** Recorre los datos que quedaron sin documento (otras llamadas del filtro de datos). */
	private static function flush_pending(): void {
		$pending       = self::$pending;
		self::$pending = null;
		if ( is_array( $pending ) && is_array( $pending['data'] ) && $pending['data'] && 0 === self::$excerpt && '' === self::context() ) {
			$state  = &self::state();
			$reason = self::tree( $pending['data'], $state, 0, true );
			if ( '' !== $reason ) {
				self::add( $reason );
			}
		}
	}

	/**
	 * Recorre un documento impreso una vez por petición: cadenas en el JSON guardado y estructura en los datos.
	 *
	 * @param int        $id   Documento.
	 * @param array|null $data Datos filtrados, o `null` para usar los guardados.
	 */
	private static function walk_printed( int $id, $data ): void {
		$state = &self::state();
		if ( $id > 0 && array_key_exists( $id, $state['memo'] ) ) {
			$reason = $state['memo'][ $id ];
		} else {
			$raw = $id > 0 ? self::raw_data( $id ) : null;
			if ( $id > 0 ) {
				$state['path'][ $id ] = true;
			}
			try {
				if ( ! is_array( $data ) ) {
					$reason = null === $raw ? '' : self::tree_from( $raw, $state, 0, $id );
				} else {
					$nested  = array();
					$reason  = '';
					$strings = true;
					if ( is_string( $raw ) ) {
						$reason  = self::scan_json( $raw, $nested );
						$strings = false;
					}
					if ( '' === $reason ) {
						$reason = self::tree( $data, $state, 0, $strings, $nested );
					}
				}
			} finally {
				unset( $state['path'][ $id ] );
			}
			if ( $id > 0 ) {
				$state['memo'][ $id ] = $reason;
			}
		}
		if ( '' !== $reason ) {
			self::add( $reason );
		}
	}

	/** `_elementor_data` guardado (cadena JSON o arreglo antiguo), o `null`. */
	private static function raw_data( int $id ) {
		if ( $id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return null;
		}
		$raw = get_post_meta( $id, '_elementor_data', true );
		if ( ( is_string( $raw ) && '' !== $raw ) || ( is_array( $raw ) && $raw ) ) {
			return $raw;
		}
		return null;
	}

	/** Id de la entrada cuyos datos se leen. */
	private static function document_id( $document ): int {
		if ( is_object( $document ) && method_exists( $document, 'get_post' ) ) {
			$post = $document->get_post();
			if ( is_object( $post ) && isset( $post->ID ) ) {
				return (int) $post->ID;
			}
		}
		return self::main_id( $document );
	}

	private static function main_id( $document ): int {
		return is_object( $document ) && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
	}

	private static function is_popup( $document ): bool {
		if ( ! is_object( $document ) ) {
			return false;
		}
		if ( is_a( $document, 'ElementorPro\\Modules\\Popup\\Document' ) ) {
			return true;
		}
		return method_exists( $document, 'get_name' ) && 'popup' === $document->get_name();
	}

	/** Estado de la petición, con las clases y los datos del constructor. */
	private static function &state(): array {
		if ( null === self::$state ) {
			self::$state = self::new_state(
				function ( $el_type, $widget_type ) {
					return self::resolve_class( (string) $el_type, (string) $widget_type );
				},
				function ( $id ) {
					return self::raw_data( (int) $id );
				},
				true
			);
		}
		return self::$state;
	}

	/** Clase registrada de un tipo de elemento, con memoria por petición. */
	private static function resolve_class( string $el_type, string $widget_type ): string {
		static $cache = array();
		$key = $el_type . '|' . $widget_type;
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$plugin   = self::elementor();
		$instance = null;
		if ( $plugin && 'widget' === $el_type ) {
			$instance = '' !== $widget_type && isset( $plugin->widgets_manager ) ? $plugin->widgets_manager->get_widget_types( $widget_type ) : null;
		} elseif ( $plugin && '' !== $el_type && isset( $plugin->elements_manager ) ) {
			$instance = $plugin->elements_manager->get_element_types( $el_type );
		}
		$cache[ $key ] = is_object( $instance ) ? get_class( $instance ) : '';
		return $cache[ $key ];
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Recorrido.

	private static function new_state( callable $class_of, callable $load, bool $request ): array {
		return array(
			'class_of' => $class_of,
			'load'     => $load,
			'request'  => $request,
			'handled'  => null,
			'verdicts' => array(),
			'memo'     => array(),
			'path'     => array(),
			'docs'     => 0,
		);
	}

	/**
	 * Primera razón de un árbol: primero su estructura y sus cadenas; después, sus plantillas anidadas.
	 *
	 * @param array $elements Árbol.
	 * @param array $state    Estado del recorrido.
	 * @param int   $depth    Profundidad de anidado del árbol.
	 * @param bool  $strings  Buscar razones de cadena en los ajustes (no, si ya se buscaron en el JSON).
	 * @param array $nested   Ids de plantillas ya encontrados en sus cadenas.
	 */
	private static function tree( array $elements, array &$state, int $depth, bool $strings, array $nested = array() ): string {
		$reason = self::elements( $elements, $state, $strings, $nested );
		if ( '' !== $reason ) {
			return $reason;
		}
		foreach ( array_keys( $nested ) as $id ) {
			$reason = self::nested( (int) $id, $state, $depth + 1 );
			if ( '' !== $reason ) {
				return $reason;
			}
		}
		return '';
	}

	/** Datos de una plantilla: cadena JSON guardada o arreglo. */
	private static function tree_from( $data, array &$state, int $depth, int $id ): string {
		if ( null === $data || '' === $data || array() === $data ) {
			return '';
		}
		if ( is_string( $data ) ) {
			$nested = array();
			$reason = self::scan_json( $data, $nested );
			if ( '' !== $reason ) {
				return $reason;
			}
			$decoded = json_decode( $data, true );
			if ( ! is_array( $decoded ) ) {
				return 'doubt:json:' . $id;
			}
			return self::tree( $decoded, $state, $depth, false, $nested );
		}
		if ( is_array( $data ) ) {
			return self::tree( $data, $state, $depth, true );
		}
		return 'doubt:load:' . $id;
	}

	/** Plantilla anidada: una vez por id, sin ciclos y dentro de los límites. */
	private static function nested( int $id, array &$state, int $depth ): string {
		if ( $id <= 0 || isset( $state['path'][ $id ] ) ) {
			return '';
		}
		if ( ! array_key_exists( $id, $state['memo'] ) ) {
			if ( $depth > self::MAX_DEPTH ) {
				return 'doubt:depth:' . $id;
			}
			if ( $state['docs'] >= self::MAX_DOCS ) {
				return 'doubt:limit';
			}
			$state['docs']++;
			$data                 = call_user_func( $state['load'], $id );
			$state['path'][ $id ] = true;
			try {
				$inner = self::tree_from( $data, $state, $depth, $id );
			} finally {
				unset( $state['path'][ $id ] );
			}
			$state['memo'][ $id ] = $inner;
		}
		$inner = $state['memo'][ $id ];
		if ( '' === $inner || 0 === strpos( $inner, 'doubt:' ) ) {
			return $inner;
		}
		return 'nested:' . $id;
	}

	private static function elements( array $elements, array &$state, bool $strings, array &$nested ): string {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$reason = self::element( $element, $state, $strings, $nested );
			if ( '' !== $reason ) {
				return $reason;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$reason = self::elements( $element['elements'], $state, $strings, $nested );
				if ( '' !== $reason ) {
					return $reason;
				}
			}
		}
		return '';
	}

	private static function element( array $element, array &$state, bool $strings, array &$nested ): string {
		$el_type     = isset( $element['elType'] ) && is_string( $element['elType'] ) ? $element['elType'] : '';
		$widget_type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
		$settings    = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$type        = 'widget' === $el_type && '' !== $widget_type ? $widget_type : $el_type;
		if ( '' !== $el_type ) {
			$class  = (string) call_user_func( $state['class_of'], $el_type, $widget_type );
			$reason = self::verdict( $class, $type, $state );
			if ( '' !== $reason ) {
				return $reason;
			}
		}
		$reason = self::settings_reason( $settings );
		if ( '' !== $reason ) {
			return $reason;
		}
		// Con la cadena JSON ya revisada (`scan_json()`), los ajustes no se vuelven a recorrer hoja por hoja.
		if ( $strings ) {
			$reason = self::scan_settings( $settings, $nested, 0 );
			if ( '' !== $reason ) {
				return $reason;
			}
		}
		if ( 'global' === $widget_type && isset( $element['templateID'] ) && is_numeric( $element['templateID'] ) && (int) $element['templateID'] > 0 ) {
			$nested[ (int) $element['templateID'] ] = true;
		}
		return '';
	}

	/**
	 * `class:` si la clase es o extiende una clase con controlador; `third-party:` si es ajena y no se declaró libre.
	 *
	 * @param string $class Clase registrada.
	 * @param string $type  Nombre del elemento.
	 * @param array  $state Estado del recorrido.
	 */
	private static function verdict( string $class, string $type, array &$state ): string {
		$class = ltrim( $class, '\\' );
		if ( '' === $class ) {
			return '';
		}
		if ( ! isset( $state['verdicts'][ $class ] ) ) {
			$handled = self::handled( $state );
			if ( ! $handled && $state['request'] ) {
				return 'doubt:handled';
			}
			$verdict = '';
			foreach ( $handled as $parent ) {
				if ( $class === $parent || is_subclass_of( $class, $parent ) ) {
					$verdict = 'class';
					break;
				}
			}
			if ( '' === $verdict && ! self::own_class( $class ) ) {
				$needs   = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_elements_pro_frontend_third_party', true, $class, $type ) : true;
				$verdict = false === $needs ? '' : 'third';
			}
			$state['verdicts'][ $class ] = $verdict;
		}
		if ( 'class' === $state['verdicts'][ $class ] ) {
			return 'class:' . $type;
		}
		return 'third' === $state['verdicts'][ $class ] ? 'third-party:' . $class : '';
	}

	/** Clases registradas de los nombres de `HANDLED`, construidas al primer uso. */
	private static function handled( array &$state ): array {
		if ( null === $state['handled'] ) {
			$state['handled'] = array();
			foreach ( self::HANDLED as $name ) {
				$class = ltrim( (string) call_user_func( $state['class_of'], 'widget', $name ), '\\' );
				if ( '' !== $class ) {
					$state['handled'][ $class ] = $class;
				}
			}
			$state['handled'] = array_values( $state['handled'] );
		}
		return $state['handled'];
	}

	private static function own_class( string $class ): bool {
		foreach ( self::OWN_PREFIXES as $prefix ) {
			if ( 0 === strpos( $class, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/** Ajustes que activan los controladores por tipo: con valor verdadero, como los lee el paquete. */
	private static function settings_reason( array $settings ): string {
		if ( self::truthy( $settings['sticky'] ?? null ) ) {
			return 'setting:sticky';
		}
		foreach ( self::MOTION_KEYS as $key ) {
			if ( self::truthy( $settings[ $key ] ?? null ) ) {
				return 'setting:motion';
			}
		}
		return '';
	}

	private static function truthy( $value ): bool {
		if ( is_string( $value ) ) {
			return '' !== $value;
		}
		return is_bool( $value ) || is_int( $value ) || is_float( $value ) ? (bool) $value : false;
	}

	/**
	 * Recorre los ajustes y sus repetidores: plantillas anidadas y razones de cadena.
	 *
	 * @param array $settings Ajustes.
	 * @param array $nested   Recibe los ids de plantillas.
	 * @param int   $level    Nivel de anidado de los ajustes.
	 */
	private static function scan_settings( array $settings, array &$nested, int $level ): string {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( $level >= 16 ) {
					return 'doubt:settings';
				}
				$reason = self::scan_settings( $value, $nested, $level + 1 );
				if ( '' !== $reason ) {
					return $reason;
				}
				continue;
			}
			if ( is_string( $key ) && self::template_key( $key ) && is_numeric( $value ) && (int) $value > 0 ) {
				$nested[ (int) $value ] = true;
			}
			if ( is_string( $value ) ) {
				$reason = self::scan_string( $value, $nested );
				if ( '' !== $reason ) {
					return $reason;
				}
			}
		}
		return '';
	}

	/**
	 * Una cadena JSON guardada completa: razones de cadena y plantillas anidadas (claves de `TEMPLATE_KEYS`, también
	 * con sufijo, y `templateID`), sin decodificar ni volver a codificar.
	 *
	 * @param string $json   `_elementor_data`.
	 * @param array  $nested Recibe los ids de plantillas.
	 */
	private static function scan_json( string $json, array &$nested ): string {
		$reason = self::scan_string( $json, $nested );
		if ( '' !== $reason ) {
			return $reason;
		}
		$keys  = implode( '|', array_map( 'preg_quote', self::TEMPLATE_KEYS ) );
		$found = preg_match_all( '~"(?:(?:' . $keys . ')(?:_[A-Za-z0-9_]+)?|templateID)"\s*:\s*"?(\d+)~', $json, $ids );
		if ( false === $found ) {
			return 'doubt:pattern';
		}
		foreach ( $ids[1] as $id ) {
			if ( (int) $id > 0 ) {
				$nested[ (int) $id ] = true;
			}
		}
		return '';
	}

	private static function template_key( string $key ): bool {
		foreach ( self::TEMPLATE_KEYS as $template_key ) {
			if ( $key === $template_key || 0 === strpos( $key, $template_key . '_' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Razones de cadena de un valor o de una cadena JSON completa (acepta comillas escapadas). Anota los ids del
	 * shortcode `[elementor-template]` como plantillas anidadas.
	 *
	 * @param string $text   Cadena.
	 * @param array  $nested Recibe los ids de plantillas.
	 */
	private static function scan_string( string $text, array &$nested ): string {
		if ( false === stripos( $text, 'lementor' ) ) {
			return '';
		}
		$found = preg_match( self::string_pattern(), $text, $match );
		if ( false === $found ) {
			return 'doubt:pattern';
		}
		if ( $found ) {
			if ( isset( $match['tag'] ) && '' !== $match['tag'] ) {
				return 'tag:' . strtolower( $match['tag'] );
			}
			if ( isset( $match['action'] ) && '' !== $match['action'] ) {
				return 'action:' . strtolower( $match['action'] );
			}
			return 'code:global';
		}
		if ( false === stripos( $text, '[elementor-template' ) ) {
			return '';
		}
		$found = preg_match_all( '~\[elementor-template\b[^\]]{0,200}?\bid\s*=\s*(?:\\\\{0,3}["\']|&quot;|&#0?39;)?\s*(\d+)~i', $text, $ids );
		if ( false === $found ) {
			return 'doubt:pattern';
		}
		foreach ( $ids[1] as $id ) {
			if ( (int) $id > 0 ) {
				$nested[ (int) $id ] = true;
			}
		}
		return '';
	}

	/** Patrón de las razones de cadena, construido con `TAGS` y `ACTIONS`. */
	private static function string_pattern(): string {
		static $pattern = null;
		if ( null === $pattern ) {
			$quote   = function ( $value ) {
				return preg_quote( $value, '~' );
			};
			$tags    = implode( '|', array_map( $quote, self::TAGS ) );
			$actions = implode( '|', array_map( $quote, self::ACTIONS ) );
			$pattern = '~\[elementor-tag\b[^\]]{0,500}?\bname=(?:\\\\{0,3}["\']|&quot;)?(?P<tag>' . $tags . ')(?=\\\\{0,3}["\'\s\]&])'
				. '|elementor-action(?::|%3A)action(?:=|%3D)(?P<action>' . $actions . ')'
				. '|(?P<code>elementorProFrontend)~i';
		}
		return $pattern;
	}
}
