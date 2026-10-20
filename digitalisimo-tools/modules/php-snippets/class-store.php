<?php
namespace Digitalisimo\Tools\Php_Snippets;
defined( 'ABSPATH' ) || exit;

/**
 * Datos de «Código PHP»: claves, indicador, bloqueo, firma, historial, registro, conciliación y saneamiento.
 *
 * Reglas (especificación F1b, §3):
 * - El front lee lo mínimo: el indicador autoload `FLAG` y, sólo si existe, `_run` (sin autoload); en la red, una opción de
 *   `sitemeta` que Runner::prime() precarga junto con la de Ajustes del sitio.
 * - El front sólo escribe filas nuevas con `INSERT IGNORE` (insert_row()): filas `off_*`, `badsig_*` y `rescue_req`.
 * - Todo lo demás lo escriben el escritorio o el cron dentro de mutate(), que toma un bloqueo y relee sin caché.
 *
 * Ámbitos: 'site' (opciones del sitio actual) y 'network' (sitemeta, sólo con is_multisite()). Cada método que recibe un
 * ámbito lo normaliza con scope(): 'network' fuera de una red se trata como 'site'.
 *
 * Formas guardadas:
 * - `_run`:   { v:1, kid, safe, items:{ <id>:{ title, code, hash, sig, at, by } }, paused:{ <id de red>:{ at, by } } }
 *             en la red: { v:1, kid, safe, safe_sites, items:{ <id>:{ title, code, hash, sig, rev, at, by } } }
 * - índice:   { v:1, gone:{ '<source:id>':true }, items:{ <id>:{ title, status:'inactive'|'failed', draft_hash, origin_key,
 *             review, conflict, error:{ kind, at, message, line, path, hash, seen }, modified, by, rev } } }
 *             (`rev`, sólo en la red: el último `rev` usado, para que reactivar no resucite pausas viejas)
 * - ficha:    { code, dev, dsig, kid, history:[ { code, hash, at, by, event, activated, sig, rev, hsig } ], check:[], origin:[],
 *             created, created_by }. El borrador lleva su procedencia (`dev`: saved, origin o unsigned) y su firma (`dsig`), y
 *             cada versión del historial la suya (`hsig`): una escritura directa en la BD no puede hacerse pasar por un
 *             guardado desde el editor (record_signature()).
 * - registro: [ { t, user, blog_id, id, h12, event, msg, ip? } ], el más reciente primero, 200 como mucho.
 */
final class Store {
	const FLAG           = 'digitalisimo_tools_php_on';
	const SITE_RUN       = 'digitalisimo_tools_php_run';
	const SITE_INDEX     = 'digitalisimo_tools_php';
	const SITE_ITEM      = 'digitalisimo_tools_php_item_';
	const SITE_LOG       = 'digitalisimo_tools_php_log';
	const NETWORK_RUN    = 'digitalisimo_tools_php_network_run';
	const NETWORK_INDEX  = 'digitalisimo_tools_php_network';
	const NETWORK_ITEM   = 'digitalisimo_tools_php_network_item_';
	const NETWORK_LOG    = 'digitalisimo_tools_php_network_log';
	const NETWORK_MAILED = 'digitalisimo_tools_php_network_mailed';
	const RESCUE         = 'digitalisimo_tools_php_rescue';
	const RESCUE_REQ     = 'digitalisimo_tools_php_rescue_req';
	/** En una red, el turno de la petición pública de rescate, en la tabla del sitio principal: uno cada 15 minutos en toda la red. */
	const RESCUE_REQ_NETWORK = 'digitalisimo_tools_php_rescue_req_network';
	const OFF_SITE       = 'digitalisimo_tools_php_off_s_';
	const OFF_NETWORK    = 'digitalisimo_tools_php_off_n_';
	const BADSIG_SITE    = 'digitalisimo_tools_php_badsig_s';
	const BADSIG_NETWORK = 'digitalisimo_tools_php_badsig_n';
	const SITE_LOCK      = 'digitalisimo_tools_php_site.lock';
	const NETWORK_LOCK   = 'digitalisimo_tools_php_network.lock';
	/** Prefijo de los transitorios: `test_<sha256(token)>`, `session_<sha256(sid)>`, `result_<sha256(token)>`, `notice_<user>`. */
	const TRANSIENT = 'digitalisimo_tools_php_';
	const CRON      = 'digitalisimo_tools_php_hourly';
	const EVENT     = 'digitalisimo_tools_php_event';
	const PAGE      = 'digitalisimo-tools-elementor-templates';

	const ID_PATTERN        = '/^[0-9a-f]{8}$/D';
	const MAX_CODE          = 65536;   // Por fragmento (lo que se puede activar).
	const MAX_SAVE          = 262144;  // Lo que se acepta guardar como borrador: el historial no admite más.
	const MAX_ACTIVE        = 131072;  // Código activo por ámbito.
	const MAX_ITEMS         = 500;
	const MAX_LOG           = 200;
	const MAX_HISTORY       = 10;
	const MAX_HISTORY_BYTES = 262144;
	const MAX_TITLE         = 120;
	const LOCK_TTL          = 30;

	const LOCKED_MESSAGE  = 'Otra acción sobre los fragmentos está en curso. Vuelve a intentarlo en unos segundos.';
	const CHANGED_MESSAGE = 'El fragmento cambió durante la prueba; vuelve a pulsar «Guardar y activar».';

	/** Eventos que también van a error_log() (sólo evento, ámbito, sitio, id, h12 y usuario; §5.6). */
	const SECURITY_EVENTS = array( 'activated', 'deactivated', 'failed', 'safe_on', 'safe_off', 'resumed', 'deleted', 'imported', 'bad_signature_seen', 'rescue_created', 'rescue_used', 'rescue_revoked' );
	/** Únicos eventos que guardan la IP en el registro de la BD. */
	const IP_EVENTS = array( 'activated', 'deactivated', 'safe_on', 'rescue_used' );

	/** Textos de los estados visibles (§4.1). */
	const STATES = array(
		'active'       => 'Activo',
		'changed'      => 'Activo · cambios sin activar',
		'inactive'     => 'Inactivo',
		'failed'       => 'Desactivado por error',
		'signature'    => 'Detenido · firma no válida',
		'review'       => 'Importado · sin revisar',
		'origin'       => 'Importado · el origen cambió',
		'here_active'  => 'Activo en este sitio',
		'here_error'   => 'Pausado aquí por un error',
		'here_manual'  => 'Pausado aquí a mano',
		'here_stopped' => 'Detenido en este sitio',
	);

	/** Ámbitos cuyo bloqueo tiene esta petición: ámbito → valor de la fila. */
	private static $held = array();
	/** Eventos pendientes de `do_action` y correos, que se disparan al soltar el último bloqueo. */
	private static $events = array();
	private static $after  = array();
	/** Clave de firma de esta petición. */
	private static $key = null;
	/** Ámbitos cuya fila `badsig_*` ya se intentó escribir en esta petición. */
	private static $flagged = array();

	/* ------------------------------------------------------------------ *
	 * Nombres y lectura
	 * ------------------------------------------------------------------ */

	/** 'network' sólo dentro de una red; cualquier otro valor es 'site'. */
	public static function scope( $scope ) {
		return ( 'network' === $scope && is_multisite() ) ? 'network' : 'site';
	}

	/** Nombre de la opción de un ámbito: $what ∈ run, index, log, item (con $id). */
	public static function option_name( $scope, $what, $id = '' ) {
		$network = 'network' === self::scope( $scope );
		switch ( $what ) {
			case 'run':   return $network ? self::NETWORK_RUN : self::SITE_RUN;
			case 'index': return $network ? self::NETWORK_INDEX : self::SITE_INDEX;
			case 'log':   return $network ? self::NETWORK_LOG : self::SITE_LOG;
			case 'item':  return ( $network ? self::NETWORK_ITEM : self::SITE_ITEM ) . $id;
		}
		return '';
	}

	/** Los 12 primeros hexadecimales del hash: identifican la versión en rutas y filas. */
	public static function h12( $hash ) {
		return substr( (string) $hash, 0, 12 );
	}

	/** Nombre de la fila `off`: `off_s_<id>_<h12>` o `off_n_<id>_<h12>_<rev>`. */
	public static function off_name( $scope, $id, $hash, $rev = 0 ) {
		return 'network' === $scope ? self::OFF_NETWORK . $id . '_' . self::h12( $hash ) . '_' . (int) $rev : self::OFF_SITE . $id . '_' . self::h12( $hash );
	}

	/** Un id de 8 hexadecimales. Un entero se admite porque PHP convierte en entero una clave de array sólo decimal. */
	public static function valid_id( $id ) {
		if ( is_int( $id ) && $id > 0 ) $id = (string) $id;
		return is_string( $id ) && 1 === preg_match( self::ID_PATTERN, $id );
	}

	/** Lee una opción del ámbito. Dentro de mutate() borra antes su caché para leer la base de datos. */
	public static function get( $scope, $name, $default = array() ) {
		$scope = self::scope( $scope );
		if ( isset( self::$held[ $scope ] ) ) self::forget_cache( $scope, $name );
		$value = 'network' === $scope ? get_site_option( $name, $default ) : get_option( $name, $default );
		return null === $value ? $default : $value;
	}

	/** `_run` normalizado. */
	public static function run( $scope ) {
		return self::normalize_run( self::get( $scope, self::option_name( $scope, 'run' ), array() ), $scope );
	}

	/** Índice normalizado. */
	public static function index( $scope ) {
		return self::normalize_index( self::get( $scope, self::option_name( $scope, 'index' ), array() ) );
	}

	/** Ficha de un fragmento, o null si no existe. */
	public static function item( $scope, $id ) {
		if ( ! self::valid_id( $id ) ) return null;
		$item = self::get( $scope, self::option_name( $scope, 'item', $id ), null );
		return is_array( $item ) ? self::normalize_item( $item ) : null;
	}

	/** Registro del ámbito, el más reciente primero. */
	public static function log_entries( $scope, $limit = self::MAX_LOG ) {
		$log = self::get( $scope, self::option_name( $scope, 'log' ), array() );
		return array_slice( is_array( $log ) ? array_values( $log ) : array(), 0, max( 0, (int) $limit ) );
	}

	public static function normalize_run( $run, $scope ) {
		$run = is_array( $run ) ? $run : array();
		$out = array( 'v' => 1, 'kid' => is_string( $run['kid'] ?? null ) ? $run['kid'] : '', 'safe' => ! empty( $run['safe'] ), 'items' => array() );
		$network = 'network' === self::scope( $scope );
		if ( $network ) $out['safe_sites'] = ! empty( $run['safe_sites'] );
		else $out['paused'] = array();
		foreach ( is_array( $run['items'] ?? null ) ? $run['items'] : array() as $id => $item ) {
			if ( ! self::valid_id( (string) $id ) || ! is_array( $item ) ) continue;
			$entry = array(
				'title' => (string) ( $item['title'] ?? '' ),
				'code'  => (string) ( $item['code'] ?? '' ),
				'hash'  => (string) ( $item['hash'] ?? '' ),
				'sig'   => (string) ( $item['sig'] ?? '' ),
				'at'    => (int) ( $item['at'] ?? 0 ),
				'by'    => (int) ( $item['by'] ?? 0 ),
			);
			if ( $network ) $entry['rev'] = max( 1, (int) ( $item['rev'] ?? 1 ) );
			$out['items'][ (string) $id ] = $entry;
		}
		if ( ! $network ) {
			foreach ( is_array( $run['paused'] ?? null ) ? $run['paused'] : array() as $id => $pause ) {
				if ( self::valid_id( (string) $id ) ) $out['paused'][ (string) $id ] = array( 'at' => (int) ( $pause['at'] ?? 0 ), 'by' => (int) ( $pause['by'] ?? 0 ) );
			}
		}
		return $out;
	}

	public static function normalize_index( $index ) {
		$index = is_array( $index ) ? $index : array();
		$out   = array( 'v' => 1, 'gone' => array(), 'items' => array() );
		foreach ( is_array( $index['gone'] ?? null ) ? $index['gone'] : array() as $key => $gone ) if ( $gone ) $out['gone'][ (string) $key ] = true;
		foreach ( is_array( $index['items'] ?? null ) ? $index['items'] : array() as $id => $item ) {
			if ( ! self::valid_id( (string) $id ) || ! is_array( $item ) ) continue;
			$entry = array(
				'title'      => (string) ( $item['title'] ?? '' ),
				'status'     => 'failed' === ( $item['status'] ?? '' ) ? 'failed' : 'inactive',
				'draft_hash' => (string) ( $item['draft_hash'] ?? '' ),
				'origin_key' => (string) ( $item['origin_key'] ?? '' ),
				'review'     => ! empty( $item['review'] ),
				'conflict'   => ! empty( $item['conflict'] ),
				'modified'   => (int) ( $item['modified'] ?? 0 ),
				'by'         => (int) ( $item['by'] ?? 0 ),
			);
			if ( is_array( $item['error'] ?? null ) ) $entry['error'] = $item['error'];
			if ( isset( $item['rev'] ) ) $entry['rev'] = max( 0, (int) $item['rev'] );
			$out['items'][ (string) $id ] = $entry;
		}
		return $out;
	}

	public static function normalize_item( $item ) {
		$item = is_array( $item ) ? $item : array();
		return array(
			'code'       => (string) ( $item['code'] ?? '' ),
			'dev'        => (string) ( $item['dev'] ?? '' ),
			'dsig'       => (string) ( $item['dsig'] ?? '' ),
			'kid'        => (string) ( $item['kid'] ?? '' ),
			'history'    => array_values( is_array( $item['history'] ?? null ) ? $item['history'] : array() ),
			'check'      => is_array( $item['check'] ?? null ) ? $item['check'] : array(),
			'origin'     => is_array( $item['origin'] ?? null ) ? $item['origin'] : array(),
			'created'    => (int) ( $item['created'] ?? 0 ),
			'created_by' => (int) ( $item['created_by'] ?? 0 ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Escritura
	 * ------------------------------------------------------------------ */

	/** Valor de `autoload` para filas escritas a mano: 'on'/'off' desde WordPress 6.6 y 'yes'/'no' antes (Tools pide 6.0). */
	public static function autoload_value( $on ) {
		$modern = function_exists( 'wp_autoload_values_to_autoload' );
		return $on ? ( $modern ? 'on' : 'yes' ) : ( $modern ? 'off' : 'no' );
	}

	/** Escribe una opción del ámbito; en el sitio, siempre sin autoload. */
	private static function put( $scope, $name, $value ) {
		return 'network' === $scope ? update_site_option( $name, $value ) : update_option( $name, $value, false );
	}

	private static function drop( $scope, $name ) {
		return 'network' === $scope ? delete_site_option( $name ) : delete_option( $name );
	}

	private static function forget_cache( $scope, $name ) {
		if ( ! function_exists( 'wp_cache_delete' ) ) return;
		if ( 'network' === $scope ) {
			$network = (int) get_current_network_id();
			wp_cache_delete( $network . ':' . $name, 'site-options' );
			wp_cache_delete( $network . ':notoptions', 'site-options' );
		} else {
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}
	}

	/**
	 * Escribe `_run` respetando el orden de creación del índice, y mantiene el indicador y el cron: en el sitio, `_run`
	 * (sin autoload) y el indicador (con autoload) existen juntos o no existe ninguno.
	 */
	private static function write_run( $scope, array $run, $index = null ) {
		$run          = self::normalize_run( $run, $scope );
		$index        = $index ?: self::index( $scope );
		$run['items'] = self::creation_order( $run['items'], $index );
		$name         = self::option_name( $scope, 'run' );
		if ( 'network' === $scope ) {
			if ( ! $run['items'] && ! $run['safe'] && ! $run['safe_sites'] ) delete_site_option( $name );
			else update_site_option( $name, $run );
			self::sync_cron( 'network', ! empty( $run['items'] ) );
			return;
		}
		if ( ! $run['items'] && ! $run['paused'] && ! $run['safe'] ) {
			delete_option( $name );
			delete_option( self::FLAG );
			self::flush_alloptions();
			self::sync_cron( 'site', false );
			return;
		}
		update_option( $name, $run, false );
		if ( '1' !== get_option( self::FLAG ) ) {
			update_option( self::FLAG, '1', true );
			self::flush_alloptions();
		}
		self::sync_cron( 'site', true );
	}

	/** Ordena los ítems de `_run` como el índice (orden de creación); lo que no figura en el índice va al final. */
	private static function creation_order( array $items, array $index ) {
		$out = array();
		foreach ( array_keys( $index['items'] ) as $id ) if ( isset( $items[ $id ] ) ) $out[ $id ] = $items[ $id ];
		return $out + $items;
	}

	private static function flush_alloptions() {
		if ( function_exists( 'wp_cache_delete' ) ) wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Escribe una fila nueva con `INSERT IGNORE` en la tabla de opciones de $blog_id, sin leer ni reescribir nada. Es la
	 * única escritura que hace el front. Después invalida `alloptions` aunque la fila ya existiera (§3.5).
	 *
	 * @return bool true si la fila es nueva.
	 */
	public static function insert_row( $blog_id, $name, $value, $autoload = true ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) return false;
		$table = $wpdb->get_blog_prefix( (int) $blog_id ) . 'options';
		$done  = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` ( option_name, option_value, autoload ) VALUES ( %s, %s, %s )", $name, is_string( $value ) ? $value : serialize( $value ), self::autoload_value( $autoload ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'alloptions', 'options' );
			if ( ! $autoload ) { wp_cache_delete( $name, 'options' ); wp_cache_delete( 'notoptions', 'options' ); }
		}
		return 1 === $done;
	}

	/** Filas cuyo nombre empieza por $prefix en la tabla del sitio actual, leídas sin caché: nombre → valor. */
	private static function rows( $prefix ) {
		global $wpdb;
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM `{$wpdb->options}` WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out   = array();
		foreach ( (array) $found as $row ) $out[ (string) $row['option_name'] ] = self::unserialize( $row['option_value'] );
		return $out;
	}

	/** Deserializa un valor de la base de datos sin crear objetos. */
	public static function unserialize( $value ) {
		if ( ! is_string( $value ) ) return $value;
		if ( function_exists( 'is_serialized' ) ? ! is_serialized( $value ) : ! preg_match( '/^(a|s|i|d|b|N):/', $value ) ) return $value;
		$data = @unserialize( trim( $value ), array( 'allowed_classes' => false ) ); // phpcs:ignore
		return false === $data && 'b:0;' !== trim( $value ) ? $value : $data;
	}

	/* ------------------------------------------------------------------ *
	 * Bloqueo (§3.5)
	 * ------------------------------------------------------------------ */

	/**
	 * Ejecuta $fn con el bloqueo del ámbito tomado. Dentro, get() relee de la base de datos sin caché. Es reentrante: una
	 * llamada anidada del mismo ámbito no vuelve a bloquear. Los eventos registrados durante $fn se disparan al soltar el
	 * último bloqueo, para que un oyente que falle o haga `exit` no deje el bloqueo tomado.
	 *
	 * @return mixed Lo que devuelva $fn, o WP_Error( 'digitalisimo_php_locked' ) si otro proceso tiene el bloqueo.
	 */
	public static function mutate( $scope, callable $fn ) {
		$scope = self::scope( $scope );
		if ( isset( self::$held[ $scope ] ) ) return $fn();
		$token = self::lock( $scope );
		if ( '' === $token ) return new \WP_Error( 'digitalisimo_php_locked', self::LOCKED_MESSAGE, array( 'status' => 409 ) );
		self::$held[ $scope ] = $token;
		try {
			$result = $fn();
		} finally {
			unset( self::$held[ $scope ] );
			self::unlock( $scope, $token );
		}
		if ( ! self::$held ) self::flush_events();
		return $result;
	}

	/** Si esta petición tiene el bloqueo del ámbito. */
	public static function holds( $scope ) {
		return isset( self::$held[ self::scope( $scope ) ] );
	}

	/** Tabla donde vive el bloqueo: la del sitio, o la del sitio principal para la red (`sitemeta` no tiene clave única). */
	private static function lock_table( $scope ) {
		global $wpdb;
		return 'network' === $scope ? $wpdb->get_blog_prefix( get_main_site_id() ) . 'options' : $wpdb->options;
	}

	/** Toma el bloqueo como WP_Upgrader::create_lock(), con un token propio; uno de más de LOCK_TTL s se roba con un UPDATE condicionado. */
	private static function lock( $scope ) {
		global $wpdb;
		$table = self::lock_table( $scope );
		$name  = 'network' === $scope ? self::NETWORK_LOCK : self::SITE_LOCK;
		$value = time() . '|' . bin2hex( random_bytes( 8 ) );
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` ( option_name, option_value, autoload ) VALUES ( %s, %s, %s )", $name, $value, self::autoload_value( false ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `{$table}` WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '' === $held || time() - (int) $held < self::LOCK_TTL ) return '';
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $name, $held ) ) ) return ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( function_exists( 'wp_cache_delete' ) ) wp_cache_delete( $name, 'options' );
		return $value;
	}

	/** Suelta el bloqueo sólo si sigue siendo el nuestro. */
	private static function unlock( $scope, $value ) {
		global $wpdb;
		$table = self::lock_table( $scope );
		$name  = 'network' === $scope ? self::NETWORK_LOCK : self::SITE_LOCK;
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE option_name = %s AND option_value = %s", $name, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( function_exists( 'wp_cache_delete' ) ) wp_cache_delete( $name, 'options' );
	}

	/* ------------------------------------------------------------------ *
	 * Firma (§3.3)
	 * ------------------------------------------------------------------ */

	/** Clave de firma derivada de la sal `secure_auth` de wp-config.php. */
	public static function signing_key() {
		if ( null === self::$key ) self::$key = hash_hmac( 'sha256', 'digitalisimo-tools-php/v1', wp_salt( 'secure_auth' ) );
		return self::$key;
	}

	/** Olvida la clave calculada (las claves de wp-config.php cambiaron en esta petición; sólo lo usan las pruebas). */
	public static function forget_key() {
		self::$key = null;
	}

	/** Huella de la clave, guardada en cada `_run`: clasifica una firma inválida. */
	public static function kid() {
		return substr( hash_hmac( 'sha256', 'kid', self::signing_key() ), 0, 16 );
	}

	/**
	 * Firma `v1|ámbito|red|sitio_o_0|id|hash|rev_o_0`: liga la versión a su ámbito, su red, su sitio y su `rev`.
	 * $blog_id sólo se usa en el ámbito de sitio (por defecto, el actual).
	 */
	public static function sign( $scope, $id, $hash, $rev = 0, $blog_id = null ) {
		$scope   = self::scope( $scope );
		$network = (int) get_current_network_id();
		$blog    = 'network' === $scope ? 0 : (int) ( null === $blog_id ? get_current_blog_id() : $blog_id );
		$rev     = 'network' === $scope ? (int) $rev : 0;
		return hash_hmac( 'sha256', "v1|{$scope}|{$network}|{$blog}|{$id}|{$hash}|{$rev}", self::signing_key() );
	}

	/**
	 * Comprueba una entrada { scope, id, code, hash, sig, rev }: el hash es el del código y la firma es la esperada.
	 */
	public static function verify( array $e ) {
		$code = (string) ( $e['code'] ?? '' );
		$hash = (string) ( $e['hash'] ?? '' );
		$sig  = (string) ( $e['sig'] ?? '' );
		$id   = (string) ( $e['id'] ?? '' );
		if ( '' === $hash || '' === $sig || ! self::valid_id( $id ) || ! hash_equals( hash( 'sha256', $code ), $hash ) ) return false;
		return hash_equals( self::sign( (string) ( $e['scope'] ?? 'site' ), $id, $hash, (int) ( $e['rev'] ?? 0 ) ), $sig );
	}

	/**
	 * Firma de lo que Tools escribe en una ficha: el borrador ($kind 'draft') y cada versión del historial ('hist').
	 * `rec|v1|<kind>|<procedencia>|ámbito|red|sitio_o_0|id|hash`: liga además la procedencia ($event), así que una escritura
	 * directa en la BD no puede convertir código importado o alterado en un guardado desde el editor.
	 */
	public static function record_signature( $kind, $event, $scope, $id, $hash, $blog_id = null ) {
		$scope   = self::scope( $scope );
		$network = (int) get_current_network_id();
		$blog    = 'network' === $scope ? 0 : (int) ( null === $blog_id ? get_current_blog_id() : $blog_id );
		return hash_hmac( 'sha256', "rec|v1|{$kind}|{$event}|{$scope}|{$network}|{$blog}|{$id}|{$hash}", self::signing_key() );
	}

	/**
	 * Firma el borrador de una ficha con su procedencia: 'saved' (lo guardó quien edita, o es la versión que activó),
	 * 'origin' (lo escribió una importación) o 'unsigned' (es el código que se encontró cambiado fuera de Tools).
	 */
	public static function sign_draft( $scope, $id, array $item, $event ) {
		$item         = self::normalize_item( $item );
		$item['dev']  = in_array( $event, array( 'saved', 'origin', 'unsigned' ), true ) ? $event : 'saved';
		$item['dsig'] = self::record_signature( 'draft', $item['dev'], $scope, $id, hash( 'sha256', $item['code'] ) );
		$item['kid']  = self::kid();
		return $item;
	}

	/** Añade una versión al historial con su firma (`hsig`, que liga su procedencia): push_history() con la firma de Tools. */
	public static function remember( $scope, $id, array $history, array $entry ) {
		$entry['hash'] = (string) ( $entry['hash'] ?? hash( 'sha256', (string) ( $entry['code'] ?? '' ) ) );
		$entry['hsig'] = self::record_signature( 'hist', (string) ( $entry['event'] ?? 'saved' ), $scope, $id, $entry['hash'] );
		return self::push_history( $history, $entry );
	}

	/** Si la versión del historial la escribió Tools tal como está: su código, su hash y su procedencia (`hsig`). */
	public static function history_entry_valid( $scope, $id, array $entry ) {
		$hash = (string) ( $entry['hash'] ?? '' );
		if ( '' === $hash || empty( $entry['hsig'] ) || ! is_string( $entry['hsig'] ) || ! hash_equals( hash( 'sha256', (string) ( $entry['code'] ?? '' ) ), $hash ) ) return false;
		return hash_equals( self::record_signature( 'hist', (string) ( $entry['event'] ?? '' ), $scope, $id, $hash ), $entry['hsig'] );
	}

	/**
	 * Estado del borrador de una ficha: 'saved' (lo guardó Tools desde el editor), 'origin' (de una importación, sin revisar),
	 * 'unsigned' (es el código que se encontró cambiado fuera de Tools) o, si su firma no vale, 'database' (la base de datos
	 * cambió fuera de Tools) o 'keys' (cambiaron las claves de wp-config.php).
	 */
	public static function draft_status( $scope, $id, $item = null ) {
		$item = null === $item ? self::item( $scope, $id ) : self::normalize_item( $item );
		if ( ! $item ) return 'saved';
		$hash = hash( 'sha256', $item['code'] );
		if ( '' !== $item['dsig'] && in_array( $item['dev'], array( 'saved', 'origin', 'unsigned' ), true ) && hash_equals( self::record_signature( 'draft', $item['dev'], $scope, $id, $hash ), $item['dsig'] ) ) return $item['dev'];
		return ( '' !== $item['kid'] && ! hash_equals( self::kid(), $item['kid'] ) ) ? 'keys' : 'database';
	}

	/**
	 * La versión más reciente del historial (distinta de $except) que Tools puede garantizar: activada con una firma válida
	 * o guardada desde el editor con la suya. Sirve para el diff de un borrador cambiado fuera de Tools.
	 */
	public static function last_trusted( $scope, $id, array $history, $except = '' ) {
		foreach ( $history as $entry ) {
			if ( ! is_array( $entry ) || ( $entry['hash'] ?? '' ) === $except ) continue;
			if ( ! empty( $entry['activated'] ) ? self::history_signature_valid( $scope, $id, $entry ) : ( 'saved' === ( $entry['event'] ?? '' ) && self::history_entry_valid( $scope, $id, $entry ) ) ) return $entry;
		}
		return null;
	}

	/** Si una versión del historial conserva una firma válida para este sitio (y su `rev`, en la red). */
	public static function history_signature_valid( $scope, $id, array $entry ) {
		if ( empty( $entry['sig'] ) ) return false;
		return self::verify( array( 'scope' => self::scope( $scope ), 'id' => $id, 'code' => (string) ( $entry['code'] ?? '' ), 'hash' => (string) ( $entry['hash'] ?? '' ), 'sig' => (string) $entry['sig'], 'rev' => (int) ( $entry['rev'] ?? 0 ) ) );
	}

	/**
	 * Firma de una sesión o de un token de prueba (§4.4):
	 * `test|<token_hash>|<kind>|<scope>|<red>|<sitio>|<scope:id:hash,…>|<usuario>|<exp>`.
	 *
	 * @param array $s { token (sha256 del token o del sid), kind, scope, network, blog, candidates:[ { scope, id, hash } ], user, exp }
	 */
	public static function session_signature( array $s ) {
		$pairs = array();
		foreach ( (array) ( $s['candidates'] ?? array() ) as $c ) $pairs[] = ( $c['scope'] ?? '' ) . ':' . ( $c['id'] ?? '' ) . ':' . ( $c['hash'] ?? '' );
		$parts = array( 'test', (string) ( $s['token'] ?? '' ), (string) ( $s['kind'] ?? '' ), (string) ( $s['scope'] ?? '' ), (int) ( $s['network'] ?? 0 ), (int) ( $s['blog'] ?? 0 ), implode( ',', $pairs ), (int) ( $s['user'] ?? 0 ), (int) ( $s['exp'] ?? 0 ) );
		return hash_hmac( 'sha256', implode( '|', $parts ), self::signing_key() );
	}

	/** Comprueba la firma de una sesión o de un token de prueba con hash_equals. */
	public static function verify_session( array $s, $sig ) {
		return is_string( $sig ) && 64 === strlen( $sig ) && hash_equals( self::session_signature( $s ), $sig );
	}

	/**
	 * De dónde salen la clave y la sal de `secure_auth`, con las reglas exactas de wp_salt(): una constante cuenta si está
	 * definida, no vacía, no repetida entre las diez y distinta de la frase de muestra (y de su traducción); la clave también
	 * puede venir de SECRET_KEY.
	 *
	 * @param array|null  $constants  Nombre → valor (null: las constantes definidas). Sólo las pruebas lo pasan.
	 * @param string|null $translated Traducción de la frase de muestra (null: la del sitio).
	 * @return array { key: 'SECURE_AUTH_KEY'|'SECRET_KEY'|'database', salt: 'SECURE_AUTH_SALT'|'database' }
	 */
	public static function signature_sources( $constants = null, $translated = null ) {
		if ( null === $constants ) {
			$constants = array();
			foreach ( array( 'AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE', 'SECRET' ) as $first ) foreach ( array( 'KEY', 'SALT' ) as $second ) if ( defined( "{$first}_{$second}" ) ) $constants[ "{$first}_{$second}" ] = constant( "{$first}_{$second}" );
		}
		if ( null === $translated ) $translated = function_exists( '__' ) ? __( 'put your unique phrase here' ) : 'put your unique phrase here';
		$duplicated = array();
		foreach ( array( 'AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE', 'SECRET' ) as $first ) {
			foreach ( array( 'KEY', 'SALT' ) as $second ) {
				if ( ! array_key_exists( "{$first}_{$second}", $constants ) || ! is_scalar( $constants[ "{$first}_{$second}" ] ) ) continue;
				$value                = (string) $constants[ "{$first}_{$second}" ];
				$duplicated[ $value ] = isset( $duplicated[ $value ] );
			}
		}
		$duplicated['put your unique phrase here'] = true;
		$duplicated[ (string) $translated ]       = true;
		$valid = static function ( $name ) use ( $constants, $duplicated ) {
			return array_key_exists( $name, $constants ) && is_scalar( $constants[ $name ] ) && $constants[ $name ] && empty( $duplicated[ (string) $constants[ $name ] ] );
		};
		return array(
			'key'  => $valid( 'SECURE_AUTH_KEY' ) ? 'SECURE_AUTH_KEY' : ( $valid( 'SECRET_KEY' ) ? 'SECRET_KEY' : 'database' ),
			'salt' => $valid( 'SECURE_AUTH_SALT' ) ? 'SECURE_AUTH_SALT' : 'database',
		);
	}

	/** false sólo si la clave y la sal salen las dos de la base de datos: entonces la firma no protege y la pantalla avisa (§7.6). */
	public static function signature_effective( $constants = null, $translated = null ) {
		$sources = self::signature_sources( $constants, $translated );
		return 'database' !== $sources['key'] || 'database' !== $sources['salt'];
	}

	/** Clasifica una firma inválida: 'database' si `kid` es el actual (la BD cambió fuera de Tools) o 'keys' si cambiaron las claves. */
	public static function bad_signature_kind( array $run ) {
		return ( isset( $run['kid'] ) && is_string( $run['kid'] ) && hash_equals( self::kid(), $run['kid'] ) ) ? 'database' : 'keys';
	}

	/**
	 * Anota desde el front, una sola vez, que hay una firma inválida: `INSERT IGNORE` de `badsig_s` o `badsig_n` en el sitio
	 * actual con { t, kid_ok }. No hace nada si la fila ya está en `alloptions` o si ya se intentó en esta petición.
	 */
	public static function flag_bad_signature( $scope, $kid_ok ) {
		$name = 'network' === $scope ? self::BADSIG_NETWORK : self::BADSIG_SITE;
		if ( isset( self::$flagged[ $name ] ) ) return false;
		self::$flagged[ $name ] = true;
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( isset( $all[ $name ] ) ) return false;
		return self::insert_row( get_current_blog_id(), $name, array( 't' => time(), 'kid_ok' => (bool) $kid_ok ), true );
	}

	/* ------------------------------------------------------------------ *
	 * Saneamiento y topes (§3.4, §5.3, §7.8)
	 * ------------------------------------------------------------------ */

	/**
	 * Normaliza el código antes de guardarlo o revisarlo: quita el BOM, pasa `\r\n` a `\n` y quita el `?>` final con sus
	 * espacios (la causa más común de salida al cargarse).
	 */
	public static function normalize_code( $code ) {
		$code = (string) $code;
		if ( 0 === strpos( $code, "\xEF\xBB\xBF" ) ) $code = substr( $code, 3 );
		$code = str_replace( "\r\n", "\n", $code );
		$trim = preg_replace( '/\?>\s*\z/', '', $code );
		if ( is_string( $trim ) && $trim !== $code ) $code = rtrim( $trim ) . "\n";
		return $code;
	}

	/**
	 * Sanea el envío de un fragmento, igual en el sitio y en la red.
	 *
	 * @param array  $input Datos tal como llegan en $_POST (con las barras de WordPress): title, y code_b64 (del JavaScript,
	 *                      base64 estricto) o code (formulario sin JavaScript).
	 * @param string $scope 'site' o 'network'.
	 * @return array|\WP_Error { title, code }
	 */
	public static function sanitize_item( array $input, $scope ) {
		unset( $scope ); // Las reglas son las mismas en los dos ámbitos.
		$title = isset( $input['title'] ) && is_scalar( $input['title'] ) ? sanitize_text_field( wp_unslash( (string) $input['title'] ) ) : '';
		$title = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, self::MAX_TITLE ) : substr( $title, 0, self::MAX_TITLE );
		if ( '' === trim( $title ) ) $title = 'Fragmento sin nombre';
		if ( array_key_exists( 'code_b64', $input ) ) {
			$code = is_string( $input['code_b64'] ) ? base64_decode( $input['code_b64'], true ) : false;
			if ( false === $code ) return new \WP_Error( 'digitalisimo_php_code', 'El código no llegó completo. Vuelve a intentarlo.', array( 'status' => 400 ) );
		} elseif ( isset( $input['code'] ) && is_string( $input['code'] ) ) {
			$code = wp_unslash( $input['code'] );
		} else {
			$code = '';
		}
		if ( strlen( $code ) > self::MAX_SAVE ) return new \WP_Error( 'digitalisimo_php_size', 'El código supera 256 KB y no se guardó.', array( 'status' => 413 ) );
		return array( 'title' => $title, 'code' => self::normalize_code( $code ) );
	}

	/**
	 * Topes para activar $code como versión de $id: 64 KB por fragmento y 128 KB de código activo en el ámbito, contando el
	 * candidato en lugar de su versión activa.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_limits( $scope, $id, $code, $run = null ) {
		$scope = self::scope( $scope );
		if ( strlen( (string) $code ) > self::MAX_CODE ) return new \WP_Error( 'digitalisimo_php_size', 'No se activó: el fragmento supera 64 KB.', array( 'status' => 413 ) );
		$run   = $run ?: self::run( $scope );
		$total = strlen( (string) $code );
		foreach ( $run['items'] as $other => $item ) if ( $other !== $id ) $total += strlen( $item['code'] );
		if ( $total > self::MAX_ACTIVE ) return new \WP_Error( 'digitalisimo_php_size', 'network' === $scope ? 'No se activó: el código activo de la red superaría 128 KB.' : 'No se activó: el código activo de este sitio superaría 128 KB.', array( 'status' => 413 ) );
		return true;
	}

	/**
	 * Id nuevo de 8 hexadecimales que no está en el índice. No usa wp_generate_password(): pasa por el filtro `random_password`.
	 * Nunca es sólo decimal: PHP convertiría la clave «12345678» en el entero 12345678 en cada array del índice y de `_run`.
	 */
	public static function new_id( array $index ) {
		do { $id = bin2hex( random_bytes( 4 ) ); } while ( isset( $index['items'][ $id ] ) || ctype_digit( $id ) );
		return $id;
	}

	/* ------------------------------------------------------------------ *
	 * Historial y registro (§3.1, §6.3)
	 * ------------------------------------------------------------------ */

	/**
	 * Añade una versión al historial (la más reciente primero), sin duplicados por hash: una versión `activated` de un hash
	 * ya guardado lo marca como activado con su firma y lo sube al principio. Conserva como mucho 10 versiones y 256 KB.
	 *
	 * @param array $entry { code, hash, at, by, event: saved|activated|origin|unsigned, sig?, rev? }
	 */
	public static function push_history( array $history, array $entry ) {
		$entry = array(
			'code'      => (string) ( $entry['code'] ?? '' ),
			'hash'      => (string) ( $entry['hash'] ?? hash( 'sha256', (string) ( $entry['code'] ?? '' ) ) ),
			'at'        => (int) ( $entry['at'] ?? time() ),
			'by'        => (int) ( $entry['by'] ?? 0 ),
			'event'     => in_array( $entry['event'] ?? '', array( 'saved', 'activated', 'origin', 'unsigned' ), true ) ? $entry['event'] : 'saved',
			'activated' => 'activated' === ( $entry['event'] ?? '' ),
			'sig'       => (string) ( $entry['sig'] ?? '' ),
			'rev'       => (int) ( $entry['rev'] ?? 0 ),
			'hsig'      => (string) ( $entry['hsig'] ?? '' ),
		);
		$history = array_values( $history );
		foreach ( $history as $i => $old ) {
			if ( ( $old['hash'] ?? '' ) !== $entry['hash'] ) continue;
			if ( ! $entry['activated'] ) return $history;
			unset( $history[ $i ] );
			break;
		}
		array_unshift( $history, $entry );
		$history = array_slice( array_values( $history ), 0, self::MAX_HISTORY );
		while ( count( $history ) > 1 && self::history_bytes( $history ) > self::MAX_HISTORY_BYTES ) array_pop( $history );
		return $history;
	}

	public static function history_bytes( array $history ) {
		$bytes = 0;
		foreach ( $history as $entry ) $bytes += strlen( (string) ( $entry['code'] ?? '' ) );
		return $bytes;
	}

	/**
	 * Anota un evento en el registro del ámbito (200 entradas) y lo encola para `digitalisimo_tools_php_event`. Los eventos
	 * de seguridad van además a error_log() con sólo evento, ámbito, sitio, id, h12 y usuario.
	 *
	 * @param array $data { id, hash|h12, msg, user }; la IP sólo se guarda en los eventos de IP_EVENTS.
	 * @return true|\WP_Error
	 */
	public static function log( $scope, $event, array $data = array() ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $event, $data ) {
			$h12   = (string) ( $data['h12'] ?? self::h12( $data['hash'] ?? '' ) );
			$entry = array(
				't'       => time(),
				'user'    => (int) ( array_key_exists( 'user', $data ) ? $data['user'] : ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 ) ),
				'blog_id' => (int) get_current_blog_id(),
				'id'      => self::valid_id( (string) ( $data['id'] ?? '' ) ) ? (string) $data['id'] : '',
				'h12'     => preg_match( '/^[0-9a-f]{12}$/D', $h12 ) ? $h12 : '',
				'event'   => sanitize_key( (string) $event ),
				'msg'     => self::one_line( (string) ( $data['msg'] ?? '' ), 500 ),
			);
			if ( in_array( $entry['event'], self::IP_EVENTS, true ) && '' !== ( $ip = self::ip() ) ) $entry['ip'] = $ip;
			$name = self::option_name( $scope, 'log' );
			$log  = self::get( $scope, $name, array() );
			$log  = is_array( $log ) ? array_values( $log ) : array();
			array_unshift( $log, $entry );
			self::put( $scope, $name, array_slice( $log, 0, self::MAX_LOG ) );
			if ( in_array( $entry['event'], self::SECURITY_EVENTS, true ) ) error_log( sprintf( 'DIGITALISIMO Tools · Código PHP: evento=%s ámbito=%s sitio=%d id=%s h12=%s usuario=%d', $entry['event'], $scope, $entry['blog_id'], $entry['id'], $entry['h12'], $entry['user'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			self::$events[] = array( $entry['event'], array_merge( $entry, array( 'scope' => $scope ) ) );
			return true;
		} );
	}

	/** Dispara los eventos y envía los correos pendientes, ya fuera del bloqueo: un SMTP lento no retiene el bloqueo. */
	private static function flush_events() {
		while ( self::$events || self::$after ) {
			if ( self::$events ) {
				list( $event, $data ) = array_shift( self::$events );
				if ( function_exists( 'do_action' ) ) do_action( self::EVENT, $event, $data );
				continue;
			}
			$fn = array_shift( self::$after );
			$fn();
		}
	}

	/** Ejecuta $fn al soltar el último bloqueo (o ya, si no hay ninguno tomado). */
	private static function after( callable $fn ) {
		if ( self::$held ) self::$after[] = $fn;
		else $fn();
	}

	/** IP del visitante, sólo si es una dirección válida. */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** Texto en una línea, sin ABSPATH ni caracteres de control, con $max caracteres como mucho. */
	public static function one_line( $text, $max ) {
		$text = (string) $text;
		if ( defined( 'ABSPATH' ) && '' !== ABSPATH ) $text = str_replace( ABSPATH, '', $text );
		$text = trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	/* ------------------------------------------------------------------ *
	 * Operaciones del escritorio (todas bajo bloqueo)
	 * ------------------------------------------------------------------ */

	/**
	 * Crea un fragmento inactivo.
	 *
	 * @param array $extra { index: { origin_key, review, conflict }, item: { origin, check }, event: saved|origin, log: bool }
	 * @return string|\WP_Error El id nuevo.
	 */
	public static function create( $scope, $title, $code, $by = 0, array $extra = array() ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $title, $code, $by, $extra ) {
			$index = self::index( $scope );
			if ( count( $index['items'] ) >= self::MAX_ITEMS ) return new \WP_Error( 'digitalisimo_php_items', 'network' === $scope ? 'La red ya tiene 500 fragmentos, el máximo.' : 'Este sitio ya tiene 500 fragmentos, el máximo.', array( 'status' => 413 ) );
			$id   = self::new_id( $index );
			$code = (string) $code;
			$hash = hash( 'sha256', $code );
			$now  = time();
			$meta = array_intersect_key( (array) ( $extra['index'] ?? array() ), array( 'origin_key' => 1, 'review' => 1, 'conflict' => 1 ) );
			$index['items'][ $id ] = array_merge( array( 'title' => (string) $title, 'status' => 'inactive', 'draft_hash' => $hash, 'origin_key' => '', 'review' => false, 'conflict' => false, 'modified' => $now, 'by' => (int) $by ), $meta );
			$item = array_merge( self::normalize_item( array() ), array_intersect_key( (array) ( $extra['item'] ?? array() ), array( 'origin' => 1, 'check' => 1 ) ), array( 'code' => $code, 'created' => $now, 'created_by' => (int) $by ) );
			$event = 'origin' === ( $extra['event'] ?? '' ) ? 'origin' : 'saved';
			$item['history'] = self::remember( $scope, $id, array(), array( 'code' => $code, 'hash' => $hash, 'at' => $now, 'by' => $by, 'event' => $event ) );
			$item = self::sign_draft( $scope, $id, $item, $event );
			self::put( $scope, self::option_name( $scope, 'item', $id ), $item );
			self::put( $scope, self::option_name( $scope, 'index' ), $index );
			if ( false !== ( $extra['log'] ?? true ) ) self::log( $scope, 'created', array( 'id' => $id, 'hash' => $hash, 'user' => $by, 'msg' => $title ) );
			return $id;
		} );
	}

	/**
	 * Guarda el borrador (ficha y, si el hash es nuevo, historial `saved`). Lo activo sigue corriendo.
	 *
	 * Si el borrador guardado no tiene una firma válida (alguien lo cambió directamente en la BD, o cambiaron las claves),
	 * su código entra antes al historial como `unsigned` y, si se vuelve a guardar igual, conserva esa procedencia: activarlo
	 * exige la casilla de revisión (Tester::needs_review()) y Tools nunca lo firma como si lo hubiera guardado quien edita.
	 *
	 * @param array $extra { check: resultado de Tester::check() }
	 * @return array|\WP_Error { id, hash, changed, active_hash, tampered: 'database'|'keys'|'' }
	 */
	public static function save( $scope, $id, $title, $code, $by = 0, array $extra = array() ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $id, $title, $code, $by, $extra ) {
			$index = self::index( $scope );
			$item  = self::item( $scope, $id );
			if ( ! isset( $index['items'][ $id ] ) || ! $item ) return self::missing();
			$code     = (string) $code;
			$hash     = hash( 'sha256', $code );
			$old      = hash( 'sha256', $item['code'] );
			$draft    = self::draft_status( $scope, $id, $item );
			$tampered = in_array( $draft, array( 'database', 'keys' ), true ) ? $draft : '';
			$changed  = $hash !== $old || (string) $title !== $index['items'][ $id ]['title'];
			if ( '' !== $tampered && '' !== $item['code'] ) $item['history'] = self::remember( $scope, $id, $item['history'], array( 'code' => $item['code'], 'hash' => $old, 'by' => 0, 'event' => 'unsigned' ) );
			$item['code']    = $code;
			$item['history'] = self::remember( $scope, $id, $item['history'], array( 'code' => $code, 'hash' => $hash, 'by' => $by, 'event' => 'saved' ) );
			$item            = self::sign_draft( $scope, $id, $item, $hash === $old ? ( '' !== $tampered ? 'unsigned' : $draft ) : 'saved' );
			if ( isset( $extra['check'] ) && is_array( $extra['check'] ) ) $item['check'] = $extra['check'];
			$index['items'][ $id ] = array_merge( $index['items'][ $id ], array( 'title' => (string) $title, 'draft_hash' => $hash, 'modified' => time(), 'by' => (int) $by ) );
			self::put( $scope, self::option_name( $scope, 'item', $id ), $item );
			self::put( $scope, self::option_name( $scope, 'index' ), $index );
			if ( '' !== $tampered ) self::log( $scope, 'bad_signature_seen', array( 'id' => $id, 'hash' => $old, 'user' => 0, 'msg' => 'keys' === $tampered ? 'Borrador firmado con otras claves' : 'Borrador cambiado fuera de DIGITALÍSIMO Tools' ) );
			if ( $changed ) self::log( $scope, 'saved', array( 'id' => $id, 'hash' => $hash, 'user' => $by ) );
			$run = self::run( $scope );
			return array( 'id' => $id, 'hash' => $hash, 'changed' => $changed, 'active_hash' => isset( $run['items'][ $id ] ) ? $run['items'][ $id ]['hash'] : '', 'tampered' => $tampered );
		} );
	}

	/** Guarda el resultado de la revisión (Tester::check) en la ficha, sin tocar el código. */
	public static function set_check( $scope, $id, array $check ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $id, $check ) {
			$item = self::item( $scope, $id );
			if ( ! $item ) return self::missing();
			$item['check'] = $check;
			self::put( $scope, self::option_name( $scope, 'item', $id ), $item );
			return true;
		} );
	}

	/**
	 * Activa el hash exacto que pasó la prueba (§4.4, «Activación», pasos 1-4): comprueba que la ficha conserva ese hash,
	 * aplica los topes, borra una fila `off_s` antigua del mismo hash, firma, escribe `_run` y el indicador, programa el cron
	 * y actualiza índice, historial y registro. La clave de rescate (Rescue::create) y los correos quedan para quien llama.
	 *
	 * @param array $args { by: usuario }
	 * @return array|\WP_Error { id, hash, sig, rev }
	 */
	public static function activate( $scope, $id, $hash, array $args = array() ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $id, $hash, $args ) {
			$index = self::index( $scope );
			$item  = self::item( $scope, $id );
			if ( ! isset( $index['items'][ $id ] ) || ! $item ) return self::missing();
			if ( ! is_string( $hash ) || ! hash_equals( hash( 'sha256', $item['code'] ), $hash ) ) return new \WP_Error( 'digitalisimo_php_changed', self::CHANGED_MESSAGE, array( 'status' => 409 ) );
			$run    = self::run( $scope );
			$limits = self::check_limits( $scope, $id, $item['code'], $run );
			if ( is_wp_error( $limits ) ) return $limits;
			$by  = (int) ( $args['by'] ?? 0 );
			// En la red, una versión activa conserva su `rev`; al reactivar se sigue el último usado (del índice o del historial),
			// para que las pausas de un `rev` que ya se reanudó en todos los sitios no vuelvan a aplicar.
			$rev = 'network' === $scope ? max( 1, (int) ( $run['items'][ $id ]['rev'] ?? 0 ), isset( $run['items'][ $id ] ) ? 0 : max( (int) ( $index['items'][ $id ]['rev'] ?? 0 ), self::max_rev( $item['history'] ) ) ) : 0;
			if ( 'site' === $scope ) delete_option( self::off_name( 'site', $id, $hash ) );
			$sig = self::sign( $scope, $id, $hash, $rev );
			$entry = array( 'title' => $index['items'][ $id ]['title'], 'code' => $item['code'], 'hash' => $hash, 'sig' => $sig, 'at' => time(), 'by' => $by );
			if ( 'network' === $scope ) $entry['rev'] = max( 1, $rev );
			$run['items'][ $id ] = $entry;
			$run['kid']          = self::kid();
			$index['items'][ $id ] = array_merge( $index['items'][ $id ], array( 'status' => 'inactive', 'draft_hash' => $hash, 'review' => false, 'conflict' => false, 'modified' => time(), 'by' => $by ) );
			if ( 'network' === $scope ) $index['items'][ $id ]['rev'] = $entry['rev'];
			unset( $index['items'][ $id ]['error'] );
			$item['history'] = self::remember( $scope, $id, $item['history'], array( 'code' => $item['code'], 'hash' => $hash, 'by' => $by, 'event' => 'activated', 'sig' => $sig, 'rev' => $entry['rev'] ?? 0 ) );
			$item            = self::sign_draft( $scope, $id, $item, 'saved' );
			self::put( $scope, self::option_name( $scope, 'item', $id ), $item );
			self::put( $scope, self::option_name( $scope, 'index' ), $index );
			self::write_run( $scope, $run, $index );
			self::log( $scope, 'activated', array( 'id' => $id, 'hash' => $hash, 'user' => $by, 'msg' => $entry['title'] ) );
			return array( 'id' => $id, 'hash' => $hash, 'sig' => $sig, 'rev' => $entry['rev'] ?? 0 );
		} );
	}

	/** Quita un fragmento de `_run` (no firma nada). Funciona con DISALLOW_FILE_EDIT y en modo reducido. */
	public static function deactivate( $scope, $id, $by = 0 ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $id, $by ) {
			$run   = self::run( $scope );
			$index = self::index( $scope );
			if ( ! isset( $run['items'][ $id ] ) ) return isset( $index['items'][ $id ] ) ? true : self::missing();
			$hash = $run['items'][ $id ]['hash'];
			unset( $run['items'][ $id ] );
			if ( isset( $index['items'][ $id ] ) ) {
				$index['items'][ $id ] = array_merge( $index['items'][ $id ], array( 'status' => 'inactive', 'modified' => time(), 'by' => (int) $by ) );
				unset( $index['items'][ $id ]['error'] );
				self::put( $scope, self::option_name( $scope, 'index' ), $index );
			}
			self::write_run( $scope, $run, $index );
			self::log( $scope, 'deactivated', array( 'id' => $id, 'hash' => $hash, 'user' => $by ) );
			return true;
		} );
	}

	/** «Descartar cambios»: devuelve el borrador a la versión activa, sólo si su firma es válida. */
	public static function discard( $scope, $id, $by = 0 ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $id, $by ) {
			$run   = self::run( $scope );
			$index = self::index( $scope );
			$item  = self::item( $scope, $id );
			if ( ! $item || ! isset( $index['items'][ $id ], $run['items'][ $id ] ) ) return self::missing();
			$active = $run['items'][ $id ];
			if ( ! self::verify( array_merge( $active, array( 'scope' => $scope, 'id' => $id ) ) ) ) return new \WP_Error( 'digitalisimo_php_signature', 'La versión activa no tiene una firma válida: revisa el código y pulsa «Guardar y activar».', array( 'status' => 409 ) );
			// Un borrador cambiado fuera de Tools no se pierde: queda en el historial como «sin firma».
			if ( in_array( self::draft_status( $scope, $id, $item ), array( 'database', 'keys' ), true ) ) $item['history'] = self::remember( $scope, $id, $item['history'], array( 'code' => $item['code'], 'by' => 0, 'event' => 'unsigned' ) );
			$item['code'] = $active['code'];
			$item         = self::sign_draft( $scope, $id, $item, 'saved' );
			$index['items'][ $id ] = array_merge( $index['items'][ $id ], array( 'draft_hash' => $active['hash'], 'modified' => time(), 'by' => (int) $by ) );
			self::put( $scope, self::option_name( $scope, 'item', $id ), $item );
			self::put( $scope, self::option_name( $scope, 'index' ), $index );
			self::log( $scope, 'saved', array( 'id' => $id, 'hash' => $active['hash'], 'user' => $by, 'msg' => 'Cambios descartados' ) );
			return true;
		} );
	}

	/** Elimina un fragmento inactivo. Si era importado, su `source:id` pasa a `gone` para que no vuelva a importarse. */
	public static function delete( $scope, $id, $by = 0 ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $id, $by ) {
			$run   = self::run( $scope );
			$index = self::index( $scope );
			if ( ! isset( $index['items'][ $id ] ) ) return self::missing();
			if ( isset( $run['items'][ $id ] ) ) return new \WP_Error( 'digitalisimo_php_active', 'Desactiva el fragmento antes de eliminarlo.', array( 'status' => 409 ) );
			$origin = $index['items'][ $id ]['origin_key'];
			if ( 'site' === $scope && '' !== $origin ) $index['gone'][ $origin ] = true;
			$hash = $index['items'][ $id ]['draft_hash'];
			unset( $index['items'][ $id ] );
			self::put( $scope, self::option_name( $scope, 'index' ), $index );
			self::drop( $scope, self::option_name( $scope, 'item', $id ) );
			if ( 'site' === $scope ) foreach ( array_keys( self::rows( self::OFF_SITE . $id . '_' ) ) as $row ) delete_option( $row );
			self::log( $scope, 'deleted', array( 'id' => $id, 'hash' => $hash, 'user' => $by ) );
			return true;
		} );
	}

	/** «Vaciar versiones anteriores»: deja el borrador y la versión activa y borra `origin.original` (conserva sus hashes). */
	public static function purge_history( $scope, $id, $by = 0 ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope, $id, $by ) {
			$item = self::item( $scope, $id );
			if ( ! $item ) return self::missing();
			$run  = self::run( $scope );
			$keep = array_filter( array( hash( 'sha256', $item['code'] ), $run['items'][ $id ]['hash'] ?? '' ) );
			$item['history'] = array_values( array_filter( $item['history'], static function ( $entry ) use ( $keep ) { return in_array( $entry['hash'] ?? '', $keep, true ); } ) );
			unset( $item['origin']['original'] );
			self::put( $scope, self::option_name( $scope, 'item', $id ), $item );
			self::log( $scope, 'history_purged', array( 'id' => $id, 'user' => $by ) );
			return true;
		} );
	}

	/**
	 * Una versión del historial (0 = la más reciente) con `sig_valid`: si su firma sigue valiendo. Si no, activarla exige la
	 * casilla de revisión.
	 */
	public static function version( $scope, $id, $n ) {
		$item = self::item( $scope, $id );
		$n    = (int) $n;
		if ( ! $item || ! isset( $item['history'][ $n ] ) ) return null;
		$entry              = $item['history'][ $n ];
		$entry['sig_valid'] = self::history_signature_valid( $scope, $id, $entry );
		return $entry;
	}

	/**
	 * «Detener»: $which ∈ 'safe' (sitio: los de sitio y los de red aquí; red: los de red en todos los sitios) o 'safe_sites'
	 * (red: los de sitio en todos los sitios). Detener pide can_stop; reanudar, can_edit, y no prueba.
	 */
	public static function set_safe( $scope, $which, $on, $by = 0 ) {
		$scope = self::scope( $scope );
		if ( 'safe_sites' === $which && 'network' !== $scope ) return new \WP_Error( 'digitalisimo_php_scope', 'Esta acción sólo existe en la administración de la red.', array( 'status' => 400 ) );
		$which = 'safe_sites' === $which ? 'safe_sites' : 'safe';
		return self::mutate( $scope, function () use ( $scope, $which, $on, $by ) {
			$run = self::run( $scope );
			if ( (bool) $run[ $which ] === (bool) $on ) return true;
			$run[ $which ] = (bool) $on;
			self::write_run( $scope, $run );
			self::log( $scope, $on ? 'safe_on' : 'safe_off', array( 'user' => $by, 'msg' => 'safe_sites' === $which ? 'Fragmentos de todos los sitios' : ( 'network' === $scope ? 'Fragmentos de red' : 'Este sitio' ) ) );
			return true;
		} );
	}

	/** «Pausar en este sitio» un fragmento de red: pausa manual, dato del sitio; no depende de `rev`. */
	public static function pause_here( $id, $by = 0 ) {
		if ( ! is_multisite() ) return self::missing();
		return self::mutate( 'site', function () use ( $id, $by ) {
			$net = self::run( 'network' );
			if ( ! isset( $net['items'][ $id ] ) ) return self::missing();
			$run = self::run( 'site' );
			$run['paused'][ $id ] = array( 'at' => time(), 'by' => (int) $by );
			self::write_run( 'site', $run );
			self::log( 'site', 'paused_site', array( 'id' => $id, 'hash' => $net['items'][ $id ]['hash'], 'user' => $by ) );
			return true;
		} );
	}

	/**
	 * «Reanudar en este sitio» un fragmento de red, después de una prueba superada en este sitio con $hash: borra sus filas
	 * `off_n` de este sitio y su pausa manual.
	 */
	public static function resume_here( $id, $hash, $by = 0 ) {
		if ( ! is_multisite() ) return self::missing();
		return self::mutate( 'site', function () use ( $id, $hash, $by ) {
			$net = self::run( 'network' );
			if ( ! isset( $net['items'][ $id ] ) ) return self::missing();
			if ( ! is_string( $hash ) || ! hash_equals( $net['items'][ $id ]['hash'], $hash ) ) return new \WP_Error( 'digitalisimo_php_changed', self::CHANGED_MESSAGE, array( 'status' => 409 ) );
			foreach ( array_keys( self::rows( self::OFF_NETWORK . $id . '_' ) ) as $row ) delete_option( $row );
			self::flush_alloptions();
			$run = self::run( 'site' );
			if ( isset( $run['paused'][ $id ] ) ) {
				unset( $run['paused'][ $id ] );
				self::write_run( 'site', $run );
			}
			self::log( 'site', 'resumed', array( 'id' => $id, 'hash' => $hash, 'user' => $by, 'msg' => 'Fragmento de red reanudado en este sitio' ) );
			return true;
		} );
	}

	/**
	 * «Reanudar en todos los sitios», después de probar la muestra de red con $hash: sube `rev` y vuelve a firmar. Las filas
	 * `off_n` del `rev` anterior dejan de aplicar; las pausas manuales se respetan.
	 *
	 * @return array|\WP_Error { id, hash, rev, sig }
	 */
	public static function resume_everywhere( $id, $hash, $by = 0 ) {
		if ( ! is_multisite() ) return self::missing();
		return self::mutate( 'network', function () use ( $id, $hash, $by ) {
			$run = self::run( 'network' );
			if ( ! isset( $run['items'][ $id ] ) ) return self::missing();
			$item = $run['items'][ $id ];
			if ( ! is_string( $hash ) || ! hash_equals( $item['hash'], $hash ) || ! hash_equals( hash( 'sha256', $item['code'] ), $hash ) ) return new \WP_Error( 'digitalisimo_php_changed', self::CHANGED_MESSAGE, array( 'status' => 409 ) );
			$item['rev'] = (int) $item['rev'] + 1;
			$item['sig'] = self::sign( 'network', $id, $hash, $item['rev'] );
			$run['items'][ $id ] = $item;
			$run['kid']          = self::kid();
			$index               = self::index( 'network' );
			if ( isset( $index['items'][ $id ] ) ) {
				$index['items'][ $id ]['rev'] = $item['rev'];
				self::put( 'network', self::NETWORK_INDEX, $index );
			}
			self::write_run( 'network', $run, $index );
			self::log( 'network', 'resumed', array( 'id' => $id, 'hash' => $hash, 'user' => $by, 'msg' => 'Reanudado en todos los sitios' ) );
			return array( 'id' => $id, 'hash' => $hash, 'rev' => $item['rev'], 'sig' => $item['sig'] );
		} );
	}

	/** El `rev` más alto que anotó el historial de un fragmento de red. */
	private static function max_rev( array $history ) {
		$max = 0;
		foreach ( $history as $entry ) $max = max( $max, (int) ( is_array( $entry ) ? ( $entry['rev'] ?? 0 ) : 0 ) );
		return $max;
	}

	/** «1 sitio» o «3 sitios»: $one y $many llevan un %d. */
	public static function plural( $n, $one, $many ) {
		return sprintf( 1 === (int) $n ? $one : $many, (int) $n );
	}

	private static function missing() {
		return new \WP_Error( 'digitalisimo_php_missing', 'El fragmento no existe.', array( 'status' => 404 ) );
	}

	/* ------------------------------------------------------------------ *
	 * Estados calculados (§4.1)
	 * ------------------------------------------------------------------ */

	/**
	 * Estado visible de un fragmento del ámbito: active, changed, inactive, failed, signature, review u origin (claves de
	 * STATES). «Activo» = presente en `_run`, con firma válida y sin fila `off`.
	 */
	public static function state( $scope, $id, $index = null, $run = null, $all = null ) {
		$scope = self::scope( $scope );
		$index = $index ?: self::index( $scope );
		$run   = $run ?: self::run( $scope );
		$all   = null === $all ? ( function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array() ) : $all;
		$meta  = $index['items'][ $id ] ?? array();
		if ( isset( $run['items'][ $id ] ) ) {
			$active = array_merge( $run['items'][ $id ], array( 'scope' => $scope, 'id' => $id ) );
			if ( ! self::verify( $active ) ) return 'signature';
			if ( 'site' === $scope && isset( $all[ self::off_name( 'site', $id, $active['hash'] ) ] ) ) return 'failed';
			return ( isset( $meta['draft_hash'] ) && '' !== $meta['draft_hash'] && $meta['draft_hash'] !== $active['hash'] ) ? 'changed' : 'active';
		}
		if ( 'failed' === ( $meta['status'] ?? '' ) ) return 'signature' === ( $meta['error']['kind'] ?? '' ) ? 'signature' : 'failed';
		if ( ! empty( $meta['review'] ) ) return ! empty( $meta['conflict'] ) ? 'origin' : 'review';
		return 'inactive';
	}

	/**
	 * Estado en este sitio de un fragmento de red: here_active, here_error, here_manual o here_stopped, más los datos de la
	 * fila `off_n` (`row`: { t, type, line, msg, path }) cuando está pausado por un error. null si no está activo en la red.
	 */
	public static function network_state_here( $id, $net = null, $site = null, $all = null ) {
		if ( ! is_multisite() ) return null;
		$net  = $net ?: self::run( 'network' );
		$site = $site ?: self::run( 'site' );
		$all  = null === $all ? wp_load_alloptions() : $all;
		if ( ! isset( $net['items'][ $id ] ) ) return null;
		$item = $net['items'][ $id ];
		if ( $net['safe'] || $site['safe'] ) return array( 'state' => 'here_stopped' );
		if ( isset( $site['paused'][ $id ] ) ) return array( 'state' => 'here_manual', 'at' => $site['paused'][ $id ]['at'] );
		$row = self::off_name( 'network', $id, $item['hash'], $item['rev'] );
		if ( isset( $all[ $row ] ) ) return array( 'state' => 'here_error', 'row' => (array) self::unserialize( $all[ $row ] ) );
		return array( 'state' => self::verify( array_merge( $item, array( 'scope' => 'network', 'id' => $id ) ) ) ? 'here_active' : 'here_stopped' );
	}

	/**
	 * Lista para la pantalla, sin código: [ { id, title, state, label, active, changed, modified, by, review, conflict, error } ].
	 */
	public static function overview( $scope ) {
		$scope = self::scope( $scope );
		$index = self::index( $scope );
		$run   = self::run( $scope );
		$all   = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		$rows  = array();
		foreach ( $index['items'] as $id => $meta ) {
			$state  = self::state( $scope, $id, $index, $run, $all );
			$rows[] = array(
				'id'       => $id,
				'title'    => $meta['title'],
				'state'    => $state,
				'label'    => self::STATES[ $state ],
				'active'   => in_array( $state, array( 'active', 'changed' ), true ),
				'changed'  => 'changed' === $state,
				'modified' => $meta['modified'],
				'by'       => $meta['by'],
				'review'   => $meta['review'],
				'conflict' => $meta['conflict'],
				'error'    => $meta['error'] ?? null,
			);
		}
		return $rows;
	}

	/**
	 * Marca como vistos los errores del índice (`error.seen`): el aviso del escritorio desaparece al abrir la pestaña o al
	 * ocultarlo. Devuelve cuántos cambió.
	 *
	 * @return int|\WP_Error
	 */
	public static function mark_seen( $scope ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope ) {
			$index   = self::index( $scope );
			$changed = 0;
			foreach ( $index['items'] as $id => $meta ) {
				if ( empty( $meta['error'] ) || ! empty( $meta['error']['seen'] ) ) continue;
				$index['items'][ $id ]['error']['seen'] = true;
				$changed++;
			}
			if ( $changed ) self::put( $scope, self::option_name( $scope, 'index' ), $index );
			return $changed;
		} );
	}

	/** Número de fragmentos en `_run` del ámbito (para la fila del plugin). */
	public static function active_count( $scope ) {
		return count( self::run( $scope )['items'] );
	}

	/* ------------------------------------------------------------------ *
	 * Conciliación, cron y correos (§6.3)
	 * ------------------------------------------------------------------ */

	/**
	 * Concilia el ámbito bajo bloqueo: pasa a `failed` lo que el guardián anotó con filas `off_s`, anota una vez las pausas
	 * `off_n` vigentes, borra las filas viejas y saca de `_run` lo que tiene una firma inválida. Corre al abrir la pestaña y
	 * en el cron horario.
	 *
	 * @return array|\WP_Error { failed:[ids], paused:[ids], signature:[ids] }
	 */
	public static function reconcile( $scope ) {
		$scope = self::scope( $scope );
		return self::mutate( $scope, function () use ( $scope ) {
			return 'network' === $scope ? self::reconcile_network() : self::reconcile_site();
		} );
	}

	private static function reconcile_site() {
		$done  = array( 'failed' => array(), 'paused' => array(), 'signature' => array() );
		$run   = self::run( 'site' );
		$index = self::index( 'site' );
		$net   = is_multisite() ? self::run( 'network' ) : null;
		$dirty = false;
		$mails = array();
		foreach ( self::rows( self::OFF_SITE ) as $name => $row ) {
			delete_option( $name );
			if ( ! preg_match( '/^' . preg_quote( self::OFF_SITE, '/' ) . '([0-9a-f]{8})_([0-9a-f]{12})$/D', $name, $m ) ) continue;
			$id = $m[1];
			if ( ! isset( $run['items'][ $id ] ) || self::h12( $run['items'][ $id ]['hash'] ) !== $m[2] ) continue;
			$row    = is_array( $row ) ? $row : array();
			$active = $run['items'][ $id ];
			unset( $run['items'][ $id ] );
			$error = array( 'kind' => 'fatal', 'at' => (int) ( $row['t'] ?? time() ), 'message' => self::one_line( $row['msg'] ?? '', 500 ), 'line' => (int) ( $row['line'] ?? 0 ), 'path' => self::one_line( $row['path'] ?? '', 200 ), 'hash' => $active['hash'], 'seen' => false );
			if ( isset( $index['items'][ $id ] ) ) $index['items'][ $id ] = array_merge( $index['items'][ $id ], array( 'status' => 'failed', 'error' => $error ) );
			$dirty = true;
			$done['failed'][] = $id;
			self::log( 'site', 'failed', array( 'id' => $id, 'hash' => $active['hash'], 'user' => 0, 'msg' => $error['message'] ) );
			$mails[] = array( 'failed', $id, $active['title'], $error );
		}
		foreach ( self::rows( self::OFF_NETWORK ) as $name => $row ) {
			$current = false;
			if ( $net && preg_match( '/^' . preg_quote( self::OFF_NETWORK, '/' ) . '([0-9a-f]{8})_([0-9a-f]{12})_(\d+)$/D', $name, $m ) && isset( $net['items'][ $m[1] ] ) ) {
				$item    = $net['items'][ $m[1] ];
				$current = self::h12( $item['hash'] ) === $m[2] && (int) $item['rev'] === (int) $m[3];
			}
			if ( ! $current ) { delete_option( $name ); continue; }
			$row = is_array( $row ) ? $row : array();
			$done['paused'][] = $m[1];
			if ( ! empty( $row['l'] ) ) continue;
			$row['l'] = 1;
			update_option( $name, $row );
			self::log( 'site', 'failed', array( 'id' => $m[1], 'hash' => $item['hash'], 'user' => 0, 'msg' => 'Fragmento de red pausado en este sitio: ' . self::one_line( $row['msg'] ?? '', 400 ) ) );
		}
		$kind = self::bad_signature_kind( $run );
		foreach ( $run['items'] as $id => $active ) {
			if ( self::verify( array_merge( $active, array( 'scope' => 'site', 'id' => $id ) ) ) ) continue;
			unset( $run['items'][ $id ] );
			self::mark_unsigned( 'site', $id, $active, $index, $kind );
			$dirty = true;
			$done['signature'][] = $id;
		}
		delete_option( self::BADSIG_SITE );
		if ( $net ) {
			$known = self::index( 'network' )['items'];
			foreach ( array_keys( $run['paused'] ) as $id ) {
				if ( isset( $known[ $id ] ) ) continue;
				unset( $run['paused'][ $id ] );
				$dirty = true;
			}
			$net_ok = true;
			foreach ( $net['items'] as $id => $item ) if ( ! self::verify( array_merge( $item, array( 'scope' => 'network', 'id' => $id ) ) ) ) $net_ok = false;
			if ( $net_ok ) delete_option( self::BADSIG_NETWORK );
		}
		if ( $dirty ) {
			self::put( 'site', self::SITE_INDEX, $index );
			self::write_run( 'site', $run, $index );
		}
		self::flush_alloptions();
		foreach ( $mails as $mail ) self::after( static function () use ( $mail ) { self::mail_failure( 'site', $mail[1], $mail[2], $mail[3] ); } );
		if ( $done['signature'] ) self::after( static function () use ( $done, $kind ) { self::mail_signature( 'site', $done['signature'], $kind ); } );
		return $done;
	}

	private static function reconcile_network() {
		$done  = array( 'failed' => array(), 'paused' => array(), 'signature' => array() );
		$run   = self::run( 'network' );
		$index = self::index( 'network' );
		$kind  = self::bad_signature_kind( $run );
		foreach ( $run['items'] as $id => $item ) {
			if ( self::verify( array_merge( $item, array( 'scope' => 'network', 'id' => $id ) ) ) ) continue;
			unset( $run['items'][ $id ] );
			self::mark_unsigned( 'network', $id, $item, $index, $kind );
			$done['signature'][] = $id;
		}
		if ( $done['signature'] ) {
			self::put( 'network', self::NETWORK_INDEX, $index );
			self::write_run( 'network', $run, $index );
			self::after( static function () use ( $done, $kind ) { self::mail_signature( 'network', $done['signature'], $kind ); } );
		}
		return $done;
	}

	/** Un ítem con firma inválida sale de `_run` y pasa a `failed` (`kind:'signature'`); su código, si difiere, entra al historial como `unsigned`. */
	private static function mark_unsigned( $scope, $id, array $active, array &$index, $kind ) {
		$item = self::item( $scope, $id );
		if ( $item && hash( 'sha256', $item['code'] ) !== hash( 'sha256', $active['code'] ) ) {
			$item['history'] = self::remember( $scope, $id, $item['history'], array( 'code' => $active['code'], 'hash' => hash( 'sha256', $active['code'] ), 'event' => 'unsigned' ) );
			self::put( $scope, self::option_name( $scope, 'item', $id ), $item );
		}
		if ( isset( $index['items'][ $id ] ) ) $index['items'][ $id ] = array_merge( $index['items'][ $id ], array( 'status' => 'failed', 'error' => array( 'kind' => 'signature', 'at' => time(), 'message' => 'keys' === $kind ? 'Cambiaron las claves de wp-config.php.' : 'La base de datos se modificó fuera de DIGITALÍSIMO Tools.', 'line' => 0, 'path' => '', 'hash' => (string) $active['hash'], 'seen' => false, 'cause' => $kind ) ) );
		self::log( $scope, 'bad_signature_seen', array( 'id' => $id, 'hash' => $active['hash'], 'user' => 0, 'msg' => 'keys' === $kind ? 'Claves distintas' : 'Misma clave' ) );
	}

	/** Manejador del cron horario: concilia el sitio y, en el principal, la red y los correos de pausa de red. */
	public static function cron() {
		self::reconcile( 'site' );
		if ( is_multisite() && is_main_site() ) {
			self::reconcile( 'network' );
			self::network_sweep();
		}
		self::sync_cron( 'site', '1' === get_option( self::FLAG ) );
	}

	/**
	 * Programa o quita el cron horario: existe en el sitio mientras haya indicador y en el sitio principal mientras haya
	 * fragmentos de red.
	 */
	public static function sync_cron( $scope, $needed ) {
		if ( ! function_exists( 'wp_next_scheduled' ) ) return;
		if ( 'network' === $scope ) {
			$main = (int) get_main_site_id();
			$swap = (int) get_current_blog_id() !== $main;
			if ( $swap ) switch_to_blog( $main );
			try {
				$needed = $needed || '1' === get_option( self::FLAG );
				self::schedule( $needed );
			} finally {
				if ( $swap ) restore_current_blog();
			}
			return;
		}
		if ( ! $needed && is_multisite() && is_main_site() ) $needed = ! empty( self::run( 'network' )['items'] );
		self::schedule( $needed );
	}

	private static function schedule( $needed ) {
		$next = wp_next_scheduled( self::CRON );
		if ( $needed && ! $next ) wp_schedule_event( time() + 3600, 'hourly', self::CRON );
		if ( ! $needed && $next ) wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * En el sitio principal: reúne las filas `off_n` vigentes de todos los sitios (UNION de 100 tablas por consulta) y envía
	 * un solo correo por `<id>_<h12>_<rev>` al `admin_email` de la red, anotado en `…_network_mailed`.
	 *
	 * @return array|\WP_Error Claves enviadas en esta pasada.
	 */
	public static function network_sweep() {
		if ( ! is_multisite() ) return array();
		return self::mutate( 'network', function () {
			$net    = self::run( 'network' );
			$mailed = self::get( 'network', self::NETWORK_MAILED, array() );
			$mailed = is_array( $mailed ) ? $mailed : array();
			$groups = array();
			foreach ( self::network_rows( self::OFF_NETWORK ) as $row ) {
				if ( ! preg_match( '/^' . preg_quote( self::OFF_NETWORK, '/' ) . '([0-9a-f]{8})_([0-9a-f]{12})_(\d+)$/D', $row['name'], $m ) || ! isset( $net['items'][ $m[1] ] ) ) continue;
				$item = $net['items'][ $m[1] ];
				if ( self::h12( $item['hash'] ) !== $m[2] || (int) $item['rev'] !== (int) $m[3] ) continue;
				$groups[ $m[1] . '_' . $m[2] . '_' . $m[3] ][] = $row;
			}
			$sent = array();
			foreach ( $groups as $key => $rows ) {
				// { t, n }: cuándo se envió el correo y en cuántos sitios está pausado (el aviso de la red lo lee sin consultar los sitios).
				if ( isset( $mailed[ $key ] ) ) {
					$mailed[ $key ] = array( 't' => (int) ( is_array( $mailed[ $key ] ) ? ( $mailed[ $key ]['t'] ?? 0 ) : $mailed[ $key ] ), 'n' => count( $rows ) );
					continue;
				}
				$id    = substr( $key, 0, 8 );
				$title = $net['items'][ $id ]['title'];
				self::after( static function () use ( $id, $title, $rows ) { self::mail_network_pause( $id, $title, $rows ); } );
				$mailed[ $key ] = array( 't' => time(), 'n' => count( $rows ) );
				$sent[]         = $key;
			}
			$mailed = array_intersect_key( $mailed, $groups );
			if ( $mailed ) update_site_option( self::NETWORK_MAILED, $mailed );
			else delete_site_option( self::NETWORK_MAILED );
			return $sent;
		} );
	}

	/**
	 * Para la pantalla de red: dónde está pausado cada fragmento de red y qué sitios tienen fragmentos propios. Sólo se
	 * calcula al abrirla (UNION de 100 tablas por consulta).
	 *
	 * @return array { paused:{ <id>:{ error:[ { blog_id, t, path, msg } ], manual:[ blog_id ] } }, sites:{ <blog_id>: n } }
	 */
	public static function network_inventory() {
		$out = array( 'paused' => array(), 'sites' => array() );
		if ( ! is_multisite() ) return $out;
		$net = self::run( 'network' );
		// Fila por fila, sin acumular los valores: `_run` de cada sitio lleva su código y la red puede tener miles de sitios.
		self::network_rows( self::OFF_NETWORK, array( self::SITE_RUN, self::SITE_INDEX ), static function ( $row ) use ( $net, &$out ) {
			$blog = (int) $row['blog_id'];
			if ( self::SITE_INDEX === $row['name'] ) {
				$count = count( self::normalize_index( self::unserialize( $row['value'] ) )['items'] );
				if ( $count ) $out['sites'][ $blog ] = $count;
				return;
			}
			if ( self::SITE_RUN === $row['name'] ) {
				foreach ( array_keys( self::normalize_run( self::unserialize( $row['value'] ), 'site' )['paused'] ) as $id ) if ( isset( $net['items'][ $id ] ) ) $out['paused'][ $id ]['manual'][] = $blog;
				return;
			}
			if ( ! preg_match( '/^' . preg_quote( self::OFF_NETWORK, '/' ) . '([0-9a-f]{8})_([0-9a-f]{12})_(\d+)$/D', $row['name'], $m ) || ! isset( $net['items'][ $m[1] ] ) ) return;
			$item = $net['items'][ $m[1] ];
			if ( self::h12( $item['hash'] ) !== $m[2] || (int) $item['rev'] !== (int) $m[3] ) return;
			$data = (array) self::unserialize( $row['value'] );
			$out['paused'][ $m[1] ]['error'][] = array( 'blog_id' => $blog, 't' => (int) ( $data['t'] ?? 0 ), 'path' => self::one_line( $data['path'] ?? '', 200 ), 'msg' => self::one_line( $data['msg'] ?? '', 300 ) );
		} );
		foreach ( $out['paused'] as $id => $paused ) $out['paused'][ $id ] += array( 'error' => array(), 'manual' => array() );
		return $out;
	}

	/**
	 * Sitios activos de esta red (sólo de esta: `get_sites()` sin `network_id` devuelve los de todas las redes de la
	 * instalación). $number 0 = todos.
	 */
	public static function network_blogs( array $args = array() ) {
		return array_map( 'intval', (array) get_sites( array_merge( array( 'fields' => 'ids', 'number' => 0, 'network_id' => (int) get_current_network_id(), 'archived' => 0, 'spam' => 0, 'deleted' => 0 ), $args ) ) );
	}

	/**
	 * Lee con consultas UNION ALL de 100 tablas, una por sitio de $blogs: $select( $blog ) devuelve [ SQL preparado de ese
	 * sitio ]. Si una consulta falla (a un sitio le falta su tabla de opciones: alta en curso o tablas borradas), repite ese
	 * tramo sitio por sitio y salta sólo el que falla, para que un sitio roto no deje sin datos a los otros 99.
	 * $each recibe cada fila; si es null, se devuelven todas.
	 */
	public static function union_rows( array $blogs, callable $select, $each = null ) {
		global $wpdb;
		$out = array();
		foreach ( array_chunk( $blogs, 100 ) as $chunk ) {
			$parts = array_map( $select, $chunk );
			$rows  = $wpdb->get_results( implode( ' UNION ALL ', $parts ), ARRAY_A ); // phpcs:ignore WordPress.DB
			if ( '' !== (string) $wpdb->last_error ) {
				$rows = array();
				foreach ( $parts as $part ) {
					$one = $wpdb->get_results( $part, ARRAY_A ); // phpcs:ignore WordPress.DB
					if ( '' === (string) $wpdb->last_error ) $rows = array_merge( $rows, (array) $one );
				}
			}
			foreach ( (array) $rows as $row ) {
				if ( null === $each ) $out[] = $row;
				else $each( $row );
			}
			unset( $rows );
		}
		return $out;
	}

	/**
	 * Filas de todos los sitios activos de esta red cuyo nombre empieza por $prefix o está en $names, en consultas UNION ALL
	 * de 100 tablas: [ { blog_id, name, value } ], o cada una a $each sin acumularlas.
	 */
	private static function network_rows( $prefix, array $names = array(), $each = null ) {
		global $wpdb;
		$select = static function ( $blog ) use ( $wpdb, $prefix, $names ) {
			$where = 'option_name LIKE %s' . ( $names ? ' OR option_name IN (' . implode( ', ', array_fill( 0, count( $names ), '%s' ) ) . ')' : '' );
			return $wpdb->prepare( 'SELECT %d AS blog_id, option_name, option_value FROM `' . $wpdb->get_blog_prefix( $blog ) . 'options` WHERE ' . $where, array_merge( array( $blog, $wpdb->esc_like( $prefix ) . '%' ), $names ) ); // phpcs:ignore WordPress.DB
		};
		$out  = array();
		$keep = static function ( $row ) use ( $each, &$out ) {
			$row = array( 'blog_id' => (int) $row['blog_id'], 'name' => (string) $row['option_name'], 'value' => $row['option_value'] );
			if ( null === $each ) $out[] = $row;
			else $each( $row );
		};
		self::union_rows( self::network_blogs(), $select, $keep );
		return $out;
	}

	/** Correo en texto plano: cada dato pasa por wp_strip_all_tags y nunca lleva código (§5.5). */
	public static function mail( $to, $subject, array $lines ) {
		if ( ! function_exists( 'wp_mail' ) || ! is_email( $to ) ) return false;
		$body = implode( "\n", array_map( static function ( $line ) { return wp_strip_all_tags( (string) $line ); }, $lines ) );
		return wp_mail( $to, wp_strip_all_tags( (string) $subject ), $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/** Nombre del sitio (o de la red) para el asunto de un correo. */
	private static function mail_name( $scope ) {
		if ( 'network' === $scope && function_exists( 'get_network' ) && ( $network = get_network() ) ) return wp_specialchars_decode( (string) $network->site_name, ENT_QUOTES );
		return wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	}

	/**
	 * Dirección de los correos de fallo, firma y activación: en una red, siempre la de la red. Sólo el superadministrador
	 * gestiona los fragmentos (§5.2); el administrador de un subsitio no debe recibir sus errores ni quién los activó.
	 */
	public static function mail_to( $scope ) {
		return (string) ( is_multisite() ? get_site_option( 'admin_email' ) : get_option( 'admin_email' ) );
	}

	/**
	 * Portada y pestaña «Código PHP» de cada sitio de $ids, sin cambiar de sitio: con el dominio y la ruta que da get_sites()
	 * en una sola consulta. get_home_url() y get_admin_url() cambian de sitio y cargan sus opciones uno por uno, lo que en una
	 * red grande agota la memoria justo cuando un fragmento de red falla en muchos sitios.
	 *
	 * @return array blog_id → { home, tab }
	 */
	public static function site_links( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids || ! is_multisite() ) return array();
		$scheme = (string) wp_parse_url( network_home_url( '/' ), PHP_URL_SCHEME );
		$scheme = '' !== $scheme ? $scheme : ( is_ssl() ? 'https' : 'http' );
		$out    = array();
		foreach ( (array) get_sites( array( 'site__in' => $ids, 'number' => count( $ids ), 'network_id' => (int) get_current_network_id() ) ) as $site ) {
			if ( ! is_object( $site ) ) continue;
			$home = $scheme . '://' . $site->domain . $site->path;
			$out[ (int) $site->blog_id ] = array( 'home' => $home, 'tab' => $home . 'wp-admin/admin.php?page=' . self::PAGE . '&tab=php' );
		}
		return $out;
	}

	/** Dirección del editor de un fragmento, en la administración que le corresponde. */
	public static function editor_url( $scope, $id = '' ) {
		$args = array( 'page' => self::PAGE, 'tab' => 'php' );
		if ( '' !== $id ) $args['edit'] = $id;
		return add_query_arg( $args, 'network' === self::scope( $scope ) ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) );
	}

	private static function mail_failure( $scope, $id, $title, array $error ) {
		$date = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $error['at'] ) : gmdate( 'Y-m-d H:i', $error['at'] );
		self::mail( self::mail_to( $scope ), sprintf( '[%s] Se desactivó un fragmento PHP por un error', self::mail_name( $scope ) ), array(
			sprintf( 'DIGITALÍSIMO Tools desactivó el fragmento PHP «%s» porque provocó un error fatal. El sitio sigue funcionando sin él.', $title ),
			'',
			'Ámbito: ' . ( 'network' === $scope ? 'toda la red' : 'este sitio' ),
			'Dirección: ' . ( '' !== $error['path'] ? $error['path'] : '—' ),
			'Error: ' . $error['message'],
			'Línea: ' . ( $error['line'] ? $error['line'] : '—' ),
			'Fecha: ' . $date,
			'',
			'Revísalo: ' . self::editor_url( $scope, $id ),
		) );
	}

	private static function mail_signature( $scope, array $ids, $kind ) {
		$lines = array( 'keys' === $kind
			? self::signature_text( 'keys', count( $ids ) )
			: self::signature_text( 'database', count( $ids ) ), '', 'Revísalos: ' . self::editor_url( $scope ) );
		self::mail( self::mail_to( $scope ), sprintf( '[%s] Se detuvieron fragmentos PHP: su firma no es válida', self::mail_name( $scope ) ), $lines );
	}

	/** Textos de §7.6 para $n fragmentos con firma no válida: $kind 'keys' (cambiaron las claves) o 'database'. */
	public static function signature_text( $kind, $n ) {
		return 'keys' === $kind
			? self::plural( $n, '%d fragmento PHP se detuvo porque su firma ya no es válida: cambiaron las claves de wp-config.php (por ejemplo, al migrar o restaurar el sitio). Ábrelo, revisa su código y pulsa «Guardar y activar».', '%d fragmentos PHP se detuvieron porque su firma ya no es válida: cambiaron las claves de wp-config.php (por ejemplo, al migrar o restaurar el sitio). Ábrelos uno por uno, revisa su código y pulsa «Guardar y activar».' )
			: self::plural( $n, 'La base de datos se modificó fuera de DIGITALÍSIMO Tools: %d fragmento PHP cambió sin pasar por esta pantalla y no se ejecuta. Revisa su código antes de reactivarlo; si no reconoces el cambio, trátalo como un incidente de seguridad.', 'La base de datos se modificó fuera de DIGITALÍSIMO Tools: %d fragmentos PHP cambiaron sin pasar por esta pantalla y no se ejecutan. Revisa su código antes de reactivarlos; si no reconoces el cambio, trátalo como un incidente de seguridad.' );
	}

	private static function mail_network_pause( $id, $title, array $rows ) {
		$lines = array( sprintf( 'El fragmento de red «%s» provocó un error fatal y quedó pausado en %s. En los demás sitios sigue ejecutándose.', $title, self::plural( count( $rows ), '%d sitio', '%d sitios' ) ), '' );
		$links = self::site_links( array_column( array_slice( $rows, 0, 20 ), 'blog_id' ) );
		foreach ( array_slice( $rows, 0, 20 ) as $row ) {
			$data    = (array) self::unserialize( $row['value'] );
			$site    = $links[ (int) $row['blog_id'] ]['home'] ?? '#' . (int) $row['blog_id'];
			$lines[] = sprintf( '- %s · %s · %s', $site, self::one_line( $data['path'] ?? '', 200 ), self::one_line( $data['msg'] ?? '', 300 ) );
		}
		if ( count( $rows ) > 20 ) $lines[] = sprintf( 'y %d más.', count( $rows ) - 20 );
		$lines[] = '';
		$lines[] = 'Revísalo: ' . self::editor_url( 'network', $id );
		self::mail( self::mail_to( 'network' ), sprintf( '[%s] Un fragmento PHP de red se pausó en algunos sitios', self::mail_name( 'network' ) ), $lines );
	}
}

/**
 * Permisos de «Código PHP» (§5.2). Son funciones auxiliares y no `map_meta_cap`: DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS,
 * `file_mod_allowed`, DISALLOW_UNFILTERED_HTML y el superadministrador ya los resuelve el mapeo del núcleo de `edit_themes`
 * y `unfiltered_html`.
 */
final class Access {
	/** Acciones del manejador único (§5.3). */
	const ACTIONS = array( 'save', 'prepare', 'activate', 'discard', 'deactivate', 'delete', 'purge_history', 'pause_site', 'resume_site', 'resume_all', 'stop', 'resume', 'stop_sites', 'resume_sites', 'rescue_new', 'rescue_revoke', 'rescue_exit', 'dismiss' );
	/** Las que sólo reducen: piden can_stop y son las únicas permitidas en modo reducido (§5.4). */
	const STOP_ACTIONS = array( 'deactivate', 'pause_site', 'stop', 'stop_sites', 'rescue_exit', 'dismiss' );
	const REDUCED_MESSAGE = 'Estás en modo seguro: sólo puedes desactivar o detener fragmentos. Sal del modo seguro para editar.';

	/** Ver código, crear, guardar, activar, reanudar, registro y rescate: `edit_themes` + `unfiltered_html` + gestionar el ámbito. */
	public static function can_edit( $scope = 'site' ) {
		return current_user_can( 'edit_themes' ) && current_user_can( 'unfiltered_html' ) && current_user_can( 'network' === Store::scope( $scope ) ? 'manage_network_options' : 'manage_options' );
	}

	/** Ver la lista sin código, desactivar, pausar y detener: en una red, sólo el superadministrador (ningún administrador de subsitio). */
	public static function can_stop( $scope = 'site' ) {
		unset( $scope );
		return current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' );
	}

	/** La edición de código está cerrada en este servidor (D1: lo activo sigue y sólo se puede desactivar o detener). */
	public static function editing_blocked() {
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) return true;
		return function_exists( 'wp_is_file_mod_allowed' ) ? ! wp_is_file_mod_allowed( 'capability_edit_themes' ) : ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
	}

	/** Modo reducido: el Runner omitió los fragmentos por la constante o por la cookie de rescate. */
	public static function reduced() {
		return Runner::reduced();
	}

	/** Permiso que pide una acción: 'stop' o 'edit' ('' si la acción no existe). */
	public static function needs( $do ) {
		if ( ! in_array( $do, self::ACTIONS, true ) ) return '';
		return in_array( $do, self::STOP_ACTIONS, true ) ? 'stop' : 'edit';
	}

	/** Si el usuario actual puede hacer $do en $scope, contando el modo reducido. */
	public static function allowed( $do, $scope ) {
		$needs = self::needs( $do );
		if ( '' === $needs ) return false;
		if ( 'edit' === $needs && self::reduced() ) return false;
		return 'edit' === $needs ? self::can_edit( $scope ) : self::can_stop( $scope );
	}

	/** Acción del nonce: `digitalisimo_tools_php:{do}:{scope}:{b<blog>|n<network>}:{id}`. */
	public static function nonce_action( $do, $scope, $id = '', $blog_id = null ) {
		$scope = Store::scope( $scope );
		$owner = 'network' === $scope ? 'n' . (int) get_current_network_id() : 'b' . (int) ( null === $blog_id ? get_current_blog_id() : $blog_id );
		return 'digitalisimo_tools_php:' . $do . ':' . $scope . ':' . $owner . ':' . ( '' === (string) $id ? '0' : (string) $id );
	}

	/**
	 * Comprobaciones 1-6 del manejador único (§5.3), en orden y sin terminar la petición: sesión, ámbito y sitio declarados,
	 * id, permiso según el ámbito declarado, modo reducido y nonce. El saneamiento (paso 7) es Store::sanitize_item().
	 *
	 * @param string     $do      Acción.
	 * @param string     $scope   Ámbito declarado en el formulario ('site' o 'network').
	 * @param int|string $blog_id Sitio declarado en el formulario.
	 * @param string     $id      Id del fragmento, 'new' o '' para las acciones sin fragmento.
	 * @param string     $nonce   Nonce enviado.
	 * @return true|\WP_Error Con `status` 400, 401 o 403.
	 */
	public static function authorize( $do, $scope, $blog_id, $id, $nonce ) {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) return new \WP_Error( 'digitalisimo_php_login', 'Inicia sesión para continuar.', array( 'status' => 401 ) );
		if ( ! is_string( $do ) || '' === self::needs( $do ) ) return new \WP_Error( 'digitalisimo_php_action', 'Acción no válida.', array( 'status' => 400 ) );
		if ( ! in_array( $scope, array( 'site', 'network' ), true ) || ( 'network' === $scope && ! is_multisite() ) ) return new \WP_Error( 'digitalisimo_php_scope', 'Ámbito no válido.', array( 'status' => 400 ) );
		if ( 'site' === $scope && (int) $blog_id !== (int) get_current_blog_id() ) return new \WP_Error( 'digitalisimo_php_site', 'No autorizado para este sitio.', array( 'status' => 403 ) );
		if ( ! is_string( $id ) || ( '' !== $id && 'new' !== $id && ! Store::valid_id( $id ) ) ) return new \WP_Error( 'digitalisimo_php_id', 'Fragmento no válido.', array( 'status' => 400 ) );
		if ( 'edit' === self::needs( $do ) ? ! self::can_edit( $scope ) : ! self::can_stop( $scope ) ) return new \WP_Error( 'digitalisimo_php_forbidden', self::editing_blocked() ? 'La edición de código está bloqueada en este servidor (DISALLOW_FILE_EDIT o DISALLOW_FILE_MODS). Los fragmentos activos siguen ejecutándose; sólo puedes desactivarlos o detenerlos.' : 'No tienes permiso para esta acción.', array( 'status' => 403 ) );
		if ( 'edit' === self::needs( $do ) && self::reduced() ) return new \WP_Error( 'digitalisimo_php_reduced', self::REDUCED_MESSAGE, array( 'status' => 403 ) );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::nonce_action( $do, $scope, $id, (int) $blog_id ) ) ) return new \WP_Error( 'digitalisimo_php_nonce', 'La página caducó. Recárgala y vuelve a intentarlo.', array( 'status' => 403 ) );
		return true;
	}
}
