<?php
/** Flujo completo de copias CSS: modo seguro, estricto, aprobación por modo y fallback. */
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
$url = 'https://example.test/uploads/elementor/google-fonts/css/fonts.css';

// Seguro: aun con inventario completo, una familia no detectada se conserva y se registra.
$result = $guard::build( true );
if ( 0 !== $result['removed'] || array( 'Unused' ) !== $GLOBALS['options'][ $guard::OPTION ]['undetected'] || $url !== $guard::style_src( $url, 'font-css' ) ) throw new RuntimeException( 'El modo seguro debe conservar y registrar familias no detectadas sin crear copias innecesarias.' );

// Estricto: elimina todo @font-face no autorizado, nunca los iconos.
$GLOBALS['settings']['perf_font_guard_mode'] = 'strict';
$result = $guard::build( true );
$generated = $GLOBALS['options'][ $guard::OPTION ]['rows'][ $url ]['generated'] ?? '';
if ( 1 !== $result['removed'] || 1 !== $result['built'] || ! is_file( $generated ) ) throw new RuntimeException( 'Estricto debe generar una copia sin la familia no autorizada.' );
$css = file_get_contents( $generated );
if ( false !== strpos( $css, 'Unused' ) || false === strpos( $css, 'Inter' ) || false === strpos( $css, 'Font Awesome' ) ) throw new RuntimeException( 'Debe preservar la familia autorizada y los iconos.' );
if ( $generated !== ( $GLOBALS['options'][ $guard::OPTION ]['rows'][ $url ]['generated'] ?? '' ) || true !== $GLOBALS['options'][ $guard::OPTION ]['rows'][ $url ]['valid'] ) throw new RuntimeException( 'La copia debe superar la validación posterior.' );
if ( $url === $guard::style_src( $url, 'font-css' ) ) throw new RuntimeException( 'La copia aprobada debe sustituir el CSS local incluso con modo seguro.' );

// Un recálculo automático (sin aprobación explícita) conserva la aprobación del modo.
$guard::forget();
$guard::build( false );
if ( $url === $guard::style_src( $url, 'font-css' ) ) throw new RuntimeException( 'El recálculo tras editar en Elementor no debe apagar la optimización aprobada.' );

// Cambiar de modo exige aprobar otra vez: la aprobación pertenece a un modo.
$GLOBALS['settings']['perf_font_guard_mode'] = 'manual';
$GLOBALS['settings']['perf_font_guard_allowlist'] = 'Inter|400|normal';
$guard::build( false );
if ( $url !== $guard::style_src( $url, 'font-css' ) ) throw new RuntimeException( 'Un modo distinto sin aprobar debe servir el CSS original.' );

// Si Elementor regenera el original, se sirve el original hasta recalcular.
$GLOBALS['settings']['perf_font_guard_mode'] = 'strict';
$GLOBALS['settings']['perf_font_guard_allowlist'] = '';
$guard::build( true );
touch( $original, time() + 60 );
clearstatcache();
if ( $url !== $guard::style_src( $url, 'font-css' ) ) throw new RuntimeException( 'Un original regenerado debe anular la copia anterior.' );

// Un fallo queda registrado con la huella vigente para no reintentar en bucle.
$GLOBALS['options']['fonts']['families'] = array();
$result = $guard::build( false );
if ( empty( $result['error'] ) || '' === ( $GLOBALS['options'][ $guard::OPTION ]['error'] ?? '' ) || $guard::fingerprint( 'strict', '' ) !== $GLOBALS['options'][ $guard::OPTION ]['fingerprint'] ) throw new RuntimeException( 'Sin familias, la generación debe fallar abierta y registrar el motivo.' );

foreach ( glob( $directory . '/digitalisimo-*.css' ) as $path ) unlink( $path );
unlink( $original );
rmdir( $directory ); rmdir( dirname( $directory ) ); rmdir( dirname( dirname( $directory ) ) ); rmdir( $base );
echo "Optimización de fuentes: seguro, estricto, aprobación por modo y fallbacks correctos.\n";
