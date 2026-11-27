<?php
// Pago manual (pedido del propietario del 2026-11-27: «permite pago manual solo en admin para si me pagan por otro lado o
// en persona, yo habilitarles el año y no se les suspenda»), con Webuzo, Namecheap y Dynadot simulados:
//   1. un administrador registra el pago de un servicio en gracia: el pedido de renovación pendiente queda pagado, vence
//      un periodo después, se cancelan los avisos y la suspensión, y la revisión diaria ya no lo suspende;
//   2. un servicio ya suspendido por falta de pago se reactiva en Webuzo;
//   3. el doble envío (la misma clave del formulario o, por MCP, los mismos datos) extiende una sola vez;
//   4. quien no administra no ve el botón ni puede llamar a la acción (pantalla, API y MCP);
//   5. «Renovar también el dominio» lo renueva con el registrador que lo tiene (Dynadot para el .mx, aunque Namecheap sea
//      el predeterminado), una sola vez; sin saldo, el pago queda registrado y se avisa;
//   6. la bitácora del servicio, la de pagos manuales y la de escrituras del MCP quedan escritas; el gancho para la
//      suscripción automática se dispara con el vencimiento nuevo.
// Uso: php tests/hosting-manual-payment.php
namespace {
	require __DIR__ . '/fixtures/hosting-wp.php';
	require __DIR__ . '/fixtures/hosting-dynadot.php';
	$P = 'Digitalisimo_Hosting_Provisioning'; $S = 'Digitalisimo_Hosting_Services_Page';
	if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
	function home_url( $p = '' ) { return 'https://tienda.test' . $p; }
	function mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
	function near( $a, $b ) { return abs( (int) $a - (int) $b ) <= 120; }
	function provisioned( $domain, $domain_product = 20 ) {
		$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( $domain_product, 'Dominio', 1, array( 'Dominio' => $domain ) ) ), array( 'email' => 'ana@' . $domain ) );
		$o->update_status( 'processing' ); digi_run_queue();
		check( 'completed' === $o->get_status(), "El pedido de $domain se entrega" );
		return (int) $o->get_meta( '_digitalisimo_service' );
	}
	function screen( array $get = array() ) { $_GET = $get; ob_start(); Digitalisimo_Hosting_Services_Page::render(); $_GET = array(); return ob_get_clean(); }
	function act( array $post ) {
		$_POST = $post + array( 'action' => 'digitalisimo_hosting_service', 'op' => 'manual_payment' );
		if ( ! isset( $_POST['_wpnonce'] ) ) $_POST['_wpnonce'] = 'n:' . Digitalisimo_Hosting_Services_Page::nonce_action( 'manual_payment', (int) $post['service'] );
		try { Digitalisimo_Hosting_Services_Page::handle(); return 'sin salida'; }
		catch ( Digi_Test_Redirect $e ) { return 'redirect'; }
		catch ( Digi_Test_Die $e ) { return 'die:' . $e->status; }
		finally { $_POST = array(); }
	}
	$GLOBALS['hooked'] = array();
	add_action( 'digitalisimo_hosting_manual_payment', function ( $id, $r ) { $GLOBALS['hooked'][] = array( $id, $r ); }, 10, 2 );

	digi_servers_reset(); dd_reset();
	digi_store_settings( array( 'grace_days' => 7, 'dynadot_api_key' => DD_KEY, 'registrar_by_tld' => array( '.mx' => 'dynadot' ) ) );
	$GLOBALS['nc']['balance'] = 500;

	// ── 1. En gracia ────────────────────────────────────────────────────────────────────────────────────────────
	$s = provisioned( 'gracia.com' );
	$exp = (int) $P::svc( $s, 'expires' );
	$P::daily( $exp - 29 * DAY_IN_SECONDS ); $P::daily( $exp - 6 * DAY_IN_SECONDS );
	$pending = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
	$P::daily( $exp + 3 * DAY_IN_SECONDS );
	check( $pending && 'pending' === $pending->get_status() && 'active' === $P::svc( $s, 'status' ), 'Preparación: vencido, en gracia, con su renovación sin pagar' );
	$html = screen();
	check( preg_match( '#<tr data-service="' . $s . '">.*?href="https://tienda\.test/wp-admin/admin\.php\?page=digitalisimo-hosting-services&amp;manual=' . $s . '">Registrar pago manual</a>#s', $html ), 'Cada servicio tiene «Registrar pago manual»' );
	$form = screen( array( 'manual' => $s ) );
	check( false !== strpos( $form, '<option value="12" selected>Anual (un año)</option>' ) && false !== strpos( $form, '<option value="1">Mensual (1 mes)</option>' ) && false !== strpos( $form, '<option value="cash">Efectivo</option>' ) && false !== strpos( $form, 'name="amount"' ) && false !== strpos( $form, 'name="note"' ), 'El formulario: periodo (de fábrica el del servicio), medio, monto y nota' );
	check( preg_match( '#<input type="checkbox" name="renew_domain" value="1" checked> <strong>Renovar también el dominio con su registrador</strong>#', $form ) && false !== strpos( $form, 'Costo en Namecheap: 11.46 USD' ) && false !== strpos( $form, 'value="n:digitalisimo_hosting_service_manual_payment_' . $s . '"' ), 'La casilla del dominio sale marcada (vence dentro del periodo), con su costo, y el formulario lleva su nonce' );
	preg_match( '#name="key" value="([a-z0-9]+)"#', $form, $km );
	$GLOBALS['webuzo']['calls'] = array(); $renews = digi_nc_count( 'namecheap.domains.renew' );
	$GLOBALS['real_transients'] = true; // el aviso de la pantalla viaja en un transitorio
	check( 'redirect' === act( array( 'service' => $s, 'months' => '12', 'method' => 'cash', 'amount' => '970', 'note' => 'Pagó en la oficina', 'key' => $km[1] ) ), 'Guardar el formulario' );
	check( 'completed' === $pending->get_status() && $P::svc( $s, 'last_renewal_order' ) == $pending->get_id() && ! $P::svc( $s, 'renewal_order' ), 'La renovación pendiente queda pagada' );
	check( near( (int) $P::svc( $s, 'expires' ), strtotime( '+1 year', $exp ) ) && 'active' === $P::svc( $s, 'status' ) && array() === $P::svc( $s, 'notices' ), 'Vence un periodo (un año) después del vencimiento anterior y los avisos de ese periodo se cancelan' );
	check( $renews === digi_nc_count( 'namecheap.domains.renew' ), 'Sin marcar la casilla, el dominio no se renueva' );
	$GLOBALS['queue'] = array();
	$P::daily( $exp + 8 * DAY_IN_SECONDS ); $P::daily( $exp + 40 * DAY_IN_SECONDS );
	check( ! array_filter( $GLOBALS['queue'], function ( $q ) { return 'digitalisimo_hosting_suspend' === $q['hook']; } ) && 'active' === $P::svc( $s, 'status' ), 'Pasada la gracia ya no se suspende' );
	check( preg_grep( '/Pago manual registrado por admin: efectivo, hosting anual, monto 970\.00 \(Pagó en la oficina\); vence el ' . gmdate( 'Y-m-d', (int) $P::svc( $s, 'expires' ) ) . '/', $pending->notes ) && preg_grep( '/Pago manual registrado por admin: efectivo.*Pedido #' . $pending->get_id() . '/', array_column( (array) $P::svc( $s, 'log' ), 1 ) ), 'El pedido y la bitácora del servicio anotan quién, el medio, el periodo, el monto y la nota' );
	$log = get_option( $P::MANUAL_LOG );
	check( is_array( $log ) && 1 === count( $log ) && 'admin' === $log[0]['user'] && 'cash' === $log[0]['method'] && 12 === $log[0]['months'] && 970.0 === $log[0]['amount'] && $s === $log[0]['service'] && $exp === $log[0]['from'] && $log[0]['t'] > 0, 'La bitácora de pagos manuales: quién, cuándo, servicio, medio, periodo, monto y fechas' );
	check( 1 === count( $GLOBALS['hooked'] ) && $s === $GLOBALS['hooked'][0][0] && (int) $P::svc( $s, 'expires' ) === $GLOBALS['hooked'][0][1]['expires_ts'], 'El gancho para la suscripción automática recibe el vencimiento nuevo' );
	$msg = screen( array( 'done' => 'manual' ) );
	check( false !== strpos( $msg, 'notice-success' ) && false !== strpos( $msg, 'Pago manual registrado por admin' ), 'La pantalla confirma el pago' );
	$GLOBALS['real_transients'] = false; $GLOBALS['transients'] = array();

	// ── 3. Doble envío ──────────────────────────────────────────────────────────────────────────────────────────
	$after = (int) $P::svc( $s, 'expires' );
	act( array( 'service' => $s, 'months' => '12', 'method' => 'cash', 'amount' => '970', 'key' => $km[1] ) );
	check( $after === (int) $P::svc( $s, 'expires' ) && 1 === count( get_option( $P::MANUAL_LOG ) ) && 1 === count( $GLOBALS['hooked'] ), 'El doble envío del formulario (misma clave) no extiende dos veces' );
	$r1 = $P::manual_payment( $s, array( 'months' => 1, 'method' => 'transfer' ) );
	$r2 = $P::manual_payment( $s, array( 'months' => 1, 'method' => 'transfer' ) );
	check( ! is_wp_error( $r1 ) && false === $r1['duplicate'] && true === $r2['duplicate'] && near( (int) $P::svc( $s, 'expires' ), strtotime( '+1 month', $after ) ), 'Sin clave, los mismos datos dos veces seguidas extienden una sola vez (un mes)' );
	$e = $P::manual_payment( $s, array( 'months' => 5, 'method' => 'cash' ) );
	check( is_wp_error( $e ) && 'digitalisimo_hosting_invalid_value' === $e->code, 'Un periodo que no existe no se registra' );
	check( is_wp_error( $P::manual_payment( $s, array( 'method' => 'tarjeta' ) ) ) && is_wp_error( $P::manual_payment( $s, array( 'method' => 'cash', 'amount' => 'mucho' ) ) ), 'Un medio o un monto inválidos no se registran' );

	// ── 4. Sólo administradores ─────────────────────────────────────────────────────────────────────────────────
	$GLOBALS['caps'] = array( 'edit_posts', 'read' );
	$before = (int) $P::svc( $s, 'expires' );
	$e = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'key' => 'otra' ) );
	check( is_wp_error( $e ) && 'digitalisimo_hosting_forbidden' === $e->code && $before === (int) $P::svc( $s, 'expires' ), 'Un no administrador recibe rechazo y no cambia nada' );
	check( 'die:403' === act( array( 'service' => $s, 'months' => '12', 'method' => 'cash', 'key' => 'otra2' ) ) && $before === (int) $P::svc( $s, 'expires' ), 'La acción del escritorio sin permiso: 403' );
	check( '' === screen() && '' === screen( array( 'manual' => $s ) ), 'Sin permiso no se pinta la pantalla ni el formulario' );
	unset( $GLOBALS['caps'] );
	check( 'die:403' === act( array( 'service' => $s, 'months' => '12', 'method' => 'cash', 'key' => 'otra3', '_wpnonce' => 'n:otra-cosa' ) ) && $before === (int) $P::svc( $s, 'expires' ), 'Sin el nonce de ese servicio no se registra nada' );

	// ── 2. Suspendido por falta de pago ─────────────────────────────────────────────────────────────────────────
	$s2 = provisioned( 'suspendido.com' );
	$exp2 = (int) $P::svc( $s2, 'expires' );
	$P::daily( $exp2 - 29 * DAY_IN_SECONDS ); $P::daily( $exp2 + 8 * DAY_IN_SECONDS ); digi_run_queue( true, 200 );
	check( 'suspended' === $P::svc( $s2, 'status' ) && 'suspended' === $GLOBALS['webuzo']['users']['suspendido']['status'], 'Preparación: suspendido por falta de pago' );
	$r = $P::manual_payment( $s2, array( 'months' => 6, 'method' => 'deposit', 'key' => 'dep1' ) );
	digi_run_queue( true, 200 );
	check( ! is_wp_error( $r ) && 'active' === $P::svc( $s2, 'status' ) && '' === $GLOBALS['webuzo']['users']['suspendido']['status'] && near( (int) $P::svc( $s2, 'expires' ), strtotime( '+6 month', $exp2 ) ), 'Un servicio suspendido se reactiva en Webuzo y vence seis meses después' );
	$GLOBALS['queue'] = array();
	$P::daily( $exp2 + 20 * DAY_IN_SECONDS );
	check( ! array_filter( $GLOBALS['queue'], function ( $q ) { return 'digitalisimo_hosting_suspend' === $q['hook']; } ), 'Y la revisión diaria ya no lo suspende' );

	// ── 5. Renovar el dominio con su registrador (Dynadot) ──────────────────────────────────────────────────────
	$s3 = provisioned( 'mitienda.mx', 21 );
	check( 'dynadot' === Digitalisimo_Hosting_Registrars::of_service( $s3 ) && isset( $GLOBALS['dd']['domains']['mitienda.mx'] ), 'Preparación: el .mx quedó en Dynadot' );
	$form = screen( array( 'manual' => $s3 ) );
	check( false !== strpos( $form, 'Costo en Dynadot: 25.00 USD (saldo ' ), 'El formulario dice el costo en el registrador que tiene el dominio' );
	$ddue = $P::domain_due( $s3 ); $nc_renews = digi_nc_count( 'namecheap.domains.renew' );
	$r = $P::manual_payment( $s3, array( 'months' => 12, 'method' => 'transfer', 'renew_domain' => true, 'key' => 'dom1' ) );
	check( ! is_wp_error( $r ) && 'renewed' === $r['domain_renewal'] && 1 === dd_count( 'renew' ) && $nc_renews === digi_nc_count( 'namecheap.domains.renew' ), 'Con la casilla, el dominio se renueva en Dynadot (el registrador que lo tiene), no en Namecheap' );
	check( near( $P::domain_due( $s3 ), strtotime( '+1 year', $ddue ) ), 'El vencimiento del dominio suma un año' );
	$P::manual_payment( $s3, array( 'months' => 12, 'method' => 'transfer', 'renew_domain' => true, 'key' => 'dom1' ) );
	check( 1 === dd_count( 'renew' ), 'Nunca dos veces' );
	$GLOBALS['dd']['balance'] = 1.0; $GLOBALS['mails'] = array();
	$before = (int) $P::svc( $s3, 'expires' );
	$form = screen( array( 'manual' => $s3 ) );
	check( false !== strpos( $form, 'no alcanza para renovar el dominio' ), 'Sin saldo, el formulario avisa antes de guardar' );
	$r = $P::manual_payment( $s3, array( 'months' => 12, 'method' => 'cash', 'renew_domain' => true, 'key' => 'dom2' ) );
	check( ! is_wp_error( $r ) && 'failed' === $r['domain_renewal'] && near( (int) $P::svc( $s3, 'expires' ), strtotime( '+1 year', $before ) ) && 1 === dd_count( 'renew' ), 'Sin saldo: el pago queda registrado y el dominio no se renueva' );
	check( array_filter( $GLOBALS['mails'], function ( $m ) { return false !== strpos( $m['subject'], 'dominio sin renovar tras un pago manual' ); } ), 'Y se avisa al administrador' );

	// ── 6. MCP: sólo administradores, anotado como escritura ────────────────────────────────────────────────────
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-hosting/mcp/class-tools.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-hosting/mcp/class-executor.php';
}
namespace {
	use Digitalisimo\Elements\Hosting\Mcp\Executor;
	use Digitalisimo\Elements\Hosting\Mcp\Tools;
	check( in_array( 'manual-payment', Tools::ACTIONS['settings'], true ) && Tools::is_write( 'manual-payment' ) && true === Tools::definition( 'settings' )['meta']['annotations']['destructive'] && array( 'manage_options' ) === Tools::capabilities( 'settings' ), 'MCP: la acción «manual-payment» es de la herramienta de ajustes (sólo administradores) y está anotada como escritura' );
	$GLOBALS['caps'] = array( 'manage_woocommerce' );
	$e = Executor::run( 'settings', array( 'action' => 'manual-payment', 'service' => $s, 'method' => 'cash', 'months' => 12 ) );
	check( $e instanceof WP_Error && 'denied' === get_option( Executor::LOG )[0]['result'], 'MCP: sin manage_options se rechaza y queda en la bitácora' );
	unset( $GLOBALS['caps'] );
	$before = (int) $P::svc( $s, 'expires' );
	$r = Executor::run( 'settings', array( 'action' => 'manual-payment', 'service' => $s, 'method' => 'deposit', 'months' => 3, 'amount' => 450, 'request_id' => 'mcp-1' ) );
	$entry = get_option( Executor::LOG )[0];
	check( is_array( $r ) && near( (int) $P::svc( $s, 'expires' ), strtotime( '+3 month', $before ) ) && 'ok' === $entry['result'] && array( 'manual_payment', 'method_deposit', 'months_3', 'amount_450_00', 'domain_skipped' ) === $entry['keys'] && $s === $entry['product'], 'MCP: registra el pago y la bitácora de escrituras anota medio, periodo y monto' );
	$r = Executor::run( 'settings', array( 'action' => 'manual-payment', 'service' => $s, 'method' => 'deposit', 'months' => 3, 'amount' => 450, 'request_id' => 'mcp-1' ) );
	check( true === $r['duplicate'] && near( (int) $P::svc( $s, 'expires' ), strtotime( '+3 month', $before ) ) && 'duplicate' === get_option( Executor::LOG )[0]['result'], 'MCP: la misma clave no extiende dos veces' );

	echo "Hosting, pago manual: {$GLOBALS['checks']} comprobaciones correctas.\n";
}
