<?php
/**
 * Los actualizadores y catálogos de los siete plugins y del tema consultan GitHub sin castigar el escritorio
 * (auditoría admin-perf, 2026-11-18):
 *  1. Con GitHub caído o limitado, una carga del escritorio (que lee update_plugins y update_themes) hace como mucho
 *     una petición al índice y una a la API entre todos, no una (o dos) por actualizador y por plugin hermano.
 *  2. El fallo se recuerda dos minutos para todos: la carga siguiente no vuelve a esperar a GitHub.
 *  3. «Buscar actualizaciones» (o tras actualizar) lo olvida y consulta de inmediato.
 *  4. La vuelta del cron de cinco minutos (Backups, Tools, Elements, Migración y SEO) descarga un solo índice, no cinco.
 *  5. En una pantalla el tiempo de espera es de 3 s; en el cron, 10.
 *  6. Toda copia que descarga el índice o consulta la API respeta las mismas marcas.
 *  7. (2026-11-20) Leer update_plugins o update_themes en una pantalla del escritorio no consulta GitHub en ninguna copia
 *     de los plugins: usa la caché propia, el índice compartido que otro bajó o la última versión conocida, sin escribir
 *     nada. Descargan el cron, WP-CLI, las consultas de WordPress (pre_set_…) y las acciones explícitas. El tema (fijado
 *     byte a byte a su ZIP 1.0.1) aún descarga al leer: es el único que pide GitHub en una carga del escritorio.
 * Se cargan a la vez las copias reales (familia compartida de SEO/IA/Hosting, Backups, Tools, Elements, Migración,
 * hermanos de Elements, copias del tema de Tools, comprobación de SEO y, desde el tema 1.0.1, el actualizador del tema)
 * con WordPress simulado.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_PLUGIN_DIR', __DIR__ . '/sin-plugins-instalados' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
foreach ( array( 'BACKUPS' => '1.0.80', 'TOOLS' => '1.0.38', 'ELEMENTS' => '4.3.0.187', 'MIGRATION' => '1.0.28', 'THEME' => '1.0.0', 'INTEGRATIONS' => '1.0.254' ) as $k => $v ) define( 'DIGITALISIMO_' . $k . '_VERSION', $v );
define( 'DIGITALISIMO_BACKUPS_FILE', '/p/digitalisimo-backups/digitalisimo-backups.php' );
define( 'DIGITALISIMO_TOOLS_FILE', '/p/digitalisimo-tools/digitalisimo-tools.php' );
define( 'DIGITALISIMO_ELEMENTS_FILE', '/p/digitalisimo-elements/pro-elements.php' );
define( 'DIGITALISIMO_MIGRATION_FILE', '/p/digitalisimo-migration/digitalisimo-migration.php' );
define( 'DIGITALISIMO_INTEGRATIONS_FILE', '/p/digitalisimo-seo/digitalisimo-integrations.php' );

$GLOBALS['t'] = array(); $GLOBALS['ttl'] = array(); $GLOBALS['o'] = array(); $GLOBALS['calls'] = array(); $GLOBALS['net'] = 'down'; $GLOBALS['cron'] = false;
function add_filter() {} function add_action() {} function has_action() { return false; }
function plugin_basename( $f ) { return preg_replace( '#^/p/#', '', (string) $f ); }
function is_multisite() { return ! empty( $GLOBALS['ms'] ); } function is_main_site() { return true; } function is_network_admin() { return false; } function get_current_network_id() { return 1; }
function wp_next_scheduled() { return 1; } function wp_schedule_event() { return true; } function wp_clear_scheduled_hook() {}
// Reloj del ensayo: un transitorio caduca a su hora (la espera de 2 minutos y el índice reciente de 4 se comprueban así).
$GLOBALS['now'] = 1000000; $GLOBALS['exp'] = array();
function get_site_transient( $k ) { if ( ! empty( $GLOBALS['exp'][ $k ] ) && $GLOBALS['exp'][ $k ] <= $GLOBALS['now'] ) unset( $GLOBALS['t'][ $k ] ); return $GLOBALS['t'][ $k ] ?? false; }
function set_site_transient( $k, $v, $ttl = 0 ) { $GLOBALS['t'][ $k ] = $v; $GLOBALS['ttl'][ $k ] = $ttl; $GLOBALS['exp'][ $k ] = $ttl ? $GLOBALS['now'] + $ttl : 0; return true; }
function delete_site_transient( $k ) { unset( $GLOBALS['t'][ $k ] ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['t'][ $k ] ); return true; }
function get_site_option( $k, $d = false ) { return $GLOBALS['o'][ $k ] ?? $d; }
function update_site_option( $k, $v ) { $GLOBALS['o'][ $k ] = $v; return true; }
function wp_doing_cron() { return $GLOBALS['cron']; }
function current_user_can() { return $GLOBALS['caps'] ?? true; }
function get_bloginfo() { return '6.9'; }
function esc_url_raw( $u ) { return $u; } function esc_url( $u ) { return $u; } function esc_html( $s ) { return $s; } function wp_kses_post( $s ) { return $s; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; } function network_admin_url( $p = '' ) { return admin_url( 'network/' . $p ); }
function add_query_arg( ...$x ) { if ( is_array( $x[0] ) ) return $x[1] . '?' . http_build_query( $x[0] ); return $x[2] . '?' . http_build_query( array( $x[0] => $x[1] ) ); }
function is_wp_version_compatible() { return true; } function is_php_version_compatible() { return true; }
function wp_update_plugins() {} function wp_update_themes() {}
function get_template() { return 'digitalisimo-theme'; }
final class Digi_Theme_Stub { public function get( $k ) { return array( 'UpdateURI' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme', 'Version' => '1.0.0', 'Name' => 'DIGITALÍSIMO' )[ $k ] ?? ''; } public function get_template() { return 'digitalisimo-theme'; } public function get_stylesheet() { return 'digitalisimo-theme'; } }
function wp_get_theme() { return new Digi_Theme_Stub(); }
function wp_get_themes() { return array( 'digitalisimo-theme' => new Digi_Theme_Stub(), 'digitalisimo-theme-1.0.0' => new Digi_Theme_Stub() ); }
function is_plugin_active( $f ) { return true; }
/** Los siete plugins instalados con su «Update URI» (los hermanos de Elements los recorren). */
function get_plugins() {
	$out = array();
	foreach ( array( 'digitalisimo-seo/digitalisimo-integrations.php' => 'digitalisimo-seo', 'digitalisimo.chatbot/digitalisimo-chatbot.php' => 'digitalisimo-ia-tools', 'digitalisimo-hosting/digitalisimo-hosting.php' => 'digitalisimo-hosting', 'digitalisimo-backups/digitalisimo-backups.php' => 'digitalisimo-backups', 'digitalisimo-tools/digitalisimo-tools.php' => 'digitalisimo-tools', 'digitalisimo-elements/pro-elements.php' => 'digitalisimo-elements', 'digitalisimo-migration/digitalisimo-migration.php' => 'digitalisimo-migration' ) as $file => $slug ) $out[ $file ] = array( 'UpdateURI' => 'https://github.com/danbutanda/digitalisimo-plugin/' . $slug, 'Version' => '1.0.0', 'Name' => $slug );
	return $out;
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['calls'][] = array( 'url' => $url, 'timeout' => $args['timeout'] ?? null, 'ua' => $args['headers']['User-Agent'] ?? '' );
	if ( 'down' === $GLOBALS['net'] ) return array( 'code' => 503, 'body' => '' );
	if ( false !== strpos( $url, 'api.github.com' ) ) return array( 'code' => 200, 'body' => '[]' );
	$plugins = array();
	foreach ( array( 'digitalisimo-seo', 'digitalisimo-ia-tools', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration' ) as $slug ) $plugins[ $slug ] = array( 'version' => '9.0.0', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v9/' . $slug . '-9.0.0.zip' );
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => 'v9', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v9', 'plugins' => $plugins, 'themes' => array( 'digitalisimo-theme' => array( 'version' => '9.0.0', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v9/digitalisimo-theme-9.0.0.zip' ) ) ) ) );
}
function is_wp_error() { return false; }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); } function wp_unslash( $v ) { return $v; } function esc_attr( $s ) { return $s; } function esc_html__( $s ) { return $s; } function __( $s ) { return $s; }
function wp_verify_nonce() { return true; } function wp_create_nonce() { return 'n'; } function wp_nonce_url( $u ) { return $u . '&_wpnonce=n'; } function check_admin_referer() { return true; } function do_action() {}
function wp_get_referer() { return false; } function wp_die( $m = '' ) { throw new RuntimeException( (string) $m ); }
function wp_safe_redirect( $url ) { $GLOBALS['redirect'] = $url; throw new RuntimeException( 'redirect' ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }

$root = dirname( __DIR__ ) . '/';
require $root . 'digitalisimo-seo/includes/class-digitalisimo-updater.php';
require $root . 'digitalisimo-backups/includes/class-digitalisimo-updater.php';
require $root . 'digitalisimo-tools/includes/class-updater.php';
require $root . 'digitalisimo-elements/digitalisimo-updater.php';
require $root . 'digitalisimo-migration/includes/class-updater.php';
require $root . 'digitalisimo-elements/digitalisimo-sibling-updates.php';
require $root . 'digitalisimo-tools/includes/class-theme-updates.php';
require $root . 'digitalisimo-seo/includes/class-seo-release-check.php';
require $root . 'digitalisimo-tools/includes/class-plugin-catalog.php';
require $root . 'digitalisimo-theme/inc/class-updater.php';
foreach ( array( 'digitalisimo-seo/digitalisimo-integrations.php' => 'digitalisimo-seo', 'digitalisimo.chatbot/digitalisimo-chatbot.php' => 'digitalisimo-ia-tools', 'digitalisimo-hosting/digitalisimo-hosting.php' => 'digitalisimo-hosting' ) as $file => $slug ) Digitalisimo_Updater::register( '/plugins/' . $file, '1.0.0', $slug );
function digi_check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, 'FALLA: ' . $message . "\n" ); exit( 1 ); } }
/** Una carga del escritorio: el menú lee update_plugins y update_themes, y cada actualizador pasa por su filtro de lectura. */
function admin_load() {
	$plugins = (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() );
	foreach ( array( 'Digitalisimo_Updater::inject_known', 'Digitalisimo_Backups_Updater::check_known', 'Digitalisimo\Tools\Updater::check_known', 'Digitalisimo_Elements_Updater::check_known', 'Digitalisimo\Migration\Updater::check_known', 'Digitalisimo_SEO_Release_Check::inject_known', 'Digitalisimo_Elements_Sibling_Updates::check_known' ) as $cb ) $plugins = call_user_func( read_cb( $cb ), $plugins );
	$themes = (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array(), 'no_update' => array() );
	// El tema 1.0.1 lee con su check() de siempre (su árbol está fijado a su ZIP): sigue descargando al leer.
	$themes = Digitalisimo_Theme_Updater::check( $themes );
	$themes = call_user_func( read_cb( 'Digitalisimo\Tools\Theme_Updates::check_known' ), $themes );
	return array( $plugins, $themes );
}
/** El filtro de lectura de cada copia; antes de la lectura sin red (2026-11-20) era el mismo de la escritura. */
function read_cb( $cb ) { list( $class, $method ) = explode( '::', $cb ); return method_exists( $class, $method ) ? $cb : $class . '::' . ( 'inject_known' === $method && 'Digitalisimo_SEO_Release_Check' !== $class ? 'inject' : 'check' ); }
/** Peticiones de las copias de los plugins (todas menos las del tema). */
function plugin_calls() { return array_values( array_filter( $GLOBALS['calls'], function ( $c ) { return 'Digitalisimo-Theme' !== $c['ua']; } ) ); }
/** Lo que pasa a los 2 o 10 minutos: vencen las cachés propias de cada actualizador (no las marcas compartidas). */
function expire_own() { foreach ( array_keys( $GLOBALS['t'] ) as $k ) if ( ! in_array( $k, array( 'digitalisimo_plugins_manifest_retry', 'digitalisimo_github_api_retry', 'digitalisimo_plugins_manifest_fresh' ), true ) ) unset( $GLOBALS['t'][ $k ] ); }
$count = function ( $needle ) { return count( array_filter( $GLOBALS['calls'], function ( $c ) use ( $needle ) { return false !== strpos( $c['url'], $needle ); } ) ); };

// 1. GitHub caído: una carga del escritorio pide el índice una vez y la API una vez, entre todos.
admin_load();
$m = $count( 'digitalisimo-plugins.json' ); $a = $count( 'api.github.com' );
digi_check( 1 === $m && 1 === $a, "Con GitHub caído una carga del escritorio debe hacer 1 petición al índice y 1 a la API entre todos; hizo $m y $a." );
digi_check( array() === plugin_calls(), 'Leer update_plugins y update_themes no consulta GitHub en ninguna copia de los plugins (sólo el tema): ' . json_encode( plugin_calls() ) );
digi_check( 120 === ( $GLOBALS['ttl']['digitalisimo_plugins_manifest_retry'] ?? 0 ) && 120 === ( $GLOBALS['ttl']['digitalisimo_github_api_retry'] ?? 0 ), 'El fallo se recuerda dos minutos, el del índice y el de la API.' );
// 5. En una pantalla se espera como mucho 3 s.
digi_check( array( 3 ) === array_values( array_unique( array_column( $GLOBALS['calls'], 'timeout' ) ) ), 'En una pantalla del escritorio las consultas a GitHub esperan como mucho 3 s.' );

// 2. La carga siguiente, con las cachés propias vencidas, no vuelve a esperar a GitHub.
$GLOBALS['calls'] = array(); expire_own();
admin_load();
digi_check( 0 === count( $GLOBALS['calls'] ), 'Dentro de los dos minutos ninguna carga vuelve a consultar GitHub; hizo ' . count( $GLOBALS['calls'] ) . '.' );

// 3. «Buscar actualizaciones» olvida el fallo y consulta de inmediato (GitHub ya responde).
$GLOBALS['net'] = 'up'; $GLOBALS['calls'] = array();
expire_own();
// Lo que hace «Buscar actualizaciones» (sin su redirección): vaciar las cachés y las marcas, y volver a consultar.
$GLOBALS['t']['update_plugins'] = (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() );
Digitalisimo_Backups_Updater::clear(); Digitalisimo_Backups_Updater::refresh();
unset( $GLOBALS['t']['update_plugins'] );
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && 0 === $count( 'api.github.com' ), '«Buscar actualizaciones» vuelve a consultar el índice una sola vez y sin la API.' );
// La carga siguiente lee el índice que «Buscar actualizaciones» dejó en la caché compartida: ninguna copia consulta.
$GLOBALS['calls'] = array();
list( $plugins, $themes ) = admin_load();
digi_check( 0 === count( $GLOBALS['calls'] ), 'Con el índice compartido en caché, ninguna lectura consulta GitHub; hizo ' . count( $GLOBALS['calls'] ) . '.' );
digi_check( '9.0.0' === ( $plugins->response['digitalisimo-seo/digitalisimo-integrations.php']->new_version ?? '' ) && '9.0.0' === ( $plugins->response['digitalisimo-migration/digitalisimo-migration.php']->new_version ?? '' ), 'La lectura sin red ofrece la versión del índice compartido también a los actualizadores que no lo descargaron.' );
digi_check( '9.0.0' === ( $plugins->response['digitalisimo-backups/digitalisimo-backups.php']->new_version ?? '' ) && '9.0.0' === ( $themes->response['digitalisimo-theme']['new_version'] ?? '' ), 'Con GitHub de vuelta se ofrecen las versiones nuevas de los plugins y del tema.' );
digi_check( 240 === ( $GLOBALS['ttl']['digitalisimo_plugins_manifest_fresh'] ?? 0 ), 'El índice descargado queda marcado como reciente cuatro minutos.' );

// 4. Vuelta del cron (cada plugin con su evento de cinco minutos): un solo índice entre todos.
$GLOBALS['cron'] = true; $GLOBALS['calls'] = array();
unset( $GLOBALS['t']['digitalisimo_plugins_manifest_fresh'] ); // la vuelta anterior fue hace cinco minutos
$GLOBALS['t']['update_plugins'] = $plugins;
foreach ( array( 'Digitalisimo_Backups_Updater', 'Digitalisimo\Tools\Updater', 'Digitalisimo_Elements_Updater', 'Digitalisimo\Migration\Updater', 'Digitalisimo_SEO_Release_Check' ) as $class ) $class::refresh();
$m = $count( 'digitalisimo-plugins.json' );
digi_check( 1 === $m && 0 === $count( 'api.github.com' ), "La vuelta del cron descarga un solo índice entre los cinco actualizadores; descargó $m." );
digi_check( array( 10 ) === array_values( array_unique( array_column( $GLOBALS['calls'], 'timeout' ) ) ), 'En el cron las consultas esperan hasta 10 s.' );
$GLOBALS['cron'] = false;

// 4b. Sin WP-Cron: vencen las cachés propias y el índice compartido (diez minutos después). La lectura del escritorio
// no consulta GitHub (con GitHub vivo), no escribe nada y sigue ofreciendo la última versión conocida.
$GLOBALS['net'] = 'up'; $GLOBALS['calls'] = array();
expire_own(); unset( $GLOBALS['t']['digitalisimo_plugins_manifest'], $GLOBALS['t']['digitalisimo_plugins_manifest_fresh'] );
$before = array( array_keys( $GLOBALS['t'] ), $GLOBALS['o'] );
$t0 = $GLOBALS['t'];
$plugins_only = (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() );
foreach ( array( 'Digitalisimo_Updater::inject_known', 'Digitalisimo_Backups_Updater::check_known', 'Digitalisimo\Tools\Updater::check_known', 'Digitalisimo_Elements_Updater::check_known', 'Digitalisimo\Migration\Updater::check_known', 'Digitalisimo_SEO_Release_Check::inject_known', 'Digitalisimo_Elements_Sibling_Updates::check_known' ) as $cb ) $plugins_only = call_user_func( $cb, $plugins_only );
$themes_only = Digitalisimo\Tools\Theme_Updates::check_known( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array(), 'no_update' => array() ) );
digi_check( 0 === count( $GLOBALS['calls'] ), 'Sin cron y con todo vencido, la lectura del escritorio no consulta GitHub; hizo ' . count( $GLOBALS['calls'] ) . '.' );
digi_check( $before === array( array_keys( $GLOBALS['t'] ), $GLOBALS['o'] ) && $t0 === $GLOBALS['t'], 'La lectura sin red no escribe transitorios ni opciones.' );
foreach ( array( 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php', 'digitalisimo-seo/digitalisimo-integrations.php' ) as $file ) {
	digi_check( '9.0.0' === ( $plugins_only->response[ $file ]->new_version ?? '' ), "Sin cron, la lectura sigue ofreciendo la última versión conocida de $file." );
}
// Una lectura sin nada sabido (instalación nueva, sin índice ni última versión) deja las entradas de WordPress como estaban.
$o_saved = $GLOBALS['o']; $GLOBALS['o'] = array(); $GLOBALS['calls'] = array();
$kept = (object) array( 'checked' => array( 'digitalisimo-hosting/digitalisimo-hosting.php' => '1.0.0' ), 'response' => array( 'digitalisimo-hosting/digitalisimo-hosting.php' => (object) array( 'new_version' => '8.0.0' ) ), 'no_update' => array() );
$kept = Digitalisimo_Updater::inject_known( $kept );
digi_check( '8.0.0' === ( $kept->response['digitalisimo-hosting/digitalisimo-hosting.php']->new_version ?? '' ) && 0 === count( $GLOBALS['calls'] ), 'Sin nada sabido, la lectura no quita la actualización que WordPress guardó ni consulta GitHub.' );
$GLOBALS['o'] = $o_saved;
// Cuando WordPress consulta (pre_set_…, en Plugins, Actualizaciones o su cron) sí se descarga, con 3 s en una pantalla.
$GLOBALS['calls'] = array();
Digitalisimo_Updater::inject( (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() ) );
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && array( 3 ) === array_column( $GLOBALS['calls'], 'timeout' ), 'La consulta de WordPress (pre_set) descarga el índice una vez, con 3 s en una pantalla.' );
// Y en el cron (las actualizaciones automáticas leen update_plugins ahí) la lectura también puede descargar, con 10 s.
$GLOBALS['cron'] = true; $GLOBALS['calls'] = array();
expire_own(); unset( $GLOBALS['t']['digitalisimo_plugins_manifest'], $GLOBALS['t']['digitalisimo_plugins_manifest_fresh'] );
Digitalisimo_Backups_Updater::check_known( (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() ) );
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && array( 10 ) === array_column( $GLOBALS['calls'], 'timeout' ), 'En el cron la lectura descarga el índice, con 10 s.' );
$GLOBALS['cron'] = false;

// 4c (revisión M1). Sin WP-Cron, Plugins, Actualizaciones y una actualización en curso (update.php, o AJAX desde Plugins
// o Temas) sí pueden descargar al leer (3 s, con la espera compartida); el resto del escritorio y otro AJAX, no.
$read_all = function () {
	$t = (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() );
	foreach ( array( 'Digitalisimo_Updater::inject_known', 'Digitalisimo_Backups_Updater::check_known', 'Digitalisimo\Tools\Updater::check_known', 'Digitalisimo_Elements_Updater::check_known', 'Digitalisimo\Migration\Updater::check_known', 'Digitalisimo_Elements_Sibling_Updates::check_known' ) as $cb ) $t = call_user_func( read_cb( $cb ), $t );
	call_user_func( read_cb( 'Digitalisimo\Tools\Theme_Updates::check_known' ), (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array(), 'no_update' => array() ) );
	return $t;
};
$case = function ( $page, $ajax_action ) use ( $read_all ) {
	expire_own(); unset( $GLOBALS['t']['digitalisimo_plugins_manifest'], $GLOBALS['t']['digitalisimo_plugins_manifest_fresh'], $GLOBALS['t']['digitalisimo_plugins_manifest_retry'] );
	$GLOBALS['pagenow'] = $page; $GLOBALS['ajax'] = null !== $ajax_action; $_REQUEST = null !== $ajax_action ? array( 'action' => $ajax_action ) : array();
	$GLOBALS['calls'] = array();
	$rt = $read_all();
	$GLOBALS['pagenow'] = ''; $GLOBALS['ajax'] = false; $_REQUEST = array();
	return $rt;
};
foreach ( array( array( 'plugins.php', null ), array( 'update-core.php', null ), array( 'update.php', null ), array( 'admin-ajax.php', 'update-plugin' ), array( 'admin-ajax.php', 'update-theme' ) ) as $c ) {
	$case( $c[0], $c[1] );
	digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && array( 3 ) === array_values( array_unique( array_column( $GLOBALS['calls'], 'timeout' ) ) ), 'Al leer en ' . $c[0] . ( $c[1] ? ' (' . $c[1] . ')' : '' ) . ' se descarga el índice una vez, con 3 s; hizo ' . count( $GLOBALS['calls'] ) . '.' );
}
foreach ( array( array( 'index.php', null ), array( 'edit.php', null ), array( 'admin-ajax.php', 'heartbeat' ) ) as $c ) {
	$case( $c[0], $c[1] );
	digi_check( 0 === count( $GLOBALS['calls'] ), 'Al leer en ' . $c[0] . ( $c[1] ? ' (' . $c[1] . ')' : '' ) . ' no se consulta GitHub; hizo ' . count( $GLOBALS['calls'] ) . '.' );
}
// Con la espera vigente tras un fallo, tampoco Plugins vuelve a esperar a GitHub.
expire_own(); unset( $GLOBALS['t']['digitalisimo_plugins_manifest'] ); $GLOBALS['t']['digitalisimo_plugins_manifest_retry'] = 1; $GLOBALS['t']['digitalisimo_github_api_retry'] = 1;
$GLOBALS['pagenow'] = 'plugins.php'; $GLOBALS['calls'] = array(); $read_all(); $GLOBALS['pagenow'] = '';
digi_check( 0 === count( $GLOBALS['calls'] ), 'En Plugins, con la espera vigente, la lectura no consulta GitHub.' );
unset( $GLOBALS['t']['digitalisimo_plugins_manifest_retry'], $GLOBALS['t']['digitalisimo_github_api_retry'] );

// 4d (revisión M2). La lectura sin red ofrece la versión más alta entre el índice compartido y la última conocida.
$zip = function ( $slug, $v ) { return 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v/' . $slug . '-' . $v . '.zip'; };
$manifest_with = function ( $v ) use ( $zip ) {
	$plugins = array();
	foreach ( array( 'digitalisimo-seo', 'digitalisimo-ia-tools', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration' ) as $slug ) $plugins[ $slug ] = array( 'version' => $v, 'url' => $zip( $slug, $v ) );
	return array( 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases', 'plugins' => $plugins, 'themes' => array( 'digitalisimo-theme' => array( 'version' => $v, 'url' => $zip( 'digitalisimo-theme', $v ) ) ) );
};
$known_all = function ( $v ) use ( $zip ) {
	$GLOBALS['o']['digitalisimo_releases_last_known'] = array();
	foreach ( array( 'digitalisimo-hosting' ) as $slug ) $GLOBALS['o']['digitalisimo_releases_last_known'][ $slug ] = array( 'version' => $v, 'package' => $zip( $slug, $v ), 'url' => 'u' );
	foreach ( array( 'backups', 'tools', 'elements', 'migration' ) as $p ) $GLOBALS['o'][ 'digitalisimo_' . $p . '_last_release' ] = array( 'version' => $v, 'package' => $zip( 'digitalisimo-' . $p, $v ), 'url' => 'u' );
	$GLOBALS['o']['digitalisimo_tools_theme_last_release'] = array( 'version' => $v, 'package' => $zip( 'digitalisimo-theme', $v ), 'url' => 'u', 'requires' => '6.8', 'requires_php' => '7.4' );
};
foreach ( array( array( '9.0.1', '9.0.2' ), array( '9.0.3', '9.0.2' ) ) as $pair ) {
	list( $index_v, $known_v ) = $pair;
	expire_own(); $GLOBALS['t']['digitalisimo_plugins_manifest'] = $manifest_with( $index_v ); $GLOBALS['exp']['digitalisimo_plugins_manifest'] = 0;
	$known_all( $known_v ); $GLOBALS['calls'] = array();
	$rt = $read_all();
	$themes_m2 = Digitalisimo\Tools\Theme_Updates::check_known( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array(), 'no_update' => array() ) );
	$want = version_compare( $index_v, $known_v, '>' ) ? $index_v : $known_v;
	// Digitalisimo_Updater registra sus módulos con la ruta del ensayo (/plugins/…), que los hermanos de Elements quitan
	// por no estar instalada: se lee aparte.
	$shared = Digitalisimo_Updater::inject_known( (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() ) );
	$rt->response['/plugins/digitalisimo-hosting/digitalisimo-hosting.php'] = $shared->response['/plugins/digitalisimo-hosting/digitalisimo-hosting.php'] ?? null;
	foreach ( array( '/plugins/digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' ) as $file ) {
		digi_check( $want === ( $rt->response[ $file ]->new_version ?? '' ), "Índice $index_v y última conocida $known_v: la lectura de $file ofrece $want; ofreció " . ( $rt->response[ $file ]->new_version ?? '-' ) . '.' );
	}
	digi_check( $want === ( $themes_m2->response['digitalisimo-theme-1.0.0']['new_version'] ?? '' ), "Índice $index_v y última conocida $known_v: las copias del tema de Tools ofrecen $want." );
	digi_check( 0 === count( $GLOBALS['calls'] ), 'Sin red al comparar.' );
}

// 4e (revisión S2). Si la escritura lanza una excepción durante una lectura, la lectura sin red no queda puesta para
// el resto de la petición: la consulta siguiente de WordPress (pre_set) sigue descargando.
final class Digi_Throwing_Transient { public function __isset( $k ) { throw new RuntimeException( 'transitorio roto' ); } }
foreach ( array( 'Digitalisimo_Backups_Updater::check_known' => 'Digitalisimo_Backups_Updater::check', 'Digitalisimo_Updater::inject_known' => 'Digitalisimo_Updater::inject' ) as $read => $write ) {
	try { call_user_func( $read, new Digi_Throwing_Transient() ); } catch ( RuntimeException $e ) {}
	expire_own(); unset( $GLOBALS['t']['digitalisimo_plugins_manifest'], $GLOBALS['t']['digitalisimo_plugins_manifest_retry'] ); $GLOBALS['calls'] = array();
	call_user_func( $write, (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() ) );
	digi_check( 1 === $count( 'digitalisimo-plugins.json' ), "Tras una excepción en $read, la escritura vuelve a descargar (la lectura sin red se restauró)." );
}

// 6. Toda copia que descarga el índice o consulta la API usa las mismas marcas.
foreach ( array( 'digitalisimo-seo/includes/class-digitalisimo-updater.php', 'digitalisimo-ia-tools/includes/class-digitalisimo-updater.php', 'digitalisimo-hosting/includes/class-digitalisimo-updater.php', 'digitalisimo-backups/includes/class-digitalisimo-updater.php', 'digitalisimo-tools/includes/class-updater.php', 'digitalisimo-elements/digitalisimo-updater.php', 'digitalisimo-migration/includes/class-updater.php', 'digitalisimo-elements/digitalisimo-sibling-updates.php', 'digitalisimo-tools/includes/class-theme-updates.php', 'digitalisimo-tools/includes/class-plugin-catalog.php', 'digitalisimo-seo/includes/class-plugin-catalog.php', 'digitalisimo-seo/includes/class-seo-release-check.php', 'digitalisimo-seo/includes/class-tools-update-bridge.php', 'digitalisimo-theme/inc/class-updater.php' ) as $file ) {
	$src = (string) file_get_contents( $root . $file );
	if ( false === strpos( $src, 'wp_remote_get' ) ) continue;
	$catalog = false !== strpos( $file, 'class-plugin-catalog.php' );
	digi_check( ( $catalog ? 0 === substr_count( $src, 'get_site_transient( self::MANIFEST_RETRY )' ) : 1 <= substr_count( $src, '! self::$explicit && false !== get_site_transient( self::MANIFEST_RETRY )' ) ) && false !== strpos( $src, 'set_site_transient( self::MANIFEST_RETRY, 1, 2 * MINUTE_IN_SECONDS )' ) && false !== strpos( $src, 'set_site_transient( self::MANIFEST_FRESH, time(), 4 * MINUTE_IN_SECONDS )' ), "$file deja la espera compartida tras un fallo y la respeta sólo en las lecturas del escritorio (los catálogos, nunca)." );
	if ( false !== strpos( $src, 'api.github.com' ) ) digi_check( false !== strpos( $src, 'set_site_transient( self::API_RETRY, 1, 2 * MINUTE_IN_SECONDS )' ) && ( $catalog || false !== strpos( $src, '! self::$explicit && false !== get_site_transient( self::API_RETRY )' ) ), "$file deja y respeta la espera de la API." );
}
// 7. Las cachés de todos los actualizadores se leen en una sola consulta en init de cada pantalla del escritorio (el menú,
// wp-admin/menu.php, las lee antes de admin_init): cada copia del camino de lectura la registra y su lista cubre la caché
// de cada copia.
$primed = array();
function wp_using_ext_object_cache() { return false; }
function is_admin() { return true; } function wp_doing_ajax() { return ! empty( $GLOBALS['ajax'] ); }
function wp_prime_option_caches( $keys ) { $GLOBALS['primed'] = $keys; }
function wp_prime_network_option_caches( $network, $keys ) { $GLOBALS['primed_network'] = $keys; }
$GLOBALS['pagenow'] = 'index.php';
Digitalisimo_Backups_Updater::prime();
foreach ( array( 'Digitalisimo_Updater::CACHE_KEY', 'Digitalisimo_Backups_Updater::CACHE_KEY', 'Digitalisimo\Tools\Updater::CACHE_KEY', 'Digitalisimo_Elements_Updater::CACHE_KEY', 'Digitalisimo\Migration\Updater::CACHE_KEY', 'Digitalisimo_SEO_Release_Check::CACHE_KEY', 'Digitalisimo\Tools\Theme_Updates::CACHE', 'Digitalisimo_Backups_Updater::MANIFEST_CACHE' ) as $const ) {
	$key = constant( $const );
	digi_check( in_array( '_site_transient_' . $key, $GLOBALS['primed'], true ) && in_array( '_site_transient_timeout_' . $key, $GLOBALS['primed'], true ), "La lectura conjunta incluye $key ($const)." );
}
foreach ( array( 'digitalisimo-seo/includes/class-digitalisimo-updater.php', 'digitalisimo-ia-tools/includes/class-digitalisimo-updater.php', 'digitalisimo-hosting/includes/class-digitalisimo-updater.php', 'digitalisimo-backups/includes/class-digitalisimo-updater.php', 'digitalisimo-tools/includes/class-updater.php', 'digitalisimo-elements/digitalisimo-updater.php', 'digitalisimo-migration/includes/class-updater.php', 'digitalisimo-elements/digitalisimo-sibling-updates.php', 'digitalisimo-tools/includes/class-theme-updates.php', 'digitalisimo-seo/includes/class-seo-release-check.php', 'digitalisimo-theme/inc/class-updater.php' ) as $file ) {
	$src = (string) file_get_contents( $root . $file );
	$at = strpos( $src, 'public static function prime()' );
	digi_check( false !== strpos( $src, "add_action( 'init', array( __CLASS__, 'prime' ), 0 );" ) && false !== $at && preg_match_all( "/'(digitalisimo_[a-z_]+)'/", substr( $src, $at, (int) strpos( $src, ') as $key )', $at ) - $at ), $m ), "$file lee todas las cachés en una consulta (init, 0)." );
	if ( ! isset( $GLOBALS['digi_prime_list'] ) ) $GLOBALS['digi_prime_list'] = $m[1];
	digi_check( $m[1] === $GLOBALS['digi_prime_list'], "$file usa la misma lista de cachés que las demás copias." );
	// Las copias de los plugins leen también, en la misma consulta, las últimas versiones conocidas (la lectura sin red las usa).
	if ( 0 === strpos( $file, 'digitalisimo-theme/' ) ) continue;
	$body = substr( $src, $at, (int) strpos( $src, 'if ( is_multisite() )', $at ) - $at );
	digi_check( preg_match( "/foreach \\( array\\( ('digitalisimo_[a-z_]+last_[a-z_]+'(?:, )?)+ \\) as \\\$key \\) \\\$keys\\[\\] = \\\$key;/", $body, $lk ), "$file lee las últimas versiones conocidas en la misma consulta." );
	if ( ! isset( $GLOBALS['digi_known_list'] ) ) $GLOBALS['digi_known_list'] = $lk[0];
	digi_check( $lk[0] === $GLOBALS['digi_known_list'], "$file usa la misma lista de últimas versiones conocidas." );
}
foreach ( array( 'Digitalisimo_Updater', 'Digitalisimo_Backups_Updater', 'Digitalisimo\Tools\Updater', 'Digitalisimo_Elements_Updater', 'Digitalisimo\Migration\Updater', 'Digitalisimo_SEO_Release_Check', 'Digitalisimo\Tools\Theme_Updates', 'Digitalisimo_Tools_Update_Bridge' ) as $class ) {
	if ( 'Digitalisimo_Tools_Update_Bridge' === $class && ! class_exists( $class ) ) require $root . 'digitalisimo-seo/includes/class-tools-update-bridge.php';
	digi_check( in_array( constant( $class . '::LAST_KNOWN' ), $GLOBALS['primed'], true ), "La lectura conjunta incluye la última versión conocida de $class." );
}
// 8. La espera dura dos minutos y el índice reciente cuatro: a los 119 s ninguna lectura consulta; a los 121 s, una.
$GLOBALS['net'] = 'down'; $GLOBALS['calls'] = array(); $GLOBALS['t'] = array();
admin_load();
$GLOBALS['now'] += 119; expire_own(); $GLOBALS['calls'] = array(); admin_load();
digi_check( 0 === count( $GLOBALS['calls'] ), 'A los 119 s del fallo ninguna lectura vuelve a consultar.' );
$GLOBALS['now'] += 2; expire_own(); admin_load();
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && 1 === $count( 'api.github.com' ), 'A los 121 s se vuelve a consultar una vez el índice y una la API.' );
// Dos vueltas del cron separadas cinco minutos: cada una descarga el índice una vez.
$GLOBALS['net'] = 'up'; $GLOBALS['cron'] = true; $GLOBALS['t'] = array( 'update_plugins' => $plugins );
foreach ( array( 0, 300 ) as $step ) {
	$GLOBALS['now'] += $step; $GLOBALS['calls'] = array();
	foreach ( array( 'Digitalisimo_Backups_Updater', 'Digitalisimo\Tools\Updater', 'Digitalisimo_Elements_Updater', 'Digitalisimo\Migration\Updater', 'Digitalisimo_SEO_Release_Check' ) as $class ) $class::refresh();
	digi_check( 1 === $count( 'digitalisimo-plugins.json' ), 'Cada vuelta del cron (la segunda, cinco minutos después) descarga el índice una vez; descargó ' . $count( 'digitalisimo-plugins.json' ) . '.' );
}
$GLOBALS['cron'] = false;

// 9. En una red la lectura conjunta va a sitemeta; quien no ve actualizaciones, admin-post.php y async-upload.php no la pagan.
$GLOBALS['ms'] = true; unset( $GLOBALS['primed_network'] );
Digitalisimo\Tools\Updater::prime();
digi_check( isset( $GLOBALS['primed_network'] ) && $GLOBALS['primed_network'] === $GLOBALS['primed'], 'En una red las cachés se leen de sitemeta con la misma lista.' );
$GLOBALS['ms'] = false;
foreach ( array( 'admin-post.php', 'async-upload.php' ) as $page ) { $GLOBALS['pagenow'] = $page; $GLOBALS['primed'] = null; Digitalisimo_Backups_Updater::prime(); digi_check( null === $GLOBALS['primed'], "Sin lectura conjunta en $page." ); }
$GLOBALS['pagenow'] = 'index.php'; $GLOBALS['caps'] = false; $GLOBALS['primed'] = null;
Digitalisimo_Backups_Updater::prime();
digi_check( null === $GLOBALS['primed'], 'Sin lectura conjunta para quien no puede actualizar plugins ni temas.' );
$GLOBALS['caps'] = true;

// 10. Las acciones explícitas esperan 10 s y no respetan la espera: «Ver detalles», «Buscar actualizaciones» (que avisa
// si GitHub no respondió), el catálogo con «Instalar todos» y la instalación del tema desde Tools.
$GLOBALS['net'] = 'down'; $GLOBALS['t'] = array( 'digitalisimo_plugins_manifest_retry' => 1, 'digitalisimo_github_api_retry' => 1 ); $GLOBALS['exp'] = array(); $GLOBALS['calls'] = array();
Digitalisimo_Backups_Updater::info( false, 'plugin_information', (object) array( 'slug' => 'digitalisimo-backups' ) );
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && 1 === $count( 'api.github.com' ) && array( 10 ) === array_values( array_unique( array_column( $GLOBALS['calls'], 'timeout' ) ) ), '«Ver detalles» consulta aunque haya espera, con 10 s.' );
$GLOBALS['calls'] = array();
$GLOBALS['t']['update_plugins'] = (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() );
try { Digitalisimo_Updater::force_check(); } catch ( RuntimeException $e ) {}
digi_check( $count( 'digitalisimo-plugins.json' ) >= 1 && false !== strpos( (string) ( $GLOBALS['redirect'] ?? '' ), 'digitalisimo-checked=failed' ) && array( 10 ) === array_values( array_unique( array_column( $GLOBALS['calls'], 'timeout' ) ) ), '«Buscar actualizaciones» consulta con 10 s y, si GitHub no responde, lo dice: ' . ( $GLOBALS['redirect'] ?? '' ) );
$_GET['digitalisimo-checked'] = 'failed'; ob_start(); Digitalisimo_Updater::notice(); $notice = ob_get_clean(); unset( $_GET['digitalisimo-checked'] );
digi_check( false !== strpos( $notice, 'no se pudo consultar GitHub' ), 'El aviso dice que no se pudo consultar, no «al día».' );
$GLOBALS['net'] = 'up'; $GLOBALS['t'] = array( 'digitalisimo_plugins_manifest_retry' => 1, 'digitalisimo_github_api_retry' => 1 ); $GLOBALS['calls'] = array();
ob_start(); Digitalisimo\Tools\Plugin_Catalog::page(); $html = ob_get_clean();
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && array( 12 ) === array_column( $GLOBALS['calls'], 'timeout' ) && false !== strpos( $html, Digitalisimo\Tools\Plugin_Catalog::INSTALL_ALL ) && false !== strpos( $html, '9.0.0' ), 'El catálogo consulta aunque haya espera (12 s) y ofrece «Instalar todos» con las versiones publicadas.' );
$GLOBALS['t'] = array( 'digitalisimo_plugins_manifest_retry' => 1, 'digitalisimo_github_api_retry' => 1 ); $GLOBALS['calls'] = array();
Digitalisimo\Tools\Theme_Updates::explicit();
$release = Digitalisimo\Tools\Theme_Updates::release();
digi_check( '9.0.0' === ( $release['version'] ?? '' ) && ! empty( $release['package'] ) && array( 10 ) === array_column( $GLOBALS['calls'], 'timeout' ), 'Instalar o actualizar el tema desde Tools consulta aunque haya espera, con 10 s.' );
// El actualizador del tema solo, en una pantalla: espera como mucho 3 s y, si GitHub falla, deja las dos marcas.
$GLOBALS['net'] = 'down'; $GLOBALS['t'] = array(); $GLOBALS['calls'] = array();
Digitalisimo_Theme_Updater::check( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array(), 'no_update' => array() ) );
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && 1 === $count( 'api.github.com' ) && array( 3 ) === array_values( array_unique( array_column( $GLOBALS['calls'], 'timeout' ) ) ) && isset( $GLOBALS['t']['digitalisimo_plugins_manifest_retry'], $GLOBALS['t']['digitalisimo_github_api_retry'] ), 'El tema, en una pantalla: 3 s y deja la espera tras el fallo.' );
$GLOBALS['net'] = 'up';
// El actualizador del tema: con espera vigente su lectura pasiva no consulta; «Buscar actualizaciones de nuevo» sí, con 10 s.
$GLOBALS['t'] = array( 'digitalisimo_plugins_manifest_retry' => 1, 'digitalisimo_github_api_retry' => 1 ); $GLOBALS['calls'] = array();
Digitalisimo_Theme_Updater::check( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array(), 'no_update' => array() ) );
digi_check( 0 === count( $GLOBALS['calls'] ), 'Con la espera vigente, la lectura del tema no consulta GitHub.' );
$_GET['force-check'] = '1'; Digitalisimo_Theme_Updater::native_force_check(); unset( $_GET['force-check'] );
$themes = Digitalisimo_Theme_Updater::check( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array(), 'no_update' => array() ) );
digi_check( 1 === $count( 'digitalisimo-plugins.json' ) && array( 10 ) === array_values( array_unique( array_column( $GLOBALS['calls'], 'timeout' ) ) ) && '9.0.0' === ( $themes->response['digitalisimo-theme']['new_version'] ?? '' ) && 240 === ( $GLOBALS['ttl']['digitalisimo_plugins_manifest_fresh'] ?? 0 ), '«Buscar de nuevo» olvida la espera y consulta el índice del tema con 10 s.' );
$src = (string) file_get_contents( $root . 'digitalisimo-tools/includes/class-theme-catalog.php' );
foreach ( array( 'public static function render(', 'public static function install()', 'public static function update()' ) as $method ) {
	$body = substr( $src, (int) strpos( $src, $method ), 1200 );
	digi_check( false !== strpos( $body, 'Theme_Updates::explicit();' ) && strpos( $body, 'Theme_Updates::explicit();' ) < strpos( $body, 'Theme_Updates::release()' ), "El catálogo del tema ($method) consulta como acción explícita." );
}
echo "Actualizadores: con GitHub caído 1+1 peticiones por carga y ninguna en dos minutos; el cron baja un solo índice por vuelta; sus cachés se leen en una consulta; las acciones explícitas esperan 10 s y no respetan la espera.\n";
