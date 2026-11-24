<?php
/**
 * Costo fijo de DIGITALÍSIMO SEO en cada petición (auditoría del escritorio, segunda vuelta, 2026-11-20), sin WordPress:
 *   1. arrancar SEO (incluir su archivo principal y su plugins_loaded) ya no compila sus 70 clases (1,3 MB) en cada
 *      petición: en el front, en el escritorio y en una red sólo se cargan las que el arranque usa. El importador de
 *      Rank Math, las auditorías, el inspector del front, el schema, las fuentes, Enlaces o las abilities se cargan
 *      cuando WordPress dispara uno de sus ganchos;
 *   2. los ganchos que registra el arranque son los mismos que registraba el init() de cada módulo (nombre, método,
 *      prioridad, argumentos y orden), en el escritorio y en el front, en un sitio y en una red, con y sin la API de
 *      abilities: `includes/lazy-hooks.php` está al día (`php tests/seo-fixed-cost.php --write` lo regenera);
 *   3. cada clase propia de SEO está en el mapa del autoloader y su archivo la declara;
 *   4. el puente de Tools no lee la cabecera de Tools (get_file_data) con Tools activo, ni en el front con Tools
 *      inactivo; en el escritorio con Tools inactivo, sí.
 * Cada contexto corre en un proceso propio (`--boot=<contexto>`): una clase cargada no se puede descargar.
 */
$root = dirname( __DIR__ );
$seo  = $root . '/digitalisimo-seo';

/** Módulos cuyos ganchos se registran sin cargar su clase: su init() sólo llama add_action/add_filter/add_shortcode. */
const DIGI_LAZY = array(
	'Digitalisimo_Integrations_Settings', 'Digitalisimo_Integrations_Performance_Manager', 'Digitalisimo_Integrations_Performance_CSS',
	'Digitalisimo_Integrations_Performance_CSS_Audit', 'Digitalisimo_Integrations_Performance_Hero', 'Digitalisimo_Integrations_Performance_Fonts',
	'Digitalisimo_Integrations_Performance_Font_Guard', 'Digitalisimo_Integrations_Performance_Font_Inline', 'Digitalisimo_Integrations_Performance_Images',
	'Digitalisimo_Integrations_Media_Repair', 'Digitalisimo_Integrations_Performance_JavaScript', 'Digitalisimo_Integrations_Quality_Audit',
	'Digitalisimo_Integrations_LLMS', 'Digitalisimo_Integrations_Favicon', 'Digitalisimo_Integrations_Performance_Migration',
	'Digitalisimo_Integrations_Performance_Tracking', 'Digitalisimo_Integrations_Performance_Cache', 'Digitalisimo_Integrations_Performance_Console',
	'Digitalisimo_Integrations_Asset_Diagnostics', 'Digitalisimo_Integrations_Performance_Measurements', 'Digitalisimo_Integrations_Schema_Graph',
	'Digitalisimo_Integrations_Schema_Audit', 'Digitalisimo_Integrations_Sitemap', 'Digitalisimo_Integrations_Redirections',
	'Digitalisimo_Integrations_404_Monitor', 'Digitalisimo_Integrations_Verification', 'Digitalisimo_Integrations_RankMath_Import',
	'Digitalisimo_Integrations_Links', 'Digitalisimo_Integrations_Content_Analysis', 'Digitalisimo_Integrations_SEO_Bulk',
	'Digitalisimo_Integrations_SEO_Feed', 'Digitalisimo_Integrations_Video', 'Digitalisimo_Integrations_URL_Bases',
	'Digitalisimo_Integrations_Product_Identifiers', 'Digitalisimo_Integrations_SEO_Abilities', 'Digitalisimo_Integrations_SEO_Abilities_Write',
	'Digitalisimo_Integrations_Brand_Check',
	'Digitalisimo_Integrations_Robots', 'Digitalisimo_Integrations_SEO_AI_Crawler_Tools', 'Digitalisimo_Integrations_SEO_AI',
	'Digitalisimo_Integrations_SEO_AI_Crawler_Verifier', 'Digitalisimo_Integrations_SEO_AI_Audit_Tools', 'Digitalisimo_Integrations_SEO_AI_LLMS_Tools',
	'Digitalisimo_Integrations_SEO_AI_IndexNow_Tools', 'Digitalisimo_Integrations_SEO_AI_Referral_Tools', 'Digitalisimo_Integrations_SEO_AI_Entities_Tools',
	'Digitalisimo_Integrations_Editorial', 'Digitalisimo_Admin_UI',
);
/** Módulos cuyo init() empieza por `if ( ! function_exists( $fn ) ) return;`. */
const DIGI_REQUIRES = array( 'Digitalisimo_Integrations_SEO_Abilities' => 'wp_register_ability', 'Digitalisimo_Integrations_SEO_Abilities_Write' => 'wp_register_ability' );
/** Contextos: escritorio (a) o front (f), sitio (s) o red (m), con o sin abilities, con o sin IA Tools, con o sin Tools activo. */
const DIGI_CONTEXTS = array(
	'as' => array( 'admin' => true, 'multi' => false, 'abilities' => true, 'ia' => true, 'tools' => true ),
	'am' => array( 'admin' => true, 'multi' => true, 'abilities' => false, 'ia' => false, 'tools' => false ),
	'fs' => array( 'admin' => false, 'multi' => false, 'abilities' => false, 'ia' => true, 'tools' => false ),
	'fm' => array( 'admin' => false, 'multi' => true, 'abilities' => true, 'ia' => false, 'tools' => true ),
);
/**
 * Lo único que el arranque puede cargar (además de lo compartido: diseño, núcleo, catálogo y actualizador): ajustes y
 * su resolución, la ruta privada, los módulos con init() condicional (SEO, SEO Suite, precargas y su caché, chequeo de
 * versiones),
 * el campo de medios y el puente de Tools sólo sin Tools activo.
 */
const DIGI_BOOT_ALLOWED = array( 'class-settings.php', 'class-seo-resolver.php', 'class-hide-login.php', 'class-seo.php', 'class-seo-suite.php', 'class-performance-preloads.php', 'class-seo-release-check.php', 'class-media-field.php', 'class-tools-update-bridge.php', 'class-performance-cache.php', 'lazy-hooks.php' );

$opt = getopt( '', array( 'boot:', 'write' ) );
if ( isset( $opt['boot'] ) ) { digi_child( $seo, (string) $opt['boot'] ); exit( 0 ); }

/* ------------------------------------------------------------------------------------------------------------------ */
$failures = array();
function fail( $m ) { $GLOBALS['failures'][] = $m; fwrite( STDERR, "FALLA: $m\n" ); }
$runs = array();
foreach ( array_keys( DIGI_CONTEXTS ) as $ctx ) {
	$cmd = escapeshellarg( PHP_BINARY ) . ' -d error_reporting=E_ALL -d display_errors=stderr ' . escapeshellarg( __FILE__ ) . ' --boot=' . $ctx . ' 2>&1';
	$out = (string) shell_exec( $cmd );
	$json = null;
	if ( preg_match( '/^DIGI-JSON (.*)$/m', $out, $m ) ) $json = json_decode( $m[1], true );
	if ( ! is_array( $json ) ) { fail( "El arranque en el contexto $ctx no terminó: " . substr( trim( $out ), 0, 1500 ) ); continue; }
	$noise = trim( preg_replace( '/^DIGI-(JSON|COST) .*$/m', '', $out ) );
	if ( preg_match( '/^DIGI-COST (.*)$/m', $out, $m ) ) $json['cost'] = json_decode( $m[1], true );
	if ( '' !== $noise ) fail( "Avisos de PHP al arrancar en $ctx: " . substr( $noise, 0, 800 ) );
	$runs[ $ctx ] = $json;
}

// 1. Lo que el arranque compila.
foreach ( $runs as $ctx => $run ) {
	$extra = array_values( array_diff( $run['loaded'], DIGI_BOOT_ALLOWED ) );
	if ( $extra ) fail( "Arrancar SEO en $ctx carga " . count( $run['loaded'] ) . ' archivos propios (' . $run['loaded_kb'] . ' KB); sobran: ' . implode( ', ', $extra ) );
	foreach ( array( 'class-rankmath-import.php', 'class-quality-audit.php', 'class-schema-graph.php', 'class-seo-front-inspector.php', 'class-performance-font-guard.php' ) as $heavy ) {
		if ( in_array( $heavy, $run['loaded'], true ) ) fail( "En $ctx se compila $heavy sin que nada lo use." );
	}
	echo "arranque $ctx: " . count( $run['loaded'] ) . ' archivos propios de SEO, ' . $run['loaded_kb'] . ' KB · ' . json_encode( $run['cost'] ?? null ) . "\n";
}

// 2. Los mismos ganchos que init(), y el manifiesto al día.
$lazy_file = $seo . '/includes/lazy-hooks.php';
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ );
$current = is_file( $lazy_file ) ? require $lazy_file : null;
$expected = array();
foreach ( DIGI_LAZY as $class ) {
	// Orden: se mezclan las secuencias de cada contexto conservando el orden relativo de cada una (un gancho sólo de red
	// queda donde init() lo registra, no al final).
	$per = array(); $order = array();
	foreach ( $runs as $ctx => $run ) {
		$prev = null;
		foreach ( (array) ( $run['init'][ $class ] ?? array() ) as $hook ) {
			$key = json_encode( array_slice( $hook, 0, 5 ) );
			if ( ! isset( $per[ $key ] ) ) {
				$at = null === $prev ? 0 : array_search( $prev, $order, true ) + 1;
				array_splice( $order, $at, 0, array( $key ) );
			}
			$per[ $key ][ $ctx ] = true;
			$prev = $key;
		}
	}
	$hooks = array();
	foreach ( $order as $key ) {
		$where = $per[ $key ];
		$admin = array_unique( array_map( function( $c ) { return $c[0]; }, array_keys( $where ) ) );
		$net   = array_unique( array_map( function( $c ) { return $c[1]; }, array_keys( $where ) ) );
		$all   = array();
		foreach ( $admin as $a ) foreach ( $net as $n ) $all[] = $a . $n;
		// Sin abilities, sus módulos no registran nada: sólo cuentan los contextos con la API.
		$seen = isset( DIGI_REQUIRES[ $class ] ) ? array_keys( array_filter( DIGI_CONTEXTS, function( $c ) { return $c['abilities']; } ) ) : array_keys( DIGI_CONTEXTS );
		$all = array_values( array_intersect( $all, $seen ) );
		sort( $all ); $have = array_keys( $where ); sort( $have );
		if ( $all !== $have ) { fail( "$class registra $key sólo en " . implode( ',', $have ) . ': no es una condición de escritorio/front y sitio/red.' ); continue; }
		$when = ( 1 === count( $admin ) ? reset( $admin ) : '*' ) . ( 1 === count( $net ) ? reset( $net ) : '*' );
		$hook = json_decode( $key, true );
		if ( 0 !== strpos( $hook[2], $class . '::' ) ) { fail( "$class registra un gancho de otra clase ({$hook[2]}): no puede diferirse." ); continue; }
		$hook[2] = substr( $hook[2], strlen( $class ) + 2 );
		$hooks[] = array_merge( $hook, array( $when ) );
	}
	$expected[ $class ] = isset( DIGI_REQUIRES[ $class ] ) ? array( 'requires' => DIGI_REQUIRES[ $class ], 'hooks' => $hooks ) : array( 'hooks' => $hooks );
}
if ( isset( $opt['write'] ) ) {
	$php = "<?php\n/**\n * Ganchos de los módulos de DIGITALÍSIMO SEO que se registran sin cargar su clase (digitalisimo_integrations_lazy()).\n * Generado de su init() con `php tests/seo-fixed-cost.php --write`: no se edita a mano. Cada gancho es\n * [tipo (a acción, f filtro, s shortcode), nombre, método, prioridad, argumentos, contexto]; el contexto es escritorio\n * (a) o front (f) y sitio (s) o red (m), con * para cualquiera.\n */\ndefined( 'ABSPATH' ) || exit;\n\nreturn array(\n";
	foreach ( $expected as $class => $entry ) {
		$php .= "\t'$class' => array(" . ( isset( $entry['requires'] ) ? " 'requires' => '" . $entry['requires'] . "'," : '' ) . " 'hooks' => array(\n";
		foreach ( $entry['hooks'] as $h ) $php .= "\t\tarray( " . implode( ', ', array_map( function( $v ) { return is_int( $v ) ? ( PHP_INT_MAX === $v ? 'PHP_INT_MAX' : ( PHP_INT_MIN === $v ? 'PHP_INT_MIN' : (string) $v ) ) : var_export( $v, true ); }, $h ) ) . " ),\n";
		$php .= "\t) ),\n";
	}
	$php .= ");\n";
	file_put_contents( $lazy_file, $php );
	echo "Escrito $lazy_file\n";
	$current = $expected;
}
// Una prioridad PHP_INT_MAX se escribe con la constante: el literal de 64 bits sería un float en un PHP de 32 bits.
if ( is_file( $lazy_file ) && preg_match( '/\b(9223372036854775807|-9223372036854775808)\b/', (string) file_get_contents( $lazy_file ) ) ) fail( 'includes/lazy-hooks.php escribe PHP_INT_MAX como número.' );
// Ningún gancho diferido en shutdown: cargaría la clase al final de cada petición, también la que actualiza SEO (con sus
// archivos fuera de su sitio hasta que WordPress restaura la copia, en shutdown).
foreach ( $expected as $class => $entry ) foreach ( $entry['hooks'] as $h ) if ( 'shutdown' === $h[1] ) fail( "$class registra {$h[2]} en shutdown en cada petición." );
if ( $current !== $expected ) fail( 'includes/lazy-hooks.php no corresponde a los init() de los módulos: regenéralo con php tests/seo-fixed-cost.php --write.' );
foreach ( $runs as $ctx => $run ) foreach ( (array) $run['parity'] as $class => $diff ) fail( "En $ctx, $class registra con el arranque otra cosa que con init(): $diff" );

// 3. Mapa del autoloader completo.
foreach ( $runs as $run ) { foreach ( (array) $run['map_errors'] as $e ) fail( $e ); break; }

// 4. Cabecera de Tools.
foreach ( $runs as $ctx => $run ) {
	$tools = DIGI_CONTEXTS[ $ctx ]['tools']; $admin = DIGI_CONTEXTS[ $ctx ]['admin'];
	if ( ( $tools || ! $admin ) && $run['bridge_header_reads'] ) fail( "El puente lee la cabecera de Tools en $ctx (" . ( $tools ? 'Tools activo' : 'front' ) . ').' );
	if ( ! $tools && $admin && ! $run['bridge_header_reads'] ) fail( "En $ctx (escritorio, Tools inactivo) el puente debe seguir leyendo la versión instalada de Tools." );
}

if ( $failures ) { fwrite( STDERR, count( $failures ) . " fallas\n" ); exit( 1 ); }
echo "OK: SEO arranca sin compilar sus módulos y con los mismos ganchos\n";
exit( 0 );

/* ------------------------------------------------------------------------------------------------------------------ */
/** Un contexto: stubs de WordPress, incluir el archivo principal, plugins_loaded y comparar cada módulo diferido. */
function digi_child( $seo, $ctx ) {
	$c = DIGI_CONTEXTS[ $ctx ];
	$GLOBALS['digi_ctx'] = $c;
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'WP_PLUGIN_DIR', dirname( $seo ) );
	foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400, 'WEEK_IN_SECONDS' => 604800 ) as $k => $v ) define( $k, $v );
	if ( $c['tools'] ) { define( 'DIGITALISIMO_TOOLS_VERSION', '1.0.42' ); define( 'DIGITALISIMO_TOOLS_FILE', WP_PLUGIN_DIR . '/digitalisimo-tools/digitalisimo-tools.php' ); }
	if ( $c['abilities'] ) { function wp_register_ability() { return true; } }
	if ( $c['ia'] ) { eval( 'class Digitalisimo_AI { public static function get( $k = null ) { return null; } }' ); }
	$GLOBALS['digi_hooks'] = array(); $GLOBALS['digi_header_reads'] = 0;
	function digi_rec( $kind, $hook, $cb, $prio = 10, $args = 1 ) {
		$method = is_array( $cb ) ? ( is_string( $cb[0] ) ? $cb[0] : get_class( $cb[0] ) ) . '::' . $cb[1] : ( is_string( $cb ) ? $cb : 'closure' );
		$GLOBALS['digi_hooks'][] = array( $kind, (string) $hook, $method, (int) $prio, (int) $args );
		return true;
	}
	function add_action( $h, $cb, $p = 10, $a = 1 ) { return digi_rec( 'a', $h, $cb, $p, $a ); }
	function add_filter( $h, $cb, $p = 10, $a = 1 ) { return digi_rec( 'f', $h, $cb, $p, $a ); }
	function add_shortcode( $h, $cb ) { return digi_rec( 's', $h, $cb ); }
	function remove_action() { return true; } function remove_filter() { return true; }
	function register_activation_hook() {} function register_deactivation_hook() {}
	function is_admin() { return $GLOBALS['digi_ctx']['admin']; }
	function is_multisite() { return $GLOBALS['digi_ctx']['multi']; }
	function is_network_admin() { return false; } function is_main_site() { return true; }
	function wp_doing_cron() { return false; } function wp_doing_ajax() { return false; }
	function plugin_dir_path( $f ) { return dirname( $f ) . '/'; } function plugin_dir_url( $f ) { return 'https://sitio.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
	function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
	function wp_normalize_path( $p ) { return str_replace( '\\', '/', (string) $p ); }
	function get_option( $k, $d = false ) { return 'digitalisimo_integrations_options' === $k ? array( 'enable_seo' => 1 ) : $d; }
	function get_site_option( $k, $d = false ) { return $d; }
	function get_transient() { return false; } function get_site_transient() { return false; }
	// Sólo cuentan las lecturas del puente (la del actualizador compartido es de la rama de los actualizadores).
	function get_file_data( $file ) {
		$from = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[0]['file'] ?? '';
		if ( false !== strpos( (string) $file, 'digitalisimo-tools' ) ) $GLOBALS[ false !== strpos( $from, 'class-tools-update-bridge.php' ) ? 'digi_header_reads' : 'digi_header_reads_shared' ] = ( $GLOBALS[ false !== strpos( $from, 'class-tools-update-bridge.php' ) ? 'digi_header_reads' : 'digi_header_reads_shared' ] ?? 0 ) + 1;
		return array( 'Version' => '1.0.42' );
	}
	function wp_next_scheduled() { return time(); } function wp_schedule_event() { return true; } function wp_schedule_single_event() { return true; }
	function get_bloginfo() { return '6.8'; }
	function did_action() { return 0; } function doing_action() { return false; }
	function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
	function current_user_can() { return false; }
	function __( $t ) { return $t; }
	function apply_upgrader() { return digitalisimo_integrations_load_all( true ); }

	$t0 = hrtime( true ); $m0 = memory_get_usage();
	require $seo . '/digitalisimo-integrations.php';
	digitalisimo_integrations_boot();
	$boot_hooks = $GLOBALS['digi_hooks'];
	// Costo de incluir y arrancar (sin OPcache, como Playground): se imprime antes de lo que sólo existe con el arreglo.
	echo 'DIGI-COST ' . json_encode( array( 'ms' => round( ( hrtime( true ) - $t0 ) / 1e6, 2 ), 'mem_kb' => (int) round( ( memory_get_usage() - $m0 ) / 1024 ), 'files' => count( array_filter( get_included_files(), function( $f ) use ( $seo ) { return 0 === strpos( $f, $seo . '/' ); } ) ) ) ) . "\n";

	$loaded = array(); $kb = 0;
	foreach ( get_included_files() as $f ) {
		if ( 0 !== strpos( $f, $seo . '/includes/' ) ) continue;
		$b = basename( $f );
		if ( in_array( $b, array( 'class-digitalisimo-core.php', 'class-plugin-catalog.php', 'class-digitalisimo-updater.php' ), true ) ) continue;
		$loaded[] = $b; $kb += filesize( $f );
	}

	$pre_errors = array();
	// Antes de que WordPress toque los archivos de un plugin, SEO compila todas sus clases.
	$pre = in_array( array( 'f', 'upgrader_pre_install', 'digitalisimo_integrations_load_all', 1, 1 ), $boot_hooks, true );
	if ( $pre ) { apply_upgrader(); foreach ( digitalisimo_integrations_classes() as $class => $file ) if ( ! in_array( realpath( $seo . '/' . $file ), get_included_files(), true ) ) { $pre_errors[] = "Antes de actualizar no se cargó $file."; break; } }
	else $pre_errors[] = 'SEO no precarga sus clases en upgrader_pre_install.';
	// Cada módulo diferido: lo que registra digitalisimo_integrations_lazy() y lo que registra su init(), en el mismo contexto.
	$init = array(); $parity = array();
	foreach ( DIGI_LAZY as $class ) {
		$GLOBALS['digi_hooks'] = array();
		digitalisimo_integrations_lazy( $class );
		$lazy = $GLOBALS['digi_hooks'];
		$GLOBALS['digi_hooks'] = array();
		if ( ! isset( DIGI_REQUIRES[ $class ] ) || function_exists( DIGI_REQUIRES[ $class ] ) ) call_user_func( array( $class, 'init' ) );
		$real = $GLOBALS['digi_hooks'];
		$init[ $class ] = $real;
		if ( $lazy !== $real ) $parity[ $class ] = 'lazy ' . json_encode( $lazy ) . ' · init ' . json_encode( $real );
	}

	// Mapa: cada clase declarada en includes/ está en él y su archivo la declara.
	$map = digitalisimo_integrations_classes(); $errors = $pre_errors;
	foreach ( $map as $class => $file ) {
		if ( ! is_file( $seo . '/' . $file ) ) { $errors[] = "El mapa apunta a $file, que no existe."; continue; }
		if ( ! preg_match( '/^\s*(?:final\s+|abstract\s+)?class\s+' . preg_quote( $class, '/' ) . '\b/m', (string) file_get_contents( $seo . '/' . $file ) ) ) $errors[] = "$file no declara $class.";
		if ( ! class_exists( $class ) ) $errors[] = "El autoloader no carga $class.";
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $seo . '/includes' ) );
	foreach ( $it as $f ) {
		if ( '.php' !== substr( (string) $f, -4 ) ) continue;
		if ( preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/m', (string) file_get_contents( (string) $f ), $m ) ) {
			foreach ( $m[1] as $class ) if ( ! isset( $map[ $class ] ) && ! in_array( $class, array( 'Digitalisimo_Core', 'Digitalisimo_Plugin_Catalog', 'Digitalisimo_Updater' ), true ) ) $errors[] = "$class (" . basename( (string) $f ) . ') no está en el mapa del autoloader.';
		}
	}

	echo 'DIGI-JSON ' . json_encode( array( 'loaded' => $loaded, 'loaded_kb' => (int) round( $kb / 1024 ), 'init' => $init, 'parity' => $parity, 'map_errors' => $errors, 'bridge_header_reads' => $GLOBALS['digi_header_reads'], 'boot_hooks' => count( $boot_hooks ) ) ) . "\n";
}
