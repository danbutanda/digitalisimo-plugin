<?php
// WooCommerce → Servicios de hosting y ajustes de la venta automática de DIGITALÍSIMO Hosting, sin WordPress:
//   1. la lista muestra cliente, dominio, plan, cuenta de Webuzo, vencimiento y estado, con las acciones que tocan;
//      «Suspender» pide confirmación;
//   2. cada acción exige manage_woocommerce y el nonce de ESA acción y ESE servicio; la llamada remota se encola;
//      «Reenviar accesos» manda otra vez el correo con la contraseña guardada cifrada;
//   3. ajustes: URL de Webuzo sólo https, nameservers saneados (los de DIGITALÍSIMO por defecto), días de gracia y
//      prefijo; la clave de Webuzo con la máscara, «Quitar la clave» y nunca devuelta a la página;
//   4. multisitio: la red define el servidor Webuzo y los nameservers, un sitio los hereda sólo con «Heredar» (sin ver
//      la clave), la parada por clave rechazada es de toda la red y se levanta guardando una clave nueva en la red;
//   5. con las constantes de wp-config.php, manda la constante y la pantalla no pinta el campo.
require __DIR__ . '/fixtures/hosting-provision.php';
$W = $GLOBALS['webuzo']; $N = $GLOBALS['namecheap'];
$P = 'Digitalisimo_Hosting_Provisioning'; $S = 'Digitalisimo_Hosting_Services_Page';
hosting_settings();
$N->balance = 100;

$o = hosting_order( 'pantalla.com', 14695, 201 ); $o->update_status( 'processing' ); run_queue();
$svc = (int) $o->meta[ $P::META_SERVICE ];
$o2 = hosting_order( 'detenido.com', 14693, 201, array( 'address_1' => '' ) ); $o2->update_status( 'processing' ); run_queue();
$svc2 = (int) $o2->meta[ $P::META_SERVICE ];

// 1. La lista.
function screen() { ob_start(); Digitalisimo_Hosting_Services_Page::render(); return ob_get_clean(); }
$html = screen();
check( false !== strpos( $html, '<h1>Servicios de hosting</h1><hr class="wp-header-end">' ), 'Pantalla con su título y la marca de los avisos' );
check( preg_match( '#<tr data-service="' . $svc . '">.*?Ana Pérez López.*?ana@cliente\.test.*?pantalla\.com.*?digitalisimo_growth.*?pantalla.*?' . ( gmdate( 'Y' ) + 1 ) . '-.*?Activo#s', $html ), 'Fila con cliente, dominio, plan, cuenta de Webuzo, vencimiento y estado' );
check( preg_match( '#<tr data-service="' . $svc2 . '">.*?En espera.*?Faltan datos del titular.*?Reintentar#s', $html ), 'Un servicio detenido muestra el motivo y «Reintentar»' );
check( preg_match( '#<form[^>]+onsubmit="return window\.confirm\([^"]*Suspender la cuenta[^"]*"[^>]*>.*?name="op" value="suspend".*?name="_wpnonce" value="n:digitalisimo_hosting_service_suspend_' . $svc . '"#s', $html ), '«Suspender» pide confirmación y lleva el nonce de esa acción y ese servicio' );
check( false !== strpos( $html, 'value="n:digitalisimo_hosting_service_resend_' . $svc . '"' ) && false !== strpos( $html, 'action="https://tienda.test/wp-admin/admin-post.php"' ), '«Reenviar accesos» por admin-post.php del sitio' );
check( false === strpos( $html, $W->users['pantalla']['pass'] ), 'La pantalla nunca muestra la contraseña' );
$GLOBALS['caps']['manage_woocommerce'] = false;
check( '' === screen(), 'Sin manage_woocommerce no se pinta nada' );

// 2. Acciones: capacidad y nonce.
function act( $op, $id, $nonce = null ) {
	$_POST = array( 'action' => 'digitalisimo_hosting_service', 'service' => $id, 'op' => $op, '_wpnonce' => $nonce ?? 'n:digitalisimo_hosting_service_' . $op . '_' . $id );
	try { Digitalisimo_Hosting_Services_Page::handle(); return 'sin salida'; }
	catch ( Hosting_Test_Redirect $e ) { return 'redirect:' . $e->getMessage(); }
	catch ( Hosting_Test_Die $e ) { return 'die:' . $e->status; }
	finally { $_POST = array(); }
}
check( 'die:403' === act( 'suspend', $svc ) && ! pending_actions(), 'Sin manage_woocommerce: 403 y nada en cola' );
$GLOBALS['caps']['manage_woocommerce'] = true;
check( 'die:403' === act( 'suspend', $svc, 'n:otra-cosa' ) && ! pending_actions(), 'Nonce inválido: rechazado y nada en cola' );
check( 'die:403' === act( 'suspend', $svc, 'n:digitalisimo_hosting_service_suspend_' . $svc2 ) && ! pending_actions(), 'El nonce de otro servicio no sirve' );
check( 'die:403' === act( 'suspend', $svc, 'n:digitalisimo_hosting_service_unsuspend_' . $svc ) && ! pending_actions(), 'El nonce de otra acción no sirve' );
check( 'die:400' === act( 'borrar', $svc ) && ! pending_actions(), 'Una acción desconocida se rechaza' );
check( 'die:404' === act( 'suspend', 999999 ), 'Un servicio inexistente se rechaza' );
$calls = count( $W->calls );
$r = act( 'suspend', $svc );
check( 'redirect:https://tienda.test/wp-admin/admin.php?page=digitalisimo-hosting-services&done=suspend' === $r && 1 === count( pending_actions( $P::HOOK_SUSPEND ) ) && $calls === count( $W->calls ), 'Suspender: se encola (sin llamar a Webuzo durante la petición) y vuelve a la lista' );
run_queue();
check( 'suspended' === $P::svc( $svc, 'status' ) && 'suspended' === $W->users['pantalla']['status'], 'La cola suspende la cuenta en Webuzo' );
check( false !== strpos( screen(), 'Reactivar' ), 'Suspendido, la fila ofrece «Reactivar»' );
act( 'unsuspend', $svc ); run_queue();
check( 'active' === $P::svc( $svc, 'status' ) && '' === $W->users['pantalla']['status'], 'Reactivar desde la lista' );
$before = count( $GLOBALS['wc_mails'] );
act( 'resend', $svc ); run_queue();
$last = end( $GLOBALS['wc_mails'] );
check( count( $GLOBALS['wc_mails'] ) === $before + 1 && 'digitalisimo_hosting_access' === $last['id'] && false !== strpos( $last['body'], esc_html( $W->users['pantalla']['pass'] ) ), '«Reenviar accesos» manda otra vez el correo con la contraseña inicial' );
$o2->billing['address_1'] = 'Calle 5'; $o2->billing['country'] = 'MX';
check( 'redirect:https://tienda.test/wp-admin/admin.php?page=digitalisimo-hosting-services&done=retry' === act( 'retry', $svc2 ), '«Reintentar» encola lo que está a medias' );
run_queue();
check( 'active' === $P::svc( $svc2, 'status' ) && 'completed' === $o2->status, 'Con los datos completos, el reintento entrega el servicio' );
check( false !== strpos( act( 'retry', $svc ), 'done=nothing' ), 'Reintentar un servicio entregado no encola nada' );
$log = $P::svc( $svc, 'log' );
check( false !== strpos( serialize( $log ), 'Acción manual «suspend» de admin' ), 'Cada acción manual queda en el registro del servicio' );

// 3. Ajustes de la venta automática (sitio individual).
function save_site( array $fill ) {
	$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => $fill );
	ob_start(); Digitalisimo_Hosting::page(); $html = ob_get_clean(); $_POST = array();
	return $html;
}
$html = save_site( array( 'webuzo_url' => 'http://inseguro.test:2005', 'nameservers' => 'ns1.digitalisimo.website, <script>, ns2.digitalisimo.website', 'grace_days' => '500', 'webuzo_user_prefix' => 'Prue ba!!xyz', 'provision_enabled' => '1', 'webuzo_api_key' => '••••••••••••' ) );
$opt = get_option( 'digitalisimo_hosting_options' );
check( '' === $opt['webuzo_url'] && 'ns1.digitalisimo.website, ns2.digitalisimo.website' === $opt['nameservers'] && 90 === $opt['grace_days'] && 'prueba' === $opt['webuzo_user_prefix'], 'URL sin https rechazada, nameservers saneados, gracia ≤ 90 y prefijo sólo letras y números' );
check( $W->key === $opt['webuzo_api_key'], 'Reenviar la máscara conserva la clave de Webuzo' );
save_site( array( 'webuzo_url' => 'https://webuzo.test:2005/', 'nameservers' => '' ) );
$opt = get_option( 'digitalisimo_hosting_options' );
check( 'https://webuzo.test:2005' === $opt['webuzo_url'] && Digitalisimo_Hosting::DEFAULT_NAMESERVERS === $opt['nameservers'], 'URL https guardada sin la barra final; nameservers vacíos = los de DIGITALÍSIMO' );
$html = save_site( array() );
check( preg_match( '#name="hosting\[webuzo_api_key\]" value="••••••••••••" autocomplete="new-password" onfocus="this\.select\(\)"#u', $html ) && false === strpos( $html, $W->key ) && false !== strpos( $html, 'name="hosting[webuzo_api_key_remove]"' ), 'La clave de Webuzo se ve como ••••, se selecciona al enfocarla, se puede quitar y nunca vuelve a la página' );
check( false !== strpos( $html, 'name="hosting[provision_enabled]" value="1" checked' ) && false !== strpos( $html, 'name="hosting[grace_days]" value="90"' ) && false !== strpos( $html, 'Venta automática' ), 'La pantalla del sitio pinta la venta automática con sus valores' );
save_site( array( 'webuzo_api_key_remove' => '1', 'webuzo_api_key' => '' ) );
check( '' === get_option( 'digitalisimo_hosting_options' )['webuzo_api_key'], '«Quitar la clave» borra la de Webuzo' );
$before = count( $W->calls ); $o3 = hosting_order( 'sinclave.com' ); $o3->update_status( 'processing' ); run_queue();
check( $before === count( $W->calls ) && 'on-hold' === $o3->status && false !== strpos( end( $o3->notes ), 'Faltan la URL, el usuario o la clave de la API de Webuzo' ), 'Sin clave de Webuzo no se llama: el pedido queda en espera diciendo qué falta' );

// 4. Multisitio: la red define el servidor; un sitio lo hereda sólo con «Heredar»; la parada es de toda la red.
$GLOBALS['multisite'] = true; $GLOBALS['network_admin'] = true;
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_network_save', 'digitalisimo_hosting_network_save' => 1, 'hosting' => array( 'api_user' => 'digitalisimo', 'username' => 'digitalisimo', 'api_key' => $N->key, 'client_ip' => '203.0.113.10', 'webuzo_url' => 'https://webuzo.test:2005', 'webuzo_api_user' => 'root', 'webuzo_api_key' => 'CLAVE-RED-MALA', 'nameservers' => 'ns1.red.test, ns2.red.test' ) );
ob_start(); Digitalisimo_Hosting::network_page(); $net = ob_get_clean(); $_POST = array();
$n = $GLOBALS['site_options']['digitalisimo_hosting_options'];
check( 'https://webuzo.test:2005' === $n['webuzo_url'] && 'CLAVE-RED-MALA' === $n['webuzo_api_key'] && 'ns1.red.test, ns2.red.test' === $n['nameservers'], 'La red guarda en sitemeta el servidor Webuzo, su clave y los nameservers' );
check( false !== strpos( $net, 'name="hosting[webuzo_url]"' ) && false === strpos( $net, 'hosting[provision_enabled]' ) && false === strpos( $net, 'hosting[grace_days]' ) && false === strpos( $net, 'CLAVE-RED-MALA' ), 'La pantalla de red pinta el servidor (no la tienda) y nunca la clave' );
$GLOBALS['network_admin'] = false;
$site = get_option( 'digitalisimo_hosting_options' );
check( 'https://webuzo.test:2005' === $site['webuzo_url'] && '' === $site['webuzo_api_key'], 'Un sitio sin «Heredar» sigue con lo suyo' );
save_site( array( 'inherit_network' => '1' ) );
$e = Digitalisimo_Hosting::settings();
check( 'CLAVE-RED-MALA' === $e['webuzo_api_key'] && 'ns1.red.test, ns2.red.test' === $e['nameservers'] && 1 === $e['provision_enabled'] && 90 === $e['grace_days'], 'Con «Heredar», el sitio usa el servidor y los nameservers de la red y conserva su venta automática' );
$html = save_site( array( 'inherit_network' => '1' ) );
check( false === strpos( $html, 'hosting[webuzo_url]' ) && false === strpos( $html, 'CLAVE-RED-MALA' ) && false !== strpos( $html, 'Usa el servidor Webuzo y los nameservers de la red' ), 'Con «Heredar», la pantalla del sitio no muestra ni deja editar el servidor de la red' );
check( false === strpos( serialize( $GLOBALS['options'] ), 'CLAVE-RED-MALA' ), 'La clave de la red nunca se copia a las opciones del sitio' );
$o4 = hosting_order( 'red.com' ); $o4->update_status( 'processing' ); run_queue();
check( 'on-hold' === $o4->status && isset( $GLOBALS['site_options'][ Digitalisimo_Hosting_Webuzo::BLOCK ] ) && ! isset( $GLOBALS['options'][ Digitalisimo_Hosting_Webuzo::BLOCK ] ), 'La clave de la red rechazada pone la parada en la red (sitemeta), no sólo en el sitio' );
$GLOBALS['network_admin'] = true;
ob_start(); Digitalisimo_Hosting::network_page(); $net = ob_get_clean();
check( false !== strpos( $net, 'Aprovisionamiento detenido' ), 'La pantalla de red muestra la parada' );
$before = count( $W->calls );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_network_save', 'digitalisimo_hosting_network_save' => 1, 'hosting' => array( 'webuzo_api_key' => $W->key ) );
ob_start(); Digitalisimo_Hosting::network_page(); ob_end_clean(); $_POST = array(); $GLOBALS['network_admin'] = false;
check( ! isset( $GLOBALS['site_options'][ Digitalisimo_Hosting_Webuzo::BLOCK ] ) && $before === count( $W->calls ), 'Guardar la clave nueva en la red levanta la parada (sin llamar a Webuzo al guardar)' );
run_queue();
check( 'completed' === $o4->status && 'red.com' === ( $W->users['pruebared']['domain'] ?? '' ) && array( 'ns1.red.test', 'ns2.red.test' ) === $N->domains['red.com']['ns'], 'Y el pedido detenido se entrega con el servidor y los nameservers de la red (usuario con el prefijo del sitio)' );
$GLOBALS['multisite'] = false;

// 5. Constantes de wp-config.php: mandan y la pantalla no pinta el campo.
update_option( 'digitalisimo_hosting_options', array( 'webuzo_api_key' => 'DE-LA-BASE' ) + get_option( 'digitalisimo_hosting_options' ) );
define( 'DIGITALISIMO_WEBUZO_API_KEY', $W->key );
$html = save_site( array() );
check( $W->key === Digitalisimo_Hosting::settings()['webuzo_api_key'] && false === strpos( $html, 'name="hosting[webuzo_api_key]"' ) && false !== strpos( $html, 'Definida en wp-config.php (<code>DIGITALISIMO_WEBUZO_API_KEY</code>)' ) && false === strpos( $html, $W->key ), 'Con DIGITALISIMO_WEBUZO_API_KEY manda la constante y la pantalla sólo dice dónde está' );

echo "Hosting, servicios y ajustes de la venta automática (sitio y red): $checks comprobaciones correctas.\n";
