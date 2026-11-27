<?php
// Segunda revisión crítica de hosting/dynadot (2026-11-27, sobre c09ef045): cada punto con su comprobación, con Webuzo,
// Namecheap y Dynadot simulados. Corrida contra c09ef045, cada punto falla.
//   N1. Un servicio de antes de esta versión (sin `domain_expires`) no pierde la renovación del dominio: dos renovaciones
//       anuales seguidas (pago del cliente y pago manual) y la migración idempotente que rellena `domain_expires`.
//   N2. Varios dominios por pedido: los extras tienen salida completa («Reintentar», «Aprobar dominio», revisión diaria,
//       needs_review / owner_conflict, aviso al agotar reintentos, el pedido no se completa con extras pendientes, el
//       correo de accesos lista cada dominio con su estado) y una sola tarea y un solo aviso por pasada.
//   N3. Dos transferencias en un pedido conservan los dos códigos EPP; al llegar una se borra sólo el suyo.
//   N4. Hosting mensual + dominio en el mes 12: el pedido sólo del dominio sin pagar se cancela y los dos se cobran juntos;
//       el aviso dice que incluye el dominio.
//   N5. «Ya lo renové yo» se comprueba en el registrador (sólo lectura) y se guarda su vencimiento.
//   R1. Pago manual de sólo el dominio: una de las dos opciones; sin ninguna, error y nada registrado (y un dominio
//       detenido por saldo no se renueva solo al completar el pedido).
//   R3. Dynadot: la lista de precios guardada se pide otra vez antes de declarar premium por precio.
//   Menores: pago manual de una transferencia que no llegó = error claro.
// Uso: php tests/hosting-second-review.php
require __DIR__ . '/fixtures/hosting-wp.php';
require __DIR__ . '/fixtures/hosting-dynadot.php';
$P = 'Digitalisimo_Hosting_Provisioning'; $SP = 'Digitalisimo_Hosting_Services_Page';
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
function home_url( $p = '' ) { return 'https://tienda.test' . $p; }
function ymd( $t ) { return gmdate( 'Y-m-d', (int) $t ); }
function same_day( $a, $b ) { return abs( (int) $a - (int) $b ) < DAY_IN_SECONDS; }
function line_ids( $order ) { return array_map( function ( $i ) { return (int) ( $i->get_variation_id() ?: $i->get_product_id() ); }, $order->get_items() ); }
function store( array $extra = array() ) {
	digi_servers_reset(); dd_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['transients'] = array(); $GLOBALS['real_transients'] = 0;
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7, 'dynadot_api_key' => DD_KEY ) );
	$GLOBALS['nc']['balance'] = 5000;
	Digitalisimo_Hosting_Products::flush();
}
function paid( array $items, $email ) { $o = digi_order( $items, array( 'email' => $email ) ); $o->update_status( 'processing' ); digi_run_queue( true, 200 ); return $o; }
function admin_mails( $needle = '' ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $needle ) { return 'admin@tienda.test' === $m['to'] && ( '' === $needle || false !== strpos( $m['subject'], $needle ) ); } ) ); }
function client_mails( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function svc_of( $o, $domain ) { foreach ( Digitalisimo_Hosting_Provisioning::services() as $id ) if ( (int) Digitalisimo_Hosting_Provisioning::svc( $id, 'order' ) === $o->get_id() && $domain === Digitalisimo_Hosting_Provisioning::svc( $id, 'domain' ) ) return $id; return 0; }
function screen_ops( $id ) { $_GET = array(); ob_start(); Digitalisimo_Hosting_Services_Page::render(); $h = ob_get_clean(); $row = preg_match( '#<tr data-service="' . (int) $id . '">(.*?)</tr>#s', $h, $m ) ? $m[1] : ''; return array_values( array_unique( preg_match_all( '#name="op" value="([a-z_]+)"#', $row, $mm ) ? $mm[1] : array() ) ); }
function post_op( $op, $id, array $extra = array() ) { $_POST = $extra + array( 'service' => $id, 'op' => $op, '_wpnonce' => 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( $op, $id ) ); try { Digitalisimo_Hosting_Services_Page::handle(); } catch ( Digi_Test_Redirect $e ) {} $_POST = array(); return (string) ( $GLOBALS['redirect'] ?? '' ); }
/** Falla la llamada $cmd de Namecheap para esos dominios (sin respuesta, como un corte de red). */
function nc_down_for( array $domains, $cmd = 'namecheap.domains.create' ) { $GLOBALS['nc_down'] = array( $cmd, $domains ); }
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre || empty( $GLOBALS['nc_down'] ) || 0 !== strpos( $url, 'https://api.namecheap.com/' ) ) return $pre;
	$b = (array) ( $args['body'] ?? array() );
	if ( $GLOBALS['nc_down'][0] === ( $b['Command'] ?? '' ) && in_array( (string) ( $b['DomainName'] ?? '' ), $GLOBALS['nc_down'][1], true ) ) { $GLOBALS['nc']['calls'][] = array( 'cmd' => $b['Command'], 'fields' => $b, 'key' => '' ); return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ); }
	return $pre;
}, 1, 3 );

/** Un servicio como los que ya existen en producción: hosting anual + dominio, sin las metas nuevas. */
function legacy( $domain ) {
	$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => $domain ) ) ), 'ana@' . $domain );
	$s = (int) $o->get_meta( '_digitalisimo_service' );
	foreach ( array( 'domain_expires', 'period', 'registrar' ) as $k ) delete_post_meta( $s, '_dhs_' . $k );
	return $s;
}

// ── N1. Servicio de antes de esta versión: dos renovaciones anuales ───────────────────────────────────────────
store();
$s = legacy( 'viejo.com' );
check( '' === $P::svc( $s, 'domain_expires' ) && $P::domain_due( $s ) === (int) $P::svc( $s, 'expires' ), 'N1 el servicio de prueba es como los de antes: sin domain_expires' );
foreach ( array( 1, 2 ) as $year ) {
	$exp = (int) $P::svc( $s, 'expires' ); $reg = $GLOBALS['nc']['domains']['viejo.com']; $n = digi_nc_count( 'namecheap.domains.renew' );
	$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp - 6 * DAY_IN_SECONDS );
	$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
	check( $r && array( 14693, 20 ) === line_ids( $r ) && ! (int) $P::svc( $s, 'domain_renewal_order' ), "N1 año $year: el pedido de renovación del servicio viejo lleva hosting y dominio · " . json_encode( $r ? line_ids( $r ) : null ) );
	$r->update_status( 'processing' ); digi_run_queue( true, 200 );
	$now_reg = $GLOBALS['nc']['domains']['viejo.com'];
	check( $n + 1 === digi_nc_count( 'namecheap.domains.renew' ) && ymd( $now_reg ) === ymd( strtotime( '+1 year', $reg ) ) && same_day( $P::domain_due( $s ), $now_reg ) && same_day( $P::svc( $s, 'expires' ), $now_reg ), "N1 año $year: el dominio se renueva una vez y el servicio anota lo mismo que el registrador · servicio " . ymd( $P::domain_due( $s ) ) . ' / registrador ' . ymd( $now_reg ) . ' / hosting ' . ymd( $P::svc( $s, 'expires' ) ) );
}
// Pago manual con «renovar el dominio» sobre un servicio viejo, y el año siguiente por el cliente.
$s = legacy( 'viejomanual.com' );
$r = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'renew_domain' => true, 'key' => 'legacy' ) ); digi_run_queue( true, 200 );
$reg = $GLOBALS['nc']['domains']['viejomanual.com'];
check( 'renewed' === $r['domain_renewal'] && same_day( $P::domain_due( $s ), $reg ) && same_day( $P::svc( $s, 'expires' ), $reg ), 'N1 pago manual en un servicio viejo: el dominio queda anotado con el vencimiento del registrador · ' . ymd( $P::domain_due( $s ) ) . ' / ' . ymd( $reg ) );
$exp = (int) $P::svc( $s, 'expires' ); $P::daily( $exp - 29 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
check( $r && array( 14693, 20 ) === line_ids( $r ), 'N1 pago manual: al año siguiente la renovación vuelve a llevar el dominio' );
$n = digi_nc_count( 'namecheap.domains.renew' ); $r->update_status( 'processing' ); digi_run_queue( true, 200 );
check( $n + 1 === digi_nc_count( 'namecheap.domains.renew' ) && same_day( $P::domain_due( $s ), $GLOBALS['nc']['domains']['viejomanual.com'] ), 'N1 pago manual: y el registrador lo renueva' );
// La migración: rellena domain_expires en los servicios con dominio que no lo tienen; idempotente.
store();
$a = legacy( 'migra1.com' ); $b = legacy( 'migra2.com' );
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter', 1, array( 'Dominio' => 'soloplan.com' ) ) ), 'ana@soloplan.com' );
$c = (int) $o->get_meta( '_digitalisimo_service' );
delete_option( $P::DOMAIN_EXPIRES_MIGRATED );
$hooked = false; foreach ( $GLOBALS['hooks']['init'] ?? array() as $cbs ) foreach ( $cbs as $cb ) if ( array( $P, 'migrate_domain_expires' ) === $cb[0] ) $hooked = true;
check( $hooked, 'N1 la migración corre en init' );
$filled = $P::migrate_domain_expires();
check( $filled >= 2 && (int) $P::svc( $a, 'domain_expires' ) === (int) $P::svc( $a, 'expires' ) && (int) $P::svc( $b, 'domain_expires' ) === (int) $P::svc( $b, 'expires' ) && '' === $P::svc( $c, 'domain_expires' ), 'N1 migración: rellena domain_expires = expires en los servicios con dominio; uno sólo de hosting no se toca' );
$before = array( $P::svc( $a, 'domain_expires' ), $P::svc( $b, 'domain_expires' ) );
$again = $P::migrate_domain_expires();
delete_option( $P::DOMAIN_EXPIRES_MIGRATED ); $third = $P::migrate_domain_expires();
check( 0 === $again && 0 === $third && $before === array( $P::svc( $a, 'domain_expires' ), $P::svc( $b, 'domain_expires' ) ), 'N1 migración idempotente: otra vez (y sin la marca) no cambia nada' );

// ── N2. Varios dominios por pedido: salida completa de los extras ─────────────────────────────────────────────
store(); $GLOBALS['nc']['balance'] = 15;
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'uno.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'uno.mx' ) ) ), 'ana@uno.com' );
$main = (int) $o->get_meta( '_digitalisimo_service' ); $x = svc_of( $o, 'uno.mx' );
check( 'completed' !== $o->get_status() && 'on-hold' === $o->get_status() && 'on-hold' === $P::svc( $x, 'status' ) && $P::is_extra( $x ) && ! isset( $GLOBALS['nc']['domains']['uno.mx'] ), 'N2 con un dominio más sin registrar (sin saldo) el pedido NO se completa: queda en espera · ' . $o->get_status() );
check( 1 === count( admin_mails() ) && admin_mails( 'dominio en espera: uno.mx' ), 'N2 un solo aviso al dueño, del dominio en espera · ' . json_encode( array_column( admin_mails(), 'subject' ), JSON_UNESCAPED_UNICODE ) );
$acc = client_mails( 'ana@uno.com' );
check( 1 === count( $acc ) && false !== strpos( $acc[0]['body'], '<strong>uno.mx</strong>: en trámite' ), 'N2 el correo de accesos lista el otro dominio con su estado (en trámite)' );
check( in_array( 'retry', screen_ops( $x ), true ), 'N2 el servicio del dominio en espera tiene «Reintentar»' );
$GLOBALS['nc']['balance'] = 5000; $GLOBALS['queue'] = array();
check( $P::retry( $x ), 'N2 «Reintentar» del dominio más encola la tarea' );
digi_run_queue( true, 200 );
check( isset( $GLOBALS['nc']['domains']['uno.mx'] ) && 'active' === $P::svc( $x, 'status' ) && 'completed' === $o->get_status(), 'N2 con saldo, «Reintentar» lo registra, el servicio queda activo y recién entonces el pedido se completa' );
// «Reintentar» desde el servicio del plan también lo retoma.
$GLOBALS['nc']['balance'] = 15; $GLOBALS['mails'] = array();
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'dos.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'dos.mx' ) ) ), 'ana@dos.com' );
$GLOBALS['nc']['balance'] = 5000; $GLOBALS['queue'] = array();
check( $P::retry( (int) $o->get_meta( '_digitalisimo_service' ) ), 'N2 «Reintentar» del servicio del plan también encola los dominios pendientes' );
digi_run_queue( true, 200 );
check( isset( $GLOBALS['nc']['domains']['dos.mx'] ) && 'completed' === $o->get_status(), 'N2 y los registra' );
// Todo bien: el correo de accesos lista el otro dominio como registrado.
store(); $o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'bien.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'bien.mx' ) ) ), 'ana@bien.com' );
$acc = client_mails( 'ana@bien.com' );
check( 'completed' === $o->get_status() && 1 === count( $acc ) && false !== strpos( $acc[0]['body'], '<strong>bien.mx</strong>: registrado y apuntado a nuestros nameservers' ), 'N2 todo bien: el pedido se completa y el correo dice que el otro dominio quedó registrado' );
// Registro + transferencia con la revisión manual de fábrica: «Aprobar dominio» para la transferencia.
store( array( 'own_domain_auto' => 0 ) );
digi_product( 50, 'Transferencia', 400 ); digi_domain( 50, '*', 'transfer' ); Digitalisimo_Hosting_Products::flush();
$GLOBALS['nc']['taken'] = array( 'ajeno.com' );
$o = digi_order( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'nuevo.com' ) ), new Digi_Test_Item( 50, 'Transferencia', 1, array( 'Dominio' => 'ajeno.com' ) ) ), array( 'email' => 'ana@nuevo.com' ) );
$o->update_meta_data( '_digitalisimo_epp', array( 'ajeno.com' => $P::encrypt( 'EPP-ajeno-333' ) ) ); $o->save();
$o->update_status( 'processing' ); digi_run_queue( true, 200 );
$x = svc_of( $o, 'ajeno.com' );
check( 'on-hold' === $o->get_status() && (bool) $P::svc( $x, 'needs_review' ) && in_array( 'approve', screen_ops( $x ), true ), 'N2 la transferencia que espera revisión marca needs_review, sale «Aprobar dominio» y el pedido no se completa' );
$GLOBALS['queue'] = array();
post_op( 'approve', $x ); digi_run_queue( true, 200 );
check( (bool) $P::svc( $x, 'transfer_pending' ) && 'active' === $P::svc( $x, 'status' ) && 'completed' === $o->get_status(), 'N2 «Aprobar dominio» tramita la transferencia y completa el pedido' );
// Un dominio más que ya es de otro cliente: owner_conflict.
store();
$GLOBALS['nc']['taken'] = array(); $other = paid( array( new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'ajeno.mx' ) ) ), 'otro@cliente.test' );
$other_s = (int) $other->get_meta( '_digitalisimo_service' ); $P::svc_set( $other_s, array( 'customer' => 99 ) );
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'propio.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'ajeno.mx' ) ) ), 'ana@propio.com' );
$x = svc_of( $o, 'ajeno.mx' );
check( (bool) $P::svc( $x, 'owner_conflict' ) && 'on-hold' === $P::svc( $x, 'status' ) && 'on-hold' === $o->get_status(), 'N2 un dominio más que es de otro cliente marca owner_conflict y el pedido no se completa' );
// La red falla sólo para el dominio más: reintentos y, agotados, UN aviso; sin tareas de más.
store();
nc_down_for( array( 'red2.mx' ) );
$o = digi_order( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'red1.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'red2.mx' ) ) ), array( 'email' => 'ana@red1.com' ) );
$o->update_status( 'processing' );
$max_queue = 0; for ( $i = 0; $i < 12 && $GLOBALS['queue']; $i++ ) { $max_queue = max( $max_queue, count( $GLOBALS['queue'] ) ); digi_run_queue( true, 1 ); }
$x = svc_of( $o, 'red2.mx' );
$st = $o->get_meta( '_digitalisimo_provision_x_' . substr( md5( 'register:21:red2.mx' ), 0, 12 ) );
check( 5 === digi_nc_count( 'namecheap.domains.create' ) - 1 && ! empty( $st['stopped'] ) && 'on-hold' === $P::svc( $x, 'status' ) && 'on-hold' === $o->get_status() && ! $GLOBALS['queue'] && 1 === $max_queue, 'N2 la red falla para el dominio más: 5 intentos con una sola tarea en cola a la vez, después queda detenido y el pedido en espera · intentos ' . ( digi_nc_count( 'namecheap.domains.create' ) - 1 ) . ', cola máx. ' . $max_queue );
check( 1 === count( admin_mails() ) && admin_mails( 'dominio en espera: red2.mx' ) && false !== strpos( admin_mails()[0]['body'], 'Se agotaron los reintentos automáticos' ), 'N2 al agotar los reintentos se avisa UNA vez · ' . json_encode( array_column( admin_mails(), 'subject' ), JSON_UNESCAPED_UNICODE ) );
// Namecheap caído para los dos (sonda 15 del revisor): un aviso, no cinco.
store(); unset( $GLOBALS['nc_down'] );
$GLOBALS['nc']['timeout'] = array( 'namecheap.domains.create' => 99 );
$o = digi_order( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'red1.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'red2.mx' ) ) ), array( 'email' => 'ana@red1.com' ) );
$o->update_status( 'processing' );
for ( $i = 0; $i < 12 && $GLOBALS['queue']; $i++ ) digi_run_queue( true, 5 );
check( 1 === count( admin_mails() ) && false !== strpos( admin_mails()[0]['body'], 'red2.mx' ) && ! $GLOBALS['queue'] && 10 === digi_nc_count( 'namecheap.domains.create' ), 'N2 Namecheap caído para los dos dominios: cinco intentos de cada uno y UN aviso que nombra a los dos · ' . count( admin_mails() ) . ' avisos, ' . digi_nc_count( 'namecheap.domains.create' ) . ' intentos' );
// El alta detenida (faltan datos) no se repite con una tarea que sólo venía por los demás dominios (ni repite su aviso);
// marcar el pedido pagado otra vez, sí (cuenta como «Reintentar»).
store();
$o = digi_order( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'sintel.com' ) ) ), array( 'email' => 'ana@sintel.com', 'phone' => '' ) );
$o->update_status( 'processing' ); digi_run_queue( true, 200 );
$P::enqueue( $P::HOOK_PROVISION, $o->get_id() ); digi_run_queue( true, 200 );
check( 'on-hold' === $o->get_status() && 1 === count( admin_mails() ) && ! isset( $GLOBALS['nc']['domains']['sintel.com'] ), 'N2 una tarea suelta no repite el alta detenida ni su aviso · ' . count( admin_mails() ) . ' avisos' );
$o->billing['phone'] = '33 1234 5678'; $o->update_status( 'completed' ); digi_run_queue( true, 200 );
check( isset( $GLOBALS['nc']['domains']['sintel.com'] ), 'N2 corregidos los datos, marcar el pedido pagado otra vez lo retoma (como «Reintentar»)' );
// La revisión diaria retoma un dominio más cuya tarea se perdió.
store();
nc_down_for( array( 'perdido.mx' ) );
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'perdido.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'perdido.mx' ) ) ), array( 'email' => 'ana@perdido.com' ) );
$o->update_status( 'processing' ); digi_run_queue( true, 1 );
$x = svc_of( $o, 'perdido.mx' );
unset( $GLOBALS['nc_down'] ); $GLOBALS['queue'] = array(); // la tarea del reintento se perdió
$P::svc_set( $x, array( 'created' => time() - 2 * HOUR_IN_SECONDS ) );
$P::daily(); digi_run_queue( true, 200 );
check( isset( $GLOBALS['nc']['domains']['perdido.mx'] ) && 'active' === $P::svc( $x, 'status' ) && 'completed' === $o->get_status(), 'N2 la revisión diaria retoma un dominio más cuya tarea se perdió, lo registra y completa el pedido' );
// Dar de baja el dominio más que no se puede: el pedido se completa (y la baja no desencola el alta de los demás).
store(); $GLOBALS['nc']['balance'] = 15;
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'baja.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'baja.mx' ) ) ), 'ana@baja.com' );
$P::cancel_service( svc_of( $o, 'baja.mx' ) );
check( 'completed' === $o->get_status(), 'N2 dado de baja el dominio más que no se pudo, el pedido se completa' );

// ── N3. Dos transferencias en un pedido: los dos EPP ──────────────────────────────────────────────────────────
store( array( 'own_domain_auto' => 1 ) );
digi_product( 50, 'Transferencia', 400 ); digi_domain( 50, '*', 'transfer' ); Digitalisimo_Hosting_Products::flush();
$GLOBALS['nc']['taken'] = array( 't1.com', 't2.com' );
$o = digi_order( array( new Digi_Test_Item( 50, 'Transferencia', 1, array( 'Dominio' => 't1.com' ) ), new Digi_Test_Item( 50, 'Transferencia', 1, array( 'Dominio' => 't2.com' ) ) ), array( 'email' => 'ana@t1.com' ) );
$o->update_meta_data( '_digitalisimo_epp', array( 't1.com' => $P::encrypt( 'EPP-de-t1-111' ), 't2.com' => $P::encrypt( 'EPP-de-t2-222' ) ) ); $o->save();
$o->update_status( 'processing' ); digi_run_queue( true, 200 );
$epp = $o->get_meta( '_digitalisimo_epp' );
check( 'completed' === $o->get_status() && is_array( $epp ) && array( 't1.com', 't2.com' ) === array_keys( $epp ) && 'EPP-de-t2-222' === $P::decrypt( $epp['t2.com'] ), 'N3 dos transferencias en un pedido: los dos códigos EPP siguen en el pedido' );
$GLOBALS['nc']['domains']['t1.com'] = strtotime( '+1 year' ); // llegó la primera
$P::transfer_settled( svc_of( $o, 't1.com' ) );
$epp = $o->get_meta( '_digitalisimo_epp' );
check( is_array( $epp ) && array( 't2.com' ) === array_keys( $epp ), 'N3 al llegar una transferencia se borra sólo su código; el de la otra queda' );

// ── N4. Hosting mensual + dominio: mes 12 con el pedido sólo del dominio vivo ─────────────────────────────────
store();
digi_variable( 70, 'Plan Nube', array( 71 => array( 'Mensual', 150, array( '_digitalisimo_hosting_period' => 1 ) ) ), array( '_digitalisimo_hosting_type' => 'hosting', '_digitalisimo_webuzo_plan' => 'digitalisimo_starter', '_digitalisimo_hosting_period' => 12 ) );
$o = paid( array( new Digi_Test_Item( 71, 'Plan Nube' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'coincide.com' ) ) ), 'ana@coincide.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' );
for ( $i = 1; $i <= 10; $i++ ) { $P::daily( (int) $P::svc( $s, 'expires' ) - 6 * DAY_IN_SECONDS ); wc_get_order( (int) $P::svc( $s, 'renewal_order' ) )->update_status( 'processing' ); digi_run_queue( true, 200 ); }
$exp = (int) $P::svc( $s, 'expires' ); $dd = $P::domain_due( $s );
$P::daily( $exp - 6 * DAY_IN_SECONDS ); $r11 = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
$P::daily( $dd - 29 * DAY_IN_SECONDS ); $dro = wc_get_order( (int) $P::svc( $s, 'domain_renewal_order' ) );
$r11->update_status( 'processing' ); digi_run_queue( true, 200 ); // pagó tarde el mes 11: las fechas coinciden
$GLOBALS['mails'] = array();
$P::daily( (int) $P::svc( $s, 'expires' ) - 6 * DAY_IN_SECONDS ); $r12 = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
check( $dro && 'cancelled' === $dro->get_status() && array( 71, 20 ) === line_ids( $r12 ) && ! (int) $P::svc( $s, 'domain_renewal_order' ), 'N4 el pedido sólo del dominio sin pagar se cancela y el del mes 12 cobra hosting y dominio juntos · ' . json_encode( line_ids( $r12 ) ) . ' #' . $dro->get_id() . ' ' . $dro->get_status() );
$notice = client_mails( 'ana@coincide.com' );
check( $notice && false !== strpos( $notice[0]['body'], 'ni tu dominio' ) && false !== strpos( $notice[0]['body'], 'dominio por un año' ), 'N4 el aviso del mes 12 dice que incluye el dominio (el aviso del dominio no se pierde)' );
$n = digi_nc_count( 'namecheap.domains.renew' ); $was = $GLOBALS['nc']['domains']['coincide.com'];
$r12->update_status( 'processing' ); digi_run_queue( true, 200 );
check( $n + 1 === digi_nc_count( 'namecheap.domains.renew' ) && ymd( $GLOBALS['nc']['domains']['coincide.com'] ) === ymd( strtotime( '+1 year', $was ) ) && same_day( $GLOBALS['nc']['domains']['coincide.com'], $P::domain_due( $s ) ), 'N4 pagado el mes 12, el dominio se renueva una vez y el servicio anota lo mismo que el registrador' );
// El pedido sólo del dominio que se canceló llega pagado igual (un pago que ya iba en camino): no se renueva otra vez.
$n = digi_nc_count( 'namecheap.domains.renew' ); $due = $P::domain_due( $s ); $GLOBALS['mails'] = array();
$dro->update_status( 'processing' ); digi_run_queue( true, 200 );
check( $n === digi_nc_count( 'namecheap.domains.renew' ) && $due === $P::domain_due( $s ) && admin_mails() && false !== strpos( admin_mails()[0]['body'], 'ya se renovó con otro pedido' ), 'N4 si el pedido cancelado del dominio llega pagado igual, no se renueva dos veces: se avisa para reembolsarlo' );

// ── N5. «Ya lo renové yo» se comprueba en el registrador ──────────────────────────────────────────────────────
store();
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'dijoquesi.com' ) ) ), 'ana@dijoquesi.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $exp = (int) $P::svc( $s, 'expires' ); $no = $GLOBALS['next_order']; $GLOBALS['mails'] = array();
$e = $P::manual_payment( $s, array( 'months' => 36, 'method' => 'cash', 'renewed_elsewhere' => true, 'key' => 'n5a' ) );
check( is_wp_error( $e ) && false !== strpos( $e->get_error_message(), 'la renovación en el registrador no alcanza' ) && $exp === (int) $P::svc( $s, 'expires' ) && $no === $GLOBALS['next_order'] && ! $P::svc( $s, 'manual_keys' ), 'N5 «ya lo renové» de 3 años sin renovar en el registrador: error y nada registrado' );
$GLOBALS['nc']['domains']['dijoquesi.com'] = strtotime( '+1 year', $GLOBALS['nc']['domains']['dijoquesi.com'] );
$e = $P::manual_payment( $s, array( 'months' => 36, 'method' => 'cash', 'renewed_elsewhere' => true, 'key' => 'n5b' ) );
check( is_wp_error( $e ) && $exp === (int) $P::svc( $s, 'expires' ), 'N5 renovado sólo un año de los tres pagados: tampoco alcanza' );
$GLOBALS['nc']['domains']['dijoquesi.com'] = strtotime( '+2 year', $GLOBALS['nc']['domains']['dijoquesi.com'] );
$r = $P::manual_payment( $s, array( 'months' => 36, 'method' => 'cash', 'renewed_elsewhere' => true, 'key' => 'n5c' ) );
check( ! is_wp_error( $r ) && 'elsewhere' === $r['domain_renewal'] && ymd( $GLOBALS['nc']['domains']['dijoquesi.com'] ) === ymd( $P::svc( $s, 'expires' ) ) && (int) $P::svc( $s, 'expires' ) === (int) $P::svc( $s, 'domain_expires' ) && 0 === digi_nc_count( 'namecheap.domains.renew' ), 'N5 con los tres años renovados allá: se registra y el servicio guarda el vencimiento del registrador, sin renovar otra vez' );
// Con hosting: también se comprueba y se guarda la fecha del registrador.
$s = legacy( 'conhosting.com' ); $GLOBALS['mails'] = array();
$e = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'renewed_elsewhere' => true, 'key' => 'n5d' ) );
check( is_wp_error( $e ), 'N5 con hosting: «ya lo renové» sin renovar allá también es error' );
$GLOBALS['nc']['domains']['conhosting.com'] = strtotime( '+1 year', $GLOBALS['nc']['domains']['conhosting.com'] );
$r = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'renewed_elsewhere' => true, 'key' => 'n5e' ) );
check( ! is_wp_error( $r ) && ymd( $GLOBALS['nc']['domains']['conhosting.com'] ) === ymd( $P::domain_due( $s ) ) && 0 === digi_nc_count( 'namecheap.domains.renew' ), 'N5 con hosting: renovado allá, el dominio queda con la fecha del registrador' );

// ── R1. Sólo el dominio: una de las dos opciones ──────────────────────────────────────────────────────────────
store();
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'solo.com' ) ) ), 'ana@solo.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $exp = (int) $P::svc( $s, 'expires' ); $GLOBALS['queue'] = array(); $no = $GLOBALS['next_order'];
$e = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'renew_domain' => false, 'key' => 'r1a' ) );
digi_run_queue( true, 200 );
check( is_wp_error( $e ) && $no === $GLOBALS['next_order'] && 0 === digi_nc_count( 'namecheap.domains.renew' ) && $exp === (int) $P::svc( $s, 'expires' ), 'R1 sin renovar ni «ya lo renové»: error, ningún pedido y nada se renueva solo después' );
$e = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'renew_domain' => true, 'renewed_elsewhere' => true, 'key' => 'r1b' ) );
check( is_wp_error( $e ) && false !== strpos( $e->get_error_message(), 'Elige una sola opción' ), 'R1 las dos a la vez: error' );
$_GET = array( 'manual' => $s ); ob_start(); $SP::render(); $form = ob_get_clean(); $_GET = array();
check( false !== strpos( $form, 'name="domain_action" value="renew" checked' ) && false !== strpos( $form, 'name="domain_action" value="elsewhere"' ) && false === strpos( $form, 'name="renew_domain"' ), 'R1 el formulario de un servicio que es sólo el dominio pide una de dos (radio), renovar marcada' );
$GLOBALS['nc']['domains']['solo.com'] = strtotime( '+1 year', $GLOBALS['nc']['domains']['solo.com'] );
post_op( 'manual_payment', $s, array( 'months' => 12, 'method' => 'cash', 'domain_action' => 'elsewhere', 'key' => 'r1c' ) );
check( 0 === digi_nc_count( 'namecheap.domains.renew' ) && ymd( $GLOBALS['nc']['domains']['solo.com'] ) === ymd( $P::svc( $s, 'expires' ) ), 'R1 el formulario con «ya lo renové» registra el pago sin renovar otra vez' );
// Sin saldo: el pedido del pago se completa, pero la renovación NO corre sola (espera «Reintentar»); un aviso.
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'sinsaldo.com' ) ) ), 'ana@sinsaldo.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $GLOBALS['nc']['balance'] = 1; $GLOBALS['mails'] = array(); $GLOBALS['queue'] = array();
$r = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'key' => 'r1d' ) );
$queued = array_column( $GLOBALS['queue'], 'hook' ); $renews = digi_nc_count( 'namecheap.domains.renew' );
digi_run_queue( true, 200 );
check( 'failed' === $r['domain_renewal'] && 'completed' === wc_get_order( $r['order'] )->get_status() && ! in_array( $P::HOOK_RENEW, $queued, true ) && 1 === count( admin_mails() ) && $renews === digi_nc_count( 'namecheap.domains.renew' ), 'R1 sin saldo: el pedido del pago queda completado sin renovar solo al completarse, con un aviso · cola ' . json_encode( $queued ) );
$GLOBALS['nc']['balance'] = 5000;
check( $P::retry( $s ), 'R1 «Reintentar» sí lo renueva' );
digi_run_queue( true, 200 );
check( $renews + 1 === digi_nc_count( 'namecheap.domains.renew' ) && same_day( $GLOBALS['nc']['domains']['sinsaldo.com'], $P::svc( $s, 'expires' ) ), 'R1 renovado con «Reintentar», el servicio vence con el registrador' );

// ── Menor: pago manual de una transferencia que no ha llegado ─────────────────────────────────────────────────
store( array( 'own_domain_auto' => 1 ) );
digi_product( 50, 'Transferencia', 400 ); digi_domain( 50, '*', 'transfer' ); Digitalisimo_Hosting_Products::flush();
$GLOBALS['nc']['taken'] = array( 'viene.com' );
$o = paid( array( new Digi_Test_Item( 50, 'Transferencia', 1, array( 'Dominio' => 'viene.com' ) ) ), 'ana@viene.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $exp = (int) $P::svc( $s, 'expires' ); $no = $GLOBALS['next_order'];
$e = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'amount' => '400', 'key' => 'menor17' ) );
check( is_wp_error( $e ) && false !== strpos( $e->get_error_message(), 'la transferencia no ha terminado' ) && $no === $GLOBALS['next_order'] && $exp === (int) $P::svc( $s, 'expires' ), 'Menor: pago manual de una transferencia que no llegó = error claro, nada registrado' );

// ── R3. Dynadot: la lista de precios guardada se pide otra vez antes de declarar premium por precio ───────────
store( array( 'registrar_by_tld' => array( '.lat' => 'dynadot' ) ) );
$GLOBALS['real_transients'] = 1; $GLOBALS['transients'] = array();
$dyn = new Digitalisimo_Hosting_Dynadot( array( 'dynadot_api_key' => DD_KEY ) );
$GLOBALS['dd']['prices']['lat'] = array( 1.54, 26.97, 26.97 ); // promoción
$dyn->price( 'lat', 'REGISTER', 15 ); // la lista queda guardada con la promoción
$GLOBALS['dd']['prices']['lat'] = array( 26.97, 26.97, 26.97 ); // terminó la promoción
$GLOBALS['dd']['calls'] = array();
$c = $dyn->check( 'nuevo.lat', 15 );
check( 'ok' === $c['kind'] && false === $c['premium'] && 1 === dd_count( 'tld_price' ), 'R3 al acabar una promoción, el precio normal no sale premium: la lista se pide otra vez (una vez) · tld_price ' . dd_count( 'tld_price' ) );
$GLOBALS['dd']['calls'] = array();
$c = $dyn->check( 'otro.lat', 15 );
check( false === $c['premium'] && 0 === dd_count( 'tld_price' ), 'R3 la lista nueva queda guardada: la siguiente búsqueda no la pide otra vez' );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre || 0 !== strpos( $url, 'https://api.dynadot.com/api3.json' ) ) return $pre;
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	if ( 'search' === $q['command'] && 'caro.lat' === ( $q['domain0'] ?? '' ) ) { $GLOBALS['dd']['calls'][] = array( 'cmd' => 'search', 'params' => $q ); return dd_response( 'search', array( 'ResponseCode' => '0', 'SearchResults' => array( array( 'DomainName' => 'caro.lat', 'Status' => 'success', 'Available' => 'yes', 'Price' => 'Registration Price: 715.00 in USD and Renewal price: 715.00 in USD' ) ) ) ); }
	return $pre;
}, 1, 3 );
$GLOBALS['dd']['calls'] = array(); $GLOBALS['transients'] = array();
$dyn->price( 'lat', 'REGISTER', 15 ); $GLOBALS['dd']['calls'] = array();
$c = $dyn->check( 'caro.lat', 15 );
check( true === $c['premium'] && 1 === dd_count( 'tld_price' ), 'R3 un precio especial de verdad sigue siendo premium tras pedir la lista otra vez' );
$GLOBALS['real_transients'] = 0; $GLOBALS['transients'] = array();

echo "Hosting, segunda revisión crítica: {$GLOBALS['checks']} comprobaciones correctas.\n";
