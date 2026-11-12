<?php
defined( 'ABSPATH' ) || exit;

/**
 * Respaldo antes de actualizar (I1): el núcleo, los plugins y los temas, en las actualizaciones manuales y en las
 * automáticas. Ver «Respaldo antes de actualizar» en docs/BACKUPS.md.
 *
 * - Automáticas: `auto_update_core|plugin|theme` (prioridad 100, sólo dentro de `wp_maybe_auto_update` y sólo cuando
 *   WordPress iba a actualizar). Sin un respaldo reciente válido, la actualización se retiene (el filtro devuelve false)
 *   y se encola un trabajo de respaldo en la cola de siempre: la petición no espera, el respaldo avanza por ticks
 *   (WP-Cron, la pantalla, REST o WP-CLI) dentro de su presupuesto. Cuando termina bien, se programa otra vez
 *   `wp_maybe_auto_update`, que ya encuentra el respaldo y actualiza. Si falla, la actualización queda pospuesta (se
 *   reintenta en la siguiente comprobación de WordPress) y el aviso por correo de Backups lo dice.
 * - Manuales: `upgrader_pre_download`. En la pantalla, assets/pre-update.js crea el respaldo antes de enviar la
 *   actualización y, si falla, pregunta si seguir sin respaldo. Sin JavaScript (o por un enlace directo), el servidor
 *   rechaza la actualización con un error que explica que el respaldo está en marcha. En WP-CLI el respaldo corre en
 *   el mismo proceso (sin límite de tiempo) y, si falla, la actualización se rechaza hasta `pre-update skip`.
 * - Ajuste por tipo: `all` (manuales y automáticas), `auto` (sólo automáticas) u `off`; y la antigüedad máxima del
 *   respaldo que evita repetirlo (`max_age`, en horas). Un respaldo es válido si es completo de esta instalación (o de la
 *   red), lo creó este sitio y su ZIP sigue en el almacén con el tamaño registrado o tiene una copia remota verificada.
 * - Ajustes y estado de toda la instalación: `sitemeta` en una red (Digitalisimo_Backups_Keys), igual que los avisos.
 * - Tope (revisión H1): con 48 h desde la primera retención o dos respaldos previos fallidos seguidos, WordPress
 *   actualiza igual durante 24 h y llega un correo «actualizado sin respaldo previo» (aunque «Fallos» esté apagado). No se
 *   retienen nunca el núcleo menor de la misma rama, las actualizaciones que WordPress.org fuerza (`autoupdate`) ni el
 *   propio Backups. Mientras haya retenidas o pospuestas, un aviso fijo en el escritorio lo dice.
 * - Todo lo que lee o escribe el historial corre en el sitio principal (WP-CLI con `--url` de un subsitio).
 */
final class Digitalisimo_Backups_Pre_Update {
	const OPTION = 'digitalisimo_backups_pre_update';
	const STATE = 'digitalisimo_backups_pre_update_state';
	const POLICY = 'pre-update';
	const ACTION_SAVE = 'digitalisimo_backups_save_pre_update';
	const ACTION_SKIP = 'digitalisimo_backups_pre_update_skip';
	const AJAX_START = 'digitalisimo_backups_pre_update_start';
	const AJAX_STATUS = 'digitalisimo_backups_pre_update_status';
	const AJAX_SKIP = 'digitalisimo_backups_pre_update_skip';
	const NONCE = 'digitalisimo_backups_pre_update';
	const TYPES = array( 'core' => 'WordPress (núcleo)', 'plugin' => 'Plugins', 'theme' => 'Temas' );
	const MODES = array( 'all' => 'Manuales y automáticas', 'auto' => 'Sólo automáticas', 'off' => 'Nunca' );
	/** Antigüedad máxima (horas) de un respaldo que evita hacer otro. */
	const AGES = array( 1, 6, 12, 24, 48, 168 );
	/** Respaldos previos a actualizar que conserva su propia retención. */
	const RETAIN = 3;
	/** «Actualizar sin respaldo»: minutos durante los que las actualizaciones manuales no esperan respaldo. */
	const SKIP_MINUTES = 15;
	/** Un aviso por correo de un respaldo que ni siquiera pudo encolarse, como mucho cada 12 horas. */
	const MAIL_EVERY = 43200;
	const ITEMS = 20;
	/** Tope de la retención: horas desde la primera retención y respaldos previos fallidos seguidos. */
	const MAX_HOLD = 172800;
	const MAX_FAILS = 2;
	/** Tras el tope, las automáticas no esperan respaldo durante este tiempo (y no se reintenta el respaldo previo). */
	const BYPASS_FOR = 86400;
	/** Un respaldo previo lento (cron poco frecuente, sitio grande) no caduca a las 24 h del motor, sino a las 72 h. */
	const EXPIRES_AFTER = 259200;
	/** Tras un respaldo previo fallido, la puerta web no encola otro durante este tiempo: responde con el motivo. */
	const RECENT_FAILURE = 900;
	/** WP-CLI: sin ningún avance del respaldo durante este tiempo, la espera termina (por encima del reintento más largo). */
	const STALL = 1200;
	/** Pruebas: fuerza el modo WP-CLI (null lo detecta), sustituye la espera entre ticks, el reloj y la cuenta. */
	public static $cli = null;
	public static $sleep = 'sleep';
	public static $clock = 'time';
	public static $user = null;
	/** Pruebas: argumentos de la orden de WP-CLI (array( posicionales, con nombre )); null los lee de WP-CLI. */
	public static $cli_args = null;
	/** WP-CLI: el respaldo previo de este proceso ya falló (no se repite por cada plugin del lote; las pruebas lo vacían). */
	public static $cli_failure = null;

	public static function init() {
		add_filter( 'auto_update_core', array( __CLASS__, 'auto_core' ), 100, 2 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_plugin' ), 100, 2 );
		add_filter( 'auto_update_theme', array( __CLASS__, 'auto_theme' ), 100, 2 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'gate_download' ), 100, 4 );
		add_filter( 'digitalisimo_backups_job_origin', array( __CLASS__, 'origin' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_SKIP, array( __CLASS__, 'admin_skip' ) );
		add_action( 'wp_ajax_' . self::AJAX_START, array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_' . self::AJAX_STATUS, array( __CLASS__, 'ajax_status' ) );
		add_action( 'wp_ajax_' . self::AJAX_SKIP, array( __CLASS__, 'ajax_skip' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		// WP-CLI: el respaldo va antes de que empiece el actualizador (nunca con el sitio en mantenimiento).
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) && method_exists( 'WP_CLI', 'add_hook' ) ) {
			foreach ( array( 'core' => 'core update', 'plugin' => 'plugin update', 'theme' => 'theme update' ) as $type => $command ) WP_CLI::add_hook( 'before_invoke:' . $command, function() use ( $type ) { Digitalisimo_Backups_Pre_Update::cli_before( $type ); } );
		}
		// Un trabajo que terminó en un tick sin este enganche (p. ej. tras morir su proceso) se resuelve en el watchdog.
		add_action( Digitalisimo_Backups_Jobs::HOOK_WATCHDOG, array( __CLASS__, 'after_tick' ), 20 );
	}

	/* ------------------------------------------------------------------ Ajustes y estado */

	public static function defaults() {
		return array( 'core' => 'all', 'plugin' => 'all', 'theme' => 'all', 'max_age' => 24 );
	}
	public static function settings() {
		$saved = Digitalisimo_Backups_Keys::get( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out = self::defaults();
		foreach ( array_keys( self::TYPES ) as $type ) if ( isset( $saved[ $type ] ) && is_string( $saved[ $type ] ) && isset( self::MODES[ $saved[ $type ] ] ) ) $out[ $type ] = $saved[ $type ];
		if ( isset( $saved['max_age'] ) && in_array( (int) $saved['max_age'], self::AGES, true ) ) $out['max_age'] = (int) $saved['max_age'];
		return $out;
	}
	/** Valida y guarda lo que llega (lo que falta conserva su valor). Un valor desconocido no cambia nada (400). */
	public static function store( array $input ) {
		$settings = self::settings();
		foreach ( array_keys( self::TYPES ) as $type ) {
			if ( ! array_key_exists( $type, $input ) || null === $input[ $type ] || '' === $input[ $type ] ) continue;
			$mode = is_string( $input[ $type ] ) ? sanitize_key( $input[ $type ] ) : '';
			if ( ! isset( self::MODES[ $mode ] ) ) return new WP_Error( 'digitalisimo_backups_pre_update_mode', 'Modo no válido para ' . self::TYPES[ $type ] . ': usa all, auto u off. No se cambió nada.', array( 'status' => 400 ) );
			$settings[ $type ] = $mode;
		}
		if ( array_key_exists( 'max_age', $input ) && null !== $input['max_age'] && '' !== $input['max_age'] ) {
			$age = is_numeric( $input['max_age'] ) ? (int) $input['max_age'] : 0;
			if ( ! in_array( $age, self::AGES, true ) ) return new WP_Error( 'digitalisimo_backups_pre_update_age', 'Antigüedad no válida: usa ' . implode( ', ', self::AGES ) . ' horas. No se cambió nada.', array( 'status' => 400 ) );
			$settings['max_age'] = $age;
		}
		Digitalisimo_Backups_Keys::put( self::OPTION, $settings );
		return $settings;
	}
	private static function state() {
		$saved = Digitalisimo_Backups_Keys::get( self::STATE, array() );
		return array_merge( array( 'job' => '', 'auto' => false, 'items' => array(), 'since' => 0, 'failure' => '', 'failed_at' => 0, 'fails' => 0, 'mailed_at' => 0, 'skip_until' => 0, 'skip_via' => '', 'skip_user' => 0, 'last_ok' => 0, 'last_file' => '', 'win_from' => 0, 'win_extra' => 0, 'bypass_until' => 0, 'bypass_reason' => '', 'bypass_items' => array() ), is_array( $saved ) ? $saved : array() );
	}
	private static function user() { return null !== self::$user ? (int) self::$user : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 ); }
	/** En una red, el historial y la cola son del sitio principal: WP-CLI con `--url` de un subsitio también va allí. */
	private static function on_main( callable $callback ) {
		$switch = is_multisite() && ! is_main_site() && function_exists( 'switch_to_blog' );
		if ( $switch ) switch_to_blog( get_main_site_id() );
		try {
			return $callback();
		} finally {
			if ( $switch ) restore_current_blog();
		}
	}
	/** Cambia el estado con el cerrojo del registro de trabajos (dos procesos no se pisan). */
	private static function change( callable $change ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $change ) {
			$state = self::state();
			if ( false !== $change( $state ) ) Digitalisimo_Backups_Keys::put( self::STATE, $state );
			return $state;
		} );
	}

	/**
	 * Respaldo reciente válido de esta instalación (o de la red): completo, creado aquí (no subido, importado ni una copia
	 * previa a restaurar), dentro de la antigüedad máxima, con su ZIP en el almacén y el tamaño registrado (o una copia
	 * remota verificada). El SHA-256 se comprobó al crearlo; aquí no se vuelve a leer el archivo entero.
	 */
	public static function fresh( $max_age = null ) {
		return self::on_main( function() use ( $max_age ) { return self::fresh_here( $max_age ); } );
	}
	private static function fresh_here( $max_age ) {
		$max_age = null === $max_age ? self::settings()['max_age'] : (int) $max_age;
		$now = strtotime( current_time( 'mysql' ) );
		$scope = is_multisite() ? 'network' : 'installation';
		try { $dir = Digitalisimo_Backups::storage_directory(); } catch ( Throwable $e ) { $dir = ''; }
		foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $item ) {
			$item = (array) $item;
			if ( $scope !== (string) ( $item['scope'] ?? '' ) || 'full' !== (string) ( $item['backup_type'] ?? 'full' ) ) continue;
			if ( 'created' !== (string) ( $item['origin'] ?? 'created' ) || 'pre-restore' === (string) ( $item['policy'] ?? '' ) ) continue;
			// Los avisos `files_missing` y `files_changed` (una caché que desaparece o cambia durante el respaldo) no lo
			// descalifican: es lo normal en un sitio con caché, y descartarlo repetiría respaldos completos sin fin (revisión N1).
			$time = strtotime( (string) ( $item['date'] ?? '' ) );
			if ( false === $time || false === $now ) continue;
			$age = $now - $time;
			// Una fecha en el futuro (reloj cambiado) no cuenta como reciente.
			if ( $age < -300 || $age > $max_age * 3600 ) continue;
			if ( ! self::copy_exists( $item, $dir ) ) continue;
			return array( 'file' => (string) $item['file'], 'date' => (string) $item['date'], 'age_minutes' => (int) floor( max( 0, $age ) / 60 ), 'policy' => (string) ( $item['policy'] ?? 'manual' ) );
		}
		return null;
	}
	/**
	 * Respaldo reciente para actualizar: el válido del historial o, si no aparece, el respaldo previo que terminó bien
	 * dentro de la antigüedad máxima, siempre que su archivo siga en el almacén y sano (intact()). Si se borró o se dañó,
	 * se permite **un** respaldo previo más en esa ventana (`win_extra`); después cuenta igual, para no repetir respaldos
	 * completos sin fin (revisión N1 y su límite 1).
	 */
	public static function recent() {
		$fresh = self::fresh();
		if ( $fresh ) return $fresh;
		$state = self::state();
		$ok = (int) $state['last_ok'];
		$window = self::settings()['max_age'] * 3600;
		if ( ! $ok || time() - $ok > $window ) return null;
		$file = sanitize_file_name( (string) $state['last_file'] );
		$sound = false;
		try {
			$path = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . $file;
			$sound = '' !== $file && is_file( $path ) && self::intact( $path );
		} catch ( Throwable $e ) {
			$sound = false;
		}
		if ( ! $sound && (int) $state['win_extra'] < 1 ) return null;
		return array( 'file' => (string) $state['last_file'], 'date' => gmdate( 'Y-m-d H:i:s', $ok ), 'age_minutes' => (int) floor( ( time() - $ok ) / 60 ), 'policy' => self::POLICY );
	}
	/** Dentro de la ventana del último respaldo previo bueno (para contar el respaldo de más que se permite). */
	private static function in_window( array $state ) {
		return (int) $state['win_from'] && time() - (int) $state['win_from'] <= self::settings()['max_age'] * 3600;
	}
	private static function copy_exists( array $item, $dir ) {
		$file = sanitize_file_name( (string) ( $item['file'] ?? '' ) );
		if ( '' !== $file && '' !== $dir && $file === (string) ( $item['file'] ?? '' ) ) {
			$path = rtrim( $dir, '/\\' ) . '/' . $file;
			if ( is_file( $path ) && (int) filesize( $path ) === (int) ( $item['size'] ?? -1 ) && self::intact( $path ) ) return true;
		}
		// Sólo en el remoto: cuenta la copia verificada (la misma regla que «copies» del historial).
		$target = (string) ( $item['transfer_destination'] ?? '' );
		if ( '' === $target ) $target = (string) ( $item['destination'] ?? 'local' );
		if ( '' === $target || 'local' === $target ) return false;
		$state = (string) ( $item['transfer'] ?? '' );
		if ( '' === $state ) $state = ! empty( $item['remote']['provider'] ) ? 'ok' : '';
		return 'ok' === $state;
	}

	/**
	 * Comprobación barata (sin releer el archivo entero): un ZIP empieza por una cabecera local y termina con el registro
	 * del directorio central (EOCD); un `.dgbk`, con la cabecera DGBK v1. Un archivo dañado del mismo tamaño no cuenta.
	 */
	private static function intact( $path ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) return false;
		try {
			$head = (string) fread( $handle, 8 );
			if ( '.dgbk' === substr( $path, -5 ) ) return 'DGBK' === substr( $head, 0, 4 ) && 1 === ord( $head[4] ?? "\0" ) && "\0\0\0" === substr( $head, 5, 3 );
			if ( "PK\x03\x04" !== substr( $head, 0, 4 ) ) return false;
			$size = (int) filesize( $path );
			$tail = min( $size, 65557 );
			if ( $tail < 22 || 0 !== fseek( $handle, -$tail, SEEK_END ) ) return false;
			$end = (string) fread( $handle, $tail );
			$at = strrpos( $end, "PK\x05\x06" );
			return false !== $at && strlen( $end ) - $at >= 22;
		} finally {
			fclose( $handle );
		}
	}

	/** Respaldo completo de la instalación sin terminar (manual o automatización): se espera a ése en vez de hacer otro. */
	private static function active_backup() {
		foreach ( Digitalisimo_Backups_Jobs::active() as $job ) {
			$spec = (array) ( $job['spec'] ?? array() );
			if ( Digitalisimo_Backups_Jobs::is_restore( $job ) || ! empty( $spec['inline'] ) || Digitalisimo_Backups_Site_Extract::is_extract( $spec ) || ! empty( $job['cancel_requested_at'] ) ) continue;
			if ( 'full' !== (string) ( $spec['type'] ?? '' ) ) continue;
			// Una migración (recibida o el paquete para otro sitio) no es un respaldo de esta instalación.
			if ( ! empty( $spec['migration'] ) || ( isset( $spec['kind'] ) && 'backup' !== $spec['kind'] ) ) continue;
			return $job;
		}
		return null;
	}

	/**
	 * Lo que hace falta para actualizar ahora. Devuelve `ready` (respaldo reciente válido), `running` (un respaldo en
	 * curso: propio, encolado ahora, o uno que ya estaba en marcha) o `failed` (ni siquiera pudo encolarse). Nunca espera.
	 * `$auto`: la pide una actualización automática, que queda retenida (con `$item` en la lista) hasta que termine.
	 */
	public static function ensure( $via, $auto = false, $item = '' ) {
		return self::on_main( function() use ( $via, $auto, $item ) { return self::ensure_here( $via, $auto, $item ); } );
	}
	private static function ensure_here( $via, $auto, $item ) {
		$queued = null;
		$state = self::resolve();
		$fresh = self::recent();
		if ( $fresh ) return array( 'state' => 'ready', 'fresh' => $fresh );
		$job = '' !== $state['job'] ? Digitalisimo_Backups_Jobs::get( $state['job'] ) : null;
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) $job = self::active_backup();
		if ( ! $job ) {
			try {
				$spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => self::POLICY, 'destination' => 'local', 'retain' => self::RETAIN, 'retention' => 'policy', 'via' => $via, 'network' => is_multisite(), 'site_id' => is_multisite() ? get_main_site_id() : 0 ) );
				// Un respaldo lento no caduca a las 24 h (si caducara siempre, la retención no se liberaría nunca).
				$spec['expires_after'] = self::EXPIRES_AFTER;
				$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
				$job = $queued['job'];
			} catch ( Throwable $e ) {
				$message = 'No se pudo iniciar el respaldo previo: ' . $e->getMessage();
				self::failed( $message, $auto, $item );
				return array( 'state' => 'failed', 'message' => $message );
			}
			Digitalisimo_Backups_Jobs::schedule( true );
		}
		$id = (string) $job['id'];
		$launched = ! empty( $queued ) && empty( $queued['duplicate'] );
		self::change( function( &$state ) use ( $id, $auto, $item, $launched ) {
			if ( $launched && $state['job'] !== $id && self::in_window( $state ) ) $state['win_extra'] = (int) $state['win_extra'] + 1;
			$state['job'] = $id;
			if ( $auto ) {
				$state['auto'] = true;
				if ( ! $state['since'] ) $state['since'] = time();
				self::add_item( $state, $item );
			}
		} );
		return array( 'state' => 'running', 'job' => $id, 'view' => Digitalisimo_Backups_Jobs::view( $job ) );
	}
	private static function add_item( array &$state, $item ) {
		$item = trim( preg_replace( '/[\x00-\x1f<>]+/', '', (string) $item ) );
		if ( '' === $item ) return;
		$items = array_values( array_unique( array_merge( (array) $state['items'], array( substr( $item, 0, 120 ) ) ) ) );
		$state['items'] = array_slice( $items, 0, self::ITEMS );
	}
	/** Un respaldo que no pudo encolarse: queda anotado; si retenía automáticas, avisa por correo (cada 12 h como mucho). */
	private static function failed( $message, $auto, $item = '' ) {
		$mail = false;
		self::change( function( &$state ) use ( $message, $auto, $item, &$mail ) {
			$state['job'] = '';
			$state['failure'] = (string) $message;
			if ( time() - (int) $state['failed_at'] > 300 ) $state['fails'] = (int) $state['fails'] + 1;
			$state['failed_at'] = time();
			if ( ! $auto ) return;
			$state['auto'] = true;
			if ( ! $state['since'] ) $state['since'] = time();
			self::add_item( $state, $item );
			if ( time() - (int) $state['mailed_at'] >= self::MAIL_EVERY ) { $state['mailed_at'] = time(); $mail = true; }
		} );
		if ( $mail ) Digitalisimo_Backups_Notify::pre_update_postponed( $message, self::state()['items'] );
	}

	/**
	 * Mira el respaldo que se espera: si terminó bien (o ya hay uno reciente válido), libera las automáticas retenidas
	 * programando otra vez `wp_maybe_auto_update`; si falló, las deja pospuestas y anota el motivo. Un respaldo ajeno
	 * (manual o de una automatización) que falla mientras retiene automáticas se sustituye por uno propio. Unas
	 * automáticas pospuestas se liberan en cuanto aparece un respaldo reciente válido (p. ej. uno manual).
	 */
	public static function resolve() {
		$state = self::state();
		$expected = (string) $state['job'];
		if ( '' === $expected && empty( $state['auto'] ) ) return $state;
		$job = '' !== $expected ? Digitalisimo_Backups_Jobs::get( $expected ) : null;
		$fresh = self::fresh();
		if ( '' === $expected && ! $fresh ) return $state;
		if ( $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) && ! $fresh ) return $state;
		$ok = $fresh || ( $job && 'success' === $job['status'] );
		$release = false;
		$retry = false;
		$state = self::change( function( &$state ) use ( $expected, $job, $ok, &$release, &$retry ) {
			// Otro proceso ya lo resolvió (o espera ya otro trabajo): nada que hacer.
			if ( (string) $state['job'] !== $expected ) return false;
			$state['job'] = '';
			if ( $ok ) {
				if ( $job && 'success' === $job['status'] ) {
					$state['last_ok'] = time();
					$state['last_file'] = basename( (string) ( $job['result']['file'] ?? $job['spec']['file'] ?? '' ) );
					if ( ! self::in_window( $state ) ) { $state['win_from'] = time(); $state['win_extra'] = 0; }
				}
				$release = ! empty( $state['auto'] );
				$state['auto'] = false;
				$state['items'] = array();
				$state['since'] = 0;
				$state['failure'] = '';
				$state['failed_at'] = 0;
				$state['fails'] = 0;
				return;
			}
			$state['fails'] = (int) $state['fails'] + 1;
			$state['failure'] = $job ? (string) ( $job['error']['message'] ?? ( 'cancelled' === $job['status'] ? 'El respaldo previo se canceló.' : 'El respaldo previo falló.' ) ) : 'El trabajo del respaldo previo ya no existe.';
			if ( $job && 'cancelled' === $job['status'] ) $state['failure'] = 'El respaldo previo se canceló.';
			$state['failed_at'] = time();
			$retry = ! empty( $state['auto'] ) && $job && 'failed' === $job['status'] && self::POLICY !== (string) ( $job['spec']['policy'] ?? '' );
		} );
		if ( $release ) self::release_auto();
		if ( $retry ) self::ensure( 'cron', true );
		return self::state();
	}
	/** Después de cada tick de la cola (y en el watchdog): también libera pospuestas tras otro respaldo válido. Nunca lanza. */
	public static function after_tick() {
		try {
			$state = self::state();
			if ( '' !== $state['job'] || ! empty( $state['auto'] ) ) self::resolve();
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: antes de actualizar: ' . $e->getMessage() );
		}
	}
	/** Vuelve a lanzar las actualizaciones automáticas de WordPress (otro proceso de WP-Cron, no éste). */
	private static function release_auto() {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) return;
		$switch = is_multisite() && function_exists( 'switch_to_blog' ) && (int) get_current_blog_id() !== (int) get_main_site_id();
		if ( $switch ) switch_to_blog( get_main_site_id() );
		try {
			$next = wp_next_scheduled( 'wp_maybe_auto_update' );
			if ( false === $next || (int) $next > time() + 600 ) wp_schedule_single_event( time(), 'wp_maybe_auto_update' );
			if ( function_exists( 'spawn_cron' ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) spawn_cron();
		} finally {
			if ( $switch ) restore_current_blog();
		}
	}

	/* ------------------------------------------------------------------ Actualizaciones automáticas */

	public static function auto_core( $update, $item = null ) { return self::hold( 'core', $update, $item ); }
	public static function auto_plugin( $update, $item = null ) { return self::hold( 'plugin', $update, $item ); }
	public static function auto_theme( $update, $item = null ) { return self::hold( 'theme', $update, $item ); }
	/** Dentro de `wp_maybe_auto_update` (WP-Cron o `wp cron event run`): no las listas de plugins ni Salud del sitio. */
	public static function auto_context() {
		$doing = function_exists( 'doing_action' ) && doing_action( 'wp_maybe_auto_update' );
		return (bool) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_pre_update_auto_context', $doing ) : $doing );
	}
	/**
	 * Decide una actualización automática que WordPress iba a hacer: sigue si hay un respaldo reciente válido; si no, se
	 * retiene (false) y el respaldo se encola. Nunca espera al respaldo ni lo hace en esta petición.
	 */
	public static function hold( $type, $update, $item = null ) {
		if ( ! $update || ! isset( self::TYPES[ $type ] ) || ! self::auto_context() ) return $update;
		if ( 'off' === self::settings()[ $type ] ) return $update;
		if ( is_multisite() && ! is_main_site() ) return $update;
		if ( self::exempt( $type, $item ) ) return $update;
		try {
			// Tras el tope, las automáticas no esperan (ni se reintenta el respaldo) hasta que pase la ventana.
			if ( (int) self::state()['bypass_until'] > time() ) return $update;
			if ( self::capped() ) return $update;
			$outcome = self::ensure( 'cron', true, self::item_label( $type, $item ) );
			if ( 'failed' === $outcome['state'] && self::capped() ) return $update;
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: antes de actualizar: ' . $e->getMessage() );
			return $update;
		}
		if ( 'ready' === $outcome['state'] ) return $update;
		// Mientras el primer respaldo está en marcha, el núcleo retenido no manda el correo de «hay una versión nueva» (el
		// aviso es el de Backups). Con las actualizaciones ya pospuestas por un fallo, WordPress avisa como siempre.
		$state = self::state();
		if ( 'core' === $type && 'running' === $outcome['state'] && '' === (string) $state['failure'] && 0 === (int) $state['fails'] && function_exists( 'add_filter' ) ) add_filter( 'send_core_update_notification_email', '__return_false' );
		return false;
	}
	/**
	 * Nunca se retienen: el núcleo menor de la misma rama (seguridad), lo que WordPress.org fuerza (`autoupdate`) y el
	 * propio Backups (la única vía para recibir el arreglo de un fallo de Backups). Filtro
	 * `digitalisimo_backups_pre_update_exempt` para añadir otros.
	 */
	public static function exempt( $type, $item ) {
		$data = is_object( $item ) ? get_object_vars( $item ) : (array) $item;
		$exempt = false;
		if ( 'core' === $type ) {
			$new = (string) ( $data['current'] ?? $data['version'] ?? '' );
			$installed = (string) ( $GLOBALS['wp_version'] ?? ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '' ) );
			$branch = function( $version ) { return implode( '.', array_slice( explode( '.', $version ), 0, 2 ) ); };
			$exempt = '' !== $new && '' !== $installed && $branch( $new ) === $branch( $installed ) && version_compare( $new, $installed, '>' );
		} elseif ( ! empty( $data['autoupdate'] ) ) {
			$exempt = true;
		} elseif ( 'plugin' === $type && self::is_backups( (string) ( $data['plugin'] ?? '' ) ) ) {
			$exempt = true;
		}
		return (bool) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_pre_update_exempt', $exempt, $type, $item ) : $exempt );
	}
	private static function is_backups( $plugin ) {
		if ( '' === $plugin ) return false;
		$own = class_exists( 'Digitalisimo_Backups_Updater', false ) ? (string) Digitalisimo_Backups_Updater::file() : '';
		return ( '' !== $own && $own === $plugin ) || 'digitalisimo-backups/digitalisimo-backups.php' === $plugin;
	}
	/**
	 * Tope de la retención: 48 h desde la primera retención o dos respaldos previos fallidos seguidos. Al llegar, abre una
	 * ventana de 24 h en la que las automáticas no esperan, avisa por correo (siempre, aunque «Fallos» esté apagado) y
	 * deja el aviso en el escritorio. Devuelve si se alcanzó.
	 */
	private static function capped() {
		$state = self::state();
		if ( empty( $state['auto'] ) ) return false;
		$hours = (int) $state['since'] && time() - (int) $state['since'] >= self::MAX_HOLD;
		$fails = (int) $state['fails'] >= self::MAX_FAILS;
		if ( ! $hours && ! $fails ) return false;
		$reason = $fails ? (int) $state['fails'] . ' respaldos previos fallidos seguidos' . ( '' !== (string) $state['failure'] ? ' (el último: ' . $state['failure'] . ')' : '' ) : 'más de ' . (int) ( self::MAX_HOLD / 3600 ) . ' h esperando un respaldo previo';
		$items = array();
		$opened = false;
		self::change( function( &$state ) use ( $reason, &$items, &$opened ) {
			if ( (int) $state['bypass_until'] > time() ) return false;
			$items = (array) $state['items'];
			$state['bypass_until'] = time() + self::BYPASS_FOR;
			$state['bypass_reason'] = $reason;
			$state['bypass_items'] = $items;
			$state['auto'] = false;
			$state['items'] = array();
			$state['since'] = 0;
			$state['fails'] = 0;
			$opened = true;
		} );
		if ( $opened ) Digitalisimo_Backups_Notify::pre_update_bypassed( $reason, $items );
		return true;
	}
	private static function item_label( $type, $item ) {
		$item = is_object( $item ) ? get_object_vars( $item ) : (array) $item;
		if ( 'core' === $type ) return 'WordPress ' . (string) ( $item['current'] ?? $item['version'] ?? '' );
		if ( 'plugin' === $type ) return 'Plugin ' . (string) ( $item['plugin'] ?? $item['slug'] ?? '' ) . ( isset( $item['new_version'] ) ? ' ' . $item['new_version'] : '' );
		return 'Tema ' . (string) ( $item['theme'] ?? $item['slug'] ?? '' ) . ( isset( $item['new_version'] ) ? ' ' . $item['new_version'] : '' );
	}

	/* ------------------------------------------------------------------ Actualizaciones manuales */

	/** core, plugin o theme para una actualización; '' para instalar algo nuevo, traducciones u otros. */
	public static function upgrader_type( $upgrader, array $hook_extra = array() ) {
		if ( is_a( $upgrader, 'Core_Upgrader' ) ) return 'core';
		if ( 'install' === (string) ( $hook_extra['action'] ?? '' ) ) return '';
		if ( is_a( $upgrader, 'Plugin_Upgrader' ) ) return 'plugin';
		if ( is_a( $upgrader, 'Theme_Upgrader' ) ) return 'theme';
		return '';
	}
	private static function doing_ajax() { return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX ); }
	private static function is_cli() { return null === self::$cli ? ( defined( 'WP_CLI' ) && WP_CLI ) : (bool) self::$cli; }
	/**
	 * `upgrader_pre_download`: una actualización manual de un tipo con `all` sólo descarga con un respaldo reciente
	 * válido (o con «Actualizar sin respaldo» vigente para esta cuenta). Sin él, en la web encola el respaldo y rechaza
	 * esta actualización con un error claro (la pantalla con JavaScript ya lo esperó antes de enviarla); si el respaldo
	 * previo acaba de fallar, responde con ese motivo sin encolar otro (sólo «Crear el respaldo previo», REST o WP-CLI
	 * lo reintentan). En WP-CLI el respaldo se hace antes del actualizador (cli_before()); aquí sólo se espera si el sitio
	 * no está en mantenimiento, y un fallo del proceso no se repite por cada plugin del lote.
	 */
	public static function gate_download( $reply, $package = '', $upgrader = null, $hook_extra = array() ) {
		if ( false !== $reply || self::auto_context() ) return $reply;
		$hook_extra = is_array( $hook_extra ) ? $hook_extra : array();
		$type = self::upgrader_type( $upgrader, $hook_extra );
		if ( '' === $type || 'all' !== self::settings()[ $type ] ) return $reply;
		// El propio Backups nunca espera: es la vía para recibir el arreglo de un fallo de Backups.
		if ( 'plugin' === $type && self::is_backups( (string) ( $hook_extra['plugin'] ?? '' ) ) ) return $reply;
		try {
			if ( self::skipping() ) return $reply;
			$outcome = self::on_main( function() {
				if ( self::is_cli() ) {
					if ( null !== self::$cli_failure ) return array( 'state' => 'failed', 'message' => self::$cli_failure );
					$fresh = self::recent();
					if ( $fresh ) return array( 'state' => 'ready', 'fresh' => $fresh );
					// Nunca un respaldo completo con el sitio en mantenimiento (los visitantes verían un 503).
					if ( self::maintenance() ) return self::cli_failed( 'El sitio está en modo mantenimiento y el respaldo previo no se hace con los visitantes fuera: créalo antes con wp digitalisimo-backups pre-update run.' );
					$outcome = self::cli_wait();
					return 'ready' === $outcome['state'] ? $outcome : self::cli_failed( (string) ( $outcome['message'] ?? 'El respaldo previo falló.' ) );
				}
				$recent = self::recent_failure();
				return '' !== $recent ? array( 'state' => 'failed', 'message' => $recent ) : self::ensure( self::doing_ajax() ? 'ajax' : 'admin-post' );
			} );
		} catch ( Throwable $e ) {
			$outcome = array( 'state' => 'failed', 'message' => $e->getMessage() );
		}
		if ( 'ready' === $outcome['state'] ) return $reply;
		return new WP_Error( 'digitalisimo_backups_pre_update', self::blocked_message( $outcome ) );
	}
	private static function maintenance() { return defined( 'ABSPATH' ) && file_exists( ABSPATH . '.maintenance' ); }
	private static function cli_failed( $message ) {
		self::$cli_failure = (string) $message;
		return array( 'state' => 'failed', 'message' => self::$cli_failure );
	}
	/** El último respaldo previo falló hace poco y no hay otro en marcha: su motivo ('' si no). */
	private static function recent_failure() {
		$state = self::resolve();
		if ( '' !== (string) $state['job'] || '' === (string) $state['failure'] ) return '';
		return time() - (int) $state['failed_at'] < self::RECENT_FAILURE ? (string) $state['failure'] : '';
	}
	private static function blocked_message( array $outcome ) {
		$skip = self::is_cli() ? ' Para actualizar sin respaldo: wp digitalisimo-backups pre-update skip.' : ' Para actualizar sin respaldo, elige «Actualizar sin respaldo» en Backups.';
		if ( 'running' === $outcome['state'] ) {
			$percent = (int) ( $outcome['view']['percent'] ?? 0 );
			return 'DIGITALÍSIMO Backups está creando un respaldo completo antes de actualizar (' . $percent . ' %). Vuelve a intentar la actualización cuando termine; su avance está en Backups → Herramientas → Trabajos.';
		}
		return 'No se actualizó: el respaldo previo falló. ' . (string) ( $outcome['message'] ?? '' ) . $skip;
	}
	/**
	 * WP-CLI, `before_invoke` de `core update`, `plugin update` y `theme update`: el respaldo previo se hace antes de que
	 * el actualizador ponga el sitio en mantenimiento, y sólo si hay algo que actualizar (aparte del propio Backups). Si
	 * falla, el motivo queda para este proceso y la guarda rechaza cada elemento con él, sin otro respaldo.
	 */
	public static function cli_before( $type ) {
		if ( ! isset( self::TYPES[ $type ] ) || 'all' !== self::settings()[ $type ] ) return;
		try {
			list( $args, $assoc ) = self::cli_args();
			if ( ! empty( $assoc['dry-run'] ) ) return;
			if ( self::skipping() || ! self::pending( $type, $args, $assoc ) ) return;
			self::on_main( function() {
				if ( self::recent() || null !== self::$cli_failure ) return;
				$outcome = self::cli_wait();
				if ( 'ready' !== $outcome['state'] ) self::cli_failed( (string) ( $outcome['message'] ?? 'El respaldo previo falló.' ) );
			} );
		} catch ( Throwable $e ) {
			self::cli_failed( $e->getMessage() );
		}
	}
	/** Argumentos de la orden de WP-CLI en curso (las pruebas los sustituyen con `$cli_args`). */
	private static function cli_args() {
		if ( is_array( self::$cli_args ) ) return array( (array) ( self::$cli_args[0] ?? array() ), (array) ( self::$cli_args[1] ?? array() ) );
		if ( ! class_exists( 'WP_CLI' ) || ! method_exists( 'WP_CLI', 'get_runner' ) ) return array( array(), array() );
		$runner = WP_CLI::get_runner();
		return array( (array) ( $runner->arguments ?? array() ), (array) ( $runner->assoc_args ?? array() ) );
	}
	/**
	 * Hay algo de ese tipo que actualizar (sin contar Backups). El núcleo: una versión o un paquete pedidos en la orden, o
	 * una oferta `upgrade` en `update_core`.
	 */
	private static function pending( $type, array $args = array(), array $assoc = array() ) {
		if ( 'core' === $type ) {
			if ( ! empty( $assoc['version'] ) || count( $args ) > 2 ) return true;
			if ( function_exists( 'wp_version_check' ) && ! empty( $assoc['force'] ) ) wp_version_check();
			$core = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_core' ) : null;
			if ( ! is_object( $core ) ) return true;
			foreach ( (array) ( $core->updates ?? array() ) as $offer ) if ( 'upgrade' === (string) ( ( (array) $offer )['response'] ?? '' ) ) return true;
			return false;
		}
		$check = 'plugin' === $type ? 'wp_update_plugins' : 'wp_update_themes';
		if ( function_exists( $check ) ) $check();
		$transient = function_exists( 'get_site_transient' ) ? get_site_transient( 'plugin' === $type ? 'update_plugins' : 'update_themes' ) : null;
		if ( ! is_object( $transient ) || empty( $transient->response ) ) return ! is_object( $transient );
		foreach ( array_keys( (array) $transient->response ) as $key ) if ( 'theme' === $type || ! self::is_backups( (string) $key ) ) return true;
		return false;
	}
	/** WP-CLI: hace avanzar la cola en este proceso (en el sitio principal) hasta que el respaldo termina o deja de avanzar. */
	private static function cli_wait() {
		return self::on_main( function() {
			$outcome = self::ensure( 'cli' );
			$log = function( $line ) { if ( class_exists( 'WP_CLI' ) ) WP_CLI::log( 'Respaldo antes de actualizar: ' . $line ); };
			if ( 'running' === $outcome['state'] ) $log( 'creando un respaldo completo (trabajo ' . $outcome['job'] . ')…' );
			$shown = -1;
			$seen = '';
			$moved = call_user_func( self::$clock );
			while ( 'running' === $outcome['state'] ) {
				$tick = Digitalisimo_Backups_Jobs::tick();
				$outcome = self::check( $outcome['job'] );
				$view = (array) ( $outcome['view'] ?? array() );
				$percent = (int) ( $view['percent'] ?? 0 );
				if ( 'running' === $outcome['state'] && $percent !== $shown ) { $log( $percent . ' % · ' . (string) ( $view['message'] ?? '' ) ); $shown = $percent; }
				if ( 'ready' === $outcome['state'] ) $log( 'listo (' . (string) $outcome['fresh']['file'] . ').' );
				if ( 'running' !== $outcome['state'] ) break;
				// Sin ningún avance (fase, porcentaje, mensaje o reintento) durante STALL, la espera termina con el motivo.
				$now = call_user_func( self::$clock );
				$signature = json_encode( array( $view['status'] ?? '', $view['phase'] ?? '', $percent, $view['message'] ?? '', $view['retry_label'] ?? '', $view['attempt'] ?? 0 ) );
				if ( $signature !== $seen ) { $seen = $signature; $moved = $now; }
				elseif ( $now - $moved >= self::STALL ) return array( 'state' => 'failed', 'job' => (string) $outcome['job'], 'message' => 'El respaldo previo no avanzó en ' . (int) ( self::STALL / 60 ) . ' minutos (' . ( 'error' === ( $tick['result'] ?? '' ) ? (string) ( $tick['message'] ?? 'la cola no pudo avanzar' ) : 'otra operación ocupa el almacén o espera su reintento' ) . '); sigue en Backups → Herramientas → Trabajos.' );
				if ( 'worked' !== ( $tick['result'] ?? '' ) ) call_user_func( self::$sleep, 1 );
			}
			return $outcome;
		} );
	}
	/** Estado de un respaldo que se espera (el de la pantalla o WP-CLI): ready, running o failed con el motivo. */
	public static function check( $id ) {
		return self::on_main( function() use ( $id ) {
			self::resolve();
			$job = Digitalisimo_Backups_Job_Store::valid_id( (string) $id ) ? Digitalisimo_Backups_Jobs::get( (string) $id ) : null;
			if ( $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return array( 'state' => 'running', 'job' => (string) $id, 'view' => Digitalisimo_Backups_Jobs::view( $job ) );
			$fresh = self::recent();
			if ( $fresh ) return array( 'state' => 'ready', 'fresh' => $fresh );
			if ( ! $job ) return array( 'state' => 'failed', 'message' => 'El trabajo del respaldo previo ya no existe.' );
			if ( 'success' === $job['status'] ) return array( 'state' => 'ready', 'fresh' => array( 'file' => basename( (string) ( $job['spec']['file'] ?? '' ) ), 'date' => gmdate( 'Y-m-d H:i:s', (int) ( $job['finished_at'] ?? time() ) ), 'age_minutes' => 0, 'policy' => (string) ( $job['spec']['policy'] ?? self::POLICY ) ) );
			return array( 'state' => 'failed', 'job' => (string) $id, 'message' => 'cancelled' === $job['status'] ? 'El respaldo previo se canceló.' : (string) ( $job['error']['message'] ?? 'El respaldo previo falló.' ) );
		} );
	}
	/**
	 * «Actualizar sin respaldo»: las manuales de la cuenta que lo pidió (en WP-CLI sin `--user`, la cuenta 0) no esperan
	 * durante unos minutos. Las automáticas siguen retenidas. Queda en el registro con la cuenta y la superficie.
	 */
	public static function skip( $via, $minutes = self::SKIP_MINUTES ) {
		$minutes = max( 1, min( 60, (int) $minutes ) );
		$until = time() + $minutes * 60;
		$user = self::is_cli() ? 'cli' : self::user();
		self::change( function( &$state ) use ( $until, $via, $user ) { $state['skip_until'] = $until; $state['skip_via'] = (string) $via; $state['skip_user'] = $user; } );
		if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: actualizaciones manuales sin respaldo previo durante ' . $minutes . ' min para la cuenta ' . $user . ' (' . $via . ').' );
		return $until;
	}
	/** «Sin respaldo» vigente para quien actualiza: la misma cuenta en la web; en WP-CLI, la consola (con cualquier `--user`). */
	private static function skipping() {
		$state = self::state();
		if ( (int) $state['skip_until'] <= time() ) return false;
		return self::is_cli() ? 'cli' === $state['skip_user'] : ( 'cli' !== $state['skip_user'] && (int) $state['skip_user'] === self::user() );
	}

	/* ------------------------------------------------------------------ Casos de uso (pantalla, REST y WP-CLI) */

	public static function service_status() {
		$allowed = Digitalisimo_Backups::service_check( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		// Sólo lee (revisión H8): ni encola, ni libera, ni lanza el cron; eso lo hacen los ticks, los filtros y ensure().
		try {
			$state = self::state();
			$job = '' !== $state['job'] ? Digitalisimo_Backups_Jobs::get( $state['job'] ) : null;
		} catch ( Throwable $e ) {
			return new WP_Error( 'digitalisimo_backups_storage', $e->getMessage(), array( 'status' => 500 ) );
		}
		$fresh = self::fresh();
		// Un trabajo esperado que ya terminó mal y aún no se anotó cuenta como fallo.
		$ended = $job && Digitalisimo_Backups_Job_Store::is_terminal( $job ) && 'success' !== $job['status'];
		$failure = (string) $state['failure'];
		if ( $ended && '' === $failure ) $failure = 'cancelled' === $job['status'] ? 'El respaldo previo se canceló.' : (string) ( $job['error']['message'] ?? 'El respaldo previo falló.' );
		$skip = (int) $state['skip_until'] > time() ? (int) $state['skip_until'] : 0;
		$bypass = (int) $state['bypass_until'] > time() ? (int) $state['bypass_until'] : 0;
		return array(
			'settings' => self::settings(),
			'fresh' => $fresh,
			'job' => $job ? Digitalisimo_Backups::service_job_view( $job ) : null,
			'retained' => ! empty( $state['auto'] ) ? array_values( (array) $state['items'] ) : array(),
			'retained_since' => ! empty( $state['auto'] ) && $state['since'] ? gmdate( 'c', (int) $state['since'] ) : '',
			'postponed' => ! empty( $state['auto'] ) && ( ( '' === $state['job'] && '' !== $failure ) || $ended ),
			'failure' => $failure,
			'failed_at' => $state['failed_at'] ? gmdate( 'c', (int) $state['failed_at'] ) : '',
			'fails' => (int) $state['fails'],
			'bypass_until' => $bypass ? gmdate( 'c', $bypass ) : '',
			'bypass_reason' => $bypass ? (string) $state['bypass_reason'] : '',
			'bypassed' => $bypass ? array_values( (array) $state['bypass_items'] ) : array(),
			'skip_until' => $skip ? gmdate( 'c', $skip ) : '',
		);
	}
	public static function service_save( array $input ) {
		$allowed = Digitalisimo_Backups::service_check( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$saved = self::store( $input );
		return is_wp_error( $saved ) ? $saved : self::service_status();
	}
	/** Respaldo antes de actualizar, ahora: `ready` si ya hay uno reciente válido; si no, el trabajo (202). */
	public static function service_backup( array $args = array() ) {
		$allowed = Digitalisimo_Backups::service_check( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$outcome = self::ensure( (string) ( $args['via'] ?? 'rest' ) );
		return self::public_outcome( $outcome );
	}
	public static function service_check_job( $id ) {
		$allowed = Digitalisimo_Backups::service_check( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		return self::public_outcome( self::check( (string) $id ) );
	}
	public static function service_skip( array $args = array() ) {
		$allowed = Digitalisimo_Backups::service_check( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$until = self::skip( (string) ( $args['via'] ?? 'rest' ), (int) ( $args['minutes'] ?? self::SKIP_MINUTES ) );
		return array( 'skip_until' => gmdate( 'c', $until ), 'message' => 'Tus actualizaciones manuales no esperarán un respaldo hasta las ' . gmdate( 'H:i', $until ) . ' UTC (sólo las de esta cuenta). Las automáticas siguen esperando uno.' );
	}
	private static function public_outcome( array $outcome ) {
		$out = array( 'state' => (string) $outcome['state'], 'message' => '', 'job' => null, 'fresh' => $outcome['fresh'] ?? null );
		if ( ! empty( $outcome['job'] ) ) {
			$job = Digitalisimo_Backups_Jobs::get( (string) $outcome['job'] );
			if ( $job ) $out['job'] = Digitalisimo_Backups::service_job_view( $job );
		}
		if ( 'ready' === $out['state'] ) $out['message'] = 'Hay un respaldo reciente válido: ' . (string) $outcome['fresh']['file'] . '.';
		elseif ( 'running' === $out['state'] ) $out['message'] = 'Creando un respaldo completo antes de actualizar (' . (int) ( $outcome['view']['percent'] ?? 0 ) . ' %).';
		else $out['message'] = (string) ( $outcome['message'] ?? 'El respaldo previo falló.' );
		return $out;
	}

	/* ------------------------------------------------------------------ Pantalla */

	/** Etiqueta del trabajo en «Trabajos», REST y WP-CLI. */
	public static function origin( $label, $spec = array() ) {
		return '' === (string) $label && self::POLICY === (string) ( ( (array) $spec )['policy'] ?? '' ) ? 'Respaldo antes de actualizar' : $label;
	}
	/** «Ajustes» → «Antes de actualizar»: una línea con lo que está activo; el formulario, plegado en «Cambiar» ($open lo abre). */
	public static function panel( $network, $open = false ) {
		echo '<section class="digitalisimo-backups-card digitalisimo-backups-pre-update"><h2>Antes de actualizar</h2>';
		if ( is_multisite() && ! $network && ! is_main_site() ) { echo '<p>El respaldo antes de actualizar es de toda la red: se configura desde la administración de red o el sitio principal.</p></section>'; return; }
		$status = self::service_status();
		if ( is_wp_error( $status ) ) { echo '<p>' . esc_html( $status->get_error_message() ) . '</p></section>'; return; }
		$settings = $status['settings'];
		echo '<p>' . esc_html( self::summary( $settings ) ) . '</p>' . Digitalisimo_Backups::help_html( 'Antes de actualizar WordPress, un plugin o un tema se crea un respaldo completo, salvo que ya haya uno reciente y válido. Las actualizaciones automáticas esperan a que termine; si falla, se posponen y llega un aviso por correo, y a las ' . (int) ( self::MAX_HOLD / 3600 ) . ' h de espera o tras ' . (int) self::MAX_FAILS . ' fallos seguidos se hacen igual, sin respaldo, con su aviso. Nunca esperan las actualizaciones menores de seguridad de WordPress, las que fuerza WordPress.org ni la del propio Backups. En las manuales, si el respaldo falla, se pregunta si seguir sin él. En una tienda conviene un respaldo reciente de 1 a 6 h.' );
		if ( $status['fresh'] ) echo '<p class="description">Respaldo reciente válido: <strong>' . esc_html( $status['fresh']['file'] ) . '</strong> (' . esc_html( $status['fresh']['date'] ) . ').</p>';
		if ( $status['job'] ) echo '<p class="notice notice-info inline">Respaldo previo en curso: ' . esc_html( (string) $status['job']['percent'] ) . ' % · ' . esc_html( (string) $status['job']['message'] ) . '</p>';
		if ( $status['retained'] ) echo '<p class="notice notice-warning inline">Actualizaciones automáticas ' . ( $status['postponed'] ? 'pospuestas' : 'en espera del respaldo' ) . ': ' . esc_html( implode( ', ', $status['retained'] ) ) . '.</p>';
		if ( '' !== $status['bypass_until'] ) echo '<p class="notice notice-error inline">Hasta ' . esc_html( $status['bypass_until'] ) . ' las actualizaciones automáticas se hacen sin respaldo previo (' . esc_html( $status['bypass_reason'] ) . ').</p>';
		if ( '' !== $status['failure'] ) {
			echo '<div class="notice notice-error inline"><p>Último respaldo previo fallido: ' . esc_html( $status['failure'] ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_SKIP );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SKIP ) . '">';
			if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
			echo '<p><button class="button">Actualizar sin respaldo durante ' . (int) self::SKIP_MINUTES . ' minutos</button> <span class="description">Sólo las actualizaciones manuales; las automáticas siguen esperando un respaldo.</span></p></form></div>';
		}
		if ( '' !== $status['skip_until'] ) echo '<p class="notice notice-warning inline">Actualizaciones manuales sin respaldo previo hasta ' . esc_html( $status['skip_until'] ) . '.</p>';
		echo '<details class="digitalisimo-backups-help digitalisimo-backups-pre-update-form"' . ( $open ? ' open' : '' ) . '><summary>Cambiar</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '"><input type="hidden" name="digitalisimo_backups_return" value="settings:pre-update">';
		if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
		echo '<table class="form-table">';
		foreach ( self::TYPES as $type => $label ) {
			echo '<tr><th><label for="digitalisimo-backups-pre-update-' . esc_attr( $type ) . '">' . esc_html( $label ) . '</label></th><td><select id="digitalisimo-backups-pre-update-' . esc_attr( $type ) . '" name="digitalisimo_backups_pre_update[' . esc_attr( $type ) . ']">';
			foreach ( self::MODES as $mode => $text ) echo '<option value="' . esc_attr( $mode ) . '"' . ( $mode === $settings[ $type ] ? ' selected="selected"' : '' ) . '>' . esc_html( $text ) . '</option>';
			echo '</select></td></tr>';
		}
		echo '<tr><th><label for="digitalisimo-backups-pre-update-age">Respaldo reciente</label></th><td><select id="digitalisimo-backups-pre-update-age" name="digitalisimo_backups_pre_update[max_age]">';
		foreach ( self::AGES as $age ) echo '<option value="' . (int) $age . '"' . ( $age === $settings['max_age'] ? ' selected="selected"' : '' ) . '>' . esc_html( $age < 48 ? $age . ' h' : ( $age / 24 ) . ' días' ) . '</option>';
		echo '</select><p class="description">Un respaldo completo y válido de esta antigüedad o menos evita crear otro antes de actualizar.</p></td></tr>';
		echo '</table><p><button class="button button-primary">Guardar</button></p></form></details></section>';
	}
	/** Una línea: qué tipos se respaldan antes de actualizar y qué antigüedad de respaldo basta. */
	private static function summary( array $settings ) {
		$by = array();
		foreach ( self::TYPES as $type => $label ) $by[ $settings[ $type ] ?? 'off' ][] = array( 'core' => 'WordPress', 'plugin' => 'plugins', 'theme' => 'temas' )[ $type ];
		if ( empty( $by['all'] ) && empty( $by['auto'] ) ) return 'Desactivado: no se respalda antes de actualizar.';
		$parts = array();
		foreach ( array( 'all' => 'manuales y automáticas', 'auto' => 'sólo automáticas' ) as $mode => $text ) if ( ! empty( $by[ $mode ] ) ) $parts[] = self::join( $by[ $mode ] ) . ' (' . $text . ')';
		$age = (int) ( $settings['max_age'] ?? 24 );
		return 'Activo para ' . implode( '; ', $parts ) . '. Basta un respaldo de ' . ( $age < 48 ? $age . ' h' : ( $age / 24 ) . ' días' ) . ' o menos.';
	}
	private static function join( array $items ) { return count( $items ) > 1 ? implode( ', ', array_slice( $items, 0, -1 ) ) . ' y ' . end( $items ) : (string) reset( $items ); }
	/**
	 * La línea de la franja de avisos de la pantalla de Backups: las mismas condiciones que notice() (automáticas
	 * retenidas o pospuestas, o sin respaldo previo por el tope), en una frase corta; null si no hay nada.
	 */
	public static function strip_item() {
		try { $state = self::state(); } catch ( Throwable $e ) { return null; }
		if ( empty( $state['auto'] ) && (int) $state['bypass_until'] <= time() ) return null;
		$status = self::service_status();
		if ( is_wp_error( $status ) ) return null;
		// El detalle (plegado en la franja) dice lo mismo que el aviso del escritorio, notice(): el motivo y qué esperaban.
		if ( $status['bypassed'] || '' !== $status['bypass_reason'] ) {
			$detail = (string) $status['bypass_reason'];
			if ( $status['bypassed'] ) $detail .= ( '' !== $detail ? '; ' : '' ) . 'esperaban: ' . implode( ', ', $status['bypassed'] ) . '.';
			return array( 'class' => 'digitalisimo-backups-pre-update-alert', 'level' => 'error', 'text' => 'Las actualizaciones automáticas se hacen sin respaldo previo hasta ' . $status['bypass_until'] . '.', 'detail' => $detail );
		}
		if ( ! $status['retained'] ) return null;
		return array( 'class' => 'digitalisimo-backups-pre-update-alert', 'level' => $status['postponed'] ? 'error' : 'warning', 'text' => $status['postponed'] ? 'Actualizaciones automáticas pospuestas: el respaldo previo falló.' : 'Actualizaciones automáticas en espera del respaldo previo.', 'detail' => implode( ', ', $status['retained'] ) . ( $status['postponed'] ? '. ' . $status['failure'] : '' ) . '. Se actualizarán igual, sin respaldo, a las ' . (int) ( self::MAX_HOLD / 3600 ) . ' h de la primera espera o tras ' . (int) self::MAX_FAILS . ' fallos seguidos.' );
	}
	public static function save() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_SAVE );
		if ( is_multisite() && ! is_main_site() ) Digitalisimo_Backups::redirect( false, 'El respaldo antes de actualizar se configura desde la administración de red o el sitio principal.' );
		$input = isset( $_POST['digitalisimo_backups_pre_update'] ) && is_array( $_POST['digitalisimo_backups_pre_update'] ) ? wp_unslash( $_POST['digitalisimo_backups_pre_update'] ) : array();
		$saved = self::store( array_intersect_key( (array) $input, self::defaults() ) );
		if ( is_wp_error( $saved ) ) Digitalisimo_Backups::redirect( false, $saved->get_error_message() );
		Digitalisimo_Backups::redirect( true, 'Respaldo antes de actualizar guardado.' );
	}
	public static function admin_skip() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_SKIP );
		if ( is_multisite() && ! is_main_site() ) Digitalisimo_Backups::redirect( false, 'El respaldo antes de actualizar se configura desde la administración de red o el sitio principal.' );
		$result = self::service_skip( array( 'network' => is_multisite(), 'via' => 'admin-post' ) );
		Digitalisimo_Backups::redirect( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : $result['message'] );
	}

	/**
	 * Aviso fijo en el escritorio (sitio y red) para quien gestiona los respaldos mientras haya automáticas retenidas o
	 * pospuestas, o se hayan actualizado sin respaldo por el tope: no depende del correo.
	 */
	public static function notice() {
		if ( ! Digitalisimo_Backups::can_manage_backups() || Digitalisimo_Backups::is_backups_screen() ) return;
		// Barato en cada pantalla: sin nada retenido ni ventana sin respaldo, no se lee el historial ni el ZIP.
		try { $state = self::state(); } catch ( Throwable $e ) { return; }
		if ( empty( $state['auto'] ) && (int) $state['bypass_until'] <= time() ) return;
		if ( is_multisite() && ! ( function_exists( 'is_network_admin' ) && is_network_admin() ) && ! is_main_site() ) return;
		try {
			$status = self::service_status();
		} catch ( Throwable $e ) {
			return;
		}
		if ( is_wp_error( $status ) ) return;
		$link = '<a href="' . esc_url( is_multisite() ? network_admin_url( 'admin.php?page=digitalisimo-network-backups&tab=settings&open=pre-update' ) : admin_url( 'admin.php?page=digitalisimo-backups&tab=settings&open=pre-update' ) ) . '">Backups → Ajustes → Antes de actualizar</a>';
		if ( $status['bypassed'] || '' !== $status['bypass_reason'] ) {
			echo '<div class="notice notice-error digitalisimo-backups-pre-update-alert"><p><strong>Las actualizaciones automáticas se hacen sin respaldo previo hasta ' . esc_html( $status['bypass_until'] ) . '</strong> (' . esc_html( $status['bypass_reason'] ) . ')' . ( $status['bypassed'] ? '; esperaban: ' . esc_html( implode( ', ', $status['bypassed'] ) ) : '' ) . '. Revisa ' . $link . '.</p></div>';
			return;
		}
		if ( ! $status['retained'] ) return;
		$text = $status['postponed'] ? 'Actualizaciones automáticas pospuestas: el respaldo previo falló (' . $status['failure'] . ')' : 'Actualizaciones automáticas en espera del respaldo previo';
		echo '<div class="notice notice-' . ( $status['postponed'] ? 'error' : 'warning' ) . ' digitalisimo-backups-pre-update-alert"><p><strong>' . esc_html( $text ) . '.</strong> ' . esc_html( implode( ', ', $status['retained'] ) ) . '. Se actualizarán igual, sin respaldo, a las ' . (int) ( self::MAX_HOLD / 3600 ) . ' h de la primera espera o tras ' . (int) self::MAX_FAILS . ' fallos seguidos. ' . $link . '.</p></div>';
	}
	/** Pantallas de actualización (las de la red también): el script que crea el respaldo antes de enviar la actualización. */
	public static function enqueue( $hook = '' ) {
		if ( ! in_array( (string) $hook, array( 'update-core.php', 'plugins.php', 'themes.php' ), true ) || ! Digitalisimo_Backups::can_manage_backups() ) return;
		if ( is_multisite() && ! is_network_admin() ) return;
		$config = self::script_config();
		if ( ! $config ) return;
		wp_enqueue_script( 'digitalisimo-backups-pre-update', plugins_url( '../assets/pre-update.js', __FILE__ ), array( 'jquery', 'updates' ), DIGITALISIMO_BACKUPS_VERSION, true );
		wp_add_inline_script( 'digitalisimo-backups-pre-update', 'window.digitalisimoBackupsPreUpdate=' . wp_json_encode( $config ) . ';', 'before' );
	}
	/** Configuración del script: sólo los tipos con «Manuales y automáticas», sin secretos ni rutas. */
	public static function script_config() {
		$settings = self::settings();
		$types = array();
		foreach ( array_keys( self::TYPES ) as $type ) if ( 'all' === $settings[ $type ] ) $types[ $type ] = true;
		if ( ! $types ) return array();
		return array(
			'url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE ),
			'start' => self::AJAX_START,
			'status' => self::AJAX_STATUS,
			'skip' => self::AJAX_SKIP,
			'types' => $types,
			'text' => array(
				'running' => 'Creando un respaldo completo antes de actualizar…',
				'ready' => 'Respaldo listo: actualizando.',
				'ask' => "El respaldo antes de actualizar falló:\n%s\n\n¿Actualizar de todos modos, sin respaldo?",
				'declined' => 'No se actualizó: el respaldo previo falló. Revisa Backups → Herramientas → Trabajos.',
			),
		);
	}
	private static function ajax_guard() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_send_json_error( array( 'message' => 'No autorizado para gestionar respaldos.' ), 403 );
		check_ajax_referer( self::NONCE, 'nonce' );
	}
	public static function ajax_start() {
		self::ajax_guard();
		self::ajax_reply( self::service_backup( array( 'network' => is_multisite(), 'via' => 'ajax' ) ) );
	}
	/** Un tick de la cola (dentro del presupuesto de la petición) y el estado del respaldo que espera la pantalla. */
	public static function ajax_status() {
		self::ajax_guard();
		$id = sanitize_text_field( wp_unslash( $_POST['job'] ?? '' ) );
		if ( ! Digitalisimo_Backups_Job_Store::valid_id( $id ) ) wp_send_json_error( array( 'message' => 'El identificador del trabajo no es válido.' ), 400 );
		$current = Digitalisimo_Backups_Jobs::get( $id );
		if ( $current && ! Digitalisimo_Backups_Job_Store::is_terminal( $current ) ) Digitalisimo_Backups_Jobs::tick();
		self::ajax_reply( self::service_check_job( $id ) );
	}
	public static function ajax_skip() {
		self::ajax_guard();
		self::ajax_reply( self::service_skip( array( 'network' => is_multisite(), 'via' => 'ajax' ) ) );
	}
	private static function ajax_reply( $result ) {
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) ( $result->get_error_data()['status'] ?? 400 ) );
		wp_send_json_success( $result );
	}
}
