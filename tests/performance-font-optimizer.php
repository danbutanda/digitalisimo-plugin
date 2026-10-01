<?php
/** Comprueba el flujo completo de copias CSS automáticas y el fallback. */
define( 'ABSPATH', __DIR__ );
define( 'KB_IN_BYTES', 1024 );
$base = sys_get_temp_dir() . '/digitalisimo-font-opt-' . bin2hex( random_bytes( 5 ) );
$directory = $base . '/elementor/google-fonts/css';
mkdir( $directory, 0777, true );
$original = $directory . '/fonts.css';
file_put_contents( $original, "@font-face{font-family:Inter;font-weight:400;font-style:normal;src:url('../fonts/inter.woff2')}\n@font-face{font-family:Unused;font-weight:400;font-style:normal;src:url('../fonts/unused.woff2')}\n@font-face{font-family:'Font Awesome 5 Free';font-weight:900;src:url('../fonts/icons.woff2')}" );
class Digitalisimo_Integrations_Settings { const OPTION = 'seo'; }
class Digitalisimo_Integrations_Performance_Fonts { const OPTION = 'fonts'; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $key ] ?? ''; } }
class Digitalisimo_Integrations_Performance_Manager { public static function advanced_allowed() { return false; } public static function frontend_safe() { return true; } }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['base'], 'baseurl' => 'https://example.test/uploads' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
$GLOBALS['base'] = $base;
$GLOBALS['settings'] = array( 'perf_font_guard_mode' => 'auto', 'perf_font_guard_allowlist' => '', 'perf_safe_mode' => 1 );
$GLOBALS['options']['fonts'] = array( 'scanned_at' => '2026-10-01', 'complete' => true, 'families' => array( array( 'family' => 'Inter', 'weights' => array( '400' ), 'styles' => array( 'normal' ) ) ) );
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-font-guard.php';
$guard = Digitalisimo_Integrations_Performance_Font_Guard::class;
$result = $guard::build( true );
$url = 'https://example.test/uploads/elementor/google-fonts/css/fonts.css';
$generated = $GLOBALS['options'][ $guard::OPTION ]['rows'][ $url ]['generated'] ?? '';
if ( 1 !== $result['removed'] || 1 !== $result['built'] || ! is_file( $generated ) ) throw new RuntimeException( 'El inventario completo debe generar una copia sin la familia no usada.' );
$css = file_get_contents( $generated );
if ( false !== strpos( $css, 'Unused' ) || false === strpos( $css, 'Inter' ) || false === strpos( $css, 'Font Awesome' ) ) throw new RuntimeException( 'Debe preservar la familia configurada y los iconos.' );
if ( $url === $guard::style_src( $url, 'font-css' ) ) throw new RuntimeException( 'La copia aprobada debe sustituir el CSS local incluso con modo seguro.' );
$GLOBALS['options']['fonts']['complete'] = false;
$result = $guard::build( true );
if ( 0 !== $result['removed'] || $url !== $guard::style_src( $url, 'font-css' ) ) throw new RuntimeException( 'El inventario parcial debe conservar familias desconocidas y el CSS original.' );
foreach ( glob( $directory . '/digitalisimo-*.css' ) as $path ) unlink( $path );
unlink( $original );
rmdir( $directory ); rmdir( dirname( $directory ) ); rmdir( dirname( dirname( $directory ) ) ); rmdir( $base );
echo "Optimización de fuentes: copias aplicadas y fallback parcial correctos.\n";
