<?php
/**
 * Sal propia de la caché de objetos (digitalisimo-tools/includes/class-cache-salt.php).
 *
 * Regla del propietario del 2026-11-21: «no debemos modificar nada nosotros». Con una caché persistente sin
 * WP_CACHE_KEY_SALT ni WP_REDIS_PREFIX (incidente de plomerito), Tools agrega solo la sal a wp-config.php: con copia
 * previa, escritura atómica con los mismos permisos, validación con token_get_all y vuelta al original si algo falla; sin
 * duplicar el bloque; una sola sal en una red; y Migrar la pide antes del respaldo. Si no puede escribir, el aviso da la
 * línea exacta (único caso a mano).
 */
error_reporting( E_ALL );
$root     = dirname( __DIR__ );
$problems = array();
function cs_check( $ok, $message ) {
	if ( ! $ok ) $GLOBALS['problems'][] = $message;
}

$base = sys_get_temp_dir() . '/cs-' . getmypid();
@mkdir( $base . '/wp/', 0777, true );
@mkdir( $base . '/privado/', 0700, true );
define( 'ABSPATH', $base . '/wp/' );
define( 'DB_NAME', 'plomerito_db' );

$GLOBALS['cs'] = array( 'ext' => true, 'multi' => false, 'cap' => true, 'filters' => array(), 'log' => array(), 'db' => array( 'home' => 'https://www.plomerito.com', 'siteurl' => 'https://plomerito.com' ), 'meta' => array(), 'valid' => null );
function wp_using_ext_object_cache() { return $GLOBALS['cs']['ext']; }
function is_multisite() { return $GLOBALS['cs']['multi']; }
function get_main_site_id() { return 1; }
function get_current_network_id() { return 1; }
function current_user_can( $cap ) { $GLOBALS['cs']['caps'][] = $cap; return $GLOBALS['cs']['cap']; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['cs']['filters'][ $hook ] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['cs']['filters'][ $hook ] = $cb; }
function has_filter( $hook ) { return isset( $GLOBALS['cs']['filters'][ $hook ] ); }
function apply_filters( $hook, $value, ...$args ) {
	if ( 'digitalisimo_tools_cache_salt_valid' === $hook && null !== $GLOBALS['cs']['valid'] ) return $GLOBALS['cs']['valid'];
	if ( 'digitalisimo_tools_cache_salt_backup_dir' === $hook ) return $GLOBALS['base'] . '/privado';
	if ( isset( $GLOBALS['cs']['filters'][ $hook ] ) ) return call_user_func_array( $GLOBALS['cs']['filters'][ $hook ], array_merge( array( $value ), $args ) );
	return $value;
}
function wp_cache_get( $key, $group = '', $force = false ) { return false; }
function wp_cache_delete( $key, $group = '' ) { $GLOBALS['cs']['log'][] = "delete:{$group}:{$key}"; return true; }
function wp_cache_flush() { $GLOBALS['cs']['log'][] = 'GLOBAL_FLUSH'; return true; }
function get_option( $name, $default = false ) { return $GLOBALS['cs']['db'][ $name ] ?? $default; }
function get_site_option( $name, $default = false ) { return $GLOBALS['cs']['meta'][ $name ] ?? $default; }
function maybe_unserialize( $raw ) { $v = @unserialize( (string) $raw ); return false === $v && 'b:0;' !== $raw ? $raw : $v; }
final class CS_Wpdb {
	public $options = 'wp_options';
	public $sitemeta = 'wp_sitemeta';
	public $base_prefix = 'wp_';
	public $queries = array();
	public function prepare( $sql, ...$args ) {
		foreach ( $args as $arg ) $sql = preg_replace_callback( '/%[sd]/', function () use ( $arg ) { return is_int( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'"; }, $sql, 1 );
		return $sql;
	}
	public function get_blog_prefix( $id ) { return 1 === (int) $id ? 'wp_' : 'wp_' . (int) $id . '_'; }
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( preg_match( "/FROM wp_options WHERE option_name = '([^']*)'/", $sql, $m ) ) return isset( $GLOBALS['cs']['db'][ $m[1] ] ) ? (string) $GLOBALS['cs']['db'][ $m[1] ] : null;
		if ( preg_match( "/FROM wp_3_options WHERE option_name = '([^']*)'/", $sql, $m ) ) return 'home' === $m[1] ? 'https://sub.red.test' : null;
		if ( preg_match( "/SELECT meta_id FROM wp_sitemeta WHERE meta_key = '([^']*)'/", $sql, $m ) ) return isset( $GLOBALS['cs']['meta'][ $m[1] ] ) ? '7' : null;
		if ( preg_match( "/SELECT meta_value FROM wp_sitemeta WHERE meta_key = '([^']*)'/", $sql, $m ) ) return isset( $GLOBALS['cs']['meta'][ $m[1] ] ) ? serialize( $GLOBALS['cs']['meta'][ $m[1] ] ) : null;
		return null;
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		if ( preg_match( "/INSERT INTO wp_options .* VALUES \('([^']*)', '(.*)', 'no'\) ON DUPLICATE/s", $sql, $m ) ) $GLOBALS['cs']['db'][ $m[1] ] = stripslashes( $m[2] );
		if ( preg_match( "/INTO wp_sitemeta \(site_id, meta_key, meta_value\) VALUES \(1, '([^']*)', '(.*)'\)/s", $sql, $m ) ) $GLOBALS['cs']['meta'][ $m[1] ] = unserialize( stripslashes( $m[2] ) );
		return 1;
	}
}
$GLOBALS['wpdb'] = new CS_Wpdb();
require $root . '/digitalisimo-tools/includes/class-digitalisimo-same-site.php';
require $root . '/digitalisimo-tools/includes/class-cache-salt.php';
require $root . '/digitalisimo-tools/includes/class-cache-notice.php';
use Digitalisimo\Tools\Cache_Salt;
use Digitalisimo\Tools\Cache_Notice;

/** Nueva petición: olvida el resultado guardado en memoria. */
function cs_request() {
	$r = new ReflectionProperty( Cache_Salt::class, 'done' );
	$r->setAccessible( true );
	$r->setValue( null, null );
	$GLOBALS['cs']['log'] = array();
}
$config   = ABSPATH . 'wp-config.php';
$original = "<?php\n/** Ajustes de la base. */\ndefine( 'DB_NAME', 'plomerito_db' );\n// define( 'WP_CACHE_KEY_SALT', 'comentada' );\n\$table_prefix = 'wp_';\n\n/* That's all, stop editing! Happy publishing. */\n\nif ( ! defined( 'ABSPATH' ) ) {\n\tdefine( 'ABSPATH', __DIR__ . '/' );\n}\nrequire_once ABSPATH . 'wp-settings.php';\n";
function cs_reset_config( $contents, $perms = 0640 ) {
	global $config;
	if ( is_link( $config ) ) unlink( $config );
	file_put_contents( $config, $contents );
	chmod( $config, $perms );
	clearstatcache();
	foreach ( glob( $GLOBALS['base'] . '/privado/*' ) as $f ) unlink( $f );
	$GLOBALS['cs']['db'] = array( 'home' => 'https://www.plomerito.com', 'siteurl' => 'https://plomerito.com' );
	$GLOBALS['cs']['meta'] = array();
	cs_request();
}
$id8  = substr( hash( 'sha256', realpath( ABSPATH ) . "\nplomerito_db\nwp_" ), 0, 8 );
$line = "define( 'WP_CACHE_KEY_SALT', 'plomerito.com:{$id8}:' );";

// 1 · Caché externa sin sal: agrega la línea, antes de «That's all», con copia previa y registro.
cs_reset_config( $original );
Cache_Salt::init();
$r   = Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $config );
cs_check( 'written' === $r['state'], '1: con caché externa sin sal debe escribir: ' . json_encode( $r ) );
cs_check( 1 === substr_count( $now, $line ) && false !== strpos( $now, Cache_Salt::MARK ), '1: wp-config.php debe llevar el bloque marcado con la línea exacta: ' . $now );
cs_check( strpos( $now, Cache_Salt::MARK ) < strpos( $now, "/* That's all, stop editing!" ) && strpos( $now, $line ) < strpos( $now, 'wp-settings.php' ), '1: el bloque va antes de «That\'s all, stop editing».' );
cs_check( str_replace( Cache_Salt::MARK . "\ndefined( 'WP_CACHE_KEY_SALT' ) || {$line}\n" . Cache_Salt::END . "\n\n", '', $now ) === $original, '1: fuera del bloque, wp-config.php queda igual.' );
$copies = glob( $base . '/privado/wp-config-antes-de-la-sal-*.php' );
cs_check( 1 === count( $copies ) && $original === file_get_contents( $copies[0] ) && 0600 === ( fileperms( $copies[0] ) & 0777 ), '1: copia previa con fecha en el almacén privado, idéntica y 0600: ' . json_encode( $copies ) );
cs_check( 0640 === ( fileperms( $config ) & 0777 ), '1: conserva los permisos de wp-config.php (0640): ' . decoct( fileperms( $config ) & 0777 ) );
cs_check( ! glob( ABSPATH . '.wp-config.digitalisimo-*' ), '1: no deja el temporal.' );
cs_check( in_array( 'delete:options:home', $GLOBALS['cs']['log'], true ) && in_array( 'delete:options:alloptions', $GLOBALS['cs']['log'], true ) && ! in_array( 'GLOBAL_FLUSH', $GLOBALS['cs']['log'], true ), '1: borra sólo las claves de este sitio, sin vaciar la caché global: ' . implode( ',', $GLOBALS['cs']['log'] ) );
$logged = Cache_Salt::last();
cs_check( 'written' === ( $logged['state'] ?? '' ) && 'admin' === ( $logged['via'] ?? '' ) && $copies && $copies[0] === ( $logged['backup'] ?? '' ), '1: queda el registro con la copia: ' . json_encode( $logged ) );
// El aviso, en la misma petición: dice que ya se hizo, sin pedir nada.
$status = Cache_Notice::status();
cs_check( 'salted' === $status['state'] && false !== strpos( $status['message'], 'no hace falta hacer nada' ), '1: el aviso dice que ya se agregó: ' . json_encode( $status ) );

// 2 · No la duplica: otra petición (la constante aún no está en este proceso) encuentra el bloque y no escribe.
cs_request();
$before = (string) file_get_contents( $config );
$r      = Cache_Salt::ensure( 'admin' );
cs_check( 'pending' === $r['state'] && $before === file_get_contents( $config ) && 1 === substr_count( (string) file_get_contents( $config ), Cache_Salt::MARK ), '2: el bloque nunca se duplica: ' . json_encode( $r ) );
// Y el escritorio no reescribe el registro en cada carga mientras wp-config.php siga igual.
cs_request();
$GLOBALS['wpdb']->queries = array();
Cache_Salt::admin();
cs_check( ! preg_grep( '/^INSERT INTO wp_options/', $GLOBALS['wpdb']->queries ), '2: el escritorio no repite el intento ni el registro.' );

// 3 · Sin caché externa: no actúa.
cs_reset_config( $original );
$GLOBALS['cs']['ext'] = false;
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'not_needed' === $r['state'] && $original === file_get_contents( $config ) && ! glob( $base . '/privado/*' ), '3: sin caché externa no toca nada: ' . json_encode( $r ) );
$GLOBALS['cs']['ext'] = true;

// 4 · La validación falla: se repone el original.
cs_reset_config( $original );
$GLOBALS['cs']['valid'] = false;
$r = Cache_Salt::ensure( 'admin' );
$GLOBALS['cs']['valid'] = null;
cs_check( 'failed' === $r['state'] && $original === file_get_contents( $config ) && 0640 === ( fileperms( $config ) & 0777 ), '4: si la comprobación falla, vuelve el original: ' . json_encode( $r ) );
$status = Cache_Notice::status();
cs_check( 'unsalted' === $status['state'] && false !== strpos( $status['message'], $line ), '4: el aviso da la línea exacta para copiar: ' . json_encode( $status ) );

// 5 · Permisos: sin escritura (o un enlace) no se toca y se da la línea.
cs_reset_config( $original, 0440 );
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && $line === $r['line'] && $original === file_get_contents( $config ) && ! glob( $base . '/privado/*' ), '5: sin permiso de escritura no se toca: ' . json_encode( $r ) );
$status = Cache_Notice::status();
cs_check( 'unsalted' === $status['state'] && false !== strpos( $status['message'], $line ) && false !== strpos( $status['message'], 'no pudo agregarla solo' ), '5: el aviso pide copiar la línea: ' . json_encode( $status ) );
// Mientras wp-config.php siga igual, el escritorio no lo reintenta.
cs_request();
$GLOBALS['wpdb']->queries = array();
Cache_Salt::admin();
cs_check( ! preg_grep( '/^INSERT INTO wp_options/', $GLOBALS['wpdb']->queries ), '5: un intento sin permiso no se repite con el mismo archivo.' );
chmod( $config, 0640 );
file_put_contents( $base . '/real-config.php', $original );
unlink( $config );
symlink( $base . '/real-config.php', $config );
cs_request();
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && $original === file_get_contents( $base . '/real-config.php' ) && is_link( $config ), '5: un wp-config.php enlazado no se reemplaza: ' . json_encode( $r ) );
unlink( $config );
// wp-config.php en la carpeta padre (instalación «subida»): también vale.
cs_request();
file_put_contents( $base . '/wp-config.php', $original );
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'written' === $r['state'] && $base . '/wp-config.php' === $r['file'] && 1 === substr_count( (string) file_get_contents( $base . '/wp-config.php' ), $line ), '5: wp-config.php en la carpeta padre: ' . json_encode( $r ) );
unlink( $base . '/wp-config.php' );

// Sin «That's all» y con un comentario engañoso: va antes del require de wp-settings.php, como código.
cs_reset_config( "<?php\n/**\n * That's all, stop editing (dentro de un comentario)\n */\ndefine( 'DB_NAME', 'x' );\nrequire_once( ABSPATH . 'wp-settings.php' );\n" );
$r   = Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $config );
cs_check( 'written' === $r['state'] && strpos( $now, $line ) > strpos( $now, ' */' ) && strpos( $now, $line ) < strpos( $now, 'require_once(' ), '6: nunca dentro de un comentario; si no, antes del require: ' . $now );
// Una sal que ya nombra el código (aunque no esté en vigor): no se toca.
cs_reset_config( "<?php\nif ( getenv( 'X' ) ) define( 'WP_CACHE_KEY_SALT', 'otra:' );\nrequire_once ABSPATH . 'wp-settings.php';\n" );
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && false === strpos( (string) file_get_contents( $config ), Cache_Salt::MARK ), '6: con una sal ya escrita en wp-config.php no se agrega otra: ' . json_encode( $r ) );

// 7 · Red: una sola sal con el host del sitio principal, y el registro en la red.
cs_reset_config( $original );
$GLOBALS['cs']['multi'] = true;
$GLOBALS['cs']['db']['home'] = 'https://red.test';
$GLOBALS['wpdb']->options = 'wp_3_options'; // Estamos en el sitio 3 (sub.red.test).
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'written' === $r['state'] && "red.test:{$id8}:" === $r['salt'] && 1 === substr_count( (string) file_get_contents( $config ), "'red.test:{$id8}:'" ), '7: en una red, la sal del sitio principal: ' . json_encode( $r ) );
cs_check( 'written' === ( $GLOBALS['cs']['meta'][ Cache_Salt::LOG ]['state'] ?? '' ), '7: el registro de una red va en la red: ' . json_encode( $GLOBALS['cs']['meta'] ) );
cs_request();
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'pending' === $r['state'] && 1 === substr_count( (string) file_get_contents( $config ), Cache_Salt::MARK ), '7: desde otro sitio de la red no se agrega otra sal.' );
$GLOBALS['cs']['caps'] = array();
cs_request();
Cache_Salt::admin();
cs_check( array( 'manage_network' ) === $GLOBALS['cs']['caps'], '7: en una red, el escritorio exige manage_network: ' . json_encode( $GLOBALS['cs']['caps'] ) );
$GLOBALS['cs']['multi'] = false;
$GLOBALS['wpdb']->options = 'wp_options';

// Sin capacidad, el escritorio no escribe.
cs_reset_config( $original );
$GLOBALS['cs']['cap'] = false;
Cache_Salt::admin();
cs_check( $original === file_get_contents( $config ), '7: sin manage_options el escritorio no escribe.' );
$GLOBALS['cs']['cap'] = true;
Cache_Salt::admin();
cs_check( 1 === substr_count( (string) file_get_contents( $config ), $line ), '7: con manage_options, la siguiente carga del escritorio la escribe.' );

// 8 · Migrar la pide antes del respaldo (por el filtro de Tools) y sigue en la petición siguiente.
$full   = (string) file_get_contents( $root . '/digitalisimo-migration/includes/class-full.php' );
$backup = substr( $full, strpos( $full, 'private static function stage_backup(' ) );
$backup = substr( $backup, 0, strpos( $backup, "\n\t}\n" ) );
cs_check( false !== strpos( $backup, 'self::salted( $state, self::cache_salt() )' ) && strpos( $backup, 'self::cache_salt()' ) < strpos( $backup, '$jobs::enqueue(' ), '8: stage_backup() debe pedir la sal antes de encolar el respaldo.' );
cs_check( 1 === preg_match( "/'written' === \\\$state\['salt'\][^}]*return self::changed\( \\\$state \);/s", $full ), '8: si la sal se acaba de escribir, Migrar sigue en una petición nueva (changed()).' );
$refused = substr( $full, strpos( $full, 'private static function refused(' ) );
$refused = substr( $refused, 0, strpos( $refused, "\n\t}\n" ) );
cs_check( strpos( $refused, 'self::cache_salt()' ) < strpos( $refused, '$alien = self::alien();' ) && strpos( $refused, "empty( \$caps['activate'] )" ) < strpos( $refused, 'self::cache_salt()' ), '8: al pulsar Migrar, la sal va después de comprobar permisos y antes del candado (que la sal cura).' );
cs_check( false !== strpos( $full, "const CACHE_SALT = 'digitalisimo_tools_cache_salt';" ) && false !== strpos( $full, 'apply_filters( self::CACHE_SALT, null, \'migration\' )' ), '8: Migrar usa el filtro de Tools.' );
// El filtro de Tools responde lo mismo que ensure().
cs_reset_config( $original );
$r = apply_filters( 'digitalisimo_tools_cache_salt', null, 'migration' );
cs_check( 'written' === ( $r['state'] ?? '' ) && 'migration' === ( $r['via'] ?? '' ), '8: el filtro escribe la sal y registra que fue Migrar: ' . json_encode( $r ) );

// 9 · Con sal (constante en vigor): nada que hacer y ningún aviso.
cs_reset_config( $original );
define( 'WP_CACHE_KEY_SALT', 'plomerito.com:x:' );
$r = Cache_Salt::ensure( 'admin' );
cs_check( 'not_needed' === $r['state'] && $original === file_get_contents( $config ) && '' === Cache_Notice::status()['state'], '9: con sal no actúa: ' . json_encode( $r ) );

// AGENTS.md documenta la regla.
$agents = (string) file_get_contents( $root . '/AGENTS.md' );
cs_check( false !== strpos( $agents, 'no debemos modificar nada nosotros' ) && false !== strpos( $agents, 'Cache_Salt' ), 'AGENTS.md debe llevar la regla con la cita del propietario.' );

array_map( 'unlink', array_merge( glob( $base . '/privado/*' ), glob( ABSPATH . '*' ), glob( ABSPATH . '.wp-config*' ), glob( $base . '/*.php' ) ) );
if ( $problems ) {
	echo "FALLA tools-cache-salt:\n- " . implode( "\n- ", $problems ) . "\n";
	exit( 1 );
}
echo "OK tools-cache-salt\n";
