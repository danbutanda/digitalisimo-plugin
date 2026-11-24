<?php
defined( 'ABSPATH' ) || exit;

/**
 * Precio de los dominios en tiempo real (pedido del propietario del 2026-11-24).
 *
 * Una vez al día (Action Scheduler) y para cada producto de «Productos por extensión»:
 *  1. Namecheap: namecheap.users.getPricing de REGISTER y RENEW a 1 año (YourPrice + YourAdditonalCost, el cargo
 *     ICANN; Namecheap lo escribe así, mal escrito: se aceptan también YourAdditionalCost y AdditionalCost).
 *     https://www.namecheap.com/support/api/methods/users/get-pricing/
 *  2. Costo base = el MAYOR de los dos (el registro suele ser promoción del primer año: .mx 23.98 contra 58.98 USD).
 *  3. Tipo de cambio USD→MXN: FIX de Banxico (SIE, serie SF43718, cabecera Bmx-Token;
 *     https://www.banxico.org.mx/SieAPIRest/service/v1/doc/consultaDatosSerieOp) y, si falla o no hay token,
 *     Frankfurter (https://api.frankfurter.dev/v2/rate/usd/mxn, sin clave; https://frankfurter.dev), más un colchón.
 *  4. Precio SIN IVA (la tienda suma el IVA): la pasarela cobra su comisión sobre el total CON IVA, así que
 *        precio = costo MXN × (1 + margen) / (1 − comisión × (1 + IVA))
 *     y lo que queda tras pagar la comisión y entregar el IVA es al menos costo × (1 + margen). Se redondea hacia arriba
 *     (múltiplo de 10, múltiplo de 9 o terminado en 9) y nunca baja de costo + margen.
 *  5. Si falla cualquier consulta (Namecheap o las dos fuentes del tipo de cambio) no se cambia ningún precio y se avisa
 *     al administrador. Cada corrida queda en el historial (WooCommerce → Servicios de hosting).
 * Antes de registrar o renovar un dominio pagado se vuelve a mirar el costo real (guard()): si lo cobrado ya no cubre el
 * costo más el margen mínimo, el pedido queda en espera en lugar de registrar con pérdida.
 */
class Digitalisimo_Hosting_Pricing {
	const HOOK     = 'digitalisimo_hosting_prices';
	/** «Actualizar precios ahora»: una corrida suelta, con otro nombre para no confundirse con la recurrente. */
	const HOOK_NOW = 'digitalisimo_hosting_prices_now';
	const HISTORY  = 'digitalisimo_hosting_price_history';
	const BANXICO  = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/oportuno';
	const FALLBACK = 'https://api.frankfurter.dev/v2/rate/usd/mxn';
	const ROUNDING = array( '10' => 'Múltiplo de 10 (1,470)', '9' => 'Múltiplo de 9 (1,467)', 'end9' => 'Terminado en 9 (1,469)', 'none' => 'Sin redondeo (al peso)' );

	public static function init() { add_action( self::HOOK, array( __CLASS__, 'run' ) ); add_action( self::HOOK_NOW, array( __CLASS__, 'run' ) ); }

	/** La consulta diaria en Action Scheduler (una sola, recurrente), mientras los precios automáticos estén encendidos. */
	public static function ensure_schedule() {
		$o = Digitalisimo_Hosting::settings();
		if ( empty( $o['pricing_enabled'] ) || ! function_exists( 'as_schedule_recurring_action' ) ) return;
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK, array(), Digitalisimo_Hosting_Provisioning::GROUP ) ) return;
		as_schedule_recurring_action( time() + 300, DAY_IN_SECONDS, self::HOOK, array(), Digitalisimo_Hosting_Provisioning::GROUP );
	}

	/** Los parámetros de la fórmula, en fracciones (3 % → 0.03). */
	public static function params() {
		$o = Digitalisimo_Hosting::settings();
		$pct = function ( $key, $default ) use ( $o ) { $v = $o[ $key ] ?? $default; return round( max( 0, min( 90, (float) ( '' === $v ? $default : $v ) ) ) / 100, 6 ); };
		return array(
			'cushion'    => $pct( 'pricing_cushion', 3 ),
			'margin'     => $pct( 'pricing_margin', 25 ),
			'fee'        => $pct( 'pricing_fee', 3.6 ),
			'vat'        => $pct( 'pricing_vat', 16 ),
			'min_margin' => $pct( 'pricing_min_margin', 10 ),
			'rounding'   => isset( self::ROUNDING[ (string) ( $o['pricing_rounding'] ?? '' ) ] ) ? (string) $o['pricing_rounding'] : '10',
		);
	}

	public static function round_up( $value, $mode ) {
		$v = (int) ceil( round( $value, 6 ) );
		switch ( $mode ) {
			case '10': return (int) ( ceil( $v / 10 ) * 10 );
			case '9': return (int) ( ceil( $v / 9 ) * 9 );
			case 'end9': return 9 === $v % 10 ? $v : (int) ( floor( $v / 10 ) * 10 + 9 );
			default: return $v;
		}
	}

	/** Costo en MXN (con colchón), piso (costo + margen) y precio de venta sin IVA. */
	public static function sell_price( $base_usd, $fx, array $p ) {
		$cost  = $base_usd * $fx * ( 1 + $p['cushion'] );
		$floor = $cost * ( 1 + $p['margin'] );
		$den   = 1 - $p['fee'] * ( 1 + $p['vat'] );
		$raw   = $den > 0.05 ? $floor / $den : $floor * 2;
		$price = max( self::round_up( $raw, $p['rounding'] ), (int) ceil( $floor ) );
		return array( 'cost' => round( $cost, 2 ), 'floor' => round( $floor, 2 ), 'raw' => round( $raw, 2 ), 'price' => $price );
	}

	// ── Tipo de cambio ──────────────────────────────────────────────────────────────────────────────────────────

	/** FIX de Banxico y, si falla, Frankfurter: array( kind ok, rate, source ) o el error de las dos. */
	public static function fx() {
		$token = trim( (string) ( Digitalisimo_Hosting::settings()['banxico_token'] ?? '' ) );
		$errors = array();
		if ( '' !== $token ) {
			$r = self::banxico( $token );
			if ( 'ok' === $r['kind'] ) return $r;
			$errors[] = $r['message'];
		} else $errors[] = 'Banxico: falta el token de consulta del SIE';
		$r = self::frankfurter();
		if ( 'ok' === $r['kind'] ) { $r['source'] .= ' (respaldo: ' . $errors[0] . ')'; return $r; }
		$errors[] = $r['message'];
		return array( 'kind' => 'transient', 'message' => 'Sin tipo de cambio: ' . implode( '; ', $errors ) . '.' );
	}

	private static function banxico( $token ) {
		$res = wp_remote_get( self::BANXICO, array( 'timeout' => 20, 'redirection' => 0, 'headers' => array( 'Bmx-Token' => $token, 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $res ) ) return array( 'kind' => 'transient', 'message' => 'Banxico no respondió' );
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$row  = $json['bmx']['series'][0]['datos'][0] ?? null;
		$rate = is_array( $row ) ? (float) str_replace( ',', '', (string) ( $row['dato'] ?? '' ) ) : 0;
		if ( 200 !== $code || ! $row || $rate < 5 || $rate > 100 ) return array( 'kind' => 'api', 'message' => 'Banxico no dio el FIX (HTTP ' . $code . ')' );
		return array( 'kind' => 'ok', 'rate' => $rate, 'source' => 'Banxico FIX ' . sanitize_text_field( (string) ( $row['fecha'] ?? '' ) ) );
	}

	private static function frankfurter() {
		$res = wp_remote_get( self::FALLBACK, array( 'timeout' => 20, 'redirection' => 0, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $res ) ) return array( 'kind' => 'transient', 'message' => 'Frankfurter no respondió' );
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$rate = (float) ( $json['rate'] ?? 0 );
		if ( 200 !== $code || 'MXN' !== strtoupper( (string) ( $json['quote'] ?? '' ) ) || $rate < 5 || $rate > 100 ) return array( 'kind' => 'api', 'message' => 'Frankfurter no dio USD→MXN (HTTP ' . $code . ')' );
		return array( 'kind' => 'ok', 'rate' => $rate, 'source' => 'Frankfurter ' . sanitize_text_field( (string) ( $json['date'] ?? '' ) ) );
	}

	// ── Corrida diaria ──────────────────────────────────────────────────────────────────────────────────────────

	public static function history() { $h = get_option( self::HISTORY, array() ); return is_array( $h ) ? $h : array(); }

	private static function record( array $entry ) {
		$h = self::history(); $h[] = $entry;
		update_option( self::HISTORY, array_slice( $h, -60 ), false );
		return $entry;
	}

	private static function failed( $message ) {
		$entry = self::record( array( 't' => time(), 'status' => 'error', 'message' => $message ) );
		Digitalisimo_Hosting_Provisioning::alert_admin( 'precios de dominios sin cambios', $message . "\n\nNo se cambió ningún precio. Historial: " . admin_url( 'admin.php?page=digitalisimo-hosting-services' ) );
		return $entry;
	}

	/** Consulta costos y tipo de cambio y, sólo si todo respondió, fija los precios. Devuelve la entrada del historial. */
	public static function run() {
		$o = Digitalisimo_Hosting::settings();
		if ( empty( $o['enabled'] ) || empty( $o['pricing_enabled'] ) ) return null;
		$map = Digitalisimo_Hosting::domain_products();
		if ( ! $map ) return null;
		$nc = Digitalisimo_Hosting_Namecheap::from_settings();
		if ( ! $nc->configured() ) return self::failed( 'Faltan los datos de la cuenta de Namecheap.' );
		$costs = array();
		foreach ( $map as $ext => $pid ) {
			$tld = ltrim( $ext, '.' );
			foreach ( array( 'REGISTER', 'RENEW' ) as $action ) {
				$r = $nc->price( $tld, $action );
				if ( 'ok' !== $r['kind'] ) return self::failed( 'Namecheap (' . $action . ' .' . $tld . '): ' . $r['message'] );
				$costs[ $ext ][ $action ] = (float) $r['value'];
			}
		}
		$fx = self::fx();
		if ( 'ok' !== $fx['kind'] ) return self::failed( $fx['message'] );
		$p = self::params();
		$rows = array();
		foreach ( $map as $ext => $pid ) {
			$base = max( $costs[ $ext ]['REGISTER'], $costs[ $ext ]['RENEW'] );
			$calc = self::sell_price( $base, $fx['rate'], $p );
			$row  = array( 'ext' => $ext, 'product' => (int) $pid, 'register_usd' => $costs[ $ext ]['REGISTER'], 'renew_usd' => $costs[ $ext ]['RENEW'], 'base_usd' => $base ) + $calc;
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
			if ( ! $product ) { $row['skipped'] = 'El producto #' . $pid . ' no existe.'; $rows[] = $row; continue; }
			$row['old'] = (string) $product->get_regular_price();
			$product->set_regular_price( (string) $calc['price'] );
			// Una oferta por debajo del nuevo precio vendería con pérdida: se quita.
			if ( '' !== (string) $product->get_sale_price() && (float) $product->get_sale_price() < $calc['price'] ) { $product->set_sale_price( '' ); $row['sale_removed'] = 1; }
			$product->save();
			$rows[] = $row;
		}
		return self::record( array( 't' => time(), 'status' => 'ok', 'fx' => $fx['rate'], 'fx_source' => $fx['source'], 'cushion' => $p['cushion'], 'margin' => $p['margin'], 'fee' => $p['fee'], 'vat' => $p['vat'], 'rounding' => $p['rounding'], 'rows' => $rows ) );
	}

	// ── Antes de registrar o renovar ────────────────────────────────────────────────────────────────────────────

	/** El tipo de cambio para comprobar un pedido: el del historial de los últimos 3 días o uno nuevo. */
	private static function current_fx() {
		foreach ( array_reverse( self::history() ) as $e ) {
			if ( 'ok' === ( $e['status'] ?? '' ) && time() - (int) $e['t'] < 3 * DAY_IN_SECONDS ) return array( 'kind' => 'ok', 'rate' => (float) $e['fx'], 'source' => (string) $e['fx_source'] );
			break;
		}
		return self::fx();
	}

	/**
	 * ¿Lo cobrado (sin IVA) cubre el costo real de hoy más el margen mínimo, tras la comisión de la pasarela? null si sí
	 * (o si los precios automáticos están apagados); si no, el motivo para dejar el pedido en espera.
	 */
	public static function guard( $charged, $cost_usd, $label ) {
		$o = Digitalisimo_Hosting::settings();
		if ( empty( $o['pricing_enabled'] ) ) return null;
		$fx = self::current_fx();
		if ( 'ok' !== $fx['kind'] ) return array( 'kind' => 'transient', 'message' => 'No se pudo comprobar el costo de ' . $label . ': ' . $fx['message'] );
		$p    = self::params();
		$cost = $cost_usd * $fx['rate'];
		$net  = (float) $charged * ( 1 - $p['fee'] * ( 1 + $p['vat'] ) );
		$need = $cost * ( 1 + $p['min_margin'] );
		if ( $net + 0.005 >= $need ) return null;
		return array( 'kind' => 'price', 'message' => sprintf( 'El costo real de %s subió: Namecheap cobra %s USD (%s MXN al tipo %s de %s) y lo cobrado, %s MXN sin IVA, deja %s MXN tras la comisión; con el margen mínimo de %s %% hacen falta %s MXN. No se hizo para no perder: ajusta el precio o hazlo a mano.', $label, number_format( $cost_usd, 2, '.', '' ), number_format( $cost, 2, '.', '' ), number_format( $fx['rate'], 4, '.', '' ), $fx['source'], number_format( (float) $charged, 2, '.', '' ), number_format( $net, 2, '.', '' ), rtrim( rtrim( number_format( $p['min_margin'] * 100, 2, '.', '' ), '0' ), '.' ), number_format( $need, 2, '.', '' ) ) );
	}

	/** El historial en la pantalla de servicios (las 10 corridas más recientes). */
	public static function render_history() {
		$h = array_slice( array_reverse( self::history() ), 0, 10 );
		echo '<h2>Precios de dominios</h2>';
		$o = Digitalisimo_Hosting::settings();
		echo '<p class="description">' . ( empty( $o['pricing_enabled'] ) ? 'Los precios automáticos están apagados (Digitalisimo → Hosting → Precios de dominios).' : 'Se actualizan una vez al día con el costo de Namecheap y el tipo de cambio del día.' ) . '</p>';
		if ( ! $h ) { echo '<p>Sin corridas todavía.</p>'; return; }
		echo '<table class="widefat striped digitalisimo-hosting-prices"><thead><tr><th>Fecha</th><th>Tipo de cambio</th><th>Extensión</th><th>Registro / renovación (USD)</th><th>Costo MXN (con colchón)</th><th>Precio sin IVA</th></tr></thead><tbody>';
		foreach ( $h as $e ) {
			$date = esc_html( Digitalisimo_Hosting_Provisioning::date( (int) $e['t'] ) . ( function_exists( 'wp_date' ) ? ' ' . wp_date( 'H:i', (int) $e['t'] ) : '' ) );
			if ( 'ok' !== ( $e['status'] ?? '' ) ) { echo '<tr><td>' . $date . '</td><td colspan="5"><strong>Sin cambios:</strong> ' . esc_html( (string) ( $e['message'] ?? '' ) ) . '</td></tr>'; continue; }
			foreach ( (array) $e['rows'] as $i => $r ) {
				echo '<tr><td>' . ( 0 === $i ? $date : '' ) . '</td><td>' . ( 0 === $i ? esc_html( number_format( (float) $e['fx'], 4 ) . ' · ' . $e['fx_source'] . ' · colchón ' . round( $e['cushion'] * 100, 2 ) . ' %' ) : '' ) . '</td>';
				echo '<td>' . esc_html( $r['ext'] ) . '</td><td>' . esc_html( number_format( (float) $r['register_usd'], 2 ) . ' / ' . number_format( (float) $r['renew_usd'], 2 ) ) . '</td><td>' . esc_html( number_format( (float) $r['cost'], 2 ) ) . '</td>';
				echo '<td>' . ( isset( $r['skipped'] ) ? esc_html( $r['skipped'] ) : esc_html( ( '' !== (string) ( $r['old'] ?? '' ) && (string) $r['old'] !== (string) $r['price'] ? $r['old'] . ' → ' : '' ) . $r['price'] ) . ( ! empty( $r['sale_removed'] ) ? ' (oferta retirada)' : '' ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
	}
}
