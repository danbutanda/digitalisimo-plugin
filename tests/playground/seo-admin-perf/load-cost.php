<?php
/**
 * Costo fijo de cargar el código de SEO, IA Tools y Hosting en cada petición, con PHP nativo (Playground no tiene
 * OPcache y exagera esa parte): incluye los mismos archivos que el archivo principal de cada plugin (sus require_once)
 * y mide tiempo y memoria. Uso, desde la raíz del repositorio:
 *   php -d opcache.enable_cli=0 tests/playground/seo-admin-perf/load-cost.php                      (sin OPcache)
 *   php -d opcache.enable_cli=1 -d opcache.file_cache=DIR -d opcache.file_cache_only=1 …load-cost.php   (dos veces: la
 *   segunda lee el código ya compilado, como un servidor con OPcache)
 * Imprime una línea JSON por plugin: archivos, KB de código, ms y KB de memoria de PHP.
 */
define( 'ABSPATH', __DIR__ . '/' );
// Lo poco que un archivo puede llamar al incluirse (registrar ganchos); el resto del código sólo se declara.
foreach ( array( 'add_action', 'add_filter', 'remove_action', 'remove_filter', 'register_activation_hook', 'register_deactivation_hook', 'add_shortcode' ) as $fn ) if ( ! function_exists( $fn ) ) eval( "function $fn() { return true; }" );
if ( ! function_exists( 'did_action' ) ) { function did_action() { return 0; } }
if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return true; } }
if ( ! function_exists( 'plugin_dir_path' ) ) { function plugin_dir_path( $f ) { return dirname( $f ) . '/'; } }
if ( ! function_exists( 'plugin_dir_url' ) ) { function plugin_dir_url( $f ) { return 'https://sitio.test/'; } }
$root = $argv[1] ?? getcwd();
$plugins = array( 'seo' => 'digitalisimo-seo/digitalisimo-integrations.php', 'ia' => 'digitalisimo-ia-tools/digitalisimo-chatbot.php', 'hosting' => 'digitalisimo-hosting/digitalisimo-hosting.php' );
foreach ( $plugins as $name => $main ) {
	$dir  = dirname( $root . '/' . $main );
	$text = (string) file_get_contents( $root . '/' . $main );
	preg_match_all( "#require_once\\s+(?:__DIR__\\s*\\.\\s*'|DIGITALISIMO_INTEGRATIONS_DIR\\s*\\.\\s*')/?([^']+\\.php)'#", $text, $m );
	// SEO ya no incluye sus clases: su mapa del autoloader («'Clase' => 'includes/…php'») da todo su código, que una
	// petición sólo compila en parte (seo_files y seo_kb de la sonda dicen cuánto).
	if ( preg_match_all( "#=>\\s*'(includes/[^']+\\.php)'#", $text, $mm ) ) $m[1] = array_merge( $m[1], $mm[1] );
	$files = array_values( array_unique( array_filter( array_map( function( $f ) use ( $dir ) { return $dir . '/' . ltrim( $f, '/' ); }, $m[1] ), 'is_file' ) ) );
	// Lo compartido (digitalisimo-ui, núcleo y actualizador) puede venir ya cargado por otro plugin: se mide aparte.
	$bytes = 0; foreach ( $files as $f ) $bytes += filesize( $f );
	$mem = memory_get_usage(); $t = hrtime( true ); $ok = 0;
	foreach ( $files as $f ) { try { require_once $f; $ok++; } catch ( Throwable $e ) { /* una clase compartida ya declarada por otro plugin */ } }
	$ms = ( hrtime( true ) - $t ) / 1e6;
	echo json_encode( array( 'plugin' => $name, 'files' => count( $files ), 'loaded' => $ok, 'code_kb' => round( $bytes / 1024 ), 'ms' => round( $ms, 2 ), 'mem_kb' => round( ( memory_get_usage() - $mem ) / 1024 ), 'opcache' => function_exists( 'opcache_get_status' ) && is_array( $s = @opcache_get_status( false ) ) && ! empty( $s['opcache_enabled'] ), 'php' => PHP_VERSION ) ) . "\n";
}
