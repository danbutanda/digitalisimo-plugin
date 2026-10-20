<?php
namespace Digitalisimo\Tools\Php_Snippets;
defined( 'ABSPATH' ) || exit;

/**
 * Ejecuta los fragmentos (§4.2, §4.3): en `after_setup_theme` con prioridad PHP_INT_MIN, primero los de red y después los
 * del sitio, cada uno con `include` por el envoltorio propio y en su propio ámbito de variables.
 *
 * Contrato con Tester (segunda parte de F1b): si la petición trae `digitalisimo-php-test` (una cadena) y existe la clase
 * Tester, run() llama a Tester::claim( $token ), que valida el token (un solo uso, firma de Store::session_signature(),
 * sitio, red, caducidad y usuario), define WP_SANDBOX_SCRAPING y DONOTCACHEPAGE, llama a nocache_headers() y devuelve null
 * o el contexto de prueba:
 *
 *     array(
 *         'token'      => sha256 del token (64 hex): nombre del transitorio `…_result_<token>`,
 *         'kind'       => 'admin' | 'front',
 *         'store'      => 'site' | 'network' (dónde guarda Guard el transitorio de resultado),
 *         'user'       => usuario de la sesión,
 *         'candidates' => array( array( 'scope', 'id', 'title', 'code', 'hash', 'rev' ), … ),
 *     )
 *
 * Con ese contexto, el Runner no aplica skip(), carga cada candidato en lugar de su versión activa (aunque tenga fila
 * `off`) y Guard reúne avisos, redirección, pie de página, fragmentos cargados, salida y errores (Tester no necesita
 * registrar nada de eso) y escribe el resultado al cerrar.
 */
final class Runner {
	const TEST_PARAM = 'digitalisimo-php-test';
	const ACTION     = 'digitalisimo_tools_php';

	private static $booted  = false;
	private static $started = false;
	/** null, 'constant', 'cookie' o 'elementor': por qué no corrió ningún fragmento en esta petición. */
	private static $skipped = null;
	/** Fragmentos cargados en esta petición: [ { scope, id, hash } ]. */
	private static $loaded = array();

	/** Se llama al cargar el archivo principal de Tools, junto a Site_Options::boot(). */
	public static function boot() {
		if ( self::$booted ) return;
		self::$booted = true;
		add_action( 'after_setup_theme', array( __CLASS__, 'run' ), PHP_INT_MIN );
		add_action( Store::CRON, array( Store::class, 'cron' ) );
		// Una sola consulta para la opción de red de los fragmentos y la de Ajustes del sitio, que Hide_Login lee en plugins_loaded:1.
		if ( is_multisite() && function_exists( 'wp_prime_network_option_caches' ) ) add_action( 'plugins_loaded', array( __CLASS__, 'prime' ), 0 );
		// Después de Hide_Login::prepare (prioridad 1) y antes de cualquier fragmento.
		if ( isset( $_GET[ Rescue::PARAM ] ) ) add_action( 'plugins_loaded', array( Rescue::class, 'handle' ), 2 ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function prime() {
		wp_prime_network_option_caches( get_current_network_id(), array( \Digitalisimo\Tools\Site_Options::NETWORK, Store::NETWORK_RUN ) );
	}

	/** Motivo por el que no corrió ningún fragmento: null, 'constant', 'cookie' o 'elementor'. */
	public static function skipped() {
		return self::$skipped;
	}

	/** Modo reducido (§5.4): los fragmentos se omitieron por la constante o por la cookie de rescate. */
	public static function reduced() {
		return in_array( self::$skipped, array( 'constant', 'cookie' ), true );
	}

	/** Fragmentos cargados en esta petición: [ { scope, id, hash } ]. */
	public static function loaded() {
		return self::$loaded;
	}

	public static function run() {
		if ( self::$started ) return;
		self::$started = true;
		if ( defined( 'DIGITALISIMO_TOOLS_PHP_SAFE_MODE' ) && DIGITALISIMO_TOOLS_PHP_SAFE_MODE ) {
			self::$skipped = 'constant';
			return;
		}
		if ( function_exists( 'wp_installing' ) && wp_installing() && 'wp-activate.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) return;
		$raw  = $_GET[ self::TEST_PARAM ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification
		$test = ( is_string( $raw ) && class_exists( __NAMESPACE__ . '\\Tester' ) ) ? Tester::claim( wp_unslash( $raw ) ) : null;
		$test = is_array( $test ) ? $test : null;
		$all  = wp_load_alloptions();                                      // Ya cargado: sin consulta.
		$site = isset( $all[ Store::FLAG ] ) ? get_option( Store::SITE_RUN, array() ) : array();
		$net  = is_multisite() ? get_site_option( Store::NETWORK_RUN, array() ) : array();
		$site = is_array( $site ) ? $site : array();
		$net  = is_array( $net ) ? $net : array();
		if ( ! $test && empty( $site['items'] ) && empty( $net['items'] ) ) return; // Sin fragmentos: nada más.
		if ( ! $test && self::skip() ) return;
		if ( ! Stream::register() ) return;                                // Sin envoltorio, o esquema ajeno.
		Guard::arm( $test );
		foreach ( self::plan( $site, $net, $all, $test ) as $entry ) self::load( $entry, (bool) $test );
	}

	/**
	 * Orden de producción (§4.3): primero los de red (salvo `safe` en la red o en el sitio, pausa manual o fila `off_n` del
	 * `rev` vigente) y después los del sitio (salvo `safe` en el sitio, `safe_sites` en la red o fila `off_s`), cada grupo en
	 * orden de creación. En la prueba, cada candidato sustituye a su versión activa en su misma posición, y uno nuevo va al
	 * final de su ámbito; el candidato se carga aunque tenga fila `off` o el ámbito esté detenido.
	 *
	 * @param array      $site `_run` del sitio (crudo).
	 * @param array      $net  `_run` de la red (crudo).
	 * @param array      $all  wp_load_alloptions().
	 * @param array|null $test Contexto de prueba.
	 * @return array Entradas { scope, id, title, code, hash, sig, rev, kid, candidate }.
	 */
	public static function plan( array $site, array $net, array $all, $test = null ) {
		$site       = Store::normalize_run( $site, 'site' );
		$net        = is_multisite() ? Store::normalize_run( $net, 'network' ) : array( 'items' => array(), 'safe' => false, 'safe_sites' => false, 'kid' => '' );
		$candidates = array( 'network' => array(), 'site' => array() );
		foreach ( is_array( $test['candidates'] ?? null ) ? $test['candidates'] : array() as $c ) {
			$scope = Store::scope( (string) ( $c['scope'] ?? 'site' ) );
			$id    = (string) ( $c['id'] ?? '' );
			$code  = (string) ( $c['code'] ?? '' );
			$hash  = (string) ( $c['hash'] ?? '' );
			if ( ! Store::valid_id( $id ) || '' === $hash || ! hash_equals( hash( 'sha256', $code ), $hash ) ) continue;
			$candidates[ $scope ][ $id ] = array( 'scope' => $scope, 'id' => $id, 'title' => (string) ( $c['title'] ?? '' ), 'code' => $code, 'hash' => $hash, 'sig' => '', 'rev' => 'network' === $scope ? max( 1, (int) ( $c['rev'] ?? ( $net['items'][ $id ]['rev'] ?? 1 ) ) ) : 0, 'kid' => '', 'candidate' => true );
		}
		$network = self::scope_plan( 'network', $net['items'], $candidates['network'], $net['safe'] || $site['safe'], static function ( $id, $item ) use ( $site, $all ) {
			return isset( $site['paused'][ $id ] ) || isset( $all[ Store::off_name( 'network', $id, $item['hash'], $item['rev'] ) ] );
		}, $net['kid'] );
		$own = self::scope_plan( 'site', $site['items'], $candidates['site'], $site['safe'] || ! empty( $net['safe_sites'] ), static function ( $id, $item ) use ( $all ) {
			return isset( $all[ Store::off_name( 'site', $id, $item['hash'] ) ] );
		}, $site['kid'] );
		return array_merge( $network, $own );
	}

	private static function scope_plan( $scope, array $items, array $candidates, $stopped, callable $off, $kid ) {
		$out = array();
		foreach ( $items as $id => $item ) {
			if ( isset( $candidates[ $id ] ) ) {
				$out[] = $candidates[ $id ];
				unset( $candidates[ $id ] );
				continue;
			}
			if ( $stopped || $off( $id, $item ) ) continue;
			$out[] = array( 'scope' => $scope, 'id' => (string) $id, 'title' => $item['title'], 'code' => $item['code'], 'hash' => $item['hash'], 'sig' => $item['sig'], 'rev' => 'network' === $scope ? (int) $item['rev'] : 0, 'kid' => (string) $kid, 'candidate' => false );
		}
		foreach ( $candidates as $candidate ) $out[] = $candidate;
		return $out;
	}

	/**
	 * Carga un fragmento: comprueba hash y firma (salvo un candidato de la prueba), lo incluye por el envoltorio en un ámbito
	 * de variables propio, atrapa un Throwable, mide la salida (descartada en producción, error en la prueba) sin tocar los
	 * búferes que el fragmento haya dejado o cerrado, y borra el código del envoltorio.
	 */
	private static function load( array $e, $test ) {
		if ( empty( $e['candidate'] ) && ! Store::verify( $e ) ) {
			Store::flag_bad_signature( $e['scope'], '' !== $e['kid'] && hash_equals( Store::kid(), $e['kid'] ) );
			return;
		}
		$path  = Stream::put( $e['scope'], $e['id'], $e['code'], $e['hash'] );
		$level = ob_get_level();
		ob_start();
		Guard::enter( $e, $path );
		try {
			( static function () {
				include func_get_arg( 0 );
			} )( $path );
		} catch ( \Throwable $t ) {
			Guard::caught( $e, $t );
		} finally {
			Guard::leave();
			Stream::forget( $path );
			if ( ob_get_level() === $level + 1 ) {
				$bytes = strlen( (string) ob_get_clean() );
			} else {
				$bytes = 0;
				Guard::buffer_left( $e );
			}
			if ( $bytes ) Guard::output( $e, $bytes );
		}
		self::$loaded[] = array( 'scope' => $e['scope'], 'id' => $e['id'], 'hash' => $e['hash'] );
		if ( $test ) Guard::loaded( $e );
	}

	/**
	 * Modo seguro de la petición: la cookie de rescate válida (sólo en el acceso y en la pantalla «Código PHP», con su
	 * usuario y can_stop) o el modo seguro del editor de Elementor.
	 */
	private static function skip() {
		if ( self::cookie_applies() ) {
			self::$skipped = 'cookie';
			return true;
		}
		if ( 'elementor-safe' === get_option( 'stylesheet' ) ) {
			self::$skipped = 'elementor';
			return true;
		}
		return false;
	}

	/** El usuario sólo se calcula si la petición trae la cookie propia y es una ruta de recuperación. */
	private static function cookie_applies() {
		if ( empty( $_COOKIE[ Rescue::cookie_name() ] ) ) return false;
		$route = self::recovery_route();
		if ( '' === $route ) return false;
		$uid = Rescue::cookie_user();
		if ( null === $uid ) return false;
		if ( 'login' === $route ) return true;
		return ( 0 === $uid || (int) get_current_user_id() === $uid ) && Access::can_stop();
	}

	/**
	 * Ruta donde actúa la cookie de rescate: 'login' (wp-login.php o la ruta privada, que también fija $pagenow), 'screen'
	 * (la pantalla «Código PHP» de sitio o de red y su manejador) o ''.
	 */
	public static function recovery_route() {
		$page = (string) ( $GLOBALS['pagenow'] ?? '' );
		if ( 'wp-login.php' === $page ) return 'login';
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) return '';
		$action = $_REQUEST['action'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $page, array( 'admin-post.php', 'admin-ajax.php' ), true ) && self::ACTION === $action ) return 'screen';
		if ( 'admin.php' === $page && Store::PAGE === ( $_GET['page'] ?? '' ) && 'php' === ( $_GET['tab'] ?? '' ) ) return 'screen'; // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}
}

/**
 * Enlace de rescate y modo seguro de Código PHP (§6.5).
 *
 * - Clave `<uid>.<32 hex>`: se guarda su sha256 en `digitalisimo_tools_php_rescue` ({ epoch, keys:{ uid:{ hash, exp, at,
 *   network } } }), vale 7 días y un solo uso. Una por usuario: crear otra sustituye a la anterior. Como mucho 20 vigentes.
 * - Clave `0.<32 hex>`: la pide cualquiera en `/?digitalisimo-php-rescue=request` y llega al `admin_email` (el de la red en
 *   Multisite), como mucho una vez cada 15 minutos por sitio (`…_rescue_req`, límite atómico) y, en una red, una vez cada
 *   15 minutos en toda la red (`…_rescue_req_network`, en la tabla del sitio principal): todos los pedidos llegan a la misma
 *   dirección, y N sitios no deben multiplicar los correos.
 * - Usar el enlace: página con un botón POST «Entrar sin fragmentos» (un escáner de correo no lo gasta); al confirmar se
 *   borra la clave, se crea la cookie `digitalisimo_tools_php_safe_<blog_id>` (una hora) y se redirige a la pantalla o al
 *   acceso privado con `redirect_to`.
 * - La cookie sólo actúa en el acceso y en la pantalla «Código PHP» (Runner::recovery_route()); «Revocar» sube `epoch`.
 */
final class Rescue {
	const PARAM            = 'digitalisimo-php-rescue';
	const COOKIE           = 'digitalisimo_tools_php_safe_';
	const KEY_TTL          = 604800;
	const COOKIE_TTL       = 3600;
	const REQUEST_INTERVAL = 900;
	const MAX_KEYS         = 20;
	const CONFIRM_FIELD    = 'digitalisimo_php_rescue_confirm';
	const REQUEST_FIELD    = 'digitalisimo_php_rescue_request';

	/** Manejador de `?digitalisimo-php-rescue=…` en plugins_loaded:2. Una clave incorrecta se ignora en silencio. */
	public static function handle() {
		$raw = $_GET[ self::PARAM ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! is_string( $raw ) ) return;
		$raw  = wp_unslash( $raw );
		$post = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
		if ( 'request' === $raw ) {
			if ( $post && isset( $_POST[ self::REQUEST_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				self::send_request();
				self::finish( self::render( 'requested' ) );
			}
			self::finish( self::render( 'request' ) );
		}
		if ( ! self::check_key( $raw ) ) return;
		if ( $post && isset( $_POST[ self::CONFIRM_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$target = self::confirm( $raw );
			if ( null === $target ) self::finish( self::render( 'used' ) );
			wp_safe_redirect( $target );
			exit;
		}
		self::finish( self::render( 'confirm' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Claves
	 * ------------------------------------------------------------------ */

	/** { epoch, keys } del sitio actual. */
	public static function data() {
		$data = Store::get( 'site', Store::RESCUE, array() );
		$data = is_array( $data ) ? $data : array();
		$keys = array();
		foreach ( is_array( $data['keys'] ?? null ) ? $data['keys'] : array() as $uid => $key ) {
			if ( (int) $uid > 0 && is_array( $key ) && is_string( $key['hash'] ?? null ) ) $keys[ (int) $uid ] = array( 'hash' => $key['hash'], 'exp' => (int) ( $key['exp'] ?? 0 ), 'at' => (int) ( $key['at'] ?? 0 ), 'network' => ! empty( $key['network'] ) );
		}
		return array( 'epoch' => (int) ( $data['epoch'] ?? 0 ), 'keys' => $keys );
	}

	/**
	 * Crea la clave personal de $uid (sustituye la anterior) y devuelve el enlace **una sola vez**: nunca se guarda en claro,
	 * ni en un transitorio ni en el registro. $network dice si la pantalla de destino es la de la red.
	 *
	 * @return string|\WP_Error
	 */
	public static function create( $uid, $network = false ) {
		$uid = (int) $uid;
		if ( $uid <= 0 ) return new \WP_Error( 'digitalisimo_php_rescue', 'Usuario no válido.', array( 'status' => 400 ) );
		$secret = bin2hex( random_bytes( 16 ) );
		$done   = Store::mutate( 'site', static function () use ( $uid, $secret, $network ) {
			$data = self::data();
			$now  = time();
			$data['keys'][ $uid ] = array( 'hash' => hash( 'sha256', $secret ), 'exp' => $now + self::KEY_TTL, 'at' => $now, 'network' => (bool) $network );
			$data['keys']         = array_filter( $data['keys'], static function ( $key ) use ( $now ) { return $key['exp'] > $now; } );
			uasort( $data['keys'], static function ( $a, $b ) { return $b['at'] <=> $a['at']; } );
			$data['keys'] = array_slice( $data['keys'], 0, self::MAX_KEYS, true );
			update_option( Store::RESCUE, $data, false );
			Store::log( 'site', 'rescue_created', array( 'user' => $uid ) );
			return true;
		} );
		return is_wp_error( $done ) ? $done : self::link( $uid, $secret );
	}

	public static function link( $uid, $secret ) {
		return add_query_arg( self::PARAM, (int) $uid . '.' . $secret, home_url( '/' ) );
	}

	/** Página pública para pedir un enlace a la dirección de administración. */
	public static function request_link() {
		return add_query_arg( self::PARAM, 'request', home_url( '/' ) );
	}

	/** Caducidad de la clave vigente de $uid, o 0 si no tiene. */
	public static function status( $uid ) {
		$key = self::data()['keys'][ (int) $uid ] ?? null;
		return ( $key && $key['exp'] > time() ) ? $key['exp'] : 0;
	}

	/** «Revocar enlaces y modos seguros»: sube `epoch` (invalida todas las cookies) y borra las claves. */
	public static function revoke( $by = 0 ) {
		return Store::mutate( 'site', static function () use ( $by ) {
			$data = self::data();
			update_option( Store::RESCUE, array( 'epoch' => $data['epoch'] + 1, 'keys' => array() ), false );
			delete_option( Store::RESCUE_REQ );
			Store::log( 'site', 'rescue_revoked', array( 'user' => $by ) );
			return true;
		} );
	}

	/**
	 * Comprueba una clave sin gastarla.
	 *
	 * @return array|null { uid, network }
	 */
	public static function check_key( $raw ) {
		if ( ! is_string( $raw ) || ! preg_match( '/^(\d{1,20})\.([0-9a-f]{32})$/D', $raw, $m ) ) return null;
		$uid  = (int) $m[1];
		$hash = hash( 'sha256', $m[2] );
		if ( $uid > 0 ) {
			$key = self::data()['keys'][ $uid ] ?? null;
			return ( $key && $key['exp'] > time() && hash_equals( $key['hash'], $hash ) ) ? array( 'uid' => $uid, 'network' => $key['network'] ) : null;
		}
		$req = self::request_row();
		return ( is_array( $req['data'] ) && ( $req['data']['exp'] ?? 0 ) > time() && is_string( $req['data']['hash'] ?? null ) && '' !== $req['data']['hash'] && hash_equals( $req['data']['hash'], $hash ) ) ? array( 'uid' => 0, 'network' => is_multisite() && is_main_site() ) : null;
	}

	/**
	 * Gasta una clave de forma atómica (un solo uso).
	 *
	 * @return array|null { uid, network }
	 */
	public static function consume( $raw ) {
		$key = self::check_key( $raw );
		if ( ! $key ) return null;
		if ( 0 === $key['uid'] ) {
			global $wpdb;
			$req = self::request_row();
			if ( ! is_array( $req['data'] ) ) return null;
			$used = array_merge( $req['data'], array( 'hash' => '' ) );
			$done = (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND option_value = %s", serialize( $used ), Store::RESCUE_REQ, $req['raw'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::forget_request_cache();
			return 1 === $done ? $key : null;
		}
		$done = Store::mutate( 'site', static function () use ( $raw, $key ) {
			if ( ! self::check_key( $raw ) ) return false;
			$data = self::data();
			unset( $data['keys'][ $key['uid'] ] );
			update_option( Store::RESCUE, $data, false );
			return true;
		} );
		return true === $done ? $key : null;
	}

	/**
	 * Confirmación del POST: gasta la clave, crea la cookie, anota `rescue_used` con la IP y devuelve el destino: la pantalla
	 * «Código PHP» con sesión, o el acceso privado (Hide_Login::login_url(), ya filtrado a prioridad 2) con `redirect_to`.
	 *
	 * @return string|null null si la clave ya no vale.
	 */
	public static function confirm( $raw ) {
		$key = self::consume( $raw );
		if ( ! $key ) return null;
		self::set_cookie( $key['uid'] );
		Store::log( 'site', 'rescue_used', array( 'user' => $key['uid'] ) );
		return self::target( $key['network'], is_user_logged_in() );
	}

	/** Destino tras confirmar: la pantalla con sesión, o el acceso con `redirect_to` a ella. */
	public static function target( $network, $logged_in ) {
		$screen = self::screen_url( $network );
		return $logged_in ? $screen : add_query_arg( 'redirect_to', rawurlencode( $screen ), \Digitalisimo\Tools\Hide_Login::login_url() );
	}

	/** Pantalla «Código PHP» de sitio o de red. */
	public static function screen_url( $network ) {
		return add_query_arg( array( 'page' => Store::PAGE, 'tab' => 'php' ), ( $network && is_multisite() ) ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) );
	}

	/**
	 * POST de la página pública: como mucho una vez cada 15 minutos por sitio (y, en una red, una vez cada 15 minutos en toda
	 * la red), envía una clave `0.<hex>` a la dirección de administración. Atómico: take_turn() en cada fila; sólo la petición
	 * que cambia la fila envía el correo. La respuesta pública es siempre igual.
	 *
	 * @return bool Si esta petición envió el correo.
	 */
	public static function send_request() {
		$secret = bin2hex( random_bytes( 16 ) );
		$now    = time();
		$value  = serialize( array( 'hash' => hash( 'sha256', $secret ), 'exp' => $now + self::KEY_TTL, 'at' => $now ) );
		if ( is_multisite() && ! self::take_turn( (int) get_main_site_id(), Store::RESCUE_REQ_NETWORK, serialize( array( 'at' => $now ) ), $now ) ) return false;
		$sent = self::take_turn( (int) get_current_blog_id(), Store::RESCUE_REQ, $value, $now );
		self::forget_request_cache();
		if ( ! $sent ) return false;
		$to = (string) ( is_multisite() ? get_site_option( 'admin_email' ) : get_option( 'admin_email' ) );
		Store::mail( $to, sprintf( '[%s] Enlace de rescate de Código PHP', wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) ), array(
			sprintf( 'Alguien pidió un enlace de rescate de Código PHP para %s.', home_url( '/' ) ),
			'Si algo dejó de funcionar, este enlace te deja entrar sin fragmentos para desactivarlos o detenerlos. Vale 7 días y una sola vez:',
			self::link( 0, $secret ),
			'',
			'Si no lo pediste, ignora este correo.',
		) );
		return true;
	}

	/**
	 * Toma de forma atómica el turno de la fila $name en la tabla de opciones de $blog_id: `INSERT IGNORE` o, si ya existe y
	 * tiene más de 15 minutos, un UPDATE condicionado al valor leído. Sólo una petición concurrente lo consigue.
	 */
	private static function take_turn( $blog_id, $name, $value, $now ) {
		global $wpdb;
		if ( Store::insert_row( $blog_id, $name, $value, false ) ) return true;
		$table = $wpdb->get_blog_prefix( (int) $blog_id ) . 'options';
		$raw   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `{$table}` WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$data  = '' === $raw ? null : Store::unserialize( $raw );
		if ( '' === $raw || ( is_array( $data ) && $now - (int) ( $data['at'] ?? 0 ) < self::REQUEST_INTERVAL ) ) return false;
		return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $name, $raw ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** Fila `…_rescue_req` leída sin caché: { raw, data }. */
	private static function request_row() {
		global $wpdb;
		$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s", Store::RESCUE_REQ ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array( 'raw' => $raw, 'data' => '' === $raw ? null : Store::unserialize( $raw ) );
	}

	private static function forget_request_cache() {
		if ( ! function_exists( 'wp_cache_delete' ) ) return;
		wp_cache_delete( Store::RESCUE_REQ, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/* ------------------------------------------------------------------ *
	 * Cookie
	 * ------------------------------------------------------------------ */

	/** El nombre lleva el sitio: en una red por subdominios todos comparten COOKIE_DOMAIN. */
	public static function cookie_name( $blog_id = null ) {
		return self::COOKIE . (int) ( null === $blog_id ? get_current_blog_id() : $blog_id );
	}

	/** hmac( 'v1|red|sitio|uid|exp|epoch', wp_salt( 'auth' ) ). */
	private static function cookie_mac( $uid, $exp, $blog_id = null, $epoch = null ) {
		$blog  = (int) ( null === $blog_id ? get_current_blog_id() : $blog_id );
		$epoch = null === $epoch ? self::data()['epoch'] : (int) $epoch;
		return hash_hmac( 'sha256', 'v1|' . (int) get_current_network_id() . '|' . $blog . '|' . (int) $uid . '|' . (int) $exp . '|' . $epoch, wp_salt( 'auth' ) );
	}

	/** Valor firmado de la cookie: `uid|exp|hmac`. */
	public static function cookie_value( $uid, $exp ) {
		return (int) $uid . '|' . (int) $exp . '|' . self::cookie_mac( $uid, $exp );
	}

	/** Usuario de una cookie válida de este sitio (0 si viene de una clave pedida a la dirección de administración), o null. */
	public static function cookie_user() {
		$value = $_COOKIE[ self::cookie_name() ] ?? null;
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{1,20})\|(\d{1,12})\|([0-9a-f]{64})$/D', $value, $m ) ) return null;
		$exp = (int) $m[2];
		if ( $exp <= time() || $exp > time() + self::COOKIE_TTL + 60 ) return null;
		return hash_equals( self::cookie_mac( (int) $m[1], $exp ), $m[3] ) ? (int) $m[1] : null;
	}

	/** Hasta cuándo vale la cookie de esta petición (para «hasta las {hora}»), o 0. */
	public static function cookie_expires() {
		if ( null === self::cookie_user() ) return 0;
		return (int) explode( '|', (string) $_COOKIE[ self::cookie_name() ] )[1];
	}

	/** Crea la cookie de modo seguro durante una hora, en COOKIEPATH y SITECOOKIEPATH. */
	public static function set_cookie( $uid ) {
		$exp   = time() + self::COOKIE_TTL;
		$name  = self::cookie_name();
		$value = self::cookie_value( $uid, $exp );
		self::send_cookie( $name, $value, $exp );
		$_COOKIE[ $name ] = $value;
	}

	/** «Salir del modo seguro»: borra la cookie de este sitio. */
	public static function clear_cookie() {
		$name = self::cookie_name();
		self::send_cookie( $name, '', time() - 3600 );
		unset( $_COOKIE[ $name ] );
	}

	private static function send_cookie( $name, $value, $exp ) {
		if ( headers_sent() ) return;
		$paths = array_unique( array( defined( 'COOKIEPATH' ) ? COOKIEPATH : '/', defined( 'SITECOOKIEPATH' ) ? SITECOOKIEPATH : '/' ) );
		foreach ( $paths as $path ) {
			setcookie( $name, $value, array( 'expires' => $exp, 'path' => (string) $path, 'domain' => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Página mínima
	 * ------------------------------------------------------------------ */

	/**
	 * HTML de la página de rescate, sin recursos externos y sin repetir el parámetro: el formulario se envía a la misma
	 * dirección. $kind ∈ confirm, used, request, requested.
	 */
	public static function render( $kind ) {
		$texts = array(
			'confirm'   => array( 'Entra sin fragmentos PHP', 'Este enlace abre el modo seguro de Código PHP durante una hora: al iniciar sesión y en la pantalla «Código PHP» no se ejecuta ningún fragmento, para que puedas desactivarlos o detenerlos. El enlace sólo sirve una vez.', self::CONFIRM_FIELD, 'Entrar sin fragmentos' ),
			'used'      => array( 'El enlace ya no es válido', 'Este enlace de rescate ya se usó, caducó o fue revocado. Puedes pedir uno nuevo a la dirección de administración.', '', '' ),
			'request'   => array( 'Enlace de rescate de Código PHP', 'Si un fragmento PHP te impide entrar al escritorio, te enviamos un enlace de rescate a la dirección de administración del sitio. Vale 7 días y una sola vez.', self::REQUEST_FIELD, 'Enviar un enlace de rescate a la dirección de administración' ),
			'requested' => array( 'Solicitud recibida', 'Si la dirección de administración puede recibir correos, en unos minutos llegará un enlace de rescate. Por seguridad sólo se envía uno cada 15 minutos.', '', '' ),
		);
		list( $title, $text, $field, $button ) = $texts[ isset( $texts[ $kind ] ) ? $kind : 'used' ];
		$form = '' === $field ? '' : '<form method="post"><input type="hidden" name="' . esc_attr( $field ) . '" value="1"><button type="submit">' . esc_html( $button ) . '</button></form>';
		return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer"><title>' . esc_html( 'Modo seguro de Código PHP' ) . '</title>'
			. '<style>body{margin:0;background:var(--digi-surface,#f6f8ff);color:var(--digi-ink,#17213a);font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}main{box-sizing:border-box;max-width:520px;margin:12vh auto;padding:28px 16px}h1{font-size:24px;line-height:1.25;margin:0 0 12px}p{color:var(--digi-text,#52617e);margin:0 0 20px}button{min-height:44px;padding:10px 18px;border:0;border-radius:var(--digi-radius-control,9px);background:var(--digi-blue,#245eff);color:#fff;font:inherit;font-weight:600;cursor:pointer}button:focus-visible{outline:3px solid var(--digi-focus,#5365f5);outline-offset:2px}</style></head>'
			. '<body><main><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $text ) . '</p>' . $form . '</main></body></html>';
	}

	/** Envía la página con las cabeceras de rescate y termina la petición. */
	private static function finish( $html ) {
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Frame-Options: DENY' );
			header( 'X-Robots-Tag: noindex' );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- render() escapa cada dato.
		exit;
	}
}
