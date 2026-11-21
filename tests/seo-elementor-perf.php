<?php
/**
 * Trabajo de DIGITALÍSIMO SEO al editar con el constructor (auditoría del escritorio, segunda vuelta, 2026-11-20), sin
 * WordPress:
 *   1. con las precargas y el blindaje de fuentes apagados (por defecto), cambiar `_elementor_*`, guardar una plantilla o
 *      vaciar la caché del constructor no programa el recálculo de fuentes (que inventaría las fuentes de todo el sitio);
 *      con cualquiera de los dos encendido, sí, una vez. Cambiar los ajustes lo sigue programando siempre;
 *   2. el recorrido de todas las páginas del Hero es un solo evento con cursor (BATCH páginas por vuelta), no un evento
 *      de cron por página; termina, borra su cursor y vuelve a empezar si la generación cambia a mitad;
 *   3. con el CSS crítico del Hero apagado, guardar o editar una página no programa nada.
 */
define( 'ABSPATH', __DIR__ );
function fail( $m ) { $GLOBALS['failures'][] = $m; fwrite( STDERR, "FALLA: $m\n" ); }
$GLOBALS['opts'] = array(); $GLOBALS['events'] = array(); $GLOBALS['settings'] = array();
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $hook, $args = array() ) { foreach ( $GLOBALS['events'] as $e ) if ( $e[0] === $hook && $e[1] === $args ) return 1; return false; }
function wp_schedule_single_event( $time, $hook, $args = array() ) { $GLOBALS['events'][] = array( $hook, $args ); return true; }
function wp_is_post_revision() { return false; }
function absint( $v ) { return abs( (int) $v ); }
function get_post_type( $id ) { return $GLOBALS['types'][ $id ] ?? 'page'; }
function get_post( $id ) { return null; }
// Lo que usaba el recorrido anterior (todas las páginas de una vez): así la prueba informa en vez de romperse.
function get_posts( $args ) { return $GLOBALS['wpdb']->pages; }
function get_current_blog_id() { return 1; }
function is_multisite() { return false; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $k, $d = null ) { return $GLOBALS['settings'][ $k ] ?? $d; } }
class Digitalisimo_Integrations_Settings { const OPTION = 'digitalisimo_integrations_options'; }
class Digitalisimo_Integrations_Performance_Cache { const GENERATION = 'digitalisimo_performance_cache_generation'; public static function delete() {} public static function get() { return null; } public static function set() {} }
/** $wpdb con páginas del constructor publicadas: responde a la consulta del recorrido (ID > cursor, en orden, LIMIT). */
class Digi_Test_WPDB {
	public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta'; public $pages = array(); public $queries = 0;
	public function prepare( $sql, ...$args ) { foreach ( $args as $a ) $sql = preg_replace( '/%d/', (string) (int) $a, $sql, 1 ); return $sql; }
	public function get_col( $sql ) {
		$this->queries++;
		if ( ! preg_match( '/p\.ID > (\d+) ORDER BY p\.ID ASC LIMIT (\d+)/', $sql, $m ) ) return array();
		return array_slice( array_values( array_filter( $this->pages, function( $id ) use ( $m ) { return $id > (int) $m[1]; } ) ), 0, (int) $m[2] );
	}
}
$GLOBALS['wpdb'] = new Digi_Test_WPDB();
$dir = dirname( __DIR__ ) . '/digitalisimo-seo/includes/performance/';
require $dir . 'class-performance-font-guard.php';
require $dir . 'class-performance-preloads.php';
require $dir . 'class-performance-hero.php';
$P = 'Digitalisimo_Integrations_Performance_Preloads'; $G = 'Digitalisimo_Integrations_Performance_Font_Guard'; $H = 'Digitalisimo_Integrations_Performance_Hero';
$count = function( $hook ) { return count( array_filter( $GLOBALS['events'], function( $e ) use ( $hook ) { return $e[0] === $hook; } ) ); };

// 1. Fuentes.
foreach ( array( 'apagadas' => array( 'off', 'off', 0 ), 'precargas' => array( 'auto', 'off', 1 ), 'blindaje' => array( 'off', 'strict', 1 ) ) as $case => $c ) {
	$GLOBALS['events'] = array(); $GLOBALS['settings'] = array( 'perf_preload_mode' => $c[0], 'perf_font_guard_mode' => $c[1] );
	foreach ( array( '_elementor_data', '_elementor_page_settings', '_elementor_edit_mode' ) as $key ) { $P::schedule_for_meta( 1, 9, $key, '' ); $G::invalidate_on_meta( 1, 9, $key, '' ); }
	$G::invalidate_on_library_save(); $G::schedule_on_local_fonts();
	if ( method_exists( $P, 'schedule_for_content' ) ) $P::schedule_for_content(); else $P::schedule();
	if ( $c[2] !== $count( $P::CRON ) ) fail( "Fuentes $case: editar en el constructor programa " . $count( $P::CRON ) . " recálculos (esperado {$c[2]})." );
	$GLOBALS['events'] = array(); $P::schedule();
	if ( 1 !== $count( $P::CRON ) ) fail( "Fuentes $case: cambiar los ajustes debe programar el recálculo." );
}
$P::schedule_for_meta( 1, 9, '_thumbnail_id', '' );

// 2. Hero: un solo evento con cursor.
$GLOBALS['settings'] = array( 'perf_hero_critical' => 1 );
$GLOBALS['wpdb']->pages = range( 101, 145 ); // 45 páginas del constructor
$GLOBALS['events'] = array(); $GLOBALS['opts'] = array();
$H::schedule_all();
if ( 1 !== $count( $H::CRON_ALL ) ) fail( 'schedule_all() debe programar un evento del recorrido.' );
$rounds = 0; $done = array();
while ( $count( $H::CRON_ALL ) && $rounds < 10 ) {
	$GLOBALS['events'] = array_values( array_filter( $GLOBALS['events'], function( $e ) use ( $H ) { return $e[0] !== $H::CRON_ALL; } ) ); // el cron lo consume
	$before = $GLOBALS['opts'];
	$H::schedule_pages();
	foreach ( $GLOBALS['opts'] as $k => $v ) if ( preg_match( '/^digitalisimo_performance_hero_(\d+)$/', $k, $m ) && ! isset( $before[ $k ] ) ) $done[] = (int) $m[1];
	$rounds++;
	if ( $count( $H::CRON ) ) { fail( 'El recorrido programa ' . $count( $H::CRON ) . ' eventos por página (debe recalcular en su propio evento).' ); break; }
}
sort( $done );
if ( 3 !== $rounds || range( 101, 145 ) !== $done ) fail( "45 páginas en lotes de 20: 3 vueltas y todas calculadas (vueltas $rounds, calculadas " . count( $done ) . ').' );
if ( false !== get_option( 'digitalisimo_performance_hero_cursor', false ) ) fail( 'Al terminar, el cursor se borra.' );
// La generación cambia a mitad: el recorrido vuelve a empezar desde la primera página.
if ( defined( "$H::CURSOR" ) ) {
	$GLOBALS['opts'] = array( $H::GENERATION => 5, $H::CURSOR => array( 'generation' => 4, 'after' => 130 ) ); $GLOBALS['events'] = array();
	$H::schedule_pages();
	if ( ! isset( $GLOBALS['opts']['digitalisimo_performance_hero_101'] ) || isset( $GLOBALS['opts']['digitalisimo_performance_hero_131'] ) ) fail( 'Con otra generación, el recorrido empieza de nuevo.' );
}

// 3. Hero apagado.
$GLOBALS['settings'] = array( 'perf_hero_critical' => 0 ); $GLOBALS['events'] = array(); $GLOBALS['opts'] = array();
$H::meta_changed( 1, 120, '_elementor_data', '' ); $H::schedule( 120 ); $H::schedule_all();
if ( $GLOBALS['events'] ) fail( 'Con el CSS crítico del Hero apagado, editar una página programa ' . count( $GLOBALS['events'] ) . ' eventos.' );

if ( ! empty( $GLOBALS['failures'] ) ) { fwrite( STDERR, count( $GLOBALS['failures'] ) . " fallas\n" ); exit( 1 ); }
echo "OK: el constructor no programa recálculos de fuentes apagadas y el Hero recorre las páginas con un solo evento\n";
