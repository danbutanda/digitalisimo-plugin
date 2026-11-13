<?php
/**
 * El CSS al día tras cada escritura del MCP de DIGITALÍSIMO Elements (`modules/digitalisimo-mcp/`):
 * - `Css_Sync`: sólo dentro de una herramienta `elementor/…` (Abilities API o proxy del editor) anota el documento vivo
 *   que cambió (su propio guardado, `_elementor_data` escrito en él, su paso a `publish`; nunca el autoguardado de una
 *   publicada) y al cerrar la herramienta, o al terminar la petición, invalida sus estilos locales y de clases globales
 *   en todos los contextos (la acción del núcleo), su lista de componentes (`Cache_Validity` real del núcleo) y su caché
 *   HTML, una vez por documento; nada fuera de esas herramientas; un enlace firmado válido de `create-preview-link`
 *   (`Preview_Token` real del núcleo) invalida la página antes y después de pintarse, y uno falso, vencido o de otra
 *   página no hace nada.
 * - `Font_Stack`: una lista de familias en `font-family` sale como CSS válido; con el transformador real del núcleo
 *   (`font-family-transformer.php`) la misma lista sale `""Geist Mono", …"` (lo que el navegador descarta) y una sola
 *   familia sigue saliendo igual que en el núcleo.
 *
 * Sin WordPress: dobles mínimos de sus funciones y de las clases base del núcleo; las clases del núcleo que importan
 * (Cache_Validity, Preview_Token y el transformador de font-family) se cargan de `digitalisimo-elements/elementor/`.
 * Falla con el código anterior: no existe `modules/digitalisimo-mcp/`.
 */
namespace Elementor\Core\Files {
	/** La carpeta de las hojas del ensayo (`CSS_Files_Manager` usa `<base>css/<handle>.css`). */
	class Base {
		public static function get_base_uploads_dir() {
			return $GLOBALS['mc_upload_dir'];
		}
	}
}

namespace Elementor {
	/** Sólo los puntos de corte (`breakpoints->get_breakpoints()`), como en el núcleo. */
	class Plugin {
		public static $instance;
		public $breakpoints;
	}
}

namespace Elementor\Modules\AtomicWidgets\PropsResolver {
	class Props_Resolver_Context {
	}
	abstract class Transformer_Base {
		abstract public function transform( $value, Props_Resolver_Context $context );
	}
	/** Como el registro del núcleo (transformers-registry.php): `register()` reemplaza por clave. */
	class Transformers_Registry {
		public $items = array();
		public function register( string $key, Transformer_Base $transformer ): self {
			$this->items[ $key ] = $transformer;
			return $this;
		}
		public function get( $key, $fallback = null ) {
			return $this->items[ $key ] ?? $fallback;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$root = dirname( __DIR__ ) . '/digitalisimo-elements';

	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);

	$GLOBALS['mc_fail']   = array();
	$GLOBALS['mc_checks'] = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['mc_checks'];
		if ( ! $ok ) {
			$GLOBALS['mc_fail'][] = $message;
			echo "FALLA: $message\n";
		}
	}

	// PHP 7.4: el transformador del núcleo usa str_starts_with/str_ends_with (WordPress los trae en compat.php).
	if ( ! function_exists( 'str_starts_with' ) ) {
		function str_starts_with( $haystack, $needle ) {
			return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
		}
	}
	if ( ! function_exists( 'str_ends_with' ) ) {
		function str_ends_with( $haystack, $needle ) {
			return '' === $needle || substr( $haystack, -strlen( $needle ) ) === $needle;
		}
	}

	/** WordPress en memoria: opciones, metas, entradas y lo que cada acción del núcleo recibió (y los ganchos, con $hooks). */
	function mc_reset( bool $hooks = false ): void {
		$GLOBALS['mc'] = array(
			'hooks'   => $hooks || ! isset( $GLOBALS['mc']['hooks'] ) ? array() : $GLOBALS['mc']['hooks'],
			'options' => array(),
			'deleted' => array(),
			'clears'  => array(),
			'posts'   => array(),
			'updated' => array(),
		);
		$_GET = array();
		if ( function_exists( 'mc_seed' ) && class_exists( 'Elementor\\Modules\\AtomicWidgets\\Styles\\CacheValidity\\Cache_Validity', false ) ) {
			mc_seed( 50, 60, 70, 80, 81, 82, 90, 93, 999 );
		}
	}
	mc_reset();
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['mc']['hooks'][ $hook ][] = array( $callback, $priority, $args );
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		add_action( $hook, $callback, $priority, $args );
	}
	function remove_filter( $hook, $callback ) {
		foreach ( $GLOBALS['mc']['hooks'][ $hook ] ?? array() as $i => $entry ) {
			if ( $entry[0] === $callback ) {
				unset( $GLOBALS['mc']['hooks'][ $hook ][ $i ] );
			}
		}
		return true;
	}
	function mc_run( string $hook, array $args ) {
		$value = $args[0] ?? null;
		foreach ( $GLOBALS['mc']['hooks'][ $hook ] ?? array() as $entry ) {
			$args[0] = $value;
			$value   = call_user_func_array( $entry[0], array_slice( $args, 0, $entry[2] ) );
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		if ( 'elementor/atomic-widgets/styles/clear' === $hook ) {
			$GLOBALS['mc']['clears'][] = implode( '/', $args[0] );
			return;
		}
		mc_run( $hook, $args );
	}
	function apply_filters( $hook, ...$args ) {
		return mc_run( $hook, $args );
	}
	function get_option( $name, $default = false ) {
		$pre = apply_filters( "pre_option_{$name}", false, $name, $default );
		if ( false !== $pre ) {
			return $pre;
		}
		return array_key_exists( $name, $GLOBALS['mc']['options'] ) ? $GLOBALS['mc']['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['mc']['options'][ $name ] = $value;
		return true;
	}
	function delete_option( $name ) {
		unset( $GLOBALS['mc']['options'][ $name ] );
		return true;
	}
	function delete_post_meta( $id, $key ) {
		$GLOBALS['mc']['deleted'][] = $id . ':' . $key;
		return true;
	}
	function get_post( $id ) {
		return $GLOBALS['mc']['posts'][ (int) $id ] ?? null;
	}
	function wp_is_post_revision( $post ) {
		$p = is_object( $post ) ? $post : get_post( $post );
		return $p && 'revision' === $p->post_type ? (int) $p->post_parent : false;
	}
	function get_post_status( $id ) {
		$p = get_post( $id );
		return $p ? $p->post_status : false;
	}
	/** Como WordPress: un guardado normal dispara sus ganchos (aquí, save_post, transition_post_status y una meta). */
	function wp_update_post( $data ) {
		$id = (int) ( $data['ID'] ?? 0 );
		$GLOBALS['mc']['updated'][] = $id;
		$p = get_post( $id );
		do_action( 'transition_post_status', $p->post_status, $p->post_status, $p );
		do_action( 'updated_post_meta', 99, $id, '_elementor_data', '[]' );
		do_action( 'save_post', $id, $p, true );
		return $id;
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
	function wp_salt( $scheme = 'auth' ) {
		return 'sal-del-ensayo-' . $scheme;
	}
	function mc_post( int $id, string $type = 'page', int $parent = 0, string $status = 'draft' ) {
		$GLOBALS['mc']['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => $type, 'post_parent' => $parent, 'post_status' => $status );
		return $GLOBALS['mc']['posts'][ $id ];
	}

	/** Un documento del constructor: su entrada y la principal (la misma, o la madre de un autoguardado). */
	class Mc_Document {
		private $post;
		private $main;
		public function __construct( $post, int $main ) {
			$this->post = $post;
			$this->main = $main;
		}
		public function get_post() {
			return $this->post;
		}
		public function get_main_id() {
			return $this->main;
		}
	}
	class Mc_Request {
		private $route;
		private $method;
		public function __construct( string $route, string $method = 'POST' ) {
			$this->route  = $route;
			$this->method = $method;
		}
		public function get_route() {
			return $this->route;
		}
		public function get_method() {
			return $this->method;
		}
	}

	class Mc_Breakpoint {
		private $name;
		public function __construct( string $name ) {
			$this->name = $name;
		}
		public function get_name() {
			return $this->name;
		}
	}
	class Mc_Breakpoints {
		public function get_breakpoints() {
			return array( 'mobile' => new Mc_Breakpoint( 'mobile' ), 'tablet' => new Mc_Breakpoint( 'tablet' ) );
		}
	}
	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->breakpoints = new Mc_Breakpoints();
	$GLOBALS['mc_upload_dir']                  = sys_get_temp_dir() . '/digi-mc-css-' . getmypid() . '/';
	if ( ! is_dir( $GLOBALS['mc_upload_dir'] . 'css' ) ) {
		mkdir( $GLOBALS['mc_upload_dir'] . 'css', 0777, true );
	}
	register_shutdown_function(
		static function () {
			array_map( 'unlink', glob( $GLOBALS['mc_upload_dir'] . 'css/*' ) ?: array() );
			if ( is_dir( $GLOBALS['mc_upload_dir'] . 'css' ) ) {
				rmdir( $GLOBALS['mc_upload_dir'] . 'css' );
				rmdir( $GLOBALS['mc_upload_dir'] );
			}
		}
	);

	// Las clases del núcleo que usa el módulo, tal cual.
	require $root . '/elementor/modules/atomic-widgets/styles/cache-validity/cache-validity-item.php';
	require $root . '/elementor/modules/atomic-widgets/styles/cache-validity/cache-validity.php';
	require $root . '/elementor/modules/mcp/preview/preview-token.php';
	require $root . '/elementor/modules/atomic-widgets/props-resolver/transformers/styles/font-family-transformer.php';
	require $root . '/modules/digitalisimo-mcp/class-module.php';

	use Digitalisimo\Elements\Mcp\Css_Sync;
	use Digitalisimo\Elements\Mcp\Font_Stack;
	use Elementor\Modules\AtomicWidgets\PropsResolver\Props_Resolver_Context;
	use Elementor\Modules\AtomicWidgets\PropsResolver\Transformers\Styles\Font_Family_Transformer as Core_Font_Family;
	use Elementor\Modules\AtomicWidgets\PropsResolver\Transformers_Registry;
	use Elementor\Modules\AtomicWidgets\Styles\CacheValidity\Cache_Validity;
	use Elementor\Modules\Mcp\Preview\Preview_Token;

	/** Arranca el módulo en un WordPress en memoria limpio. */
	function mc_boot(): void {
		mc_reset( true );
		Css_Sync::reset();
		if ( class_exists( '\\Digitalisimo\\Elements\\Mcp\\Preview_Link' ) ) {
			\Digitalisimo\Elements\Mcp\Preview_Link::reset();
		}
		Font_Stack::reset();
		\Digitalisimo\Elements\Mcp\Module::init();
	}
	/** Hojas válidas de estas páginas (como tras visitarlas): local en los dos contextos y global. */
	function mc_seed( int ...$ids ): void {
		$validity = new Cache_Validity();
		foreach ( $ids as $id ) {
			$validity->validate( array( 'local', $id, 'frontend', 'desktop' ), array( 'should_exist' => true ) );
			$validity->validate( array( 'local', $id, 'preview', 'desktop' ), array( 'should_exist' => true ) );
			$validity->validate( array( 'global', $id, 'frontend', 'desktop' ), array( 'should_exist' => true ) );
		}
	}
	/**
	 * Lo que quedó invalidado de una página: «local/ID» si sus dos hojas locales, «global/ID» si la global; y cuántas
	 * veces se borró su caché HTML. Ningún archivo se borra (la acción del núcleo que los borra no se usa).
	 */
	function mc_cleared( int $id ): array {
		$validity = new Cache_Validity();
		$clears   = array();
		if ( ! $validity->is_valid( array( 'local', $id, 'frontend', 'desktop' ) ) && ! $validity->is_valid( array( 'local', $id, 'preview', 'desktop' ) ) ) {
			$clears[] = 'local/' . $id;
		}
		if ( ! $validity->is_valid( array( 'global', $id, 'frontend', 'desktop' ) ) ) {
			$clears[] = 'global/' . $id;
		}
		$html = count( array_keys( $GLOBALS['mc']['deleted'], $id . ':_elementor_element_cache', true ) );
		return array( 'clears' => $clears, 'html' => $html );
	}
	/** Nada invalidado ni borrado de estas páginas ni de la testigo 999, y ningún archivo borrado. */
	function mc_untouched( int ...$ids ): bool {
		foreach ( array_merge( $ids, array( 999 ) ) as $id ) {
			$got = mc_cleared( $id );
			if ( array() !== $got['clears'] || 0 !== $got['html'] ) {
				return false;
			}
		}
		return array() === $GLOBALS['mc']['clears'];
	}
	/** Estado de Preview_Link (vacío si el módulo no lo trae: código anterior). */
	function mc_preview_state(): array {
		return class_exists( '\\Digitalisimo\\Elements\\Mcp\\Preview_Link' ) ? \Digitalisimo\Elements\Mcp\Preview_Link::state() : array( 'post' => 0, 'live' => null );
	}
	function mc_related_valid( int $id ): bool {
		return ( new Cache_Validity() )->is_valid( array( 'component-styles-related-posts', (string) $id ) );
	}
	$ctx = new Props_Resolver_Context();

	// ---------------------------------------------------------------------------------------------------------------
	// 1. Ganchos.
	mc_boot();
	$hooks = $GLOBALS['mc']['hooks'];
	foreach ( array( 'wp_before_execute_ability', 'wp_after_execute_ability', 'rest_request_before_callbacks', 'rest_request_after_callbacks', 'elementor/document/after_save', 'added_post_meta', 'updated_post_meta', 'transition_post_status', 'parse_request', 'shutdown', 'elementor/atomic-widgets/styles/transformers/register' ) as $hook ) {
		check( ! empty( $hooks[ $hook ] ), "init() engancha $hook" );
	}
	check( 100 === $hooks['elementor/document/after_save'][0][1], 'after_save con prioridad 100 (después de los del núcleo, en 10 y 20)' );
	check( 2 === $hooks['parse_request'][0][1], 'parse_request con prioridad 2 (después del manejador de enlaces firmados del núcleo, en 1)' );
	check( 20 === $hooks['elementor/atomic-widgets/styles/transformers/register'][0][1], 'el transformador se registra con prioridad 20 (el del núcleo, en 10)' );
	$counts = array_map( 'count', $GLOBALS['mc']['hooks'] );
	\Digitalisimo\Elements\Mcp\Module::init();
	check( $counts === array_map( 'count', $GLOBALS['mc']['hooks'] ) && 2 === count( $GLOBALS['mc']['hooks']['shutdown'] ) && 1 === count( $GLOBALS['mc']['hooks']['parse_request'] ), 'init() es idempotente (shutdown: Css_Sync y Preview_Link)' );

	// ---------------------------------------------------------------------------------------------------------------
	// 2. Fuera de una herramienta del constructor no se anota nada (el editor y lo demás siguen como en el núcleo).
	mc_boot();
	$draft = mc_post( 50 );
	do_action( 'elementor/document/after_save', new Mc_Document( $draft, 50 ), array() );
	do_action( 'updated_post_meta', 1, 50, '_elementor_data', '[]' );
	do_action( 'transition_post_status', 'publish', 'draft', $draft );
	do_action( 'shutdown' );
	check( mc_untouched( 50 ), 'sin herramienta del MCP no se invalida nada (guardado del editor, meta y publicación)' );
	do_action( 'wp_before_execute_ability', 'digitalisimo-woo/products', array() );
	do_action( 'elementor/document/after_save', new Mc_Document( $draft, 50 ), array() );
	do_action( 'wp_after_execute_ability', 'digitalisimo-woo/products', array(), array() );
	check( array() === Css_Sync::state()['pending'] && mc_untouched( 50 ), 'una herramienta que no es del constructor no abre el ámbito' );

	// ---------------------------------------------------------------------------------------------------------------
	// 3. build-composition sobre un borrador: al cerrar la herramienta, sus hojas local y global (todos los contextos),
	//    su caché HTML y su lista de componentes; una sola vez aunque se guarde dos veces.
	mc_boot();
	( new Cache_Validity() )->validate( array( 'component-styles-related-posts', '50' ), array( 7 ) );
	check( mc_related_valid( 50 ), 'la lista de componentes de 50 empieza válida' );
	$draft = mc_post( 50 );
	do_action( 'wp_before_execute_ability', 'elementor/build-composition', array( 'post_id' => 50 ) );
	do_action( 'updated_post_meta', 3, 50, '_elementor_data', '[]' );
	do_action( 'elementor/document/after_save', new Mc_Document( $draft, 50 ), array() );
	do_action( 'elementor/document/after_save', new Mc_Document( $draft, 50 ), array() );
	check( array( 50 ) === Css_Sync::state()['pending'] && mc_untouched( 50 ), 'durante la herramienta sólo se anota (una vez)' );
	do_action( 'wp_after_execute_ability', 'elementor/build-composition', array( 'post_id' => 50 ), array( 'success' => true ) );
	$got = mc_cleared( 50 );
	check( array( 'local/50', 'global/50' ) === $got['clears'], 'al cerrar: las hojas locales (frontend y preview) y la global de 50 quedan inválidas · ' . implode( ',', $got['clears'] ) );
	check( mc_untouched( 60, 70, 80, 90 ), 'las demás páginas siguen válidas' );
	check( array() === $GLOBALS['mc']['clears'], 'ningún archivo se borra (se reescriben en el siguiente render; no se usa la acción del núcleo que los borra)' );
	check( 1 === $got['html'], 'al cerrar: la caché HTML de 50' );
	check( ! mc_related_valid( 50 ), 'al cerrar: la lista de componentes de 50 (Cache_Validity del núcleo) queda inválida' );
	check( array() === Css_Sync::state()['pending'] && 0 === Css_Sync::state()['depth'], 'nada pendiente y el ámbito cerrado' );
	do_action( 'shutdown' );
	check( 1 === mc_cleared( 50 )['html'], 'el cierre de la petición no repite lo ya refrescado' );

	// ---------------------------------------------------------------------------------------------------------------
	// 4. Página publicada: lo que el MCP guarda en su autoguardado no toca la publicada; la promoción de
	//    publish-document (meta escrita en la publicada, sin Document::save) sí.
	mc_boot();
	$live     = mc_post( 60, 'page', 0, 'publish' );
	$autosave = mc_post( 61, 'revision', 60, 'inherit' );
	do_action( 'wp_before_execute_ability', 'elementor/build-composition', array( 'post_id' => 60 ) );
	do_action( 'updated_post_meta', 4, 61, '_elementor_data', '[]' );
	do_action( 'elementor/document/after_save', new Mc_Document( $autosave, 60 ), array() );
	do_action( 'wp_after_execute_ability', 'elementor/build-composition', array( 'post_id' => 60 ), array() );
	check( mc_untouched( 60 ), 'el autoguardado de una publicada no invalida la publicada' );
	do_action( 'wp_before_execute_ability', 'elementor/publish-document', array( 'post_id' => 60 ) );
	do_action( 'updated_post_meta', 5, 60, '_elementor_page_settings', array() );
	check( array() === Css_Sync::state()['pending'], 'otra meta no cuenta' );
	do_action( 'updated_post_meta', 6, 60, '_elementor_data', '[]' );
	do_action( 'wp_after_execute_ability', 'elementor/publish-document', array( 'post_id' => 60 ), array( 'status' => 'publish' ) );
	$got = mc_cleared( 60 );
	check( array( 'local/60', 'global/60' ) === $got['clears'] && 1 === $got['html'], 'la promoción del autoguardado refresca la publicada (hojas y caché HTML)' );
	// Un borrador que publish-document publica sin cambios de contenido (p. ej. guardado antes en el editor).
	mc_reset();
	$draft = mc_post( 70 );
	do_action( 'wp_before_execute_ability', 'elementor/publish-document', array( 'post_id' => 70 ) );
	do_action( 'transition_post_status', 'publish', 'publish', $draft );
	check( array() === Css_Sync::state()['pending'], 'publish → publish no cuenta' );
	do_action( 'transition_post_status', 'publish', 'draft', mc_post( 71, 'revision', 70 ) );
	check( array() === Css_Sync::state()['pending'], 'una revisión que se publica no cuenta' );
	do_action( 'transition_post_status', 'publish', 'draft', $draft );
	do_action( 'wp_after_execute_ability', 'elementor/publish-document', array( 'post_id' => 70 ), array( 'status' => 'publish' ) );
	check( array( 'local/70', 'global/70' ) === mc_cleared( 70 )['clears'], 'un borrador que pasa a publish se refresca' );
	do_action( 'added_post_meta', 7, 72, '_elementor_data', '[]' );
	check( array() === Css_Sync::state()['pending'], 'fuera del ámbito, added_post_meta no cuenta' );

	// ---------------------------------------------------------------------------------------------------------------
	// 5. Herramientas anidadas, una que falla a medias (sin wp_after_execute_ability) y el proxy del editor.
	mc_boot();
	$a = mc_post( 80 );
	do_action( 'wp_before_execute_ability', 'elementor/manage-component', array() );
	do_action( 'wp_before_execute_ability', 'elementor/build-composition', array() );
	do_action( 'elementor/document/after_save', new Mc_Document( $a, 80 ), array() );
	do_action( 'wp_after_execute_ability', 'elementor/build-composition', array(), array() );
	check( mc_untouched( 80 ) && 1 === Css_Sync::state()['depth'], 'anidadas: no se refresca hasta cerrar la de fuera' );
	do_action( 'wp_after_execute_ability', 'elementor/manage-component', array(), array() );
	check( array( 'local/80', 'global/80' ) === mc_cleared( 80 )['clears'], 'anidadas: se refresca al cerrar la de fuera' );
	mc_reset();
	$b = mc_post( 81 );
	do_action( 'wp_before_execute_ability', 'elementor/manage-elements', array() );
	do_action( 'elementor/document/after_save', new Mc_Document( $b, 81 ), array() );
	check( mc_untouched( 81 ), 'una herramienta que falla después de guardar no dispara wp_after_execute_ability…' );
	do_action( 'shutdown' );
	check( array( 'local/81', 'global/81' ) === mc_cleared( 81 )['clears'], '…y lo guardado se refresca al terminar la petición' );
	mc_boot();
	$c = mc_post( 82 );
	$response = array( 'sin cambios' );
	check( $response === apply_filters( 'rest_request_before_callbacks', $response, null, new Mc_Request( '/elementor/v1/mcp-proxy' ) ), 'el filtro del proxy devuelve la respuesta intacta' );
	do_action( 'elementor/document/after_save', new Mc_Document( $c, 82 ), array() );
	check( mc_untouched( 82 ) && array( 82 ) === Css_Sync::state()['pending'], 'proxy: se anota durante la petición' );
	check( $response === apply_filters( 'rest_request_after_callbacks', $response, null, new Mc_Request( '/elementor/v1/mcp-proxy' ) ), 'el filtro posterior también la devuelve intacta' );
	check( array( 'local/82', 'global/82' ) === mc_cleared( 82 )['clears'], 'proxy: se refresca al terminar su ruta REST' );
	mc_reset();
	apply_filters( 'rest_request_before_callbacks', null, null, new Mc_Request( '/elementor/v1/mcp-proxy', 'GET' ) );
	apply_filters( 'rest_request_before_callbacks', null, null, new Mc_Request( '/wp/v2/pages' ) );
	check( 0 === Css_Sync::state()['depth'], 'el GET del proxy (recursos, sólo lectura) y otras rutas no abren el ámbito' );

	// ---------------------------------------------------------------------------------------------------------------
	// 6. Enlace firmado de create-preview-link (token real del núcleo). Revisión (ALTA 1): con la página publicada no se
	//    invalida ni se escribe nada suyo; sus hojas «frontend» se congelan (las que el núcleo regeneraría se marcan
	//    válidas sólo para esa petición) y vuelven a inválido al terminar; la caché HTML se apaga durante la petición.
	//    Falla con d85111be (se invalidaban sus hojas y se borraba su caché HTML antes y después).
	mc_boot();
	$v = new Cache_Validity();
	$secret = Preview_Token::secret();
	$posts  = static function () {
		mc_post( 90, 'page', 0, 'publish' );
		mc_post( 91, 'revision', 90, 'inherit' );
		mc_post( 92, 'revision', 99, 'inherit' );
		mc_post( 93, 'page' );
		mc_post( 94, 'revision', 93, 'inherit' );
	};
	$posts();
	// La publicada: escritorio válido con su archivo; móvil válido pero sin archivo; tableta inválida. Global: inválida.
	file_put_contents( $GLOBALS['mc_upload_dir'] . 'css/local-90-frontend-desktop.css', '.elementor .e-vivo{color:red}' );
	$v->invalidate( array( 'local', 90 ) );
	$v->invalidate( array( 'global', 90 ) );
	$v->validate( array( 'local', 90, 'frontend' ), 'ver-vivo' );
	$v->validate( array( 'local', 90, 'frontend', 'desktop' ), array( 'should_exist' => true ) );
	$v->validate( array( 'local', 90, 'frontend', 'mobile' ), array( 'should_exist' => true ) );
	$v->validate( array( 'component-styles-related-posts', '90' ), array( 7 ) );
	$_GET['elementor_preview_token'] = Preview_Token::encode( 90, 91, time() + 300, $secret );
	check( 'disable' !== get_option( 'elementor_element_cache_ttl', '' ), 'antes del enlace, la caché HTML del documento funciona' );
	do_action( 'parse_request', null );
	check( 'disable' === get_option( 'elementor_element_cache_ttl', '' ), 'durante el enlace firmado, la caché HTML del documento está apagada (no se lee ni se guarda)' );
	check( $v->is_valid( array( 'local', 90, 'frontend' ) ) && 'ver-vivo' === $v->get_meta( array( 'local', 90, 'frontend' ) ), 'la hoja local de la publicada sigue válida con su ver (no se regenera)' );
	check( $v->is_valid( array( 'local', 90, 'frontend', 'desktop' ) ) && array( 'should_exist' => true ) === $v->get_meta( array( 'local', 90, 'frontend', 'desktop' ) ), 'escritorio (con archivo): intacto, el núcleo sirve el archivo tal cual' );
	foreach ( array( 'mobile', 'tablet' ) as $bp ) {
		check( $v->is_valid( array( 'local', 90, 'frontend', $bp ) ) && array( 'should_exist' => false ) === $v->get_meta( array( 'local', 90, 'frontend', $bp ) ), "$bp (sin archivo): válida sin archivo durante la petición (el núcleo no escribe nada)" );
	}
	check( $v->is_valid( array( 'global', 90, 'frontend' ) ) && $v->is_valid( array( 'global', 90, 'frontend', 'desktop' ) ) && array( 'should_exist' => false ) === $v->get_meta( array( 'global', 90, 'frontend', 'desktop' ) ), 'la hoja global (inválida y sin archivo): congelada sin archivo' );
	check( ! mc_related_valid( 90 ), 'la lista de componentes se recalcula para la instantánea' );
	check( array() === $GLOBALS['mc']['deleted'] && 90 === mc_preview_state()['post'] && true === mc_preview_state()['live'], 'no se borra la caché HTML de la publicada' );
	do_action( 'shutdown' );
	check( 'disable' !== get_option( 'elementor_element_cache_ttl', '' ), 'al terminar, la caché HTML vuelve a funcionar' );
	check( $v->is_valid( array( 'local', 90, 'frontend' ) ) && $v->is_valid( array( 'local', 90, 'frontend', 'desktop' ) ) && 'ver-vivo' === $v->get_meta( array( 'local', 90, 'frontend' ) ), 'al terminar: escritorio sigue válido con el mismo ver (abrir el enlace no regenera la publicada)' );
	check( ! $v->is_valid( array( 'local', 90, 'frontend', 'mobile' ) ) && ! $v->is_valid( array( 'local', 90, 'frontend', 'tablet' ) ), 'al terminar: lo que se congeló vuelve a inválido' );
	check( ! $v->is_valid( array( 'global', 90, 'frontend' ) ), 'al terminar: el contexto global que estaba inválido vuelve a inválido' );
	check( '.elementor .e-vivo{color:red}' === file_get_contents( $GLOBALS['mc_upload_dir'] . 'css/local-90-frontend-desktop.css' ), 'el archivo de la publicada no cambia' );
	check( ! mc_related_valid( 90 ) && array() === $GLOBALS['mc']['deleted'] && array() === $GLOBALS['mc']['clears'], 'la lista de componentes se recalcula para la publicada; sin borrados' );
	// El mismo enlace otra vez: igual (sin regenerar nada de la publicada).
	do_action( 'parse_request', null );
	do_action( 'shutdown' );
	check( 'ver-vivo' === $v->get_meta( array( 'local', 90, 'frontend' ) ) && $v->is_valid( array( 'local', 90, 'frontend', 'desktop' ) ), 'abrirlo de nuevo tampoco cambia la hoja ni el ver de la publicada' );
	// Un borrador: sus hojas se invalidan antes y después (la instantánea se pinta con su CSS; nadie guarda su HTML).
	mc_reset();
	$posts();
	$_GET['elementor_preview_token'] = Preview_Token::encode( 93, 94, time() + 300, $secret );
	do_action( 'parse_request', null );
	check( array( 'local/93', 'global/93' ) === mc_cleared( 93 )['clears'] && 'disable' === get_option( 'elementor_element_cache_ttl', '' ) && false === mc_preview_state()['live'], 'borrador: sus hojas se invalidan antes de pintar y la caché HTML se apaga' );
	mc_seed( 93 );
	do_action( 'shutdown' );
	check( array( 'local/93', 'global/93' ) === mc_cleared( 93 )['clears'] && 0 === mc_cleared( 93 )['html'], 'borrador: y otra vez al terminar, sin tocar su caché HTML' );
	foreach ( array(
		'firma ajena'          => Preview_Token::encode( 90, 91, time() + 300, 'otro secreto' ),
		'vencido'              => Preview_Token::encode( 90, 91, time() - 1, $secret ),
		'revisión de otra'     => Preview_Token::encode( 90, 92, time() + 300, $secret ),
		'no es revisión'       => Preview_Token::encode( 90, 93, time() + 300, $secret ),
		'revisión inexistente' => Preview_Token::encode( 90, 999, time() + 300, $secret ),
		'basura'               => 'no.es-un-token',
		'vacío'                => '',
	) as $label => $token ) {
		mc_reset();
		$posts();
		$_GET['elementor_preview_token'] = $token;
		do_action( 'parse_request', null );
		$ttl = get_option( 'elementor_element_cache_ttl', '' );
		do_action( 'shutdown' );
		check( mc_untouched( 90 ) && 'disable' !== $ttl && 0 === mc_preview_state()['post'], "enlace $label: no se toca nada" );
	}
	mc_reset();
	$posts();
	$_GET['elementor_preview_token'] = array( Preview_Token::encode( 90, 91, time() + 300, $secret ) );
	do_action( 'parse_request', null );
	check( mc_untouched( 90 ) && 0 === mc_preview_state()['post'], 'un token que no es texto se ignora' );
	do_action( 'shutdown' );

	// ---------------------------------------------------------------------------------------------------------------
	// 7. Font_Stack: lista de familias.
	$cases = array(
		'"Geist Mono", ui-monospace, monospace'          => '"Geist Mono", ui-monospace, monospace',
		'"Geist Mono",ui-monospace,SFMono-Regular, monospace' => '"Geist Mono", ui-monospace, SFMono-Regular, monospace',
		'Geist Mono, ui-monospace'                       => '"Geist Mono", ui-monospace',
		"  'Open Sans' , sans-serif  "                   => "'Open Sans', sans-serif",
		'Inter, Helvetica Neue,  Arial ,sans-serif'      => 'Inter, "Helvetica Neue", Arial, sans-serif',
		'var(--titulos, Playfair Display), serif'        => 'var(--titulos, Playfair Display), serif',
		'Fira  Code, monospace,'                         => '"Fira Code", monospace',
		'Fuente "rara" x, serif'                         => '"Fuente \\"rara\\" x", serif',
	);
	foreach ( $cases as $in => $out ) {
		check( Font_Stack::normalize( $in ) === $out, "normalize($in) → $out · " . var_export( Font_Stack::normalize( $in ), true ) );
	}
	foreach ( array( 'Open Sans', '"Geist Mono"', 'sans-serif', 'var(--x)', '"A, B"', "'Foo, Bar'", '', '   ', ',', 'var(--x, a, b)' ) as $single ) {
		check( null === Font_Stack::normalize( $single ), "una sola familia ($single): la resuelve el núcleo" );
	}
	check( null === Font_Stack::normalize( null ) && null === Font_Stack::normalize( array( 'a, b' ) ) && null === Font_Stack::normalize( 12 ), 'lo que no es texto no se toca' );
	check( array( '"a\\", b"', 'c' ) === Font_Stack::split( '"a\\", b", c' ), 'una comilla escapada no cierra la familia' );
	check( array( '"sin cerrar, x' ) === Font_Stack::split( '"sin cerrar, x' ), 'una comilla sin cerrar se queda con el resto (una sola familia: núcleo)' );

	// 8. Con el transformador real del núcleo: la lista sale rota sin el módulo y bien con él; una sola familia, igual.
	$core  = new Core_Font_Family();
	$stack = '"Geist Mono", ui-monospace, monospace';
	check( '""Geist Mono", ui-monospace, monospace"' === $core->transform( $stack, $ctx ), 'núcleo 4.3.5: la lista sale con comillas anidadas (el navegador descarta la declaración) · ' . var_export( $core->transform( $stack, $ctx ), true ) );
	$registry = new Transformers_Registry();
	$registry->register( 'font-family', $core );
	mc_boot();
	do_action( 'elementor/atomic-widgets/styles/transformers/register', $registry );
	$ours = $registry->get( 'font-family' );
	check( $ours instanceof \Digitalisimo\Elements\Mcp\Font_Family_Transformer, 'el registro de estilos usa el transformador del módulo para font-family' );
	check( $stack === $ours->transform( $stack, $ctx ), 'con el módulo, la lista sale como CSS válido' );
	foreach ( array( 'Open Sans', '"Open Sans"', 'sans-serif', 'var(--e-gv-123)', '  Roboto  ', "'Lato'", '' ) as $single ) {
		check( $core->transform( $single, $ctx ) === $ours->transform( $single, $ctx ), "una sola familia ($single) sale igual que en el núcleo" );
	}
	check( null === $ours->transform( array( 'x' ), $ctx ) && null === $ours->transform( 5, $ctx ), 'un valor que no es texto sale null, como en el núcleo' );
	$empty = new Transformers_Registry();
	\Digitalisimo\Elements\Mcp\Font_Stack::register( $empty );
	check( 'Open Sans' === $empty->get( 'font-family' )->transform( 'Open Sans', $ctx ) && $stack === $empty->get( 'font-family' )->transform( $stack, $ctx ), 'sin transformador previo, una familia sale tal cual y la lista bien' );
	\Digitalisimo\Elements\Mcp\Font_Stack::register( null );
	\Digitalisimo\Elements\Mcp\Font_Stack::register( new stdClass() );
	check( true, 'un registro inválido se ignora sin errores' );

	// ---------------------------------------------------------------------------------------------------------------
	// 9. Revisión (MEDIA 3): el `execute_callback` de cada herramienta del constructor abre y cierra el ámbito en un
	//    `finally` (wp_register_ability_args): una herramienta que devuelve WP_Error (sin wp_after_execute_ability) o lanza
	//    una excepción no deja el ámbito abierto en un proceso largo (STDIO). Falla con 962e0136 (no se envolvía).
	mc_boot();
	$saved_doc = new Mc_Document( mc_post( 50 ), 50 );
	$fail_cb   = static function ( $input = null ) use ( $saved_doc ) {
		do_action( 'elementor/document/after_save', $saved_doc, array() );
		return (object) array( 'error' => 'elementor_forbidden' );
	};
	$args    = array( 'label' => 'x', 'execute_callback' => $fail_cb );
	$wrapped = apply_filters( 'wp_register_ability_args', $args, 'elementor/build-composition' );
	check( is_array( $wrapped ) && $wrapped['execute_callback'] !== $fail_cb && is_callable( $wrapped['execute_callback'] ), 'wp_register_ability_args envuelve el execute_callback de elementor/build-composition' );
	check( $args === apply_filters( 'wp_register_ability_args', $args, 'digitalisimo-woo/products' ), 'una herramienta que no es del constructor queda igual' );
	check( array( 'label' => 'x' ) === apply_filters( 'wp_register_ability_args', array( 'label' => 'x' ), 'elementor/x' ), 'sin execute_callback, igual' );
	// Como WP_Ability::execute(): wp_before_execute_ability, el callback (WP_Error) y sin wp_after_execute_ability.
	do_action( 'wp_before_execute_ability', 'elementor/build-composition', array() );
	$result = call_user_func( $wrapped['execute_callback'], array( 'post_id' => 50 ) );
	check( is_object( $result ) && 'elementor_forbidden' === $result->error, 'el resultado del callback (un error) sale intacto' );
	check( 0 === Css_Sync::state()['depth'] && array() === Css_Sync::state()['pending'], 'tras una herramienta que falla el ámbito queda cerrado y vacío · ' . wp_json_encode( Css_Sync::state() ) );
	check( array( 'local/50', 'global/50' ) === mc_cleared( 50 )['clears'], 'y lo que guardó antes de fallar ya se refrescó (sin esperar al cierre de la petición)' );
	// La escritura siguiente, en el mismo proceso, se refresca al terminar su herramienta.
	mc_reset();
	$ok_cb    = static function ( $input = null ) {
		do_action( 'elementor/document/after_save', new Mc_Document( mc_post( 60 ), 60 ), array() );
		return array( 'success' => true );
	};
	$wrapped2 = apply_filters( 'wp_register_ability_args', array( 'execute_callback' => $ok_cb ), 'elementor/build-composition' );
	do_action( 'wp_before_execute_ability', 'elementor/build-composition', array() );
	call_user_func( $wrapped2['execute_callback'], array( 'post_id' => 60 ) );
	do_action( 'wp_after_execute_ability', 'elementor/build-composition', array(), array() );
	check( array( 'local/60', 'global/60' ) === mc_cleared( 60 )['clears'] && 0 === Css_Sync::state()['depth'], 'la escritura siguiente del mismo proceso se refresca al terminar su herramienta' );
	// Una excepción también cierra el ámbito (y se propaga).
	mc_reset();
	$throw_cb = static function () {
		do_action( 'elementor/document/after_save', new Mc_Document( mc_post( 70 ), 70 ), array() );
		throw new RuntimeException( 'boom' );
	};
	$wrapped3 = apply_filters( 'wp_register_ability_args', array( 'execute_callback' => $throw_cb ), 'elementor/manage-elements' );
	$thrown   = false;
	try {
		call_user_func( $wrapped3['execute_callback'] );
	} catch ( RuntimeException $e ) {
		$thrown = 'boom' === $e->getMessage();
	}
	check( $thrown && 0 === Css_Sync::state()['depth'] && array( 'local/70', 'global/70' ) === mc_cleared( 70 )['clears'], 'una excepción se propaga y el ámbito queda cerrado y refrescado' );
	// Sin el filtro (Abilities API sin wp_register_ability_args), los ganchos antes/después siguen abriendo el ámbito.
	mc_reset();
	do_action( 'wp_before_execute_ability', 'elementor/create-page', array() );
	check( 1 === Css_Sync::state()['depth'], 'una herramienta no envuelta abre el ámbito con wp_before_execute_ability' );
	do_action( 'wp_after_execute_ability', 'elementor/create-page', array(), array() );
	check( 0 === Css_Sync::state()['depth'], 'y lo cierra con wp_after_execute_ability' );

	// ---------------------------------------------------------------------------------------------------------------
	// 10. Revisión (MEDIA 2): la promoción de publish-document sólo copia metas (sin save_post): una entrada publicada que
	//     cambió y no pasó por save_post en el ámbito recibe un wp_update_post() sin cambios, el guardado normal que
	//     escuchan las cachés de página. Falla con 962e0136 (no se avisaba).
	mc_boot();
	mc_post( 60, 'page', 0, 'publish' );
	do_action( 'wp_before_execute_ability', 'elementor/publish-document', array( 'post_id' => 60 ) );
	do_action( 'updated_post_meta', 6, 60, '_elementor_data', '[]' );
	do_action( 'wp_after_execute_ability', 'elementor/publish-document', array( 'post_id' => 60 ), array( 'status' => 'publish' ) );
	check( array( 60 ) === $GLOBALS['mc']['updated'], 'la promoción de una publicada llama a wp_update_post( [ ID => 60 ] ) una vez · ' . implode( ',', $GLOBALS['mc']['updated'] ) );
	check( array() === Css_Sync::state()['pending'] && 0 === Css_Sync::state()['depth'], 'lo que dispara ese guardado (save_post, transition_post_status, la meta) no se vuelve a anotar' );
	check( array( 'local/60', 'global/60' ) === mc_cleared( 60 )['clears'], 'y sus hojas quedan inválidas como antes' );
	// Un borrador que publish-document publica pasa por wp_update_post del núcleo (save_post): no se repite.
	mc_reset();
	$draft = mc_post( 70 );
	do_action( 'wp_before_execute_ability', 'elementor/publish-document', array( 'post_id' => 70 ) );
	$draft->post_status = 'publish';
	do_action( 'transition_post_status', 'publish', 'draft', $draft );
	do_action( 'save_post', 70, $draft, true );
	do_action( 'wp_after_execute_ability', 'elementor/publish-document', array( 'post_id' => 70 ), array() );
	check( array() === $GLOBALS['mc']['updated'] && array( 'local/70', 'global/70' ) === mc_cleared( 70 )['clears'], 'una entrada que ya pasó por save_post en el ámbito no recibe otro guardado' );
	// Un borrador que el MCP reescribe no avisa a nadie (las cachés de página no guardan borradores).
	mc_reset();
	$draft = mc_post( 50 );
	do_action( 'wp_before_execute_ability', 'elementor/build-composition', array( 'post_id' => 50 ) );
	do_action( 'elementor/document/after_save', new Mc_Document( $draft, 50 ), array() );
	do_action( 'wp_after_execute_ability', 'elementor/build-composition', array(), array() );
	check( array() === $GLOBALS['mc']['updated'], 'un borrador no recibe wp_update_post' );
	// Fuera del ámbito, save_post no se anota.
	mc_reset();
	do_action( 'save_post', 80, mc_post( 80, 'page', 0, 'publish' ), true );
	check( array() === Css_Sync::state()['pending'], 'save_post fuera del ámbito no cuenta' );

	// ---------------------------------------------------------------------------------------------------------------
	// 11. Revisión (BAJA 6): las claves del núcleo que usa el módulo salen de sus constantes (clases reales del núcleo) y
	//     el valor de respaldo coincide con ellas; la prioridad del enlace firmado va después de la del núcleo. Falla con
	//     el commit anterior (claves literales, sin referencia a la constante).
	require_once $root . '/elementor/modules/atomic-widgets/styles/atomic-widget-styles.php';
	// atomic-global-styles.php no se carga: con PHP 8.4+ emite una obsolescencia al compilarse (parámetro nulo implícito).
	$global_src   = (string) file_get_contents( $root . '/elementor/modules/global-classes/atomic-global-styles.php' );
	require_once $root . '/elementor/modules/components/styles/component-styles.php';
	Css_Sync::reset();
	$document_src = (string) file_get_contents( $root . '/elementor/core/base/document.php' );
	$core         = array(
		'local'   => \Elementor\Modules\AtomicWidgets\Styles\Atomic_Widget_Styles::STYLES_KEY,
		'global'  => preg_match( "/const STYLES_KEY = '([^']+)'/", $global_src, $m ) ? $m[1] : null,
		'related' => \Elementor\Modules\Components\Styles\Component_Styles::CACHE_ROOT_KEY,
		'html'    => preg_match( "/const CACHE_META_KEY = '([^']+)'/", $document_src, $m ) ? $m[1] : null,
		'data'    => preg_match( "/const ELEMENTOR_DATA_META_KEY = '([^']+)'/", $document_src, $m ) ? $m[1] : null,
		'token'   => Preview_Token::QUERY_ARG,
	);
	foreach ( $core as $name => $value ) {
		$entry = Css_Sync::KEYS[ $name ] ?? null;
		check( is_array( $entry ) && 3 === count( $entry ), "la clave $name del módulo nombra su clase y constante del núcleo" );
		check( is_array( $entry ) && $value === $entry[2], "el respaldo de $name coincide con la constante del núcleo ($value)" );
		check( $value === Css_Sync::key( $name ), "Css_Sync::key( '$name' ) devuelve la del núcleo ($value)" );
	}
	$handler_src = (string) file_get_contents( $root . '/elementor/modules/mcp/preview/public-preview-handler.php' );
	$core_prio   = preg_match( "/add_action\( 'parse_request', \[ \\\$this, 'maybe_activate' \], (\d+) \)/", $handler_src, $m ) ? (int) $m[1] : null;
	check( null !== $core_prio && defined( 'Digitalisimo\Elements\Mcp\Preview_Link::PRIORITY' ) && \Digitalisimo\Elements\Mcp\Preview_Link::PRIORITY > $core_prio, 'el enlace firmado se atiende después del manejador del núcleo (prioridad ' . var_export( $core_prio, true ) . ')' );

	$fails = count( $GLOBALS['mc_fail'] );
	if ( $fails ) {
		echo "CSS del MCP de Elements: $fails fallas de {$GLOBALS['mc_checks']} comprobaciones.\n";
		exit( 1 );
	}
	echo "CSS del MCP de Elements: {$GLOBALS['mc_checks']} comprobaciones en verde (ámbito de las herramientas, autoguardado y promoción, anidadas y proxy, enlaces firmados reales, listas de familias con el transformador real del núcleo).\n";
}
