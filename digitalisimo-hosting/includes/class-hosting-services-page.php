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
	const OPS    = array( 'retry', 'suspend', 'unsuspend', 'resend', 'prices', 'approve', 'cancel' );

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
		if ( isset( $off[ $done ] ) ) echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $off[ $done ] ) . '</p></div>';
		elseif ( isset( $texts[ $done ] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $texts[ $done ] ) . ' Se procesa en segundo plano en unos instantes.</p></div>';
		$o = Digitalisimo_Hosting::settings();
		if ( empty( $o['provision_enabled'] ) ) echo '<div class="notice notice-info inline"><p>La venta automática está apagada. Enciéndela en <a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Digitalisimo → Hosting</a>.</p></div>';
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
			echo '<td>' . esc_html( (string) $P::svc( $id, 'plan' ) ) . '</td>';
			echo '<td>' . esc_html( '' !== $user ? $user : '—' ) . '</td>';
			$reprieve = (int) $P::svc( $id, 'reprieve_until' );
			echo '<td>' . esc_html( $expires ? $P::date( $expires ) : '—' ) . ( $reprieve > time() ? '<br><span class="description">' . esc_html( 'Prórroga (reactivado a mano) hasta el ' . $P::date( $reprieve ) ) . '</span>' : '' ) . '</td>';
			echo '<td><strong>' . esc_html( self::status_label( $status ) ) . '</strong>' . ( '' !== $error ? '<br><span class="description">' . esc_html( $error ) . '</span>' : '' ) . '</td>';
			echo '<td>' . ( $order_id ? '<a href="' . esc_url( self::order_url( $order_id ) ) . '">#' . $order_id . '</a>' : '—' ) . ( $renewal ? '<br><span class="description">Renovación <a href="' . esc_url( self::order_url( $renewal ) ) . '">#' . $renewal . '</a></span>' : '' ) . '</td>';
			echo '<td>';
			$renewal_pending = 'cancelled' !== $status && $P::renewal_pending( $id );
			if ( 'cancelled' !== $status && ( in_array( $status, array( 'pending', 'on-hold' ), true ) || '' !== $error || $renewal_pending ) ) self::button( 'retry', $id, $renewal_pending ? 'Reintentar la renovación' : 'Reintentar' );
			if ( $P::svc( $id, 'needs_review' ) && ! $P::svc( $id, 'domain_approved' ) ) self::button( 'approve', $id, 'Aprobar dominio', '¿El dominio ' . $P::svc( $id, 'domain' ) . ' es de este cliente? Al aprobarlo se crea su cuenta de hosting.' );
			if ( '' !== $user && 'active' === $status ) self::button( 'suspend', $id, 'Suspender', '¿Suspender la cuenta «' . $user . '» de ' . $P::svc( $id, 'domain' ) . '? El sitio y el correo del cliente dejarán de funcionar hasta reactivarla.' );
			if ( '' !== $user && 'suspended' === $status ) self::button( 'unsuspend', $id, 'Reactivar' );
			if ( '' !== $user && $P::svc( $id, 'pass' ) && 'cancelled' !== $status ) self::button( 'resend', $id, 'Reenviar accesos' );
			if ( 'cancelled' !== $status ) self::button( 'cancel', $id, 'Dar de baja', '¿Dar de baja ' . $P::svc( $id, 'domain' ) . '? Deja de renovarse y de suspenderse solo. No borra nada en Webuzo ni en Namecheap.' );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
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
