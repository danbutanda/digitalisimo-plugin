<?php
/** Variante manual y automática con protección de iconos y fallos abiertos. */
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-font-guard.php';
$guard = Digitalisimo_Integrations_Performance_Font_Guard::class;
$allow = $guard::sanitize_allowlist( "Inter|400,700|normal,italic\n../../fuera|400|normal\nSource Serif 4|400|normal" );
if ( "Inter|400,700|normal,italic\nSource Serif 4|400|normal" !== $allow || 'off' !== $guard::sanitize_mode( 'otro' ) ) throw new RuntimeException( 'La política debe aceptar sólo familias y variantes válidas.' );
$css = "@font-face{font-family:'Inter';font-weight:400;font-style:normal;src:url('../fonts/a.woff2')}\n@font-face{font-family:'Inter';font-weight:700;font-style:italic;src:url('../fonts/b.woff2')}\n@font-face{font-family:'Font Awesome 5 Free';font-weight:900;src:url('../fonts/icons.woff2')}\n@font-face{font-family:'Other';font-weight:400;src:url('../fonts/c.woff2')}";
$manual = array( 'inter' => array( 'weights' => array( '400' ), 'styles' => array( 'normal' ) ) );
list( $filtered, $removed ) = $guard::filter_css( $css, $manual, 'manual' );
if ( 2 !== $removed || false === strpos( $filtered, 'a.woff2' ) || false !== strpos( $filtered, 'b.woff2' ) || false === strpos( $filtered, 'icons.woff2' ) || false !== strpos( $filtered, 'c.woff2' ) ) throw new RuntimeException( 'Debe retirar variantes excluidas y conservar icon fonts.' );
list( $filtered, $removed ) = $guard::filter_css( $css, $manual, 'auto' );
if ( 1 !== $removed || false === strpos( $filtered, 'c.woff2' ) ) throw new RuntimeException( 'Auto conserva familias desconocidas del Kit.' );
$variable = "@font-face{font-family:'Inter';font-weight:100 900;src:url('../fonts/variable.woff2')}";
if ( 0 !== $guard::filter_css( $variable, $manual, 'manual' )[1] ) throw new RuntimeException( 'Las variantes ambiguas deben conservarse.' );
class Digitalisimo_Integrations_Performance_Manager { public static function advanced_allowed() { return true; } }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $key ] ?? ''; } }
function get_option() { return $GLOBALS['manifest']; }
$original = tempnam( sys_get_temp_dir(), 'font-src-' );
$generated = tempnam( sys_get_temp_dir(), 'font-new-' );
file_put_contents( $original, $css );
file_put_contents( $generated, $filtered );
$GLOBALS['settings'] = array( 'perf_font_guard_mode' => 'manual', 'perf_font_guard_allowlist' => $allow );
$GLOBALS['settings']['perf_font_guard_allowlist'] = 'Inter|400|normal';
$faces = array( 'faces' => array(
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'normal', 'url' => 'https://site.test/a.woff2' ),
	array( 'family' => 'Inter', 'weight' => '700', 'style' => 'italic', 'url' => 'https://site.test/b.woff2' ),
	array( 'family' => 'Font Awesome 5 Free', 'weight' => '900', 'style' => 'normal', 'url' => 'https://site.test/icons.woff2' ),
) );
$permitted = $guard::permitted_urls( $faces );
if ( ! isset( $permitted['https://site.test/a.woff2'], $permitted['https://site.test/icons.woff2'] ) || isset( $permitted['https://site.test/b.woff2'] ) ) throw new RuntimeException( 'La precarga debe omitir variantes excluidas y conservar iconos.' );
$GLOBALS['settings']['perf_font_guard_allowlist'] = $allow;
$url = 'https://site.test/wp-content/uploads/elementor/google-fonts/css/inter.css';
$GLOBALS['manifest'] = array( 'fingerprint' => hash( 'sha256', "manual\n" . $allow ), 'rows' => array( $url => array( 'original' => $original, 'mtime' => filemtime( $original ), 'generated' => $generated, 'url' => 'https://site.test/generated.css' ) ) );
if ( 'https://site.test/generated.css' !== $guard::style_src( $url . '?ver=1', 'elementor-font' ) ) throw new RuntimeException( 'Debe sustituir sólo CSS local precompilado.' );
$status_report = array( 'uploads_baseurl' => 'https://site.test/wp-content/uploads/', 'faces' => array() );
$status_face = array( 'css' => 'inter.css' );
if ( ! $guard::ready_for_face( $status_face, $status_report ) ) throw new RuntimeException( 'La copia vigente debe indicarse como lista.' );
$GLOBALS['manifest']['fingerprint'] = 'stale';
if ( $url !== $guard::style_src( $url, 'elementor-font' ) ) throw new RuntimeException( 'Una política cambiada debe conservar el CSS original.' );
if ( $guard::ready_for_face( $status_face, $status_report ) ) throw new RuntimeException( 'Una copia vencida no debe anunciar bloqueo efectivo.' );
unlink( $original ); unlink( $generated );
echo "Blindaje de fuentes: política, variantes, iconos y modo seguro correctos.\n";
