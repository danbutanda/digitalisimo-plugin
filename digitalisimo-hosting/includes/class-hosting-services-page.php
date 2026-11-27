<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce → Servicios de hosting: cada servicio vendido (cliente, dominio, plan, cuenta de Webuzo, vencimiento y
 * estado) con «Reintentar», «Suspender» (con confirmación), «Reactivar» y «Reenviar accesos». Cada acción va por
 * admin-post.php con su nonce (atado a la acción y al servicio) y exige `manage_woocommerce`; la llamada remota se
 * encola, nunca corre durante la petición.
 */
class Digitalisimo_Hosting_Services_Page {
	const SLUG   = 'digitalisimo-hosting-services';
	const ACTION = 'digitalisimo_hosting_service';
	const OPS    = array( 'retry', 'suspend', 'unsuspend', 'resend', 'prices', 'approve', 'cancel', 'manual_payment' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function menu() {
		if ( ! class_exists( 'WooCommerce' ) ) return;
		add_submenu_page( 'woocommerce', 'Servicios de hosting', 'Servicios de hosting', 'manage_woocommerce', self::SLUG, array( __CLASS__, 'render' ) );
	}

	/** La pantalla del pedido (la de HPOS si la tienda la usa). */
	private static function order_url( $id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
		return $order && method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
	}

	public static function nonce_action( $op, $id ) { return self::ACTION . '_' . $op . '_' . (int) $id; }

	private static function status_label( $status ) {
		$labels = array( 'pending' => 'Pendiente', 'active' => 'Activo', 'suspended' => 'Suspendido', 'on-hold' => 'En espera', 'expired' => 'Vencido', 'cancelled' => 'Dado de baja' );
		return $labels[ $status ] ?? $status;
	}

	private static function button( $op, $id, $label, $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 4px 4px 0"' . ( $confirm ? ' onsubmit="return window.confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="service" value="' . (int) $id . '"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
		wp_nonce_field( self::nonce_action( $op, $id ), '_wpnonce', false );
		echo '<button type="submit" class="button button-small' . ( 'suspend' === $op ? ' button-link-delete' : '' ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;
		$P = 'Digitalisimo_Hosting_Provisioning';
		echo '<div class="wrap digitalisimo-hosting-services"><h1>Servicios de hosting</h1><hr class="wp-header-end">';
		$done = sanitize_key( (string) ( $_GET['done'] ?? '' ) );
		$off = array( 'off' => 'La venta automática (o el módulo) está apagada: «Suspender» y «Reactivar» no hacen nada mientras lo esté (no se llamó a Webuzo ni se guardó nada). Enciéndela en Digitalisimo → Hosting o hazlo en Webuzo.', 'approve_off' => 'Dominio aprobado. La venta automática está apagada: no se crea nada ahora; se aprovisiona solo al encenderla.', 'retry_off' => 'La venta automática está apagada: no se reintenta nada ahora; queda anotado y se retoma solo al encenderla.' );
		$texts = array( 'approve' => 'Dominio aprobado; aprovisionamiento en cola.', 'cancel' => 'Servicio dado de baja (sin renovaciones ni suspensiones automáticas).', 'already' => 'Ya estaba suspendido: se conserva el motivo (si fue por falta de pago, al pagar se reactiva).', 'prices' => 'Actualización de precios en cola.', 'retry' => 'Reintento en cola.', 'suspend' => 'Suspensión en cola.', 'unsuspend' => 'Reactivación en cola.', 'resend' => 'Reenvío de accesos en cola.', 'nothing' => 'No había nada a medias que reintentar.' );
		if ( 'manual' === $done && function_exists( 'get_transient' ) && function_exists( 'get_current_user_id' ) ) {
			$m = get_transient( 'digitalisimo_hosting_manual_msg_' . (int) get_current_user_id() );
			if ( is_array( $m ) ) { delete_transient( 'digitalisimo_hosting_manual_msg_' . (int) get_current_user_id() ); echo '<div class="notice notice-' . ( 'error' === $m[0] ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( (string) $m[1] ) . '</p></div>'; }
		}
		if ( isset( $off[ $done ] ) ) echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $off[ $done ] ) . '</p></div>';
		elseif ( isset( $texts[ $done ] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $texts[ $done ] ) . ' Se procesa en segundo plano en unos instantes.</p></div>';
		$o = Digitalisimo_Hosting::settings();
		if ( empty( $o['provision_enabled'] ) ) echo '<div class="notice notice-info inline"><p>La venta automática está apagada. Enciéndela en <a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Digitalisimo → Hosting</a>.</p></div>';
		// «Registrar pago manual»: el formulario corto de ese servicio.
		$manual = absint( $_GET['manual'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo muestra el formulario.
		if ( $manual && $P::is_service( $manual ) && $P::can_manual_payment() ) self::manual_form( $manual );
		$ids = $P::services();
		if ( ! $ids ) echo '<p>Todavía no hay servicios. Aparecen aquí al pagarse el primer pedido de hosting o dominio.</p>';
		else self::table( $ids );
		if ( class_exists( 'Digitalisimo_Hosting_Pricing' ) ) {
			Digitalisimo_Hosting_Pricing::render_history();
			if ( ! empty( $o['pricing_enabled'] ) ) self::button( 'prices', 0, 'Actualizar precios ahora' );
		}
		echo '</div>';
	}

	private static function table( array $ids ) {
		$P = 'Digitalisimo_Hosting_Provisioning';
		echo '<table class="widefat striped"><thead><tr><th>Cliente</th><th>Dominio</th><th>Plan</th><th>Cuenta Webuzo</th><th>Vencimiento</th><th>Estado</th><th>Pedido</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( array_reverse( $ids ) as $id ) {
			$status = (string) $P::svc( $id, 'status' );
			$expires = (int) $P::svc( $id, 'expires' );
			$order_id = (int) $P::svc( $id, 'order' );
			$renewal = (int) $P::svc( $id, 'renewal_order' );
			$error = (string) $P::svc( $id, 'error' );
			$user = (string) $P::svc( $id, 'webuzo_user' );
			echo '<tr data-service="' . (int) $id . '">';
			echo '<td>' . esc_html( (string) $P::svc( $id, 'name' ) ) . '<br><span class="description">' . esc_html( (string) $P::svc( $id, 'email' ) ) . '</span></td>';
			echo '<td>' . esc_html( (string) $P::svc( $id, 'domain' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $P::svc( $id, 'plan' ) ) . ( $P::has_hosting( $id ) ? '<br><span class="description">' . esc_html( 'Cobro ' . strtolower( Digitalisimo_Hosting_Products::period_label( $P::service_period( $id ) ) ) ) . '</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( '' !== $user ? $user : '—' ) . '</td>';
			$reprieve = (int) $P::svc( $id, 'reprieve_until' );
			$dd = $P::domain_due( $id );
			echo '<td>' . esc_html( $expires ? $P::date( $expires ) : '—' ) . ( $dd && $P::has_hosting( $id ) && abs( $dd - $expires ) >= 2 * DAY_IN_SECONDS ? '<br><span class="description">' . esc_html( 'Dominio: ' . $P::date( $dd ) ) . '</span>' : '' ) . ( $reprieve > time() ? '<br><span class="description">' . esc_html( 'Prórroga (reactivado a mano) hasta el ' . $P::date( $reprieve ) ) . '</span>' : '' ) . '</td>';
			echo '<td><strong>' . esc_html( self::status_label( $status ) ) . '</strong>' . ( '' !== $error ? '<br><span class="description">' . esc_html( $error ) . '</span>' : '' ) . ( $P::svc( $id, 'transfer_pending' ) ? '<br><span class="description">Transferencia de dominio pendiente: hazla en ' . esc_html( class_exists( 'Digitalisimo_Hosting_Registrars' ) ? Digitalisimo_Hosting_Registrars::label( Digitalisimo_Hosting_Registrars::of_service( $id ) ) : 'Namecheap' ) . ' con el código EPP del pedido («Ver código EPP»). Se marca sola al llegar a la cuenta.</span>' : '' ) . '</td>';
			echo '<td>' . ( $order_id ? '<a href="' . esc_url( self::order_url( $order_id ) ) . '">#' . $order_id . '</a>' : '—' ) . ( $renewal ? '<br><span class="description">Renovación <a href="' . esc_url( self::order_url( $renewal ) ) . '">#' . $renewal . '</a></span>' : '' ) . ( (int) $P::svc( $id, 'domain_renewal_order' ) ? '<br><span class="description">Renovación del dominio <a href="' . esc_url( self::order_url( (int) $P::svc( $id, 'domain_renewal_order' ) ) ) . '">#' . (int) $P::svc( $id, 'domain_renewal_order' ) . '</a></span>' : '' ) . '</td>';
			echo '<td>';
			$renewal_pending = 'cancelled' !== $status && $P::renewal_pending( $id );
			if ( 'cancelled' !== $status && ( in_array( $status, array( 'pending', 'on-hold' ), true ) || '' !== $error || $renewal_pending ) ) self::button( 'retry', $id, $renewal_pending ? 'Reintentar la renovación' : 'Reintentar' );
			if ( $P::svc( $id, 'needs_review' ) && ! $P::svc( $id, 'domain_approved' ) ) self::button( 'approve', $id, 'Aprobar dominio', '¿El dominio ' . $P::svc( $id, 'domain' ) . ' es de este cliente? Al aprobarlo se crea su cuenta de hosting.' );
			if ( '' !== $user && 'active' === $status ) self::button( 'suspend', $id, 'Suspender', '¿Suspender la cuenta «' . $user . '» de ' . $P::svc( $id, 'domain' ) . '? El sitio y el correo del cliente dejarán de funcionar hasta reactivarla.' );
			if ( '' !== $user && 'suspended' === $status ) self::button( 'unsuspend', $id, 'Reactivar' );
			if ( '' !== $user && $P::svc( $id, 'pass' ) && 'cancelled' !== $status ) self::button( 'resend', $id, 'Reenviar accesos' );
			if ( 'cancelled' !== $status && $P::can_manual_payment() ) echo '<a class="button button-small" style="margin:0 4px 4px 0" href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&manual=' . (int) $id ) ) . '">Registrar pago manual</a>';
			if ( 'cancelled' !== $status ) self::button( 'cancel', $id, 'Dar de baja', '¿Dar de baja ' . $P::svc( $id, 'domain' ) . '? Deja de renovarse y de suspenderse solo. No borra nada en Webuzo ni en el registrador del dominio.' );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * «Registrar pago manual» (sólo administradores): periodo pagado (de fábrica, el del servicio), medio, monto
	 * informativo, nota y «Renovar también el dominio con su registrador» (marcada si el dominio vence dentro del periodo,
	 * con su costo y el aviso si el saldo no alcanza). Una clave por formulario: un doble envío no extiende dos veces.
	 */
	private static function manual_form( $id ) {
		$P = 'Digitalisimo_Hosting_Provisioning';
		$pv = $P::manual_preview( $id );
		$fid = 'dh-manual-' . (int) $id;
		echo '<div class="card" style="max-width:640px"><h2>Registrar pago manual: ' . esc_html( (string) $P::svc( $id, 'domain' ) ) . '</h2>';
		echo '<p>Para un pago recibido por otro lado (en persona, transferencia o depósito). Extiende el vencimiento el periodo pagado, cancela los avisos y la suspensión de ese periodo y reactiva el servicio si estaba suspendido por falta de pago. Vence hoy el ' . esc_html( $pv['from'] ? $P::date( $pv['from'] ) : '—' ) . '.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="service" value="' . (int) $id . '"><input type="hidden" name="op" value="manual_payment">';
		echo '<input type="hidden" name="key" value="' . esc_attr( function_exists( 'wp_generate_uuid4' ) ? str_replace( '-', '', wp_generate_uuid4() ) : md5( uniqid( '', true ) ) ) . '">';
		wp_nonce_field( self::nonce_action( 'manual_payment', $id ), '_wpnonce', false );
		echo '<p><label for="' . esc_attr( $fid ) . '-months"><strong>Periodo pagado</strong></label><br><select id="' . esc_attr( $fid ) . '-months" name="months">';
		// Un servicio que es sólo el dominio se paga por años (es como lo renueva el registrador).
		foreach ( Digitalisimo_Hosting_Products::PERIODS as $m => $label ) if ( $P::has_hosting( $id ) || 0 === $m % 12 ) echo '<option value="' . (int) $m . '"' . ( (int) $m === (int) $pv['months'] ? ' selected' : '' ) . '>' . esc_html( $label . ' (' . ( 12 === $m ? 'un año' : ( $m % 12 ? $m . ( 1 === $m ? ' mes' : ' meses' ) : ( $m / 12 ) . ' años' ) ) . ')' ) . '</option>';
		echo '</select></p>';
		echo '<p><label for="' . esc_attr( $fid ) . '-method"><strong>Medio</strong></label><br><select id="' . esc_attr( $fid ) . '-method" name="method">';
		foreach ( $P::MANUAL_METHODS as $k => $label ) echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		echo '</select></p>';
		echo '<p><label for="' . esc_attr( $fid ) . '-amount"><strong>Monto</strong> (opcional, sólo para tu registro)</label><br><input type="number" min="0" step="0.01" id="' . esc_attr( $fid ) . '-amount" name="amount" class="small-text"></p>';
		echo '<p><label for="' . esc_attr( $fid ) . '-note"><strong>Nota</strong> (opcional)</label><br><input type="text" id="' . esc_attr( $fid ) . '-note" name="note" class="regular-text" maxlength="300"></p>';
		// La fecha del registrador (sólo lectura): con «ya lo renové yo» se comprueba contra la suya, no contra la del servicio.
		$reg_due = $pv['domain_due'] && $P::svc( $id, 'domain_registered' ) ? $P::registrar_now( $id ) : 0;
		$reg_text = $reg_due ? ' Hoy en ' . $pv['registrar'] . ' vence el ' . $P::date( $reg_due ) . '.' : '';
		$cost = 'El dominio vence el ' . $P::date( $pv['domain_due'] ) . '. ' . ( null !== $pv['cost'] ? 'Costo en ' . $pv['registrar'] . ': ' . number_format( (float) $pv['cost'], 2, '.', ',' ) . ' USD' . ( null !== $pv['balance'] ? ' (saldo ' . number_format( (float) $pv['balance'], 2, '.', ',' ) . ' USD).' : '.' ) : '' );
		if ( $pv['domain_due'] && $P::svc( $id, 'domain_registered' ) && ! $P::has_hosting( $id ) ) {
			// Sólo el dominio: UNA de las dos opciones (sin ninguna no se registra nada; segunda revisión, R1).
			echo '<fieldset><legend><strong>El dominio</strong></legend>';
			echo '<p><label><input type="radio" name="domain_action" value="renew" checked> <strong>Renovar el dominio con su registrador</strong></label><br><span class="description">' . esc_html( $cost ) . '</span></p>';
			echo '<p><label><input type="radio" name="domain_action" value="elsewhere"> Ya lo renové yo en el registrador (se comprueba allá que avanzó los años pagados; queda anotado)</label>' . ( '' !== $reg_text ? '<br><span class="description">' . esc_html( trim( $reg_text ) ) . '</span>' : '' ) . '</p></fieldset>';
			echo '<p class="description">Este servicio es sólo el dominio: su vencimiento cambia cuando el registrador confirma la renovación, o al vencimiento que el registrador muestra si ya la hiciste. Si no se puede renovar, queda pendiente con aviso.</p>';
			if ( '' !== $pv['warning'] ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $pv['warning'] ) . '</p></div>';
		} elseif ( $pv['domain_due'] && $P::svc( $id, 'domain_registered' ) ) {
			echo '<p><label><input type="checkbox" name="renew_domain" value="1"' . ( $pv['renew_domain'] ? ' checked' : '' ) . '> <strong>Renovar también el dominio con su registrador</strong></label><br><span class="description">' . esc_html( $cost ) . '</span></p>';
			echo '<p><label><input type="checkbox" name="renewed_elsewhere" value="1"> Ya lo renové yo en el registrador (no pedir la renovación; se comprueba allá y queda anotado)</label>' . ( '' !== $reg_text ? '<br><span class="description">' . esc_html( trim( $reg_text ) ) . '</span>' : '' ) . '</p>';
			if ( '' !== $pv['warning'] ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $pv['warning'] ) . '</p></div>';
		}
		echo '<p><button type="submit" class="button button-primary">Registrar el pago</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">Cancelar</a></p></form></div>';
	}

	/** admin-post.php: capacidad, nonce de la acción y del servicio, y la tarea en cola. */
	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'No tienes permiso para gestionar servicios de hosting.', 403 );
		$id = absint( $_POST['service'] ?? 0 );
		$op = sanitize_key( (string) ( $_POST['op'] ?? '' ) );
		if ( ! in_array( $op, self::OPS, true ) ) wp_die( 'Acción no válida.', 400 );
		check_admin_referer( self::nonce_action( $op, $id ) );
		$P = 'Digitalisimo_Hosting_Provisioning';
		if ( 'prices' === $op ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) as_enqueue_async_action( Digitalisimo_Hosting_Pricing::HOOK_NOW, array(), $P::GROUP );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&done=prices' ) );
			exit;
		}
		if ( ! $P::is_service( $id ) ) wp_die( 'Servicio no encontrado.', 404 );
		if ( 'manual_payment' === $op ) {
			// Sólo el dominio: la opción elegida (renovar o «ya lo renové»); sin ninguna, manual_payment() no registra nada.
			$action = isset( $_POST['domain_action'] ) ? sanitize_key( (string) $_POST['domain_action'] ) : '';
			$renew = '' !== $action ? 'renew' === $action : ! empty( $_POST['renew_domain'] );
			$elsewhere = '' !== $action ? 'elsewhere' === $action : ! empty( $_POST['renewed_elsewhere'] );
			$r = $P::manual_payment( $id, array( 'months' => absint( $_POST['months'] ?? 0 ), 'method' => sanitize_key( (string) ( $_POST['method'] ?? '' ) ), 'amount' => sanitize_text_field( wp_unslash( (string) ( $_POST['amount'] ?? '' ) ) ), 'note' => sanitize_text_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ), 'renew_domain' => $renew, 'renewed_elsewhere' => $elsewhere, 'key' => sanitize_key( (string) ( $_POST['key'] ?? '' ) ) ) );
			if ( function_exists( 'set_transient' ) && function_exists( 'get_current_user_id' ) ) set_transient( 'digitalisimo_hosting_manual_msg_' . (int) get_current_user_id(), is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'success', $r['message'] ), 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&done=manual' ) );
			exit;
		}
		$done = $op;
		$on = $P::sale_on(); // módulo y venta automática encendidos
		if ( 'retry' === $op ) $done = $P::retry( $id ) ? 'retry' : ( $on ? 'nothing' : 'retry_off' );
		// Con el módulo o la venta apagados, «Suspender» y «Reactivar» no hacen nada ni dejan nada guardado (se dice en llano).
		elseif ( in_array( $op, array( 'suspend', 'unsuspend' ), true ) && ! $on ) $done = 'off';
		// Suspender a mano lo ya suspendido por falta de pago conserva ese motivo (al pagar, se reactiva).
		elseif ( 'suspend' === $op && 'suspended' === $P::svc( $id, 'status' ) ) $done = 'already';
		elseif ( 'suspend' === $op ) { $P::svc_set( $id, array( 'suspend_reason' => 'manual' ) ); $P::enqueue( $P::HOOK_SUSPEND, $id ); }
		elseif ( 'approve' === $op ) { $P::svc_set( $id, array( 'domain_approved' => time(), 'needs_review' => 0 ) ); $P::retry( $id ); if ( ! $on ) $done = 'approve_off'; }
		elseif ( 'cancel' === $op ) $P::cancel_service( $id );
		elseif ( 'unsuspend' === $op ) { $P::svc_set( $id, array( 'unsuspend_reason' => 'manual' ) ); $P::enqueue( $P::HOOK_UNSUSPEND, $id ); }
		else $P::enqueue( $P::HOOK_RESEND, $id );
		$P::svc_log( $id, 'Acción manual «' . $op . '» de ' . ( function_exists( 'wp_get_current_user' ) ? wp_get_current_user()->user_login : 'un administrador' ) . '.' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&done=' . $done ) );
		exit;
	}
}
