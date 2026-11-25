<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente de la API de administración de Webuzo (puerto 2005) para la venta automática de hosting.
 *
 * Todo sale de la documentación oficial (consultada el 2026-11-23), no de suposiciones:
 *  - Autenticación: https://webuzo.com/docs/api/admin-authentication/ — `apiuser` y `apikey` como campos POST a
 *    `https://<servidor>:2005/index.php?api=json&act=<acción>`; la respuesta es JSON y un fallo trae la clave `error`.
 *  - Crear cuenta: https://webuzo.com/docs/api/add-user/ — `act=add_user` con `create_user=1`, `user`, `domain`,
 *    `user_passwd`, `cnf_user_passwd`, `email` y `plan`; el éxito trae `done` («The User has been saved successfully»).
 *  - Listar cuentas: https://webuzo.com/docs/api/list-users/ — `act=users`; `users` es un objeto por usuario con
 *    `user`, `domain`, `email`, `plan` y `status`.
 *  - Suspender: https://webuzo.com/docs/api/suspend-user/ — `act=users` con `suspend=<usuario>`.
 *  - Reactivar: https://webuzo.com/docs/api/unsuspend-user/ — `act=users` con `unsuspend=<usuario>`.
 *    La documentación pide además `skip=1`, pero en la prueba real autorizada del 2026-11-24 (webuzo-real/) ese Webuzo
 *    contestó «Usuario suspendido con éxito» / «Usuario reactivado con éxito» SIN cambiar nada; sin `skip`, la cuenta
 *    quedó suspendida en unos 45 s. Por eso no se manda `skip` y el cambio se confirma después con `act=users`
 *    (`status` = "suspended" o ""), nunca sólo por el «done».
 *  - Mensajes de bloqueo por intentos: https://webuzo.com/docs/api/brute-force/ («Incorrect Username or Password»,
 *    «attempt(s) left», «You have exceeded maximum login retries», «Your IP has been blacklisted»).
 *
 * Regla del propietario (2026-11-23): Webuzo cuenta los intentos fallidos y bloquea la IP (llegamos a «1 intento
 * restante»). Por eso una respuesta de clave rechazada (o una que no se reconoce) NUNCA se reintenta: activa un
 * interruptor global por servidor que impide cualquier llamada nueva hasta que el administrador guarde una clave nueva
 * (o cambie la constante de wp-config.php). Sólo se reintenta lo que no llegó a Webuzo: red caída o tiempo agotado.
 * La clave viaja en el cuerpo del POST (nunca en la URL, que queda en los registros) y nunca se escribe en notas,
 * correos ni registros.
 */
class Digitalisimo_Hosting_Webuzo {
	/**
	 * Claves rechazadas, de toda la red: huella de la clave => array( 't' => cuándo, 'msg' => motivo, 'key' => huella ).
	 * La parada va por CLAVE y no por servidor: escribir la URL de otra forma (IP en vez de nombre, otro puerto) no la
	 * salta. La huella no usa las sales de WordPress (rotarlas tampoco la salta).
	 */
	const BLOCK = 'digitalisimo_hosting_webuzo_block';
	/** Operaciones que cambian algo en Webuzo: nunca sin la consulta previa de sólo lectura (act=users) en esta petición. */
	const MUTATING = array( 'add_user', 'suspend', 'unsuspend' );

	private $url;
	private $user;
	private $key;
	private $insecure;
	/** La consulta de sólo lectura ya respondió bien en esta petición (con esta clave). */
	private $verified = false;

	public function __construct( $url, $user, $key, $insecure = false ) {
		$this->url      = rtrim( (string) $url, '/' );
		$this->user     = (string) $user;
		$this->key      = (string) $key;
		$this->insecure = (bool) $insecure;
	}

	/** El servidor de los ajustes de este sitio (con la herencia de la red y las constantes de wp-config.php). */
	public static function from_settings() {
		$o = Digitalisimo_Hosting::settings();
		return self::from_values( $o );
	}

	public static function from_values( array $o ) {
		return new self( $o['webuzo_url'] ?? '', $o['webuzo_api_user'] ?? '', $o['webuzo_api_key'] ?? '', ! empty( $o['webuzo_insecure'] ) );
	}

	public function configured() { return (bool) preg_match( '#^https://#i', $this->url ) && '' !== $this->user && '' !== $this->key; }

	/** Identificador del servidor (URL y usuario), sin la clave. Sólo informativo: la parada no depende de él. */
	public function server() { return substr( md5( strtolower( $this->url ) . '|' . strtolower( $this->user ) ), 0, 12 ); }

	/** Huella no reversible de una clave (SHA-256 recortado, sin sales: una clave de API ya es aleatoria). */
	public static function fingerprint_of( $key ) { return '' === (string) $key ? '' : substr( hash( 'sha256', 'digitalisimo-webuzo-key|' . $key ), 0, 24 ); }

	private function fingerprint() { return self::fingerprint_of( $this->key ); }

	/** El panel de los clientes: el mismo servidor en el puerto 2003. */
	public function panel_url() {
		$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( $this->url, PHP_URL_HOST ) : parse_url( $this->url, PHP_URL_HOST );
		$url  = $host ? 'https://' . ( false !== strpos( $host, ':' ) ? '[' . trim( $host, '[]' ) . ']' : $host ) . ':2003/' : '';
		return function_exists( 'apply_filters' ) ? (string) apply_filters( 'digitalisimo_hosting_panel_url', $url, $this->url ) : $url;
	}

	/** La parada de esta clave (o null): sigue hasta guardar otra clave o marcar «Ya corregí la causa». */
	public function blocked() {
		$fp = $this->fingerprint();
		if ( '' === $fp ) return null;
		foreach ( (array) get_site_option( self::BLOCK, array() ) as $row ) if ( is_array( $row ) && hash_equals( (string) ( $row['key'] ?? '' ), $fp ) ) return $row;
		return null;
	}

	/** ¿Hay alguna parada guardada (de cualquier clave)? */
	public static function any_block() {
		foreach ( (array) get_site_option( self::BLOCK, array() ) as $row ) if ( is_array( $row ) ) return $row;
		return null;
	}

	/**
	 * Quita la parada de una clave (la que se reemplazó al guardar otra, o la misma con «Ya corregí la causa») y avisa
	 * para volver a encolar lo detenido. Las paradas de otras claves siguen.
	 */
	public static function forget( $key ) {
		$fp = self::fingerprint_of( $key );
		$all = (array) get_site_option( self::BLOCK, array() );
		$had = false;
		foreach ( $all as $k => $row ) if ( ! is_array( $row ) || ( '' !== $fp && hash_equals( (string) ( $row['key'] ?? '' ), $fp ) ) ) { unset( $all[ $k ] ); $had = true; }
		if ( $had ) { if ( $all ) update_site_option( self::BLOCK, $all ); else delete_site_option( self::BLOCK ); }
		return $had;
	}

	/** Tras guardar: retoma lo detenido (con una clave nueva, o con la misma tras «Ya corregí la causa»). */
	public static function resume() { if ( function_exists( 'do_action' ) ) do_action( 'digitalisimo_hosting_webuzo_unblocked', true ); }

	/** «Ya corregí la causa» con esta clave: quita su parada y retoma. */
	public function unblock_mine( $force = false ) {
		if ( ! $force ) return false;
		$had = self::forget( $this->key );
		self::resume();
		return $had;
	}

	private function block( $message ) {
		$all = (array) get_site_option( self::BLOCK, array() );
		$row = array( 't' => time(), 'msg' => self::clean( $message ), 'key' => $this->fingerprint() );
		$all[ $row['key'] ] = $row;
		// Se recuerdan las claves rechazadas (no sólo la última): alternar A, B, A no vuelve a gastar intentos con A.
		$all = array_slice( $all, -30, null, true );
		update_site_option( self::BLOCK, $all );
		if ( function_exists( 'do_action' ) ) do_action( 'digitalisimo_hosting_webuzo_blocked', $row );
	}

	/** El aviso de la pantalla de ajustes (sitio o red) mientras la clave de esa pantalla esté detenida. */
	public static function settings_banner( $network = false, $o = null ) {
		$row = is_array( $o ) ? self::from_values( $o )->blocked() : self::any_block();
		if ( ! $row ) return;
		echo '<div class="notice notice-error inline"><p><strong>Aprovisionamiento detenido:</strong> Webuzo rechazó la clave de API el ' . esc_html( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', (int) $row['t'] ) : gmdate( 'Y-m-d H:i', (int) $row['t'] ) ) . ' (' . esc_html( (string) $row['msg'] ) . '). No se hará ninguna llamada a Webuzo con esta clave hasta que escribas y guardes una clave nueva aquí' . ( $network ? '' : ' (o en la red, si este sitio la hereda)' ) . '. Antes, revisa en Webuzo que la clave exista y que la IP de este servidor esté autorizada.</p><p><label><input type="checkbox" name="hosting[webuzo_unblock]" value="1"> Ya corregí la causa (por ejemplo, autoricé la IP): reintentar con la misma clave al guardar</label></p></div>';
	}

	/**
	 * Una llamada a la API. Devuelve array( 'kind' => ok|transient|auth|api|blocked|config, 'data' => JSON, 'message' ).
	 *   transient  sólo fallas de transporte: sin conexión, tiempo agotado, 5xx/408/429 sin cuerpo de la API.
	 *   auth       la API rechazó o contestó algo que no se reconoce: parada de esta clave, nunca se reintenta. La
	 *              consulta de sólo lectura (act=users) es CERRADA: cualquier error suyo, en cualquier idioma, o una
	 *              respuesta sin la forma esperada, se trata como clave rechazada.
	 *   api        error de una operación (add_user, suspend, unsuspend) que no es de clave; no se reintenta.
	 *   blocked    hay una parada vigente para esta clave: no se llamó.
	 *   config     faltan la URL, el usuario o la clave: no se llamó.
	 * Las operaciones que cambian algo hacen antes, en la misma petición, la consulta de sólo lectura: ninguna ruta
	 * (crear, suspender, reactivar, renovar, reintento manual) llama a Webuzo sin pasar por esa decisión.
	 */
	public function call( $act, array $post = array(), array $query = array() ) {
		if ( ! $this->configured() ) return array( 'kind' => 'config', 'data' => array(), 'message' => 'Faltan la URL, el usuario o la clave de la API de Webuzo en Digitalisimo → Hosting.' );
		$blocked = $this->blocked();
		if ( $blocked ) return array( 'kind' => 'blocked', 'data' => array(), 'message' => 'Aprovisionamiento detenido: Webuzo rechazó la clave de API (' . $blocked['msg'] . '). Guarda una clave nueva en Digitalisimo → Hosting.' );
		$op = 'users' === $act ? ( isset( $post['suspend'] ) ? 'suspend' : ( isset( $post['unsuspend'] ) ? 'unsuspend' : 'list' ) ) : $act;
		if ( 'list' !== $op && ! $this->verified ) {
			$pre = $this->users();
			if ( 'ok' !== $pre['kind'] ) return $pre;
		}
		$url      = $this->url . '/index.php?api=json&act=' . rawurlencode( $act ) . ( $query ? '&' . http_build_query( $query ) : '' );
		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 45,
				'redirection' => 0,
				'sslverify'   => ! $this->insecure,
				'body'        => array( 'apiuser' => $this->user, 'apikey' => $this->key ) + $post,
			)
		);
		self::$redact = array_filter( array( $this->key, (string) ( $post['user_passwd'] ?? '' ) ), 'strlen' );
		$result = self::classify( $response );
		self::$redact = array();
		if ( 'list' === $op ) {
			// Cerrada: un error de la consulta de sólo lectura es una clave rechazada, diga lo que diga.
			if ( 'api' === $result['kind'] ) $result['kind'] = 'auth';
			if ( 'ok' === $result['kind'] && ( ! isset( $result['data']['users'] ) || ! is_array( $result['data']['users'] ) ) ) $result = array( 'kind' => 'auth', 'data' => array(), 'message' => 'La lista de cuentas de Webuzo no trae «users»; se trata como clave rechazada para no gastar intentos' );
		} elseif ( 'ok' === $result['kind'] && ! isset( $result['data']['done'] ) ) {
			// Crear, suspender y reactivar sólo cuentan como hechos con la confirmación «done» de la documentación.
			$result = array( 'kind' => 'api', 'data' => $result['data'], 'message' => 'Webuzo no confirmó la operación (sin «done»)' );
		}
		// add_user devuelve la contraseña EN CLARO en done.data.user_passwd (prueba real): no sale de aquí, ni en $result.
		if ( 'add_user' === $op ) $result['data'] = isset( $result['data']['done'] ) ? array( 'done' => array( 'msg' => $result['message'] ) ) : ( isset( $result['data']['error'] ) ? array( 'error' => $result['data']['error'] ) : array() );
		if ( 'auth' === $result['kind'] ) $this->block( $result['message'] );
		if ( 'list' === $op && 'ok' === $result['kind'] ) $this->verified = true;
		return $result;
	}

	/** Clasifica la respuesta (público para las pruebas). */
	public static function classify( $response ) {
		if ( is_wp_error( $response ) ) return array( 'kind' => 'transient', 'data' => array(), 'message' => 'Sin respuesta de Webuzo: ' . self::clean( $response->get_error_message() ) );
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( 401 === $code || 403 === $code ) return array( 'kind' => 'auth', 'data' => array(), 'message' => 'Webuzo respondió HTTP ' . $code . ' (acceso denegado)' );
		if ( ! is_array( $json ) ) {
			if ( 0 === $code || 408 === $code || 429 === $code || $code >= 500 ) return array( 'kind' => 'transient', 'data' => array(), 'message' => 'Webuzo no pudo atender (HTTP ' . $code . ')' );
			// Una página de acceso o cualquier cosa que no es la API: se trata como clave rechazada para no gastar intentos.
			return array( 'kind' => 'auth', 'data' => array(), 'message' => 'Respuesta no reconocida de Webuzo (HTTP ' . $code . '); se trata como clave rechazada para no gastar intentos' );
		}
		if ( ! empty( $json['error'] ) ) {
			$message = self::flatten( $json['error'] );
			return array( 'kind' => self::is_auth_message( $message ) ? 'auth' : 'api', 'data' => $json, 'message' => $message );
		}
		// `done` es un objeto ({msg: …}, en add_user también data y user): el mensaje es done['msg'].
		$done = $json['done'] ?? null;
		return array( 'kind' => 'ok', 'data' => $json, 'message' => is_array( $done ) && isset( $done['msg'] ) ? self::clean( (string) $done['msg'] ) : ( null !== $done ? self::flatten( $done ) : '' ) );
	}

	/**
	 * ¿El error de una operación es de credenciales o del bloqueo por intentos? Textos de
	 * https://webuzo.com/docs/api/brute-force/ (configurables por el administrador de Webuzo, también en español). Es la
	 * segunda red: la primera es la consulta de sólo lectura cerrada que precede a toda operación.
	 */
	public static function is_auth_message( $message ) {
		// Sólo textos de autenticación conocidos: un error de validación («La contraseña debe tener al menos 20
		// caracteres») no detiene todo. La consulta de sólo lectura sigue cerrada (cualquier error la detiene).
		return (bool) preg_match( '/incorrect\s+(user\s*name|username|password)|invalid\s+(api\s*key|apikey|login|credentials?)|attempt\(s\)\s*left|attempts?\s+left|exceeded\s+maximum\s+login|blacklist|unauthori[sz]ed|not\s+authori[sz]ed|authentication\s+failed|access\s+denied|please\s+log\s*in|usuario\s+o\s+contrase(ñ|n)a\s+incorrect|intento\(s\)|intentos?\s+restantes?|le\s+quedan?\s+\d+\s+intento|bloquead[ao]|lista\s+negra|acceso\s+denegado|no\s+autorizad[ao]|clave\s+(de\s+)?api\s+(no\s+v[aá]lida|inv[aá]lida|incorrecta)/iu', (string) $message );
	}

	/** Un error de Webuzo puede ser un texto o una lista; se junta en una línea sin HTML. */
	private static function flatten( $value ) {
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $k => $v ) $parts[] = ( is_string( $k ) && ! is_numeric( $k ) ? $k . ': ' : '' ) . self::flatten( $v );
			return implode( '; ', array_filter( $parts, 'strlen' ) );
		}
		return self::clean( (string) $value );
	}

	/** Secretos de la llamada en curso (la clave y la contraseña): se tapan antes de recortar cualquier mensaje. */
	private static $redact = array();

	private static function clean( $text ) {
		$text = (string) $text;
		foreach ( self::$redact as $secret ) $text = str_replace( $secret, '••••', $text );
		return substr( trim( preg_replace( '/\s+/', ' ', strip_tags( $text ) ) ), 0, 300 );
	}

	/** Cuentas del servidor: usuario => array( 'user', 'domain', 'status', … ). Es la consulta de sólo lectura. */
	public function users() {
		$r = $this->call( 'users' );
		if ( 'ok' !== $r['kind'] ) return $r;
		$users = self::parse_users( $r['data']['users'] );
		// La lista real pagina (prueba real autorizada del 2026-11-24, webuzo-real/): trae num_res (total), reslen (50 por
		// página) y cur_page (desplazamiento). La página siguiente se pide con `page` EN LA URL, empezando en 1 (page=2 da
		// las cuentas 50…99); `page` en el cuerpo del POST se ignora. Cada página es la misma consulta cerrada. Si aun así
		// faltan cuentas, se marca «partial»: un usuario o dominio repetido lo rechaza Webuzo y el pedido queda en espera,
		// nunca se duplica.
		$total = (int) ( $r['data']['num_res'] ?? 0 );
		$per   = (int) ( $r['data']['reslen'] ?? 0 );
		for ( $page = 2; $per > 0 && $total > count( $users ) && $page <= (int) ceil( $total / $per ) && $page < 100; $page++ ) {
			$p = $this->call( 'users', array(), array( 'page' => $page ) );
			if ( 'ok' !== $p['kind'] ) return $p;
			$before = count( $users );
			$users += self::parse_users( $p['data']['users'] );
			if ( count( $users ) === $before ) break;
		}
		$r['users'] = $users;
		$r['partial'] = $total > count( $users );
		return $r;
	}

	private static function parse_users( $rows ) {
		$users = array();
		foreach ( (array) $rows as $name => $row ) {
			$row = (array) $row;
			$user = strtolower( (string) ( $row['user'] ?? $name ) );
			if ( '' !== $user ) $users[ $user ] = array( 'user' => $user, 'domain' => strtolower( (string) ( $row['domain'] ?? '' ) ), 'status' => (string) ( $row['status'] ?? '' ), 'plan' => (string) ( $row['plan'] ?? '' ) );
		}
		return $users;
	}

	public function create_user( $user, $domain, $password, $email, $plan ) {
		return $this->call( 'add_user', array( 'create_user' => 1, 'user' => $user, 'domain' => $domain, 'user_passwd' => $password, 'cnf_user_passwd' => $password, 'email' => $email, 'plan' => $plan ) );
	}

	/** Suspender y reactivar: antes la consulta de sólo lectura, y la cuenta tiene que existir. */
	public function suspend( $user ) { return $this->toggle( $user, 'suspend' ); }

	public function unsuspend( $user ) { return $this->toggle( $user, 'unsuspend' ); }

	private function toggle( $user, $op ) {
		$list = $this->users();
		if ( 'ok' !== $list['kind'] ) return $list;
		if ( ! isset( $list['users'][ strtolower( (string) $user ) ] ) ) return array( 'kind' => 'api', 'data' => array(), 'message' => 'La cuenta «' . $user . '» no existe en Webuzo' );
		// Sin `skip` (ver arriba): con skip=1 este Webuzo responde éxito sin hacer nada.
		return $this->call( 'users', array( $op => $user ) );
	}

	/** El estado actual de una cuenta («suspended» o ""), o null si no está o la lista no respondió (con $r el resultado). */
	public function status_of( $user, &$r = null ) {
		$r = $this->users();
		if ( 'ok' !== $r['kind'] ) return null;
		$u = $r['users'][ strtolower( (string) $user ) ] ?? null;
		return $u ? (string) $u['status'] : null;
	}
}
