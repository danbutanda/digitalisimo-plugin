<?php
/**
 * Whitelist efectiva por sitio con familias arbitrarias: ningún nombre fijo.
 * Cubre variantes exactas, unicode-range, icon fonts, excepciones, modos seguro
 * y estricto, validación, preloads críticos, estados de debug y aislamiento
 * entre sitios de una red.
 */
define( 'ABSPATH', __DIR__ );
define( 'KB_IN_BYTES', 1024 );

$GLOBALS['blog']  = 1;
$GLOBALS['root']  = sys_get_temp_dir() . '/digitalisimo-font-policy-' . bin2hex( random_bytes( 5 ) );
$GLOBALS['sites'] = array();

class Digitalisimo_Integrations_Settings { const OPTION = 'seo'; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['sites'][ $GLOBALS['blog'] ]['settings'][ $key ] ?? ''; } }
class Digitalisimo_Integrations_Performance_Manager { public static function advanced_allowed() { return true; } public static function frontend_safe() { return true; } }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['root'] . '/site-' . $GLOBALS['blog'], 'baseurl' => 'https://red.test/site-' . $GLOBALS['blog'] . '/uploads' ); }
function get_option( $key, $default = false ) { return $GLOBALS['sites'][ $GLOBALS['blog'] ]['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['sites'][ $GLOBALS['blog'] ]['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['sites'][ $GLOBALS['blog'] ]['options'][ $key ] ); return true; }
function current_time() { return '2026-10-01 12:00:00'; }
function absint( $value ) { return abs( (int) $value ); }
function is_multisite() { return true; }
function current_user_can( $cap ) { return 'manage_network_options' === $cap; }
function check_admin_referer( $action ) { return true; }
function get_sites( $args ) { return array_keys( $GLOBALS['sites'] ); }
function switch_to_blog( $id ) { $GLOBALS['stack'][] = $GLOBALS['blog']; $GLOBALS['blog'] = (int) $id; }
function restore_current_blog() { $GLOBALS['blog'] = array_pop( $GLOBALS['stack'] ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['sites'][ $GLOBALS['blog'] ]['cron'][ $hook ] ?? false; }
function wp_schedule_single_event( $when, $hook ) { $GLOBALS['sites'][ $GLOBALS['blog'] ]['cron'][ $hook ] = $when; }
function network_admin_url( $path = '' ) { return 'https://red.test/wp-admin/network/' . $path; }
function add_query_arg( $args, $url ) { return $url . '&' . http_build_query( $args ); }
class Redirected extends Exception {}
function wp_safe_redirect( $url ) { $GLOBALS['redirect'] = $url; throw new Redirected(); }

require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-fonts.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-font-guard.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-preloads.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-debug.php';
$guard   = Digitalisimo_Integrations_Performance_Font_Guard::class;
$fonts   = Digitalisimo_Integrations_Performance_Fonts::class;
$preload = Digitalisimo_Integrations_Performance_Preloads::class;
$debug   = Digitalisimo_Integrations_Performance_Debug::class;

function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
function face( $family, $weight, $style, $file, $range = 'U+0000-00FF' ) {
	return "@font-face{font-family:'$family';font-style:$style;font-weight:$weight;font-display:swap;src:url(../fonts/$file.woff2) format('woff2');unicode-range:$range}";
}
function site( $id, $settings, $report, $css ) {
	$GLOBALS['blog'] = $id;
	$GLOBALS['sites'][ $id ]['settings'] = $settings + array( 'perf_safe_mode' => 0, 'perf_font_guard_allowlist' => '', 'perf_font_icon_families' => '' );
	$dir = $GLOBALS['root'] . '/site-' . $id . '/elementor/google-fonts/css';
	if ( ! is_dir( $dir ) ) mkdir( $dir, 0777, true );
	file_put_contents( $dir . '/fonts.css', implode( "\n", $css ) );
	$base = 'https://red.test/site-' . $id . '/uploads/';
	$report += array( 'scanned_at' => 'now', 'complete' => true, 'kit_families' => array(), 'critical' => array(), 'uploads_baseurl' => $base, 'faces' => array() );
	update_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, $report );
	return $report;
}
function generated( $id ) {
	$GLOBALS['blog'] = $id;
	$row = get_option( Digitalisimo_Integrations_Performance_Font_Guard::OPTION )['rows'][ 'https://red.test/site-' . $id . '/uploads/elementor/google-fonts/css/fonts.css' ] ?? array();
	return ! empty( $row['generated'] ) ? file_get_contents( $row['generated'] ) : '';
}
function variants( $css ) {
	$out = array();
	foreach ( Digitalisimo_Integrations_Performance_Font_Guard::faces_in( $css ) as $f ) $out[] = $f['family'] . '|' . $f['style'] . '|' . $f['weight'] . '|' . $f['range'];
	sort( $out );
	return $out;
}

// ---------------------------------------------------------------------------
// Sitio 1 · Roboto normal 400/700, con subsets e icon fonts.
$css1 = array(
	face( 'Roboto', 300, 'normal', 'r300' ),
	face( 'Roboto', 400, 'normal', 'r400-latin' ),
	face( 'Roboto', 400, 'normal', 'r400-ext', 'U+0100-024F' ),
	face( 'Roboto', 500, 'normal', 'r500' ),
	face( 'Roboto', 700, 'normal', 'r700' ),
	face( 'Roboto', 900, 'normal', 'r900' ),
	face( 'Roboto', 400, 'italic', 'r400i' ),
	face( 'Material Symbols Outlined', 400, 'normal', 'symbols' ),
);
$report1 = site( 1, array( 'perf_font_guard_mode' => 'strict' ), array(
	'kit_families' => array( array( 'family' => 'Roboto' ) ),
	'families'     => array( array( 'family' => 'Roboto', 'weights' => array( '400', '700' ), 'styles' => array( 'normal' ) ) ),
	'critical'     => $fonts::critical_from_settings( array( 'body_typography_font_family' => 'Roboto', 'h1_typography_font_family' => 'Roboto', 'h1_typography_font_weight' => '700', 'h1_typography_font_style' => '' ) ),
), $css1 );
check( array( array( 'family' => 'Roboto', 'weight' => '400', 'style' => 'normal', 'role' => 'Cuerpo', 'body' => true ), array( 'family' => 'Roboto', 'weight' => '700', 'style' => 'normal', 'role' => 'H1', 'body' => false ) ) === $report1['critical'], 'Las variantes críticas salen del Kit del sitio, con 400 sólo como valor CSS por defecto del cuerpo.' );
check( array() === $fonts::critical_from_settings( array( 'h1_typography_font_family' => 'Roboto' ) ), 'Un H1 sin peso no se adivina.' );
$result = $guard::build( true, $report1 );
$out1 = generated( 1 );
check( 4 === $result['removed'] && 0 === $result['invalid'], 'Roboto debe perder exactamente 300, 500, 900 e italic.' );
check( array( 'Material Symbols Outlined|normal|400|U+0000-00FF', 'Roboto|normal|400|U+0000-00FF', 'Roboto|normal|400|U+0100-024F', 'Roboto|normal|700|U+0000-00FF' ) === variants( $out1 ), 'El CSS de Roboto debe contener sólo sus variantes autorizadas, con todos sus subsets e iconos.' );
check( 4 === substr_count( $out1, 'font-display:swap' ) && false !== strpos( $out1, 'unicode-range:U+0100-024F' ), 'Los bloques conservados mantienen font-display y unicode-range originales.' );

// ---------------------------------------------------------------------------
// Sitio 2 · Montserrat normal 400, italic 400 y normal 600; misma red, otra política.
$css2 = array(
	face( 'Montserrat', 400, 'normal', 'm400' ),
	face( 'Montserrat', 400, 'italic', 'm400i' ),
	face( 'Montserrat', 600, 'normal', 'm600' ),
	face( 'Montserrat', 600, 'italic', 'm600i' ),
	face( 'Montserrat', 700, 'normal', 'm700' ),
	face( 'Roboto', 400, 'normal', 'shared' ),
	face( 'Playfair Display', 400, 'normal', 'p400' ),
	face( 'Playfair Display', 700, 'italic', 'p700i' ),
	face( 'MyGlyphs', 400, 'normal', 'glyphs' ),
);
$report2 = site( 2, array( 'perf_font_guard_mode' => 'strict', 'perf_font_guard_allowlist' => 'Playfair Display|700|italic', 'perf_font_icon_families' => 'MyGlyphs' ), array(
	'families' => array(
		array( 'family' => 'Montserrat', 'weights' => array( '400' ), 'styles' => array( 'normal', 'italic' ) ),
		array( 'family' => 'Montserrat', 'weights' => array( '600' ), 'styles' => array( 'normal' ) ),
	),
), $css2 );
// Dos filas de la misma familia: la segunda prevalece; se combinan como en el informe real.
$report2['families'] = array( array( 'family' => 'Montserrat', 'weights' => array( '400', '600' ), 'styles' => array( 'normal', 'italic' ) ) );
update_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, $report2 );
$guard::build( true, $report2 );
$out2 = generated( 2 );
check( false === strpos( $out2, 'shared' ), 'Roboto no está en la whitelist del sitio 2: la política del sitio 1 no se filtra a otro sitio.' );
check( false !== strpos( $out2, 'p700i' ) && false === strpos( $out2, 'p400' ), 'La excepción manual autoriza sólo su variante.' );
check( false !== strpos( $out2, 'glyphs' ), 'Un icon font configurado nunca se filtra.' );
check( false === strpos( $out2, 'm700' ), 'Un peso no detectado se retira.' );
$GLOBALS['blog'] = 2;
check( $guard::is_exception( array( 'family' => 'Playfair Display', 'weight' => '700', 'style' => 'italic' ), $report2 ), 'La variante por excepción debe identificarse.' );
check( ! $guard::is_exception( array( 'family' => 'Montserrat', 'weight' => '400', 'style' => 'normal' ), $report2 ), 'Una variante detectada no es excepción.' );

// El sitio 1 sigue intacto después de generar el 2.
check( $out1 === generated( 1 ), 'Generar un sitio no altera las copias de otro.' );

// ---------------------------------------------------------------------------
// Sitio 3 · modo seguro: conserva y registra lo que la detección no vio.
$report3 = site( 3, array( 'perf_font_guard_mode' => 'auto' ), array(
	'families' => array( array( 'family' => 'Montserrat', 'weights' => array( '400' ), 'styles' => array( 'normal' ) ) ),
), array( face( 'Montserrat', 400, 'normal', 'm400' ), face( 'Montserrat', 800, 'normal', 'm800' ), face( 'Fuente Local', 400, 'normal', 'local' ) ) );
$guard::build( true, $report3 );
$out3 = generated( 3 );
check( false !== strpos( $out3, 'local' ) && false === strpos( $out3, 'm800' ), 'Seguro recorta variantes de familias detectadas y conserva familias no detectadas.' );
check( array( 'Fuente Local' ) === get_option( $guard::OPTION )['undetected'], 'Seguro registra las familias conservadas sin detectar.' );
check( 'No detectado · conservado por modo seguro' === $guard::origin( array( 'family' => 'Fuente Local' ), $report3 ), 'El origen debe explicar por qué se conservó.' );

// ---------------------------------------------------------------------------
// Validación posterior a la generación.
$GLOBALS['blog'] = 1;
$rules = ( new ReflectionMethod( $guard, 'rules' ) )->invoke( null, 'strict', '', $report1 );
$original = implode( "\n", $css1 );
check( array() === $guard::validate_css( $original, $out1, $rules, 'strict', '' ), 'Una copia correcta no debe tener errores.' );
$errors = $guard::validate_css( $original, $original, $rules, 'strict', '' );
check( 4 === count( preg_grep( '/^Variante no autorizada/', $errors ) ), 'Una variante no autorizada en el CSS generado es un error.' );
$errors = $guard::validate_css( $original, str_replace( face( 'Roboto', 700, 'normal', 'r700' ), '', $out1 ), $rules, 'strict', '' );
check( array( 'Falta una variante autorizada: Roboto normal 700 [U+0000-00FF]' ) === $errors, 'Perder una variante autorizada es un error.' );
$errors = $guard::validate_css( $original, $out1 . "\n" . face( 'Roboto', 700, 'normal', 'r700' ), $rules, 'strict', '' );
check( 1 === count( preg_grep( '/^Regla @font-face duplicada/', $errors ) ), 'Un duplicado introducido por la generación es un error.' );
list( $deduped, $dropped ) = $guard::filter_css( $original . "\n" . face( 'Roboto', 700, 'normal', 'r700' ), $rules, 'strict', '' );
check( 5 === $dropped && 1 === substr_count( $deduped, 'r700' ), 'Un bloque idéntico repetido se elimina al generar.' );

// ---------------------------------------------------------------------------
// Preloads: variantes críticas reales, autorizadas y sin duplicados.
$base1 = 'https://red.test/site-1/uploads/elementor/google-fonts/fonts/';
$report1['faces'] = array(
	array( 'family' => 'Roboto', 'weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0100-024F', 'css' => 'fonts.css', 'url' => $base1 . 'r400-ext.woff2' ),
	array( 'family' => 'Roboto', 'weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'css' => 'fonts.css', 'url' => $base1 . 'r400-latin.woff2' ),
	array( 'family' => 'Roboto', 'weight' => '400', 'style' => 'italic', 'unicode_range' => 'U+0000-00FF', 'css' => 'fonts.css', 'url' => $base1 . 'r400i.woff2' ),
	array( 'family' => 'Roboto', 'weight' => '700', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'css' => 'fonts.css', 'url' => $base1 . 'r700.woff2' ),
	array( 'family' => 'Material Symbols Outlined', 'weight' => '400', 'style' => 'normal', 'unicode_range' => '', 'css' => 'fonts.css', 'url' => $base1 . 'symbols.woff2' ),
);
$GLOBALS['sites'][1]['settings'] += array( 'perf_preload_mode' => 'auto', 'perf_preload_limit' => 2, 'perf_preload_paths' => '' );
$manifest = $preload::rebuild( $report1 );
check( array( $base1 . 'r400-latin.woff2', $base1 . 'r700.woff2' ) === array_column( $manifest['rows'], 'url' ) && array() === $manifest['validation'], 'Se precargan las variantes críticas del Kit en su subset latino, sin italic ni iconos.' );
check( 'Crítico · Cuerpo' === $manifest['rows'][0]['source'], 'El preload declara de qué variante crítica viene.' );
// Un cuerpo en 300 precarga 300: el peso no está fijo en el código.
$report1['critical'] = array( array( 'family' => 'Roboto', 'weight' => '300', 'style' => 'normal', 'role' => 'Cuerpo' ), array( 'family' => 'Roboto', 'weight' => '300', 'style' => 'normal', 'role' => 'Texto global' ) );
$report1['faces'][] = array( 'family' => 'Roboto', 'weight' => '300', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'css' => 'fonts.css', 'url' => $base1 . 'r300.woff2' );
$manifest = $preload::rebuild( $report1 );
check( array() === $manifest['rows'], 'Una variante crítica bloqueada por la whitelist no se precarga.' );
$GLOBALS['sites'][1]['settings']['perf_font_guard_allowlist'] = 'Roboto|300|normal';
$manifest = $preload::rebuild( $report1 );
check( array( $base1 . 'r300.woff2' ) === array_column( $manifest['rows'], 'url' ), 'Autorizada por excepción, la variante crítica 300 se precarga una sola vez.' );
list( , $errors ) = $preload::validate_rows( array( array( 'url' => $base1 . 'r300.woff2' ), array( 'url' => $base1 . 'r300.woff2?ver=2' ), array( 'url' => $base1 . 'r900.woff2' ) ), array( $base1 . 'r300.woff2' => true, $base1 . 'r300.woff2?ver=2' => true ) );
check( 2 === count( $errors ), 'La validación rechaza preloads duplicados y no autorizados.' );

// ---------------------------------------------------------------------------
// Debug: estados combinados por variante.
$GLOBALS['sites'][1]['settings']['perf_font_guard_allowlist'] = '';
$report1['critical'] = $fonts::critical_from_settings( array( 'body_typography_font_family' => 'Roboto' ) );
$guard::build( true, $report1 );
$preloaded = array( 'roboto|400|normal' => true );
check( array( 'PERMITIDO', 'CRÍTICO', 'PRELOAD' ) === $debug::font_states( $report1['faces'][1], $report1, $preloaded ), 'Una variante crítica precargada muestra los tres estados.' );
check( array( 'BLOQUEADO' ) === $debug::font_states( $report1['faces'][2], $report1, $preloaded ), 'Una variante retirada de una copia vigente figura bloqueada.' );
check( array( 'ICON FONT' ) === $debug::font_states( $report1['faces'][4], $report1, $preloaded ), 'Los icon fonts van por su propia ruta.' );
$GLOBALS['blog'] = 2;
check( in_array( 'EXCEPCIÓN MANUAL', $debug::font_states( array( 'family' => 'Playfair Display', 'weight' => '700', 'style' => 'italic' ), $report2, array() ), true ), 'Las excepciones manuales se señalan.' );

// ---------------------------------------------------------------------------
// Fuente variable: todos los pesos de un estilo comparten archivo, como Inter
// o Source Serif 4 en Google Fonts. Bloquear 700 no evita ninguna descarga.
$css4 = array(
	face( 'Var Sans', 400, 'normal', 'var-normal' ),
	face( 'Var Sans', 700, 'normal', 'var-normal' ),
	face( 'Var Sans', 900, 'normal', 'var-normal' ),
	face( 'Var Sans', 400, 'italic', 'var-italic' ),
	face( 'Var Sans', 700, 'italic', 'var-italic' ),
);
$base4 = 'https://red.test/site-4/uploads/elementor/google-fonts/fonts/';
$report4 = site( 4, array( 'perf_font_guard_mode' => 'strict' ), array(
	'families' => array( array( 'family' => 'Var Sans', 'weights' => array( '400' ), 'styles' => array( 'normal' ) ) ),
	'faces'    => array(
		array( 'family' => 'Var Sans', 'weight' => '400', 'style' => 'normal', 'css' => 'fonts.css', 'url' => $base4 . 'var-normal.woff2' ),
		array( 'family' => 'Var Sans', 'weight' => '700', 'style' => 'normal', 'css' => 'fonts.css', 'url' => $base4 . 'var-normal.woff2' ),
		array( 'family' => 'Var Sans', 'weight' => '400', 'style' => 'italic', 'css' => 'fonts.css', 'url' => $base4 . 'var-italic.woff2' ),
	),
), $css4 );
$result = $guard::build( true, $report4 );
$out4 = generated( 4 );
check( 2 === $result['removed'] && 0 === $result['invalid'], 'Sólo se retiran las reglas cuyo archivo no usa ninguna variante permitida.' );
check( 3 === substr_count( $out4, 'var-normal' ) && false === strpos( $out4, 'var-italic' ), 'Los pesos que comparten archivo con una variante permitida se conservan; el estilo sin uso se retira.' );
check( array( 'PERMITIDO', 'FUENTE VARIABLE' ) === $debug::font_states( $report4['faces'][1], $report4, array() ), 'Un peso no detectado de una fuente variable se muestra permitido por compartir archivo.' );
check( array( 'BLOQUEADO' ) === $debug::font_states( $report4['faces'][2], $report4, array() ), 'Un archivo que sólo usan variantes bloqueadas sigue bloqueado.' );
check( array( 'PERMITIDO', 'FUENTE VARIABLE', 'PRELOAD' ) === $debug::font_states( $report4['faces'][0], $report4, array( 'var sans|400|normal' => true ) ) && ! in_array( 'PRELOAD', $debug::font_states( $report4['faces'][1], $report4, array( 'var sans|400|normal' => true ) ), true ), 'PRELOAD se marca en la variante elegida, no en cada peso del mismo archivo.' );
check( array( 'var sans|400|normal' => true ) === $debug::preloaded_variants( array( 'rows' => array( array( 'family' => 'Var Sans', 'variant' => '400 normal' ) ) ) ), 'Un manifest anterior sin peso explícito se interpreta por su variante.' );
$groups = $debug::group_faces( array(
	array( 'family' => 'Var Sans', 'weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0100-024F', 'url' => 'ext' ),
	array( 'family' => 'Var Sans', 'weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'url' => 'latin' ),
	array( 'family' => 'Var Sans', 'weight' => '700', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'url' => 'latin' ),
) );
check( 2 === count( $groups ) && 2 === $groups[0]['subsets'] && 'latin' === $groups[0]['face']['url'], 'El debug agrupa los subsets de una variante y la representa con el latino.' );

// ---------------------------------------------------------------------------
// Negritas y cursivas del contenido: variantes reales que el Kit no declara.
check( array( 'bold' => true, 'italic' => false ) === $fonts::inline_formatting( '<p>Hola <strong>mundo</strong><br><img src="x"></p>' ), '<strong> cuenta como negrita; <br> e <img> no.' );
check( array( 'bold' => false, 'italic' => true ) === $fonts::inline_formatting( '{"editor":"\\u003cem\\u003eHola\\u003c\\/em\\u003e"}' ), 'También se reconoce el formato escapado en el JSON de Elementor.' );
check( array( 'bold' => false, 'italic' => false ) === $fonts::inline_formatting( '<blockquote><button><iframe>' ), 'Etiquetas que empiezan por b o i no son formato.' );
$extra = $fonts::inline_families( $fonts::critical_from_settings( array( 'body_typography_font_family' => 'Roboto', 'h1_typography_font_family' => 'Lora', 'h1_typography_font_weight' => '600' ) ), array( 'bold' => true, 'italic' => true ) );
check( array( array( 'family' => 'Roboto', 'weights' => array( '400', '700' ), 'styles' => array( 'normal', 'italic' ) ) ) === $extra, 'El formato en línea amplía sólo la familia del texto corrido, no la de los títulos.' );

// ---------------------------------------------------------------------------
// Google Fonts remoto: el sitio no tiene Elementor en local y pide todo a Google.
$GLOBALS['blog'] = 1;
$rules = array( 'inter' => array( 'weights' => array( '400', '600' ), 'styles' => array( 'normal', 'italic' ) ), 'source serif 4' => array( 'weights' => array( '400' ), 'styles' => array( 'normal' ) ) );
$all = '100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic';
$elementor = 'https://fonts.googleapis.com/css?family=Inter:' . $all . '%7CSource+Serif+4:' . $all . '&display=swap';
check( 'https://fonts.googleapis.com/css?family=Inter:400,400italic,600,600italic%7CSource+Serif+4:400&display=swap' === $guard::google_fonts_url( $elementor, $rules, 'strict', '' ), 'La URL v1 de Elementor debe pedir sólo las variantes autorizadas de cada familia.' );
check( 'https://fonts.googleapis.com/css?family=Inter:400,600%7CLora:400,700&subset=latin' === $guard::google_fonts_url( 'https://fonts.googleapis.com/css?family=Inter:400,700,600%7CLora:400,700&subset=latin', $rules, 'auto', '' ), 'En modo seguro una familia no detectada se pide completa.' );
check( 'https://fonts.googleapis.com/css?family=Inter:400&display=swap' === $guard::google_fonts_url( 'https://fonts.googleapis.com/css?family=Inter:400%7CLora:400,700&display=swap', $rules, 'strict', '' ), 'En modo estricto una familia no autorizada sale de la URL.' );
check( false === $guard::google_fonts_url( 'https://fonts.googleapis.com/css?family=Lora:400,700', $rules, 'strict', '' ), 'Sin familias autorizadas la hoja no se imprime.' );
check( 'https://fonts.googleapis.com/css?family=Inter:regular,600italic,weird' === $guard::google_fonts_url( 'https://fonts.googleapis.com/css?family=Inter:regular,bold,600italic,weird', $rules, 'strict', '' ), 'Los alias de v1 se interpretan y un token desconocido se conserva.' );
check( 'https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;1,600&family=Material+Symbols+Outlined:opsz,wght@20..48,100..700&display=swap' === $guard::google_fonts_url( 'https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;1,600;1,900&family=Material+Symbols+Outlined:opsz,wght@20..48,100..700&display=swap', $rules, 'strict', '' ), 'css2 filtra sus tuplas y nunca toca un icon font.' );
check( 'https://fonts.googleapis.com/css2?family=Inter:wght@100..900' === $guard::google_fonts_url( 'https://fonts.googleapis.com/css2?family=Inter:wght@100..900', $rules, 'strict', '' ), 'Un rango variable no se recorta.' );
check( 'https://example.test/fonts.css' === $guard::google_fonts_url( 'https://example.test/fonts.css', $rules, 'strict', '' ), 'Una hoja que no es de Google no se toca.' );

// Un sitio sin CSS local también se optimiza: su whitelist reescribe la URL remota.
$GLOBALS['blog'] = 5;
$GLOBALS['sites'][5]['settings'] = array( 'perf_font_guard_mode' => 'strict', 'perf_safe_mode' => 0, 'perf_font_guard_allowlist' => '', 'perf_font_icon_families' => '' );
$report5 = array( 'scanned_at' => 'now', 'complete' => true, 'kit_families' => array(), 'uploads_baseurl' => 'https://red.test/site-5/uploads/', 'faces' => array(), 'families' => array( array( 'family' => 'Lora', 'weights' => array( '400' ), 'styles' => array( 'normal' ) ) ) );
$result = $guard::build( true, $report5 );
check( ! empty( $result['remote_only'] ) && empty( $result['error'] ), 'Sin Google Fonts en local, la generación no es un error.' );
check( 'https://fonts.googleapis.com/css?family=Lora:400&display=swap' === $guard::style_src( 'https://fonts.googleapis.com/css?family=Lora:400,400italic,700%7CInter:400&display=swap', 'google-fonts-1' ), 'El frontend del sitio remoto recibe la URL recortada con su propia whitelist.' );
check( $guard::status()['remote'], 'El estado debe indicar que el sitio está optimizado en remoto.' );
$GLOBALS['blog'] = 1;
check( false === $guard::google_fonts_url( 'https://fonts.googleapis.com/css?family=Lora:400,700', get_option( $guard::OPTION )['whitelist'], 'strict', '' ), 'La whitelist del sitio 1 no contiene la familia del sitio 5.' );

// ---------------------------------------------------------------------------
// Recálculo de toda la red: cada sitio con su modo; los apagados se omiten.
$GLOBALS['sites'][6]['settings'] = array( 'perf_font_guard_mode' => 'off' );
foreach ( array_keys( $GLOBALS['sites'] ) as $id ) unset( $GLOBALS['sites'][ $id ]['cron'] );
try { $guard::recalculate_network_action(); } catch ( Redirected $e ) {}
check( false !== strpos( $GLOBALS['redirect'], 'font_network_scheduled=5' ) && false !== strpos( $GLOBALS['redirect'], 'font_network_skipped=1' ), 'Se programan los cinco sitios activos y se omite el apagado.' );
check( ! empty( $GLOBALS['sites'][3]['cron'][ $preload::CRON ] ) && empty( $GLOBALS['sites'][6]['cron'] ), 'El recálculo se agenda en el cron de cada sitio, no en el de la red.' );
check( 'auto' === ( $GLOBALS['sites'][3]['options'][ $guard::APPROVAL ]['mode'] ?? '' ) && 'strict' === ( $GLOBALS['sites'][2]['options'][ $guard::APPROVAL ]['mode'] ?? '' ), 'Cada sitio queda aprobado con su propio modo.' );
check( 1 === $GLOBALS['blog'], 'La red vuelve al sitio de origen.' );

// ---------------------------------------------------------------------------
// Icon fonts reconocidos sin configuración, y texto que no lo es.
foreach ( array( 'eicons', 'Font Awesome 6 Free', 'Material Symbols Rounded', 'Material Icons', 'dashicons', 'Line Awesome Free', 'icomoon', 'WooCommerce', 'star' ) as $family ) check( $guard::icon_family( $family, '' ), $family . ' es un icon font.' );
foreach ( array( 'Roboto', 'Montserrat', 'Playfair Display', 'Inter' ) as $family ) check( ! $guard::icon_family( $family, '' ), $family . ' no es un icon font.' );

$cleanup = function( $dir ) use ( &$cleanup ) { foreach ( glob( $dir . '/*' ) as $path ) is_dir( $path ) ? $cleanup( $path ) : unlink( $path ); rmdir( $dir ); };
$cleanup( $GLOBALS['root'] );
echo "Política de fuentes: familias arbitrarias, variantes exactas, multisite, validación y preloads críticos correctos.\n";
