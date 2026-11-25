<?php
/**
 * Imágenes de marca (auditoría SEO del 2026-11-24, punto A1), sin WordPress:
 *   1. el front no hace peticiones: og:image, twitter:image y el logo e image del schema sólo leen la comprobación guardada;
 *   2. la comprobación corre en el cron: una imagen en un dominio que no existe, o que responde una página en lugar de
 *      una imagen, cae a la de reserva comprobada (el logo del tema); un logo de demo (Softaculous) no se publica;
 *   3. sólo un fallo inequívoco (el dominio no existe, 404 o 410, no es una imagen, el archivo ya no está) quita la
 *      imagen, y sólo si se repite en otra comprobación al menos media hora después; lo dudoso (403, 401, 429, 5xx, sin
 *      respuesta, peticiones bloqueadas) nunca la quita: se publica y se avisa; se pide con agente de navegador; una
 *      imagen en otro dominio que sí responde se publica, con aviso;
 *   4. los avisos salen en el Escritorio (con la X y por sitio) y en el MCP; un sitio sin imágenes de marca no cambia.
 * Dominios y nombres inventados.
 */
require_once __DIR__ . '/fixtures/php74.php';
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$tmp = sys_get_temp_dir() . '/digi-brand-' . getmypid();
define( 'WP_CONTENT_DIR', $tmp . '/wp-content' );
@mkdir( WP_CONTENT_DIR . '/uploads/2026/01', 0777, true );

function png( $file, $w, $h ) { file_put_contents( $file, "\x89PNG\r\n\x1a\n" . pack( 'N', 13 ) . 'IHDR' . pack( 'NN', $w, $h ) . "\x08\x06\x00\x00\x00" . "\0\0\0\0" ); }
png( WP_CONTENT_DIR . '/uploads/2026/01/logo-marca-softaculous-demo.png', 120, 120 );
png( WP_CONTENT_DIR . '/uploads/2026/01/logo-oficial.png', 400, 400 );
png( WP_CONTENT_DIR . '/uploads/2026/01/logo-chico.png', 60, 40 );

$GLOBALS['settings'] = array();
$GLOBALS['options']  = array();
$GLOBALS['cron']     = array();
$GLOBALS['http']     = array();
$GLOBALS['remote']   = array();
$GLOBALS['theme']    = array();
$GLOBALS['attach']   = array( 11 => 'https://cliente-ejemplo.test/wp-content/uploads/2026/01/logo-oficial.png' );
$GLOBALS['notices']  = array();
$GLOBALS['screen']   = (object) array( 'id' => 'dashboard' );
$GLOBALS['meta']     = array();

class Digitalisimo_Integrations_Settings { public static function get( $key, $fallback = '' ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; } }
class Digitalisimo_Integrations_SEO_Resolver {
	public static function option( $key, $default = null ) { return Digitalisimo_Integrations_Settings::get( $key, $default ); }
	public static function post( $id, $key, $default = '' ) { return $default; }
	public static function foreign_seo() { return ''; }
}
class Digitalisimo_Notices {
	public static function site( $id ) { return $id . '-s1'; }
	public static function show( $id, $type, $content, array $args = array() ) { $GLOBALS['notices'][] = compact( 'id', 'type', 'content', 'args' ); return true; }
}
class WP_Error { private $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } public function get_error_message() { return $this->m; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; $GLOBALS['autoload'][ $key ] = $autoload; return true; }
function get_theme_mod( $key, $default = false ) { return $GLOBALS['theme'][ $key ] ?? $default; }
function wp_get_attachment_image_url( $id, $size ) { return $GLOBALS['attach'][ $id ] ?? false; }
function home_url( $path = '' ) { return 'https://cliente-ejemplo.test' . $path; }
function admin_url( $path = '' ) { return 'https://cliente-ejemplo.test/wp-admin/' . $path; }
function content_url() { return 'https://cliente-ejemplo.test/wp-content'; }
function wp_get_upload_dir() { return array( 'baseurl' => 'https://cliente-ejemplo.test/wp-content/uploads', 'basedir' => WP_CONTENT_DIR . '/uploads' ); }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['cron'][ $hook . json_encode( $args ) ] ?? false; }
function wp_schedule_single_event( $time, $hook, $args = array() ) { $GLOBALS['cron'][ $hook . json_encode( $args ) ] = $time; return true; }
function wp_safe_remote_head( $url, $args ) { $GLOBALS['http'][] = 'HEAD ' . $url; $GLOBALS['http_args'] = $args; return $GLOBALS['remote'][ $url ] ?? new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' ); }
function wp_safe_remote_get( $url, $args ) { $GLOBALS['http'][] = 'GET ' . $url; $GLOBALS['http_args'] = $args; return $GLOBALS['remote'][ $url ] ?? new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_header( $r, $h ) { return $r['type'] ?? ''; }
function current_user_can( $cap ) { return true; }
function is_network_admin() { return false; }
function get_current_screen() { return $GLOBALS['screen']; }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function wp_unslash( $v ) { return $v; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function wp_list_pluck( $list, $field ) { return array_map( function( $r ) use ( $field ) { return $r[ $field ]; }, $list ); }
function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function get_the_post_thumbnail_url( $id, $size ) { return false; }
function get_locale() { return 'es_MX'; }
function is_singular( $type = '' ) { return true; }
function get_bloginfo( $key = '' ) { return 'Cliente Ejemplo'; }

require __DIR__ . '/../digitalisimo-seo/includes/class-brand-check.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php';
$B = 'Digitalisimo_Integrations_Brand_Check';

$fails = 0;
function check( $ok, $message ) { global $fails; if ( $ok ) return; $fails++; fwrite( STDERR, "FALLA: {$message}\n" ); }
function codes() { return array_column( Digitalisimo_Integrations_Brand_Check::warnings(), 'code' ); }
function social_html() {
	$social = digi_test_accessible( new ReflectionMethod( 'Digitalisimo_Integrations_SEO_Suite', 'social' ) );
	ob_start(); $social->invoke( null, array( 'id' => 5, 'title' => 'Página', 'description' => 'Descripción.', 'canonical' => 'https://cliente-ejemplo.test/pagina/' ) ); return ob_get_clean();
}

// 4. Un sitio sin imágenes de marca no cambia: nada que comprobar ni avisar.
check( '' === $B::url( 'image' ) && '' === $B::url( 'logo' ), 'Sin imágenes configuradas no se publica ninguna.' );
$GLOBALS['theme']['custom_logo'] = 11;
check( false === $B::refresh() && ! $GLOBALS['cron'], 'Sin imagen social ni logo de SEO no se programa nada, aunque el tema tenga logo.' );
check( array() === $B::warnings(), 'Sin imágenes configuradas no hay avisos.' );

// Imágenes como las de la auditoría: la social en un dominio que ya no existe y el logo de una demo.
// .invalid nunca resuelve (RFC 2606): es el dominio que ya no existe.
$dead = 'https://media.marca-anterior.invalid/wp-content/uploads/2023/11/Imagotipo.webp';
$demo = 'https://cliente-ejemplo.test/wp-content/uploads/2026/01/logo-marca-softaculous-demo.png';
$real = 'https://cliente-ejemplo.test/wp-content/uploads/2026/01/logo-oficial.png';
$GLOBALS['settings'] = array( 'seo_default_image' => $dead, 'seo_logo' => $demo, 'seo_twitter_enabled' => 1 );
$GLOBALS['theme']['custom_logo'] = 11;

// 1. Antes de la primera comprobación: se publica lo configurado, salvo el logo de demo; ninguna petición.
check( $dead === $B::url( 'image' ), 'Sin comprobar, la imagen configurada se publica como antes.' );
check( '' === $B::url( 'logo' ), 'Un logo con nombre de demo no se publica ni antes de comprobarlo.' );
$html = social_html();
check( false !== strpos( $html, '<meta property="og:image" content="' . $dead . '">' ) && false !== strpos( $html, '<meta name="twitter:image" content="' . $dead . '">' ), 'og:image y twitter:image salen juntos: ' . $html );
check( array() === $GLOBALS['http'], 'El front no hace peticiones HTTP.' );

// Guardar o abrir el escritorio programa la comprobación; no la corre.
check( true === $B::refresh() && isset( $GLOBALS['cron'][ $B::HOOK . '[]' ] ), 'Con imágenes nuevas se programa la comprobación en el cron.' );
check( array() === $GLOBALS['http'], 'Programar no hace peticiones.' );

/** Pasa una hora: la próxima comprobación ya cuenta como «otra comprobación separada». */
function pasa_una_hora() { foreach ( $GLOBALS['options'][ Digitalisimo_Integrations_Brand_Check::OPTION ]['urls'] as &$row ) if ( isset( $row['gone_since'] ) ) $row['gone_since'] -= 3600; }

// 2. El cron comprueba.
$state = $B::run();
check( in_array( 'HEAD ' . $dead, $GLOBALS['http'], true ) && in_array( 'GET ' . $dead, $GLOBALS['http'], true ), 'Una imagen de otro dominio se pide por HTTP (HEAD y, si falla, GET).' );
check( 0 === strpos( (string) ( $GLOBALS['http_args']['user-agent'] ?? '' ), 'Mozilla/5.0' ), 'Se pide con el agente de un navegador normal.' );
check( ! preg_grep( '/cliente-ejemplo\.test/', $GLOBALS['http'] ), 'Las imágenes de la biblioteca de este sitio se comprueban en el disco, sin peticiones.' );
check( true === $GLOBALS['autoload'][ $B::OPTION ], 'El resultado se guarda con autoload: el front lo lee sin consulta de más.' );
check( 'gone' === $state['urls'][ $dead ]['verdict'] && $state['urls'][ $dead ]['offsite'], 'El dominio que no existe es un fallo inequívoco y de otro dominio.' );
check( $dead === $B::url( 'image' ) && isset( $GLOBALS['cron'][ $B::HOOK . '["retry"]' ] ), 'La primera vez no se quita: se sigue publicando y se reintenta en una hora.' );
check( in_array( 'image_pending', codes(), true ), 'Se avisa que parece rota y se volverá a comprobar.' );
check( $real === $B::url( 'logo' ), 'El logo de demo (Softaculous) cae al logo del tema: ' . $B::url( 'logo' ) );
$B::run();
check( $dead === $B::url( 'image' ), 'Dos comprobaciones seguidas, sin tiempo entre ellas, no bastan.' );
pasa_una_hora();
$B::run( $B::RETRY );
check( $real === $B::url( 'image' ), 'Confirmado una hora después, cae al logo del tema: ' . $B::url( 'image' ) );
$GLOBALS['http'] = array();
$html = social_html();
check( false !== strpos( $html, 'og:image" content="' . $real ) && false !== strpos( $html, 'twitter:image" content="' . $real ) && false === strpos( $html, 'marca-anterior' ), 'og:image y twitter:image usan la de reserva: ' . $html );
check( array() === $GLOBALS['http'], 'El front sigue sin peticiones después de la comprobación.' );
check( isset( $GLOBALS['cron'][ $B::HOOK . '["daily"]' ] ), 'La comprobación se repite cada día.' );
$codes = codes();
check( in_array( 'image_broken', $codes, true ) && in_array( 'image_offsite', $codes, true ) && in_array( 'logo_demo', $codes, true ), 'Avisos de imagen rota, de otro dominio y de logo de demo: ' . implode( ', ', $codes ) );
$messages = implode( ' ', array_column( $B::warnings(), 'message' ) );
check( false !== strpos( $messages, 'Mientras tanto se publica ' . $real ), 'El aviso dice qué se publica en su lugar.' );

// Sin reserva buena: no se publica imagen (mejor que una rota).
$GLOBALS['theme']['custom_logo'] = 0;
$B::run();
check( '' === $B::url( 'image' ) && '' === $B::url( 'logo' ), 'Sin reserva buena no se publica imagen ni logo.' );
check( false === strpos( social_html(), 'og:image' ), 'Sin imagen buena no sale og:image.' );
$GLOBALS['theme']['custom_logo'] = 11;

// Un dominio estacionado responde 200 con una página: no es una imagen (inequívoco, también con confirmación).
$parked = 'https://media.marca-estacionada.test/logo.png';
$GLOBALS['settings']['seo_default_image'] = $parked;
$GLOBALS['remote'][ $parked ] = array( 'code' => 200, 'type' => 'text/html; charset=UTF-8' );
$B::run();
check( $parked === $B::url( 'image' ), 'Una página en lugar de una imagen: la primera vez se sigue publicando.' );
pasa_una_hora(); $B::run();
check( $real === $B::url( 'image' ), 'Confirmado, cuenta como rota.' );
$GLOBALS['remote'][ $parked ] = array( 'code' => 404 );
pasa_una_hora(); $B::run();
check( $real === $B::url( 'image' ) && false === strpos( (string) $B::state()['urls'][ $parked ]['error'], 'html' ), 'Un 404 también es inequívoco.' );

// Lo dudoso nunca quita la imagen: 403 de un cortafuegos, 401, 429, 5xx, sin respuesta o peticiones bloqueadas.
$cdn = 'https://cdn.proveedor-ejemplo.test/marca/social.png';
$GLOBALS['settings']['seo_default_image'] = $cdn;
foreach ( array( 403, 401, 429, 500, 503 ) as $code ) {
	$GLOBALS['remote'][ $cdn ] = array( 'code' => $code, 'type' => 'text/html' );
	$B::run(); pasa_una_hora(); $B::run();
	check( $cdn === $B::url( 'image' ) && in_array( 'image_unchecked', codes(), true ) && ! in_array( 'image_broken', codes(), true ), "Un {$code} repetido no quita la imagen: se publica y se avisa." );
}
check( false !== strpos( implode( ' ', array_column( $B::warnings(), 'message' ) ), 'no pudimos comprobarla; puede estar bien' ), 'El aviso lo dice en lenguaje claro.' );
$blocked = 'https://127.0.0.1/marca/social.png';
$GLOBALS['settings']['seo_default_image'] = $blocked;
$GLOBALS['remote'][ $blocked ] = new WP_Error( 'http_request_blocked', 'User has blocked requests through HTTP.' );
$B::run(); pasa_una_hora(); $B::run();
check( $blocked === $B::url( 'image' ) && in_array( 'image_unchecked', codes(), true ), 'Con WP_HTTP_BLOCK_EXTERNAL o un corte de red la imagen se sigue publicando.' );

// 3. Otro dominio que sí responde una imagen (un CDN): se publica, con aviso.
$GLOBALS['settings']['seo_default_image'] = $cdn;
$GLOBALS['remote'][ $cdn ] = array( 'code' => 200, 'type' => 'image/png' );
$B::run();
check( $cdn === $B::url( 'image' ), 'Una imagen de otro dominio que responde se publica.' );
check( in_array( 'image_offsite', codes(), true ) && ! array_intersect( array( 'image_broken', 'image_unchecked', 'image_pending' ), codes() ), 'Se avisa que está en otro dominio, no que esté rota.' );
check( $B::same_site( 'cdn.cliente-ejemplo.test', 'cliente-ejemplo.test' ) && ! $B::same_site( 'cliente-ejemplo.test.evil.test', 'cliente-ejemplo.test' ), 'Un subdominio propio es el mismo sitio; un dominio que sólo lo contiene, no.' );

// Logo de menos de 112 px.
$GLOBALS['settings']['seo_logo'] = 'https://cliente-ejemplo.test/wp-content/uploads/2026/01/logo-chico.png';
$B::run();
check( in_array( 'logo_small', codes(), true ), 'Un logo de 60×40 px avisa que Google pide 112×112.' );

// Un archivo de la biblioteca que ya no existe: inequívoco, con la misma confirmación.
$GLOBALS['settings']['seo_logo'] = 'https://cliente-ejemplo.test/wp-content/uploads/2026/01/borrado.png';
$B::run(); pasa_una_hora(); $state = $B::run();
check( false === $state['urls'][ $GLOBALS['settings']['seo_logo'] ]['ok'] && $real === $B::url( 'logo' ), 'Un archivo borrado de la biblioteca cae al logo del tema.' );
check( '' === $B::local_file( 'https://cliente-ejemplo.test/wp-content/uploads/../../wp-config.php' ), 'Una ruta con «..» no se lee del disco.' );

// 4. El aviso del escritorio: en el Escritorio, con la X (Digitalisimo_Notices) y por sitio.
$GLOBALS['notices'] = array();
$B::notice();
check( 1 === count( $GLOBALS['notices'] ) && 'seo-brand-s1' === $GLOBALS['notices'][0]['id'] && false !== strpos( $GLOBALS['notices'][0]['content'], 'borrado.png' ), 'El aviso sale en el Escritorio con el id del sitio.' );
$GLOBALS['screen'] = (object) array( 'id' => 'edit-post' ); $GLOBALS['notices'] = array();
$B::notice();
check( array() === $GLOBALS['notices'], 'Fuera del Escritorio y de las pantallas DIGITALÍSIMO no sale.' );

// El MCP: la ability de estado del sitio devuelve los mismos avisos.
$abilities = (string) file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-abilities.php' );
check( false !== strpos( $abilities, "'brand_warnings' => Digitalisimo_Integrations_Brand_Check::warnings()" ) && false !== strpos( $abilities, 'avisos de marca' ), 'get-site-status del MCP lleva los avisos de marca y lo dice en su descripción.' );

@array_map( 'unlink', glob( WP_CONTENT_DIR . '/uploads/2026/01/*' ) );
if ( $fails ) { fwrite( STDERR, "{$fails} falla(s).\n" ); exit( 1 ); }
echo "SEO: imágenes de marca comprobadas en segundo plano, con reserva y avisos; el front sólo lee.\n";
