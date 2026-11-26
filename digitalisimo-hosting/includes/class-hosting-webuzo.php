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

	public function configured() { return self::safe_url( $this->url ) && '' !== $this->user && '' !== $this->key; }

	/**
	 * ¿URL aceptable para mandarle la clave? Sólo https, con host (y puerto), sin usuario, ruta ni consulta, y que el host
	 * no lleve a loopback, link-local, redes privadas, 0.0.0.0, CGNAT ni IPv4 dentro de IPv6 (mapeadas o 64:ff9b::),
	 * salvo la constante DIGITALISIMO_WEBUZO_ALLOW_PRIVATE. Se compara contra la IP: las formas numéricas (127.1,
	 * 2130706433, 0x7f000001, 0177.0.0.1, 0) se normalizan como lo haría el sistema y los nombres se resuelven (A y AAAA).
	 * Un nombre que hoy no resuelve no se puede comprobar: se acepta (la llamada fallaría igual).
	 */
	public static function safe_url( $url ) {
		if ( ! preg_match( '#^https://(\[[0-9a-f:.]+\]|[a-z0-9.-]+)(:\d{1,5})?/?$#i', (string) $url, $m ) ) return false;
		$host = strtolower( rtrim( trim( $m[1], '[]' ), '.' ) );
		if ( defined( 'DIGITALISIMO_WEBUZO_ALLOW_PRIVATE' ) && DIGITALISIMO_WEBUZO_ALLOW_PRIVATE ) return true;
		if ( '' === $host || 'localhost' === $host || preg_match( '/\.(localhost|local|internal|home\.arpa|lan)$/', $host ) ) return false;
		foreach ( self::host_ips( $host ) as $ip ) if ( ! self::public_ip( $ip ) ) return false;
		return true;
	}

	private static function host_ips( $host ) {
		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) return array( $host );
		$v4 = self::inet_aton( $host );
		if ( null !== $v4 ) return array( $v4 );
		/** Para pruebas y redes con DNS propio: una lista de IP evita la resolución. */
		$pre = apply_filters( 'digitalisimo_hosting_webuzo_host_ips', null, $host );
		if ( is_array( $pre ) ) return $pre;
		$ips = function_exists( 'gethostbynamel' ) ? (array) @gethostbynamel( $host ) : array();
		if ( function_exists( 'dns_get_record' ) && defined( 'DNS_AAAA' ) ) foreach ( (array) @dns_get_record( $host, DNS_AAAA ) as $rec ) if ( ! empty( $rec['ipv6'] ) ) $ips[] = $rec['ipv6'];
		return array_values( array_filter( $ips ) );
	}

	/** Forma numérica de IPv4 como inet_aton (1 a 4 partes, decimal, octal con 0 o hex con 0x), o null si no es numérica. */
	private static function inet_aton( $host ) {
		if ( ! preg_match( '/^(0x[0-9a-f]*|[0-9]+)(\.(0x[0-9a-f]*|[0-9]+)){0,3}$/i', $host ) ) return null;
		$n = array();
		foreach ( explode( '.', $host ) as $p ) {
			if ( preg_match( '/^0x/i', $p ) ) $v = '' === substr( $p, 2 ) ? 0 : hexdec( substr( $p, 2 ) );
			elseif ( strlen( $p ) > 1 && '0' === $p[0] ) { if ( ! preg_match( '/^[0-7]+$/', $p ) ) return '0.0.0.0'; $v = octdec( $p ); }
			else $v = (float) $p;
			$n[] = $v;
		}
		$max = array( 1 => array( 4294967295 ), 2 => array( 255, 16777215 ), 3 => array( 255, 255, 65535 ), 4 => array( 255, 255, 255, 255 ) );
		foreach ( $n as $i => $v ) if ( $v > $max[ count( $n ) ][ $i ] ) return '0.0.0.0';
		switch ( count( $n ) ) {
			case 1: $l = $n[0]; break;
			case 2: $l = $n[0] * 16777216 + $n[1]; break;
			case 3: $l = $n[0] * 16777216 + $n[1] * 65536 + $n[2]; break;
			default: $l = $n[0] * 16777216 + $n[1] * 65536 + $n[2] * 256 + $n[3];
		}
		return long2ip( (int) $l );
	}

	private static function public_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$b = @inet_pton( $ip );
			if ( false === $b || 16 !== strlen( $b ) ) return false;
			if ( substr( $b, 0, 12 ) === str_repeat( "\0", 10 ) . "\xff\xff" || substr( $b, 0, 12 ) === "\x00\x64\xff\x9b" . str_repeat( "\0", 8 ) ) return self::public_ip( inet_ntop( substr( $b, 12 ) ) );
			if ( substr( $b, 0, 12 ) === str_repeat( "\0", 12 ) ) return false; // ::, ::1 y las compatibles con IPv4
			if ( preg_match( '/^(fe[89ab]|f[cd])/i', bin2hex( substr( $b, 0, 2 ) ) ) ) return false; // link-local y únicas locales
			return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return false;
		return ! preg_match( '/^(127\.|169\.254\.|0\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.)/', $ip );
	}

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
		echo '<div class="notice notice-error inline"><p><strong>Aprovisionamiento detenido:</strong> Webuzo rechazó la clave de API el ' . esc_html( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', (int) $row['t'] ) : gmdate( 'Y-m-d H:i', (int) $row['t'] ) ) . ' (' . esc_html( (string) $row['msg'] ) . '). No se hará ninguna llamada a Webuzo con esta clave hasta que escribas y guardes una clave nueva aquí' . ( $network ? '' : ' (o en la red, si este sitio la hereda)' ) . '. Antes, revisa en Webuzo que la clave exista y que la IP de este servidor esté autorizada.</p>' . ( ! is_multisite() || current_user_can( 'manage_network_options' ) ? '<p><label><input type="checkbox" name="hosting[webuzo_unblock]" value="1"> Ya corregí la causa (por ejemplo, autoricé la IP): reintentar con la misma clave al guardar</label></p>' : '<p class="description">Sólo el superadministrador de la red puede reintentar con la misma clave.</p>' ) . '</div>';
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
		// Lo que el pedido mismo pone en el mensaje (dominio, usuario) no cuenta al buscar textos de autenticación:
		// «El dominio lista-negra.mx ya existe» no es un rechazo de clave.
		self::$neutral = array_filter( array( (string) ( $post['domain'] ?? '' ), (string) ( $post['user'] ?? '' ), (string) ( $post['suspend'] ?? '' ), (string) ( $post['unsuspend'] ?? '' ) ), 'strlen' );
		$result = self::classify( $response );
		self::$redact = array(); self::$neutral = array();
		// La consulta de sólo lectura con esta MISMA clave acaba de responder bien: un error de una operación de escritura
		// no puede ser la clave rechazada (es de la operación). Sólo 401/403 siguen contando como acceso denegado.
		if ( 'auth' === $result['kind'] && 'list' !== $op && $this->verified && ! empty( $result['data'] ) ) $result['kind'] = 'api';
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
			$probe   = $message;
			foreach ( self::$neutral as $n ) $probe = str_ireplace( $n, '', $probe );
			return array( 'kind' => self::is_auth_message( $probe ) ? 'auth' : 'api', 'data' => $json, 'message' => $message );
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
	/** Datos del propio pedido (dominio, usuario) que no cuentan al buscar textos de autenticación. */
	private static $neutral = array();

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
	public function suspend( $user, $domain = null ) { return $this->toggle( $user, 'suspend', $domain ); }

	public function unsuspend( $user, $domain = null ) { return $this->toggle( $user, 'unsuspend', $domain ); }

	/**
	 * Suspender o reactivar por nombre de usuario, sólo si la cuenta sigue siendo la del dominio del servicio: si se borró
	 * y otro cliente heredó el nombre, no se toca nada (kind «mismatch»).
	 */
	private function toggle( $user, $op, $domain = null ) {
		$list = $this->users();
		if ( 'ok' !== $list['kind'] ) return $list;
		$row = $list['users'][ strtolower( (string) $user ) ] ?? null;
		if ( ! $row ) return array( 'kind' => 'api', 'data' => array(), 'message' => 'La cuenta «' . $user . '» no existe en Webuzo' );
		if ( null !== $domain && strtolower( (string) $domain ) !== (string) $row['domain'] ) return array( 'kind' => 'mismatch', 'data' => array(), 'message' => 'La cuenta «' . $user . '» de Webuzo es hoy del dominio ' . $row['domain'] . ', no de ' . strtolower( (string) $domain ) . ': no se tocó' );
		// Sin `skip` (ver arriba): con skip=1 este Webuzo responde éxito sin hacer nada.
		return $this->call( 'users', array( $op => $user ) );
	}

	/** El estado actual de una cuenta («suspended» o ""), o null si no está o la lista no respondió (con $r el resultado). */
	public function status_of( $user, &$r = null, $domain = null ) {
		$r = $this->users();
		if ( 'ok' !== $r['kind'] ) return null;
		$u = $r['users'][ strtolower( (string) $user ) ] ?? null;
		if ( $u && null !== $domain && strtolower( (string) $domain ) !== (string) $u['domain'] ) { $r['mismatch'] = $u['domain']; return null; }
		return $u ? (string) $u['status'] : null;
	}
}
