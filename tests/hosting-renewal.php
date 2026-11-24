<?php
// Renovación anual de DIGITALÍSIMO Hosting (WooCommerce no trae suscripciones), con Webuzo y Namecheap simulados:
//   1. 30 días antes: pedido de renovación pagable (productos y datos del cliente, sin cancelarse solo) y correo con el
//      enlace de pago; una sola vez por ciclo aunque la revisión corra varias veces;
//   2. 7 días antes: segundo aviso con el mismo pedido;
//   3. dentro de la gracia no pasa nada; pasados N días sin pago (configurable) se suspende en Webuzo y se avisa;
//   4. al pagar: renovación del dominio en Namecheap (con saldo validado), reactivación en Webuzo y un año más desde el
//      vencimiento anterior; correr otra vez no repite nada;
//   5. saldo insuficiente al renovar → no renueva, aviso y «Reintentar» lo termina; una renovación que llegó sin
//      respuesta no se cobra dos veces;
//   6. un servicio sólo de dominio vence sin suspender nada.
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function digi_notes( $order ) { return implode( "\n", $order->notes ); }
function digi_provisioned( $domain, $hosting = 14693, $domain_product = 20 ) {
	$items = array();
	if ( $hosting ) $items[] = new Digi_Test_Item( $hosting, 'Web Hosting' );
	$items[] = new Digi_Test_Item( $domain_product, 'Dominio', 1, array( 'Dominio' => $domain ) );
	$order = digi_order( $items );
	$order->update_status( 'processing' );
	digi_run_queue();
	check( 'completed' === $order->get_status(), "El pedido de $domain se entrega" );
	return array( $order, (int) $order->get_meta( '_digitalisimo_service' ) );
}

digi_store_settings( array( 'grace_days' => 7 ) );
list( $order, $s ) = digi_provisioned( 'renueva.com' );
$expires = (int) $P::svc( $s, 'expires' );
$GLOBALS['mails'] = array();
$calls = count( $GLOBALS['webuzo']['calls'] ) + count( $GLOBALS['nc']['calls'] );

// ── 1. 30 días antes ────────────────────────────────────────────────────────────────────────────────────────
$P::daily( $expires - 31 * DAY_IN_SECONDS );
check( ! $GLOBALS['mails'] && ! $P::svc( $s, 'renewal_order' ), 'A 31 días todavía no hay aviso ni pedido' );
$P::daily( $expires - 29 * DAY_IN_SECONDS );
$renewal = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
check( $renewal && $renewal->get_id() !== $order->get_id() && 'pending' === $renewal->get_status() && $s === (int) $renewal->get_meta( '_digitalisimo_renewal_of' ), 'A 29 días se crea el pedido de renovación pendiente de pago, ligado al servicio' );
check( array( 14693, 20 ) === array_map( function ( $i ) { return $i->get_product_id(); }, $renewal->get_items() ) && 'renueva.com' === $renewal->get_items()[1]->get_meta( 'Dominio' ), 'Lleva el plan y el dominio (con su nombre) a los precios vigentes de la tienda' );
check( 'ana@cliente.test' === $renewal->get_billing_email() && 'Guadalajara' === $renewal->get_billing_city() && 7 === $renewal->get_customer_id(), 'Con los datos de facturación y el cliente del pedido original' );
$mail = digi_mails_to( 'ana@cliente.test' );
check( 1 === count( $mail ) && 'Tu hosting vence en 30 días: renueva.com' === $mail[0]['subject'] && false !== strpos( $mail[0]['body'], esc_url( $renewal->get_checkout_payment_url() ) ) && false !== strpos( $mail[0]['body'], 'se suspende 7 días después' ), 'El cliente recibe el aviso de 30 días con el enlace de pago de WooCommerce y los días de gracia' );
check( false !== strpos( digi_notes( $renewal ), 'aviso de vencimiento (30 días) enviado' ), 'El pedido de renovación anota el aviso' );
$P::daily( $expires - 28 * DAY_IN_SECONDS ); $P::daily( $expires - 20 * DAY_IN_SECONDS );
check( 1 === count( $GLOBALS['mails'] ) && $renewal->get_id() === (int) $P::svc( $s, 'renewal_order' ), 'La revisión diaria no repite el aviso ni crea otro pedido' );

// ── 2. 7 días antes ─────────────────────────────────────────────────────────────────────────────────────────
$P::daily( $expires - 6 * DAY_IN_SECONDS );
$mail = digi_mails_to( 'ana@cliente.test' );
check( 2 === count( $mail ) && 'Tu hosting vence en 7 días: renueva.com' === $mail[1]['subject'] && false !== strpos( $mail[1]['body'], esc_url( $renewal->get_checkout_payment_url() ) ), 'A 6 días, segundo aviso con el mismo enlace de pago' );
$P::daily( $expires - 2 * DAY_IN_SECONDS );
check( 2 === count( $GLOBALS['mails'] ), 'Sin más avisos hasta el vencimiento' );

// ── 3. Gracia y suspensión ──────────────────────────────────────────────────────────────────────────────────
$P::daily( $expires + 6 * DAY_IN_SECONDS );
check( ! $GLOBALS['queue'] && 'active' === $P::svc( $s, 'status' ), 'Dentro de los 7 días de gracia no se suspende' );
check( count( $GLOBALS['webuzo']['calls'] ) + count( $GLOBALS['nc']['calls'] ) === $calls, 'Los avisos no llaman a ninguna API' );
$P::daily( $expires + 8 * DAY_IN_SECONDS );
check( 1 === count( $GLOBALS['queue'] ) && 'digitalisimo_hosting_suspend' === $GLOBALS['queue'][0]['hook'], 'Pasada la gracia se encola la suspensión' );
digi_run_queue();
check( 1 === digi_webuzo_count( 'users', 'suspend', 'renueva' ) && '1' === (string) end( $GLOBALS['webuzo']['calls'] )['fields']['skip'], 'Webuzo recibe act=users con suspend=<usuario> y skip=1 (https://webuzo.com/docs/api/suspend-user/)' );
check( 'suspended' === $P::svc( $s, 'status' ) && 'suspended' === $GLOBALS['webuzo']['users']['renueva']['status'], 'El servicio y la cuenta quedan suspendidos' );
$mail = digi_mails_to( 'ana@cliente.test' );
check( 3 === count( $mail ) && 'Tu hosting está suspendido: renueva.com' === $mail[2]['subject'] && false !== strpos( $mail[2]['body'], 'Pagar la renovación' ), 'El cliente recibe el aviso de suspensión con el enlace de pago' );
check( false !== strpos( digi_notes( $renewal ), 'cuenta «renueva» suspendida en Webuzo' ), 'El pedido de renovación anota la suspensión' );
$P::daily( $expires + 9 * DAY_IN_SECONDS ); digi_run_queue();
check( 1 === digi_webuzo_count( 'users', 'suspend', 'renueva' ), 'Una cuenta suspendida no se vuelve a suspender' );

// ── 4. Pago: renovar dominio, reactivar y un año más ───────────────────────────────────────────────────────
$domain_before = $GLOBALS['nc']['domains']['renueva.com'];
$GLOBALS['mails'] = array();
$renewal->update_status( 'processing' );
check( 1 === count( $GLOBALS['queue'] ) && 'digitalisimo_hosting_renew' === $GLOBALS['queue'][0]['hook'], 'Al pagarse la renovación se encola, no se aprovisiona otra cuenta' );
digi_run_queue();
$renew_calls = array_slice( array_column( $GLOBALS['nc']['calls'], 'cmd' ), -4 );
check( array( 'namecheap.domains.getInfo', 'namecheap.users.getPricing', 'namecheap.users.getBalances', 'namecheap.domains.renew' ) === $renew_calls, 'Namecheap: ¿es nuestro?, precio de renovación, saldo y renovación' );
check( 'RENEW' === $GLOBALS['nc']['calls'][ count( $GLOBALS['nc']['calls'] ) - 3 ]['fields']['ActionName'] && 'renueva.com' === end( $GLOBALS['nc']['calls'] )['fields']['DomainName'] && 1 === (int) end( $GLOBALS['nc']['calls'] )['fields']['Years'], 'Precio de RENEW y renovación de un año' );
check( $GLOBALS['nc']['domains']['renueva.com'] > $domain_before, 'El dominio queda renovado en Namecheap' );
check( 1 === digi_webuzo_count( 'users', 'unsuspend', 'renueva' ) && '' === $GLOBALS['webuzo']['users']['renueva']['status'], 'La cuenta se reactiva en Webuzo (act=users con unsuspend, https://webuzo.com/docs/api/unsuspend-user/)' );
check( 0 === digi_webuzo_count( 'add_user' ) - 1, 'No se crea otra cuenta' );
check( 'active' === $P::svc( $s, 'status' ) && strtotime( '+1 year', $expires ) === (int) $P::svc( $s, 'expires' ) && ! $P::svc( $s, 'renewal_order' ), 'El servicio vuelve a activo con un año más desde el vencimiento anterior' );
check( 'completed' === $renewal->get_status() && false !== strpos( digi_notes( $renewal ), 'renovado en Namecheap un año' ) && false !== strpos( digi_notes( $renewal ), 'reactivada en Webuzo' ), 'El pedido de renovación se completa con sus notas' );
$mail = digi_mails_to( 'ana@cliente.test' );
check( 1 === count( $mail ) && 'Tu hosting quedó renovado: renueva.com' === $mail[0]['subject'], 'El cliente recibe la confirmación de la renovación' );
$calls = count( $GLOBALS['webuzo']['calls'] ) + count( $GLOBALS['nc']['calls'] );
$P::run_renew( $renewal->get_id() ); $renewal->update_status( 'completed' ); digi_run_queue();
check( count( $GLOBALS['webuzo']['calls'] ) + count( $GLOBALS['nc']['calls'] ) === $calls && strtotime( '+1 year', $expires ) === (int) $P::svc( $s, 'expires' ), 'Correr otra vez la renovación no cobra ni extiende de nuevo' );
// El ciclo siguiente empieza limpio: a 29 días del nuevo vencimiento, nuevo pedido y nuevo aviso.
$GLOBALS['mails'] = array();
$P::daily( (int) $P::svc( $s, 'expires' ) - 29 * DAY_IN_SECONDS );
check( $P::svc( $s, 'renewal_order' ) && (int) $P::svc( $s, 'renewal_order' ) !== $renewal->get_id() && 1 === count( $GLOBALS['mails'] ), 'El año siguiente se crea otro pedido de renovación y otro aviso' );

// ── 5. Saldo insuficiente y renovación sin respuesta ───────────────────────────────────────────────────────
list( , $s2 ) = digi_provisioned( 'pobre.com' );
$exp2 = (int) $P::svc( $s2, 'expires' );
$P::daily( $exp2 - 29 * DAY_IN_SECONDS );
$r2 = wc_get_order( (int) $P::svc( $s2, 'renewal_order' ) );
$GLOBALS['nc']['balance'] = 0.02; $GLOBALS['mails'] = array();
$renews = digi_nc_count( 'namecheap.domains.renew' );
$r2->update_status( 'processing' ); digi_run_queue();
check( $renews === digi_nc_count( 'namecheap.domains.renew' ) && false !== strpos( digi_notes( $r2 ), 'Saldo insuficiente en Namecheap para renovar pobre.com' ), 'Sin saldo no se renueva y la nota lo dice' );
check( 1 === count( digi_mails_to( 'admin@tienda.test' ) ) && $exp2 === (int) $P::svc( $s2, 'expires' ) && ! $GLOBALS['queue'], 'Aviso al administrador, sin extender el vencimiento y sin reintentos automáticos' );
$GLOBALS['nc']['balance'] = 30;
check( true === $P::retry( $s2 ), '«Reintentar» encola la renovación pagada' );
digi_run_queue();
check( $renews + 1 === digi_nc_count( 'namecheap.domains.renew' ) && strtotime( '+1 year', $exp2 ) === (int) $P::svc( $s2, 'expires' ) && 'completed' === $r2->get_status(), 'Con saldo, la renovación termina' );

$GLOBALS['nc']['balance'] = 100;
list( , $s3 ) = digi_provisioned( 'sinrespuesta.com' );
$exp3 = (int) $P::svc( $s3, 'expires' );
$P::daily( $exp3 - 29 * DAY_IN_SECONDS );
$r3 = wc_get_order( (int) $P::svc( $s3, 'renewal_order' ) );
$GLOBALS['nc']['timeout']['namecheap.domains.renew'] = 1;
$renews = digi_nc_count( 'namecheap.domains.renew' );
$r3->update_status( 'processing' ); digi_run_queue( true, 1 );
$GLOBALS['nc']['domains']['sinrespuesta.com'] = strtotime( '+1 year', $GLOBALS['nc']['domains']['sinrespuesta.com'] ); // sí llegó
digi_run_queue();
check( $renews + 1 === digi_nc_count( 'namecheap.domains.renew' ) && false !== strpos( digi_notes( $r3 ), 'ya estaba renovado (intento anterior); no se cobró otra vez' ) && 'completed' === $r3->get_status(), 'Una renovación que llegó sin respuesta no se cobra dos veces' );

// ── 6. Sólo dominio ─────────────────────────────────────────────────────────────────────────────────────────
list( , $s4 ) = digi_provisioned( 'solo.com', 0 );
$exp4 = (int) $P::svc( $s4, 'expires' );
$P::daily( $exp4 + 9 * DAY_IN_SECONDS );
check( 'expired' === $P::svc( $s4, 'status' ) && ! $GLOBALS['queue'], 'Un dominio sin hosting vencido y sin pago queda «vencido», sin suspender nada' );
// Los días de gracia se configuran.
update_option( 'digitalisimo_hosting_options', array( 'grace_days' => 15 ) + get_option( 'digitalisimo_hosting_options' ) );
list( , $s5 ) = digi_provisioned( 'gracia.com' );
$exp5 = (int) $P::svc( $s5, 'expires' );
$P::daily( $exp5 + 10 * DAY_IN_SECONDS );
check( ! $GLOBALS['queue'] && 'active' === $P::svc( $s5, 'status' ), 'Con 15 días de gracia, a los 10 días no se suspende' );
$P::daily( $exp5 + 16 * DAY_IN_SECONDS );
check( 1 === count( $GLOBALS['queue'] ), 'A los 16 días sí' );

// ── 7. Hallazgos de la revisión adversarial ────────────────────────────────────────────────────────────────
update_option( 'digitalisimo_hosting_options', array( 'grace_days' => 7 ) + get_option( 'digitalisimo_hosting_options' ) );
$GLOBALS['nc']['balance'] = 200; $GLOBALS['queue'] = array();
// La suspensión falla por red; el cliente paga antes del reintento: no se suspende.
list( , $s6 ) = digi_provisioned( 'pagatarde.com' );
$exp6 = (int) $P::svc( $s6, 'expires' );
$P::daily( $exp6 - 29 * DAY_IN_SECONDS );
$r6 = wc_get_order( (int) $P::svc( $s6, 'renewal_order' ) );
$GLOBALS['webuzo']['timeout'] = 1;
// Lo que decide la revisión diaria pasada la gracia (sólo para este servicio).
$P::svc_set( $s6, array( 'suspend_reason' => 'auto', 'suspend_for' => $exp6 ) ); $GLOBALS['queue'] = array();
$P::enqueue( $P::HOOK_SUSPEND, $s6 ); digi_run_queue( true, 1 );
check( 'active' === $P::svc( $s6, 'status' ) && 1 === count( $GLOBALS['queue'] ), 'La suspensión que falló por red queda con su reintento' );
$suspends = digi_webuzo_count( 'users', 'suspend', 'pagatarde' );
// Orden real de Action Scheduler: la renovación pagada (asíncrona) corre ANTES que el reintento programado a futuro.
$retry6 = $GLOBALS['queue']; $GLOBALS['queue'] = array();
$r6->update_status( 'processing' ); digi_run_queue();
check( 'active' === $P::svc( $s6, 'status' ) && (int) $P::svc( $s6, 'expires' ) > $exp6, 'La renovación pagada corre primero' );
$GLOBALS['queue'] = $retry6; digi_run_queue();
check( $suspends === digi_webuzo_count( 'users', 'suspend', 'pagatarde' ) && 'active' === $P::svc( $s6, 'status' ) && '' === $GLOBALS['webuzo']['users']['pagatarde']['status'], 'Después corre el reintento de la suspensión y ya no suspende a quien pagó' );
// Suspendida a mano (p. ej. por abuso): el pago la renueva pero no la reactiva.
list( , $s7 ) = digi_provisioned( 'abuso.com' );
$exp7 = (int) $P::svc( $s7, 'expires' );
$P::daily( $exp7 - 29 * DAY_IN_SECONDS );
$r7 = wc_get_order( (int) $P::svc( $s7, 'renewal_order' ) );
$P::svc_set( $s7, array( 'suspend_reason' => 'manual' ) ); $P::run_suspend( $s7 );
check( 'suspended' === $P::svc( $s7, 'status' ), 'Suspensión manual' );
$r7->update_status( 'processing' ); digi_run_queue();
check( 0 === digi_webuzo_count( 'users', 'unsuspend', 'abuso' ) && 'suspended' === $P::svc( $s7, 'status' ) && false !== strpos( implode( "\n", $r7->notes ), 'suspendida a mano; el pago no la reactiva' ) && (int) $P::svc( $s7, 'expires' ) > $exp7, 'Pagar no reactiva una cuenta suspendida a mano (sí la renueva)' );
// Un pedido de renovación sin la línea del dominio no renueva el dominio.
list( , $s8 ) = digi_provisioned( 'sinlinea.com' );
$P::daily( (int) $P::svc( $s8, 'expires' ) - 29 * DAY_IN_SECONDS );
$r8 = wc_get_order( (int) $P::svc( $s8, 'renewal_order' ) );
array_pop( $r8->items );
$renews = digi_nc_count( 'namecheap.domains.renew' );
$r8->update_status( 'processing' ); digi_run_queue();
check( $renews === digi_nc_count( 'namecheap.domains.renew' ) && false !== strpos( implode( "\n", $r8->notes ), 'no trae la línea del dominio' ), 'Sin la línea del dominio en la renovación, no se renueva' );
// Una tarea cortada a la mitad (sin nada en cola, servicio pendiente hace más de una hora): la revisión diaria la retoma y avisa.
$o9 = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'cortado.com' ) ) ) );
$o9->update_status( 'processing' ); $GLOBALS['queue'] = array(); // PHP la mató antes de terminar
$GLOBALS['webuzo']['timeout'] = 1; $P::run_provision( $o9->get_id() ); $GLOBALS['queue'] = array();
$s9 = (int) $o9->get_meta( '_digitalisimo_service' );
$P::svc_set( $s9, array( 'created' => time() - 2 * HOUR_IN_SECONDS ) );
$GLOBALS['mails'] = array();
$P::daily();
check( 1 === count( $GLOBALS['queue'] ) && 'digitalisimo_hosting_provision' === $GLOBALS['queue'][0]['hook'] && 1 === count( digi_mails_to( 'admin@tienda.test' ) ), 'Un aprovisionamiento a medias sin tarea en cola se retoma y se avisa' );
digi_run_queue();
check( 'completed' === $o9->get_status() && 1 === digi_webuzo_count( 'add_user', 'domain', 'cortado.com' ), 'Y termina sin duplicar' );
$P::daily();
check( ! $GLOBALS['queue'], 'Uno entregado no se vuelve a retomar' );

echo "Hosting, renovación: {$GLOBALS['checks']} comprobaciones correctas.\n";
