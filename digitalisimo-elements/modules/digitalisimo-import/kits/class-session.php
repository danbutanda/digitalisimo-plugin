<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Sesión de K (§5.3): la opción de detalle de cada importación, los temporales, el token de un solo uso, la regla
 * del modo (D1), la procedencia (D7) y las acciones de la pantalla «Kits» (§12.2).
 *
 * El estado vive en las sesiones nativas (`elementor_import_sessions` y `elementor_revert_sessions`); K sólo
 * guarda su detalle en `digitalisimo_elements_kit_session_<sesión>`, sin autoload, y su puntero en la metadata del
 * runner `digitalisimo` (`{ v, mode, kit, report_option }`).
 *
 * Temporales: `uploads/digitalisimo-elements/tmp/<64 hex>/`, con `index.php` y `.htaccess` que niega el acceso. La
 * sesión guarda `staged_rel` relativa a la carpeta de subidas (un sitio clonado resuelve la suya) y todo borrado
 * comprueba con `realpath` que queda dentro de `tmp/`.
 */
final class Session {
	const VERSION      = 1;
	const KEEP         = 10;
	const REPORT_BYTES = 65536;
	const LIST_MAX     = 200;
	const TOKEN_TTL    = 3600;
	const TOKEN_PREFIX = 'digitalisimo_elements_kit_token_';
	const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

	const STATUS_UPLOADED = 'uploaded';
	const STATUS_IMPORTED = 'imported';
	const STATUS_REVERTED = 'reverted';

	/** Las tres entradas que crea WordPress al instalarse no hacen «no vacío» un sitio (D1). */
	const WORDPRESS_DEFAULTS = array( 'hello-world', 'sample-page', 'privacy-policy' );

	/** @var array[] Sesiones leídas en esta petición. */
	private static $cache = array();

	// ── Opción de detalle ───────────────────────────────────────────────────────────────────────────────────────

	public static function option( string $session ): string {
		return Module::OPTION_SESSION_PREFIX . $session;
	}

	public static function valid_id( string $session ): bool {
		return 1 === preg_match( Import_Context::SESSION_PATTERN, $session );
	}

	/** Detalle de una sesión de K, o null. */
	public static function get( string $session ): ?array {
		if ( ! self::valid_id( $session ) ) {
			return null;
		}
		if ( ! array_key_exists( $session, self::$cache ) ) {
			$record                   = get_option( self::option( $session ), null );
			self::$cache[ $session ] = is_array( $record ) ? $record : null;
		}
		return self::$cache[ $session ];
	}

	/** Guarda el detalle (sin autoload), con el informe recortado a 64 KB y sus listas a 200 entradas. */
	public static function save( string $session, array $record ): void {
		if ( ! self::valid_id( $session ) ) {
			return;
		}
		$record['v'] = self::VERSION;
		if ( isset( $record['report'] ) && is_array( $record['report'] ) ) {
			$record['report'] = self::fit_report( $record['report'] );
		}
		self::$cache[ $session ] = $record;
		$name                    = self::option( $session );
		if ( false === get_option( $name, false ) ) {
			add_option( $name, $record, '', false );
		} else {
			update_option( $name, $record, false );
		}
	}

	/** Cambia claves de primer nivel del detalle. */
	public static function update( string $session, array $changes ): ?array {
		$record = self::get( $session );
		if ( null === $record ) {
			return null;
		}
		$record = array_replace( $record, $changes );
		self::save( $session, $record );
		return $record;
	}

	public static function forget( string $session ): void {
		unset( self::$cache[ $session ] );
	}

	/** Sesiones nativas de importación, por ID. */
	public static function native_sessions(): array {
		$sessions = get_option( 'elementor_import_sessions', array() );
		return is_array( $sessions ) ? $sessions : array();
	}

	/** ID de la última sesión nativa (la única que «Revertir» deshace), o ''. */
	public static function last_session_id(): string {
		$last = '';
		$time = -1;
		foreach ( self::native_sessions() as $id => $session ) {
			$start = is_array( $session ) && isset( $session['start_timestamp'] ) ? (int) $session['start_timestamp'] : 0;
			if ( $start >= $time ) {
				$time = $start;
				$last = (string) $id;
			}
		}
		return $last;
	}

	/**
	 * Sesiones para la pantalla «Kits»: las nativas unidas a su detalle, más recientes primero. Las revertidas
	 * salen de `elementor_revert_sessions` con `status = reverted` y sin acciones.
	 */
	public static function all(): array {
		$list = array();
		foreach ( self::native_sessions() as $id => $native ) {
			$list[ (string) $id ] = array(
				'session' => (string) $id,
				'native'  => is_array( $native ) ? array_diff_key( $native, array( 'instance_data' => true ) ) : array(),
				'detail'  => self::get( (string) $id ),
				'status'  => self::STATUS_IMPORTED,
				'last'    => false,
			);
		}
		$last = self::last_session_id();
		if ( isset( $list[ $last ] ) ) {
			$list[ $last ]['last'] = true;
		}
		$reverted = get_option( 'elementor_revert_sessions', array() );
		foreach ( is_array( $reverted ) ? $reverted : array() as $native ) {
			$id = is_array( $native ) && isset( $native['session_id'] ) ? (string) $native['session_id'] : '';
			if ( '' !== $id && ! isset( $list[ $id ] ) ) {
				$list[ $id ] = array(
					'session' => $id,
					'native'  => $native,
					'detail'  => self::get( $id ),
					'status'  => self::STATUS_REVERTED,
					'last'    => false,
				);
			}
		}
		uasort(
			$list,
			function ( $a, $b ) {
				$ta = (int) ( $a['native']['start_timestamp'] ?? $a['native']['import_timestamp'] ?? 0 );
				$tb = (int) ( $b['native']['start_timestamp'] ?? $b['native']['import_timestamp'] ?? 0 );
				return $tb <=> $ta;
			}
		);
		return $list;
	}

	/** ¿Se puede actuar sobre la sesión? Existe en este sitio, es de K y no se revirtió. */
	public static function actionable( string $session ): bool {
		$record = self::get( $session );
		if ( null === $record || self::STATUS_REVERTED === ( $record['status'] ?? '' ) ) {
			return false;
		}
		return isset( self::native_sessions()[ $session ] );
	}

	/** Recorta el informe a `REPORT_BYTES` serializado y sus listas a `LIST_MAX`. */
	private static function fit_report( array $report ): array {
		$report += array(
			'entries' => array(),
			'counts'  => array(),
			'dropped' => 0,
		);
		if ( count( $report['entries'] ) > self::LIST_MAX * 10 ) {
			$report['dropped'] += count( $report['entries'] ) - self::LIST_MAX * 10;
			$report['entries']  = array_slice( $report['entries'], 0, self::LIST_MAX * 10 );
		}
		while ( $report['entries'] && strlen( serialize( $report ) ) > self::REPORT_BYTES ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- sólo mide.
			array_pop( $report['entries'] );
			++$report['dropped'];
		}
		return $report;
	}

	/** Une dos informes: entradas en orden, contadores sumados. */
	public static function merge_report( array $base, array $more ): array {
		$base += array(
			'entries' => array(),
			'counts'  => array(),
			'dropped' => 0,
		);
		foreach ( isset( $more['entries'] ) && is_array( $more['entries'] ) ? $more['entries'] : array() as $entry ) {
			$base['entries'][] = $entry;
		}
		foreach ( isset( $more['counts'] ) && is_array( $more['counts'] ) ? $more['counts'] : array() as $code => $n ) {
			$base['counts'][ $code ] = ( $base['counts'][ $code ] ?? 0 ) + (int) $n;
		}
		$base['dropped'] += isset( $more['dropped'] ) ? (int) $more['dropped'] : 0;
		return $base;
	}

	/** Borra los detalles más antiguos y sus temporales: se conservan las 10 últimas sesiones. */
	public static function prune(): void {
		global $wpdb;
		$like  = $wpdb->esc_like( Module::OPTION_SESSION_PREFIX ) . '%';
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( count( $names ) <= self::KEEP ) {
			return;
		}
		$times = array();
		foreach ( $names as $name ) {
			$record         = get_option( $name );
			$times[ $name ] = is_array( $record ) && isset( $record['time'] ) ? (int) $record['time'] : 0;
		}
		arsort( $times );
		foreach ( array_slice( array_keys( $times ), self::KEEP ) as $name ) {
			$record = get_option( $name );
			if ( is_array( $record ) && ! empty( $record['token'] ) ) {
				self::delete_tmp( (string) $record['token'] );
			}
			delete_option( $name );
			unset( self::$cache[ substr( $name, strlen( Module::OPTION_SESSION_PREFIX ) ) ] );
		}
	}

	// ── Temporales ──────────────────────────────────────────────────────────────────────────────────────────────

	/** Carpeta real de temporales, con barra final; la crea protegida. '' si no hay carpeta de subidas. */
	public static function tmp_root(): string {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$root = rtrim( (string) $uploads['basedir'], '/\\' ) . '/' . Module::TMP_DIR;
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return '';
		}
		self::protect( $root );
		$real = realpath( $root );
		return false === $real ? '' : rtrim( str_replace( '\\', '/', $real ), '/' ) . '/';
	}

	/** `index.php` vacío y `.htaccess` que niega el acceso. */
	private static function protect( string $dir ): void {
		if ( ! is_file( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Crea `tmp/<64 hex>/` con sus protecciones.
	 *
	 * @return array{token: string, dir: string}
	 *
	 * @throws \RuntimeException Si no se puede crear.
	 */
	public static function create_tmp(): array {
		$root = self::tmp_root();
		if ( '' === $root ) {
			throw new \RuntimeException( 'No se pudo preparar la carpeta temporal de kits en la carpeta de subidas.' );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$dir   = $root . $token;
		if ( ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( 'No se pudo preparar la carpeta temporal de kits en la carpeta de subidas.' );
		}
		self::protect( $dir );
		return array(
			'token' => $token,
			'dir'   => (string) self::tmp_dir( $token ),
		);
	}

	/** Carpeta real de un token, con barra final, o null si no existe o sale de `tmp/`. */
	public static function tmp_dir( string $token ): ?string {
		if ( 1 !== preg_match( self::TOKEN_PATTERN, $token ) ) {
			return null;
		}
		$root = self::tmp_root();
		if ( '' === $root ) {
			return null;
		}
		$real = realpath( $root . $token );
		if ( false === $real || ! is_dir( $real ) ) {
			return null;
		}
		$real = rtrim( str_replace( '\\', '/', $real ), '/' ) . '/';
		return 0 === strpos( $real, $root ) && $real !== $root ? $real : null;
	}

	/** Carpeta preparada real de una sesión (desde `staged_rel`), o null. */
	public static function staged_dir( array $record ): ?string {
		$relative = isset( $record['staged_rel'] ) && is_string( $record['staged_rel'] ) ? $record['staged_rel'] : '';
		if ( ! preg_match( '#^' . preg_quote( Module::TMP_DIR, '#' ) . '/[0-9a-f]{64}/staged$#', $relative ) ) {
			return null;
		}
		$token = substr( $relative, strlen( Module::TMP_DIR ) + 1, 64 );
		$dir   = self::tmp_dir( $token );
		if ( null === $dir ) {
			return null;
		}
		$real = realpath( $dir . 'staged' );
		return false !== $real && is_dir( $real ) ? rtrim( str_replace( '\\', '/', $real ), '/' ) . '/' : null;
	}

	/** Borra la carpeta de un token. Nunca sale de `tmp/`. */
	public static function delete_tmp( string $token ): bool {
		$dir  = self::tmp_dir( $token );
		$root = self::tmp_root();
		if ( null === $dir || '' === $root ) {
			return false;
		}
		self::remove( $dir, $root );
		$gone = ! is_dir( $dir );
		self::tidy_root( $root );
		return $gone;
	}

	/**
	 * Sin temporales pendientes, `tmp/` (y `uploads/digitalisimo-elements/` si queda vacía) no se queda en el sitio:
	 * una importación terminada o revertida no deja carpetas nuevas en `uploads` (A10).
	 */
	private static function tidy_root( string $root ): void {
		$items = array_values( array_diff( (array) scandir( $root ), array( '.', '..', 'index.php', '.htaccess' ) ) );
		if ( $items ) {
			return;
		}
		foreach ( array( 'index.php', '.htaccess' ) as $file ) {
			if ( is_file( $root . $file ) && ! is_link( $root . $file ) ) {
				@unlink( $root . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		@rmdir( rtrim( $root, '/' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		$parent = dirname( rtrim( $root, '/' ) );
		if ( 'digitalisimo-elements' === basename( $parent ) && is_dir( $parent ) && ! array_diff( (array) scandir( $parent ), array( '.', '..' ) ) ) {
			@rmdir( $parent ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}

	/**
	 * Borra la carpeta donde el núcleo extrajo el kit (`uploads/elementor/tmp/<…>/`), sin `.htaccess`, cuando la
	 * importación ya terminó. Sólo si queda dentro de `uploads/elementor/tmp/` y no es esa carpeta.
	 *
	 * @param string $relative Ruta relativa a `uploads` que anotó `Finish::import()`.
	 */
	public static function delete_extracted( string $relative ): bool {
		$uploads = wp_upload_dir( null, false );
		if ( '' === $relative || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || false !== strpos( $relative, '..' ) ) {
			return false;
		}
		$base = rtrim( str_replace( '\\', '/', (string) $uploads['basedir'] ), '/' );
		$root = realpath( $base . '/elementor/tmp' );
		$real = realpath( $base . '/' . ltrim( $relative, '/' ) );
		if ( false === $root || false === $real || ! is_dir( $real ) ) {
			return false;
		}
		$root = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';
		$real = rtrim( str_replace( '\\', '/', $real ), '/' ) . '/';
		if ( 0 !== strpos( $real, $root ) || $real === $root ) {
			return false;
		}
		self::remove( $real, $root );
		return ! is_dir( $real );
	}

	/** Borrado recursivo acotado: cada ruta se resuelve y debe quedar dentro de `$root` (sin seguir enlaces). */
	private static function remove( string $path, string $root ): void {
		$real = realpath( $path );
		if ( false === $real ) {
			return;
		}
		$real = str_replace( '\\', '/', $real );
		if ( 0 !== strpos( rtrim( $real, '/' ) . '/', $root ) || rtrim( $real, '/' ) . '/' === $root ) {
			return;
		}
		if ( is_link( $path ) || ! is_dir( $real ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return;
		}
		foreach ( (array) scandir( $real ) as $item ) {
			if ( '.' === $item || '..' === $item || '' === $item ) {
				continue;
			}
			self::remove( $real . '/' . $item, $root );
		}
		@rmdir( $real ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/** Evento único de limpieza: borra los temporales de la sesión (se programa con el ID, nunca con una ruta). */
	public static function cleanup( $session = '' ): void {
		$session = is_string( $session ) ? $session : '';
		$record  = self::get( $session );
		if ( null !== $record && ! empty( $record['token'] ) ) {
			self::delete_tmp( (string) $record['token'] );
		}
	}

	public static function schedule_cleanup( string $session ): void {
		if ( self::valid_id( $session ) && ! wp_next_scheduled( Module::CLEANUP_EVENT, array( $session ) ) ) {
			wp_schedule_single_event( time() + DAY_IN_SECONDS, Module::CLEANUP_EVENT, array( $session ) );
		}
	}

	// ── Preparación de un kit ───────────────────────────────────────────────────────────────────────────────────

	/**
	 * Guardián, normalizador y preparación (§7.2): el ZIP limpio y los medios del kit en `tmp/<token>/`.
	 *
	 * @param string $zip  ZIP subido.
	 * @param array  $args {
	 *     @type string $mode    Modo pedido: `add` (por defecto) o `fresh`.
	 *     @type string $surface `app`, `screen`, `library` o `cli`.
	 *     @type string $target  Dónde escribir el ZIP limpio; por defecto `tmp/<token>/kit/kit.zip`.
	 * }
	 *
	 * @return array Registro pendiente: `token`, `user`, `requested`, `mode`, `error`, `kit_rel`, `staged_rel`,
	 *               `stage`, `origin`, `format`, `requests`, `analysis` y `time`.
	 *
	 * @throws \RuntimeException Con el motivo, si el kit no es admisible.
	 */
	public static function prepare( string $zip, array $args = array() ): array {
		$inspection = Zip_Guard::inspect( $zip );
		$tmp        = null;
		try {
			$normalized = Normalizer::run( $inspection );
			$tmp        = self::create_tmp();
			$staged     = $tmp['dir'] . 'staged';
			if ( ! wp_mkdir_p( $staged ) ) {
				throw new \RuntimeException( 'No se pudo preparar la carpeta temporal de kits.' );
			}
			$stage   = $normalized->stage( $staged );
			$kit_rel = '';
			if ( isset( $args['target'] ) && is_string( $args['target'] ) && '' !== $args['target'] ) {
				$target = $args['target'];
			} else {
				if ( ! wp_mkdir_p( $tmp['dir'] . 'kit' ) ) {
					throw new \RuntimeException( 'No se pudo preparar la carpeta temporal de kits.' );
				}
				$target  = $tmp['dir'] . 'kit/kit.zip';
				$kit_rel = Module::TMP_DIR . '/' . $tmp['token'] . '/kit/kit.zip';
			}
			$normalized->write_zip( $target );
		} catch ( \RuntimeException $error ) {
			if ( null !== $tmp ) {
				self::delete_tmp( $tmp['token'] );
			}
			throw $error;
		} finally {
			$inspection->cleanup();
		}

		$requested = isset( $args['mode'] ) && Module::MODE_FRESH === $args['mode'] ? Module::MODE_FRESH : Module::MODE_ADD;
		$decision  = self::decide_mode( $requested, $normalized->format() );
		return array(
			'token'      => $tmp['token'],
			'user'       => get_current_user_id(),
			'surface'    => isset( $args['surface'] ) && is_string( $args['surface'] ) ? $args['surface'] : 'app',
			'requested'  => $requested,
			'mode'       => $decision['mode'],
			'error'      => $decision['error'],
			'kit_rel'    => $kit_rel,
			'staged_rel' => Module::TMP_DIR . '/' . $tmp['token'] . '/staged',
			'stage'      => $stage,
			'time'       => time(),
		) + $normalized->record();
	}

	/** Guarda el registro pendiente con su token, para un solo uso y una hora, del usuario que lo preparó. */
	public static function remember_token( array $pending ): void {
		if ( isset( $pending['token'] ) && 1 === preg_match( self::TOKEN_PATTERN, (string) $pending['token'] ) ) {
			$pending['used'] = false;
			set_transient( self::TOKEN_PREFIX . $pending['token'], $pending, self::TOKEN_TTL );
		}
	}

	/** Toma un token: del usuario actual, vigente y sin usar; queda usado (CV-16). */
	public static function take_token( string $token ): ?array {
		if ( 1 !== preg_match( self::TOKEN_PATTERN, $token ) ) {
			return null;
		}
		$pending = get_transient( self::TOKEN_PREFIX . $token );
		if ( ! is_array( $pending ) || ! empty( $pending['used'] ) || (int) ( $pending['user'] ?? 0 ) !== get_current_user_id() || get_current_user_id() <= 0 ) {
			return null;
		}
		$pending['used'] = true;
		set_transient( self::TOKEN_PREFIX . $token, $pending, self::TOKEN_TTL );
		return $pending;
	}

	/**
	 * `elementor/import/kit/result/digitalisimo`: la App pide con `kit_id = <token>` el kit preparado por la pantalla
	 * «Kits» o por la biblioteca (V-8). Sólo responde al mismo usuario, con un token vigente y sin usar.
	 *
	 * @param mixed $result Lo que trae el filtro (`kit_id`, `source`).
	 *
	 * @return array|\WP_Error
	 */
	public static function kit_result( $result ) {
		$token = is_array( $result ) && isset( $result['kit_id'] ) && is_string( $result['kit_id'] ) ? $result['kit_id'] : '';
		if ( ! current_user_can( Module::CAPABILITY ) ) {
			return new \WP_Error( 'digitalisimo_kit_forbidden', 'No tienes permiso para importar kits.' );
		}
		$pending = self::take_token( $token );
		$dir     = null === $pending ? null : self::tmp_dir( $token );
		$file    = null === $dir ? '' : $dir . 'kit/kit.zip';
		if ( null === $pending || '' === $file || ! is_file( $file ) ) {
			return new \WP_Error( 'digitalisimo_kit_token', 'El kit preparado caducó o ya se usó. Vuelve a subirlo desde la pantalla «Kits».' );
		}
		Context::set_pending( $pending );
		return array(
			'file_name' => $file,
			'referrer'  => 'local',
		);
	}

	/**
	 * Crea el detalle de la sesión del núcleo a partir del registro pendiente (§7.2, regla 5).
	 */
	public static function bind( string $session, array $pending ): array {
		$record = array(
			'v'          => self::VERSION,
			'status'     => self::STATUS_UPLOADED,
			'mode'       => isset( $pending['mode'] ) && Module::MODE_FRESH === $pending['mode'] ? Module::MODE_FRESH : Module::MODE_ADD,
			'requested'  => isset( $pending['requested'] ) ? (string) $pending['requested'] : Module::MODE_ADD,
			'user'       => get_current_user_id(),
			'time'       => time(),
			'surface'    => isset( $pending['surface'] ) ? (string) $pending['surface'] : 'app',
			'token'      => isset( $pending['token'] ) ? (string) $pending['token'] : '',
			'staged_rel' => isset( $pending['staged_rel'] ) ? (string) $pending['staged_rel'] : '',
			'format'     => isset( $pending['format'] ) ? (string) $pending['format'] : Normalizer::FORMAT_KIT3,
			'origin'     => isset( $pending['origin'] ) && is_array( $pending['origin'] ) ? $pending['origin'] : array(),
			'requests'   => isset( $pending['requests'] ) && is_array( $pending['requests'] ) ? $pending['requests'] : array(),
			'analysis'   => isset( $pending['analysis'] ) && is_array( $pending['analysis'] ) ? $pending['analysis'] : array(),
			'stage'      => isset( $pending['stage'] ) && is_array( $pending['stage'] ) ? $pending['stage'] : array(),
			'report'     => array(
				'entries' => array(),
				'counts'  => array(),
				'dropped' => 0,
			),
		);
		self::save( $session, $record );
		self::prune();
		return $record;
	}

	// ── Modo (D1) ───────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * ¿Está vacío el sitio? Sin entradas de tipos públicos en ningún estado (salvo las tres de WordPress con ID ≤ 3),
	 * sin plantillas salvo el kit, con `show_on_front = posts` y sin menús.
	 *
	 * @return array{empty: bool, reasons: string[]}
	 */
	public static function site_is_empty(): array {
		global $wpdb;
		$reasons = array();
		$types   = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'elementor_library' ) ) );
		if ( $types ) {
			$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status <> 'auto-draft' LIMIT 10", $types ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $rows as $row ) {
				if ( (int) $row->ID <= 3 && in_array( (string) $row->post_name, self::WORDPRESS_DEFAULTS, true ) ) {
					continue;
				}
				$reasons['content'] = 'El sitio ya tiene contenido.';
				break;
			}
		}
		$templates = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_template_type' WHERE p.post_type = 'elementor_library' AND p.post_status NOT IN ('auto-draft','trash') AND ( m.meta_value IS NULL OR m.meta_value <> 'kit' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $templates > 0 ) {
			$reasons['templates'] = 'El sitio ya tiene plantillas.';
		}
		if ( 'posts' !== get_option( 'show_on_front', 'posts' ) ) {
			$reasons['front'] = 'El sitio ya tiene una portada estática.';
		}
		if ( function_exists( 'wp_get_nav_menus' ) && wp_get_nav_menus() ) {
			$reasons['menus'] = 'El sitio ya tiene menús.';
		}
		return array(
			'empty'   => ! $reasons,
			'reasons' => array_values( $reasons ),
		);
	}

	/**
	 * Modo de una importación (D1): «agregar» siempre por defecto; «nuevo» sólo pedido y en un sitio vacío. Un kit
	 * convertido (Envato) entra siempre en «agregar».
	 *
	 * @return array{mode: string, error: string}
	 */
	public static function decide_mode( string $requested, string $format = Normalizer::FORMAT_KIT3 ): array {
		if ( Module::MODE_FRESH !== $requested ) {
			return array(
				'mode'  => Module::MODE_ADD,
				'error' => '',
			);
		}
		if ( Normalizer::FORMAT_ENVATO === $format ) {
			return array(
				'mode'  => Module::MODE_ADD,
				'error' => 'Un Template Kit de Envato se importa siempre en «sólo agregar».',
			);
		}
		$empty = self::site_is_empty();
		if ( ! $empty['empty'] ) {
			return array(
				'mode'  => Module::MODE_ADD,
				'error' => 'Sólo se importa como sitio nuevo en un sitio vacío. ' . implode( ' ', $empty['reasons'] ),
			);
		}
		return array(
			'mode'  => Module::MODE_FRESH,
			'error' => '',
		);
	}

	// ── Procedencia (D7) ────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Huellas y procedencia (D7): la fuente única es la parte `origin` (`Parts\Origin`). Estos métodos sólo la
	 * exponen con el nombre que usan los runners de K y los derivados de PRO.
	 */
	public static function document_hash( array $data ): string {
		return Parts\Origin::document_hash( $data );
	}

	/** Huella de una entrada del WXR (`Parts\Origin::wxr_hash()`). */
	public static function wxr_hash( array $post ): string {
		return Parts\Origin::wxr_hash( $post );
	}

	/** Huella de la copia local (`Parts\Origin::local_hash()`): publicarla no la cambia. */
	public static function local_hash( int $post_id ): string {
		return Parts\Origin::local_hash( $post_id );
	}

	/**
	 * Anota la procedencia de algo que creó la sesión (`_digitalisimo_import_origin`, `Parts\Origin::record()`).
	 * `local` se calcula en `Finish::after_save()`, cuando ya está guardado.
	 *
	 * @param int        $post_id ID nuevo.
	 * @param string     $type    Tipo con que se busca la procedencia.
	 * @param int|string $old_id  ID de origen.
	 * @param string     $hash    Huella de origen.
	 * @param string     $status  `new`, `update` o `conflict`.
	 */
	public static function mark_origin( int $post_id, string $type, $old_id, string $hash, string $status = 'new' ): void {
		$origin = self::origin_record( $type, $old_id, $hash, $status );
		if ( $post_id > 0 && null !== $origin ) {
			update_post_meta( $post_id, Module::META_ORIGIN, $origin );
		}
	}

	/** Procedencia de un término creado por la sesión. */
	public static function mark_term_origin( int $term_id, string $taxonomy, $old_id ): void {
		$origin = self::origin_record( 'term:' . $taxonomy, $old_id, '', 'new' );
		if ( $term_id > 0 && null !== $origin ) {
			update_term_meta( $term_id, Module::META_ORIGIN, $origin );
		}
	}

	/** Valor de `_digitalisimo_import_origin` de la sesión activa, o null sin sesión ni huella del kit. */
	private static function origin_record( string $type, $old_id, string $hash, string $status ): ?array {
		$record = Context::record();
		$kit    = null === $record ? '' : (string) ( $record['origin']['kit'] ?? '' );
		if ( '' === $kit || ( ! is_int( $old_id ) && ! is_string( $old_id ) ) ) {
			return null;
		}
		return Parts\Origin::record( $kit, $type, $old_id, $hash, Context::session(), $status, Module::FORMAT );
	}

	/** Recalcula la huella local de un objeto creado por la sesión. */
	public static function refresh_local( int $post_id ): void {
		Parts\Origin::refresh_local( $post_id );
	}

	/**
	 * Busca, en cualquier estado salvo la papelera, lo que ya importó el mismo kit con ese ID de origen (D7):
	 * `Parts\Origin::lookup()` con la huella del kit de la sesión activa.
	 *
	 * @return array{status: string, id: int, previous: int} `reuse` (mismo origen y copia sin editar: `id`),
	 *         `update` (cambió el origen), `conflict` (se editó la copia local) o `new`. `previous` es lo encontrado.
	 */
	public static function lookup( string $type, $old_id, string $hash ): array {
		$record = Context::record();
		$kit    = null === $record ? '' : (string) ( $record['origin']['kit'] ?? '' );
		if ( '' === $kit || ( ! is_int( $old_id ) && ! is_string( $old_id ) ) || '' === (string) $old_id ) {
			return array(
				'status'   => 'new',
				'id'       => 0,
				'previous' => 0,
			);
		}
		$found = Parts\Origin::lookup( $kit, $type, $old_id, $hash );
		return array(
			'status'   => (string) $found['status'],
			'id'       => (int) $found['id'],
			'previous' => (int) $found['previous'],
		);
	}

	/**
	 * Puente de `digitalisimo_elements_kit_existing_document` (prioridad 5, sólo dentro del contexto): si nadie
	 * encontró aún el documento, lo busca por procedencia. La parte `origin` puede afinarlo después.
	 *
	 * @param int|null   $id     Lo que ya trae el filtro.
	 * @param string     $type   Tipo de documento o de entrada.
	 * @param int|string $old_id ID de origen.
	 * @param array      $data   Datos del kit (o `[ 'hash' => … ]`).
	 *
	 * @return int|null
	 */
	public static function existing_bridge( $id, $type = '', $old_id = '', $data = array() ) {
		if ( null !== $id && 0 !== $id ) {
			return $id;
		}
		$data   = is_array( $data ) ? $data : array();
		$hash   = isset( $data['hash'] ) && is_string( $data['hash'] ) && 1 === count( $data ) ? $data['hash'] : self::document_hash( $data );
		$lookup = self::lookup( (string) $type, $old_id, $hash );
		Context::note_lookup( (string) $type, $old_id, $hash, $lookup );
		return 'reuse' === $lookup['status'] ? $lookup['id'] : null;
	}

	// ── Pantalla «Kits» (la pintan y la atienden `Report_Page` y `Report`) ─────────────────────────────────────

	/** URL de la pantalla «Kits» del sitio actual, con una sesión y un aviso opcionales. */
	public static function screen_url( string $session = '', string $notice = '' ): string {
		$args = array( 'page' => Module::PAGE );
		if ( '' !== $session ) {
			$args['session'] = $session;
		}
		if ( '' !== $notice ) {
			$args['kit_notice'] = $notice;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** URL de la App que importa el kit preparado con ese token. */
	public static function app_url( string $token ): string {
		$base = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->app ) ? \Elementor\Plugin::$instance->app->get_base_url() : admin_url( 'admin.php?page=elementor-app' );
		return $base . '#/import-customization?referrer=' . Module::SOURCE . '&id=' . rawurlencode( $token );
	}

	/**
	 * ¿Es una plantilla que se aplica sola (D8)? Las del Theme Builder con ubicación propia (cabecera, pie, entrada
	 * única, archivo…) y los popups. No lo son `loop-item` ni `section`: también son del grupo Theme Builder, pero no
	 * tienen ubicación y sólo se ven incrustadas (una sección con ubicación y condiciones la cierra
	 * `Finish::after_save()`).
	 */
	public static function is_theme_template( string $doc_type ): bool {
		if ( '' === $doc_type || 'loop-item' === $doc_type ) {
			return false;
		}
		if ( 'popup' === $doc_type ) {
			return true;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return false;
		}
		$class = \Elementor\Plugin::$instance->documents->get_document_type( $doc_type, false );
		if ( ! is_string( $class ) || ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document' ) || ! is_subclass_of( $class, '\ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document' ) ) {
			return false;
		}
		$location = is_callable( array( $class, 'get_property' ) ) ? call_user_func( array( $class, 'get_property' ), 'location' ) : '';
		return is_string( $location ) && '' !== $location;
	}

	/** Tipos de entrada que «Publicar páginas y plantillas» nunca publica (D8): no se ven solos o se publican en otro sitio. */
	const NEVER_PUBLISH = array( 'e-floating-buttons', 'elementor_snippet', 'elementor_font', 'elementor_icons', 'attachment', 'nav_menu_item', 'revision', 'wp_block', 'product_variation' );

	/**
	 * ¿Puede «Publicar páginas y plantillas» publicar esta entrada (D8)? Un borrador de un tipo visible (páginas,
	 * entradas, productos…) o una plantilla de la biblioteca que sólo se ve incrustada. Nunca las plantillas del
	 * Theme Builder ni los popups (se publican desde el Theme Builder), los botones flotantes, los fragmentos, las
	 * fuentes ni los iconos. Lo demás (sesión, revisión, copia local) lo decide quien llama.
	 */
	public static function publishable( int $post_id ): bool {
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post || 'draft' !== $post->post_status || in_array( $post->post_type, self::NEVER_PUBLISH, true ) ) {
			return false;
		}
		if ( 'elementor_library' === $post->post_type ) {
			$doc_type = (string) get_post_meta( $post_id, '_elementor_template_type', true );
			return '' !== $doc_type && 'kit' !== $doc_type && ! self::is_theme_template( $doc_type );
		}
		return function_exists( 'is_post_type_viewable' ) && is_post_type_viewable( $post->post_type );
	}

	// ── WP-CLI (lo usa `Kits\Cli`) ──────────────────────────────────────────────────────────────────────────────

	/**
	 * `wp digitalisimo-elements kit import`: guardián, normalizador, `upload_kit()` e `import_kit( …, false )` con el
	 * contexto, y después `Finish::after_save()`.
	 *
	 * @param string $zip  ZIP del kit.
	 * @param array  $args `mode` (`add`|`fresh`), `include` (lista) y `customization` (como la App).
	 *
	 * @return array{session: string, mode: string, report: array}
	 *
	 * @throws \RuntimeException Con el motivo.
	 */
	public static function import_file( string $zip, array $args = array() ): array {
		if ( ! current_user_can( Module::CAPABILITY ) ) {
			throw new \RuntimeException( 'Ejecuta el comando con --user=<administrador> (hace falta manage_options).' );
		}
		$module = self::import_module();
		$mode   = isset( $args['mode'] ) && Module::MODE_FRESH === $args['mode'] ? Module::MODE_FRESH : Module::MODE_ADD;
		$pending = self::prepare(
			$zip,
			array(
				'mode'    => $mode,
				'surface' => 'cli',
			)
		);
		if ( Module::MODE_FRESH === $mode && '' !== $pending['error'] ) {
			self::delete_tmp( $pending['token'] );
			throw new \RuntimeException( $pending['error'] );
		}
		$kit     = (string) self::tmp_dir( $pending['token'] ) . 'kit/kit.zip';
		$session = '';
		try {
			Context::set_pending( $pending );
			Context::activate( '', $pending );
			$uploaded = $module->upload_kit( $kit, 'local' );
			$session  = (string) $uploaded['session'];
			self::bind( $session, $pending );
			Context::deactivate();
			Context::activate( $session );
			$include = isset( $args['include'] ) && is_array( $args['include'] ) && $args['include'] ? array_values( array_map( 'strval', $args['include'] ) ) : array( 'templates', 'content', 'settings' );
			$module->import_kit(
				$session,
				array(
					'include'       => $include,
					'customization' => isset( $args['customization'] ) && is_array( $args['customization'] ) ? $args['customization'] : null,
				),
				false
			);
			$report = Runners\Finish::after_save( $session );
		} finally {
			Context::deactivate();
			if ( '' === $session ) {
				self::delete_tmp( $pending['token'] );
			}
		}
		$record = self::get( $session );
		return array(
			'session' => $session,
			'mode'    => null === $record ? Module::MODE_ADD : (string) $record['mode'],
			'report'  => $report,
		);
	}

	/**
	 * `wp digitalisimo-elements kit export`: `export_kit()` del 3.0 con el contexto de exportación; después reabre
	 * el ZIP y añade los medios con `addFile` (sin tope total, D6).
	 *
	 * @param string $target Archivo de salida.
	 * @param array  $args   `include`, `post_types`, `title` y `media` (`embed`|`link`).
	 *
	 * @return array{file: string, manifest: array, report: array}
	 *
	 * @throws \RuntimeException Con el motivo.
	 */
	public static function export_file( string $target, array $args = array() ): array {
		if ( ! current_user_can( Module::CAPABILITY ) ) {
			throw new \RuntimeException( 'Ejecuta el comando con --user=<administrador> (hace falta manage_options).' );
		}
		$module   = self::import_module();
		$title    = isset( $args['title'] ) && is_string( $args['title'] ) && '' !== $args['title'] ? $args['title'] : (string) get_bloginfo( 'name' );
		$settings = array(
			'include'       => isset( $args['include'] ) && is_array( $args['include'] ) && $args['include'] ? array_values( array_map( 'strval', $args['include'] ) ) : array( 'templates', 'content', 'settings' ),
			'kitInfo'       => array(
				'title'       => '' !== $title ? $title : 'kit',
				'description' => '',
				'source'      => 'file',
			),
			// Como «exportar todo» en la App. Sin esto el núcleo usa su personalización por defecto (`content` con
			// `mediaFormat`), que deja fuera las taxonomías de las entradas (categorías y etiquetas): sin ellas, las
			// condiciones y las metas de término del kit no tenían destino al importarlo.
			'customization' => array(
				'settings'  => null,
				'templates' => null,
				'content'   => null,
				'plugins'   => null,
			),
		);
		if ( isset( $args['post_types'] ) && is_array( $args['post_types'] ) ) {
			$settings['selectedCustomPostTypes']                   = array_values( array_map( 'strval', $args['post_types'] ) );
			$settings['customization']['content']['customPostTypes'] = $settings['selectedCustomPostTypes'];
		}
		Context::prepare_export(
			array(
				'surface'  => Export_Context::SURFACE_CLI,
				'media'    => isset( $args['media'] ) && Export_Context::MEDIA_LINK === $args['media'] ? Export_Context::MEDIA_LINK : Export_Context::MEDIA_EMBED,
				'kit_info' => $settings['kitInfo'],
			)
		);
		try {
			$export  = $module->export_kit( $settings );
			$file    = (string) $export['file_name'];
			$context = Context::export_context();
			if ( null !== $context && $context->files() ) {
				$zip = new \ZipArchive();
				if ( true !== $zip->open( $file ) ) {
					throw new \RuntimeException( 'No se pudo abrir el kit exportado para añadir los medios.' );
				}
				foreach ( $context->files() as $path => $item ) {
					$zip->addFile( $item['source'], Module::KIT_FOLDER . $path );
				}
				$zip->close();
			}
			if ( ! copy( $file, $target ) ) {
				throw new \RuntimeException( 'No se pudo escribir el archivo de salida.' );
			}
			if ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->uploads_manager->remove_file_or_dir( dirname( $file ) );
			}
			return array(
				'file'     => $target,
				'manifest' => isset( $export['manifest'] ) && is_array( $export['manifest'] ) ? $export['manifest'] : array(),
				'report'   => null === $context ? array() : $context->get_report(),
			);
		} finally {
			Context::deactivate_export();
		}
	}

	/**
	 * `wp digitalisimo-elements kit inspect`: guardián, normalizador y análisis, sin escribir fuera de los temporales.
	 *
	 * @throws \RuntimeException Con el motivo.
	 */
	public static function inspect_file( string $zip ): array {
		$inspection = Zip_Guard::inspect( $zip );
		try {
			$normalized = Normalizer::run( $inspection );
			return $normalized->record() + array(
				'media'   => count( $normalized->media() ),
				'entries' => count( $inspection->entries() ),
				'bytes'   => $inspection->bytes(),
				'mode'    => self::decide_mode( Module::MODE_FRESH, $normalized->format() ),
			);
		} finally {
			$inspection->cleanup();
		}
	}

	/**
	 * Módulo del importador 3.0 del núcleo.
	 *
	 * @return object
	 *
	 * @throws \RuntimeException Si el núcleo no lo tiene.
	 */
	public static function import_module() {
		$module = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->app ) ? \Elementor\Plugin::$instance->app->get_component( 'import-export-customization' ) : null;
		if ( ! is_object( $module ) ) {
			throw new \RuntimeException( 'El importador de kits del constructor no está disponible.' );
		}
		return $module;
	}
}
