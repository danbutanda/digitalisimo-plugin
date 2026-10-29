<?php
namespace Digitalisimo\Elements\F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Borrado de plantillas y consultas guardadas en uso (V.6, C1-9, C2-19).
 *
 * - **Bloqueo:** `pre_trash_post` y `pre_delete_post` devuelven `false` (no nulo: `wp_trash_post()` y
 *   `wp_delete_post()` se detienen sin tocar nada) para una entrada de `elementor_library` o `digitalisimo_query` con
 *   usos (`Usages::of()`: `publish`, `future` y `private`; los borradores no bloquean). Nunca `wp_die()`: quien
 *   intentó borrar ve un aviso de administración con la lista y el enlace a los usos.
 * - **Lo que ve el administrador** (`edit.php`): la fila muestra «En uso (N)», enlazado a la pantalla de usos, en lugar de
 *   «Papelera» o «Borrar permanentemente»; las acciones en lote (también «Vaciar papelera») quitan antes esos elementos y
 *   lo avisan (`load-edit.php`, con el nonce `bulk-posts` ya válido); el enlace directo de `post.php` vuelve a la lista
 *   con el aviso (`admin_action_trash`/`admin_action_delete`). Columna «Usos» en la lista de la biblioteca y pantalla
 *   oculta `admin.php?page=digitalisimo-elements-uses` con los documentos que el usuario puede editar.
 * - **Biblioteca del editor:** las acciones AJAX `delete_template` y `bulk_delete_templates` del núcleo
 *   (`elementor/includes/template-library/manager.php:1015`) se sustituyen por nombre en `elementor/ajax/register_actions`
 *   con prioridad 20 (el núcleo registra en la 10): sin usos, delegan en la función original del núcleo, leída de su
 *   registro de acciones; con usos, devuelven un error con la lista. Sólo se calcula para quien puede borrar esa
 *   plantilla (`delete_post`): a cualquier otro le responde el original, con su «Acceso denegado».
 * - **Sin bloqueo, sólo aviso:** durante una importación o una reversión de kits (K), dentro de `wp_delete_user()`, en
 *   WP-CLI y con la constante `DIGITALISIMO_FORCE_DELETE`. Mientras el recorrido de `Scan` calcula, nada se bloquea.
 */
final class Deletion {
	/** Tipos con usos y su clase de referencia. */
	const TYPES = array(
		'elementor_library'  => Usages::KIND_TEMPLATE,
		'digitalisimo_query' => Usages::KIND_QUERY,
	);

	const FORCE        = 'DIGITALISIMO_FORCE_DELETE';
	const NOTICE       = 'digitalisimo_elements_f7b_notice';
	const PAGE         = 'digitalisimo-elements-uses';
	const COLUMN       = 'digitalisimo_uses';
	const ROW_ACTION   = 'digitalisimo_in_use';
	const MAX_LISTED   = 5;
	const MAX_NOTICES  = 10;
	const AJAX_ACTIONS = array( 'delete_template', 'bulk_delete_templates' );

	/** Procesos de K durante los que nada se bloquea. */
	const KIT_HOOKS = array(
		'elementor/import-export-customization/import-kit',
		'elementor/import-export-customization/revert-kit',
		'elementor/import-export/import-kit',
		'elementor/import-export/revert-kit',
	);

	/** @var bool */
	private static $initialized = false;

	/** @var int Borrados de usuarios en curso. */
	private static $deleting_user = 0;

	/** @var bool Una importación o reversión de kits corre en esta petición. */
	private static $kit = false;

	/** @var array<string,callable> Funciones originales del núcleo, por acción AJAX. */
	private static $originals = array();

	/** @var array<string,bool> Pantallas cuya página de la lista ya se resolvió (`prime_screen()`). */
	private static $primed = array();

	/** @var bool Terminar la petición tras redirigir (las pruebas lo apagan). */
	private static $exit = true;

	/** @var string Última redirección (pruebas). */
	private static $redirected = '';

	/** Engancha el bloqueo y, en la administración, lo que ve el administrador. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'pre_trash_post', array( __CLASS__, 'pre_trash' ), 10, 3 );
		add_filter( 'pre_delete_post', array( __CLASS__, 'pre_delete' ), 10, 3 );
		add_action( 'delete_user', array( __CLASS__, 'user_start' ), 1, 0 );
		add_action( 'wpmu_delete_user', array( __CLASS__, 'user_start' ), 1, 0 );
		add_action( 'deleted_user', array( __CLASS__, 'user_end' ), 99, 0 );
		foreach ( self::KIT_HOOKS as $hook ) {
			add_action( $hook, array( __CLASS__, 'kit_start' ), 1, 0 );
		}
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register_ajax' ), 20, 1 );
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 20, 2 );
			add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 20, 2 );
			add_filter( 'manage_elementor_library_posts_columns', array( __CLASS__, 'columns' ), 20, 1 );
			add_action( 'manage_elementor_library_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
			add_action( 'load-edit.php', array( __CLASS__, 'filter_bulk' ), 1, 0 );
			add_action( 'admin_action_trash', array( __CLASS__, 'single_action' ), 1, 0 );
			add_action( 'admin_action_delete', array( __CLASS__, 'single_action' ), 1, 0 );
			add_action( 'admin_notices', array( __CLASS__, 'notices' ), 10, 0 );
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20, 0 );
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Bloqueo.

	/** `pre_trash_post`. */
	public static function pre_trash( $check, $post = null, $previous_status = '' ) {
		return self::check( $check, $post );
	}

	/** `pre_delete_post`. */
	public static function pre_delete( $check, $post = null, $force_delete = false ) {
		return self::check( $check, $post );
	}

	/**
	 * `false` si la entrada tiene usos y nada permite saltarse el bloqueo; si no, `$check` tal cual.
	 *
	 * @param mixed  $check Valor previo del filtro.
	 * @param object $post  Entrada.
	 * @return mixed
	 */
	private static function check( $check, $post ) {
		if ( null !== $check || ! is_object( $post ) || empty( $post->ID ) ) {
			return $check;
		}
		$kind = self::kind( $post );
		if ( '' === $kind || Scan::running() ) {
			return $check;
		}
		$uses = Usages::of( (int) $post->ID, $kind );
		if ( $uses['count'] <= 0 ) {
			return $check;
		}
		$bypass = self::bypass();
		if ( '' !== $bypass ) {
			self::warn( $post, $uses, $bypass );
			return $check;
		}
		self::remember( self::message( $post, $uses ) );
		return false;
	}

	/**
	 * Motivo por el que nada se bloquea en esta petición, o '': `force` (constante), `cli`, `user` (dentro de
	 * `wp_delete_user()`) o `kit` (importación o reversión de K).
	 */
	public static function bypass(): string {
		if ( defined( self::FORCE ) && constant( self::FORCE ) ) {
			return 'force';
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( self::$deleting_user > 0 ) {
			return 'user';
		}
		$context = '\\Digitalisimo\\Elements\\Import\\Kits\\Context';
		if ( self::$kit || ( class_exists( $context, false ) && method_exists( $context, 'active' ) && $context::active() ) ) {
			return 'kit';
		}
		return '';
	}

	/** `delete_user`/`wpmu_delete_user`. */
	public static function user_start(): void {
		++self::$deleting_user;
	}

	/** `deleted_user`. */
	public static function user_end(): void {
		self::$deleting_user = max( 0, self::$deleting_user - 1 );
	}

	/** Procesos de importación o reversión de kits: el resto de la petición no bloquea. */
	public static function kit_start(): void {
		self::$kit = true;
	}

	/** Clase de referencia (`t` o `q`) de una entrada, o ''. */
	public static function kind( $post ): string {
		if ( ! is_object( $post ) || empty( $post->post_type ) || ! is_string( $post->post_type ) ) {
			return '';
		}
		return isset( self::TYPES[ $post->post_type ] ) ? self::TYPES[ $post->post_type ] : '';
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Biblioteca del editor (AJAX).

	/**
	 * `elementor/ajax/register_actions` (20): sustituye por nombre las acciones de borrado de la biblioteca. Sin la
	 * función original no se sustituye nada (el bloqueo de `pre_delete_post` sigue).
	 *
	 * @param object $ajax Gestor AJAX del núcleo.
	 */
	public static function register_ajax( $ajax ): void {
		if ( ! is_object( $ajax ) || ! method_exists( $ajax, 'register_ajax_action' ) ) {
			return;
		}
		foreach ( self::AJAX_ACTIONS as $name ) {
			$original = self::original( $ajax, $name );
			if ( ! is_callable( $original ) ) {
				continue;
			}
			self::$originals[ $name ] = $original;
			$ajax->register_ajax_action( $name, array( __CLASS__, 'ajax_' . $name ) );
		}
	}

	/**
	 * La función que el núcleo registró para `$name`. El gestor no la expone: se lee de su registro privado
	 * (`$ajax_actions[ $tag ]['callback']`, `elementor/core/common/modules/ajax/module.php:35, 116`), con un cierre
	 * ligado a su clase; `elements-f7b-usages.php` vigila ese contrato.
	 *
	 * @param object $ajax Gestor AJAX.
	 * @return callable|null
	 */
	public static function original( $ajax, string $name ) {
		$core  = 'Elementor\\Core\\Common\\Modules\\Ajax\\Module';
		$scope = class_exists( $core, false ) && $ajax instanceof $core ? $core : get_class( $ajax );
		try {
			$read = \Closure::bind(
				function ( $name ) {
					return isset( $this->ajax_actions[ $name ]['callback'] ) ? $this->ajax_actions[ $name ]['callback'] : null;
				},
				$ajax,
				$scope
			);
			return $read ? $read( $name ) : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * `delete_template`: con usos, error con la lista; sin ellos, el original del núcleo.
	 *
	 * @param array  $data Datos de la acción.
	 * @param object $ajax Gestor AJAX.
	 * @return mixed
	 * @throws \Exception Si la plantilla está en uso.
	 */
	public static function ajax_delete_template( $data = array(), $ajax = null ) {
		$data = is_array( $data ) ? $data : array();
		self::guard_ajax( array( isset( $data['template_id'] ) ? $data['template_id'] : 0 ), $data );
		return self::delegate( 'delete_template', $data, $ajax );
	}

	/**
	 * `bulk_delete_templates`: si alguna está en uso, error con la lista y no se borra ninguna.
	 *
	 * @param array  $data Datos de la acción.
	 * @param object $ajax Gestor AJAX.
	 * @return mixed
	 * @throws \Exception Si alguna plantilla está en uso.
	 */
	public static function ajax_bulk_delete_templates( $data = array(), $ajax = null ) {
		$data = is_array( $data ) ? $data : array();
		self::guard_ajax( isset( $data['template_ids'] ) && is_array( $data['template_ids'] ) ? $data['template_ids'] : array(), $data );
		return self::delegate( 'bulk_delete_templates', $data, $ajax );
	}

	/** @throws \Exception Si alguna plantilla está en uso (código 409). */
	private static function guard_ajax( array $ids, array $data ): void {
		$source = isset( $data['source'] ) && is_string( $data['source'] ) ? $data['source'] : 'local';
		if ( 'local' !== $source || Scan::running() || '' !== self::bypass() ) {
			return;
		}
		$messages = array();
		foreach ( $ids as $raw ) {
			$id = is_numeric( $raw ) ? (int) $raw : 0;
			// Sólo para quien puede borrarla: a los demás les responde el original del núcleo, sin revelar usos.
			if ( $id <= 0 || ! current_user_can( 'delete_post', $id ) ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! is_object( $post ) || Usages::KIND_TEMPLATE !== self::kind( $post ) ) {
				continue;
			}
			$uses = Usages::of( $id, Usages::KIND_TEMPLATE );
			if ( $uses['count'] > 0 ) {
				$messages[] = self::message( $post, $uses )['text'];
			}
		}
		if ( $messages ) {
			throw new \Exception( implode( ' ', $messages ), 409 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Texto plano en la respuesta JSON.
		}
	}

	/** @return mixed */
	private static function delegate( string $name, array $data, $ajax ) {
		if ( ! isset( self::$originals[ $name ] ) || ! is_callable( self::$originals[ $name ] ) ) {
			throw new \Exception( esc_html__( 'Acción no disponible.', 'pro-elements' ), 400 );
		}
		return call_user_func( self::$originals[ $name ], $data, $ajax );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lista de la biblioteca (`edit.php`).

	/**
	 * `post_row_actions`/`page_row_actions`: «En uso (N)» en lugar de «Papelera» y «Borrar permanentemente».
	 *
	 * @param array  $actions Acciones de la fila.
	 * @param object $post    Entrada.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( ! is_array( $actions ) || ( ! isset( $actions['trash'] ) && ! isset( $actions['delete'] ) ) ) {
			return $actions;
		}
		$kind = self::kind( $post );
		if ( '' === $kind || Scan::running() || '' !== self::bypass() ) {
			return $actions;
		}
		self::prime_screen( $kind );
		$uses = Usages::of( (int) $post->ID, $kind );
		if ( $uses['count'] <= 0 ) {
			return $actions;
		}
		unset( $actions['trash'], $actions['delete'] );
		$actions[ self::ROW_ACTION ] = sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( self::uses_url( (int) $post->ID, $kind ) ),
			/* translators: %s: título. */
			esc_attr( sprintf( __( 'Ver dónde se usa «%s»; mientras se use no se puede borrar', 'pro-elements' ), self::title( (int) $post->ID ) ) ),
			/* translators: %d: número de documentos. */
			esc_html( sprintf( __( 'En uso (%d)', 'pro-elements' ), $uses['count'] ) )
		);
		return $actions;
	}

	/**
	 * Columna «Usos», antes de la fecha.
	 *
	 * @param array $columns Columnas.
	 * @return array
	 */
	public static function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$out[ self::COLUMN ] = __( 'Usos', 'pro-elements' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'Usos', 'pro-elements' );
		}
		return $out;
	}

	/** Celda de la columna «Usos». */
	public static function column( $column, $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		if ( Scan::running() ) {
			echo esc_html__( 'Calculando…', 'pro-elements' );
			return;
		}
		self::prime_screen( Usages::KIND_TEMPLATE );
		$id     = (int) $post_id;
		$uses   = Usages::of( $id, Usages::KIND_TEMPLATE );
		$drafts = count( $uses['drafts'] );
		if ( 0 === $uses['count'] && 0 === $drafts ) {
			echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Sin usos', 'pro-elements' ) . '</span>';
			return;
		}
		/* translators: %d: número de documentos. */
		$label = sprintf( _n( '%d documento', '%d documentos', $uses['count'], 'pro-elements' ), $uses['count'] );
		if ( $drafts > 0 ) {
			/* translators: %d: número de borradores. */
			$label .= ' · ' . sprintf( _n( '%d borrador', '%d borradores', $drafts, 'pro-elements' ), $drafts );
		}
		printf( '<a href="%1$s">%2$s</a>', esc_url( self::uses_url( $id, Usages::KIND_TEMPLATE ) ), esc_html( $label ) );
	}

	/** Resuelve con una consulta los usos de la página de la lista en pantalla. */
	private static function prime_screen( string $kind ): void {
		if ( isset( self::$primed[ $kind ] ) ) {
			return;
		}
		self::$primed[ $kind ] = true;
		$posts = isset( $GLOBALS['wp_query'] ) && is_object( $GLOBALS['wp_query'] ) && isset( $GLOBALS['wp_query']->posts ) && is_array( $GLOBALS['wp_query']->posts ) ? $GLOBALS['wp_query']->posts : array();
		$ids   = array();
		foreach ( $posts as $post ) {
			if ( is_object( $post ) && $kind === self::kind( $post ) ) {
				$ids[] = (int) $post->ID;
			}
		}
		Usages::prime( $ids, $kind );
	}

	/**
	 * `load-edit.php` (1): antes de que `edit.php` procese «Papelera», «Borrar permanentemente» o «Vaciar papelera» en
	 * lote, quita los elementos en uso y lo avisa. Sólo con el nonce `bulk-posts` válido (si no, `edit.php` se detiene
	 * solo) y sin añadir nunca un elemento a la petición.
	 */
	public static function filter_bulk(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- El nonce se comprueba abajo.
		$type = isset( $_REQUEST['post_type'] ) && is_string( $_REQUEST['post_type'] ) ? sanitize_key( $_REQUEST['post_type'] ) : 'post';
		$kind = isset( self::TYPES[ $type ] ) ? self::TYPES[ $type ] : '';
		if ( '' === $kind || Scan::running() || '' !== self::bypass() ) {
			return;
		}
		$delete_all = isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] );
		$action     = self::bulk_action();
		if ( ! $delete_all && 'trash' !== $action && 'delete' !== $action ) {
			return;
		}
		$nonce = isset( $_REQUEST['_wpnonce'] ) && is_string( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'bulk-posts' ) ) {
			return;
		}
		$field = '';
		if ( $delete_all ) {
			$ids = self::trashed( $type );
		} elseif ( isset( $_REQUEST['ids'] ) && is_string( $_REQUEST['ids'] ) ) {
			$field = 'ids';
			$ids   = array_map( 'intval', explode( ',', $_REQUEST['ids'] ) );
		} elseif ( ! empty( $_REQUEST['post'] ) ) {
			$field = 'post';
			$ids   = array_map( 'intval', (array) $_REQUEST['post'] );
		} else {
			return;
		}
		// phpcs:enable
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( ! $ids ) {
			return;
		}
		Usages::prime( $ids, $kind );
		$allowed = array();
		$blocked = 0;
		foreach ( $ids as $id ) {
			$uses = Usages::of( $id, $kind );
			if ( $uses['count'] > 0 && current_user_can( 'delete_post', $id ) ) {
				self::remember( self::message( get_post( $id ), $uses ) );
				++$blocked;
				continue;
			}
			$allowed[] = $id;
		}
		if ( 0 === $blocked ) {
			return;
		}
		if ( ! $allowed ) {
			self::redirect( self::back_url( $type ) );
			return;
		}
		if ( $delete_all ) {
			unset( $_REQUEST['delete_all'], $_REQUEST['delete_all2'], $_GET['delete_all'], $_GET['delete_all2'], $_POST['delete_all'], $_POST['delete_all2'] );
			$_REQUEST['action'] = 'delete';
			$_GET['action']     = 'delete';
			$field              = 'post';
		}
		if ( 'ids' === $field ) {
			$_REQUEST['ids'] = implode( ',', $allowed );
			$_GET['ids']     = $_REQUEST['ids'];
		} else {
			$_REQUEST['post'] = $allowed;
			$_GET['post']     = $allowed;
		}
	}

	/** Acción en lote pedida (`action`, o `action2` del selector de abajo). */
	private static function bulk_action(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'action', 'action2' ) as $key ) {
			$value = isset( $_REQUEST[ $key ] ) && is_string( $_REQUEST[ $key ] ) ? $_REQUEST[ $key ] : '';
			if ( '' !== $value && '-1' !== $value ) {
				return $value;
			}
		}
		// phpcs:enable
		return '';
	}

	/** Elementos de la papelera de un tipo (lo mismo que borra «Vaciar papelera» en `edit.php`). */
	private static function trashed( string $type ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_col' ) ) {
			return array();
		}
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'trash'", $type ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * `admin_action_trash`/`admin_action_delete` en `post.php`: el enlace directo de una plantilla en uso vuelve a la
	 * lista con el aviso, en lugar del «Error al mover a la papelera» de WordPress.
	 */
	public static function single_action(): void {
		$script = isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( 'post.php' !== $script || Scan::running() || '' !== self::bypass() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- El nonce se comprueba abajo.
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
		$id     = isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$nonce  = isset( $_REQUEST['_wpnonce'] ) && is_string( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		// phpcs:enable
		if ( ( 'trash' !== $action && 'delete' !== $action ) || $id <= 0 || '' === $nonce || ! wp_verify_nonce( $nonce, $action . '-post_' . $id ) ) {
			return;
		}
		$post = get_post( $id );
		$kind = self::kind( $post );
		if ( '' === $kind || ! current_user_can( 'delete_post', $id ) ) {
			return;
		}
		$uses = Usages::of( $id, $kind );
		if ( $uses['count'] <= 0 ) {
			return;
		}
		self::remember( self::message( $post, $uses ) );
		self::redirect( self::back_url( (string) $post->post_type ) );
	}

	private static function back_url( string $type ): string {
		$back = function_exists( 'wp_get_referer' ) ? wp_get_referer() : false;
		return is_string( $back ) && '' !== $back ? $back : admin_url( 'edit.php?post_type=' . rawurlencode( $type ) );
	}

	private static function redirect( string $url ): void {
		self::$redirected = $url;
		wp_safe_redirect( $url );
		if ( self::$exit ) {
			exit;
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Avisos y pantalla de usos.

	/**
	 * Texto del aviso y enlace a los usos.
	 *
	 * @param object $post Plantilla o consulta.
	 * @param array  $uses Resultado de `Usages::of()`.
	 * @return array{text:string,url:string}
	 */
	public static function message( $post, array $uses ): array {
		$id    = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
		$kind  = self::kind( $post );
		$count = (int) $uses['count'];
		$text  = sprintf(
			/* translators: 1: título, 2: número de documentos, 3: lista de títulos. */
			_n( '«%1$s» no se puede borrar porque se usa en %2$d documento: %3$s. Quítalo de ahí antes de borrarlo.', '«%1$s» no se puede borrar porque se usa en %2$d documentos: %3$s. Quítalo de ellos antes de borrarlo.', $count, 'pro-elements' ),
			self::title( $id ),
			$count,
			self::names( $uses['posts'] )
		);
		return array(
			'text' => $text,
			'url'  => '' === $kind ? '' : self::uses_url( $id, $kind ),
		);
	}

	/** Hasta cinco títulos de los documentos que el usuario puede editar, y cuántos más hay. */
	private static function names( array $ids ): string {
		$all    = defined( 'WP_CLI' ) && WP_CLI;
		$names  = array();
		$hidden = 0;
		foreach ( $ids as $doc ) {
			$doc = (int) $doc;
			if ( count( $names ) >= self::MAX_LISTED || ( ! $all && ! current_user_can( 'edit_post', $doc ) ) ) {
				++$hidden;
				continue;
			}
			$names[] = $all ? self::title( $doc ) . ' (' . $doc . ')' : self::title( $doc );
		}
		if ( ! $names ) {
			/* translators: %d: número de documentos. */
			return sprintf( _n( '%d documento que no puedes editar', '%d documentos que no puedes editar', $hidden, 'pro-elements' ), $hidden );
		}
		$list = implode( ', ', $names );
		if ( $hidden > 0 ) {
			/* translators: 1: lista de títulos, 2: cuántos más. */
			$list = sprintf( __( '%1$s y %2$d más', 'pro-elements' ), $list, $hidden );
		}
		return $list;
	}

	private static function title( int $id ): string {
		$title = function_exists( 'get_the_title' ) ? trim( wp_strip_all_tags( (string) get_the_title( $id ) ) ) : '';
		return '' === $title ? __( '(sin título)', 'pro-elements' ) : $title;
	}

	/** Enlace a la pantalla de usos. */
	public static function uses_url( int $id, string $kind ): string {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'kind' => $kind,
				'id'   => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Borrado permitido de algo en uso (constante, WP-CLI, borrado de un usuario, kits): sólo un aviso.
	 *
	 * @param object $post   Plantilla o consulta.
	 * @param array  $uses   Usos.
	 * @param string $reason Motivo de `bypass()`.
	 */
	private static function warn( $post, array $uses, string $reason ): void {
		$text = sprintf(
			/* translators: 1: título, 2: número de documentos. */
			_n( 'Se borró «%1$s», que se usaba en %2$d documento; ahí ya no se verá.', 'Se borró «%1$s», que se usaba en %2$d documentos; ahí ya no se verá.', (int) $uses['count'], 'pro-elements' ),
			self::title( (int) $post->ID ),
			(int) $uses['count']
		);
		if ( 'cli' === $reason ) {
			if ( class_exists( '\\WP_CLI' ) && method_exists( '\\WP_CLI', 'warning' ) ) {
				\WP_CLI::warning( $text . ' ' . self::names( $uses['posts'] ) );
			}
			return;
		}
		self::remember(
			array(
				'text' => $text,
				'url'  => '',
			)
		);
	}

	/**
	 * Guarda un aviso para el usuario actual (meta de usuario; se muestra una vez en la administración).
	 *
	 * @param array $message `text` y `url`.
	 */
	private static function remember( array $message ): void {
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user <= 0 || '' === $message['text'] ) {
			return;
		}
		$stored = get_user_meta( $user, self::NOTICE, true );
		$stored = is_array( $stored ) ? $stored : array();
		foreach ( $stored as $item ) {
			if ( is_array( $item ) && isset( $item['text'] ) && $item['text'] === $message['text'] ) {
				return;
			}
		}
		$stored[] = $message;
		update_user_meta( $user, self::NOTICE, array_slice( $stored, -self::MAX_NOTICES ) );
	}

	/** `admin_notices`: los avisos guardados del usuario, una vez. */
	public static function notices(): void {
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user <= 0 ) {
			return;
		}
		$stored = get_user_meta( $user, self::NOTICE, true );
		if ( ! is_array( $stored ) || ! $stored ) {
			return;
		}
		delete_user_meta( $user, self::NOTICE );
		foreach ( $stored as $item ) {
			if ( ! is_array( $item ) || empty( $item['text'] ) ) {
				continue;
			}
			$link = empty( $item['url'] ) ? '' : ' <a href="' . esc_url( (string) $item['url'] ) . '">' . esc_html__( 'Ver dónde se usa', 'pro-elements' ) . '</a>';
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( (string) $item['text'] ) . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
		}
	}

	/** Pantalla oculta de usos (`admin.php?page=digitalisimo-elements-uses`, con el diseño compartido `digitalisimo-ui`). */
	public static function menu(): void {
		add_submenu_page( '', __( 'Dónde se usa', 'pro-elements' ), __( 'Dónde se usa', 'pro-elements' ), 'edit_posts', self::PAGE, array( __CLASS__, 'render_page' ) );
	}

	/** Lista de los documentos que usan una plantilla o una consulta guardada. */
	public static function render_page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Sólo lectura.
		$kind = isset( $_GET['kind'] ) && Usages::KIND_QUERY === $_GET['kind'] ? Usages::KIND_QUERY : Usages::KIND_TEMPLATE;
		$id   = isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		// phpcs:enable
		$post = $id > 0 ? get_post( $id ) : null;
		echo '<div class="wrap">';
		if ( ! is_object( $post ) || self::kind( $post ) !== $kind || ! current_user_can( 'edit_post', $id ) ) {
			echo '<h1>' . esc_html__( 'Dónde se usa', 'pro-elements' ) . '</h1><p>' . esc_html__( 'No existe o no tienes permiso para verlo.', 'pro-elements' ) . '</p></div>';
			return;
		}
		/* translators: %s: título. */
		echo '<h1>' . esc_html( sprintf( __( 'Dónde se usa «%s»', 'pro-elements' ), self::title( $id ) ) ) . '</h1>';
		if ( Scan::running() ) {
			echo '<p>' . esc_html__( 'Calculando…', 'pro-elements' ) . '</p></div>';
			return;
		}
		$uses = Usages::of( $id, $kind );
		$rows = array_merge( $uses['posts'], $uses['drafts'] );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No se usa en ningún documento: se puede borrar.', 'pro-elements' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Mientras se use en un documento publicado, programado o privado, no se puede borrar. Los borradores no lo impiden.', 'pro-elements' ) . '</p>';
			echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Documento', 'pro-elements' ) . '</th><th scope="col">' . esc_html__( 'Tipo', 'pro-elements' ) . '</th><th scope="col">' . esc_html__( 'Estado', 'pro-elements' ) . '</th></tr></thead><tbody>';
			$hidden = 0;
			foreach ( $rows as $doc ) {
				$doc = (int) $doc;
				if ( ! current_user_can( 'edit_post', $doc ) ) {
					++$hidden;
					continue;
				}
				$type   = get_post_type_object( (string) get_post_type( $doc ) );
				$status = get_post_status_object( (string) get_post_status( $doc ) );
				$link   = get_edit_post_link( $doc );
				$name   = esc_html( self::title( $doc ) );
				echo '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . $name . '</a>' : $name ) . '</td><td>' . esc_html( $type && isset( $type->labels->singular_name ) ? $type->labels->singular_name : '' ) . '</td><td>' . esc_html( $status && isset( $status->label ) ? $status->label : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
			}
			echo '</tbody></table>';
			if ( $hidden > 0 ) {
				/* translators: %d: número de documentos. */
				echo '<p>' . esc_html( sprintf( _n( 'Y %d documento que no puedes editar.', 'Y %d documentos que no puedes editar.', $hidden, 'pro-elements' ), $hidden ) ) . '</p>';
			}
		}
		$back = Usages::KIND_TEMPLATE === $kind ? admin_url( 'edit.php?post_type=elementor_library' ) : '';
		if ( '' !== $back ) {
			echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( 'Volver a las plantillas', 'pro-elements' ) . '</a></p>';
		}
		echo '</div>';
	}

	/** Para las pruebas. */
	public static function reset( bool $exit = true ): void {
		self::$initialized   = false;
		self::$deleting_user = 0;
		self::$kit           = false;
		self::$originals     = array();
		self::$primed        = array();
		self::$exit          = $exit;
		self::$redirected    = '';
	}

	/** Para las pruebas: última redirección. */
	public static function redirected(): string {
		return self::$redirected;
	}
}
