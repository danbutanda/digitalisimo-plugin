<?php
/**
 * Escritura SEO por MCP con acceso total (decisión del dueño del 2026-11-27; antes, B5 de la auditoría SEO: cuatro
 * partes con interruptor, apagadas de fábrica). Ahora:
 *   - no hay interruptores: ni `seo_abilities_write` ni sus cuatro partes en los valores, la pantalla o los guardados;
 *   - un sitio (o una red) que los tenía apagados escribe metadatos SEO, schema, indexación y redirecciones por MCP
 *     sin que nadie toque nada; decide la capacidad del usuario (editar ese contenido; administrar el sitio);
 *   - la migración borra los valores viejos del sitio, de su herencia y de la red, una vez y sin tocar nada más;
 *   - cada cambio queda en el registro, agrupado por parte, con quién y por dónde (cliente MCP o Asistente).
 * Falla con el código de v522/v523 (partes cerradas → digitalisimo_seo_part_closed). Uso: php tests/seo-abilities-parts.php
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['opt'] = array(); $GLOBALS['site_opt'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['multi'] = false; $GLOBALS['pagenow'] = 'admin.php';
function add_action() {} function add_filter() {}
function is_multisite() { return $GLOBALS['multi']; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['writes'][] = $k; $GLOBALS['opt'][ $k ] = $v; return true; }
function get_site_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['site_opt'] ) ? $GLOBALS['site_opt'][ $k ] : $d; }
function update_site_option( $k, $v ) { $GLOBALS['writes'][] = 'site:' . $k; $GLOBALS['site_opt'][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
function get_post( $id ) { return 7 === (int) $id ? (object) array( 'ID' => 7, 'post_type' => 'page', 'post_status' => 'publish' ) : null; }
function current_user_can( $cap = '' ) { return null === $GLOBALS['caps'] || in_array( $cap, $GLOBALS['caps'], true ); }
$GLOBALS['caps'] = null; $GLOBALS['writes'] = array();
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
	public static function can_edit( $input = array() ) { return current_user_can( 'edit_post' ); }
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
$w     = 'Digitalisimo_Integrations_SEO_Abilities_Write';
$S     = Digitalisimo_Integrations_Settings::OPTION;
$old   = Digitalisimo_Integrations_Settings::legacy_ability_keys();
$off   = array_fill_keys( $old, 0 );
$reset = function( array $site = array(), array $network = array(), array $inherit = array(), $multi = false ) use ( $S ) {
	$GLOBALS['opt'] = array( $S => $site, 'digitalisimo_seo_network_inherit' => $inherit ); $GLOBALS['site_opt'] = array( $S => $network ); $GLOBALS['meta'] = array(); $GLOBALS['multi'] = $multi; $GLOBALS['caps'] = null; $GLOBALS['writes'] = array(); Digitalisimo_AI_Assistant_Context::$open = '';
};

// 1. Sin interruptores: ni en los valores, ni en la pantalla (sitio y red), ni en los guardados.
check( array( 'seo_abilities_write', 'seo_abilities_write_meta', 'seo_abilities_write_schema', 'seo_abilities_write_index', 'seo_abilities_write_redirects' ) === $old, 'Las cinco claves retiradas, nombradas para borrarlas.' );
$defaults = Digitalisimo_Integrations_Settings::defaults();
$suite    = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php' );
$sanit    = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-settings.php' );
$writer   = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-abilities-write.php' );
foreach ( $old as $key ) {
	check( ! array_key_exists( $key, $defaults ), "$key ya no está en los valores." );
	check( false === strpos( $suite, "'$key'" ), "$key ya no se pinta ni se guarda en la pantalla." );
	check( 0 === preg_match( "/'enable_seo', 'enable_woocommerce'.*'$key'/", $sanit ), "El sitio ya no valida $key como casilla." );
}
check( false === strpos( $writer, 'part_enabled' ) && false === strpos( $writer, 'digitalisimo_seo_part_closed' ) && false === strpos( $writer, 'Se enciende en' ), 'La escritura no consulta interruptores ni manda a encender nada.' );
check( false !== strpos( $suite, 'No hay interruptores' ), '«Agentes de IA» explica que no hay interruptores.' );

// 2. Un sitio que venía con todo apagado (el interruptor único y las cuatro partes) escribe por MCP sin tocar nada.
$reset( $off );
check( true === $w::can_edit( array( 'post_id' => 7 ) ) && true === $w::can_manage(), 'Todo apagado antes: un cliente MCP con la capacidad entra (antes no).' );
$r = $w::update_post_seo( array( 'post_id' => 7, 'title' => 'Nuevo título', 'description' => 'Desc', 'noindex' => true, 'schema_type' => 'Service' ) );
check( is_array( $r ) && array( 'title', 'description', 'noindex', 'schema_type' ) === $r['changed'], 'Escribe título, descripción, indexación y schema en una llamada: ' . json_encode( $r instanceof WP_Error ? $r->code : $r ) );
check( 'Nuevo título' === get_post_meta( 7, 'digitalisimo_seo_title' ) && '1' === get_post_meta( 7, 'digitalisimo_seo_noindex' ) && 'Service' === get_post_meta( 7, 'digitalisimo_seo_schema_type' ), 'Los metadatos quedaron guardados.' );
$log   = get_option( $w::LOG );
$parts = array_column( $log, 'part' );
sort( $parts );
check( array( 'index', 'meta', 'schema' ) === $parts && 'mcp' === $log[0]['via'] && 1 === $log[0]['user'], 'Cada cambio en el registro, por parte, con quién y como cliente MCP.' );
$r = $w::revert_post_seo( array( 'post_id' => 7 ) );
check( is_array( $r ) && '' === get_post_meta( 7, 'digitalisimo_seo_title' ) && '' === get_post_meta( 7, 'digitalisimo_seo_schema_type' ), 'Deshacer funciona igual.' );

// 3. Lo mismo en una red que tenía todo apagado y que el sitio heredaba, y en un sitio que lo apagó por su cuenta.
$reset( array(), $off, array(), true );
$r = $w::update_post_seo( array( 'post_id' => 7, 'canonical' => 'https://a.test/x' ) );
check( is_array( $r ) && 'https://a.test/x' === get_post_meta( 7, 'digitalisimo_seo_canonical' ), 'Red apagada y heredada: escribe.' );
$reset( $off, array( 'seo_abilities_write' => 1 ), array_fill_keys( $old, 0 ), true );
check( true === $w::can_edit( array( 'post_id' => 7 ) ) && true === $w::can_manage(), 'Sitio de la red con todo apagado por su cuenta: entra.' );

// 4. La capacidad sigue mandando.
$reset();
$GLOBALS['caps'] = array( 'read' );
check( false === $w::can_edit( array( 'post_id' => 7 ) ) && false === $w::can_manage(), 'Sin poder editar ni administrar: rechazado.' );
$GLOBALS['caps'] = array( 'edit_post' );
check( true === $w::can_edit( array( 'post_id' => 7 ) ) && false === $w::can_manage(), 'Quien edita el contenido cambia su SEO pero no crea redirecciones.' );

// 5. La migración borra los valores viejos del sitio, de su herencia y de la red; una vez y sin tocar nada más.
$reset( array( 'seo_abilities_write' => 1, 'seo_abilities_write_meta' => 0, 'seo_title_page' => '%title%' ), array( 'seo_abilities_write_index' => 0, 'enable_seo' => 1 ), array( 'seo_abilities_write' => 0, 'seo_title_page' => 1 ), true );
Digitalisimo_Integrations_Settings::forget_ability_switches();
check( array( 'seo_title_page' => '%title%' ) === $GLOBALS['opt'][ $S ], 'Sitio: sólo se quitan las claves viejas.' );
check( array( 'seo_title_page' => 1 ) === $GLOBALS['opt']['digitalisimo_seo_network_inherit'], 'Herencia: sólo se quitan las claves viejas.' );
check( array( 'enable_seo' => 1 ) === $GLOBALS['site_opt'][ $S ], 'Red: sólo se quitan las claves viejas.' );
$GLOBALS['writes'] = array();
Digitalisimo_Integrations_Settings::forget_ability_switches();
check( array() === $GLOBALS['writes'], 'Idempotente: la segunda vez no escribe nada.' );
$reset( array( 'enable_seo' => 1 ) );
Digitalisimo_Integrations_Settings::forget_ability_switches();
check( array() === $GLOBALS['writes'], 'Un sitio sin claves viejas no se toca.' );

// 6. El Asistente del escritorio: igual que un cliente, registrado como Asistente.
$reset( $off );
Digitalisimo_AI_Assistant_Context::$open = 'digitalisimo-seo/update-post-seo';
$r = $w::update_post_seo( array( 'post_id' => 7, 'title' => 'Del Asistente' ) );
check( is_array( $r ) && 'assistant' === get_option( $w::LOG )[0]['via'], 'El Asistente escribe y queda como Asistente en el registro.' );

// 7. El registro se recorta y se lee por parte.
$GLOBALS['opt'][ $w::LOG ] = array();
for ( $i = 0; $i < $w::LOG_KEEP + 15; $i++ ) $w::log( 'update-post-seo', 'post', 7, array( 'canonical' => array( '', 'https://a.test/' . $i ) ) );
check( $w::LOG_KEEP === count( get_option( $w::LOG ) ) && 'https://a.test/' . ( $w::LOG_KEEP + 14 ) === $w::log_entries( 'index', 1 )[0]['fields']['canonical'][1] && array() === $w::log_entries( 'meta' ), 'Registro acotado y filtrado por parte, lo más reciente primero.' );

echo "Escritura SEO por MCP con acceso total: sin interruptores, capacidad, migración, Asistente y registro.\n";
