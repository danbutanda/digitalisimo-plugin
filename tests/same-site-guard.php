<?php
/**
 * Candado «mismo sitio» (includes/class-digitalisimo-same-site.php en Backups, Migración y Tools).
 *
 * Incidente del 2026-11-20: con una caché de objetos persistente compartida entre dos cuentas del servidor y sin
 * WP_CACHE_KEY_SALT, «Migrar a DIGITALÍSIMO» en un sitio dejó las opciones del otro (home, siteurl, blogname,
 * active_plugins, site_icon). Aquí:
 *  - Estático: ningún wp_cache_flush() en el código propio (vaciar la caché global borra las claves de los otros sitios
 *    y los hace volver a llenar `alloptions`); las tres copias son idénticas y cada plugin las carga.
 *  - Unidad: check() detecta una caché que da el `home` de otro sitio, y las invalidaciones nunca vacían la caché global.
 */
error_reporting( E_ALL );
$root     = dirname( __DIR__ );
$problems = array();
function ss_check( $ok, $message ) {
	if ( ! $ok ) $GLOBALS['problems'][] = $message;
}

/* ------------------------------------------------------------------ Estático */

$skip = array( '/digitalisimo-elements/elementor/', '/vendor/', '/node_modules/' );
$own  = array( 'digitalisimo-seo', 'digitalisimo-ia-tools', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration', 'digitalisimo-theme', 'digitalisimo-elements-installer' );
$calls = array();
foreach ( $own as $dir ) {
	if ( ! is_dir( $root . '/' . $dir ) ) continue;
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = str_replace( '\\', '/', (string) $file );
		if ( '.php' !== substr( $path, -4 ) ) continue;
		foreach ( $skip as $part ) if ( false !== strpos( $path, $part ) ) continue 2;
		$tokens = token_get_all( (string) file_get_contents( $path ) );
		$count  = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || 'wp_cache_flush' !== strtolower( $tokens[ $i ][1] ) ) continue;
			// Lo que sigue: «(» es una llamada; lo de antes: `function`, `->` o `::` no son la función global.
			$next = $i + 1;
			while ( $next < $count && is_array( $tokens[ $next ] ) && T_WHITESPACE === $tokens[ $next ][0] ) $next++;
			$prev = $i - 1;
			while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) $prev--;
			$is_call = $next < $count && '(' === $tokens[ $next ];
			$method  = $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true );
			if ( $is_call && ! $method ) $calls[] = substr( $path, strlen( $root ) + 1 ) . ':' . $tokens[ $i ][2];
		}
	}
}
// Única excepción: el candado, con la caché aislada por WP_CACHE_KEY_SALT o WP_REDIS_PREFIX (vaciarla sólo toca esta
// instalación).
$isolated = array();
foreach ( $calls as $i => $call ) {
	if ( 1 === preg_match( '#/includes/class-digitalisimo-same-site\.php:\d+$#', $call ) ) { $isolated[] = $call; unset( $calls[ $i ] ); }
}
ss_check( 3 === count( $isolated ), 'El candado debe vaciar la caché sólo en su rama aislada (una vez por copia): ' . implode( ', ', $isolated ) );
ss_check( false !== strpos( (string) file_get_contents( $root . '/digitalisimo-backups/includes/class-digitalisimo-same-site.php' ), "if ( self::salted() && function_exists( 'wp_cache_flush' ) ) {" ), 'El vaciado del candado debe ir sólo con la sal o el prefijo propios.' );
ss_check( ! $calls, 'Quedan llamadas a wp_cache_flush() (vacía la caché global, compartida con otros sitios): ' . implode( ', ', $calls ) );

$copies = array();
foreach ( array( 'digitalisimo-backups', 'digitalisimo-migration', 'digitalisimo-tools' ) as $dir ) {
	$path = $root . '/' . $dir . '/includes/class-digitalisimo-same-site.php';
	ss_check( is_file( $path ), "Falta la copia del candado en {$dir}." );
	$copies[ $dir ] = is_file( $path ) ? (string) file_get_contents( $path ) : '';
}
ss_check( 1 === count( array_unique( $copies ) ), 'Las copias de class-digitalisimo-same-site.php de Backups, Migración y Tools deben ser idénticas.' );
$loaders = array(
	'digitalisimo-backups/digitalisimo-backups.php'     => "includes/class-digitalisimo-same-site.php",
	'digitalisimo-backups/includes/class-backups.php'   => "class-digitalisimo-same-site.php",
	'digitalisimo-migration/digitalisimo-migration.php' => "includes/class-digitalisimo-same-site.php",
	'digitalisimo-tools/digitalisimo-tools.php'         => "includes/class-digitalisimo-same-site.php",
);
foreach ( $loaders as $file => $needle ) ss_check( false !== strpos( (string) file_get_contents( $root . '/' . $file ), $needle ), "{$file} debe cargar el candado «mismo sitio»." );
$full = (string) file_get_contents( $root . '/digitalisimo-migration/includes/class-full.php' );
ss_check( 1 === preg_match( '/\$alien = self::alien\(\);\s*if \( \'\' !== \$alien \) \{\s*throw new Same_Site_Exception\( \$alien \);\s*\}\s*\$result = activate_plugin\(/', $full ), 'Migrar debe comprobar el candado justo antes de cada activate_plugin().' );
ss_check( false !== strpos( $full, "\$jobs::tick( max( 5, (int) floor( \$deadline - microtime( true ) ) ), (string) \$state['backup']['job'] );" ), 'Migrar debe conducir sólo su propio trabajo de respaldo (tick con su id).' );
ss_check( 3 === preg_match_all( '/self::assert_same_site\(\);\s*deactivate_plugins\(/', $full ) && 3 === substr_count( $full, 'deactivate_plugins(' ), 'Migrar debe comprobar el candado justo antes de cada deactivate_plugins().' );
ss_check( 1 === preg_match( '/self::assert_same_site\(\);\s*switch_theme\(/', $full ), 'Migrar debe comprobar el candado justo antes de switch_theme().' );
$switch = (string) file_get_contents( $root . '/digitalisimo-theme/inc/switch.php' );
ss_check( 1 === preg_match( '/\$alien = digitalisimo_theme_alien\(\);\s*if \( \'\' === \$alien \) \{\s*\$saved\s*= wp_update_custom_css_post\(/', $switch ), 'El tema debe comprobar el candado justo antes de wp_update_custom_css_post().' );
$service = (string) file_get_contents( $root . '/digitalisimo-backups/includes/trait-backups-service.php' );
ss_check( false !== strpos( $service, "self::restore_origin_problem( \$path, \$restored, \$args )" ), 'La restauración debe comprobar el origen del manifiesto (restore_origin_problem).' );
$relocate = (string) file_get_contents( $root . '/digitalisimo-backups/includes/class-backups-relocate.php' );
ss_check( false !== strpos( $relocate, "\$home = self::stored_url( 'home' );" ) && false === strpos( $relocate, "\$home = function_exists( 'get_option' ) ? (string) get_option( 'home' )" ), 'Relocate::describe() debe leer home/siteurl de la base, no de la caché.' );
$favicon = (string) file_get_contents( $root . '/digitalisimo-seo/includes/class-favicon.php' );
ss_check( false !== strpos( $favicon, 'return self::files_here();' ), 'El favicon sólo se publica si sus archivos están en este sitio.' );

/* ------------------------------------------------------------------ Unidad: check() e invalidaciones */

define( 'ABSPATH', sys_get_temp_dir() . '/' );
$GLOBALS['ss'] = array( 'ext' => true, 'db' => array( 'home' => 'https://plomerito.com', 'siteurl' => 'https://plomerito.com' ), 'cache' => array(), 'log' => array(), 'supports' => array() );
function wp_using_ext_object_cache() { return $GLOBALS['ss']['ext']; }
function wp_cache_get( $key, $group = '', $force = false ) { return $GLOBALS['ss']['cache'][ $group ][ $key ] ?? false; }
function wp_cache_delete( $key, $group = '' ) { $GLOBALS['ss']['log'][] = "delete:{$group}:{$key}"; unset( $GLOBALS['ss']['cache'][ $group ][ $key ] ); return true; }
function wp_cache_supports( $feature ) { return in_array( $feature, $GLOBALS['ss']['supports'], true ); }
function wp_cache_flush_group( $group ) { $GLOBALS['ss']['log'][] = "flush_group:{$group}"; return true; }
function wp_cache_flush_runtime() { $GLOBALS['ss']['log'][] = 'flush_runtime'; return true; }
function wp_cache_flush() { $GLOBALS['ss']['log'][] = 'GLOBAL_FLUSH'; return true; }
function get_option( $name, $default = false ) {
	$all = $GLOBALS['ss']['cache']['options']['alloptions'] ?? array();
	return array_key_exists( $name, $all ) ? $all[ $name ] : ( $GLOBALS['ss']['db'][ $name ] ?? $default );
}
function is_multisite() { return false; }
final class SS_Wpdb {
	public $options = 'wp_options';
	public $posts = 'wp_posts';
	public $terms = 'wp_terms';
	public $comments = 'wp_comments';
	public $users = 'wp_users';
	public $queries = array();
	public $max = array( 'wp_posts' => 7, 'wp_users' => 1, 'wp_terms' => 0, 'wp_comments' => 0 );
	public $user_rows = array( array( 'ID' => 1, 'user_login' => 'admin', 'user_email' => 'a@plomerito.com', 'user_nicename' => 'admin' ) );
	public function prepare( $sql, ...$args ) { return str_replace( '%s', "'" . addslashes( (string) $args[0] ) . "'", $sql ); }
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( preg_match( '/SELECT MAX\(`\w+`\) FROM (\w+)/', $sql, $m ) ) return $this->max[ $m[1] ] ?? 0;
		return preg_match( "/option_name = '([^']*)'/", $sql, $m ) ? ( $GLOBALS['ss']['db'][ $m[1] ] ?? null ) : null;
	}
	public function get_col( $sql ) { $this->queries[] = $sql; return array(); }
	public function get_results( $sql ) {
		$this->queries[] = $sql;
		if ( false !== strpos( $sql, ' WHERE ' ) ) return array(); // Segundo lote: no hay más.
		if ( false !== strpos( $sql, 'FROM wp_options' ) ) return array_map( function( $name ) { return (object) array( 'option_name' => $name ); }, array_keys( $GLOBALS['ss']['db'] ) );
		if ( false !== strpos( $sql, 'FROM wp_users' ) ) return array_map( function( $row ) { return (object) $row; }, $this->user_rows );
		return array();
	}
}
$GLOBALS['wpdb'] = new SS_Wpdb();
require $root . '/digitalisimo-backups/includes/class-digitalisimo-same-site.php';

// Caché coherente con la base: bien.
$GLOBALS['ss']['cache']['options']['alloptions'] = array( 'home' => 'https://plomerito.com', 'siteurl' => 'https://plomerito.com', 'blogname' => 'Plomerito' );
ss_check( Digitalisimo_Same_Site::ok(), 'Una caché coherente con la base no debe bloquear.' );
// http/https o www no son otro sitio.
$GLOBALS['ss']['cache']['options']['alloptions']['home'] = 'http://www.plomerito.com/';
ss_check( Digitalisimo_Same_Site::ok(), 'http, www o la barra final no son otro sitio.' );
// La caché da el home de otro sitio (el incidente): falla, con el host y la sal.
$GLOBALS['ss']['cache']['options']['alloptions'] = array( 'home' => 'https://digitalisimo.lat', 'siteurl' => 'https://digitalisimo.lat', 'blogname' => 'DIGITALÍSIMO' );
$check = Digitalisimo_Same_Site::check();
ss_check( empty( $check['ok'] ) && 'digitalisimo.lat' === $check['foreign'], 'La caché con el home de otro sitio debe detectarse: ' . json_encode( $check ) );
$refusal = Digitalisimo_Same_Site::refusal( 'Migrar' );
ss_check( 0 === strpos( $refusal, 'La caché de objetos de este servidor está devolviendo datos de otro sitio (digitalisimo.lat); define WP_CACHE_KEY_SALT por sitio o usa una base de caché distinta, y vuelve a pulsar Migrar.' ), 'Mensaje del candado: ' . $refusal );
// Sólo el siteurl ajeno también cuenta.
$GLOBALS['ss']['cache']['options']['alloptions'] = array( 'home' => 'https://plomerito.com', 'siteurl' => 'https://digitalisimo.lat' );
ss_check( ! Digitalisimo_Same_Site::ok(), 'Un siteurl de otro sitio en la caché también debe bloquear.' );
// Sin caché externa no se compara (la de la petición sale de la base).
$GLOBALS['ss']['ext'] = false;
ss_check( Digitalisimo_Same_Site::ok(), 'Sin caché persistente no hay a quién culpar: no bloquea.' );
$GLOBALS['ss']['ext'] = true;
ss_check( ! Digitalisimo_Same_Site::salted(), 'Sin WP_CACHE_KEY_SALT ni WP_REDIS_PREFIX, la caché no tiene sal.' );
ss_check( Digitalisimo_Same_Site::own_url( 'https://www.plomerito.com/wp-cron.php' ) && ! Digitalisimo_Same_Site::own_url( 'https://digitalisimo.lat/wp-cron.php' ), 'own_url() reconoce sólo el host de la base.' );

// Invalidaciones: nunca la caché global compartida.
$GLOBALS['ss']['log'] = array();
Digitalisimo_Same_Site::invalidate( array( 'template', 'stylesheet' ) );
ss_check( in_array( 'delete:options:alloptions', $GLOBALS['ss']['log'], true ) && in_array( 'delete:options:template', $GLOBALS['ss']['log'], true ) && ! in_array( 'GLOBAL_FLUSH', $GLOBALS['ss']['log'], true ), 'invalidate() borra alloptions y las opciones nombradas, sin vaciar la caché: ' . implode( ', ', $GLOBALS['ss']['log'] ) );
$GLOBALS['ss']['cache']['options']['alloptions'] = array( 'home' => 'https://digitalisimo.lat', 'siteurl' => 'https://digitalisimo.lat' );
ss_check( false !== strpos( Digitalisimo_Same_Site::refusal( 'Migrar' ), 'vacía la caché de objetos de este sitio' ), 'El mensaje debe sugerir también vaciar la caché de este sitio (su propia caché vieja).' );

// A2 · Sin borrado por grupo ni sal: lo de antes del cambio de tablas (instantánea) y lo de ahora, exactamente.
$GLOBALS['ss']['cache']['options']['alloptions'] = array( 'home' => 'https://plomerito.com', 'siteurl' => 'https://plomerito.com' );
$GLOBALS['wpdb']->max = array( 'wp_posts' => 40, 'wp_users' => 9, 'wp_terms' => 3, 'wp_comments' => 2 );
$GLOBALS['wpdb']->user_rows = array( array( 'ID' => 1, 'user_login' => 'admin', 'user_email' => 'a@plomerito.com', 'user_nicename' => 'admin' ), array( 'ID' => 9, 'user_login' => 'intruso', 'user_email' => 'x@malo.test', 'user_nicename' => 'intruso' ) );
$GLOBALS['ss']['db']['opcion_nueva'] = 'x';
$before = Digitalisimo_Same_Site::snapshot_site();
ss_check( 9 === $before['users_max'] && 40 === $before['sites'][1]['posts'] && in_array( array( 'intruso', 'x@malo.test', 'intruso' ), $before['users'], true ) && in_array( 'opcion_nueva', $before['sites'][1]['options'], true ), 'snapshot_site() guarda los identificadores mayores, las cuentas y las opciones de antes: ' . json_encode( $before ) );
// El respaldo restaurado es más viejo: sin la cuenta 9, sin la opción nueva, con menos entradas.
unset( $GLOBALS['ss']['db']['opcion_nueva'] );
$GLOBALS['wpdb']->max = array( 'wp_posts' => 10, 'wp_users' => 1, 'wp_terms' => 3, 'wp_comments' => 2 );
$GLOBALS['wpdb']->user_rows = array( array( 'ID' => 1, 'user_login' => 'admin', 'user_email' => 'a@plomerito.com', 'user_nicename' => 'admin' ) );
$GLOBALS['wpdb']->queries = array();
$GLOBALS['ss']['log'] = array();
$done = Digitalisimo_Same_Site::invalidate_site( null, $before );
$log = $GLOBALS['ss']['log'];
ss_check( $done && in_array( 'delete:users:9', $log, true ) && in_array( 'delete:user_meta:9', $log, true ) && in_array( 'delete:userlogins:intruso', $log, true ) && in_array( 'delete:useremail:x@malo.test', $log, true ), 'La cuenta creada después del respaldo (de la instantánea) debe salir de la caché: ' . implode( ', ', array_slice( $log, 0, 30 ) ) );
ss_check( in_array( 'delete:posts:40', $log, true ) && ! in_array( 'delete:posts:41', $log, true ) && ! in_array( 'delete:users:10', $log, true ), 'Hasta el identificador mayor de antes y de ahora, sin colas fijas.' );
ss_check( in_array( 'delete:options:opcion_nueva', $log, true ) && in_array( 'delete:transient:doing_cron', $log, true ) && in_array( 'delete:site-transient:update_plugins', $log, true ) && ! in_array( 'GLOBAL_FLUSH', $log, true ), 'Las opciones de antes y los transitorios del núcleo, sin vaciar la caché compartida.' );
$unordered = array_filter( $GLOBALS['wpdb']->queries, function( $q ) { return 0 === strpos( $q, 'SELECT ' ) && false === strpos( $q, 'MAX(' ) && false === strpos( $q, 'option_value' ) && false === strpos( $q, 'ORDER BY' ); } );
ss_check( ! $unordered && ! preg_grep( '/LIMIT 200000/', $GLOBALS['wpdb']->queries ), 'Los recorridos van por lotes ordenados, sin tope: ' . implode( ' | ', $unordered ) );
ss_check( false === Digitalisimo_Same_Site::invalidate_site( null, array( 'users_max' => 5000000 ), 0.0 ), 'Con el presupuesto agotado, invalidate_site() lo dice (false).' );
$GLOBALS['ss']['supports'] = array( 'flush_group', 'flush_runtime' );
$GLOBALS['ss']['log'] = array();
Digitalisimo_Same_Site::invalidate_site();
ss_check( in_array( 'flush_group:options', $GLOBALS['ss']['log'], true ) && in_array( 'flush_group:transient', $GLOBALS['ss']['log'], true ) && in_array( 'flush_runtime', $GLOBALS['ss']['log'], true ) && ! in_array( 'GLOBAL_FLUSH', $GLOBALS['ss']['log'], true ), 'Con borrado por grupo, invalidate_site() usa wp_cache_flush_group(): ' . implode( ', ', $GLOBALS['ss']['log'] ) );
// Aislada por la sal (y sin borrado por grupo): vaciarla es seguro y lo correcto.
$GLOBALS['ss']['supports'] = array();
define( 'WP_CACHE_KEY_SALT', 'plomerito.com:' );
$GLOBALS['ss']['log'] = array();
Digitalisimo_Same_Site::invalidate_site();
ss_check( array( 'GLOBAL_FLUSH' ) === array_values( array_diff( $GLOBALS['ss']['log'], array( 'flush_runtime' ) ) ), 'Con WP_CACHE_KEY_SALT y sin borrado por grupo, wp_cache_flush(): ' . implode( ', ', $GLOBALS['ss']['log'] ) );
ss_check( Digitalisimo_Same_Site::isolated(), 'Con sal, la caché está aislada (no hace falta el aviso).' );

if ( $problems ) {
	echo "FALLA same-site-guard\n  - " . implode( "\n  - ", $problems ) . "\n";
	exit( 1 );
}
echo "OK same-site-guard\n";
