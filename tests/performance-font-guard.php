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
list( $filtered, $removed ) = $guard::filter_css( $css, $manual, 'auto-prune' );
if ( 2 !== $removed || false !== strpos( $filtered, 'c.woff2' ) || false === strpos( $filtered, 'icons.woff2' ) ) throw new RuntimeException( 'El alias histórico auto-prune equivale a estricto: excluye familias no usadas sin retirar iconos.' );
$variable = "@font-face{font-family:'Inter';font-weight:100 900;src:url('../fonts/variable.woff2')}";
if ( 0 !== $guard::filter_css( $variable, $manual, 'manual' )[1] ) throw new RuntimeException( 'Las variantes ambiguas deben conservarse.' );
class Digitalisimo_Integrations_Performance_Manager { public static function advanced_allowed() { return empty( $GLOBALS['safe_mode'] ); } public static function frontend_safe() { return true; } }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $key ] ?? ''; } }
function get_option( $key, $default = array() ) { return 'digitalisimo_performance_font_guard_manifest' === $key ? $GLOBALS['manifest'] : ( $GLOBALS['options'][ $key ] ?? $default ); }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; }
function delete_option( $key ) { $GLOBALS['deleted_manifest'] = $key; }
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
$GLOBALS['manifest'] = array( 'fingerprint' => $guard::fingerprint( 'manual', $allow ), 'rows' => array( $url => array( 'original' => $original, 'mtime' => filemtime( $original ), 'generated' => $generated, 'url' => 'https://site.test/generated.css' ) ) );
if ( 'https://site.test/generated.css' !== $guard::style_src( $url . '?ver=1', 'elementor-font' ) ) throw new RuntimeException( 'Debe sustituir sólo CSS local precompilado.' );
$status_report = array( 'uploads_baseurl' => 'https://site.test/wp-content/uploads/', 'faces' => array() );
$status_face = array( 'css' => 'inter.css' );
if ( ! $guard::ready_for_face( $status_face, $status_report ) ) throw new RuntimeException( 'La copia vigente debe indicarse como lista.' );
$GLOBALS['manifest']['fingerprint'] = 'stale';
if ( $url !== $guard::style_src( $url, 'elementor-font' ) ) throw new RuntimeException( 'Una política cambiada debe conservar el CSS original.' );
if ( $guard::ready_for_face( $status_face, $status_report ) ) throw new RuntimeException( 'Una copia vencida no debe anunciar bloqueo efectivo.' );
$GLOBALS['manifest']['fingerprint'] = $guard::fingerprint( 'manual', $allow );
$GLOBALS['manifest']['approved'] = true;
$GLOBALS['safe_mode'] = true;
$GLOBALS['settings']['perf_safe_mode'] = 1;
if ( 'https://site.test/generated.css' !== $guard::style_src( $url, 'elementor-font' ) ) throw new RuntimeException( 'Una copia aprobada debe funcionar en modo seguro sin habilitar otras optimizaciones.' );
$GLOBALS['deleted_manifest'] = '';
$guard::invalidate_on_meta( 1, 5, '_otro_campo', '' );
if ( $GLOBALS['deleted_manifest'] ) throw new RuntimeException( 'Los cambios ajenos a Elementor no deben invalidar el blindaje.' );
$guard::invalidate_on_meta( 1, 5, '_elementor_data', '' );
if ( $guard::OPTION !== $GLOBALS['deleted_manifest'] ) throw new RuntimeException( 'Un cambio en Elementor debe invalidar las copias preparadas.' );

// El núcleo anota cada CSS local en `_elementor_local_google_fonts` al volver a descargarlo
// (tras su propio vaciado o el de DIGITALÍSIMO Elements): se agenda el cron compartido una sola vez.
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-preloads.php';
$preloads = Digitalisimo_Integrations_Performance_Preloads::class;
$GLOBALS['hooks']  = array();
$GLOBALS['events'] = array();
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; return true; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return add_action( $hook, $callback, $priority, $accepted_args ); }
function do_action( $hook, ...$args ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $callback ) call_user_func_array( $callback, $args ); }
function wp_next_scheduled( $hook ) { foreach ( $GLOBALS['events'] as $event ) if ( $hook === $event[1] ) return $event[0]; return false; }
function wp_schedule_single_event( $timestamp, $hook ) { $GLOBALS['events'][] = array( $timestamp, $hook ); return true; }
$rebuilds = function() use ( $preloads ) { return count( array_filter( $GLOBALS['events'], function( $event ) use ( $preloads ) { return $preloads::CRON === $event[1]; } ) ); };
$guard::init();
foreach ( array( 'add_option__elementor_local_google_fonts', 'update_option__elementor_local_google_fonts' ) as $hook ) {
	if ( array( array( $guard, 'schedule_on_local_fonts' ) ) !== ( $GLOBALS['hooks'][ $hook ] ?? array() ) ) throw new RuntimeException( 'El blindaje debe escuchar ' . $hook . ' una sola vez.' );
}
if ( isset( $GLOBALS['hooks']['delete_option__elementor_local_google_fonts'] ) ) throw new RuntimeException( 'Vaciar las fuentes no debe agendar: sin archivos, el recálculo dejaría el blindaje sin filas.' );
$manifest_before = $GLOBALS['manifest'];
$options_before  = $GLOBALS['options'] ?? array();
$GLOBALS['deleted_manifest'] = '';
$first  = array( 'inter' => array( 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/css/inter.css', 'version' => 1 ) );
$second = $first + array( 'roboto' => array( 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/css/roboto.css', 'version' => 1 ) );
// Primera descarga tras vaciar: la fila no existe y update_option() crea la opción.
do_action( 'add_option__elementor_local_google_fonts', '_elementor_local_google_fonts', $first );
if ( 1 !== $rebuilds() ) throw new RuntimeException( 'Crear la lista de CSS locales debe agendar el recálculo.' );
// Otras familias en la misma visita o en las siguientes, con el evento aún pendiente.
do_action( 'update_option__elementor_local_google_fonts', $first, $second, '_elementor_local_google_fonts' );
do_action( 'update_option__elementor_local_google_fonts', $second, $second + array( 'lora' => array() ), '_elementor_local_google_fonts' );
if ( 1 !== $rebuilds() || 1 !== count( $GLOBALS['events'] ) ) throw new RuntimeException( 'Crear y escribir la lista de CSS locales debe agendar el recálculo compartido una sola vez.' );
if ( $GLOBALS['deleted_manifest'] || $manifest_before !== $GLOBALS['manifest'] || $options_before !== ( $GLOBALS['options'] ?? array() ) ) throw new RuntimeException( 'Durante la visita sólo se agenda: ni copias, ni manifiesto, ni opciones.' );
// Ya ejecutado el evento, una descarga posterior vuelve a agendar.
$GLOBALS['events'] = array();
do_action( 'update_option__elementor_local_google_fonts', $second, $first, '_elementor_local_google_fonts' );
if ( 1 !== $rebuilds() ) throw new RuntimeException( 'Una descarga posterior al recálculo debe volver a agendarlo.' );
// Cambiar font-display sólo vacía (lo hace el núcleo); el recálculo llega con la nueva descarga.
$GLOBALS['events'] = array();
do_action( 'update_option_elementor_font_display', 'auto', 'swap', 'elementor_font_display' );
do_action( 'delete_option__elementor_local_google_fonts', '_elementor_local_google_fonts' );
if ( $GLOBALS['events'] ) throw new RuntimeException( 'Sin una nueva descarga no debe agendarse el recálculo.' );
unlink( $original ); unlink( $generated );
echo "Blindaje de fuentes: política, variantes, iconos, modo seguro y recálculo tras una nueva descarga correctos.\n";
