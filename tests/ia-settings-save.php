<?php
// IA Tools: la pantalla de un sitio guarda lo que se escribe (caso real del propietario, 2026-11-22: «estoy agregando
// la clave API en Proveedores de IA Tools pero se borra al guardar; ya van varias veces que la pongo»). La prueba pinta
// el formulario real, lo envía como un navegador (campos del formulario + el botón pulsado) y comprueba lo guardado.
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
$checks = 0;
function check( $c, $m ) { global $checks; if ( ! $c ) { echo "FALLA: $m\n"; exit( 1 ); } $checks++; }
$GLOBALS['options'] = array(); $GLOBALS['site_options'] = array(); $GLOBALS['multisite'] = false;
function add_action() {} function add_filter() {} function add_submenu_page() {} function add_menu_page() {}
function apply_filters( $h, $v ) { return $v; }
function is_multisite() { return $GLOBALS['multisite']; }
function current_user_can( $c ) { return true; }
function is_super_admin() { return true; }
function is_network_admin() { return false; }
function check_admin_referer( $a ) { return isset( $_POST['_wpnonce'] ) && 'n:' . $a === $_POST['_wpnonce']; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="n:' . $a . '">'; }
function submit_button( $text = 'Guardar', $type = 'primary', $name = 'submit' ) { echo '<input type="submit" name="' . $name . '" value="' . $text . '">'; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function get_site_option( $k, $d = false ) { return $GLOBALS['site_options'][ $k ] ?? $d; }
function update_site_option( $k, $v ) { $GLOBALS['site_options'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function esc_url_raw( $u ) { return (string) $u; }
function esc_url( $u ) { return (string) $u; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function wp_unslash( $v ) { return $v; }
function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
function checked( $a, $b = true, $echo = true ) { return $a == $b ? 'checked' : ''; }
function selected( $a, $b = true, $echo = true ) { return $a == $b ? 'selected' : ''; }
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
function screen() { if ( ! empty( $GLOBALS['network_save'] ) ) Digitalisimo_AI::network_page(); else Digitalisimo_AI::page(); }
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-content-playbook.php';
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }

/** Pinta la pantalla y devuelve lo que enviaría un navegador al pulsar su botón de guardar, con $fill para los campos. */
function submit_form( $tab, array $fill ) {
	$_GET = array( 'tab' => $tab ); $_POST = array();
	ob_start(); screen(); $html = ob_get_clean();
	check( preg_match( '#<form method="post">(.*)</form>#s', $html, $m ), "La pestaña $tab tiene su formulario" );
	$post = array();
	preg_match_all( '#<input([^>]*)>#', $m[1], $inputs );
	foreach ( $inputs[1] as $attrs ) {
		if ( ! preg_match( '#name="([^"]+)"#', $attrs, $n ) ) continue;
		$name = html_entity_decode( $n[1] ); preg_match( '#value="([^"]*)"#', $attrs, $v ); $value = isset( $v[1] ) ? html_entity_decode( $v[1] ) : '';
		if ( preg_match( '#type="submit"#', $attrs ) ) { $post[] = array( $name, $value ); continue; }
		if ( preg_match( '#type="checkbox"#', $attrs ) && false === strpos( $attrs, 'checked' ) && ! array_key_exists( $name, $fill ) ) continue;
		$post[] = array( $name, array_key_exists( $name, $fill ) ? $fill[ $name ] : $value );
	}
	$query = implode( '&', array_map( function ( $p ) { return rawurlencode( $p[0] ) . '=' . rawurlencode( (string) $p[1] ); }, $post ) );
	parse_str( $query, $_POST );
	ob_start(); screen(); $after = ob_get_clean();
	return $after;
}

// 1. Sitio individual: escribir la clave de Anthropic, activarla y guardar.
$after = submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-ant-PRUEBA-123', 'providers[anthropic][enabled]' => '1' ) );
$o = get_option( 'digitalisimo_ai_options', array() );
check( 'sk-ant-PRUEBA-123' === ( $o['providers']['anthropic']['key'] ?? null ), 'Guardar en Proveedores conserva la clave escrita' );
check( ! empty( $o['providers']['anthropic']['enabled'] ), 'Guardar en Proveedores conserva «Activar»' );
check( false !== strpos( $after, 'Configuración guardada.' ), 'Tras guardar se ve «Configuración guardada.»' );
check( false !== strpos( $after, 'Guardada · escribe una nueva sólo si quieres reemplazarla' ), 'Tras guardar, el proveedor dice que su clave está guardada' );
check( false === strpos( $after, 'sk-ant-PRUEBA-123' ), 'La clave no vuelve a imprimirse en la página' );

check( false !== strpos( $after, 'name="providers[anthropic][key]" autocomplete="new-password" value="••••••••••••" onfocus="this.select()"' ), 'Con una clave guardada, el campo muestra la máscara ••••, no un campo vacío' );

// 2. Volver a guardar sin tocar la clave (llega la máscara) no la borra; vaciar el campo tampoco; una nueva la
// reemplaza y «Quitar la clave» la borra (pedido del propietario: «deben salir los *****»).
$key = function () { $o = get_option( 'digitalisimo_ai_options', array() ); return $o['providers']['anthropic']['key'] ?? null; };
submit_form( 'providers', array() );
check( 'sk-ant-PRUEBA-123' === $key(), 'Guardar otra vez con la máscara conserva la clave' );
submit_form( 'providers', array( 'providers[anthropic][key]' => '' ) );
check( 'sk-ant-PRUEBA-123' === $key(), 'Guardar con el campo vaciado conserva la clave' );
submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-ant-NUEVA' ) );
check( 'sk-ant-NUEVA' === $key(), 'Escribir una clave nueva la reemplaza' );
submit_form( 'providers', array( 'providers[anthropic][key]' => '••••••••••••sk-ant-PEGADA' ) );
check( 'sk-ant-PEGADA' === $key(), 'Pegar la clave nueva detrás de la máscara guarda sólo la clave' );
submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-ant-DELANTE••••••••••••' ) );
check( 'sk-ant-DELANTE' === $key(), 'Pegarla delante de la máscara también guarda sólo la clave' );
$after = submit_form( 'providers', array( 'providers[anthropic][remove]' => '1' ) );
check( '' === $key() && false !== strpos( $after, 'Sin clave configurada' ), '«Quitar la clave» la borra' );

// 2b. Lo mismo en la pantalla de red.
$GLOBALS['multisite'] = true; $GLOBALS['network_save'] = true; $GLOBALS['site_options'] = array();
$net = function () { $o = get_site_option( 'digitalisimo_ai_options', array() ); return $o['providers']['openai']['key'] ?? null; };
submit_form( 'providers', array( 'providers[openai][key]' => 'sk-RED', 'providers[openai][enabled]' => '1' ) );
$after = submit_form( 'providers', array() );
check( 'sk-RED' === $net() && false !== strpos( $after, 'value="••••••••••••"' ) && false === strpos( $after, 'sk-RED' ), 'Red: la clave se ve como •••• y guardar sin tocarla la conserva' );
submit_form( 'providers', array( 'providers[openai][remove]' => '1' ) );
check( '' === $net(), 'Red: «Quitar la clave» la borra' );
$GLOBALS['multisite'] = false; $GLOBALS['network_save'] = false; $GLOBALS['site_options'] = array();

// 3. Otras pestañas también guardan (el mismo botón servía a todas).
submit_form( 'advanced', array( 'ai[log_retention]' => '45' ) );
check( '45' === (string) ( get_option( 'digitalisimo_ai_options', array() )['log_retention'] ?? '' ), 'La pestaña Avanzado también guarda' );

// 4. Red: un sitio que hereda la configuración de la red avisa en Proveedores que sus claves son las de la red.
$GLOBALS['multisite'] = true; $GLOBALS['options'] = array();
$_GET = array( 'tab' => 'providers' ); $_POST = array(); ob_start(); Digitalisimo_AI::page(); $html = ob_get_clean();
check( false !== strpos( $html, 'hereda la configuración de IA de la red' ), 'Un sitio que hereda la red lo dice en Proveedores' );

echo "IA Tools, guardar ajustes: $checks comprobaciones correctas.\n";
