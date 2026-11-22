<?php
// Hosting: el formulario de ajustes se pinta y se envía como un navegador (campos del formulario + el botón pulsado) en
// un sitio individual, en un sitio de una red y en la administración de la red, y se comprueba lo guardado y lo que usa
// el sitio. Paridad sitio/red (pedido del propietario del 2026-11-22: «que sean exactamente los mismos plugins pero en
// multisite sin problemas»):
//   1. sitio individual: guarda todo, la clave no vuelve a la página y guardar con el campo vacío no la borra;
//   2. red: guarda en sitemeta, sin la casilla «Heredar» y sin tocar las opciones del sitio;
//   3. un sitio de la red que nunca guardó Hosting usa los valores de la red y su casilla «Heredar» sale marcada;
//   4. al desmarcar «Heredar» con la clave vacía, el sitio conserva la clave que el formulario daba por «Ya configurada»
//      (la de la red) y sigue consultando dominios: antes se quedaba sin clave («La consulta de dominios no está
//      configurada»);
//   5. un cambio hecho mientras el sitio hereda la red no se usa y la pantalla lo dice (antes: «guardada» y el valor
//      volvía al de la red al recargar);
//   6. un sitio que ya guardó sus valores sin la marca «Heredar» (migrado de Ecommerce en un WordPress individual que
//      después pasó a red) conserva los suyos.
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DIGITALISIMO_HOSTING_URL', 'https://sitio.test/wp-content/plugins/digitalisimo-hosting/' );
define( 'DIGITALISIMO_HOSTING_VERSION', '9.9.9' );
$checks = 0;
function check( $c, $m ) { global $checks; if ( ! $c ) { echo "FALLA: $m\n"; exit( 1 ); } $checks++; }
$GLOBALS['options'] = array(); $GLOBALS['site_options'] = array(); $GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false;
function add_action() {} function add_filter() {} function add_shortcode() {} function register_rest_route() {}
function apply_filters( $h, $v ) { return $v; }
function is_multisite() { return $GLOBALS['multisite']; }
function is_network_admin() { return $GLOBALS['network_admin']; }
function current_user_can( $c ) { return 'manage_network_options' !== $c || $GLOBALS['multisite']; }
function check_admin_referer( $a ) { if ( ( $_POST['_wpnonce'] ?? '' ) !== 'n:' . $a ) { echo "FALLA: nonce de $a\n"; exit( 1 ); } return true; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="n:' . $a . '"><input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=x">'; }
function submit_button( $text = 'Guardar', $type = 'primary', $name = 'submit' ) { echo '<p class="submit"><input type="submit" name="' . $name . '" id="' . $name . '" class="button button-' . $type . '" value="' . $text . '"></p>'; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function wp_load_alloptions() { return $GLOBALS['options']; }
function wp_set_option_autoload( $k, $a ) { return true; }
function get_site_option( $k, $d = false ) { if ( ! $GLOBALS['multisite'] ) return get_option( $k, $d ); return $GLOBALS['site_options'][ $k ] ?? $d; }
function update_site_option( $k, $v ) { if ( ! $GLOBALS['multisite'] ) return update_option( $k, $v ); $GLOBALS['site_options'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function wp_unslash( $v ) { return $v; }
function checked( $a, $b = true, $echo = true ) { return $a == $b ? 'checked="checked"' : ''; }
function absint( $v ) { return abs( (int) $v ); }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t ) { return true; }
function wp_salt( $s ) { return 'sal'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function rest_ensure_response( $r ) { return $r; }
class WP_Error { public $code; public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
$GLOBALS['remote'] = array();
function wp_remote_get( $url, $args = array() ) { $GLOBALS['remote'][] = $url; return array( 'body' => '<ApiResponse><CommandResponse><DomainCheckResult Domain="x.com" Available="true"/></CommandResponse></ApiResponse>' ); }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
class Hosting_Request { public function get_param( $k ) { return 'digitalisimo.com'; } }
require __DIR__ . '/../digitalisimo-hosting/includes/class-hosting.php';

/**
 * Pinta la pantalla (del sitio o de la red), simula lo que un navegador envía al pulsar «Guardar» con $fill para los
 * campos (una casilla se marca con '1' y se desmarca con null) y devuelve la página que se ve después.
 */
function hosting_submit( $network, array $fill ) {
	$page = $network ? array( 'Digitalisimo_Hosting', 'network_page' ) : array( 'Digitalisimo_Hosting', 'page' );
	$_POST = array();
	ob_start(); call_user_func( $page ); $html = ob_get_clean();
	check( preg_match( '#<form method="post">(.*)</form>#s', $html, $m ), 'La pantalla tiene su formulario' );
	$doc = new DOMDocument(); libxml_use_internal_errors( true ); $doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $m[1] . '</div>' ); libxml_clear_errors();
	$pairs = array(); $seen = array();
	foreach ( array( 'input', 'textarea' ) as $tag ) foreach ( $doc->getElementsByTagName( $tag ) as $field ) {
		$name = $field->getAttribute( 'name' ); if ( '' === $name ) continue;
		$type = strtolower( $field->getAttribute( 'type' ) );
		if ( 'submit' === $type ) { $pairs[] = array( $name, $field->getAttribute( 'value' ) ); continue; }
		if ( 'checkbox' === $type ) {
			$on = array_key_exists( $name, $fill ) ? null !== $fill[ $name ] : $field->hasAttribute( 'checked' );
			if ( $on ) $pairs[] = array( $name, $field->getAttribute( 'value' ) );
			continue;
		}
		// Un textarea llega del navegador con los saltos en \r\n.
		$value = 'textarea' === $tag ? preg_replace( '/\r\n?|\n/', "\r\n", $field->textContent ) : $field->getAttribute( 'value' );
		if ( 'hidden' !== $type && array_key_exists( $name, $fill ) ) $value = $fill[ $name ];
		$pairs[] = array( $name, $value ); $seen[ $name ] = true;
	}
	foreach ( $fill as $name => $value ) if ( null !== $value && ! isset( $seen[ $name ] ) && ! preg_match( '#inherit_network|enabled|require_domain_hosting|replace_hosting|simplify_checkout#', $name ) ) check( false, "El formulario no tiene el campo $name" );
	parse_str( implode( '&', array_map( function ( $p ) { return rawurlencode( $p[0] ) . '=' . rawurlencode( (string) $p[1] ); }, $pairs ) ), $_POST );
	ob_start(); call_user_func( $page ); $after = ob_get_clean();
	$_POST = array();
	return array( $html, $after );
}
function hosting_effective() { $m = new ReflectionMethod( 'Digitalisimo_Hosting', 'all' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invoke( null ); }
function hosting_check_domain() { $GLOBALS['remote'] = array(); $r = Digitalisimo_Hosting::check_domain( new Hosting_Request() ); return is_wp_error( $r ) ? $r->code : ( $r['available'] ? 'available' : 'taken' ); }

// 1. Sitio individual.
list( $before, $after ) = hosting_submit( false, array( 'hosting[api_user]' => 'api-sitio', 'hosting[username]' => 'cuenta-sitio', 'hosting[api_key]' => 'CLAVE-SITIO-123', 'hosting[client_ip]' => '203.0.113.7', 'hosting[domain_products]' => ".com|10\r\n.mx|11", 'hosting[simplify_checkout]' => '1' ) );
$o = get_option( 'digitalisimo_hosting_options' );
check( 'api-sitio' === $o['api_user'] && 'CLAVE-SITIO-123' === $o['api_key'] && 1 === $o['simplify_checkout'], 'Sitio individual: guarda lo escrito y la casilla' );
check( false === strpos( $before, 'hosting[inherit_network]' ), 'Sitio individual: sin la casilla «Heredar»' );
check( false !== strpos( $after, 'Configuración guardada.' ) && false === strpos( $after, 'CLAVE-SITIO-123' ), 'Sitio individual: «guardada» y la clave no vuelve a la página' );
check( false !== strpos( $after, 'Ya configurada' ), 'Sitio individual: la clave se ve «Ya configurada»' );
hosting_submit( false, array() );
check( 'CLAVE-SITIO-123' === get_option( 'digitalisimo_hosting_options' )['api_key'] && ".com|10\n.mx|11" === str_replace( "\r", '', get_option( 'digitalisimo_hosting_options' )['domain_products'] ), 'Sitio individual: guardar otra vez con el campo vacío no borra la clave ni los productos' );
check( 'available' === hosting_check_domain(), 'Sitio individual: la consulta de dominios funciona' );

// 2. Administración de la red.
$GLOBALS['multisite'] = true; $GLOBALS['network_admin'] = true; $GLOBALS['options'] = array();
list( $before, $after ) = hosting_submit( true, array( 'hosting[api_user]' => 'api-red', 'hosting[username]' => 'cuenta-red', 'hosting[api_key]' => 'CLAVE-RED-456', 'hosting[client_ip]' => '198.51.100.4', 'hosting[domain_products]' => ".org|20\r\n.net|21" ) );
$n = $GLOBALS['site_options']['digitalisimo_hosting_options'] ?? array();
check( 'api-red' === $n['api_user'] && 'CLAVE-RED-456' === $n['api_key'] && ".org|20\n.net|21" === str_replace( "\r", '', $n['domain_products'] ), 'Red: guarda en sitemeta lo escrito' );
check( ! isset( $n['inherit_network'] ) && false === strpos( $before, 'hosting[inherit_network]' ), 'Red: sin la casilla «Heredar» ni su valor' );
check( false !== strpos( $before, 'también los que nunca guardaron Hosting' ), 'Red: la pantalla dice qué sitios usan estos valores' );
check( array() === $GLOBALS['options'], 'Red: no escribe las opciones de ningún sitio' );
check( false !== strpos( $after, 'Configuración de red guardada.' ) && false === strpos( $after, 'CLAVE-RED-456' ), 'Red: «guardada» y la clave no vuelve a la página' );
hosting_submit( true, array() );
check( 'CLAVE-RED-456' === $GLOBALS['site_options']['digitalisimo_hosting_options']['api_key'], 'Red: guardar otra vez con el campo vacío no borra la clave' );

// 3. Un sitio de la red que nunca guardó Hosting usa los valores de la red.
$GLOBALS['network_admin'] = false;
check( 'api-red' === hosting_effective()['api_user'] && 'CLAVE-RED-456' === hosting_effective()['api_key'], 'Un sitio que nunca guardó Hosting usa los valores de la red' );
check( 'available' === hosting_check_domain() && false !== strpos( $GLOBALS['remote'][0], 'ApiKey=CLAVE-RED-456' ), 'Un sitio que nunca guardó Hosting consulta dominios con la clave de la red' );
ob_start(); Digitalisimo_Hosting::page(); $html = ob_get_clean();
check( preg_match( '#name="hosting\[inherit_network\]" value="1" checked#', $html ) && false !== strpos( $html, 'Valor efectivo: Configuración de red' ), 'Su casilla «Heredar» sale marcada y dice que manda la red' );

// 5. Un cambio mientras hereda no se usa, y la pantalla lo dice.
list( , $after ) = hosting_submit( false, array( 'hosting[api_user]' => 'api-propio' ) );
check( false !== strpos( $after, 'notice-warning' ) && false !== strpos( $after, 'no se usan tus cambios en Usuario API Namecheap' ), 'Un cambio mientras hereda la red avisa que no se usa' );
check( 'api-red' === hosting_effective()['api_user'], 'Mientras hereda, manda la red' );
list( , $after ) = hosting_submit( false, array() );
check( false === strpos( $after, 'notice-warning' ), 'Guardar sin cambios mientras hereda no avisa nada (ni por los saltos \r\n de un textarea)' );

// 4. Al desmarcar «Heredar» con la clave vacía, el sitio conserva la de la red y los demás valores que veía.
list( , $after ) = hosting_submit( false, array( 'hosting[inherit_network]' => null ) );
$o = get_option( 'digitalisimo_hosting_options' );
check( 0 === $o['inherit_network'] && 'api-red' === $o['api_user'] && ".org|20\n.net|21" === str_replace( "\r", '', $o['domain_products'] ), 'Al dejar de heredar, el sitio guarda los valores que veía' );
check( 'CLAVE-RED-456' === ( $o['api_key'] ?? '' ), 'Al dejar de heredar con la clave vacía, el sitio conserva la clave que veía «Ya configurada»' );
check( 'available' === hosting_check_domain(), 'Tras dejar de heredar, la consulta de dominios sigue funcionando' );
check( false !== strpos( $after, 'Valor efectivo: Este sitio' ) && false !== strpos( $after, 'Ya configurada' ), 'Tras dejar de heredar, la pantalla dice «Este sitio» y la clave sigue «Ya configurada»' );
// Ya con valores propios, un cambio se guarda y se usa; la red no cambia.
hosting_submit( false, array( 'hosting[api_user]' => 'api-propio', 'hosting[api_key]' => 'CLAVE-PROPIA-789' ) );
check( 'api-propio' === hosting_effective()['api_user'] && 'CLAVE-PROPIA-789' === hosting_effective()['api_key'], 'Con valores propios, lo que se guarda es lo que usa el sitio' );
check( 'api-red' === $GLOBALS['site_options']['digitalisimo_hosting_options']['api_user'] && 'CLAVE-RED-456' === $GLOBALS['site_options']['digitalisimo_hosting_options']['api_key'], 'Guardar el sitio no toca la red' );
// Volver a heredar: manda la red otra vez y el sitio conserva lo suyo para cuando deje de heredar.
hosting_submit( false, array( 'hosting[inherit_network]' => '1' ) );
check( 'api-red' === hosting_effective()['api_user'] && 'CLAVE-PROPIA-789' === get_option( 'digitalisimo_hosting_options' )['api_key'], 'Volver a heredar: manda la red y la clave propia queda guardada' );

// 6. Valores propios sin la marca «Heredar» (migración de Ecommerce en un WordPress individual que pasó a red).
$GLOBALS['options'] = array( 'digitalisimo_hosting_options' => array( 'api_user' => 'api-antiguo', 'api_key' => 'CLAVE-ANTIGUA', 'username' => 'u', 'client_ip' => '192.0.2.1', 'enabled' => 1 ), 'digitalisimo_hosting_ecommerce_migrated' => 1 );
check( 'api-antiguo' === hosting_effective()['api_user'] && 'CLAVE-ANTIGUA' === hosting_effective()['api_key'], 'Un sitio con valores propios sin la marca «Heredar» conserva los suyos' );

echo "Hosting, guardar ajustes en sitio, subsitio y red: $checks comprobaciones correctas.\n";
