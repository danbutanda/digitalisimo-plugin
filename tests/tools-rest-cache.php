<?php
/**
 * La API pública sin respuestas viejas en la caché de página (digitalisimo-tools/includes/class-rest-cache.php).
 *
 * Caso del 23-nov: tras activar WooCommerce, `/wp-json/` sin sesión seguía sin `wc` (y `/wp/v2/types` sin `product`)
 * porque LiteSpeed Cache guarda la API pública una semana y no la purga al activar un plugin. Comprueba que el índice,
 * los índices de namespace, los tipos/taxonomías/estados y el 404 `rest_no_route` salen sin caché sólo en la petición
 * principal GET/HEAD, que lo demás no cambia y que activar o desactivar un plugin purga la etiqueta REST de LiteSpeed.
 *
 * Uso: php tests/tools-rest-cache.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['rc'] = array( 'hooks' => array(), 'did' => array(), 'litespeed' => false, 'multi' => false );
$problems = array();
function rc_check( $ok, $message ) { if ( ! $ok ) $GLOBALS['problems'][] = $message; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['rc']['hooks'][ $hook ] = array( $cb, $prio ); }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { add_filter( $hook, $cb, $prio, $args ); }
function has_action( $hook ) { return 'litespeed_purge' === $hook ? $GLOBALS['rc']['litespeed'] : false; }
function do_action( $hook, ...$args ) { $GLOBALS['rc']['did'][] = array( $hook, $args ); }
function is_multisite() { return $GLOBALS['rc']['multi']; }
function get_option( $name, $default = false ) { return $default; }
function wp_get_nocache_headers() { return array( 'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private', 'Last-Modified' => false ); }

class RC_Response {
	public $headers = array(); public $status; public $data;
	function __construct( $status = 200, $data = array() ) { $this->status = $status; $this->data = $data; }
	function header( $k, $v ) { $this->headers[ $k ] = $v; }
	function get_status() { return $this->status; }
	function get_data() { return $this->data; }
}
class RC_Request {
	public $route; public $method;
	function __construct( $route, $method = 'GET' ) { $this->route = $route; $this->method = $method; }
	function get_route() { return $this->route; }
	function get_method() { return $this->method; }
}
class RC_Server { function get_namespaces() { return array( 'oembed/1.0', 'wp/v2', 'wc/v3', 'wc/store/v1' ); } }

$file = dirname( __DIR__ ) . '/digitalisimo-tools/includes/class-rest-cache.php';
if ( ! is_file( $file ) ) { echo "FALLA: no existe digitalisimo-tools/includes/class-rest-cache.php (la API pública se sirve vieja tras activar un plugin)\n"; exit( 1 ); }
require $file;
use Digitalisimo\Tools\Rest_Cache;

Rest_Cache::init();
rc_check( isset( $GLOBALS['rc']['hooks']['rest_post_dispatch'], $GLOBALS['rc']['hooks']['activated_plugin'], $GLOBALS['rc']['hooks']['deactivated_plugin'] ), 'init() engancha rest_post_dispatch, activated_plugin y deactivated_plugin' );
rc_check( 999 === ( $GLOBALS['rc']['hooks']['rest_post_dispatch'][1] ?? 0 ), 'rest_post_dispatch va tarde (999), después de los plugins que ponen sus cabeceras' );

/** Una petición «principal» a $main y la respuesta que sale para $route. */
function rc_run( $main, $route, $method = 'GET', $response = null ) {
	$GLOBALS['wp'] = (object) array( 'query_vars' => array( 'rest_route' => $main ) );
	$GLOBALS['rc']['did'] = array();
	$response = $response ?: new RC_Response();
	return Rest_Cache::dispatch( $response, new RC_Server(), new RC_Request( $route, $method ) );
}
function rc_nocache( $response ) { return false !== strpos( (string) ( $response->headers['Cache-Control'] ?? '' ), 'no-store' ); }
function rc_ls() { foreach ( $GLOBALS['rc']['did'] as $d ) if ( 'litespeed_control_set_nocache' === $d[0] ) return true; return false; }

// Sin REST_REQUEST (una precarga del editor, rest_do_request): nada.
$r = rc_run( '/', '/' );
rc_check( ! rc_nocache( $r ) && ! rc_ls() && ! defined( 'DONOTCACHEPAGE' ), 'fuera de una petición REST no cambia nada' );
define( 'REST_REQUEST', true );

// Lo que no depende de los plugins sigue igual.
$r = rc_run( '/wp/v2/posts', '/wp/v2/posts' );
rc_check( ! rc_nocache( $r ) && ! rc_ls() && ! defined( 'DONOTCACHEPAGE' ), '/wp/v2/posts se sigue cacheando como siempre' );
$r = rc_run( '/wc/v3/products/12', '/wc/v3/products/12' );
rc_check( ! rc_nocache( $r ) && ! defined( 'DONOTCACHEPAGE' ), 'una ruta de un plugin con 200 se sigue cacheando (se renueva con la purga)' );
$r = rc_run( '/', '/', 'POST' );
rc_check( ! rc_nocache( $r ) && ! defined( 'DONOTCACHEPAGE' ), 'un POST no se toca' );
$r = rc_run( '/wp/v2/posts', '/' );
rc_check( ! rc_nocache( $r ) && ! defined( 'DONOTCACHEPAGE' ), 'una petición interna a / dentro de otra (embed, batch) no marca la principal' );
$r = rc_run( '/wp/v2/posts/9', '/wp/v2/posts/9', 'GET', new RC_Response( 404, array( 'code' => 'rest_post_invalid_id' ) ) );
rc_check( ! rc_nocache( $r ) && ! defined( 'DONOTCACHEPAGE' ), 'un 404 que no es rest_no_route no se toca' );

// El índice de la API: sin caché, con DONOTCACHEPAGE y el aviso a LiteSpeed Cache.
$r = rc_run( '', '/' );
rc_check( rc_nocache( $r ) && rc_ls() && defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, '/wp-json/ (índice) sale sin caché: ' . json_encode( $r->headers ) );
rc_check( ! array_key_exists( 'Last-Modified', $r->headers ), 'las cabeceras falsas de wp_get_nocache_headers() no se envían' );
$cases = array(
	array( '/', '/', 'el índice por ?rest_route=/' ),
	array( '/wc/v3', '/wc/v3', 'el índice del namespace wc/v3' ),
	array( '/wc/v3/', '/wc/v3', 'el índice del namespace con barra final' ),
	array( '/wc/store/v1', '/wc/store/v1', 'un namespace con barra en el nombre' ),
	array( '/wp/v2/types', '/wp/v2/types', '/wp/v2/types' ),
	array( '/wp/v2/types/product', '/wp/v2/types/product', '/wp/v2/types/product' ),
	array( '/wp/v2/taxonomies', '/wp/v2/taxonomies', '/wp/v2/taxonomies' ),
	array( '/wp/v2/statuses', '/wp/v2/statuses', '/wp/v2/statuses' ),
);
foreach ( $cases as $c ) {
	$r = rc_run( $c[0], $c[1] );
	rc_check( rc_nocache( $r ) && rc_ls(), $c[2] . ' sale sin caché' );
	$r = rc_run( $c[0], $c[1], 'HEAD' );
	rc_check( rc_nocache( $r ), $c[2] . ' (HEAD) sale sin caché' );
}
$r = rc_run( '/wc/v3/products', '/wc/v3/products', 'GET', new RC_Response( 404, array( 'code' => 'rest_no_route', 'data' => array( 'status' => 404 ) ) ) );
rc_check( rc_nocache( $r ) && rc_ls(), 'el 404 rest_no_route (la ruta aún no existe) no se guarda' );
$r = rc_run( '/wp/v2/typesx', '/wp/v2/typesx' );
rc_check( ! rc_nocache( $r ), '/wp/v2/typesx no es descubrimiento' );

// Purga al activar o desactivar un plugin.
$GLOBALS['rc']['did'] = array();
Rest_Cache::changed( 'woocommerce/woocommerce.php', false );
rc_check( array() === $GLOBALS['rc']['did'], 'sin LiteSpeed Cache no se purga nada' );
$GLOBALS['rc']['litespeed'] = true;
Rest_Cache::changed( 'woocommerce/woocommerce.php', false );
rc_check( array( array( 'litespeed_purge', array( 'REST' ) ) ) === $GLOBALS['rc']['did'], 'con LiteSpeed Cache, activar un plugin purga la etiqueta REST: ' . json_encode( $GLOBALS['rc']['did'] ) );
$GLOBALS['rc']['did'] = array();
$GLOBALS['rc']['multi'] = true;
Rest_Cache::changed( 'woocommerce/woocommerce.php', true );
rc_check( array( array( 'litespeed_purge', array( '*' ) ) ) === $GLOBALS['rc']['did'], 'activado en toda la red, una purga «*» de la caché de página (nunca litespeed_purge_all, que vacía también CSS/JS, objetos, opcache y Cloudflare): ' . json_encode( $GLOBALS['rc']['did'] ) );
$GLOBALS['rc']['did'] = array();
Rest_Cache::changed( 'woocommerce/woocommerce.php', false );
rc_check( 'litespeed_purge' === ( $GLOBALS['rc']['did'][0][0] ?? '' ), 'en una red, activado en un sitio, purga sólo la API de ese sitio' );

$s = Rest_Cache::status();
rc_check( '' === $s['page_cache'] && false === $s['caches_rest_api'], 'status() sin LiteSpeed Cache: ' . json_encode( $s ) );

foreach ( $problems as $p ) echo "FALLA $p\n";
echo $problems ? '' : "OK tools-rest-cache: índice, namespaces, tipos y 404 de ruta sin caché; purga REST de LiteSpeed al activar/desactivar\n";
exit( $problems ? 1 : 0 );
