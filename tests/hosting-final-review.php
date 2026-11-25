<?php
// Revisión final de la venta automática de DIGITALÍSIMO Hosting (2026-11-25), sin WordPress y con Webuzo y Namecheap
// simulados como los reales (tests/fixtures/hosting-wp.php). Cada caso del sondeo del revisor (P1–P16) falla con el
// código anterior al arreglo (commit 7c2ed5ad):
//   A1 (P6)  suspender o reactivar sólo si la cuenta de Webuzo sigue siendo la del dominio del servicio; «Dar de baja»;
//   A2 (P2)  una renovación cobrada que no llegó a ejecutarse se reencola y se avisa; «Reintentar» también la rescata;
//   A3 (P3, P12) confirmaciones agotadas por red: se limpia y se avisa; al pagar se reconcilia con la lista real;
//   M1 (P4)  un error definitivo de registro borra la marca de intento; adoptar exige que se haya creado después;
//   M2 (P1)  con la venta apagada nada de la cola llama a Webuzo;
//   M3 (P7)  un error de add_user que repite el dominio no es una clave rechazada;
//   M4 (P16) un pedido anterior al encendido queda «en espera» con «Reintentar» y aviso;
//   S1 (P5)  hosting con dominio propio: revisión manual por omisión; correo público, sufijos y dominios propios nunca;
//   S2  el tipo de entrada de servicios sin capacidades propias de WordPress; S3 privacidad de WHOIS y premium;
//   B1 (P8), B2/B3 (P9), B4 (P11).
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function reset_all( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array(); $GLOBALS['http'] = array();
	update_post_meta( 14693, '_digitalisimo_webuzo_plan', 'digitalisimo_starter' );
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7 ) );
}
function provisioned( $domain, $customer = 7 ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => $domain ) ) ) );
	$o->customer = $customer; $o->update_status( 'processing' ); digi_run_queue();
	return (int) $o->get_meta( '_digitalisimo_service' );
}
function hosting_only( $domain, $customer = 7 ) {
	$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting', 1, array( 'Dominio' => $domain ) ) ) );
	$o->customer = $customer; $o->update_status( 'processing' ); digi_run_queue();
	return $o;
}
function sale( $on ) { $o = get_option( 'digitalisimo_hosting_options' ); $o['provision_enabled'] = $on ? 1 : 0; update_option( 'digitalisimo_hosting_options', $o ); }
function admin_mails() { return array_map( function ( $m ) { return $m['subject']; }, array_values( array_filter( $GLOBALS['mails'], function ( $m ) { return 'admin@tienda.test' === $m['to']; } ) ) ); }
function has_mail( $needle ) { foreach ( admin_mails() as $s ) if ( false !== strpos( $s, $needle ) ) return true; return false; }
function account( $domain ) { foreach ( $GLOBALS['webuzo']['users'] as $u ) if ( $domain === $u['domain'] ) return $u; return null; }
function post_screen( array $post ) { $_POST = $post; try { Digitalisimo_Hosting_Services_Page::handle(); } catch ( Exception $e ) {} $_POST = array(); }

// ── M2 (P1): venta apagada con una suspensión ya en cola ─────────────────────────────────────────────────────
reset_all();
$s = provisioned( 'apagar.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS );
sale( false ); $before = count( $GLOBALS['webuzo']['calls'] ); digi_run_queue();
check( $before === count( $GLOBALS['webuzo']['calls'] ) && '' === $GLOBALS['webuzo']['users']['apagar']['status'], 'M2 con la venta apagada, lo que quedó en cola no llama a Webuzo' );

// ── A2 (P2a): renovación pagada con la venta apagada; al reencenderla se ejecuta ─────────────────────────────
reset_all();
$s = provisioned( 'pagado.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending';
sale( false ); $r->update_status( 'processing' );
check( ! $GLOBALS['queue'], 'A2 pagada con la venta apagada: no se encola nada todavía' );
$GLOBALS['mails'] = array();
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'provision_enabled' => 1 ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
digi_run_queue();
check( (int) $P::svc( $s, 'expires' ) > $exp && 1 === digi_nc_count( 'namecheap.domains.renew' ) && has_mail( 'renovación #' ), 'A2 al reencender la venta, la renovación cobrada se ejecuta y se avisa' );
// P2b: la tarea de renovación se pierde: la revisión diaria la retoma.
reset_all();
$s = provisioned( 'muere.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending'; $r->update_status( 'processing' );
$GLOBALS['queue'] = array(); $GLOBALS['mails'] = array(); // la acción se perdió
ob_start(); Digitalisimo_Hosting_Services_Page::render(); $html = ob_get_clean();
preg_match( '#<tr data-service="' . $s . '">.*?</tr>#s', $html, $row );
check( false !== strpos( $row[0] ?? '', 'value="retry"' ) && false !== strpos( $row[0] ?? '', 'Reintentar la renovación' ), 'A2 la fila del servicio ofrece «Reintentar la renovación»' );
$P::daily( $exp - 5 * DAY_IN_SECONDS ); digi_run_queue();
check( (int) $P::svc( $s, 'expires' ) > $exp && has_mail( 'retomada' ), 'A2 la revisión diaria retoma la renovación cobrada cuya tarea se perdió, y avisa' );

// ── A3 (P3): la confirmación falla por red hasta agotarse: se limpia y se avisa; al pagar se reconcilia ───────
reset_all();
$s = provisioned( 'colgado.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS );
digi_run_queue( true, 1 ); // la orden de suspender: Webuzo la acepta y la aplica
$GLOBALS['webuzo']['timeout'] = 99; $GLOBALS['mails'] = array();
digi_run_queue();
$GLOBALS['webuzo']['timeout'] = 0;
check( ! $P::svc( $s, 'pending_toggle' ) && has_mail( 'sin confirmar' ), 'A3 confirmaciones agotadas por red: se limpia la orden pendiente y se avisa' );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending'; $r->update_status( 'processing' ); digi_run_queue();
check( '' === $GLOBALS['webuzo']['users']['colgado']['status'] && 'active' === $P::svc( $s, 'status' ), 'A3 al pagar se reconcilia con la lista real: estaba suspendida en Webuzo y se reactiva' );
// P12: pagó con la suspensión en camino y Webuzo nunca la aplicó: nada queda colgado.
reset_all();
$s = provisioned( 'camino.com' ); $exp = (int) $P::svc( $s, 'expires' );
$GLOBALS['webuzo']['mode'] = 'ignora';
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 1 );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending'; $r->update_status( 'processing' ); digi_run_queue();
$GLOBALS['webuzo']['mode'] = '';
check( ! $P::svc( $s, 'pending_toggle' ), 'A3 (P12) al pagar se cancela la orden de suspender en camino' );
$exp2 = (int) $P::svc( $s, 'expires' );
$P::daily( $exp2 - 29 * DAY_IN_SECONDS ); $P::daily( $exp2 + 30 * DAY_IN_SECONDS ); digi_run_queue();
check( 'suspended' === $GLOBALS['webuzo']['users']['camino']['status'], 'A3 (P12) al año siguiente, vencido sin pagar, sí se suspende (nada lo frena)' );
// pending_toggle caduca por su marca t.
$P::svc_set( $s, array( 'status' => 'active', 'pending_toggle' => array( 'op' => 'suspend', 'sends' => 1, 'checks' => 0, 't' => time() - 2 * DAY_IN_SECONDS ) ) );
$P::check_service( $s, time(), 7 );
check( ! $P::svc( $s, 'pending_toggle' ), 'A3 una orden sin confirmar de hace más de un día caduca' );

// ── M1 (P4): error definitivo de registro y el dominio entra después a la cuenta por otra vía ─────────────────
reset_all();
$GLOBALS['fail_create'] = true;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) { $b = (array) ( $args['body'] ?? array() ); if ( ! empty( $GLOBALS['fail_create'] ) && 'namecheap.domains.create' === ( $b['Command'] ?? '' ) ) return digi_xml( '<Error Number="2030280">Domain is not available</Error>', false ); return $pre; }, 5, 3 );
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'tomado.com' ) ) ) );
$o->customer = 9; $o->update_status( 'processing' ); digi_run_queue();
$s = (int) $o->get_meta( '_digitalisimo_service' );
check( empty( $P::state( $o )['domain']['create_sent'] ), 'M1 tras un error definitivo («Domain is not available») no queda la marca de intento' );
$GLOBALS['fail_create'] = false;
$GLOBALS['nc']['domains']['tomado.com'] = strtotime( '+200 days' ); $GLOBALS['nc']['created']['tomado.com'] = time() - 400 * DAY_IN_SECONDS;
$GLOBALS['nc']['dns']['tomado.com'] = 'ns1.otro-proveedor.test,ns2.otro-proveedor.test';
$P::retry( $s ); digi_run_queue();
check( 'ns1.otro-proveedor.test,ns2.otro-proveedor.test' === $GLOBALS['nc']['dns']['tomado.com'] && null === account( 'tomado.com' ) && 'on-hold' === $o->get_status(), 'M1 «Reintentar» no adopta el dominio ajeno: ni nameservers ni cuenta' );
// Con respuesta perdida (intento sin respuesta) y creado hoy, sí se adopta.
reset_all();
$GLOBALS['nc']['timeout']['namecheap.domains.create'] = 1;
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'perdido.com' ) ) ) );
$o->update_status( 'processing' ); digi_run_queue( true, 1 );
$GLOBALS['nc']['domains']['perdido.com'] = strtotime( '+1 year' ); $GLOBALS['nc']['created']['perdido.com'] = time();
digi_run_queue();
check( 'completed' === $o->get_status() && 1 === digi_nc_count( 'namecheap.domains.create' ), 'M1 un registro sin respuesta, creado ese día, sí se adopta (no se registra dos veces)' );

// ── M3 (P7): add_user dice «El dominio <dominio-del-pedido> ya existe» con palabras de la lista ────────────────
reset_all();
$GLOBALS['echo_err'] = true;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) { $b = (array) ( $args['body'] ?? array() ); if ( ! empty( $GLOBALS['echo_err'] ) && false !== strpos( $url, 'act=add_user' ) ) return digi_json( array( 'error' => array( 'domain_exists' => 'El dominio ' . $b['domain'] . ' ya existe en el servidor' ) ) ); return $pre; }, 5, 3 );
$o = hosting_only( 'desbloqueados.com', 40 );
$GLOBALS['echo_err'] = false;
check( ! get_option( Digitalisimo_Hosting_Webuzo::BLOCK ) && 'on-hold' === $o->get_status(), 'M3 un error de add_user que repite el dominio («desbloqueados») no detiene Webuzo' );
$o2 = hosting_only( 'inocente.com', 41 );
check( 'completed' === $o2->get_status(), 'M3 el siguiente cliente se aprovisiona normal' );

// ── M4 (P16): pedido creado antes de encender la venta y pagado después ───────────────────────────────────────
reset_all( array( 'provision_since' => time() - 3600 ) );
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'antes.com' ) ) ) );
$o->created = time() - 2 * DAY_IN_SECONDS; $o->method = 'bacs';
$o->update_status( 'completed' ); digi_run_queue();
$s = (int) $o->get_meta( '_digitalisimo_service' );
check( $s && 'on-hold' === $P::svc( $s, 'status' ) && ! isset( $GLOBALS['webuzo']['users']['antes'] ) && has_mail( 'anterior al encendido' ), 'M4 queda en Servicios «en espera», sin aprovisionarse solo, y se avisa' );
ob_start(); Digitalisimo_Hosting_Services_Page::render(); $html = ob_get_clean();
check( false !== strpos( $html, 'antes.com' ) && false !== strpos( $html, 'value="retry"' ), 'M4 con su botón «Reintentar»' );
$P::retry( $s ); digi_run_queue();
check( isset( $GLOBALS['webuzo']['users']['antes'] ) && 'completed' === $o->get_status(), 'M4 «Reintentar» lo aprovisiona' );

// ── S1 (P5): hosting con dominio propio ───────────────────────────────────────────────────────────────────────
foreach ( array( 'gmail.com', 'yahoo.com.mx', 'com.mx', 'digitalisimo.website' ) as $d ) {
	reset_all();
	$o = hosting_only( $d, 31 );
	check( null === account( $d ) && 'on-hold' === $o->get_status(), "S1 {$d} nunca se hospeda" );
}
reset_all( array( 'own_domain_auto' => 0 ) );
$o = hosting_only( 'banco-ejemplo.com.mx', 31 );
$s = (int) $o->get_meta( '_digitalisimo_service' );
check( null === account( 'banco-ejemplo.com.mx' ) && 'on-hold' === $o->get_status() && $P::svc( $s, 'needs_review' ) && has_mail( 'en espera' ), 'S1 por omisión, hosting con dominio propio va a revisión manual (en espera y aviso)' );
ob_start(); Digitalisimo_Hosting_Services_Page::render(); $html = ob_get_clean();
check( false !== strpos( $html, 'value="approve"' ), 'S1 la pantalla ofrece «Aprobar dominio»' );
post_screen( array( 'service' => $s, 'op' => 'approve', '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( 'approve', $s ) ) );
digi_run_queue();
check( null !== account( 'banco-ejemplo.com.mx' ) && 'completed' === $o->get_status(), 'S1 aprobado, se crea la cuenta' );
reset_all( array( 'own_domain_auto' => 1 ) );
$o = hosting_only( 'inmediato.com.mx', 31 );
check( null !== account( 'inmediato.com.mx' ), 'S1 con el ajuste «de inmediato», se aprovisiona sin revisión' );

// ── A1 (P6): el usuario de Webuzo de un servicio viejo es hoy de otro cliente ─────────────────────────────────
reset_all();
$a = provisioned( 'mitienda.com', 7 ); $exp = (int) $P::svc( $a, 'expires' );
unset( $GLOBALS['webuzo']['users']['mitienda'] ); // A se fue y el administrador borró su cuenta en Webuzo
$ob = hosting_only( 'mitienda.mx', 8 ); $b = (int) $ob->get_meta( '_digitalisimo_service' );
$P::svc_set( $b, array( 'expires' => $exp + 300 * DAY_IN_SECONDS ) );
check( 'mitienda' === $P::svc( $b, 'webuzo_user' ), 'A1 B heredó el usuario «mitienda»' );
$GLOBALS['mails'] = array();
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue();
check( 0 === digi_webuzo_count( 'users', 'suspend', 'mitienda' ) && '' === $GLOBALS['webuzo']['users']['mitienda']['status'], 'A1 el servicio viejo de A no suspende la cuenta que hoy es de B' );
check( has_mail( 'cuenta de Webuzo de otro dominio' ), 'A1 se avisa al administrador' );
post_screen( array( 'service' => $a, 'op' => 'cancel', '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( 'cancel', $a ) ) );
check( 'cancelled' === $P::svc( $a, 'status' ), 'A1 «Dar de baja» deja el servicio dado de baja' );
$GLOBALS['queue'] = array(); $GLOBALS['webuzo']['calls'] = array();
$P::daily( $exp + 40 * DAY_IN_SECONDS ); digi_run_queue();
check( ! $GLOBALS['webuzo']['calls'] && ! $GLOBALS['queue'], 'A1 dado de baja: ni renovaciones ni suspensiones' );

// ── B4 (P11): «Suspender» a mano sobre uno ya suspendido por falta de pago ───────────────────────────────────
reset_all();
$s = provisioned( 'vieja.com' ); $exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue();
post_screen( array( 'service' => $s, 'op' => 'suspend', '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( 'suspend', $s ) ) ); digi_run_queue();
check( 'auto' === $P::svc( $s, 'suspend_reason' ), 'B4 el motivo de falta de pago se conserva' );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->status = 'pending'; $r->update_status( 'processing' ); digi_run_queue();
check( '' === $GLOBALS['webuzo']['users']['vieja']['status'] && 'active' === $P::svc( $s, 'status' ), 'B4 al pagar se reactiva' );

// ── B1 (P8): administrador de un sitio de la red sin manage_network_options ──────────────────────────────────
reset_all();
$GLOBALS['multisite'] = true;
$GLOBALS['site_options']['digitalisimo_hosting_options'] = array( 'api_user' => 'nc-api', 'username' => 'nc-user', 'api_key' => 'CLAVE-NAMECHEAP', 'client_ip' => '203.0.113.5', 'webuzo_url' => 'https://webuzo.test:2005', 'webuzo_api_user' => 'root', 'webuzo_api_key' => 'CLAVE-RED-MALA', 'nameservers' => 'ns1.digitalisimo.website, ns2.digitalisimo.website' );
$GLOBALS['site_options']['digitalisimo_hosting_ecommerce_network_migrated'] = 1;
$opt = get_option( 'digitalisimo_hosting_options' ); $opt['inherit_network'] = 1; update_option( 'digitalisimo_hosting_options', $opt );
$W::from_settings()->users();
$GLOBALS['caps'] = array( 'manage_options', 'manage_woocommerce' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'inherit_network' => 1, 'webuzo_unblock' => 1 ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
check( (bool) get_site_option( $W::BLOCK ), 'B1 el administrador de un sitio no levanta con la misma clave la parada de la red' );
$GLOBALS['multisite'] = false; unset( $GLOBALS['caps'] );

// ── B3 (P9): URLs que no se aceptan ──────────────────────────────────────────────────────────────────────────
reset_all();
foreach ( array( 'https://127.0.0.1:2005', 'https://localhost:6379', 'https://169.254.169.254', 'https://[::1]:2005', 'https://10.0.0.5:8443/', 'https://192.168.1.25:2005', 'http://webuzo.test:2005', 'https://a.test:2005/x', 'https://u:p@a.test:2005', 'https://a.test:2005?x=1' ) as $u ) {
	$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_url' => $u ) );
	ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
	check( '' === get_option( 'digitalisimo_hosting_options' )['webuzo_url'], "B3 la URL «{$u}» no se acepta" );
}
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_url' => 'https://199.85.208.102:2005' ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
check( 'https://199.85.208.102:2005' === get_option( 'digitalisimo_hosting_options' )['webuzo_url'], 'B3 una IP pública con https sí' );
$o = get_option( 'digitalisimo_hosting_options' ); $o['webuzo_url'] = 'https://127.0.0.1:2005'; update_option( 'digitalisimo_hosting_options', $o );
check( ! $W::from_settings()->configured(), 'B3 una URL de loopback guardada antes tampoco se usa' );

// ── S2: el tipo de entrada de servicios ──────────────────────────────────────────────────────────────────────
$GLOBALS['registered_cpt'] = null;
$P::register();
check( 'manage_woocommerce' === ( $GLOBALS['registered_cpt']['capabilities']['edit_posts'] ?? '' ) && false === $GLOBALS['registered_cpt']['show_in_rest'] && false === $GLOBALS['registered_cpt']['map_meta_cap'], 'S2 capacidades del tipo de servicio = manage_woocommerce, sin REST' );

// ── S3: privacidad de WHOIS y dominios premium ───────────────────────────────────────────────────────────────
reset_all();
provisioned( 'privado.com' );
$c = array_values( array_filter( $GLOBALS['nc']['calls'], function ( $c ) { return 'namecheap.domains.create' === $c['cmd']; } ) )[0]['fields'];
check( 'yes' === ( $c['AddFreeWhoisguard'] ?? '' ) && 'yes' === ( $c['WGEnabled'] ?? '' ), 'S3 .com se registra con privacidad de WHOIS' );
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'privado.mx' ) ) ) ); $o->update_status( 'processing' ); digi_run_queue();
$c = array_values( array_filter( $GLOBALS['nc']['calls'], function ( $c ) { return 'namecheap.domains.create' === $c['cmd'] && 'privado.mx' === $c['fields']['DomainName']; } ) )[0]['fields'];
check( ! isset( $c['AddFreeWhoisguard'] ), 'S3 .mx (sin privacidad en Namecheap) no la pide' );
reset_all(); $GLOBALS['nc']['premium'] = array( 'caro.com' );
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'caro.com' ) ) ) ); $o->update_status( 'processing' ); digi_run_queue();
check( 0 === digi_nc_count( 'namecheap.domains.create' ) && 'on-hold' === $o->get_status() && false !== strpos( implode( "\n", $o->notes ), 'premium' ), 'S3 un dominio premium no se registra solo' );

echo "Hosting, revisión final: {$GLOBALS['checks']} comprobaciones correctas.\n";
