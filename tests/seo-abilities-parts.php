<?php
/**
 * B5 de la auditoría SEO (2026-11-24): el permiso de escritura de las abilities de SEO deja de ser todo o nada.
 *   - cuatro partes, cada una con su interruptor: títulos y descripciones; schema y datos de marca; sitemap e
 *     indexación; redirecciones;
 *   - el interruptor único de antes se migra: encendido → las cuatro encendidas; apagado → las cuatro apagadas; y hasta
 *     que la migración corre (un cliente MCP llega por REST sin pasar por el escritorio) cada parte vale lo que él;
 *   - en una red, un sitio sin migrar hereda (o no) como heredaba el interruptor único;
 *   - un campo de una parte cerrada no se escribe, ni los demás de la misma llamada; el Asistente del escritorio no
 *     depende de los interruptores (su contexto), y cada cambio queda en el registro de su parte.
 * Falla con el código de v518 (un solo interruptor). Uso: php tests/seo-abilities-parts.php
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['opt'] = array(); $GLOBALS['site_opt'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['multi'] = false; $GLOBALS['pagenow'] = 'admin.php';
function add_action() {} function add_filter() {}
function is_multisite() { return $GLOBALS['multi']; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function get_site_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['site_opt'] ) ? $GLOBALS['site_opt'][ $k ] : $d; }
function update_site_option( $k, $v ) { $GLOBALS['site_opt'][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
function get_post( $id ) { return 7 === (int) $id ? (object) array( 'ID' => 7, 'post_type' => 'page', 'post_status' => 'publish' ) : null; }
function current_user_can() { return true; }
function get_current_user_id() { return 1; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $v ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $v ) ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_url_raw( $v, $p = null ) { return preg_match( '#^https?://#i', (string) $v ) ? (string) $v : ''; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error { public $code; public $message; public $data; public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; } public function get_error_code() { return $this->code; } public function get_error_message() { return $this->message; } }
class Digitalisimo_Integrations_Editorial { public static function content_types() { return array( 'post', 'page' ); } }
class Digitalisimo_Integrations_SEO_Abilities {
	const CATEGORY = 'digitalisimo-seo';
	public static function can_edit( $input = array() ) { return true; }
	public static function get_post_seo( $input ) { return array(); }
}
/** El contexto del Asistente (IA Tools): abierto sólo para la ability que se nombra. */
final class Digitalisimo_AI_Assistant_Context {
	public static $open = '';
	public static function allows( $ability ) { return '' !== self::$open && self::$open === $ability; }
}
require __DIR__ . '/../digitalisimo-seo/includes/class-settings.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-resolver.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-abilities-write.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$w   = 'Digitalisimo_Integrations_SEO_Abilities_Write';
$S   = Digitalisimo_Integrations_Settings::OPTION;
$all = function() use ( $w ) { $out = array(); foreach ( array( 'meta', 'schema', 'index', 'redirects' ) as $p ) $out[ $p ] = $w::part_enabled( $p ); return $out; };
$reset = function( array $site = array(), array $network = array(), array $inherit = array(), $multi = false ) use ( $S ) {
	$GLOBALS['opt'] = array( $S => $site, 'digitalisimo_seo_network_inherit' => $inherit ); $GLOBALS['site_opt'] = array( $S => $network ); $GLOBALS['meta'] = array(); $GLOBALS['multi'] = $multi; Digitalisimo_AI_Assistant_Context::$open = '';
};

// 1. Cuatro partes, cada una con su opción declarada (paridad: defaults del sitio y de la red).
check( defined( "$w::PARTS" ) && array( 'meta', 'schema', 'index', 'redirects' ) === array_keys( $w::PARTS ), 'Cuatro partes: títulos y descripciones, schema y marca, sitemap e indexación, redirecciones.' );
$defaults = Digitalisimo_Integrations_Settings::defaults();
foreach ( $w::part_keys() as $key ) check( array_key_exists( $key, $defaults ) && 0 === $defaults[ $key ], "La parte $key está declarada y apagada de fábrica." );
$suite = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php' );
$save  = substr( $suite, strpos( $suite, 'public static function save_network' ), 4000 );
$sanit = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-settings.php' );
foreach ( $w::part_keys() as $key ) {
	check( false !== strpos( $suite, "self::field( \$network, '$key'" ), "$key se pinta igual en el sitio y en la red." );
	check( false !== strpos( $save, "'$key'" ), "La red valida $key como casilla." );
	check( 1 === preg_match( "/'enable_seo', 'enable_woocommerce'.*'$key'/", $sanit ), "El sitio valida $key como casilla." );
}

// 2. Sin migrar: cada parte vale lo que el interruptor único (sitio individual).
$reset( array( 'seo_abilities_write' => 1 ) );
check( array( 'meta' => true, 'schema' => true, 'index' => true, 'redirects' => true ) === $all(), 'Encendido antes → las cuatro abiertas sin pasar por el escritorio.' );
$reset( array( 'seo_abilities_write' => 0 ) );
check( ! in_array( true, $all(), true ), 'Apagado antes → las cuatro cerradas.' );
$reset();
check( ! in_array( true, $all(), true ), 'Sin nada guardado: todo cerrado (opt-in).' );

// 3. La migración copia el interruptor a las cuatro, una sola vez, y respeta lo que el sitio ya eligió.
$reset( array( 'seo_abilities_write' => 1, 'seo_title_page' => '%title%' ) );
Digitalisimo_Integrations_Settings::migrate_ability_parts();
foreach ( $w::part_keys() as $key ) check( 1 === $GLOBALS['opt'][ $S ][ $key ], "Migrado: $key encendida." );
check( '%title%' === $GLOBALS['opt'][ $S ]['seo_title_page'], 'La migración no toca otros ajustes.' );
$GLOBALS['opt'][ $S ]['seo_abilities_write_index'] = 0;
Digitalisimo_Integrations_Settings::migrate_ability_parts();
check( 0 === $GLOBALS['opt'][ $S ]['seo_abilities_write_index'] && false === $w::part_enabled( 'index' ) && true === $w::part_enabled( 'meta' ), 'Lo que el dueño apagó después no se vuelve a encender.' );
$reset( array( 'enable_seo' => 1 ) );
Digitalisimo_Integrations_Settings::migrate_ability_parts();
check( ! array_key_exists( 'seo_abilities_write_meta', $GLOBALS['opt'][ $S ] ), 'Un sitio que nunca guardó el interruptor no cambia.' );
$reset( array( 'seo_abilities_write' => 1 ) );
$GLOBALS['pagenow'] = 'options.php'; $_POST['option_page'] = 'digitalisimo_integrations';
Digitalisimo_Integrations_Settings::migrate_ability_parts();
check( ! array_key_exists( 'seo_abilities_write_meta', $GLOBALS['opt'][ $S ] ), 'Durante el envío del formulario no se mezcla la migración.' );
$GLOBALS['pagenow'] = 'admin.php'; unset( $_POST['option_page'] );

// 4. Red: un sitio sin migrar hereda como heredaba; uno que personalizó el interruptor conserva lo suyo.
$reset( array(), array( 'seo_abilities_write' => 1 ), array(), true );
check( array( 'meta' => true, 'schema' => true, 'index' => true, 'redirects' => true ) === $all(), 'Red encendida antes, sitio que hereda → abiertas.' );
$reset( array( 'seo_abilities_write' => 0 ), array( 'seo_abilities_write_meta' => 1, 'seo_abilities_write' => 1 ), array( 'seo_abilities_write' => 0 ), true );
check( false === $w::part_enabled( 'meta' ), 'El sitio que apagó el interruptor por su cuenta no hereda la parte que encendió la red.' );
$reset( array(), array( 'seo_abilities_write' => 1 ), array(), true );
Digitalisimo_Integrations_Settings::migrate_ability_parts();
check( 1 === $GLOBALS['site_opt'][ $S ]['seo_abilities_write_redirects'], 'La red también se migra.' );
$reset( array( 'seo_abilities_write' => 1 ), array( 'seo_abilities_write' => 0 ), array( 'seo_abilities_write' => 0 ), true );
Digitalisimo_Integrations_Settings::migrate_ability_parts();
check( 0 === $GLOBALS['opt']['digitalisimo_seo_network_inherit']['seo_abilities_write_meta'] && true === $w::part_enabled( 'meta' ), 'La herencia personalizada se copia a las partes: el sitio sigue con lo suyo.' );

// 5. Cada campo pide su parte: con una cerrada no se escribe nada de la llamada.
$reset( array( 'seo_abilities_write_meta' => 1, 'seo_abilities_write_schema' => 0, 'seo_abilities_write_index' => 0, 'seo_abilities_write_redirects' => 0 ) );
check( true === $w::can_edit( array( 'post_id' => 7 ) ) && false === $w::can_manage(), 'Con títulos abiertos se puede editar; redirecciones siguen cerradas.' );
$r = $w::update_post_seo( array( 'post_id' => 7, 'title' => 'Nuevo título', 'noindex' => true ) );
check( $r instanceof WP_Error && 'digitalisimo_seo_part_closed' === $r->code && array( 'index' ) === $r->data['parts'], 'Título + noindex con indexación cerrada → error que nombra la parte.' );
check( '' === get_post_meta( 7, 'digitalisimo_seo_title' ) && '' === get_post_meta( 7, 'digitalisimo_seo_noindex' ), 'Y no cambió nada, tampoco el título.' );
$r = $w::update_post_seo( array( 'post_id' => 7, 'title' => 'Nuevo título' ) );
check( is_array( $r ) && array( 'title' ) === $r['changed'] && 'Nuevo título' === get_post_meta( 7, 'digitalisimo_seo_title' ), 'Sólo el título: se escribe.' );
$log = get_option( $w::LOG );
check( 1 === count( $log ) && 'meta' === $log[0]['part'] && 'mcp' === $log[0]['via'] && array( '', 'Nuevo título' ) === $log[0]['fields']['title'], 'Queda en el registro de «Títulos y descripciones», como cliente MCP.' );
$r = $w::update_post_seo( array( 'post_id' => 7, 'schema_type' => 'Service' ) );
check( $r instanceof WP_Error && array( 'schema' ) === $r->data['parts'], 'El tipo de schema pide la parte de schema.' );
$GLOBALS['opt'][ $S ]['seo_abilities_write_schema'] = 1;
$r = $w::update_post_seo( array( 'post_id' => 7, 'schema_type' => 'NoExiste' ) );
check( array() === $r['changed'] && '' === get_post_meta( 7, 'digitalisimo_seo_schema_type' ), 'Un tipo de schema fuera de la lista del editor no se escribe.' );
$w::update_post_seo( array( 'post_id' => 7, 'schema_type' => 'Service' ) );
check( 'Service' === get_post_meta( 7, 'digitalisimo_seo_schema_type' ) && 'schema' === end( $GLOBALS['opt'][ $w::LOG ] )['part'], 'Con la parte abierta se escribe y se registra en «Schema».' );

// 6. Deshacer pide las partes de lo que restaura.
$GLOBALS['opt'][ $S ]['seo_abilities_write_schema'] = 0;
$r = $w::revert_post_seo( array( 'post_id' => 7 ) );
check( $r instanceof WP_Error && 'Service' === get_post_meta( 7, 'digitalisimo_seo_schema_type' ), 'Deshacer un cambio de schema con la parte cerrada: no se toca nada.' );

// 7. El Asistente del escritorio no depende de los interruptores, y se registra como Asistente.
$reset( array( 'seo_abilities_write_meta' => 0, 'seo_abilities_write_schema' => 0, 'seo_abilities_write_index' => 0, 'seo_abilities_write_redirects' => 0, 'seo_abilities_write' => 1 ) );
check( false === $w::can_edit( array( 'post_id' => 7 ) ), 'Todo cerrado (aunque el interruptor viejo siga en 1): un cliente MCP no entra.' );
Digitalisimo_AI_Assistant_Context::$open = 'digitalisimo-seo/update-post-seo';
check( true === $w::can_edit( array( 'post_id' => 7 ) ), 'El Asistente sí, con su tarjeta.' );
$r = $w::update_post_seo( array( 'post_id' => 7, 'title' => 'Del Asistente', 'noindex' => true ) );
check( is_array( $r ) && '1' === get_post_meta( 7, 'digitalisimo_seo_noindex' ), 'El Asistente escribe aunque las partes estén cerradas.' );
$parts = array_column( get_option( $w::LOG ), 'part' );
sort( $parts );
check( array( 'index', 'meta' ) === $parts && 'assistant' === get_option( $w::LOG )[0]['via'], 'Una entrada por parte tocada, marcada como Asistente.' );
Digitalisimo_AI_Assistant_Context::$open = 'digitalisimo-seo/create-redirection';
check( false === $w::can_edit( array( 'post_id' => 7 ) ), 'El contexto abierto para otra ability no abre ésta.' );

// 8. El registro se recorta y se lee por parte.
$GLOBALS['opt'][ $w::LOG ] = array();
for ( $i = 0; $i < $w::LOG_KEEP + 15; $i++ ) $w::log( 'update-post-seo', 'post', 7, array( 'canonical' => array( '', 'https://a.test/' . $i ) ) );
check( $w::LOG_KEEP === count( get_option( $w::LOG ) ) && 'https://a.test/' . ( $w::LOG_KEEP + 14 ) === $w::log_entries( 'index', 1 )[0]['fields']['canonical'][1] && array() === $w::log_entries( 'meta' ), 'Registro acotado y filtrado por parte, lo más reciente primero.' );

echo "Abilities de escritura por partes: migración, herencia, permiso por campo, Asistente y registro.\n";
