<?php
// Hosting: el formulario de ajustes se pinta y se envía como un navegador (campos del formulario + el botón pulsado) en
// un sitio individual, en un sitio de una red y en la administración de la red, y se comprueba lo guardado y lo que usa
// el sitio. Paridad sitio/red (pedido del propietario del 2026-11-22: «que sean exactamente los mismos plugins pero en
// multisite sin problemas»):
//   1. sitio individual: guarda todo, la clave no vuelve a la página y guardar con el campo vacío no la borra;
//      escribir otra la reemplaza, una pegada detrás de la máscara se guarda sin los «•» y «Quitar la clave» la borra;
//   2. red: sólo la cuenta de Namecheap (los productos, las categorías y el checkout son de cada tienda), en sitemeta,
//      sin la casilla «Heredar» y sin tocar las opciones de ningún sitio;
//   3. un sitio de la red que nunca guardó Hosting NO hereda nada (la herencia es opcional): la pantalla le dice que la
//      red tiene una cuenta y cómo usarla;
//   4. con «Heredar», el sitio consulta con la cuenta de la red (sin ver sus datos) y su límite de consultas es de toda
//      la red; sus productos, categorías y checkout siguen siendo los suyos;
//   5. al dejar de heredar, el sitio recupera su propia clave (que se conservó) y nunca recibe la de la red; uno sin
//      clave propia se queda sin clave;
//   6. un sitio que ya heredaba la red (y con ella su tienda) ve una vez en el escritorio que ahora usa su propia
//      configuración de tienda, con el enlace a revisarla; abrir la pantalla Hosting lo retira.
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
$GLOBALS['transients'] = array(); $GLOBALS['site_transients'] = array();
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function get_site_transient( $k ) { return $GLOBALS['site_transients'][ $k ] ?? false; }
function set_site_transient( $k, $v, $t ) { $GLOBALS['site_transients'][ $k ] = $v; return true; }
function wp_salt( $s ) { return 'sal'; }
function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function rest_ensure_response( $r ) { return $r; }
class WP_Error { public $code; public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
$GLOBALS['remote'] = array();
// La consulta va por POST con la clave en el cuerpo (2026-11-26); se anota la URL y, detrás, el cuerpo.
function wp_remote_post( $url, $args = array() ) { $GLOBALS['remote_url'][] = $url; $GLOBALS['remote'][] = $url . ' ' . http_build_query( (array) ( $args['body'] ?? array() ) ); return array( 'response' => array( 'code' => 200 ), 'body' => '<ApiResponse Status="OK"><CommandResponse><DomainCheckResult Domain="x.com" Available="true"/></CommandResponse></ApiResponse>' ); }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
class Hosting_Request { public function get_param( $k ) { return 'digitalisimo.com'; } }
require __DIR__ . '/../digitalisimo-hosting/includes/class-hosting.php';
require __DIR__ . '/../digitalisimo-hosting/includes/class-hosting-namecheap.php';

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
	foreach ( $fill as $name => $value ) if ( null !== $value && ! isset( $seen[ $name ] ) && ! preg_match( '#inherit_network|_remove|enabled|require_domain_hosting|replace_hosting|simplify_checkout#', $name ) ) check( false, "El formulario no tiene el campo $name" );
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
check( false !== strpos( $after, 'Guardada · escribe una nueva' ), 'Sitio individual: la clave se ve «Guardada»' );
hosting_submit( false, array() );
check( 'CLAVE-SITIO-123' === get_option( 'digitalisimo_hosting_options' )['api_key'] && ".com|10\n.mx|11" === str_replace( "\r", '', get_option( 'digitalisimo_hosting_options' )['domain_products'] ), 'Sitio individual: guardar otra vez con el campo vacío no borra la clave ni los productos' );
check( 'available' === hosting_check_domain(), 'Sitio individual: la consulta de dominios funciona' );
check( false === strpos( implode( ' ', $GLOBALS['remote_url'] ), 'ApiKey' ) && false !== strpos( $GLOBALS['remote'][0], 'Command=namecheap.domains.check' ), 'La clave de Namecheap viaja en el cuerpo del POST, nunca en la URL' );
// La máscara (pedido del propietario: «deben salir los *****»): el campo lleva •••• y no la clave; enviarla la conserva,
// escribir otra la reemplaza y «Quitar la clave» la borra.
ob_start(); Digitalisimo_Hosting::page(); $html = ob_get_clean();
check( preg_match( '#name="hosting\[api_key\]" value="••••••••••••"#u', $html ) && false === strpos( $html, 'CLAVE-SITIO-123' ), 'Sitio individual: la clave guardada se ve como •••• y no se imprime' );
check( false !== strpos( $html, 'name="hosting[api_key_remove]"' ), 'Sitio individual: «Quitar la clave» junto a la clave guardada' );
hosting_submit( false, array( 'hosting[api_key]' => 'CLAVE-NUEVA-321' ) );
check( 'CLAVE-NUEVA-321' === get_option( 'digitalisimo_hosting_options' )['api_key'], 'Sitio individual: escribir otra clave la reemplaza' );
hosting_submit( false, array( 'hosting[api_key_remove]' => '1' ) );
check( '' === get_option( 'digitalisimo_hosting_options' )['api_key'], 'Sitio individual: «Quitar la clave» la borra' );
check( false !== strpos( $html, 'name="hosting[api_key]" value="••••••••••••" autocomplete="new-password" onfocus="this.select()"' ), 'Sitio individual: el campo de la clave se selecciona entero al enfocarlo' );
ob_start(); Digitalisimo_Hosting::page(); $html = ob_get_clean();
check( preg_match( '#name="hosting\[api_key\]" value=""#', $html ) && false === strpos( $html, 'api_key_remove' ), 'Sin clave, el campo va vacío y sin «Quitar»' );
hosting_submit( false, array( 'hosting[api_key]' => '••••••••••••CLAVE-PEGADA-9' ) );
check( 'CLAVE-PEGADA-9' === get_option( 'digitalisimo_hosting_options' )['api_key'], 'Sitio individual: una clave pegada detrás de la máscara se guarda sin los «•»' );
hosting_submit( false, array( 'hosting[api_key]' => 'CLAVE-SITIO-123' ) );

// 2. Administración de la red: sólo la cuenta de Namecheap.
$GLOBALS['multisite'] = true; $GLOBALS['network_admin'] = true; $GLOBALS['options'] = array();
list( $before, $after ) = hosting_submit( true, array( 'hosting[api_user]' => 'api-red', 'hosting[username]' => 'cuenta-red', 'hosting[api_key]' => 'CLAVE-RED-456', 'hosting[client_ip]' => '198.51.100.4' ) );
$n = $GLOBALS['site_options']['digitalisimo_hosting_options'] ?? array();
check( 'api-red' === $n['api_user'] && 'cuenta-red' === $n['username'] && 'CLAVE-RED-456' === $n['api_key'] && '198.51.100.4' === $n['client_ip'], 'Red: guarda en sitemeta la cuenta de Namecheap' );
check( false === strpos( $before, 'hosting[domain_products]' ) && false === strpos( $before, 'hosting[hosting_product_ids]' ) && false === strpos( $before, 'hosting[simplify_checkout]' ), 'Red: no pinta productos, categorías ni checkout (son de cada tienda)' );
check( ! isset( $n['inherit_network'] ) && false === strpos( $before, 'hosting[inherit_network]' ), 'Red: sin la casilla «Heredar» ni su valor' );
check( false !== strpos( $before, 'Un sitio la usa sólo si su administrador marca' ), 'Red: la pantalla dice que los sitios la usan sólo si la heredan' );
check( array() === $GLOBALS['options'], 'Red: no escribe las opciones de ningún sitio' );
check( false !== strpos( $after, 'Configuración de red guardada.' ) && false === strpos( $after, 'CLAVE-RED-456' ), 'Red: «guardada» y la clave no vuelve a la página' );
hosting_submit( true, array() );
check( 'CLAVE-RED-456' === $GLOBALS['site_options']['digitalisimo_hosting_options']['api_key'], 'Red: guardar otra vez (con la máscara) no borra la clave' );
ob_start(); Digitalisimo_Hosting::network_page(); $html = ob_get_clean();
check( preg_match( '#name="hosting\[api_key\]" value="••••••••••••"#u', $html ) && false === strpos( $html, 'CLAVE-RED-456' ) && false !== strpos( $html, 'api_key_remove' ), 'Red: la clave se ve como ••••, no se imprime y se puede quitar' );
// Datos de tienda que quedaron en la red (opciones de red de Ecommerce migradas): nadie los hereda.
$GLOBALS['site_options']['digitalisimo_hosting_options'] += array( 'domain_products' => '.com|999', 'hosting_product_ids' => '777', 'simplify_checkout' => 1, 'replace_hosting' => 1 );

// 3. Un sitio de la red que nunca guardó Hosting no hereda nada; la pantalla le dice cómo usar la cuenta de la red.
$GLOBALS['network_admin'] = false;
$e = hosting_effective();
check( '' === $e['api_user'] && '' === $e['api_key'] && ".com|\n.net|\n.mx|" === $e['domain_products'] && '' === $e['hosting_product_ids'] && 0 === $e['simplify_checkout'], 'Un sitio que nunca guardó Hosting no hereda nada de la red' );
check( 'not_configured' === hosting_check_domain() && ! $GLOBALS['remote'], 'Un sitio que nunca guardó Hosting no consulta con la cuenta de la red' );
ob_start(); Digitalisimo_Hosting::page(); $html = ob_get_clean();
check( ! preg_match( '#name="hosting\[inherit_network\]" value="1" checked#', $html ) && false !== strpos( $html, 'La red tiene una cuenta de Namecheap configurada' ), 'Su casilla «Heredar» sale sin marcar y la pantalla dice que la red tiene una cuenta y cómo usarla' );
check( false === strpos( $html, 'api-red' ) && false === strpos( $html, '198.51.100.4' ), 'La pantalla del sitio no muestra los datos de la cuenta de la red' );

// 4. El sitio guarda lo suyo (tienda y cuenta propia) y después hereda la cuenta de la red.
hosting_submit( false, array( 'hosting[api_user]' => 'api-propio', 'hosting[username]' => 'cuenta-propia', 'hosting[api_key]' => 'CLAVE-PROPIA-789', 'hosting[client_ip]' => '192.0.2.9', 'hosting[domain_products]' => '.mx|5', 'hosting[hosting_product_ids]' => '42', 'hosting[simplify_checkout]' => '1' ) );
check( 'CLAVE-PROPIA-789' === hosting_effective()['api_key'] && 'available' === hosting_check_domain() && false !== strpos( $GLOBALS['remote'][0], 'ApiKey=CLAVE-PROPIA-789' ), 'Con valores propios, el sitio usa los suyos' );
list( , $after ) = hosting_submit( false, array( 'hosting[inherit_network]' => '1' ) );
$e = hosting_effective(); $o = get_option( 'digitalisimo_hosting_options' );
check( 'api-red' === $e['api_user'] && 'CLAVE-RED-456' === $e['api_key'] && '198.51.100.4' === $e['client_ip'], 'Con «Heredar», el sitio usa la cuenta de Namecheap de la red' );
check( '.mx|5' === $e['domain_products'] && '42' === $e['hosting_product_ids'] && 1 === $e['simplify_checkout'] && 0 === $e['replace_hosting'], 'Con «Heredar», productos, categorías y checkout siguen siendo los del sitio' );
check( 'CLAVE-PROPIA-789' === $o['api_key'] && 'api-propio' === $o['api_user'], 'Con «Heredar», la cuenta propia del sitio se conserva' );
check( false === strpos( $after, 'api-red' ) && false === strpos( $after, 'hosting[api_user]' ) && false !== strpos( $after, 'Usa la cuenta de Namecheap de la red' ), 'Con «Heredar», la pantalla no muestra ni deja editar la cuenta de la red' );
hosting_submit( false, array( 'hosting[domain_products]' => '.mx|6' ) );
check( '.mx|6' === hosting_effective()['domain_products'] && 'CLAVE-PROPIA-789' === get_option( 'digitalisimo_hosting_options' )['api_key'], 'Con «Heredar», guardar la tienda funciona y no toca la cuenta propia' );
$GLOBALS['transients'] = array(); $GLOBALS['site_transients'] = array();
check( 'available' === hosting_check_domain() && false !== strpos( $GLOBALS['remote'][0], 'ApiKey=CLAVE-RED-456' ), 'Con «Heredar», consulta dominios con la clave de la red' );
check( 1 === count( $GLOBALS['site_transients'] ) && ! $GLOBALS['transients'], 'Con la cuenta de la red, el límite de consultas se cuenta para toda la red' );

// 5. Al dejar de heredar, el sitio recupera su clave y nunca recibe la de la red.
hosting_submit( false, array( 'hosting[inherit_network]' => null ) );
$o = get_option( 'digitalisimo_hosting_options' );
check( 0 === $o['inherit_network'] && 'CLAVE-PROPIA-789' === $o['api_key'] && 'api-propio' === $o['api_user'] && 'CLAVE-PROPIA-789' === hosting_effective()['api_key'], 'Al dejar de heredar, el sitio recupera su propia cuenta' );
check( false === strpos( serialize( $GLOBALS['options'] ), 'CLAVE-RED-456' ), 'La clave de la red nunca se copia a las opciones del sitio' );
$GLOBALS['transients'] = array(); $GLOBALS['site_transients'] = array();
hosting_check_domain();
check( 1 === count( $GLOBALS['transients'] ) && ! $GLOBALS['site_transients'], 'Con su cuenta propia, el límite de consultas es del sitio' );
// Un sitio sin cuenta propia que hereda y deja de heredar se queda sin clave (no con la de la red).
$GLOBALS['options'] = array();
hosting_submit( false, array( 'hosting[inherit_network]' => '1' ) );
check( 'CLAVE-RED-456' === hosting_effective()['api_key'], 'Un sitio sin cuenta propia hereda la de la red al marcar la casilla' );
hosting_submit( false, array( 'hosting[inherit_network]' => null ) );
check( '' === hosting_effective()['api_key'] && false === strpos( serialize( $GLOBALS['options'] ), 'CLAVE-RED-456' ), 'Sin cuenta propia, dejar de heredar deja el sitio sin clave, no con la de la red' );

// 6. Un sitio que heredaba la red entera (antes de esta versión) ve el aviso en el escritorio hasta abrir Hosting.
function hosting_notice() { ob_start(); Digitalisimo_Hosting::store_notice(); return ob_get_clean(); }
$GLOBALS['pagenow'] = 'index.php';
$GLOBALS['options'] = array( 'digitalisimo_hosting_options' => array( 'inherit_network' => 1, 'domain_products' => '.com|1' ) );
$html = hosting_notice();
check( false !== strpos( $html, 'ya no usa la configuración de tienda de la red' ) && false !== strpos( $html, 'href="https://sitio.test/wp-admin/admin.php?page=digitalisimo-hosting"' ), 'Un sitio que heredaba la tienda de la red ve el aviso con el enlace a revisarla' );
$GLOBALS['pagenow'] = 'edit.php';
check( '' === hosting_notice(), 'El aviso sólo sale en el escritorio' );
$GLOBALS['pagenow'] = 'index.php';
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean();
check( '' === hosting_notice(), 'Abrir la pantalla Hosting retira el aviso' );
$GLOBALS['options'] = array( 'digitalisimo_hosting_options' => array( 'inherit_network' => 0, 'domain_products' => '.com|1' ) );
check( '' === hosting_notice(), 'Un sitio que no heredaba no ve el aviso' );
$GLOBALS['options'] = array( 'digitalisimo_hosting_options' => array( 'inherit_network' => 1 ) + $GLOBALS['site_options']['digitalisimo_hosting_options'] );
check( '' === hosting_notice(), 'Un sitio cuya tienda es igual a la de la red no ve el aviso' );

echo "Hosting, guardar ajustes en sitio, subsitio y red: $checks comprobaciones correctas.\n";
