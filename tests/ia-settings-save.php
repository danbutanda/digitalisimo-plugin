<?php
// IA Tools: las pantallas de ajustes guardan lo que se escribe, igual en un sitio individual, en cada sitio de una red y
// en la administración de la red (caso real del propietario, 2026-11-22: «estoy agregando la clave API en Proveedores de
// IA Tools pero se borra al guardar; ya van varias veces que la pongo»; después, la revisión de paridad sitio/red). La
// prueba pinta el formulario real de cada pestaña, lo envía como un navegador (tests/lib/browser-form.php: campos del
// formulario, sin los deshabilitados, y el botón pulsado) y comprueba lo guardado y dónde se guardó.
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/lib/browser-form.php';
$checks = 0;
function check( $c, $m ) { global $checks; if ( ! $c ) { echo "FALLA: $m\n"; exit( 1 ); } $checks++; }
$GLOBALS['options'] = array(); $GLOBALS['site_options'] = array(); $GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false;
function add_action() {} function add_filter() {} function add_submenu_page() {} function add_menu_page() {}
function apply_filters( $h, $v ) { return $v; }
function is_multisite() { return $GLOBALS['multisite']; }
function current_user_can( $c ) { return true; }
function is_super_admin() { return true; }
function is_network_admin() { return $GLOBALS['network_admin']; }
function check_admin_referer( $a ) { return isset( $_POST['_wpnonce'] ) && 'n:' . $a === $_POST['_wpnonce']; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="n:' . $a . '">'; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=n:' . $a; }
function submit_button( $text = 'Guardar', $type = 'primary', $name = 'submit' ) { echo '<input type="submit" name="' . $name . '" value="' . $text . '">'; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function get_site_option( $k, $d = false ) { return $GLOBALS['site_options'][ $k ] ?? $d; }
function update_site_option( $k, $v ) { $GLOBALS['site_options'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function esc_url_raw( $u ) { return (string) $u; }
function esc_url( $u ) { return (string) $u; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function wp_unslash( $v ) { return $v; }
function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
function network_admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/network/' . $p; }
function checked( $a, $b = true, $echo = true ) { return $a == $b ? 'checked' : ''; }
function selected( $a, $b = true, $echo = true ) { return $a == $b ? 'selected' : ''; }
function mysql2date( $f, $d ) { return $d; }
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-content-playbook.php';

const IA_TABS = array( 'general', 'providers', 'profiles', 'writing', 'chat', 'knowledge', 'images', 'advanced' );
/** Modo de la prueba: 'single' (WordPress individual), 'site' (un sitio de una red) o 'network' (administración de la red). */
function mode( $mode ) { $GLOBALS['multisite'] = 'single' !== $mode; $GLOBALS['network_admin'] = 'network' === $mode; }
function screen() { if ( $GLOBALS['network_admin'] ) Digitalisimo_AI::network_page(); else Digitalisimo_AI::page(); }
function render( $tab ) { $_GET = array( 'tab' => $tab ); $_POST = array(); ob_start(); screen(); return ob_get_clean(); }
/** Pinta la pestaña, la envía con $fill y devuelve la página que se ve después de guardar (y el envío). */
function submit_form( $tab, array $fill ) {
	$form = digi_browser_form( render( $tab ), $fill );
	check( $form['pressed'], "La pestaña $tab tiene su botón de guardar" );
	$_GET = array( 'tab' => $tab ); $_POST = $form['post'];
	ob_start(); screen(); $after = ob_get_clean();
	return array( $after, $form );
}
function site_o() { return (array) get_option( 'digitalisimo_ai_options', array() ); }
function network_o() { return (array) get_site_option( 'digitalisimo_ai_options', array() ); }

// 1. Sitio individual: escribir la clave de Anthropic, activarla y guardar.
mode( 'single' );
list( $after ) = submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-ant-PRUEBA-123', 'providers[anthropic][enabled]' => '1' ) );
$o = site_o();
check( 'sk-ant-PRUEBA-123' === ( $o['providers']['anthropic']['key'] ?? null ), 'Guardar en Proveedores conserva la clave escrita' );
check( ! empty( $o['providers']['anthropic']['enabled'] ), 'Guardar en Proveedores conserva «Activar»' );
check( false !== strpos( $after, 'Configuración guardada.' ), 'Tras guardar se ve «Configuración guardada.»' );
check( false !== strpos( $after, 'Guardada · escribe una nueva sólo si quieres reemplazarla' ), 'Tras guardar, el proveedor dice que su clave está guardada' );
check( false === strpos( $after, 'sk-ant-PRUEBA-123' ), 'La clave no vuelve a imprimirse en la página' );

check( false !== strpos( $after, 'name="providers[anthropic][key]" autocomplete="new-password" value="••••••••••••"' ), 'Con una clave guardada, el campo muestra la máscara ••••, no un campo vacío' );

// 2. Volver a guardar sin tocar la clave (llega la máscara) no la borra; vaciar el campo tampoco; una nueva la
// reemplaza y «Quitar la clave» la borra.
submit_form( 'providers', array() );
check( 'sk-ant-PRUEBA-123' === ( site_o()['providers']['anthropic']['key'] ?? null ), 'Guardar otra vez con la máscara conserva la clave' );
submit_form( 'providers', array( 'providers[anthropic][key]' => '' ) );
check( 'sk-ant-PRUEBA-123' === ( site_o()['providers']['anthropic']['key'] ?? null ), 'Guardar con el campo vaciado conserva la clave' );
submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-ant-NUEVA' ) );
check( 'sk-ant-NUEVA' === ( site_o()['providers']['anthropic']['key'] ?? null ), 'Escribir una clave nueva la reemplaza' );
submit_form( 'providers', array( 'providers[anthropic][key]' => '••••••••••••sk-ant-PEGADA' ) );
check( 'sk-ant-PEGADA' === ( site_o()['providers']['anthropic']['key'] ?? null ), 'Pegar la clave nueva detrás de la máscara guarda sólo la clave' );
submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-ant-DELANTE••••••••••••' ) );
check( 'sk-ant-DELANTE' === ( site_o()['providers']['anthropic']['key'] ?? null ), 'Pegarla delante de la máscara también guarda sólo la clave' );
check( false !== strpos( render( 'providers' ), 'value="••••••••••••" onfocus="this.select()"' ), 'Al enfocar el campo se selecciona la máscara: lo que se pegue la reemplaza' );
list( $after ) = submit_form( 'providers', array( 'providers[anthropic][remove]' => '1' ) );
check( '' === ( site_o()['providers']['anthropic']['key'] ?? null ) && false !== strpos( $after, 'Sin clave configurada' ), '«Quitar la clave» la borra' );
submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-ant-PRUEBA-123' ) );

// 3. Otras pestañas también guardan (el mismo botón servía a todas), y la redacción conserva sus saltos de línea.
submit_form( 'advanced', array( 'ai[log_retention]' => '45' ) );
check( '45' === (string) ( site_o()['log_retention'] ?? '' ), 'La pestaña Avanzado también guarda' );
submit_form( 'writing', array( 'ai[editorial_method]' => "Regla uno\nRegla dos" ) );
check( "Regla uno\nRegla dos" === ( site_o()['editorial_method'] ?? '' ), 'Redacción conserva las líneas de sus textareas' );
check( array() === network_o(), 'Un sitio individual nunca escribe opciones de red' );
// 3b. «Permitir que Codex suba imágenes destacadas mediante MCP» se retiró (el MCP trae acceso total, 2026-11-27): no se
// pinta, y guardar borra el valor viejo aunque llegue en el envío.
check( false === strpos( render( 'general' ), 'mcp_featured_image_enabled' ), 'General ya no pinta el interruptor de imágenes por MCP' );
submit_form( 'general', array( 'ai[mcp_featured_image_enabled]' => '0' ) );
check( ! array_key_exists( 'mcp_featured_image_enabled', site_o() ), 'Guardar borra el interruptor viejo de imágenes por MCP' );

/** Las mismas comprobaciones en cada superficie: cada pestaña guarda lo escrito donde corresponde. */
function every_tab_saves( $mode, $read, $other ) {
	mode( $mode );
	$before_other = $other();
	submit_form( 'general', array( 'ai[knowledge_enabled]' => '1' ) );
	check( 1 === ( $read()['knowledge_enabled'] ?? null ), "$mode: General guarda" );
	submit_form( 'providers', array( 'providers[openai][key]' => "sk-$mode", 'providers[openai][enabled]' => '1', 'providers[openai][url]' => "https://$mode.test/v1" ) );
	check( "sk-$mode" === ( $read()['providers']['openai']['key'] ?? null ) && ! empty( $read()['providers']['openai']['enabled'] ) && "https://$mode.test/v1" === ( $read()['providers']['openai']['url'] ?? null ), "$mode: Proveedores guarda clave, activación y URL" );
	list( $after ) = submit_form( 'providers', array() );
	check( "sk-$mode" === ( $read()['providers']['openai']['key'] ?? null ) && false !== strpos( $after, 'value="••••••••••••"' ) && false === strpos( $after, "sk-$mode" ), "$mode: la clave guardada se ve como •••• (nunca el secreto) y guardar sin tocarla la conserva" );
	submit_form( 'profiles', array( 'profiles[seo][provider]' => 'anthropic', 'profiles[seo][model]' => "modelo-$mode" ) );
	check( 'anthropic' === ( $read()['profiles']['seo']['provider'] ?? null ) && "modelo-$mode" === ( $read()['profiles']['seo']['model'] ?? null ), "$mode: Perfiles guarda" );
	submit_form( 'writing', array( 'ai[editorial_voice]' => "Voz $mode\nsegunda línea" ) );
	check( "Voz $mode\nsegunda línea" === ( $read()['editorial_voice'] ?? null ), "$mode: Redacción guarda con sus líneas" );
	submit_form( 'chat', array( 'ai[context_posts]' => '7' ) );
	check( '7' === (string) ( $read()['context_posts'] ?? '' ), "$mode: Chatbot guarda" );
	submit_form( 'knowledge', array( 'ai[knowledge_auto_index]' => '', 'ai[knowledge_results]' => '6' ) );
	check( 0 === ( $read()['knowledge_auto_index'] ?? null ) && '6' === (string) ( $read()['knowledge_results'] ?? '' ), "$mode: Conocimiento guarda «Actualizar al guardar contenido público» y las fuentes" );
	submit_form( 'images', array( 'ai[image_quality]' => 'high', 'profiles[images][model]' => 'gpt-image-1-mini' ) );
	check( 'high' === ( $read()['image_quality'] ?? null ) && 'gpt-image-1-mini' === ( $read()['profiles']['images']['model'] ?? null ), "$mode: Imágenes guarda" );
	submit_form( 'advanced', array( 'ai[assistant_rate_minute]' => '4' ) );
	check( '4' === (string) ( $read()['assistant_rate_minute'] ?? '' ), "$mode: Avanzado guarda" );
	check( $before_other === $other(), "$mode: guardar no toca el otro almacén (sitio o red)" );
}

// 4. Administración de la red: cada pestaña guarda en la opción de red y nada en la del sitio principal.
$GLOBALS['options'] = array( 'digitalisimo_ai_options' => array( 'inherit_network' => 0, 'editorial_voice' => 'VOZ DEL SITIO PRINCIPAL' ) ); $GLOBALS['site_options'] = array();
mode( 'network' );
check( false === strpos( render( 'writing' ), 'VOZ DEL SITIO PRINCIPAL' ), 'La red pinta su propia redacción, no la del sitio principal (al guardar la copiaba a la red)' );
every_tab_saves( 'network', 'network_o', 'site_o' );
check( 'VOZ DEL SITIO PRINCIPAL' === site_o()['editorial_voice'], 'La red no cambia la redacción del sitio principal' );

// 5. Un sitio de la red que personaliza: guarda en el sitio y nada en la red.
$network_before = network_o();
$GLOBALS['options'] = array( 'digitalisimo_ai_options' => array( 'inherit_network' => 0 ) );
every_tab_saves( 'site', 'site_o', 'network_o' );
check( $network_before === network_o(), 'Un sitio nunca escribe la configuración de red' );

// 6. Un sitio que hereda (lo nuevo en una red): ve los valores de la red, no puede editarlos y sólo cambia la herencia.
$GLOBALS['options'] = array(); mode( 'site' );
foreach ( IA_TABS as $tab ) {
	$html = render( $tab );
	check( false !== strpos( $html, 'hereda la configuración de IA de la red' ), "Un sitio que hereda lo dice en $tab" );
	$form = digi_browser_form( $html, array() );
	$sent = array_diff( $form['names'], array( '_wpnonce', 'digitalisimo_ai_save' ) );
	check( 'general' === $tab ? $form['pressed'] && array( 'ai[inherit_network]' ) === array_values( $sent ) : ! $form['pressed'] && ! $sent, "Heredando, $tab no envía valores (antes se guardaban en el sitio y la pantalla volvía a mostrar los de la red): " . implode( ', ', $sent ) );
	check( 'general' === $tab || $form['disabled'], "Heredando, $tab muestra sus campos deshabilitados" );
}
check( false !== strpos( render( 'providers' ), 'Guardada · escribe una nueva' ), 'Heredando, Proveedores muestra que la red tiene su clave' );
submit_form( 'general', array( 'ai[inherit_network]' => '' ) );
check( 0 === ( site_o()['inherit_network'] ?? null ) && $network_before === network_o(), 'Desmarcar la herencia la guarda en el sitio y no toca la red' );
check( 1 === ( site_o()['knowledge_enabled'] ?? null ) && 'Voz network' === substr( (string) ( site_o()['editorial_voice'] ?? '' ), 0, 11 ), 'Dejar de heredar conserva lo que se veía (los valores de la red)' );
check( '' === ( site_o()['providers']['openai']['key'] ?? '' ) && false !== strpos( render( 'providers' ), 'Escribe la clave de este sitio' ), 'Dejar de heredar nunca copia las claves de la red: el sitio pide la suya' );
list( $after ) = submit_form( 'providers', array( 'providers[anthropic][key]' => 'sk-propia', 'providers[anthropic][enabled]' => '1' ) );
check( 'sk-propia' === ( site_o()['providers']['anthropic']['key'] ?? null ) && false === strpos( $after, 'hereda la configuración de IA de la red' ), 'Ya personalizado, Proveedores guarda la clave propia del sitio' );

// 7. Paridad: las mismas opciones en cada pestaña del sitio y de la red; sólo la herencia es exclusiva del sitio.
foreach ( IA_TABS as $tab ) {
	mode( 'site' ); $site = digi_browser_form( render( $tab ) )['names'];
	mode( 'network' ); $network = digi_browser_form( render( $tab ) )['names'];
	$site_keys = array_diff( array_merge( digi_browser_keys( $site, 'ai' ), preg_grep( '/^(providers|profiles)\[/', $site ) ), array( 'inherit_network' ) );
	$network_keys = array_merge( digi_browser_keys( $network, 'ai' ), preg_grep( '/^(providers|profiles)\[/', $network ) );
	sort( $site_keys ); sort( $network_keys );
	check( $site_keys === $network_keys, "Paridad en $tab: sitio [" . implode( ', ', array_diff( $site_keys, $network_keys ) ) . '] red [' . implode( ', ', array_diff( $network_keys, $site_keys ) ) . ']' );
}

echo "IA Tools, guardar ajustes en sitio, red y sitio de red: $checks comprobaciones correctas.\n";
