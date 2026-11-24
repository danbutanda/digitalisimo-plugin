<?php
// WooCommerce → Servicios de hosting y paridad multisitio de la venta automática, sin WordPress:
//   1. la lista muestra cliente, dominio, plan, cuenta de Webuzo, vencimiento y estado, con sus acciones;
//   2. cada acción exige manage_woocommerce y un nonce atado a la acción y al servicio; «Suspender» pide confirmación;
//      la llamada a Webuzo se encola y no corre en la petición;
//   3. suspender, reactivar, reenviar accesos y reintentar hacen lo suyo;
//   4. red: los campos de Webuzo se guardan en la red con las mismas reglas que en el sitio (URL https, nameservers,
//      clave enmascarada); un sitio los usa sólo si hereda; lo de la tienda (venta automática, gracia, prefijo) nunca
//      está en la red; la parada por clave rechazada vale para toda la red y la levanta la clave nueva de la red; cada
//      sitio ve sólo sus servicios.
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$S = 'Digitalisimo_Hosting_Services_Page';
function digi_page() { ob_start(); Digitalisimo_Hosting_Services_Page::render(); return ob_get_clean(); }
function digi_post( array $post ) {
	$_POST = $post; $GLOBALS['redirect'] = null;
	try { Digitalisimo_Hosting_Services_Page::handle(); } catch ( Digi_Test_Redirect $r ) { $_POST = array(); return array( 'redirect', $r->getMessage() ); } catch ( Digi_Test_Die $d ) { $_POST = array(); return array( 'die', $d->status ); }
	$_POST = array(); return array( 'none', '' );
}
function digi_action( $op, $id, $nonce_op = null, $nonce_id = null ) { return digi_post( array( 'action' => 'digitalisimo_hosting_service', 'service' => $id, 'op' => $op, '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( $nonce_op ?? $op, $nonce_id ?? $id ) ) ); }
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }

digi_store_settings();
$order = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'lista.com' ) ) ) );
$order->update_status( 'processing' ); digi_run_queue();
$s = (int) $order->get_meta( '_digitalisimo_service' );
$password = $GLOBALS['webuzo']['users']['lista']['pass'];

// ── 1. Lista ────────────────────────────────────────────────────────────────────────────────────────────────
$html = digi_page();
check( false !== strpos( $html, '<h1>Servicios de hosting</h1><hr class="wp-header-end">' ), 'La pantalla lleva su título y la marca de los avisos' );
foreach ( array( 'Ana López', 'ana@cliente.test', 'lista.com', 'digitalisimo_starter', '<td>lista</td>', gmdate( 'Y-m-d', strtotime( '+1 year' ) ), 'Activo', '#' . $order->get_id() ) as $needle ) check( false !== strpos( $html, $needle ), "La lista muestra «{$needle}»" );
check( false === strpos( $html, $password ) && false === strpos( $html, (string) $P::svc( $s, 'pass' ) ), 'La lista no muestra la contraseña ni su cifrado' );
check( false !== strpos( $html, 'name="op" value="suspend"' ) && false !== strpos( $html, 'name="op" value="resend"' ) && false === strpos( $html, 'name="op" value="unsuspend"' ) && false === strpos( $html, 'name="op" value="retry"' ), 'Un servicio activo ofrece «Suspender» y «Reenviar accesos»' );
check( false !== strpos( $html, 'onsubmit="return window.confirm(' . esc_attr( wp_json_encode( '¿Suspender la cuenta «lista» de lista.com? El sitio y el correo del cliente dejarán de funcionar hasta reactivarla.' ) ) . ')"' ) && false !== strpos( $html, 'value="n:digitalisimo_hosting_service_suspend_' . $s . '"' ), '«Suspender» pide confirmación y lleva su nonce' );
check( false !== strpos( $html, 'action="https://tienda.test/wp-admin/admin-post.php"' ), 'Las acciones van por admin-post.php del sitio' );
$GLOBALS['caps'] = array( 'manage_options' );
check( '' === digi_page(), 'Sin manage_woocommerce la pantalla no pinta nada' );

// ── 2. Capacidad y nonce ────────────────────────────────────────────────────────────────────────────────────
$r = digi_action( 'suspend', $s );
check( array( 'die', 403 ) === $r && ! $GLOBALS['queue'], 'Sin manage_woocommerce la acción se rechaza (403) y no encola nada' );
$GLOBALS['caps'] = array( 'manage_woocommerce' );
check( array( 'die', 403 ) === digi_action( 'suspend', $s, 'unsuspend' ) && ! $GLOBALS['queue'], 'El nonce de otra acción no sirve' );
check( array( 'die', 403 ) === digi_action( 'suspend', $s, 'suspend', $s + 1 ) && ! $GLOBALS['queue'], 'El nonce de otro servicio no sirve' );
check( array( 'die', 403 ) === digi_post( array( 'service' => $s, 'op' => 'suspend' ) ) && ! $GLOBALS['queue'], 'Sin nonce no hay acción' );
check( array( 'die', 400 ) === digi_action( 'delete', $s ), 'Una acción desconocida se rechaza' );
check( array( 'die', 404 ) === digi_action( 'suspend', 99999 ), 'Un servicio inexistente se rechaza' );
check( array( 'die', 404 ) === digi_action( 'suspend', $order->get_id() ), 'Un ID que no es un servicio (el del pedido) se rechaza' );

// ── 3. Acciones ─────────────────────────────────────────────────────────────────────────────────────────────
$calls = count( $GLOBALS['webuzo']['calls'] );
$r = digi_action( 'suspend', $s );
check( array( 'redirect', 'https://tienda.test/wp-admin/admin.php?page=digitalisimo-hosting-services&done=suspend' ) === $r, 'Suspender vuelve a la lista con el aviso' );
check( $calls === count( $GLOBALS['webuzo']['calls'] ) && 1 === count( $GLOBALS['queue'] ), 'La suspensión se encola y no llama a Webuzo durante la petición' );
digi_run_queue();
check( 'suspended' === $P::svc( $s, 'status' ) && 1 === digi_webuzo_count( 'users', 'suspend', 'lista' ), 'La suspensión manual llega a Webuzo' );
check( ! digi_mails_to( 'ana@cliente.test' ) || 1 === count( digi_mails_to( 'ana@cliente.test' ) ), 'Una suspensión manual no manda el aviso de falta de pago' );
$html = digi_page();
check( false !== strpos( $html, 'Suspendido' ) && false !== strpos( $html, 'name="op" value="unsuspend"' ) && false === strpos( $html, 'name="op" value="suspend"' ), 'Suspendido ofrece «Reactivar»' );
digi_action( 'unsuspend', $s ); digi_run_queue();
check( 'active' === $P::svc( $s, 'status' ) && 1 === digi_webuzo_count( 'users', 'unsuspend', 'lista' ), '«Reactivar» la reactiva' );
$GLOBALS['mails'] = array();
digi_action( 'resend', $s ); digi_run_queue();
$mail = digi_mails_to( 'ana@cliente.test' );
check( 1 === count( $mail ) && false !== strpos( $mail[0]['body'], $password ) && false === strpos( implode( "\n", $order->notes ), $password ), '«Reenviar accesos» manda la contraseña inicial y la nota no la lleva' );
check( array( 'redirect', 'https://tienda.test/wp-admin/admin.php?page=digitalisimo-hosting-services&done=nothing' ) === digi_action( 'retry', $s ), '«Reintentar» sin nada a medias lo dice' );

// ── 4. Multisitio ───────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['caps'] = array( 'manage_options', 'manage_woocommerce', 'manage_network_options' );
$GLOBALS['multisite'] = true; $GLOBALS['network_admin'] = true;
ob_start(); Digitalisimo_Hosting::network_page(); $net = ob_get_clean();
check( false !== strpos( $net, 'name="hosting[webuzo_url]"' ) && false !== strpos( $net, 'name="hosting[webuzo_api_key]"' ) && false !== strpos( $net, 'name="hosting[nameservers]"' ), 'La red pinta el servidor Webuzo, su clave y los nameservers' );
check( false === strpos( $net, 'hosting[provision_enabled]' ) && false === strpos( $net, 'hosting[grace_days]' ) && false === strpos( $net, 'hosting[webuzo_user_prefix]' ), 'La red no pinta lo de cada tienda (venta automática, gracia, prefijo)' );
check( false !== strpos( $net, 'name="hosting[banxico_token]"' ) && false === strpos( $net, 'hosting[pricing_enabled]' ) && false === strpos( $net, 'hosting[pricing_margin]' ), 'La red pinta el token de Banxico (heredable) y no la fórmula de precios de cada tienda' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_network_save', 'digitalisimo_hosting_network_save' => 1, 'hosting' => array( 'webuzo_url' => 'https://webuzo.test:2005/', 'webuzo_api_user' => 'root', 'webuzo_api_key' => 'CLAVE-RED-MALA', 'nameservers' => 'NS1.Red.Test,  ns2.red.test, no valido', 'provision_enabled' => 1, 'grace_days' => 3 ) );
ob_start(); Digitalisimo_Hosting::network_page(); $after = ob_get_clean(); $_POST = array();
$n = $GLOBALS['site_options']['digitalisimo_hosting_options'];
check( 'https://webuzo.test:2005' === $n['webuzo_url'] && 'CLAVE-RED-MALA' === $n['webuzo_api_key'] && 'ns1.red.test, ns2.red.test' === $n['nameservers'], 'La red guarda URL, clave y nameservers con las mismas reglas que el sitio' );
check( ! isset( $n['provision_enabled'] ) && ! isset( $n['grace_days'] ), 'La red no acepta lo de la tienda aunque se envíe' );
check( false === strpos( $after, 'CLAVE-RED-MALA' ) && preg_match( '#name="hosting\[webuzo_api_key\]" value="••••••••••••"#u', $after ), 'La clave de la red no vuelve a la página: sale la máscara' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_network_save', 'digitalisimo_hosting_network_save' => 1, 'hosting' => array( 'webuzo_url' => 'http://inseguro.test:2005', 'nameservers' => '' ) );
ob_start(); Digitalisimo_Hosting::network_page(); ob_end_clean(); $_POST = array();
$n = $GLOBALS['site_options']['digitalisimo_hosting_options'];
check( '' === $n['webuzo_url'] && 'ns1.digitalisimo.website, ns2.digitalisimo.website' === $n['nameservers'] && 'CLAVE-RED-MALA' === $n['webuzo_api_key'], 'Una URL sin https se descarta, sin nameservers quedan los de DIGITALÍSIMO y la clave guardada se conserva' );
$GLOBALS['site_options']['digitalisimo_hosting_options']['webuzo_url'] = 'https://webuzo.test:2005';
$GLOBALS['site_options']['digitalisimo_hosting_options'] += array( 'api_user' => 'nc-api', 'username' => 'nc-user', 'api_key' => 'CLAVE-NAMECHEAP', 'client_ip' => '203.0.113.5' );
$GLOBALS['network_admin'] = false;
// Sitio 2: tienda propia que hereda la cuenta de la red.
switch_blog( 2 );
digi_store_settings( array( 'inherit_network' => 1, 'webuzo_url' => '', 'webuzo_api_key' => '' ) );
$e = Digitalisimo_Hosting::settings();
check( 'https://webuzo.test:2005' === $e['webuzo_url'] && 'CLAVE-RED-MALA' === $e['webuzo_api_key'] && 1 === $e['provision_enabled'], 'Con «Heredar», el sitio usa el servidor Webuzo de la red y conserva su venta automática' );
check( '' === digi_page() || false !== strpos( digi_page(), 'Todavía no hay servicios' ), 'El sitio 2 no ve los servicios del sitio 1' );
$o2 = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'red.com' ) ) ) );
$before = count( $GLOBALS['webuzo']['calls'] );
$o2->update_status( 'processing' ); digi_run_queue();
check( $before + 1 === count( $GLOBALS['webuzo']['calls'] ) && 'on-hold' === $o2->get_status() && $GLOBALS['site_options'][ Digitalisimo_Hosting_Webuzo::BLOCK ], 'La clave de la red rechazada detiene el sitio 2 con una sola llamada y la parada queda en la red' );
// El sitio 1 (su propia clave buena, mismo servidor y usuario): también se detiene, porque Webuzo bloquea la IP.
switch_blog( 1 );
digi_store_settings();
$o1 = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'uno.com' ) ) ) );
$o1->update_status( 'processing' ); digi_run_queue();
check( $before + 3 === count( $GLOBALS['webuzo']['calls'] ) && 'completed' === $o1->get_status(), 'Otra clave distinta de la rechazada (la del sitio 1) sigue funcionando: la parada es por clave' );
switch_blog( 2 );
$o3 = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'tres.com' ) ) ) );
$c = count( $GLOBALS['webuzo']['calls'] );
$o3->update_status( 'processing' ); digi_run_queue();
check( $c === count( $GLOBALS['webuzo']['calls'] ) && 'on-hold' === $o3->get_status(), 'Mientras tanto, la clave rechazada de la red no vuelve a llamar' );
// La red guarda la clave buena: la parada se levanta y el sitio 2 retoma lo suyo en su revisión diaria.
$GLOBALS['network_admin'] = true;
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_network_save', 'digitalisimo_hosting_network_save' => 1, 'hosting' => array( 'webuzo_api_key' => 'CLAVE-WEBUZO-OK' ) );
ob_start(); Digitalisimo_Hosting::network_page(); ob_end_clean(); $_POST = array();
$GLOBALS['network_admin'] = false;
check( null === Digitalisimo_Hosting_Webuzo::from_values( $GLOBALS['site_options']['digitalisimo_hosting_options'] )->blocked(), 'La clave nueva de la red ya no está detenida (la rechazada se recuerda)' );
$P::daily(); digi_run_queue();
check( 'completed' === $o2->get_status() && 'completed' === $o3->get_status(), 'El sitio 2 retoma sus pedidos detenidos' );
$html = digi_page();
check( false !== strpos( $html, 'red.com' ) && false !== strpos( $html, 'tres.com' ) && false === strpos( $html, 'lista.com' ) && false === strpos( $html, 'uno.com' ), 'Cada sitio ve sólo sus servicios' );
// Sin «Heredar», el sitio no usa nada de la red.
update_option( 'digitalisimo_hosting_options', array( 'inherit_network' => 0 ) + get_option( 'digitalisimo_hosting_options' ) );
check( '' === Digitalisimo_Hosting::settings()['webuzo_url'], 'Sin «Heredar», el sitio no usa el servidor Webuzo de la red' );

echo "Hosting, servicios y red: {$GLOBALS['checks']} comprobaciones correctas.\n";
