<?php
/**
 * Salud de plugins (digitalisimo-tools/includes/class-plugin-health.php): un plugin «activo» que no cargó.
 *
 * Caso del 23-nov (digitalisimo.lat): WooCommerce figuraba activo sin tipo `product` ni rutas `wc/v3`. Comprueba que
 * se compara la base de datos con lo que de verdad cargó y se dice por qué falta (filtro con su archivo y línea, caché
 * de objetos, archivo que no existe, archivo no incluido, marca de WooCommerce o Elementor), que un sitio sano no
 * escribe nada, que el cron y el MCP recuerdan el problema, el aviso del Escritorio con la X y la función del MCP.
 *
 * Uso: php tests/tools-plugin-health.php
 */
error_reporting( E_ALL );
$base = sys_get_temp_dir() . '/tph-' . getmypid();
define( 'ABSPATH', $base . '/' );
define( 'WP_PLUGIN_DIR', $base . '/wp-content/plugins' );
foreach ( array( 'ok/ok.php' => 'Bien', 'woocommerce/woocommerce.php' => 'WooCommerce', 'tarde/tarde.php' => 'Tarde', 'elementor/elementor.php' => 'Elementor' ) as $f => $n ) {
	@mkdir( dirname( WP_PLUGIN_DIR . '/' . $f ), 0777, true );
	file_put_contents( WP_PLUGIN_DIR . '/' . $f, "<?php\n/**\n * Plugin Name: $n\n */\n" );
}
$problems = array();
function ph_check( $ok, $message ) { if ( ! $ok ) $GLOBALS['problems'][] = $message; }
$GLOBALS['ph'] = array( 'did' => array( 'plugins_loaded' => 1, 'init' => 1 ), 'options' => array(), 'writes' => 0, 'cron' => false, 'types' => array( 'post', 'page' ), 'ns' => array( 'wp/v2' ), 'cap' => true, 'screen' => 'dashboard', 'hooks' => array(), 'ext' => false, 'db' => array(), 'cache' => array() );
function did_action( $h ) { return $GLOBALS['ph']['did'][ $h ] ?? 0; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['ph']['hooks'][ $h ] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { add_action( $h, $cb ); }
function apply_filters( $h, $v ) { return $v; }
function is_multisite() { return false; }
function is_network_admin() { return false; }
function validate_file( $f ) { return false !== strpos( $f, '..' ) ? 1 : 0; }
function get_option( $k, $d = false ) { return $GLOBALS['ph']['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['ph']['writes']++; $GLOBALS['ph']['options'][ $k ] = $v; return true; }
function maybe_unserialize( $v ) { return is_string( $v ) && false !== @unserialize( $v ) ? unserialize( $v ) : $v; }
function wp_using_ext_object_cache() { return $GLOBALS['ph']['ext']; }
function wp_load_alloptions() { return $GLOBALS['ph']['cache']; }
function wp_cache_get( $k, $g = '', $force = false ) { return 'alloptions' === $k ? $GLOBALS['ph']['cache'] : false; }
function wp_doing_cron() { return $GLOBALS['ph']['cron']; }
function post_type_exists( $t ) { return in_array( $t, $GLOBALS['ph']['types'], true ); }
function rest_get_server() { return new class { function get_namespaces() { return $GLOBALS['ph']['ns']; } }; }
function get_plugin_data( $path ) { preg_match( '/Plugin Name:\s*(.+)/', (string) file_get_contents( $path ), $m ); return array( 'Name' => trim( $m[1] ?? '' ) ); }
function current_user_can( $c ) { $GLOBALS['ph']['caps'][] = $c; return $GLOBALS['ph']['cap']; }
function is_user_logged_in() { return true; }
function get_current_screen() { return (object) array( 'id' => $GLOBALS['ph']['screen'] ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) ); }
function wp_unslash( $v ) { return $v; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function home_url( $p = '' ) { return 'https://sitio.test' . $p; }
function get_current_user_id() { return 1; }
function get_user_meta( $u, $k, $s = false ) { return ''; }
function wp_create_nonce( $a ) { return 'abc123'; }
function admin_url( $p = '', $s = '' ) { return '/wp-admin/' . $p; }
function wp_json_encode( $v ) { return json_encode( $v ); }
class PH_WPDB {
	public $options = 'wp_options'; public $sitemeta = 'wp_sitemeta';
	function prepare( $q, ...$a ) { return array( $q, $a ); }
	function get_var( $q ) { return serialize( $GLOBALS['ph']['db'] ); }
}
$GLOBALS['wpdb'] = new PH_WPDB();

$file = dirname( __DIR__ ) . '/digitalisimo-tools/includes/class-plugin-health.php';
if ( ! is_file( $file ) ) { echo "FALLA: no existe digitalisimo-tools/includes/class-plugin-health.php (un plugin activo que no carga pasa inadvertido)\n"; exit( 1 ); }
require dirname( __DIR__ ) . '/digitalisimo-tools/includes/class-digitalisimo-notices.php';
require $file;
use Digitalisimo\Tools\Plugin_Health as H;

H::init();
foreach ( array( 'admin_notices', 'network_admin_notices', 'site_status_tests', 'wp_update_plugins', 'wp_abilities_api_init', 'elementor/mcp/server/tools' ) as $h ) ph_check( isset( $GLOBALS['ph']['hooks'][ $h ] ), "init() engancha $h" );
ph_check( ! isset( $GLOBALS['ph']['hooks']['init'] ) && ! isset( $GLOBALS['ph']['hooks']['wp'] ) && ! isset( $GLOBALS['ph']['hooks']['plugins_loaded'] ), 'nada corre en cada petición de la portada' );

$p = function ( $f ) { return WP_PLUGIN_DIR . '/' . $f; };
$all = array( 'ok/ok.php', 'woocommerce/woocommerce.php' );
$inc = array_flip( array( $p( 'ok/ok.php' ), $p( 'woocommerce/woocommerce.php' ) ) );
$reasons = function ( $list ) { $o = array(); foreach ( $list as $x ) $o[ $x['plugin'] ] = $x['reason']; return $o; };

// Antes de plugins_loaded, nada que decir.
$GLOBALS['ph']['did'] = array();
ph_check( array() === H::check( array( 'stored' => $all, 'visible' => array(), 'cached' => array(), 'included' => array() ) ), 'antes de plugins_loaded no se compara' );
$GLOBALS['ph']['did'] = array( 'plugins_loaded' => 1, 'init' => 1 );

// Sano.
$GLOBALS['ph']['types'][] = 'product';
ph_check( array() === H::check( array( 'stored' => $all, 'visible' => $all, 'cached' => $all, 'included' => $inc ) ), 'un sitio sano no da problemas' );

// Un filtro lo quita: se dice quién (archivo y línea).
$GLOBALS['wp_filter']['option_active_plugins'] = (object) array( 'callbacks' => array( 10 => array( 'x' => array( 'function' => function ( $l ) { return $l; } ) ) ) );
$line = __LINE__ - 1;
$r = H::check( array( 'stored' => $all, 'visible' => array( 'ok/ok.php' ), 'cached' => $all, 'included' => $inc ) );
ph_check( array( 'woocommerce/woocommerce.php' => 'filtered' ) === $reasons( $r ), 'un filtro que lo quita: filtered ' . json_encode( $r ) );
ph_check( false !== strpos( $r[0]['detail'] ?? '', 'option_active_plugins: ' ) && false !== strpos( $r[0]['detail'] ?? '', basename( __FILE__ ) . ':' . $line ), 'el detalle nombra el filtro con su archivo y línea: ' . ( $r[0]['detail'] ?? '' ) );
ph_check( 'WooCommerce' === ( $r[0]['name'] ?? '' ), 'se nombra el plugin por su cabecera' );
unset( $GLOBALS['wp_filter'] );

// Caché de objetos vieja o de otro sitio (persistente): cache. Sin caché persistente: otra petición lo acaba de activar.
$r = H::check( array( 'stored' => $all, 'visible' => array( 'ok/ok.php' ), 'cached' => array( 'ok/ok.php' ), 'ext' => true, 'included' => $inc ) );
ph_check( array( 'woocommerce/woocommerce.php' => 'cache' ) === $reasons( $r ), 'la caché persistente da otra lista: cache ' . json_encode( $r ) );
$r = H::check( array( 'stored' => $all, 'visible' => array( 'ok/ok.php' ), 'cached' => array( 'ok/ok.php' ), 'ext' => false, 'included' => $inc ) );
ph_check( array() === $r, 'sin caché persistente, una activación en otra petición no es un problema' );

// Archivo que no existe o ruta no válida.
$r = H::check( array( 'stored' => array( 'ok/ok.php', 'borrado/borrado.php', '../fuera.php' ), 'visible' => array( 'ok/ok.php', 'borrado/borrado.php', '../fuera.php' ), 'cached' => array(), 'included' => $inc ) );
ph_check( array( 'borrado/borrado.php' => 'missing', '../fuera.php' => 'missing' ) === $reasons( $r ), 'archivo que no existe o no válido: missing ' . json_encode( $r ) );

// En la lista y con archivo, pero no incluido en esta petición.
$r = H::check( array( 'stored' => array( 'ok/ok.php', 'tarde/tarde.php' ), 'visible' => array( 'ok/ok.php', 'tarde/tarde.php' ), 'cached' => array(), 'included' => $inc ) );
ph_check( array( 'tarde/tarde.php' => 'not_loaded' ) === $reasons( $r ), 'archivo no incluido: not_loaded ' . json_encode( $r ) );
// La ruta real cuenta (enlace simbólico o ruta resuelta en get_included_files()).
@symlink( WP_PLUGIN_DIR . '/tarde', WP_PLUGIN_DIR . '/enlace' );
if ( is_link( WP_PLUGIN_DIR . '/enlace' ) ) {
	$r = H::check( array( 'stored' => array( 'enlace/tarde.php' ), 'visible' => array( 'enlace/tarde.php' ), 'cached' => array(), 'included' => array_flip( array( realpath( WP_PLUGIN_DIR . '/tarde/tarde.php' ) ) ) ) );
	ph_check( array() === $r, 'un plugin en una carpeta enlazada cuenta por su ruta real' );
}

// Marcas: WooCommerce sin product, sin wc/v3 (sólo con la API armada); Elementor sin elementor/loaded.
$GLOBALS['ph']['types'] = array( 'post' );
$r = H::check( array( 'stored' => $all, 'visible' => $all, 'cached' => $all, 'included' => $inc ) );
ph_check( array( 'woocommerce/woocommerce.php' => 'marker' ) === $reasons( $r ) && false !== strpos( $r[0]['detail'], '«product»' ), 'WooCommerce sin el tipo product: marker ' . json_encode( $r ) );
$GLOBALS['ph']['did'] = array( 'plugins_loaded' => 1 );
ph_check( array() === H::check( array( 'stored' => $all, 'visible' => $all, 'cached' => $all, 'included' => $inc ) ), 'antes de init no se mira el tipo de contenido' );
$GLOBALS['ph']['did'] = array( 'plugins_loaded' => 1, 'init' => 1, 'rest_api_init' => 1 );
$GLOBALS['ph']['types'][] = 'product';
$r = H::check( array( 'stored' => $all, 'visible' => $all, 'cached' => $all, 'included' => $inc ) );
ph_check( array( 'woocommerce/woocommerce.php' => 'marker' ) === $reasons( $r ) && false !== strpos( $r[0]['detail'], 'wc/v3' ), 'WooCommerce sin wc/v3 con la API armada: marker ' . json_encode( $r ) );
$GLOBALS['ph']['ns'][] = 'wc/v3';
ph_check( array() === H::check( array( 'stored' => $all, 'visible' => $all, 'cached' => $all, 'included' => $inc ) ), 'WooCommerce con product y wc/v3 está bien' );
$el = array( 'elementor/elementor.php' );
$r = H::check( array( 'stored' => $el, 'visible' => $el, 'cached' => $el, 'included' => array_flip( array( $p( 'elementor/elementor.php' ) ) ) ) );
ph_check( array( 'elementor/elementor.php' => 'marker' ) === $reasons( $r ), 'Elementor sin elementor/loaded: marker' );
$GLOBALS['ph']['did']['elementor/loaded'] = 1;
ph_check( array() === H::check( array( 'stored' => $el, 'visible' => $el, 'cached' => $el, 'included' => array_flip( array( $p( 'elementor/elementor.php' ) ) ) ) ), 'Elementor arrancado está bien' );

// Fuentes reales: la base (consulta directa), get_option, alloptions y get_included_files().
require $p( 'ok/ok.php' );
$GLOBALS['ph']['db'] = array( 'ok/ok.php', 'tarde/tarde.php' );
$GLOBALS['ph']['options']['active_plugins'] = array( 'ok/ok.php', 'tarde/tarde.php' );
$GLOBALS['ph']['cache'] = array( 'active_plugins' => serialize( array( 'ok/ok.php', 'tarde/tarde.php' ) ) );
ph_check( array( 'tarde/tarde.php' => 'not_loaded' ) === $reasons( H::check() ), 'con las fuentes reales, sólo falta el archivo no incluido: ' . json_encode( H::check() ) );

// Cron: sólo escribe si hay problema; fuera del cron, nada.
$GLOBALS['ph']['writes'] = 0;
H::background();
ph_check( 0 === $GLOBALS['ph']['writes'], 'fuera del cron, wp_update_plugins no comprueba' );
$GLOBALS['ph']['cron'] = true;
$GLOBALS['ph']['db'] = $GLOBALS['ph']['options']['active_plugins'] = array( 'ok/ok.php' );
H::background();
ph_check( 0 === $GLOBALS['ph']['writes'], 'un sitio sano no escribe nada en el cron' );
$GLOBALS['ph']['db'] = $GLOBALS['ph']['options']['active_plugins'] = array( 'ok/ok.php', 'tarde/tarde.php' );
H::background();
$seen = $GLOBALS['ph']['options'][ H::OPTION ] ?? array();
ph_check( 1 === $GLOBALS['ph']['writes'] && 'cron' === ( $seen['context'] ?? '' ) && 'tarde/tarde.php' === ( $seen['problems'][0]['plugin'] ?? '' ), 'el cron recuerda el problema: ' . json_encode( $seen ) );

// Aviso: ahora (error) y lo que vio el cron (advertencia, tres días).
$s = H::status();
ph_check( 'now' === $s['state'], 'status() ahora: ' . json_encode( $s ) );
ob_start(); H::notice(); $html = ob_get_clean();
ph_check( false !== strpos( $html, 'notice-error' ) && false !== strpos( $html, 'is-dismissible' ) && false !== strpos( $html, 'data-digitalisimo-notice="tools-plugin-health"' ) && false !== strpos( $html, 'Tarde (tarde/tarde.php)' ), 'aviso de error con la X en el Escritorio: ' . $html );
ph_check( in_array( 'activate_plugins', $GLOBALS['ph']['caps'], true ), 'el aviso es para quien gestiona plugins' );
$GLOBALS['ph']['screen'] = 'edit-post';
ob_start(); H::notice(); ph_check( '' === ob_get_clean(), 'fuera del Escritorio, Plugins y las pantallas Digitalisimo, no hay aviso' );
$GLOBALS['ph']['screen'] = 'plugins';
$GLOBALS['ph']['db'] = $GLOBALS['ph']['options']['active_plugins'] = array( 'ok/ok.php' );
$s = H::status();
ph_check( 'recent' === $s['state'] && false !== strpos( $s['message'], 'cron' ), 'ya arreglado: dice lo que vio el cron: ' . json_encode( $s ) );
ob_start(); H::notice(); $html = ob_get_clean();
ph_check( false !== strpos( $html, 'notice-warning' ), 'lo que vio el cron sale como advertencia' );
$GLOBALS['ph']['options'][ H::OPTION ]['at'] = time() - H::RECENT - 10;
ph_check( '' === H::status()['state'], 'pasados tres días, ya no se dice' );
$GLOBALS['ph']['cap'] = false;
ob_start(); H::notice(); ph_check( '' === ob_get_clean(), 'sin activate_plugins no hay aviso' );
$GLOBALS['ph']['cap'] = true;

// «Salud del sitio».
$t = H::tests( array() );
ph_check( isset( $t['direct']['digitalisimo_plugin_health'] ), 'prueba directa en «Salud del sitio»' );
ph_check( 'good' === H::health()['status'], 'sano: good' );
$GLOBALS['ph']['db'] = $GLOBALS['ph']['options']['active_plugins'] = array( 'ok/ok.php', 'tarde/tarde.php' );
ph_check( 'critical' === H::health()['status'], 'con un plugin sin cargar: critical' );

// MCP.
$out = H::execute( array() );
ph_check( false === $out['ok'] && 'tarde/tarde.php' === ( $out['problems'][0]['plugin'] ?? '' ) && isset( $out['rest_cache'] ), 'la función del MCP dice qué no cargó: ' . json_encode( $out ) );
ph_check( 'mcp' === ( $GLOBALS['ph']['options'][ H::OPTION ]['context'] ?? '' ), 'lo visto por el MCP queda para el Escritorio' );
ph_check( H::permission() && in_array( 'activate_plugins', array_slice( $GLOBALS['ph']['caps'], -1 ), true ), 'la función del MCP pide activate_plugins' );
$d = H::definition();
ph_check( ! empty( $d['meta']['annotations']['readonly'] ) && ! empty( $d['meta']['mcp']['public'] ) && 'digitalisimo-tools' === $d['category'], 'función de sólo lectura, pública para MCP' );
function wp_register_ability( $n, $a ) { $GLOBALS['ph']['ability'][ $n ] = $a; }
ph_check( in_array( H::ABILITY, H::server_tools( array( 'x' ) ), true ), 'el MCP de Elements la ofrece' );
H::register_ability();
ph_check( isset( $GLOBALS['ph']['ability'][ H::ABILITY ] ), 'se registra la Ability' );

@unlink( WP_PLUGIN_DIR . '/enlace' );
array_map( 'unlink', array_filter( glob( WP_PLUGIN_DIR . '/*/*.php' ), 'is_file' ) );
foreach ( $problems as $x ) echo "FALLA $x\n";
echo $problems ? '' : "OK tools-plugin-health: filtro, caché, archivo, no incluido y marcas; cron, aviso, «Salud del sitio» y MCP\n";
exit( $problems ? 1 : 0 );
