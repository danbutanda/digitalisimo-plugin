<?php
/** Asegura precarga contextual y sin duplicados, sin iniciar WordPress completo. */
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_upload_dir() { return array( 'baseurl' => 'https://site.test/wp-content/uploads' ); }
function current_time() { return '2026-10-01 12:00:00'; }
function update_option( $key, $value ) { $GLOBALS['stored_options'][ $key ] = $value; }
function get_option( $key, $default = false ) { return $GLOBALS['stored_options'][ $key ] ?? $default; }
function wp_next_scheduled( $hook ) { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_single_event( $when, $hook ) { $GLOBALS['scheduled'][ $hook ] = $when; }
function esc_url( $url ) { return $url; }
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { return true; } }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $key ] ?? ''; } }
class Digitalisimo_Integrations_Performance_Fonts { public static function scan() { return $GLOBALS['scan_report']; } }
class Digitalisimo_Integrations_Performance_Font_Guard { public static function permitted_urls( $report ) { return array_fill_keys( array_column( $report['faces'], 'url' ), true ); } }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-preloads.php';
$class = Digitalisimo_Integrations_Performance_Preloads::class;
if ( 'off' !== $class::sanitize_mode( 'malicioso' ) || 2 !== $class::sanitize_limit( 90 ) || 1 !== $class::sanitize_limit( 0 ) ) throw new RuntimeException( 'El modo y el límite deben cerrarse a valores seguros.' );
if ( "elementor/google-fonts/fonts/a.woff2" !== $class::sanitize_paths( "../fuera.woff2\nelementor/google-fonts/fonts/a.woff2\nelementor/google-fonts/fonts/a.woff2\nhttps://evil.test/a.woff2" ) ) throw new RuntimeException( 'Sólo rutas locales válidas y únicas.' );
$report = array( 'uploads_baseurl' => 'https://site.test/wp-content/uploads/', 'families' => array( array( 'family' => 'Inter' ) ), 'faces' => array(
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'normal', 'css' => 'inter.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' ),
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'normal', 'css' => 'inter.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' ),
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'italic', 'css' => 'inter.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/b.woff2' ),
	array( 'family' => 'Other', 'weight' => '400', 'style' => 'normal', 'css' => 'other.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/c.woff2' ),
) );
$queued = array( 'inter.css' => true );
$auto = $class::candidates( $report, 'auto', '', $queued, 2, array() );
if ( array( 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' ) !== $auto ) throw new RuntimeException( 'Auto sólo debe elegir la fuente normal del Kit y evitar duplicados.' );
$manual = $class::candidates( $report, 'manual', "elementor/google-fonts/fonts/b.woff2\nelementor/google-fonts/fonts/c.woff2", $queued, 2, array() );
if ( array( 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/b.woff2' ) !== $manual ) throw new RuntimeException( 'Manual debe exigir CSS encolado.' );
$blocked = $class::candidates( $report, 'manual', 'elementor/google-fonts/fonts/b.woff2', $queued, 2, $manual );
if ( $blocked ) throw new RuntimeException( 'No se debe duplicar un preload publicado.' );
$blocked = $class::candidates( $report, 'manual', 'elementor/google-fonts/fonts/b.woff2', $queued, 2, array(), array( 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' => true ) );
if ( $blocked ) throw new RuntimeException( 'No se debe precargar una variante bloqueada por la política de fuentes.' );
$base = 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/';
$GLOBALS['settings'] = array( 'perf_preload_mode' => 'auto', 'perf_preload_limit' => 2, 'perf_preload_paths' => '' );
$GLOBALS['scan_report'] = array( 'scanned_at' => 'now', 'uploads_baseurl' => 'https://site.test/wp-content/uploads/', 'kit_families' => array(), 'families' => array( array( 'family' => 'Source Serif 4' ), array( 'family' => 'Inter' ) ), 'faces' => array(
	array( 'family' => 'Source Serif 4', 'weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0100-024F', 'css' => 'sourceserif4.css', 'url' => $base . 'serif-other.woff2' ),
	array( 'family' => 'Source Serif 4', 'weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'css' => 'sourceserif4.css', 'url' => $base . 'serif-latin.woff2' ),
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'css' => 'inter.css', 'url' => $base . 'inter-latin.woff2' ),
) );
$manifest = $class::rebuild();
if ( array( $base . 'serif-latin.woff2', $base . 'inter-latin.woff2' ) !== array_column( $manifest['rows'], 'url' ) ) throw new RuntimeException( 'El manifest debe elegir las dos fuentes latinas del Kit, no el primer subconjunto.' );
unset( $GLOBALS['stored_options'][ $class::OPTION ] );
$class::schedule_missing();
if ( empty( $GLOBALS['scheduled'][ $class::CRON ] ) ) throw new RuntimeException( 'Una actualización automática debe programar el manifest faltante sin escanear en frontend.' );
$GLOBALS['stored_options'][ $class::OPTION ] = $manifest;
$resources = $class::inject_resources( array( array( 'href' => $base . 'serif-latin.woff2', 'as' => 'font' ) ) );
if ( 2 !== count( $resources ) || $base . 'inter-latin.woff2' !== $resources[1]['href'] || 'high' !== $resources[1]['fetchpriority'] ) throw new RuntimeException( 'El filtro de WordPress debe evitar duplicados y priorizar la fuente restante.' );
ob_start(); $class::print_links(); $printed = ob_get_clean();
if ( 1 !== substr_count( $printed, 'fetchpriority="high"' ) || false === strpos( $printed, 'crossorigin' ) ) throw new RuntimeException( 'El fallback debe respetar URLs que WordPress ya precarga.' );
echo "Preloads: rutas, contexto, límite y duplicados correctos.\n";
