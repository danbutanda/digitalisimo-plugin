<?php
// Tercera revisión adversarial de la venta automática de DIGITALÍSIMO Hosting (2026-11-24), sin WordPress y con Webuzo
// y Namecheap simulados (tests/fixtures/hosting-wp.php). Cada caso del sondeo del revisor (S1–S10) y los puntos 10–12
// fallan con el código anterior al arreglo (commit 72c8cb81):
//   S1  un dominio pagado y aún no entregado (en espera por saldo) reserva el dominio;
//   S2  el dueño se revisa otra vez justo antes de crear la cuenta (sin valor guardado);
//   S3/S8  subdominios (www., mail.) rechazados; variantes de escritura no saltan la revisión;
//   S4  .com.mx y otros sufijos de segundo nivel se aceptan para hosting con dominio propio;
//   S5  una renovación sin cobro confirmado (contra entrega en «procesando») no frena la suspensión y se avisa;
//   S6  pagada la renovación, el hosting se reactiva y se extiende aunque el dominio quede pendiente;
//   S7  las claves rechazadas se recuerdan (A, B, A no gasta otro intento con A);
//   S9  un error de validación de add_user en español no detiene todo;
//   S10 la prórroga de una reactivación manual no regala gracia doble;
//   10  la guarda de costo usa la moneda del pedido; 11 pasarelas sin comprobante; 12 reembolso tras entregar;
//   y la lista de cuentas de Webuzo paginada (formato real: num_res, reslen, cur_page).
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function digi_notes( $order ) { return implode( "\n", $order->notes ); }
function reset_all( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array();
	update_post_meta( 14693, '_digitalisimo_webuzo_plan', 'digitalisimo_starter' ); update_post_meta( 14696, '_digitalisimo_webuzo_plan', 'digitalisimo_pro' );
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7 ) );
}
function buy( $domain, $hosting = 14693, $dp = 20, $customer = 7, $method = 'stripe', $billing = array() ) {
	$items = array();
	if ( $hosting ) $items[] = new Digi_Test_Item( $hosting, 'Web Hosting', 1, $dp ? array() : array( 'Dominio' => $domain ) );
	if ( $dp ) $items[] = new Digi_Test_Item( $dp, 'Dominio', 1, array( 'Dominio' => $domain ) );
	$o = digi_order( $items, $billing ); $o->customer = $customer; $o->method = $method;
	return $o;
}
function pay( $o, $st = 'processing' ) { $o->update_status( $st ); digi_run_queue(); return (int) $o->get_meta( '_digitalisimo_service' ); }
function acct( $d ) { foreach ( $GLOBALS['webuzo']['users'] as $u ) if ( $d === $u['domain'] ) return $u['user']; return null; }
function save_key( $k, $extra = array() ) { $_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_api_key' => $k ) + $extra ); ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array(); }

// S1: Ana paga dominio + plan, sin saldo en Namecheap (en espera); Mallory compra sólo hosting con ese dominio.
reset_all(); $GLOBALS['nc']['balance'] = 0;
$ana = buy( 'foo.com', 14693, 20, 7 ); $sa = pay( $ana );
check( 'on-hold' === $ana->get_status(), 'S1 Ana queda en espera por saldo' );
$mal = buy( 'foo.com', 14693, 0, 99 ); pay( $mal );
check( null === acct( 'foo.com' ) && 'on-hold' === $mal->get_status() && false !== strpos( digi_notes( $mal ), 'ya tiene un servicio de otro cliente' ), 'S1 el pedido pagado de Ana (aún no entregado) reserva el dominio: Mallory no obtiene cuenta' );
$GLOBALS['nc']['balance'] = 50; $P::retry( $sa ); digi_run_queue();
check( 'completed' === $ana->get_status() && null !== acct( 'foo.com' ) && 'foo' === acct( 'foo.com' ), 'S1 Ana, con saldo y «Reintentar», recibe su dominio y su cuenta' );

// S2: Mallory pide hosting bar.com con Webuzo detenido; Ana compra sólo el dominio; al levantar la parada, Mallory no entra.
reset_all( array( 'webuzo_api_key' => 'CLAVE-MALA' ) );
$mal = buy( 'bar.com', 14693, 0, 99 ); pay( $mal );
$ana = buy( 'bar.com', 0, 20, 7 ); pay( $ana );
check( 'completed' === $ana->get_status() && isset( $GLOBALS['nc']['domains']['bar.com'] ), 'S2 Ana registra bar.com mientras Mallory espera la clave' );
save_key( 'CLAVE-WEBUZO-OK' ); digi_run_queue();
check( null === acct( 'bar.com' ) && 'on-hold' === $mal->get_status(), 'S2 al levantar la parada se revisa otra vez el dueño: Mallory no obtiene cuenta de bar.com' );

// S3/S8: subdominios y variantes de escritura.
reset_all();
$a = buy( 'cliente.lat', 14693, 0, 7 ); pay( $a );
check( 'cliente' === acct( 'cliente.lat' ), 'S3 Ana hospeda cliente.lat' );
foreach ( array( 'www.cliente.lat', 'mail.cliente.lat' ) as $v ) { $m = buy( $v, 14693, 0, 99 ); pay( $m ); check( null === acct( $v ) && 'on-hold' === $m->get_status(), "S3 {$v} (subdominio) no se hospeda" ); }
reset_all();
pay( buy( 'marca.com', 0, 20, 7 ) );
foreach ( array( 'MARCA.com', ' marca.com ', 'marca.com.', 'www.marca.com' ) as $v ) { $m = buy( $v, 14693, 0, 99 ); pay( $m ); check( null === acct( 'marca.com' ) && null === acct( strtolower( trim( $v ) ) ) && 'on-hold' === $m->get_status(), "S8 «{$v}» no da cuenta sobre marca.com" ); }

// S4: hosting con dominio propio .com.mx (y otros de segundo nivel).
reset_all();
foreach ( array( 'negocio.com.mx', 'tienda.org.mx' ) as $v ) { $c = buy( $v, 14693, 0, 7 ); pay( $c ); check( 'completed' === $c->get_status() && null !== acct( $v ), "S4 {$v} se acepta (sufijo de segundo nivel conocido)" ); }
check( null === $P::split( 'www.negocio.com.mx' ) && array( 'negocio', 'com.mx', '.com.mx' ) === $P::split( 'negocio.com.mx' ), 'S4 www.negocio.com.mx es subdominio; negocio.com.mx se separa bien' );

// S5: renovación pagada contra entrega (procesando, sin cobrar).
reset_all();
$o = buy( 'cod-renueva.com' ); $s = pay( $o );
$exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); digi_run_queue();
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->method = 'cod'; $r->update_status( 'processing' ); digi_run_queue();
$GLOBALS['mails'] = array();
$P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue();
check( 'suspended' === $P::svc( $s, 'status' ) && 'suspended' === $GLOBALS['webuzo']['users']['codrenueva']['status'], 'S5 una renovación contra entrega sin cobrar no frena la suspensión por vencimiento' );
check( 1 === count( array_filter( digi_mails_to( 'admin@tienda.test' ), function ( $m ) { return false !== strpos( $m['subject'], 'sin cobro confirmado' ); } ) ) && false !== strpos( digi_notes( $r ), 'sin cobro confirmado' ), 'S5 el administrador ve la renovación pendiente de confirmar' );
$r->update_status( 'completed' ); digi_run_queue();
check( 'active' === $P::svc( $s, 'status' ) && (int) $P::svc( $s, 'expires' ) > $exp, 'S5 al marcarla «completado» se renueva y reactiva' );

// S6: suspendido; paga la renovación en línea; nos falta saldo para el dominio.
reset_all();
$o = buy( 'sinsaldo.com' ); $s = pay( $o );
$exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue();
check( 'suspended' === $P::svc( $s, 'status' ), 'S6 suspendido tras la gracia' );
$GLOBALS['nc']['balance'] = 0; $GLOBALS['mails'] = array();
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->update_status( 'processing' ); digi_run_queue();
check( 'active' === $P::svc( $s, 'status' ) && '' === $GLOBALS['webuzo']['users']['sinsaldo']['status'] && strtotime( '+1 year', $exp ) === (int) $P::svc( $s, 'expires' ), 'S6 pagó: se reactiva y suma el año de hosting aunque falte saldo para el dominio' );
check( 'on-hold' === $r->get_status() && false !== strpos( digi_notes( $r ), 'la renovación del dominio queda pendiente' ) && digi_mails_to( 'admin@tienda.test' ), 'S6 sólo el dominio queda en espera, con nota y aviso' );
$GLOBALS['nc']['balance'] = 50; $P::retry( $s ); digi_run_queue();
check( 1 === digi_nc_count( 'namecheap.domains.renew' ) && strtotime( '+1 year', $exp ) === (int) $P::svc( $s, 'expires' ) && 'completed' === $r->get_status(), 'S6 con saldo, «Reintentar» renueva el dominio sin sumar otro año' );

// S7: clave A rechazada → B → otra vez A.
reset_all( array( 'webuzo_api_key' => 'CLAVE-A-MALA' ) );
pay( buy( 'a1.com' ) );
$na = count( array_keys( $GLOBALS['webuzo']['keys_seen'], 'CLAVE-A-MALA', true ) );
save_key( 'CLAVE-B-MALA' ); digi_run_queue();
save_key( 'CLAVE-A-MALA' ); digi_run_queue();
pay( buy( 'a2.com' ) );
check( 1 === $na && 1 === count( array_keys( $GLOBALS['webuzo']['keys_seen'], 'CLAVE-A-MALA', true ) ), 'S7 volver a poner una clave ya rechazada no gasta otro intento' );

// S9: error de validación de add_user en español (no de clave).
reset_all();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( empty( $GLOBALS['s9'] ) || false === strpos( $url, 'act=add_user' ) ) return $pre;
	$GLOBALS['webuzo']['calls'][] = array( 'act' => 'add_user', 'fields' => array(), 'url' => $url );
	return digi_json( array( 'error' => array( 'La contraseña debe tener al menos 20 caracteres' ) ) );
}, 5, 3 );
$GLOBALS['s9'] = 1; $o = buy( 's9.com' ); pay( $o ); $GLOBALS['s9'] = 0;
check( ! $W::any_block() && 'on-hold' === $o->get_status() && false !== strpos( digi_notes( $o ), 'al menos 20 caracteres' ), 'S9 un error de validación al crear no detiene todo: sólo ese pedido queda en espera' );
foreach ( array( 'Usuario o contraseña incorrectos', 'Le quedan 2 intento(s)', 'Incorrect Username or Password' ) as $msg ) check( $W::is_auth_message( $msg ), "S9 «{$msg}» sigue siendo error de clave" );
check( ! $W::is_auth_message( 'La contraseña debe tener al menos 20 caracteres' ) && ! $W::is_auth_message( 'The password must be at least 20 characters' ), 'S9 la política de contraseñas no es error de clave' );

// S10: reactivar a mano una suspensión manual a mitad del año: la prórroga no pasa del vencimiento + gracia.
reset_all();
$o = buy( 'abuso.com' ); $s = pay( $o ); $exp = (int) $P::svc( $s, 'expires' );
$P::svc_set( $s, array( 'suspend_reason' => 'manual' ) ); $P::run_suspend( $s ); $P::run_unsuspend( $s );
$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue();
check( (int) $P::svc( $s, 'reprieve_until' ) < $exp && 'suspended' === $P::svc( $s, 'status' ), 'S10 sin gracia doble: a los 8 días del vencimiento sin pago se suspende' );

// 10: la guarda de costo usa la moneda del pedido.
reset_all();
$o = buy( 'dolares.com', 0, 20 ); $o->currency = 'USD'; pay( $o );
check( 0 === digi_nc_count( 'namecheap.domains.create' ) && 'on-hold' === $o->get_status() && false !== strpos( digi_notes( $o ), 'El pedido está en USD' ), '10 un pedido en USD no se registra a ciegas (la guarda sólo compara en MXN)' );

// 11: una pasarela en línea que no deja número de transacción (o una fuera de línea de terceros) exige «completado».
reset_all();
$o = buy( 'tercero.com', 14693, 20, 7, 'pasarela_rara' ); $o->txn = ''; $o->update_status( 'processing' );
check( ! $GLOBALS['queue'] && false !== strpos( digi_notes( $o ), 'sin número de transacción' ), '11 sin número de transacción no se aprovisiona en «procesando»' );
$o->update_status( 'completed' ); digi_run_queue();
check( null !== acct( 'tercero.com' ), '11 marcado «completado» por el administrador, sí' );
add_filter( 'digitalisimo_hosting_trusted_methods', function ( $m ) { $m[] = 'pasarela_confiable'; return $m; } );
$o = buy( 'confiable.com', 14693, 20, 7, 'pasarela_confiable' ); $o->txn = ''; pay( $o );
check( null !== acct( 'confiable.com' ), '11 una pasarela de confianza (filtro) aprovisiona aunque no deje número de transacción' );

// 12: reembolso después de entregar.
reset_all();
$o = buy( 'reembolsado.com' ); pay( $o ); $GLOBALS['mails'] = array();
$o->update_status( 'refunded' );
check( false !== strpos( digi_notes( $o ), 'después de entregar reembolsado.com' ) && 1 === count( digi_mails_to( 'admin@tienda.test' ) ), '12 un reembolso tras entregar deja nota y avisa al administrador' );
$x = buy( 'nunca.com' ); $x->update_status( 'cancelled' );
check( ! digi_mails_to( 'admin@tienda.test' ) || 1 === count( digi_mails_to( 'admin@tienda.test' ) ), '12 cancelar un pedido no entregado no avisa' );

// Lista de cuentas paginada (formato real: num_res, reslen, cur_page): no se crea un usuario que está en la página 2.
reset_all();
$GLOBALS['webuzo']['users'] = array();
for ( $i = 1; $i <= 55; $i++ ) $GLOBALS['webuzo']['users'][ 'cuenta' . $i ] = array( 'user' => 'cuenta' . $i, 'domain' => 'sitio' . $i . '.com', 'status' => '' );
$GLOBALS['webuzo']['users']['pagina'] = array( 'user' => 'pagina', 'domain' => 'otro-de-pagina.com', 'status' => '' );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre || 0 !== strpos( $url, 'https://webuzo.test:2005/' ) || false === strpos( $url, 'act=users' ) || isset( $args['body']['suspend'] ) || isset( $args['body']['unsuspend'] ) ) return $pre;
	$GLOBALS['webuzo']['calls'][] = array( 'act' => 'users', 'fields' => array_diff_key( $args['body'], array( 'apikey' => 1 ) ), 'url' => $url );
	// Como el servidor real: `page` sólo cuenta en la URL (desde 1); en el cuerpo del POST se ignora; cur_page = desplazamiento.
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	$all = $GLOBALS['webuzo']['users']; $offset = max( 0, ( (int) ( $q['page'] ?? 1 ) - 1 ) * 50 );
	return digi_json( array( 'title' => 'Webuzo - Panel de administrador', 'users' => array_slice( $all, $offset, 50, true ), 'resellers' => array(), 'num_res' => count( $all ), 'reslen' => 50, 'cur_page' => $offset ) );
}, 5, 3 );
$o = buy( 'pagina.com', 14693, 0 ); pay( $o );
check( 'pagina2' === acct( 'pagina.com' ) && 2 === digi_webuzo_count( 'users' ) && false !== strpos( $GLOBALS['webuzo']['calls'][1]['url'], 'act=users&page=2' ), 'La lista paginada se lee completa (page=2 en la URL, como el servidor real): el usuario «pagina» ya existe y se usa «pagina2»' );

// Formas reales de respuesta (prueba real autorizada del 2026-11-24): errores como objeto con código y mensajes en español.
foreach ( array( array( 'pass_mismatch' => 'Las contraseñas ingresadas no coinciden.' ), array( 'inv_pri_domain' => 'Dominio inválido' ), array( 'no_user' => 'No existe tal usuario <b>zzdgtest</b>' ) ) as $err ) {
	$c = $W::classify( digi_json( array( 'title' => 'Webuzo - Panel de administrador', 'error' => $err ) ) );
	check( 'api' === $c['kind'] && false === strpos( $c['message'], '<b>' ), 'Respuesta real: «' . key( $err ) . '» es error de la operación (no de clave), sin HTML' );
}
$c = $W::classify( digi_json( array( 'title' => 'Webuzo', 'done' => array( 'msg' => 'Usuario suspendido con éxito' ), 'users' => array(), 'num_res' => 40, 'reslen' => 50, 'cur_page' => 0 ) ) );
check( 'ok' === $c['kind'] && 'msg: Usuario suspendido con éxito' === $c['message'], 'Respuesta real: «done» en español cuenta como hecho' );

echo "Hosting, tercera revisión: {$GLOBALS['checks']} comprobaciones correctas.\n";
