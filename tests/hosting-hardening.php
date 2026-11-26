<?php
// Segunda revisión adversarial de la venta automática de DIGITALÍSIMO Hosting (2026-11-24), sin WordPress y con Webuzo
// y Namecheap simulados (tests/fixtures/hosting-wp.php). Cada bloque reproduce un hallazgo y falla con el código anterior
// al arreglo (commit c15b7a89):
//   1. apropiación de dominio: un dominio que ya tiene servicio de otro cliente, o que está en nuestra cuenta de
//      Namecheap sin ser de este cliente, va a revisión manual SIN llamar a Webuzo; primero el dominio, luego la cuenta;
//   2. pagar la renovación cancela cualquier suspensión pendiente (reintento de red o en espera de una clave nueva);
//   3. parada cerrada de Webuzo: cualquier error de la consulta de sólo lectura (en cualquier idioma) o una respuesta
//      sin la forma esperada detiene todo; ni otra forma de escribir la URL ni rotar las sales la saltan; suspender y
//      reactivar pasan por la misma consulta previa;
//   4. la reactivación manual del administrador se respeta (prórroga visible);
//   5. se aprovisiona por pago confirmado, no por estado; los pedidos anteriores al encendido no se aprovisionan;
//   6. el checkout sólo cambia con la venta automática encendida;
//   y los bajos: IVA y moneda reales en los precios, tope de la comisión, «Reenviar accesos» al correo del servicio,
//   usuarios reservados y «Reintentar» sin pisar el estado.
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
$W = 'Digitalisimo_Hosting_Webuzo';
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function digi_notes( $order ) { return implode( "\n", $order->notes ); }
function digi_reset( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['postmeta'] = array();
	update_post_meta( 14693, '_digitalisimo_webuzo_plan', 'digitalisimo_starter' ); update_post_meta( 14696, '_digitalisimo_webuzo_plan', 'digitalisimo_pro' );
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7 ) );
}
/** Un pedido pagado en línea: plan y/o dominio. */
function digi_buy( $domain, $hosting = 14693, $dp = 20, array $billing = array(), $customer = 7, $method = 'stripe' ) {
	$items = array();
	if ( $hosting ) $items[] = new Digi_Test_Item( $hosting, 'Web Hosting', 1, $dp ? array() : array( 'Dominio' => $domain ) );
	if ( $dp ) $items[] = new Digi_Test_Item( $dp, 'Dominio', 1, array( 'Dominio' => $domain ) );
	$o = digi_order( $items, $billing );
	$o->customer = $customer; $o->method = $method;
	return $o;
}
function digi_pay_run( $o, $status = 'processing' ) { $o->update_status( $status ); digi_run_queue(); return (int) $o->get_meta( '_digitalisimo_service' ); }
function digi_account( $domain ) { foreach ( $GLOBALS['webuzo']['users'] as $u ) if ( $domain === $u['domain'] ) return $u; return null; }

// ── 1. Apropiación de dominio ───────────────────────────────────────────────────────────────────────────────
digi_reset();
// Caso A: Ana (cliente 7) compra sólo victima.com; otro cliente (99) compra un plan con victima.com como dominio propio.
$ana = digi_buy( 'victima.com', 0, 20 );
digi_pay_run( $ana );
check( isset( $GLOBALS['nc']['domains']['victima.com'] ) && 'completed' === $ana->get_status(), 'Ana registra victima.com (sólo dominio)' );
$GLOBALS['mails'] = array(); $calls = count( $GLOBALS['webuzo']['calls'] );
$mal = digi_buy( 'victima.com', 14693, 0, array( 'email' => 'atacante@malo.test', 'first_name' => 'Mal' ), 99 );
digi_pay_run( $mal );
check( null === digi_account( 'victima.com' ) && $calls === count( $GLOBALS['webuzo']['calls'] ), 'Otro cliente que compra un plan con victima.com no obtiene cuenta (ni se llama a Webuzo)' );
check( 'on-hold' === $mal->get_status() && false !== strpos( digi_notes( $mal ), 'ya tiene un servicio de otro cliente' ) && ! digi_mails_to( 'atacante@malo.test' ), 'Su pedido queda en espera para revisión manual, con nota y sin correo de accesos' );
check( 1 === count( digi_mails_to( 'admin@tienda.test' ) ), 'Y se avisa al administrador' );
// La misma Ana (mismo usuario de WordPress) sí puede contratar hosting para su dominio.
$ana2 = digi_buy( 'victima.com', 14693, 0 );
digi_pay_run( $ana2 );
check( 'completed' === $ana2->get_status() && digi_account( 'victima.com' ), 'La misma clienta (mismo usuario de WordPress) sí obtiene hosting para su dominio' );
// Un invitado nunca coincide (su correo no está verificado), aunque use el mismo correo.
digi_reset();
$inv = digi_buy( 'invitado.com', 0, 20, array(), 0 ); digi_pay_run( $inv );
$inv2 = digi_buy( 'invitado.com', 14693, 0, array(), 0 ); digi_pay_run( $inv2 );
check( 'on-hold' === $inv2->get_status() && null === digi_account( 'invitado.com' ), 'Un pedido de invitado sobre un dominio con servicio va a revisión manual (decisión: sólo el mismo usuario de WordPress)' );
// Caso B: plan + dominio de un dominio que ya está en nuestra cuenta de Namecheap y no es de este pedido.
digi_reset();
$GLOBALS['nc']['domains']['ajeno.com'] = strtotime( '+200 days' );
$b = digi_buy( 'ajeno.com' ); digi_pay_run( $b );
check( 'on-hold' === $b->get_status() && null === digi_account( 'ajeno.com' ) && ! $GLOBALS['webuzo']['calls'], 'Plan + dominio ajeno ya en la cuenta: ni cuenta huérfana ni llamada a Webuzo (primero el dominio)' );
// Hosting con dominio propio que está en nuestra cuenta de Namecheap sin servicio de este cliente: revisión manual.
digi_reset();
$GLOBALS['nc']['domains']['delacasa.com'] = strtotime( '+200 days' );
$c = digi_buy( 'delacasa.com', 14693, 0 ); digi_pay_run( $c );
check( 'on-hold' === $c->get_status() && ! $GLOBALS['webuzo']['calls'] && false !== strpos( digi_notes( $c ), 'está en nuestra cuenta de Namecheap' ), 'Hosting para un dominio de nuestra cuenta de Namecheap que no consta como del cliente: revisión manual' );
// Un dominio externo (no es nuestro) sí se hospeda: riesgo aceptado (no apunta a nuestros NS hasta que su dueño los cambia).
$e = digi_buy( 'externo.com', 14693, 0 ); digi_pay_run( $e );
check( 'completed' === $e->get_status() && digi_account( 'externo.com' ), 'Un dominio externo se hospeda (riesgo aceptado y documentado)' );

// ── 2. Pagar la renovación cancela la suspensión pendiente ──────────────────────────────────────────────────
digi_reset();
$o = digi_buy( 'pagatarde.com' ); $s = digi_pay_run( $o );
$exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
// Pasada la gracia la revisión diaria decide suspender; Webuzo no responde y queda un reintento programado.
$GLOBALS['webuzo']['timeout'] = 1; $GLOBALS['queue'] = array();
$P::daily( $exp + 8 * DAY_IN_SECONDS );
digi_run_queue( true, 1 );
$retry = $GLOBALS['queue']; $GLOBALS['queue'] = array();
check( 1 === count( $retry ) && 'digitalisimo_hosting_suspend' === $retry[0]['hook'], 'La suspensión quedó con su reintento' );
// Orden real de Action Scheduler: el cliente paga y la renovación (asíncrona) corre ANTES que el reintento programado.
$r->update_status( 'processing' ); digi_run_queue();
check( 'active' === $P::svc( $s, 'status' ) && (int) $P::svc( $s, 'expires' ) > $exp, 'La renovación pagada corre primero: activo y un año más' );
$GLOBALS['queue'] = $retry; digi_run_queue();
check( 'active' === $P::svc( $s, 'status' ) && '' === $GLOBALS['webuzo']['users']['pagatarde']['status'] && 0 === digi_webuzo_count( 'users', 'suspend', 'pagatarde' ), 'El reintento de la suspensión ya no suspende a quien pagó' );
// La suspensión esperaba una clave nueva de Webuzo; el cliente paga durante la parada; al guardar la clave no se suspende.
digi_reset();
$o = digi_buy( 'parada.com' ); $s = digi_pay_run( $o );
$exp = (int) $P::svc( $s, 'expires' );
$P::daily( $exp - 29 * DAY_IN_SECONDS );
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
$opts = get_option( 'digitalisimo_hosting_options' ); $opts['webuzo_api_key'] = 'CLAVE-VIEJA'; update_option( 'digitalisimo_hosting_options', $opts );
$GLOBALS['queue'] = array();
$P::daily( $exp + 8 * DAY_IN_SECONDS ); digi_run_queue();
check( $W::any_block() && isset( get_option( $P::WAITING )[ 'digitalisimo_hosting_suspend:' . $s ] ), 'Clave rechazada: la suspensión queda esperando la clave nueva' );
$r->update_status( 'processing' ); digi_run_queue();
check( ! isset( ( get_option( $P::WAITING ) ?: array() )[ 'digitalisimo_hosting_suspend:' . $s ] ), 'Pagar la renovación quita la suspensión de la espera' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_api_key' => 'CLAVE-WEBUZO-OK' ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
digi_run_queue();
check( 0 === digi_webuzo_count( 'users', 'suspend', 'parada' ) && 'active' === $P::svc( $s, 'status' ), 'Al guardar la clave nueva no se suspende a quien pagó durante la parada' );

// ── 3. Parada cerrada de Webuzo ─────────────────────────────────────────────────────────────────────────────
digi_reset();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( empty( $GLOBALS['es'] ) || 0 !== strpos( $url, 'https://webuzo.test:2005/' ) && 0 !== strpos( $url, 'https://199.85.208.102:2005/' ) && 0 !== strpos( $url, 'https://webuzo.test:2006/' ) ) return $pre;
	$GLOBALS['es_calls']++;
	return 'shape' === $GLOBALS['es'] ? digi_json( array( 'title' => 'Webuzo' ) ) : digi_json( array( 'error' => array( 'Usuario o contraseña incorrectos. Le quedan 2 intento(s)' ) ) );
}, 5, 3 );
$GLOBALS['es'] = 'es'; $GLOBALS['es_calls'] = 0;
foreach ( array( 'uno-es.com', 'dos-es.com', 'tres-es.com' ) as $d ) digi_pay_run( digi_buy( $d ) );
check( 1 === $GLOBALS['es_calls'] && $W::any_block(), 'Un rechazo en español: una sola llamada para tres pedidos y la parada puesta' );
// Otra forma de escribir el mismo servidor (IP, otro puerto) con la misma clave: sigue detenido.
foreach ( array( 'https://199.85.208.102:2005', 'https://webuzo.test:2006' ) as $url ) {
	$opts = get_option( 'digitalisimo_hosting_options' ); $opts['webuzo_url'] = $url; update_option( 'digitalisimo_hosting_options', $opts );
	digi_pay_run( digi_buy( 'otra-url-' . md5( $url ) . '.com' ) );
}
check( 1 === $GLOBALS['es_calls'], 'Cambiar cómo se escribe la URL (IP, otro puerto) no salta la parada' );
$GLOBALS['salt'] = 'sales-rotadas-';
digi_pay_run( digi_buy( 'sales.com' ) );
check( 1 === $GLOBALS['es_calls'], 'Rotar las sales de WordPress no salta la parada' );
unset( $GLOBALS['salt'] );
// Una respuesta sin la forma esperada en la consulta de sólo lectura también detiene.
digi_reset(); $GLOBALS['es'] = 'shape'; $GLOBALS['es_calls'] = 0;
digi_pay_run( digi_buy( 'forma1.com' ) ); digi_pay_run( digi_buy( 'forma2.com' ) );
check( 1 === $GLOBALS['es_calls'] && $W::any_block(), 'Una lista de cuentas sin «users» detiene igual que un rechazo de clave' );
// Suspender a mano también pasa por la consulta previa cerrada (y no llega a suspender).
digi_reset(); $GLOBALS['es'] = 0;
$o = digi_buy( 'manual.com' ); $s = digi_pay_run( $o );
$GLOBALS['es'] = 'es'; $GLOBALS['es_calls'] = 0;
$P::svc_set( $s, array( 'suspend_reason' => 'manual' ) ); $P::run_suspend( $s );
check( 1 === $GLOBALS['es_calls'] && $W::any_block() && 'active' === $P::svc( $s, 'status' ), 'Suspender a mano hace primero la consulta de sólo lectura; su rechazo detiene sin suspender' );
$GLOBALS['es'] = 0;
// La parada sólo se levanta con otra clave guardada o con «Ya corregí la causa».
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_url' => 'https://webuzo.test:2005' ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
check( $W::from_settings()->blocked(), 'Guardar otros ajustes no levanta la parada' );

// ── 4. La reactivación manual se respeta ────────────────────────────────────────────────────────────────────
// Con fechas reales (la reactivación ocurre de verdad después de la gracia): vencido hace 10 días, gracia de 7.
digi_reset();
$o = digi_buy( 'promete.com' ); $s = digi_pay_run( $o );
$P::svc_set( $s, array( 'expires' => time() - 10 * DAY_IN_SECONDS ) );
$P::daily(); digi_run_queue();
check( 'suspended' === $P::svc( $s, 'status' ), 'Suspendido tras la gracia' );
$P::svc_set( $s, array( 'unsuspend_reason' => 'manual' ) ); $P::enqueue( $P::HOOK_UNSUSPEND, $s ); digi_run_queue(); // como «Reactivar» de la pantalla
$P::daily( time() + DAY_IN_SECONDS ); digi_run_queue();
check( 'active' === $P::svc( $s, 'status' ), 'Reactivado a mano: la revisión del día siguiente no lo vuelve a suspender' );
check( abs( (int) $P::svc( $s, 'reprieve_until' ) - ( time() + 7 * DAY_IN_SECONDS ) ) < 60, 'La prórroga es una sola gracia desde hoy (no el doble)' );
ob_start(); Digitalisimo_Hosting_Services_Page::render(); $html = ob_get_clean();
check( false !== strpos( $html, 'Prórroga (reactivado a mano) hasta el' ), 'La prórroga se ve en Servicios de hosting' );
$P::daily( (int) $P::svc( $s, 'reprieve_until' ) + DAY_IN_SECONDS ); digi_run_queue();
check( 'suspended' === $P::svc( $s, 'status' ), 'Terminada la prórroga sin pago, se suspende otra vez' );

// ── 5. Pago confirmado ──────────────────────────────────────────────────────────────────────────────────────
digi_reset();
$cod = digi_buy( 'contraentrega.com', 14693, 20, array(), 7, 'cod' );
$cod->update_status( 'processing' );
check( ! $GLOBALS['queue'] && false !== strpos( digi_notes( $cod ), 'pago fuera de línea' ), 'Contra entrega en «procesando» (sin cobrar): no se aprovisiona' );
$cod->update_status( 'completed' ); digi_run_queue();
check( digi_account( 'contraentrega.com' ), 'Marcado «completado» por el administrador: se aprovisiona' );
$bacs = digi_buy( 'transferencia.com', 14693, 20, array(), 7, 'bacs' );
$bacs->update_status( 'processing' ); digi_run_queue();
check( null === digi_account( 'transferencia.com' ), 'Transferencia en «procesando»: no se aprovisiona' );
$on = digi_buy( 'enlinea.com' ); $on->date_paid = 0; $on->status = 'processing';
$P::paid( $on->get_id() );
check( ! $GLOBALS['queue'], 'En línea sin fecha de pago: no se aprovisiona' );
digi_store_settings( array( 'provision_since' => time() + 100 ) );
$viejo = digi_buy( 'viejo.com' ); $viejo->update_status( 'processing' );
check( ! $GLOBALS['queue'] && false !== strpos( digi_notes( $viejo ), 'anterior al encendido' ), 'Un pedido anterior al encendido de la venta automática no se aprovisiona' );
digi_store_settings();
// Encender la venta automática desde la pantalla anota la fecha.
digi_store_settings( array( 'provision_enabled' => 0, 'provision_since' => 0 ) );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'provision_enabled' => 1 ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
check( abs( (int) get_option( 'digitalisimo_hosting_options' )['provision_since'] - time() ) < 5, 'Al encender la venta automática se anota la fecha' );

// ── 6. Checkout ─────────────────────────────────────────────────────────────────────────────────────────────
class Digi_Test_Cart2 { public function get_cart() { return array( 'a' => array( 'product_id' => 20 ) ); } }
WC()->cart = new Digi_Test_Cart2();
$fields = array( 'billing' => array( 'billing_first_name' => array( 'label' => 'Nombre' ), 'billing_address_1' => array( 'label' => 'Dirección' ), 'billing_city' => array( 'label' => 'Ciudad' ) ) );
digi_store_settings( array( 'simplify_checkout' => 1, 'provision_enabled' => 0 ) );
$out = Digitalisimo_Hosting::checkout_fields( $fields );
check( ! isset( $out['billing']['billing_address_1'] ), 'Con la venta automática apagada, instalar no agrega campos al pago' );
digi_store_settings( array( 'simplify_checkout' => 1 ) );
$out = Digitalisimo_Hosting::checkout_fields( $fields );
check( ! empty( $out['billing']['billing_address_1']['required'] ), 'Encendida y con un dominio en el carrito, se pide la dirección del titular' );
WC()->cart = null;

// ── Bajos ───────────────────────────────────────────────────────────────────────────────────────────────────
check( 'root' !== $P::username( 'root.com', array() ) && 'admin' !== $P::username( 'admin.mx', array() ) && 'mysql2' === $P::username( 'mysql.com', array() ), 'Los usuarios reservados (root, admin, mysql…) nunca se dan a un cliente' );
digi_reset();
$o = digi_buy( 'reenvio.com' ); $s = digi_pay_run( $o );
$o->billing['email'] = 'otro@cambiado.test'; $GLOBALS['mails'] = array();
$P::run_resend( $s );
check( 1 === count( digi_mails_to( 'ana@cliente.test' ) ) && ! digi_mails_to( 'otro@cambiado.test' ), '«Reenviar accesos» va al correo con el que se contrató, no al de facturación actual' );
$PR = 'Digitalisimo_Hosting_Pricing';
update_option( 'woocommerce_calc_taxes', 'no' );
$p = $PR::params();
check( 0.0 === $p['vat'], 'Sin impuestos en WooCommerce, la comisión se calcula sin IVA encima' );
update_option( 'woocommerce_calc_taxes', 'yes' );
digi_store_settings( array( 'pricing_fee' => 95 ) );
$p = $PR::params(); $x = $PR::sell_price( 10, 18.5, $p );
check( 0.3 === $p['fee'] && $x['raw'] < 10 * 18.5 * 1.03 * 1.25 * 2, 'La comisión se topa en 30 %' );
update_option( 'woocommerce_currency', 'EUR' );
digi_store_settings( array( 'pricing_enabled' => 1 ) ); update_option( 'woocommerce_currency', 'EUR' );
$before = wc_get_product( 20 )->get_regular_price();
$entry = $PR::run();
check( 'error' === $entry['status'] && false !== strpos( $entry['message'], 'EUR' ) && $before === wc_get_product( 20 )->get_regular_price(), 'Una tienda en otra moneda no recibe precios (ni se registra a ciegas)' );
update_option( 'woocommerce_currency', 'MXN' );
// «Reintentar» sólo deja una marca: no escribe el estado (que una tarea en curso podría estar guardando); la tarea limpia
// los reintentos sobre el estado que lee al empezar.
digi_reset();
$GLOBALS['webuzo']['timeout'] = 1;
$o = digi_buy( 'carrera.com' ); $o->update_status( 'processing' ); digi_run_queue( true, 1 );
$s = (int) $o->get_meta( '_digitalisimo_service' );
$before = $o->meta['_digitalisimo_provision'];
check( 1 === ( $before['attempts'] ?? 0 ), 'Un intento fallido anotado' );
$GLOBALS['queue'] = array();
$P::retry( $s );
check( $before === $o->meta['_digitalisimo_provision'] && $o->get_meta( '_digitalisimo_retry' ), '«Reintentar» deja una marca y no reescribe el estado del pedido' );
digi_run_queue();
check( 'completed' === $o->get_status() && ! $o->get_meta( '_digitalisimo_retry' ), 'La tarea aplica la marca y termina' );

echo "Hosting, segunda revisión: {$GLOBALS['checks']} comprobaciones correctas.\n";
