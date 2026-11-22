<?php
/**
 * Sal propia de la caché de objetos (includes/class-digitalisimo-cache-salt.php, copia idéntica en Tools y Migración).
 *
 * Reglas del propietario: «nadie edita wp-config.php a mano»; «no se trata de bloquear el config, se trata de que uno al
 * migrar no haga nada»; «no mezcles sitios, todo debe ser perfecto». Cada bloque lleva el hallazgo de la revisión
 * (/var/tmp/digi-wt/sal-cache-reports/REVISION.md) que comprueba. La caché de objetos es un archivo JSON compartido entre
 * procesos con la clave `<WP_CACHE_KEY_SALT><grupo>:<clave>` (como Memcached); las situaciones que necesitan constantes
 * (sal en vigor, WP_REDIS_CONFIG…) corren en un proceso hijo (`--child`).
 *
 * Uso: php tests/tools-cache-salt.php   (CS_ROOT=<otra copia> la prueba contra ese código)
 */
error_reporting( E_ALL );
$child    = in_array( '--child', $argv ?? array(), true );
$root     = getenv( 'CS_ROOT' ) ?: dirname( __DIR__ );
$problems = array();
function cs_check( $ok, $message ) {
	if ( ! $ok ) $GLOBALS['problems'][] = $message;
}

$base = getenv( 'CS_BASE' ) ?: sys_get_temp_dir() . '/cs-' . getmypid();
@mkdir( $base . '/wp/wp-content', 0777, true );
@mkdir( $base . '/privado/', 0700, true );
define( 'ABSPATH', $base . '/wp/' );
define( 'WP_CONTENT_DIR', $base . '/wp/wp-content' );
define( 'DB_NAME', 'plomerito_db' );
define( 'DB_HOST', 'localhost' );
foreach ( (array) json_decode( (string) getenv( 'CS_CONSTS' ), true ) as $name => $value ) define( $name, $value );

$GLOBALS['cs'] = array( 'ext' => true, 'multi' => false, 'cap' => true, 'mods' => true, 'filters' => array(), 'actions' => array(), 'log' => array(), 'db' => array( 'home' => 'https://www.plomerito.com', 'siteurl' => 'https://plomerito.com' ), 'meta' => array(), 'valid' => null, 'owner' => null, 'backup_ok' => null, 'runtime' => array(), 'np' => array() );
function wp_using_ext_object_cache() { return $GLOBALS['cs']['ext']; }
function is_multisite() { return $GLOBALS['cs']['multi']; }
function get_main_site_id() { return 1; }
function get_current_network_id() { return 1; }
function get_current_blog_id() { return 1; }
function current_user_can( $cap ) { $GLOBALS['cs']['caps'][] = $cap; return $GLOBALS['cs']['cap']; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function wp_is_file_mod_allowed( $context ) { return $GLOBALS['cs']['mods']; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['cs']['filters'][ $hook ] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['cs']['filters'][ $hook ] = $cb; }
function has_filter( $hook ) { return isset( $GLOBALS['cs']['filters'][ $hook ] ); }
function do_action( $hook, ...$args ) { if ( isset( $GLOBALS['cs']['actions'][ $hook ] ) ) call_user_func_array( $GLOBALS['cs']['actions'][ $hook ], $args ); }
function apply_filters( $hook, $value, ...$args ) {
	if ( in_array( $hook, array( 'digitalisimo_cache_salt_valid', 'digitalisimo_tools_cache_salt_valid' ), true ) && null !== $GLOBALS['cs']['valid'] ) return $GLOBALS['cs']['valid'];
	if ( 'digitalisimo_cache_salt_same_owner' === $hook && null !== $GLOBALS['cs']['owner'] ) return $GLOBALS['cs']['owner'];
	if ( 'digitalisimo_cache_salt_backup_ok' === $hook && null !== $GLOBALS['cs']['backup_ok'] ) return $GLOBALS['cs']['backup_ok'];
	if ( in_array( $hook, array( 'digitalisimo_cache_salt_backup_dir', 'digitalisimo_tools_cache_salt_backup_dir' ), true ) ) return $GLOBALS['base'] . '/privado';
	if ( isset( $GLOBALS['cs']['filters'][ $hook ] ) ) return call_user_func_array( $GLOBALS['cs']['filters'][ $hook ], array_merge( array( $value ), $args ) );
	return $value;
}
// Caché «externa» compartida entre procesos: un archivo JSON. Con CS_IGNORE=1, el drop-in no respeta la sal.
function cs_store( $data = null ) {
	$file = $GLOBALS['base'] . '/cache.json';
	if ( null === $data ) return is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	file_put_contents( $file, json_encode( $data ) );
}
function cs_key( $key, $group ) { return ( defined( 'WP_CACHE_KEY_SALT' ) && ! getenv( 'CS_IGNORE' ) ? WP_CACHE_KEY_SALT : '' ) . ( '' === $group ? 'default' : $group ) . ':' . $key; }
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	$k = cs_key( $key, $group );
	if ( in_array( $group, $GLOBALS['cs']['np'], true ) || ( ! $force && array_key_exists( $k, $GLOBALS['cs']['runtime'] ) ) ) return $GLOBALS['cs']['runtime'][ $k ] ?? false;
	$all = cs_store();
	return array_key_exists( $k, $all ) ? unserialize( $all[ $k ] ) : false;
}
function wp_cache_set( $key, $value, $group = '', $expire = 0 ) {
	$k = cs_key( $key, $group );
	$GLOBALS['cs']['runtime'][ $k ] = $value;
	if ( in_array( $group, $GLOBALS['cs']['np'], true ) ) return true;
	$all = cs_store();
	$all[ $k ] = serialize( $value );
	cs_store( $all );
	return true;
}
function wp_cache_delete( $key, $group = '' ) {
	$GLOBALS['cs']['log'][] = "delete:{$group}:{$key}";
	$k = cs_key( $key, $group );
	unset( $GLOBALS['cs']['runtime'][ $k ] );
	if ( in_array( $group, $GLOBALS['cs']['np'], true ) ) return true;
	$all = cs_store();
	unset( $all[ $k ] );
	cs_store( $all );
	return true;
}
function wp_cache_supports( $feature ) { return in_array( $feature, array( 'flush_group', 'flush_runtime' ), true ); }
function wp_cache_flush_group( $group ) {
	$all = cs_store();
	foreach ( array_keys( $all ) as $k ) if ( 0 === strpos( $k, cs_key( '', $group ) ) ) unset( $all[ $k ] );
	cs_store( $all );
	return true;
}
function wp_cache_flush_runtime() { $GLOBALS['cs']['runtime'] = array(); return true; }
function wp_cache_add_non_persistent_groups( $groups ) { $GLOBALS['cs']['np'] = array_merge( $GLOBALS['cs']['np'], (array) $groups ); }
function wp_cache_flush() { $GLOBALS['cs']['log'][] = 'GLOBAL_FLUSH'; return true; }
function get_option( $name, $default = false ) { return $GLOBALS['cs']['db'][ $name ] ?? $default; }
function get_site_option( $name, $default = false ) { return $GLOBALS['cs']['meta'][ $name ] ?? $default; }
function maybe_unserialize( $raw ) { $v = @unserialize( (string) $raw ); return false === $v && 'b:0;' !== $raw ? $raw : $v; }
final class CS_Wpdb {
	public $options = 'wp_options';
	public $sitemeta = 'wp_sitemeta';
	public $users = 'wp_users';
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
	public function get_col( $sql ) { return array(); }
	public function get_results( $sql ) { return array(); }
	public function query( $sql ) {
		$this->queries[] = $sql;
		if ( preg_match( "/INSERT INTO wp_options .* VALUES \('([^']*)', '(.*)', 'no'\) ON DUPLICATE/s", $sql, $m ) ) $GLOBALS['cs']['db'][ $m[1] ] = stripslashes( $m[2] );
		if ( preg_match( "/INTO wp_sitemeta \(site_id, meta_key, meta_value\) VALUES \(1, '([^']*)', '(.*)'\)/s", $sql, $m ) ) $GLOBALS['cs']['meta'][ $m[1] ] = unserialize( stripslashes( $m[2] ) );
		if ( preg_match( "/UPDATE wp_sitemeta SET meta_value = '(.*)' WHERE meta_id = 7/s", $sql, $m ) ) foreach ( $GLOBALS['cs']['meta'] as $k => $v ) if ( 'digitalisimo_tools_cache_salt' === $k ) $GLOBALS['cs']['meta'][ $k ] = unserialize( stripslashes( $m[1] ) );
		return 1;
	}
}
$GLOBALS['wpdb'] = new CS_Wpdb();
/** Backups presente, con su identidad de instalación (copiable a un clon): la sal no debe depender sólo de ella (H1). */
final class Digitalisimo_Backups {
	public static function installation_owner() { return 'ffffffff' . str_repeat( '0', 56 ); }
	public static function storage_directory() { return ''; }
}
$tools = $root . '/digitalisimo-tools/includes/';
require $tools . 'class-digitalisimo-same-site.php';
require is_file( $tools . 'class-digitalisimo-cache-salt.php' ) ? $tools . 'class-digitalisimo-cache-salt.php' : $tools . 'class-cache-salt.php';
require $tools . 'class-cache-notice.php';
if ( ! class_exists( 'Digitalisimo_Cache_Salt', false ) && class_exists( 'Digitalisimo\\Tools\\Cache_Salt', false ) ) class_alias( 'Digitalisimo\\Tools\\Cache_Salt', 'Digitalisimo_Cache_Salt' ); // Código de antes (CS_ROOT).
use Digitalisimo\Tools\Cache_Notice;

/** Base de datos del «sitio», compartida con los procesos hijos. */
function cs_db_save() { file_put_contents( $GLOBALS['base'] . '/db.json', serialize( array( $GLOBALS['cs']['db'], $GLOBALS['cs']['meta'], $GLOBALS['cs']['multi'] ) ) ); }
function cs_db_load() { if ( is_file( $GLOBALS['base'] . '/db.json' ) ) list( $GLOBALS['cs']['db'], $GLOBALS['cs']['meta'], $GLOBALS['cs']['multi'] ) = unserialize( (string) file_get_contents( $GLOBALS['base'] . '/db.json' ) ); }

if ( $child ) {
	cs_db_load();
	$action = (string) getenv( 'CS_ACTION' );
	$out    = array();
	if ( 'admin' === $action ) Digitalisimo_Cache_Salt::admin();
	if ( 'ensure' === $action || 'migration' === $action ) $out['result'] = Digitalisimo_Cache_Salt::ensure( $action );
	if ( 'bypass' === $action ) {
		$out['before'] = Digitalisimo_Same_Site::check()['ok'];
		$out['result'] = Digitalisimo_Cache_Salt::ensure( 'migration' );
		$out['bypass'] = method_exists( 'Digitalisimo_Cache_Salt', 'bypass' ) && Digitalisimo_Cache_Salt::bypass();
		$out['after']  = Digitalisimo_Same_Site::check()['ok'];
		$out['home']   = get_option( 'home' );
	}
	$out['need']   = method_exists( 'Digitalisimo_Cache_Salt', 'need' ) ? Digitalisimo_Cache_Salt::need() : '';
	$out['last']   = Digitalisimo_Cache_Salt::last();
	$out['status'] = Cache_Notice::status();
	$out['log']    = $GLOBALS['cs']['log'];
	cs_db_save();
	echo json_encode( $out );
	exit( 0 );
}

/** Una petición con constantes (sal en vigor, etc.) en otro proceso. */
function cs_child( $action, array $consts = array(), array $env = array() ) {
	cs_db_save();
	$vars = array( 'CS_BASE' => $GLOBALS['base'], 'CS_ROOT' => $GLOBALS['root'], 'CS_ACTION' => $action, 'CS_CONSTS' => json_encode( (object) $consts ) ) + $env;
	$cmd  = '';
	foreach ( $vars as $k => $v ) $cmd .= $k . '=' . escapeshellarg( (string) $v ) . ' ';
	$raw = (string) shell_exec( $cmd . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --child 2>&1' );
	cs_db_load();
	$out = json_decode( $raw, true );
	if ( ! is_array( $out ) ) { cs_check( false, "proceso hijo ({$action}): {$raw}" ); return array( 'result' => array(), 'status' => array(), 'last' => array(), 'need' => '', 'log' => array() ); }
	return $out;
}
/** Nueva petición en este proceso: olvida el resultado guardado en memoria. */
function cs_request() {
	$r = new ReflectionProperty( 'Digitalisimo_Cache_Salt', 'done' );
	$r->setAccessible( true );
	$r->setValue( null, null );
	$c = new ReflectionProperty( 'Digitalisimo_Same_Site', 'last' );
	$c->setAccessible( true );
	$c->setValue( null, null );
	$GLOBALS['cs']['log']     = array();
	$GLOBALS['cs']['runtime'] = array();
}
$config   = ABSPATH . 'wp-config.php';
$dropin   = WP_CONTENT_DIR . '/object-cache.php';
$original = "<?php\n/** Ajustes de la base. */\ndefine( 'DB_NAME', 'plomerito_db' );\n// define( 'WP_CACHE_KEY_SALT', 'comentada' );\n\$table_prefix = 'wp_';\n\n/* That's all, stop editing! Happy publishing. */\n\nif ( ! defined( 'ABSPATH' ) ) {\n\tdefine( 'ABSPATH', __DIR__ . '/' );\n}\nrequire_once ABSPATH . 'wp-settings.php';\n";
$plugin   = "<?php\n/**\n * Plugin Name: Redis Object Cache Drop-In\n */\nif ( ! defined( 'WP_CACHE_KEY_SALT' ) ) define( 'WP_CACHE_KEY_SALT', '' );\nfunction wp_cache_init() {}\n";
function cs_reset_config( $contents, $perms = 0640 ) {
	global $config, $dropin;
	if ( is_link( $config ) ) unlink( $config );
	if ( is_file( $config ) ) chmod( $config, 0640 );
	file_put_contents( $config, $contents );
	chmod( $config, $perms );
	if ( is_file( $dropin ) ) unlink( $dropin );
	clearstatcache();
	foreach ( glob( $GLOBALS['base'] . '/privado/*' ) as $f ) unlink( $f );
	@unlink( $GLOBALS['base'] . '/cache.json' );
	$GLOBALS['cs']['db']   = array( 'home' => 'https://www.plomerito.com', 'siteurl' => 'https://plomerito.com' );
	$GLOBALS['cs']['meta'] = array();
	$GLOBALS['cs']['np']   = array();
	cs_request();
}
$id8  = substr( hash( 'sha256', realpath( ABSPATH ) . "\nplomerito_db\nlocalhost\nwp_" ), 0, 8 );
$salt = "plomerito.com:{$id8}:";
$line = "defined( 'WP_CACHE_KEY_SALT' ) || define( 'WP_CACHE_KEY_SALT', '{$salt}' );";
$mark = '/* DIGITALÍSIMO: sal propia de la caché de objetos */';
$lint = function ( $code ) { try { token_get_all( $code, TOKEN_PARSE ); return true; } catch ( \Throwable $e ) { return false; } };

// H7 · Copia idéntica en Tools y Migración, que la usa sin depender de Tools.
$copies = array();
foreach ( array( 'digitalisimo-tools', 'digitalisimo-migration' ) as $dir ) $copies[] = (string) @file_get_contents( $root . '/' . $dir . '/includes/class-digitalisimo-cache-salt.php' );
cs_check( '' !== $copies[0] && 1 === count( array_unique( $copies ) ), 'H7: Tools y Migración deben llevar copias idénticas de includes/class-digitalisimo-cache-salt.php.' );

// 1 · Caché externa sin sal: agrega el bloque antes de «That's all», con copia previa y registro.
cs_reset_config( $original );
$neighbor = array( 'home' => 'https://vecino.test', 'siteurl' => 'https://vecino.test', 'active_plugins' => array() );
cs_store( array( 'options:alloptions' => serialize( $neighbor ) ) + cs_store() ); // Lo del vecino sin sal, en la clave compartida.
$GLOBALS['cs']['db'] = array( 'home' => 'https://www.plomerito.com', 'siteurl' => 'https://plomerito.com' );
Digitalisimo_Cache_Salt::init();
$r   = Digitalisimo_Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $config );
cs_check( 'written' === $r['state'] && $salt === ( $r['salt'] ?? '' ), '1/H1: escribe la sal de la ruta, la base y el prefijo (no la identidad copiable de Backups): ' . json_encode( $r ) );
cs_check( 1 === substr_count( $now, $line ) && 1 === substr_count( $now, $mark ) && false !== strpos( $now, "defined( 'WP_REDIS_PREFIX' ) || define( 'WP_REDIS_PREFIX', '{$salt}' );" ), '1/S1: el bloque define WP_CACHE_KEY_SALT y WP_REDIS_PREFIX: ' . $now );
cs_check( strpos( $now, $mark ) < strpos( $now, "/* That's all, stop editing!" ) && $lint( $now ), '1: el bloque va antes de «That\'s all» y el PHP es válido.' );
$copies = glob( $base . '/privado/wp-config-antes-de-la-sal-*.php' );
cs_check( 1 === count( $copies ) && $original === file_get_contents( $copies[0] ) && 0600 === ( fileperms( $copies[0] ) & 0777 ), '1: copia previa idéntica y 0600: ' . json_encode( $copies ) );
cs_check( 0640 === ( fileperms( $config ) & 0777 ) && ! glob( ABSPATH . '.digitalisimo-sal-*' ), '1: conserva los permisos y no deja el temporal.' );
// H2 · Tras escribir no se toca la caché compartida: el vecino sigue leyendo lo suyo, no lo de este sitio.
cs_check( ! preg_grep( '/^delete:options:/', $GLOBALS['cs']['log'] ) && ! in_array( 'GLOBAL_FLUSH', $GLOBALS['cs']['log'], true ), 'H2: tras escribir no borra claves compartidas (las volvería a llenar con los datos de este sitio): ' . implode( ',', $GLOBALS['cs']['log'] ) );
cs_check( 'https://vecino.test' === ( unserialize( cs_store()['options:alloptions'] ?? 'b:0;' )['home'] ?? '' ), 'H2: la clave compartida sigue con lo del vecino.' );
$status = Cache_Notice::status();
cs_check( 'salted' === $status['state'] && false !== stripos( $status['message'], 'no hace falta hacer nada' ), '1: el aviso dice que ya se agregó: ' . json_encode( $status ) );
// H3 · El aviso no dice que quitar el bloque sea seguro.
cs_check( false === stripos( $status['message'], 'quitarlo a mano es seguro' ) && false !== strpos( $status['message'], 'hay que vaciar la caché de objetos de este sitio' ), 'H3: el aviso dice que al quitar el bloque hay que vaciar la caché de este sitio: ' . $status['message'] );
cs_check( false === stripos( (string) file_get_contents( $root . '/digitalisimo-tools/includes/class-digitalisimo-cache-salt.php' ), 'se puede quitar a mano sin riesgo' ), 'H3: el bloque no dice que se pueda quitar sin riesgo.' );

// 2 · No la duplica: otra petición (la constante aún no está en este proceso) encuentra el bloque y no escribe.
cs_request();
$before = (string) file_get_contents( $config );
$r      = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'pending' === $r['state'] && $before === file_get_contents( $config ), '2: el bloque nunca se duplica: ' . json_encode( $r ) );
cs_request();
$GLOBALS['wpdb']->queries = array();
Digitalisimo_Cache_Salt::admin();
cs_check( ! preg_grep( '/^INSERT INTO wp_options/', $GLOBALS['wpdb']->queries ), '2: el escritorio no repite el intento ni el registro.' );
// H15/H13 · Escrita hace más de 5 minutos y sin efecto: se explica sin pedir que se copie lo que ya está.
$log = Digitalisimo_Cache_Salt::last();
$log['at'] = time() - 600;
$GLOBALS['cs']['db']['digitalisimo_tools_cache_salt'] = serialize( $log );
cs_request();
$status = Cache_Notice::status();
cs_check( false === strpos( $status['message'], $line ) && false === strpos( $status['message'], 'copia en' ), 'H13: el bloque ya puesto y sin efecto no pide copiar la línea: ' . json_encode( $status ) );

// S1 · Petición siguiente con la sal en vigor: la clave de prueba ya no se ve (el drop-in la respeta) → activa.
$a = cs_child( 'admin', array( 'WP_CACHE_KEY_SALT' => $salt, 'WP_REDIS_PREFIX' => $salt ) );
cs_check( 'active' === ( $a['last']['state'] ?? '' ) && $salt === ( $a['last']['seen'] ?? '' ), 'S1: con la sal en vigor y respetada, queda «active»: ' . json_encode( $a['last'] ) );
cs_check( 'salted' === ( $a['status']['state'] ?? '' ) && false !== strpos( (string) ( $a['status']['message'] ?? '' ), 'ya está en vigor' ), 'H13: el aviso de éxito se ve después (también si escribió Migrar): ' . json_encode( $a['status'] ) );
$a = cs_child( 'admin', array( 'WP_CACHE_KEY_SALT' => $salt, 'WP_REDIS_PREFIX' => $salt ) );
cs_check( '' === $a['need'] && ! preg_grep( '/^delete:/', $a['log'] ), 'S1: después, nada que hacer: ' . json_encode( $a ) );

// H1 · La caché da datos ajenos con nuestra sal (o la sal es de otra instalación): Tools la cambia sola.
cs_store( array( $salt . 'options:alloptions' => serialize( $neighbor ) ) + cs_store() );
$a   = cs_child( 'admin', array( 'WP_CACHE_KEY_SALT' => $salt, 'WP_REDIS_PREFIX' => $salt ) );
$now = (string) file_get_contents( $config );
cs_check( 'written' === ( $a['last']['state'] ?? '' ) && "plomerito.com:{$id8}.1:" === ( $a['last']['salt'] ?? '' ) && 2 === substr_count( $now, "'plomerito.com:{$id8}.1:'" ) && 1 === substr_count( $now, $mark ) && false === strpos( $now, "'{$salt}'" ), 'H1: con la caché dando datos ajenos y nuestra sal, se cambia dentro del bloque: ' . json_encode( $a['last'] ) );
cs_check( $lint( $now ), 'H1: el wp-config.php con la sal cambiada es PHP válido.' );
// Un clon: wp-config.php copiado con la sal del original (otra ruta) → se reemplaza por la de esta instalación.
$clone = str_replace( "plomerito.com:{$id8}.1:", 'plomerito.com:0badc0de:', $now );
file_put_contents( $config, $clone );
$GLOBALS['cs']['db']['digitalisimo_tools_cache_salt'] = serialize( array( 'state' => 'active', 'seen' => 'plomerito.com:0badc0de:', 'salt' => 'plomerito.com:0badc0de:' ) );
$a   = cs_child( 'admin', array( 'WP_CACHE_KEY_SALT' => 'plomerito.com:0badc0de:', 'WP_REDIS_PREFIX' => 'plomerito.com:0badc0de:' ) );
$now = (string) file_get_contents( $config );
cs_check( 'written' === ( $a['last']['state'] ?? '' ) && 2 === substr_count( $now, "'{$salt}'" ) && false === strpos( $now, '0badc0de' ), 'H1: un clon con la sal del original la cambia por la suya: ' . json_encode( $a['last'] ) );

// 3 · Sin caché externa: no actúa.
cs_reset_config( $original );
$GLOBALS['cs']['ext'] = false;
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'not_needed' === $r['state'] && $original === file_get_contents( $config ) && ! glob( $base . '/privado/*' ), '3: sin caché externa no toca nada: ' . json_encode( $r ) );
$GLOBALS['cs']['ext'] = true;

// 4 · La validación falla: se repone el original.
cs_reset_config( $original );
$GLOBALS['cs']['valid'] = false;
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
$GLOBALS['cs']['valid'] = null;
cs_check( 'failed' === $r['state'] && $original === file_get_contents( $config ) && 0640 === ( fileperms( $config ) & 0777 ) && false !== strpos( $r['message'], 'se repuso el original' ), '4: si la comprobación falla, vuelve el original: ' . json_encode( $r ) );
$status = Cache_Notice::status();
cs_check( 'unsalted' === $status['state'] && false !== strpos( $status['message'], $line ), '4/H13: el aviso da la línea exacta, con defined() ||: ' . json_encode( $status ) );

// H11/H9 · Otro proceso edita wp-config.php entre la lectura y la escritura: no se pisa ni se «repone».
foreach ( array( 'rename' => null, 'en su sitio' => false ) as $how => $owner ) {
	cs_reset_config( $original );
	$GLOBALS['cs']['owner'] = $owner;
	$other = str_replace( "\$table_prefix = 'wp_';", "\$table_prefix = 'wp_';\ndefine( 'WP_CACHE', true ); // WP Rocket", $original );
	$GLOBALS['cs']['actions']['digitalisimo_cache_salt_before_write'] = function ( $path ) use ( $other ) { file_put_contents( $path, $other ); };
	$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
	unset( $GLOBALS['cs']['actions']['digitalisimo_cache_salt_before_write'] );
	$GLOBALS['cs']['owner'] = null;
	cs_check( 'failed' === $r['state'] && $other === file_get_contents( $config ) && false !== strpos( $r['message'], 'cambió mientras se escribía' ), "H11/H9 ({$how}): la escritura de otro proceso se conserva, sin decir que se perdió el original: " . json_encode( $r ) );
}

// H4/H5 · Dueño distinto (el temporal no saldría con el dueño, el grupo y los permisos del archivo): en su sitio, sin vaciarlo.
cs_reset_config( $original );
$GLOBALS['cs']['owner'] = false;
$inode = fileinode( $config );
$seen  = array();
$GLOBALS['cs']['actions']['digitalisimo_cache_salt_before_write'] = function ( $path ) use ( &$seen ) { clearstatcache(); $seen[] = filesize( $path ); };
$r   = Digitalisimo_Cache_Salt::ensure( 'admin' );
unset( $GLOBALS['cs']['actions']['digitalisimo_cache_salt_before_write'] );
$GLOBALS['cs']['owner'] = null;
clearstatcache();
$now = (string) file_get_contents( $config );
cs_check( 'written' === $r['state'] && $inode === fileinode( $config ) && 1 === substr_count( $now, $line ) && $lint( $now ) && 0 === strpos( $now, substr( $original, 0, strpos( $original, '/* That' ) ) ), 'H4: con otro dueño se escribe en el mismo archivo, desde el punto de inserción: ' . json_encode( $r ) );
$src = (string) file_get_contents( $root . '/digitalisimo-tools/includes/class-digitalisimo-cache-salt.php' );
cs_check( false === strpos( $src, 'ftruncate( $handle, 0 )' ) && false === strpos( $src, 'posix_' ), 'H4/H5: nunca vacía el archivo ni depende de posix para saber el dueño.' );
cs_check( false !== strpos( $src, 'fileowner( $tmp ) === @fileowner( $path )' ), 'H5: compara el dueño del temporal con el del archivo antes del rename.' );

// H10 · Copia previa que no se pudo verificar: no se queda a medias y no se escribe.
cs_reset_config( $original );
$GLOBALS['cs']['backup_ok'] = false;
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
$GLOBALS['cs']['backup_ok'] = null;
cs_check( 'manual' === $r['state'] && $original === file_get_contents( $config ) && ! glob( $base . '/privado/*' ), 'H10: una copia previa fallida se borra y no se toca wp-config.php: ' . json_encode( $r ) );

// H6 · DISALLOW_FILE_MODS (wp_is_file_mod_allowed()): no se escribe.
cs_reset_config( $original );
$GLOBALS['cs']['mods'] = false;
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
$GLOBALS['cs']['mods'] = true;
cs_check( 'manual' === $r['state'] && $original === file_get_contents( $config ) && false !== strpos( (string) $r['message'], 'DISALLOW_FILE_MODS' ), 'H6: con DISALLOW_FILE_MODS no se toca: ' . json_encode( $r ) );

// 5 · Permisos: sin escritura y sin drop-in, no se toca y se da la línea; un enlace no se reemplaza.
cs_reset_config( $original, 0440 );
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && $line === $r['line'] && $original === file_get_contents( $config ) && ! glob( $base . '/privado/*' ), '5: sin permiso de escritura no se toca: ' . json_encode( $r ) );
$status = Cache_Notice::status();
cs_check( 'unsalted' === $status['state'] && false !== strpos( $status['message'], $line ) && false !== strpos( $status['message'], 'no pudo agregarla solo' ), '5: el aviso pide copiar la línea: ' . json_encode( $status ) );
cs_request();
$GLOBALS['wpdb']->queries = array();
Digitalisimo_Cache_Salt::admin();
cs_check( ! preg_grep( '/^INSERT INTO wp_options/', $GLOBALS['wpdb']->queries ), '5: un intento sin permiso no se repite con los mismos archivos.' );
chmod( $config, 0640 );
file_put_contents( $base . '/real-config.php', $original );
unlink( $config );
symlink( $base . '/real-config.php', $config );
cs_request();
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && $original === file_get_contents( $base . '/real-config.php' ) && is_link( $config ), '5: un wp-config.php enlazado no se reemplaza: ' . json_encode( $r ) );
unlink( $config );
cs_request();
file_put_contents( $base . '/wp-config.php', $original );
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'written' === $r['state'] && $base . '/wp-config.php' === $r['file'] && 1 === substr_count( (string) file_get_contents( $base . '/wp-config.php' ), $line ), '5: wp-config.php en la carpeta padre: ' . json_encode( $r ) );
unlink( $base . '/wp-config.php' );

// H8 · wp-config.php sin permiso de escritura y un drop-in que sí se puede escribir: la sal va al principio del drop-in.
cs_reset_config( $original, 0440 );
file_put_contents( $dropin, $plugin );
$r   = Digitalisimo_Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $dropin );
cs_check( 'written' === $r['state'] && 'dropin' === ( $r['target'] ?? '' ) && 0 === strpos( $now, "<?php\n" . $mark ) && strpos( $now, $line ) < strpos( $now, "define( 'WP_CACHE_KEY_SALT', '' )" ) && $lint( $now ) && $original === file_get_contents( $config ), 'H8: la sal se antepone en object-cache.php: ' . json_encode( $r ) . "\n" . $now );
$status = Cache_Notice::status();
cs_check( 'salted' === $status['state'] && false !== strpos( $status['message'], 'object-cache.php' ), 'H8: el aviso dice que va en el drop-in: ' . json_encode( $status ) );
// Con declare(strict_types=1), después de él; con namespace al principio, no hay sitio seguro y queda el aviso.
cs_reset_config( $original, 0440 );
file_put_contents( $dropin, "<?php\ndeclare(strict_types=1);\n/** Plugin Name: Object Cache Pro */\nfunction wp_cache_init() {}\n" );
$r   = Digitalisimo_Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $dropin );
cs_check( 'written' === $r['state'] && strpos( $now, 'declare(strict_types=1);' ) < strpos( $now, $mark ) && $lint( $now ), 'H8: con declare(strict_types=1) el bloque va después: ' . $now );
cs_reset_config( $original, 0440 );
file_put_contents( $dropin, "<?php\nnamespace Cache;\nfunction x() {}\n" );
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && false === strpos( (string) file_get_contents( $dropin ), $mark ), 'H8: con namespace al principio del drop-in no se escribe: ' . json_encode( $r ) );
// W3 Total Cache ignora la sal y separa por host y W3TC_INSTANCE_ID: el bloque también lo define.
cs_reset_config( $original );
file_put_contents( $dropin, "<?php\n/**\n * W3 Total Cache Object Cache\n */\n" );
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'written' === $r['state'] && 1 === preg_match( "/defined\( 'W3TC_INSTANCE_ID' \) \|\| define\( 'W3TC_INSTANCE_ID', [1-9][0-9]* \);/", (string) file_get_contents( $config ) ), 'S1: con W3 Total Cache el bloque define W3TC_INSTANCE_ID.' );
// H8/H3 · El plugin de caché reescribe su drop-in (la sal desaparece): se invalida lo viejo sin sal y se repone con otro valor.
cs_reset_config( $original, 0440 );
file_put_contents( $dropin, $plugin );
Digitalisimo_Cache_Salt::ensure( 'admin' );
$a = cs_child( 'admin', array( 'WP_CACHE_KEY_SALT' => $salt, 'WP_REDIS_PREFIX' => $salt ) );
cs_check( 'active' === ( $a['last']['state'] ?? '' ), 'H8: la sal del drop-in entra en vigor: ' . json_encode( $a['last'] ) );
file_put_contents( $dropin, $plugin ); // Actualización del plugin de caché.
cs_store( array( 'user_meta:7' => serialize( array( 'wp_capabilities' => array( 'administrator' => true ) ) ), 'options:alloptions' => serialize( $neighbor ) ) );
$a   = cs_child( 'admin' );
$now = (string) file_get_contents( $dropin );
cs_check( ! array_key_exists( 'user_meta:7', cs_store() ), 'H3/H8: al desaparecer la sal, se invalidan las claves viejas de este sitio sin sal (capacidades): ' . json_encode( array_keys( cs_store() ) ) );
cs_check( 'written' === ( $a['last']['state'] ?? '' ) && 2 === substr_count( $now, "'plomerito.com:{$id8}.1:'" ) && $lint( $now ), 'H3/H8: y se repone con otro valor (el espacio con la sal anterior también quedó viejo): ' . json_encode( $a['last'] ) );
cs_check( ! in_array( 'GLOBAL_FLUSH', $a['log'], true ), 'H3: sin vaciar la caché global.' );

// H12 · Nunca en código que no se ejecuta siempre.
cs_reset_config( "<?php\nif ( getenv( 'X' ) ) {\n\t/* That's all, stop editing! */\n\tdefine( 'A', 1 );\n}\nrequire_once ABSPATH . 'wp-settings.php';\n" );
$r   = Digitalisimo_Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $config );
cs_check( 'written' === $r['state'] && strpos( $now, $mark ) > strpos( $now, "}\n" ) && strpos( $now, $mark ) < strpos( $now, 'require_once' ), 'H12: «That\'s all» dentro de un if: el bloque va antes del require, fuera de las llaves: ' . $now );
cs_reset_config( "<?php\nif ( getenv( 'X' ) )\n/* That's all, stop editing! */\ndefine( 'A', 1 );\nrequire_once ABSPATH . 'wp-settings.php';\n" );
$r   = Digitalisimo_Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $config );
cs_check( 'written' === $r['state'] && strpos( $now, $mark ) > strpos( $now, "define( 'A', 1 );" ), 'H12: detrás de un if sin llaves, no: ' . $now );
cs_reset_config( "<?php\nreturn;\n/* That's all, stop editing! */\nrequire_once ABSPATH . 'wp-settings.php';\n" );
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && false === strpos( (string) file_get_contents( $config ), $mark ), 'H12: después de un return, no: ' . json_encode( $r ) );

// 6 · Nunca dentro de un comentario; una sal ya escrita en el código (sin efecto) no se toca y el aviso lo dice.
cs_reset_config( "<?php\n/**\n * That's all, stop editing (dentro de un comentario)\n */\ndefine( 'DB_NAME', 'x' );\nrequire_once( ABSPATH . 'wp-settings.php' );\n" );
$r   = Digitalisimo_Cache_Salt::ensure( 'admin' );
$now = (string) file_get_contents( $config );
cs_check( 'written' === $r['state'] && strpos( $now, $line ) > strpos( $now, ' */' ) && strpos( $now, $line ) < strpos( $now, 'require_once(' ), '6: nunca dentro de un comentario; si no, antes del require: ' . $now );
cs_reset_config( "<?php\nif ( getenv( 'X' ) ) define( 'WP_CACHE_KEY_SALT', 'otra:' );\nrequire_once ABSPATH . 'wp-settings.php';\n" );
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'manual' === $r['state'] && false === strpos( (string) file_get_contents( $config ), $mark ) && false !== strpos( $r['message'], 'WP_CACHE_KEY_SALT' ), '6: con una sal ya escrita en wp-config.php no se agrega otra: ' . json_encode( $r ) );
// H13 · WP_REDIS_CONFIG sin `prefix` (Object Cache Pro): no es «ya nombra una sal»; se escribe y la clave de prueba decide.
cs_reset_config( "<?php\ndefine( 'WP_REDIS_CONFIG', array( 'token' => 'x' ) );\n/* That's all, stop editing! */\nrequire_once ABSPATH . 'wp-settings.php';\n" );
$a = cs_child( 'ensure', array( 'WP_REDIS_CONFIG' => array( 'token' => 'x' ) ) );
cs_check( 'written' === ( $a['result']['state'] ?? '' ), 'H13: con WP_REDIS_CONFIG sin prefix se escribe la sal: ' . json_encode( $a['result'] ) );
// S1 · Un drop-in que no respeta la sal (la clave de prueba sigue visible): «ignored», y Migrar lee directo de la base.
cs_store( array( 'options:alloptions' => serialize( $neighbor ) ) + cs_store() );
$a = cs_child( 'bypass', array( 'WP_REDIS_CONFIG' => array( 'token' => 'x' ), 'WP_CACHE_KEY_SALT' => $salt, 'WP_REDIS_PREFIX' => $salt ), array( 'CS_IGNORE' => '1' ) );
cs_check( 'ignored' === ( $a['result']['state'] ?? '' ) && false !== strpos( (string) ( $a['result']['message'] ?? '' ), "'prefix' => '{$salt}'" ), 'S1: un drop-in que ignora la sal queda «ignored» y dice qué prefijo usar: ' . json_encode( $a['result'] ) );
cs_check( false === ( $a['before'] ?? null ) && true === ( $a['bypass'] ?? null ) && true === ( $a['after'] ?? null ) && 'https://www.plomerito.com' === ( $a['home'] ?? '' ), 'S1: sin forma de aislarla, bypass() hace que esta petición lea sus opciones de la base: ' . json_encode( $a ) );
cs_check( ! in_array( 'GLOBAL_FLUSH', $a['log'], true ) && 'https://vecino.test' === ( unserialize( cs_store()['options:alloptions'] ?? 'b:0;' )['home'] ?? '' ), 'S1: sin vaciar la caché global ni tocar lo del vecino.' );
cs_check( in_array( $a['status']['state'] ?? '', array( 'foreign', 'unsalted' ), true ) && false !== strpos( (string) ( $a['status']['message'] ?? '' ), 'Migrar trabaja leyendo directo de la base' ), 'S1: el aviso lo explica: ' . json_encode( $a['status'] ) );
// Con un prefijo propio (WP_REDIS_PREFIX o WP_REDIS_CONFIG['prefix']), nada que hacer.
cs_reset_config( $original );
foreach ( array( array( 'WP_REDIS_PREFIX' => 'mio:' ), array( 'WP_REDIS_CONFIG' => array( 'prefix' => 'mio' ) ), array( 'WP_CACHE_KEY_SALT' => 'mia' ) ) as $consts ) {
	$a = cs_child( 'ensure', $consts );
	cs_check( 'not_needed' === ( $a['result']['state'] ?? '' ) && $original === file_get_contents( $config ), 'S1: con ' . json_encode( $consts ) . ' no se escribe nada: ' . json_encode( $a['result'] ) );
}

// 7 · Red: una sola sal con el host del sitio principal, y el registro en la red.
cs_reset_config( $original );
$GLOBALS['cs']['multi'] = true;
$GLOBALS['cs']['db']['home'] = 'https://red.test';
$GLOBALS['wpdb']->options = 'wp_3_options'; // Estamos en el sitio 3 (sub.red.test).
$r = Digitalisimo_Cache_Salt::ensure( 'admin' );
cs_check( 'written' === $r['state'] && "red.test:{$id8}:" === $r['salt'] && 2 === substr_count( (string) file_get_contents( $config ), "'red.test:{$id8}:'" ), '7: en una red, la sal del sitio principal: ' . json_encode( $r ) );
cs_check( 'written' === ( $GLOBALS['cs']['meta']['digitalisimo_tools_cache_salt']['state'] ?? '' ), '7: el registro de una red va en la red.' );
$GLOBALS['cs']['caps'] = array();
cs_request();
Digitalisimo_Cache_Salt::admin();
cs_check( array( 'manage_network' ) === $GLOBALS['cs']['caps'], '7: en una red, el escritorio exige manage_network: ' . json_encode( $GLOBALS['cs']['caps'] ) );
$GLOBALS['cs']['multi'] = false;
$GLOBALS['wpdb']->options = 'wp_options';
cs_reset_config( $original );
$GLOBALS['cs']['cap'] = false;
Digitalisimo_Cache_Salt::admin();
cs_check( $original === file_get_contents( $config ), '7: sin manage_options el escritorio no escribe.' );
$GLOBALS['cs']['cap'] = true;
Digitalisimo_Cache_Salt::admin();
cs_check( 1 === substr_count( (string) file_get_contents( $config ), $line ), '7: con manage_options, la siguiente carga del escritorio la escribe.' );

// H7/H14 · Migrar: su propia copia, la sal al principio de cada petición (antes del candado y del análisis), lecturas
// directas de la base mientras no aísla, y otro intento después de instalar.
$full = (string) file_get_contents( $root . '/digitalisimo-migration/includes/class-full.php' );
$fn   = function ( $name ) use ( $full ) { $s = substr( $full, (int) strpos( $full, 'private static function ' . $name . '(' ) ); return substr( $s, 0, (int) strpos( $s, "\n\t}\n" ) ); };
cs_check( false !== strpos( $fn( 'cache_salt' ), "__DIR__ . '/class-digitalisimo-cache-salt.php'" ) && false === strpos( $full, 'digitalisimo-tools/includes/class-cache-salt.php' ) && false === strpos( $full, "dirname( self::TOOLS_FILE ) . '/includes/class-cache-salt.php'" ), 'H7: Migrar usa su propia copia de la clase, no la de Tools.' );
$step = substr( $full, (int) strpos( $full, 'public static function step(' ) );
cs_check( false !== strpos( $step, 'self::isolation();' ) && strpos( $step, 'self::isolation();' ) < strpos( $step, 'self::refused( $network )' ), 'H14: la sal (y las lecturas directas) van antes del candado y del análisis en cada petición.' );
cs_check( false !== strpos( $fn( 'isolation' ), '\\Digitalisimo_Cache_Salt::bypass()' ) && false !== strpos( $fn( 'isolation' ), "'ignored'" ) && false !== strpos( $fn( 'isolation' ), "'written'" ), 'S1/H14: mientras la caché no aísla, Migrar lee directo de la base.' );
cs_check( 1 === preg_match( "/'written' === \\\$now[^}]*return self::changed\( \\\$state \);/s", $fn( 'salted' ) ), 'H7: si la sal se acaba de escribir, Migrar sigue en una petición nueva (changed()).' );
cs_check( false !== strpos( $fn( 'stage_install' ), 'self::isolation( true );' ), 'H7: después de instalar, otro intento de poner la sal.' );
cs_check( false === strpos( $fn( 'stage_backup' ), 'cache_salt' ), 'H7: el respaldo ya no la pide (va al principio de cada petición).' );

// 9 · Con una sal ajena en vigor: nada que hacer y ningún aviso.
cs_reset_config( $original );
$a = cs_child( 'ensure', array( 'WP_CACHE_KEY_SALT' => 'plomerito.com:x:' ) );
cs_check( 'not_needed' === ( $a['result']['state'] ?? '' ) && $original === file_get_contents( $config ) && '' === ( $a['status']['state'] ?? 'x' ), '9: con sal no actúa: ' . json_encode( $a ) );

// AGENTS.md documenta la regla.
$agents = (string) file_get_contents( $root . '/AGENTS.md' );
cs_check( false !== strpos( $agents, 'nadie edita' ) && false !== strpos( $agents, 'Digitalisimo_Cache_Salt' ) && false === strpos( $agents, 'quitarlo a mano es seguro' ), 'AGENTS.md debe llevar la regla con la clase compartida y sin decir que quitar el bloque es seguro.' );

array_map( 'unlink', array_merge( glob( $base . '/privado/*' ), glob( ABSPATH . 'wp-content/*' ), array_filter( glob( ABSPATH . '*' ), 'is_file' ), glob( ABSPATH . '.digitalisimo*' ), glob( $base . '/*.php' ), glob( $base . '/*.json' ) ) );
if ( $problems ) {
	echo "FALLA tools-cache-salt:\n- " . implode( "\n- ", $problems ) . "\n";
	exit( 1 );
}
echo "OK tools-cache-salt\n";
