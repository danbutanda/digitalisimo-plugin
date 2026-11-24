<?php
// Venta automática de DIGITALÍSIMO Hosting (pedido del propietario del 2026-11-23), sin WordPress y con Webuzo y
// Namecheap simulados por pre_http_request (tests/fixtures/hosting-wp.php; nada sale a internet):
//   1. pedido pagado → cuenta de Webuzo con el paquete del producto, dominio registrado a nombre del cliente y apuntado
//      a los nameservers propios, correo con los accesos; notas y meta del pedido sin la contraseña; pedido completado;
//   2. idempotencia: volver a pagar o a correr no repite nada; una cuenta o un registro que llegaron sin que se viera la
//      respuesta se adoptan, no se duplican;
//   3. falla de red → reintento con espera; agotados los reintentos → en espera y aviso;
//   4. clave de Webuzo rechazada → NINGÚN reintento, interruptor global (ni otros pedidos ni suspensiones llaman),
//      aviso por correo y en el escritorio; guardar una clave nueva lo levanta y retoma lo detenido;
//   5. saldo insuficiente en Namecheap → no registra, en espera con nota y aviso; con saldo, «Reintentar» termina sin
//      crear otra cuenta;
//   6. lo que no se resuelve solo (dominio de otra extensión, datos del titular, varios planes) no llama a nadie;
//   7. correo desactivado, venta automática apagada y clasificación de respuestas.
require __DIR__ . '/fixtures/hosting-wp.php';
$P = 'Digitalisimo_Hosting_Provisioning';

function digi_reset_store( array $extra = array() ) {
	digi_servers_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array();
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra );
}
function digi_buy( $domain = 'mitienda.com', $hosting = 14693, $domain_product = 20, array $billing = array() ) {
	$items = array();
	if ( $hosting ) $items[] = new Digi_Test_Item( $hosting, 'Web Hosting' );
	if ( $domain_product ) $items[] = new Digi_Test_Item( $domain_product, 'Dominio', 1, array( 'Dominio' => $domain ) );
	elseif ( $hosting ) $items[0]->meta['Dominio'] = $domain;
	return digi_order( $items, $billing );
}
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function digi_notes( $order ) { return implode( "\n", $order->notes ); }

// ── 1. Compra pagada ────────────────────────────────────────────────────────────────────────────────────────
digi_reset_store();
$order = digi_buy();
$order->update_status( 'processing' );
check( 1 === count( $GLOBALS['queue'] ) && 'digitalisimo_hosting_provision' === $GLOBALS['queue'][0]['hook'] && 'digitalisimo-hosting' === $GLOBALS['queue'][0]['group'], 'Al pagarse el pedido se encola el aprovisionamiento en Action Scheduler (no corre en la petición)' );
check( ! $GLOBALS['webuzo']['calls'] && ! $GLOBALS['nc']['calls'], 'Pagar el pedido no llama a ninguna API en la misma petición' );
digi_run_queue();
$create = array_values( array_filter( $GLOBALS['webuzo']['calls'], function ( $c ) { return 'add_user' === $c['act']; } ) );
check( 1 === count( $create ), 'Se crea una sola cuenta en Webuzo' );
$f = $create[0]['fields'];
check( 'digitalisimo_starter' === $f['plan'] && 'mitienda.com' === $f['domain'] && 'mitienda' === $f['user'] && 'ana@cliente.test' === $f['email'] && '1' === (string) $f['create_user'], 'La cuenta lleva el paquete del producto, el dominio, el usuario derivado del dominio y el correo del cliente (act=add_user, https://webuzo.com/docs/api/add-user/)' );
$password = $f['user_passwd'];
check( 18 === strlen( $password ) && $password === $f['cnf_user_passwd'] && preg_match( '/[a-z]/', $password ) && preg_match( '/[A-Z]/', $password ) && preg_match( '/\d/', $password ) && preg_match( '/[^A-Za-z0-9]/', $password ), 'La contraseña es fuerte (18 caracteres de cuatro clases) y se confirma' );
check( 'root' === $f['apiuser'] && false === strpos( $create[0]['url'], 'CLAVE' ) && 'https://webuzo.test:2005/index.php?api=json&act=add_user' === $create[0]['url'], 'apiuser/apikey viajan en el cuerpo del POST, nunca en la URL' );
check( array( 'users', 'add_user' ) === array_column( $GLOBALS['webuzo']['calls'], 'act' ), 'Antes de crear se consulta la lista de cuentas (idempotencia)' );
check( array( 'namecheap.domains.getInfo', 'namecheap.users.getPricing', 'namecheap.users.getBalances', 'namecheap.domains.create', 'namecheap.domains.dns.setCustom' ) === array_column( $GLOBALS['nc']['calls'], 'cmd' ), 'Namecheap: ¿ya es nuestro?, precio, saldo, registro y nameservers, en ese orden' );
$reg = $GLOBALS['nc']['calls'][3]['fields'];
foreach ( array( 'Registrant', 'Tech', 'Admin', 'AuxBilling' ) as $t ) {
	check( 'Ana' === $reg[ $t . 'FirstName' ] && 'López' === $reg[ $t . 'LastName' ] && 'Av. Juárez 100' === $reg[ $t . 'Address1' ] && 'Guadalajara' === $reg[ $t . 'City' ] && 'Jalisco' === $reg[ $t . 'StateProvince' ] && '44100' === $reg[ $t . 'PostalCode' ] && 'MX' === $reg[ $t . 'Country' ] && '+52.3312345678' === $reg[ $t . 'Phone' ] && 'ana@cliente.test' === $reg[ $t . 'EmailAddress' ], "Contacto $t con los datos de facturación del cliente (estado por su nombre y teléfono +52.)" );
}
check( 'mitienda.com' === $reg['DomainName'] && 1 === (int) $reg['Years'] && ! isset( $reg['RegistrantOrganizationName'] ), 'Registro de un año del dominio del pedido, sin campos vacíos' );
$dns = $GLOBALS['nc']['calls'][4]['fields'];
check( 'mitienda' === $dns['SLD'] && 'com' === $dns['TLD'] && 'ns1.digitalisimo.website,ns2.digitalisimo.website' === $dns['Nameservers'], 'El dominio apunta a ns1 y ns2.digitalisimo.website (namecheap.domains.dns.setCustom)' );
check( 'CLAVE-NAMECHEAP' === $GLOBALS['nc']['calls'][0]['key'] && 'https://api.namecheap.com/xml.response' === $GLOBALS['http'][2]['url'], 'Namecheap recibe la clave en el cuerpo del POST' );
$customer = digi_mails_to( 'ana@cliente.test' );
check( 1 === count( $customer ) && 'Tus accesos de hosting para mitienda.com' === $customer[0]['subject'], 'El cliente recibe el correo «Accesos de hosting»' );
check( false !== strpos( $customer[0]['body'], $password ) && false !== strpos( $customer[0]['body'], 'https://webuzo.test:2003/' ) && false !== strpos( $customer[0]['body'], '<code>mitienda</code>' ) && false !== strpos( $customer[0]['body'], 'ns1.digitalisimo.website' ), 'El correo trae la contraseña, el panel en el puerto 2003, el usuario y los nameservers' );
check( false === strpos( digi_notes( $order ), $password ) && false === strpos( json_encode( $order->meta ), $password ), 'Ni las notas ni la meta del pedido guardan la contraseña' );
check( false !== strpos( digi_notes( $order ), 'cuenta de Webuzo creada · usuario «mitienda», paquete digitalisimo_starter, panel https://webuzo.test:2003/' ) && false !== strpos( digi_notes( $order ), 'dominio mitienda.com registrado en Namecheap a nombre de Ana López' ) && false !== strpos( digi_notes( $order ), 'correo de accesos enviado a ana@cliente.test' ), 'Notas privadas con la cuenta, el registro y el correo' );
check( 'completed' === $order->get_status(), 'El pedido queda completado' );
$service = (int) $order->get_meta( '_digitalisimo_service' );
check( $service && 'active' === $P::svc( $service, 'status' ) && 'mitienda' === $P::svc( $service, 'webuzo_user' ) && 'digitalisimo_starter' === $P::svc( $service, 'plan' ) && abs( (int) $P::svc( $service, 'expires' ) - strtotime( '+1 year' ) ) < 60, 'El servicio queda activo con su cuenta, su plan y vencimiento en un año' );
$stored = (string) $P::svc( $service, 'pass' );
check( '' !== $stored && false === strpos( $stored, $password ) && $password === $P::decrypt( $stored ), 'La contraseña sólo queda cifrada (clave derivada de las sales) en el servicio, para «Reenviar accesos»' );
check( ! digi_mails_to( 'admin@tienda.test' ), 'Sin problemas, el administrador no recibe avisos' );
check( isset( $GLOBALS['cron']['digitalisimo_hosting_daily'] ), 'La revisión diaria de vencimientos queda programada' );

// ── 2. Idempotencia ─────────────────────────────────────────────────────────────────────────────────────────
$calls = count( $GLOBALS['webuzo']['calls'] ) + count( $GLOBALS['nc']['calls'] );
$order->update_status( 'processing' ); $order->update_status( 'completed' );
check( ! $GLOBALS['queue'], 'Un pedido ya entregado no vuelve a encolarse al cambiar de estado' );
$P::run_provision( $order->get_id() );
check( count( $GLOBALS['webuzo']['calls'] ) + count( $GLOBALS['nc']['calls'] ) === $calls && 1 === count( digi_mails_to( 'ana@cliente.test' ) ), 'Correr otra vez un pedido entregado no llama a nadie ni reenvía el correo' );

// La cuenta se creó pero la respuesta se perdió (tiempo agotado): el reintento la encuentra y no crea otra.
digi_reset_store();
$GLOBALS['webuzo']['mode'] = 'lost_create';
$order = digi_buy( 'perdida.com' );
$order->update_status( 'processing' );
digi_run_queue( true, 1 );
check( 1 === count( $GLOBALS['queue'] ) && $GLOBALS['queue'][0]['at'] > time() + 200 && 'processing' === $order->get_status(), 'Tiempo agotado al crear: reintento programado (5 min), el pedido sigue procesando' );
check( false !== strpos( digi_notes( $order ), 'Reintento automático 1 de 4' ), 'La nota dice que se reintentará' );
digi_run_queue();
check( 1 === digi_webuzo_count( 'add_user' ) && 'completed' === $order->get_status(), 'El reintento encuentra la cuenta creada y no crea otra' );
check( false !== strpos( digi_notes( $order ), 'encontrada (la había creado un intento anterior de este pedido)' ), 'La nota explica que la cuenta ya existía' );
$customer = digi_mails_to( 'ana@cliente.test' );
check( 1 === count( $customer ) && false !== strpos( $customer[0]['body'], $GLOBALS['webuzo']['users']['perdida']['pass'] ), 'El correo lleva la misma contraseña que recibió Webuzo en el primer intento' );

// El dominio se registró pero no se vio la respuesta: el reintento lo ve en la cuenta y no lo registra otra vez.
digi_reset_store();
$GLOBALS['nc']['timeout']['namecheap.domains.create'] = 1;
$order = digi_buy( 'registrado.mx', 14693, 21 );
$order->update_status( 'processing' );
digi_run_queue( true, 1 );
$GLOBALS['nc']['domains']['registrado.mx'] = strtotime( '+1 year' ); // sí llegó a Namecheap
digi_run_queue();
check( 1 === digi_nc_count( 'namecheap.domains.create' ) && 'completed' === $order->get_status() && false !== strpos( digi_notes( $order ), 'ya estaba en la cuenta de Namecheap; no se registró otra vez' ), 'Un registro que llegó sin respuesta no se repite' );
check( 1 === digi_webuzo_count( 'add_user' ), 'Y la cuenta de Webuzo del primer intento no se duplica' );
check( 'registrado' === $GLOBALS['nc']['calls'][ count( $GLOBALS['nc']['calls'] ) - 1 ]['fields']['SLD'] && 'mx' === $GLOBALS['nc']['calls'][ count( $GLOBALS['nc']['calls'] ) - 1 ]['fields']['TLD'], '.mx: SLD y TLD correctos para los nameservers' );

// ── 3. Falla de red sin fin: cinco intentos y en espera ──────────────────────────────────────────────────────
digi_reset_store();
$GLOBALS['webuzo']['timeout'] = 99;
$order = digi_buy( 'sinred.com' );
$order->update_status( 'processing' );
digi_run_queue();
check( 5 === count( $GLOBALS['webuzo']['calls'] ) && ! $GLOBALS['queue'], 'Red caída: cinco intentos en total y ninguno más' );
check( 'on-hold' === $order->get_status() && false !== strpos( digi_notes( $order ), 'Se agotaron los reintentos automáticos' ), 'Agotados los reintentos, el pedido queda en espera con su nota' );
check( 1 === count( digi_mails_to( 'admin@tienda.test' ) ), 'El administrador recibe un aviso' );
check( ! $GLOBALS['nc']['calls'], 'Sin cuenta de hosting no se registra el dominio' );

// ── 4. Clave de Webuzo rechazada ────────────────────────────────────────────────────────────────────────────
digi_reset_store( array( 'webuzo_api_key' => 'CLAVE-VIEJA' ) );
$order = digi_buy( 'clavemala.com' );
$order->update_status( 'processing' );
digi_run_queue();
check( 1 === count( $GLOBALS['webuzo']['calls'] ) && ! $GLOBALS['queue'], 'Clave rechazada: una sola llamada y NINGÚN reintento' );
check( 'on-hold' === $order->get_status() && false !== strpos( digi_notes( $order ), 'se detuvo TODO el aprovisionamiento sin reintentar' ), 'El pedido queda en espera con la explicación' );
$admin = digi_mails_to( 'admin@tienda.test' );
check( 1 === count( $admin ) && false !== strpos( $admin[0]['subject'], 'Webuzo rechazó la clave' ) && false === strpos( $admin[0]['body'], 'CLAVE-VIEJA' ), 'El administrador recibe un solo aviso, sin la clave' );
$block = get_option( Digitalisimo_Hosting_Webuzo::BLOCK );
check( is_array( $block ) && 1 === count( $block ) && false !== strpos( json_encode( $block ), '1 attempt(s) left' ) && false === strpos( json_encode( $block ), 'CLAVE-VIEJA' ), 'El interruptor global guarda el motivo y una huella, nunca la clave' );
check( ! $GLOBALS['nc']['calls'], 'Con la clave rechazada tampoco se registra el dominio' );
// Otro pedido pagado, una suspensión manual y la revisión diaria: ninguno llama a Webuzo.
$second = digi_buy( 'otropedido.com' );
$second->update_status( 'processing' );
digi_run_queue();
$svc_first = (int) $order->get_meta( '_digitalisimo_service' );
$P::svc_set( $svc_first, array( 'webuzo_user' => 'alguien', 'status' => 'active' ) );
$P::run_suspend( $svc_first );
$P::daily();
check( 1 === count( $GLOBALS['webuzo']['calls'] ), 'Mientras dure la parada, ningún pedido, suspensión ni revisión diaria llama a Webuzo' );
check( 'on-hold' === $second->get_status() && false !== strpos( digi_notes( $second ), 'Aprovisionamiento detenido' ), 'El segundo pedido queda en espera de la clave nueva' );
ob_start(); $GLOBALS['pagenow'] = 'index.php'; $P::admin_notice(); $notice = ob_get_clean();
check( false !== strpos( $notice, 'detuvo el aprovisionamiento' ) && false !== strpos( $notice, 'page=digitalisimo-hosting' ), 'El escritorio muestra el aviso con el enlace a los ajustes' );
ob_start(); Digitalisimo_Hosting::page(); $page = ob_get_clean();
check( false !== strpos( $page, 'Aprovisionamiento detenido' ) && false === strpos( $page, 'CLAVE-VIEJA' ), 'La pantalla de ajustes explica la parada sin mostrar la clave' );
// Guardar la MISMA clave enmascarada no la levanta; escribir una nueva sí, y retoma lo detenido.
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_api_key' => Digitalisimo_Hosting::MASK ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean();
check( get_option( Digitalisimo_Hosting_Webuzo::BLOCK ) && ! $GLOBALS['queue'], 'Guardar sin escribir una clave no levanta la parada' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'webuzo_api_key' => 'CLAVE-WEBUZO-OK' ) );
ob_start(); Digitalisimo_Hosting::page(); ob_end_clean(); $_POST = array();
check( ! get_option( Digitalisimo_Hosting_Webuzo::BLOCK ) && array( 'digitalisimo_hosting_provision', 'digitalisimo_hosting_provision', 'digitalisimo_hosting_suspend' ) === array_column( $GLOBALS['queue'], 'hook' ), 'Una clave nueva levanta la parada y vuelve a encolar lo detenido: los dos pedidos y la suspensión' );
digi_run_queue();
check( 'completed' === $order->get_status() && 'completed' === $second->get_status() && 2 === digi_webuzo_count( 'add_user' ), 'Con la clave nueva los pedidos detenidos se entregan' );
check( 1 === count( array_filter( $GLOBALS['webuzo']['keys_seen'], function ( $k ) { return 'CLAVE-VIEJA' === $k; } ) ), 'La clave rechazada se usó una sola vez en total' );
// Una página de acceso (no la API) se trata igual: sin reintento.
digi_reset_store();
$GLOBALS['webuzo']['mode'] = 'html';
$order = digi_buy( 'html.com' );
$order->update_status( 'processing' );
digi_run_queue();
check( 1 === count( $GLOBALS['webuzo']['calls'] ) && ! $GLOBALS['queue'] && get_option( Digitalisimo_Hosting_Webuzo::BLOCK ), 'Una respuesta no reconocida detiene todo sin reintentar' );
// Con la clave en wp-config.php: cambiarla allí también levanta la parada (otra huella).
$api = new Digitalisimo_Hosting_Webuzo( 'https://webuzo.test:2005', 'root', 'OTRA-CLAVE' );
check( null === $api->blocked(), 'Una clave distinta de la rechazada (constante de wp-config.php) no queda bloqueada' );

// ── 5. Saldo insuficiente ───────────────────────────────────────────────────────────────────────────────────
digi_reset_store();
$GLOBALS['nc']['balance'] = 0.02;
$order = digi_buy( 'sinsaldo.com' );
$order->update_status( 'processing' );
digi_run_queue();
check( 0 === digi_nc_count( 'namecheap.domains.create' ) && 'on-hold' === $order->get_status(), 'Saldo insuficiente: no se registra y el pedido queda en espera' );
check( false !== strpos( digi_notes( $order ), 'Saldo insuficiente en Namecheap para registrar sinsaldo.com: hay 0.02 USD y cuesta 11.46 USD' ), 'La nota dice el saldo y el costo' );
check( 1 === count( digi_mails_to( 'admin@tienda.test' ) ) && ! $GLOBALS['queue'], 'Aviso al administrador y sin reintentos automáticos' );
check( ! digi_mails_to( 'ana@cliente.test' ), 'El cliente aún no recibe accesos (falta su dominio)' );
$service = (int) $order->get_meta( '_digitalisimo_service' );
check( 'on-hold' === $P::svc( $service, 'status' ) && false !== strpos( (string) $P::svc( $service, 'error' ), 'Saldo insuficiente' ), 'El servicio aparece en espera con el motivo' );
$GLOBALS['nc']['balance'] = 40;
check( true === $P::retry( $service ), '«Reintentar» encola el pedido' );
digi_run_queue();
check( 'completed' === $order->get_status() && 1 === digi_webuzo_count( 'add_user' ) && 1 === digi_nc_count( 'namecheap.domains.create' ) && 1 === count( digi_mails_to( 'ana@cliente.test' ) ), 'Con saldo, el reintento registra el dominio sin crear otra cuenta y envía los accesos' );

// ── 6. Lo que no se resuelve solo no llama a nadie ──────────────────────────────────────────────────────────
digi_reset_store();
$order = digi_buy( 'barato.io' ); // producto de .com con un dominio .io
$order->update_status( 'processing' ); digi_run_queue();
check( ! $GLOBALS['webuzo']['calls'] && ! $GLOBALS['nc']['calls'] && 'on-hold' === $order->get_status() && false !== strpos( digi_notes( $order ), 'no corresponde al producto de dominio pagado' ), 'Un dominio de otra extensión que la del producto pagado no se registra ni crea cuenta' );
$order = digi_buy( 'mexico.mx', 14693, 20 ); // .mx con el producto de .com
$order->update_status( 'processing' ); digi_run_queue();
check( ! $GLOBALS['webuzo']['calls'] && false !== strpos( digi_notes( $order ), 'no corresponde' ), 'Un .mx pagado con el producto de .com tampoco' );
$order = digi_order( array( new Digi_Test_Item( 14693, 'Starter' ), new Digi_Test_Item( 14696, 'Pro' ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'dos.com' ) ) ) );
$order->update_status( 'processing' ); digi_run_queue();
check( ! $GLOBALS['webuzo']['calls'] && false !== strpos( digi_notes( $order ), 'más de un plan' ), 'Dos planes en un pedido: a mano' );
$order = digi_buy( 'sindireccion.com', 14693, 20, array( 'address_1' => '', 'last_name' => '', 'first_name' => 'Ana' ) );
$order->update_status( 'processing' ); digi_run_queue();
check( 0 === digi_nc_count( 'namecheap.domains.create' ) && false !== strpos( digi_notes( $order ), 'Faltan datos del titular del dominio en la facturación del pedido (LastName, Address1)' ), 'Sin la dirección del titular no se registra el dominio y la nota dice qué falta' );
$GLOBALS['webuzo']['calls'] = array();
$order = digi_buy( 'propio.com', 14693, 0 ); // sólo hosting, dominio propio
$order->update_status( 'processing' ); digi_run_queue();
check( 'completed' === $order->get_status() && 0 === digi_nc_count( 'namecheap.domains.create' ), 'Hosting con dominio propio: se crea la cuenta y no se registra nada' );
check( false !== strpos( digi_notes( $order ), 'dominio propio del cliente' ) && 'propio.com' === $GLOBALS['webuzo']['users']['propio']['domain'], 'La nota pide apuntar el dominio propio a los nameservers' );
$order = digi_order( array( new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'solodominio.com' ) ) ) );
$before = count( $GLOBALS['webuzo']['calls'] );
$order->update_status( 'processing' ); digi_run_queue();
check( 'completed' === $order->get_status() && $before === count( $GLOBALS['webuzo']['calls'] ) && isset( $GLOBALS['nc']['domains']['solodominio.com'] ), 'Sólo dominio: se registra sin tocar Webuzo' );

// ── 7. Correo desactivado, venta apagada y clasificación ────────────────────────────────────────────────────
digi_reset_store();
WC()->mailer()->get_emails()['Digitalisimo_Hosting_Email_Access']->enabled = 'no';
$order = digi_buy( 'sincorreo.com' );
$order->update_status( 'processing' ); digi_run_queue();
check( ! digi_mails_to( 'ana@cliente.test' ) && false !== strpos( digi_notes( $order ), 'está desactivado en WooCommerce → Ajustes → Correos' ) && 'completed' === $order->get_status(), 'Con el correo desactivado en WooCommerce no se envía y la nota lo dice' );
WC()->mailer()->get_emails()['Digitalisimo_Hosting_Email_Access']->enabled = 'yes';
digi_reset_store( array( 'provision_enabled' => 0 ) );
$order = digi_buy( 'apagado.com' );
$order->update_status( 'processing' );
check( ! $GLOBALS['queue'], 'Con la venta automática apagada (valor por defecto) no se encola nada' );
$W = 'Digitalisimo_Hosting_Webuzo';
check( 'auth' === $W::classify( array( 'response' => array( 'code' => 401 ), 'body' => '' ) )['kind'], 'HTTP 401: clave' );
check( 'transient' === $W::classify( array( 'response' => array( 'code' => 502 ), 'body' => 'Bad gateway' ) )['kind'], 'HTTP 502 sin JSON: se puede reintentar' );
check( 'transient' === $W::classify( new WP_Error( 'x', 'cURL error 7: Failed to connect' ) )['kind'], 'Sin conexión: se puede reintentar' );
check( 'api' === $W::classify( digi_json( array( 'error' => array( 'The username already exists' ) ) ) )['kind'], 'Un error de la API que no es de clave no se reintenta ni detiene todo' );
foreach ( array( 'Incorrect Username or Password', '2 attempt(s) left', 'You have exceeded maximum login retries Please try after 30 minute(s)', 'Your IP has been blacklisted', 'Invalid API Key' ) as $msg ) check( 'auth' === $W::classify( digi_json( array( 'error' => array( $msg ) ) ) )['kind'], "«{$msg}» es error de clave" );
check( '+52.3312345678' === $P::phone( '+52 33 1234 5678', 'MX' ) && '+52.3312345678' === $P::phone( '(33) 1234-5678', 'MX' ) && '+1.5551234567' === $P::phone( '555-123-4567', 'US' ), 'Teléfonos al formato de Namecheap' );
check( 'mitienda' === $P::username( 'mitienda.com', array() ) && 'mitienda2' === $P::username( 'mitienda.com', array( 'mitienda' => 1 ) ) && 'u123abc' === $P::username( '123abc.mx', array() ) && 'pruebamitien' === $P::username( 'mitienda.com', array(), 'prueba' ), 'Usuarios derivados del dominio (prefijo, colisiones, empiezan con letra)' );

echo "Hosting, venta automática: {$GLOBALS['checks']} comprobaciones correctas.\n";
