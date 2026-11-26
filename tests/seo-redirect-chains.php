<?php
/**
 * A4 de la auditoría SEO (2026-11-24): redirecciones al cambiar un enlace sin cadenas ni bucles, sin base de datos.
 *   - A→B y B→C: A va directo a C (al servir y en la tabla); A→B→A nunca se sirve.
 *   - Una 301 de cambio de enlace sólo cuenta mientras su dirección no exista (la cadena se corta en una ruta viva).
 *   - La importación de Rank Math ya no apaga el vigilante (Rank Math lo trae apagado de fábrica) y la migración lo
 *     vuelve a encender sólo en los sitios que lo perdieron así.
 */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['options'] = array();
$GLOBALS['wp_options'] = array();
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? (string) $url : ''; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function add_action() {}
function get_option( $key, $default = false ) { return $GLOBALS['wp_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['wp_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['wp_options'][ $key ] ); return true; }
function get_current_user_id() { return 7; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; } }
class Digitalisimo_Integrations_Settings { const OPTION = 'digitalisimo_integrations_options'; }
require __DIR__ . '/../digitalisimo-seo/includes/class-redirections.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$r = Digitalisimo_Integrations_Redirections::class;

$rule = function( $id, $from, $to, $origin = 'slug', $code = 301 ) { return array( 'id' => $id, 'sources' => json_encode( array( array( 'pattern' => $from, 'comparison' => 'exact', 'ignore' => '' ) ) ), 'url_to' => $to, 'header_code' => $code, 'origin' => $origin ); };

// El índice guarda de dónde salió cada regla.
$index = $r::build_index( array( $rule( 3, 'b', '/c/' ), $rule( 2, 'a', '/b/', 'manual' ) ) );
check( 'slug' === $index['rules'][3]['o'] && 'manual' === $index['rules'][2]['o'], 'El índice anota el origen de cada regla.' );

// Destinos del propio sitio.
check( 'b' === $r::own_path( '/b/', 'sitio.test' ), 'Una ruta es del sitio.' );
check( 'b' === $r::own_path( 'https://SITIO.test/b/', 'sitio.test' ), 'Una URL con el mismo host es del sitio.' );
check( null === $r::own_path( 'https://otro.test/b/', 'sitio.test' ) && null === $r::own_path( '//otro.test/b', 'sitio.test' ), 'Otro host no es del sitio.' );
check( null === $r::own_path( '/art/$1', 'sitio.test' ), 'Un destino con referencias no se compara.' );
check( 'pagina' === $r::own_path( '/sub/pagina/', 'sitio.test', '/sub/' ), 'En un subdirectorio se descuenta la base.' );

// A→B y B→C: A va a C en un solo salto.
$follow = function( $index, $to, $from, $alive = null ) use ( $r ) { return $r::follow( $index, $to, $from, 'sitio.test', '/', $alive ); };
check( '/c/' === $follow( $index, '/b/', 'a' ), 'A→B y B→C: A va directo a C.' );
$long = $r::build_index( array( $rule( 4, 'c', '/d/' ), $rule( 3, 'b', '/c/' ), $rule( 2, 'a', '/b/' ) ) );
check( '/d/' === $follow( $long, '/b/', 'a' ), 'Una cadena larga se recorre hasta el final.' );
// A→B→A: nunca se sirve.
$loop = $r::build_index( array( $rule( 3, 'b', '/a/' ), $rule( 2, 'a', '/b/' ) ) );
check( '' === $follow( $loop, '/b/', 'a' ), 'A→B→A es un bucle: no se redirige.' );
$tri = $r::build_index( array( $rule( 4, 'c', '/b/' ), $rule( 3, 'b', '/c/' ), $rule( 2, 'a', '/b/' ) ) );
check( '' === $follow( $tri, '/b/', 'a' ), 'A→B→C→B es un bucle: no se redirige.' );
// Una 301 de cambio de enlace no cuenta si su ruta vuelve a existir.
check( '/b/' === $follow( $index, '/b/', 'a', function( $p ) { return 'b' === $p; } ), 'Si B volvió a existir, A se queda en B.' );
check( '/c/' === $follow( $r::build_index( array( $rule( 3, 'b', '/c/', 'manual' ), $rule( 2, 'a', '/b/' ) ) ), '/b/', 'a', function( $p ) { return true; } ), 'Una regla manual sobre una ruta viva sí se sigue: se aplica siempre.' );
// Un 410 o un destino externo terminan la cadena.
check( '/b/' === $follow( $r::build_index( array( $rule( 3, 'b', '', 'manual', 410 ), $rule( 2, 'a', '/b/' ) ) ), '/b/', 'a' ), 'Un 410 termina la cadena.' );
check( 'https://otro.test/x' === $follow( $r::build_index( array( $rule( 3, 'b', 'https://otro.test/x', 'manual' ), $rule( 2, 'a', '/b/' ) ) ), '/b/', 'a' ), 'Un destino externo termina la cadena.' );
check( '/b/' === $follow( $r::build_index( array() ), '/b/', 'a' ), 'Sin más reglas, el destino queda igual.' );

// En la tabla: al mover B→C, las reglas del vigilante que llevaban a B pasan a C con su query y su ancla; la que sale
// de C (B→C→B) se desactiva. Las manuales, de CSV o importadas no se tocan: sólo se avisa.
$row = function( $id, $from, $to, $origin ) { return array( 'id' => $id, 'sources' => json_encode( array( array( 'pattern' => $from, 'comparison' => 'exact', 'ignore' => '' ) ) ), 'url_to' => $to, 'origin' => $origin ); };
$rows = array(
	$row( 1, 'a', '/b/', 'slug' ),
	$row( 2, 'x', 'https://sitio.test/b?utm_source=correo#precios', 'slug' ),
	$row( 3, 'c', '/b/', 'slug' ),
	$row( 4, 'y', '/b-otra/', 'slug' ),
	$row( 5, 'z', 'https://otro.test/b/', 'slug' ),
	$row( 6, 'm', '/b/', 'manual' ),
	$row( 7, 'v', '/b/?campana=1', 'csv' ),
	$row( 8, 'w', '/b/', 'rankmath' ),
);
$plan = $r::retarget( $rows, 'b', 'c', '/c/', 'sitio.test' );
check( array( 1 => '/c/', 2 => '/c/?utm_source=correo#precios' ) === $plan['update'], 'Las reglas del vigilante que llevaban a B pasan a C sin perder su query: ' . json_encode( $plan ) );
check( array( 3 ) === $plan['deactivate'], 'La regla C→B se desactiva: con B→C sería un bucle.' );
check( array( 6, 7, 8 ) === $plan['notice'], 'Las reglas manuales, de CSV e importadas no se modifican: sólo se avisa.' );

// Rank Math: el vigilante sólo se importa encendido.
require __DIR__ . '/../digitalisimo-seo/includes/class-rankmath-import.php';
$source = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-rankmath-import.php' );
check( false === strpos( $source, "\$want['seo_redirect_slug_watch'] = self::on( \$g['redirections_post_redirect'] ) ? 1 : 0" ), 'La importación ya no copia el vigilante apagado de Rank Math.' );

// Migración: vuelve a encender el vigilante sólo en un sitio que lo perdió por la importación.
$GLOBALS['wp_options'] = array( 'digitalisimo_integrations_options' => array( 'seo_redirect_slug_watch' => 0, 'otra' => 'x' ), 'digitalisimo_seo_rankmath_job' => array( 'phase' => 'done' ), 'rank-math-options-general' => array( 'redirections_post_redirect' => 'off' ) );
check( true === $r::restore_slug_watch() && 1 === $GLOBALS['wp_options']['digitalisimo_integrations_options']['seo_redirect_slug_watch'] && 'x' === $GLOBALS['wp_options']['digitalisimo_integrations_options']['otra'] && ! empty( $GLOBALS['wp_options'][ $r::RESTORED ] ), 'Un sitio que importó de Rank Math recupera el vigilante.' );
$GLOBALS['wp_options'] = array( 'digitalisimo_integrations_options' => array( 'seo_redirect_slug_watch' => 0 ) );
check( false === $r::restore_slug_watch() && 0 === $GLOBALS['wp_options']['digitalisimo_integrations_options']['seo_redirect_slug_watch'], 'Un sitio que nunca importó y lo apagó, lo conserva apagado.' );
$GLOBALS['wp_options'] = array( 'digitalisimo_integrations_options' => array( 'seo_redirect_slug_watch' => 0 ), 'digitalisimo_seo_rankmath_job' => array( 'phase' => 'done' ), 'rank-math-options-general' => array( 'redirections_post_redirect' => 'on' ) );
check( false === $r::restore_slug_watch(), 'Si Rank Math lo tenía encendido, el apagado no vino de la importación.' );
// El dueño lo apagó a propósito: nunca se vuelve a encender, aunque el sitio haya importado de Rank Math.
$GLOBALS['wp_options'] = array( 'digitalisimo_integrations_options' => array( 'seo_redirect_slug_watch' => 1 ), 'digitalisimo_seo_rankmath_job' => array( 'phase' => 'done' ), 'rank-math-options-general' => array( 'redirections_post_redirect' => 'off' ) );
$r::watch_toggle( array( 'seo_redirect_slug_watch' => 1 ), array( 'seo_redirect_slug_watch' => 0 ) );
check( 'owner' === ( $GLOBALS['wp_options'][ $r::WATCH_OFF ]['by'] ?? '' ) && 7 === $GLOBALS['wp_options'][ $r::WATCH_OFF ]['user'], 'Al apagarlo en los ajustes se anota quién lo apagó.' );
$GLOBALS['wp_options']['digitalisimo_integrations_options']['seo_redirect_slug_watch'] = 0;
check( false === $r::restore_slug_watch() && 0 === $GLOBALS['wp_options']['digitalisimo_integrations_options']['seo_redirect_slug_watch'], 'Lo que el dueño apagó no se vuelve a encender.' );
$r::watch_toggle( array( 'seo_redirect_slug_watch' => 0 ), array( 'seo_redirect_slug_watch' => 1 ) );
check( ! isset( $GLOBALS['wp_options'][ $r::WATCH_OFF ] ), 'Encenderlo borra la marca.' );
$r::watch_toggle( array( 'otra' => 1 ), array( 'otra' => 2 ) );
check( ! isset( $GLOBALS['wp_options'][ $r::WATCH_OFF ] ), 'Guardar otros ajustes no anota nada.' );
$GLOBALS['wp_options'] = array( 'digitalisimo_integrations_options' => array( 'otra' => 'x' ), 'digitalisimo_seo_rankmath_job' => array( 'phase' => 'done' ) );
check( false === $r::restore_slug_watch() && ! isset( $GLOBALS['wp_options']['digitalisimo_integrations_options']['seo_redirect_slug_watch'] ), 'Sin valor guardado (usa el predeterminado, encendido) no se escribe nada.' );

echo "Redirecciones sin cadenas ni bucles y vigilante a salvo de la importación: correcto.\n";
