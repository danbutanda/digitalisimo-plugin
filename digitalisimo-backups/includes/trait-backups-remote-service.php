<?php
defined( 'ABSPATH' ) || exit;

/**
 * Respaldos en los destinos remotos (B1b, B2 e I13 de BRECHAS), compartidos por la pantalla, REST y WP-CLI:
 *
 * - **Listar** los respaldos de DIGITALÍSIMO de un destino con el `list()` del contrato, sin descargarlos: nombre, tamaño,
 *   de qué sitio dice ser (por su nombre) y si este sitio lo registró. Un respaldo de **otro sitio** que comparte el
 *   destino (el camino de migración de Backuply) aparece como «importable», sin confianza hasta comprobarlo.
 * - **Sincronizar**: lo mismo para todos los destinos (o uno), guardado en `digitalisimo_backups_remote_index` (de la red
 *   en Multisite) para que la pantalla lo muestre sin llamar a los destinos. Sólo nombres, tamaños y estado: ningún
 *   identificador remoto ni secreto; traer un archivo vuelve a listar el destino.
 * - **Traer al almacén** (y así restaurar desde el remoto): un trabajo `fetch` (Digitalisimo_Backups_Fetch_Job) que
 *   descarga por fragmentos con reanudación, verifica (SHA-256 del historial, o como archivo no confiable) y deja el
 *   archivo en el almacén; después se restaura con la restauración por pasos (pantalla, REST o
 *   `wp digitalisimo-backups restore --from=<destino>`, que hace las dos cosas).
 *
 * Nunca se añade al historial un archivo del remoto: el historial da confianza (su SHA-256 registrado aquí), y un archivo
 * que este sitio no registró entra como «sin registro».
 */
trait Digitalisimo_Backups_Remote_Service {

	/* ------------------------------------------------------------------ Listar y sincronizar */

	/** Nombre de un respaldo de DIGITALÍSIMO en un destino (lo que «Restaurar» lista): nunca un temporal ni la prueba de conexión. */
	public static function remote_backup_name( $name ) {
		$name = (string) $name;
		return strlen( $name ) <= 200 && (bool) preg_match( '/^(?:digitalisimo-[A-Za-z0-9_-][A-Za-z0-9._-]*\.(?:zip|dgbk)|safety-before-restore-[A-Za-z0-9_-][A-Za-z0-9._-]*\.zip)$/D', $name ) && false === strpos( $name, '..' ) && false === strpos( $name, '.part-' );
	}
	/** Sitio y fecha que dice el nombre (`digitalisimo-backup-<sitio>-<AAAAMMDD>-<HHMMSS>-<id>`): una pista, nunca una prueba. */
	private static function remote_origin( $name ) {
		if ( ! preg_match( '/^(?:safety-before-restore-)?digitalisimo-backup-([a-z0-9-]+)-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})-[0-9a-f]{8}\.(?:zip|dgbk)$/D', (string) $name, $m ) ) return array( 'site' => '', 'date' => '' );
		return array( 'site' => $m[1], 'date' => $m[2] . '-' . $m[3] . '-' . $m[4] . ' ' . $m[5] . ':' . $m[6] . ':' . $m[7] );
	}
	/** Identificador de este sitio en los nombres de sus respaldos. */
	private static function remote_own_site() { return sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); }
	/** Filas del historial de este sitio cuya copia remota está en la conexión $id, por nombre de archivo. */
	private static function remote_registered( $id ) {
		$out = array();
		foreach ( (array) get_option( self::HISTORY, array() ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['remote']['provider'] ) ) continue;
			// Sin SHA-256 en la fila (filas antiguas) no hay con qué verificar la copia: se trae como «sin registro» (C9).
			if ( ! preg_match( '/^[0-9a-f]{64}$/', strtolower( (string) ( $row['sha256'] ?? '' ) ) ) ) continue;
			if ( self::remote_connection( (array) $row['remote'] ) === (string) $id ) $out[ (string) ( $row['file'] ?? '' ) ] = $row;
		}
		return $out;
	}
	/**
	 * Vista pública de un listado: sólo los respaldos de DIGITALÍSIMO, con su estado (`registered`: de este sitio y en su
	 * historial; `local`: ya en el almacén; `importable`: de otro sitio o sin fila, sin verificar). Sin identificadores remotos.
	 */
	private static function remote_rows( $id, array $listing ) {
		$mine = self::remote_own_site();
		$registered = self::remote_registered( $id );
		$rows = array();
		foreach ( $listing as $item ) {
			$name = (string) ( $item['name'] ?? '' );
			if ( ! self::remote_backup_name( $name ) ) continue;
			$origin = self::remote_origin( $name );
			$row = $registered[ $name ] ?? null;
			try { $local = '' !== self::local_path( $name ); } catch ( Exception $e ) { $local = false; }
			$view = array(
				'destination' => (string) $id,
				'name' => $name,
				'size' => max( 0, (int) ( $item['size'] ?? 0 ) ),
				'site' => $origin['site'],
				'date' => $origin['date'],
				'origin' => '' === $origin['site'] ? 'unknown' : ( $origin['site'] === $mine ? 'this' : 'other' ),
				'registered' => (bool) $row,
				'local' => $local,
				'state' => $local ? 'local' : ( $row ? 'registered' : 'importable' ),
			);
			$view['label'] = self::remote_label( $view );
			$rows[] = $view;
		}
		usort( $rows, function( $a, $b ) { return strcmp( $b['date'] . $b['name'], $a['date'] . $a['name'] ); } );
		return array_slice( $rows, 0, 500 );
	}
	private static function remote_label( array $row ) {
		if ( 'local' === $row['state'] ) {
			$expires = self::fetched_expires( (string) $row['name'] );
			return 'Ya está en el almacén privado' . ( '' !== $expires ? ' (traído «sin registro»: se borra el ' . $expires . ' si no se usa)' : '' );
		}
		if ( $row['registered'] ) return 'De este sitio (en el historial): se verifica por su SHA-256 al traerlo';
		$who = 'other' === $row['origin'] ? 'De otro sitio (' . $row['site'] . ')' : ( 'this' === $row['origin'] ? 'Con el nombre de este sitio, sin fila en el historial' : 'Origen desconocido' );
		return $who . ': sin verificar; al traerlo se comprueba como archivo no confiable';
	}
	/** Conexión remota completa y con credenciales legibles, o el error que corresponde. */
	private static function remote_destination_id( $id ) {
		$id = (string) $id;
		if ( '' === $id || 'local' === $id || '' === Digitalisimo_Backups_Destinations::type_of( $id ) || ! Digitalisimo_Backups_Destinations::exists( $id ) ) return self::service_error( 'destination', 'El destino «' . $id . '» no existe.', 404 );
		$problem = self::credentials_problem( $id );
		if ( '' !== $problem ) return self::service_error( 'credentials', $problem, 409 );
		if ( ! isset( self::manual_destinations( self::settings() )[ $id ] ) ) return self::service_error( 'destination_incomplete', 'El destino ' . self::destination_label( $id ) . ' no tiene una conexión completa: complétala en «Destinos».', 409 );
		return $id;
	}
	/** Los respaldos de DIGITALÍSIMO del destino $id, listados ahora (sin descargarlos). */
	public static function service_remote_backups( $id ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$id = self::remote_destination_id( $id );
		if ( is_wp_error( $id ) ) return $id;
		try {
			return self::remote_rows( $id, (array) self::destination( $id )->list() );
		} catch ( Exception $e ) {
			return self::service_error( 'remote_list', 'No se pudo listar ' . self::destination_label( $id ) . ': ' . Digitalisimo_Backups_Http::redact( $e->getMessage() ), 502 );
		}
	}
	private static function remote_index() {
		$value = is_multisite() ? get_site_option( self::REMOTE_INDEX, array() ) : get_option( self::REMOTE_INDEX, array() );
		return is_array( $value ) ? $value : array();
	}
	private static function remote_index_save( array $index ) {
		if ( is_multisite() ) update_site_option( self::REMOTE_INDEX, $index );
		else update_option( self::REMOTE_INDEX, $index, false );
	}
	private static function remote_summary( array $entry ) {
		$items = (array) ( $entry['items'] ?? array() );
		$count = function( $key, $value ) use ( $items ) { return count( array_filter( $items, function( $row ) use ( $key, $value ) { return ( $row[ $key ] ?? null ) === $value; } ) ); };
		return array(
			'destination' => (string) ( $entry['destination'] ?? '' ),
			'name' => (string) ( $entry['name'] ?? '' ),
			'synced_at' => ! empty( $entry['synced_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $entry['synced_at'] ) . ' UTC' : '',
			'error' => (string) ( $entry['error'] ?? '' ),
			'count' => count( $items ),
			'importable' => $count( 'state', 'importable' ),
			'registered' => $count( 'registered', true ),
			'other_sites' => $count( 'origin', 'other' ),
		);
	}
	/**
	 * Sincroniza: lista los respaldos de cada destino remoto con conexión completa (o sólo de $id) y guarda el índice
	 * destino a destino. Un destino que no responde conserva su lista anterior con el error. En la web, con el tiempo que
	 * le queda a la petición: ningún listado espera más (Digitalisimo_Backups_Http::$cap_timeout) y los destinos que ya no
	 * caben quedan en `pending` (se sincronizan en la siguiente vez). Devuelve el resumen por destino y las filas.
	 */
	public static function service_sync( $id = '', array $args = array() ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		if ( '' !== (string) $id ) {
			$checked = self::remote_destination_id( $id );
			if ( is_wp_error( $checked ) ) return $checked;
			$ids = array( $checked );
		} else {
			$ids = array_values( array_diff( array_map( 'strval', array_keys( self::manual_destinations( self::settings() ) ) ), array( 'local' ) ) );
		}
		if ( ! $ids ) return self::service_error( 'no_destinations', 'No hay destinos remotos con una conexión completa: configúralos en «Destinos».', 409 );
		$summaries = array();
		$items = array();
		$pending = array();
		foreach ( $ids as $one ) {
			// Cada listado necesita unos segundos: sin ellos, el destino queda para la próxima sincronización.
			$left = Digitalisimo_Backups_Step_Budget::request_remaining();
			if ( $left < 5 ) { $pending[] = array( 'destination' => $one, 'name' => self::destination_label( $one ) ); continue; }
			$entry = array( 'destination' => $one, 'name' => self::destination_label( $one ), 'synced_at' => time(), 'error' => '', 'items' => array() );
			$previous = (array) ( self::remote_index()[ $one ]['items'] ?? array() );
			$cap = Digitalisimo_Backups_Http::$cap_timeout;
			try {
				if ( is_finite( $left ) ) Digitalisimo_Backups_Http::$cap_timeout = max( 3, (int) floor( $left - 1 ) );
				$problem = self::credentials_problem( $one );
				if ( '' !== $problem ) throw new Exception( $problem );
				$entry['items'] = self::remote_rows( $one, (array) self::destination( $one )->list() );
			} catch ( Throwable $e ) {
				$entry['error'] = Digitalisimo_Backups_Http::redact( $e->getMessage() );
				$entry['items'] = $previous;
			} finally {
				Digitalisimo_Backups_Http::$cap_timeout = $cap;
			}
			// Guardado destino a destino: si la petición muere en el siguiente, éste ya quedó.
			$index = self::remote_index();
			$index[ $one ] = $entry;
			foreach ( array_keys( $index ) as $known ) if ( ! Digitalisimo_Backups_Destinations::exists( (string) $known ) || 'local' === $known ) unset( $index[ $known ] );
			self::remote_index_save( $index );
			$summaries[] = self::remote_summary( $entry );
			$items = array_merge( $items, $entry['items'] );
		}
		return array( 'destinations' => $summaries, 'items' => $items, 'pending' => $pending );
	}
	/** Lo último que se sincronizó, sin llamar a los destinos (pantalla, `GET /destinations/sync`). */
	public static function service_remote_index() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$summaries = array();
		$items = array();
		foreach ( self::remote_index() as $id => $entry ) {
			if ( ! is_array( $entry ) || ! Digitalisimo_Backups_Destinations::exists( (string) $id ) || 'local' === $id ) continue;
			$registered = self::remote_registered( (string) $id );
			$rows = array();
			foreach ( (array) ( $entry['items'] ?? array() ) as $row ) {
				if ( ! is_array( $row ) ) continue;
				// Lo local y el historial cambian sin sincronizar (se trajo, se borró, se quitó la fila): se recalculan.
				try { $row['local'] = '' !== self::local_path( (string) ( $row['name'] ?? '' ) ); } catch ( Exception $e ) { $row['local'] = false; }
				$row['registered'] = isset( $registered[ (string) ( $row['name'] ?? '' ) ] );
				$row['state'] = $row['local'] ? 'local' : ( ! empty( $row['registered'] ) ? 'registered' : 'importable' );
				$row['label'] = self::remote_label( $row );
				$rows[] = $row;
			}
			$entry['items'] = $rows;
			$summaries[] = self::remote_summary( $entry );
			$items = array_merge( $items, $rows );
		}
		return array( 'destinations' => $summaries, 'items' => $items );
	}

	/* ------------------------------------------------------------------ Traer al almacén */

	/**
	 * Trae al almacén el respaldo $name del destino $id (de este sitio o de otro) con un trabajo `fetch`. En WP-CLI espera
	 * a que termine; en la web avanza lo que cabe en la petición y sigue por la cola. Devuelve el resultado o el trabajo.
	 */
	public static function service_remote_fetch( $id, $name, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$id = self::remote_destination_id( $id );
		if ( is_wp_error( $id ) ) return $id;
		$name = (string) $name;
		if ( ! self::remote_backup_name( $name ) ) return self::service_error( 'remote_name', 'Ese nombre no es el de un respaldo de DIGITALÍSIMO (.zip o .dgbk) en el destino.', 400 );
		$row = self::remote_registered( $id )[ $name ] ?? array();
		// Sólo un respaldo de este sitio que ya está en el almacén con el SHA-256 de su fila se da por traído. Cualquier otro
		// archivo con ese nombre (una subida, otra conexión, uno copiado a mano) no es la copia del destino: se trae siempre
		// (C4), y si ya estaba el mismo contenido, no se duplica (fetch_place(), C5).
		try {
			$local = '' !== $name ? self::local_path( $name ) : '';
			if ( $row && '' !== $local && hash_equals( strtolower( (string) $row['sha256'] ), (string) @hash_file( 'sha256', $local ) ) ) return array( 'file' => $name, 'fetched' => false, 'registered' => true, 'message' => 'El respaldo ya está en el almacén privado (el SHA-256 de su historial coincide).' );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return self::fetch_start( array( 'connection' => $id, 'name' => $name, 'registered' => $row ? (string) $row['file'] : '', 'sha256' => $row ? strtolower( (string) ( $row['sha256'] ?? ( $row['remote']['sha256'] ?? '' ) ) ) : '', 'size' => $row ? (int) ( $row['size'] ?? 0 ) : 0 ), $args );
	}
	/** Encola (o retoma, si ya hay uno del mismo archivo) el trabajo `fetch` y lo hace avanzar en esta petición. */
	private static function fetch_start( array $fetch, array $args ) {
		$via = (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) );
		$spec = array(
			'scope' => is_multisite() ? 'network' : 'installation',
			'type' => 'fetch',
			'kind' => Digitalisimo_Backups_Fetch_Job::KIND,
			'destination' => 'local',
			'policy' => 'fetch:' . $fetch['connection'] . ':' . $fetch['name'],
			'retain' => 1,
			'retention' => 'none',
			'site_id' => (int) get_current_blog_id(),
			'file' => (string) $fetch['name'],
			'inline' => false,
			'fetch' => $fetch,
			'origin' => array( 'via' => $via, 'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0, 'blog_id' => (int) get_current_blog_id(), 'context' => is_multisite() ? 'network' : 'site' ),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
		);
		try {
			$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
		} catch ( Throwable $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
		$cli = array_key_exists( 'drive_cli', $args ) ? (bool) $args['drive_cli'] : 'cli' === PHP_SAPI;
		return self::fetch_outcome( self::fetch_drive( (string) $queued['job']['id'], $cli ) );
	}
	/**
	 * Hace avanzar el trabajo $id: en WP-CLI hasta terminar (esperando reintentos y a otra operación del almacén, que no
	 * puede durar para siempre: tras un minuto sin turno se deja en la cola); en la web, hasta el límite de la petición.
	 */
	private static function fetch_drive( $id, $cli ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		$idle = 0;
		for ( $guard = 0; $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) && $guard < 100000; $guard++ ) {
			if ( ! $cli && Digitalisimo_Backups_Step_Budget::request_remaining() < 2 ) break;
			$wait = (int) $job['retry_at'] - time();
			if ( $wait > 0 ) {
				if ( ! $cli ) break;
				sleep( min( $wait, 30 ) );
			}
			$tick = Digitalisimo_Backups_Jobs::tick( $cli ? PHP_INT_MAX : null );
			$job = Digitalisimo_Backups_Jobs::get( $id );
			if ( 'worked' === $tick['result'] ) { $idle = 0; continue; }
			if ( ! $cli || ++$idle > 60 ) break;
			sleep( 1 );
		}
		return $job;
	}
	private static function fetch_outcome( $job ) {
		if ( ! $job ) return self::service_error( 'storage', 'El trabajo de la descarga ya no está en el almacén privado.', 500 );
		$view = self::service_job_view( $job );
		if ( 'success' === $job['status'] ) {
			$result = (array) $job['result'];
			$keep = array_flip( array( 'size', 'sha256', 'registered', 'deduplicated', 'container', 'trust', 'signature', 'key', 'confirmation', 'needs_secret', 'compatible', 'problems', 'scope', 'created_at', 'multisite' ) );
			return array( 'file' => (string) ( $result['file'] ?? '' ), 'fetched' => true, 'message' => (string) ( $job['progress']['message'] ?? '' ), 'job' => $view ) + array_intersect_key( $result, $keep );
		}
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return self::service_error( 'fetch_failed', (string) ( $job['error']['message'] ?? ( $job['progress']['message'] ?? 'La descarga falló.' ) ), 'cancelled' === $job['status'] ? 409 : 502, array( 'job' => (string) $job['id'] ) );
		return array( 'file' => '', 'fetched' => false, 'running' => true, 'message' => 'La descarga sigue en segundo plano (trabajo ' . $job['id'] . '): la continúan la pantalla, WP-Cron, `POST /jobs/tick` o `wp digitalisimo-backups jobs run`.', 'job' => $view );
	}

	/* ------------------------------------------------------------------ Pantalla */

	/**
	 * «Respaldos en los destinos remotos», en «Destinos» (desde 1.0.74; antes, dentro de «Restaurar»): sincronizar, lo último sincronizado de cada destino y
	 * «Traer al almacén» de cada respaldo que aún no está en el almacén (de este sitio o de otro).
	 */
	private static function remote_panel() {
		$ready = array_values( array_diff( array_map( 'strval', array_keys( self::manual_destinations( self::settings() ) ) ), array( 'local' ) ) );
		$index = self::service_remote_index();
		if ( is_wp_error( $index ) ) return;
		if ( ! $ready && ! $index['destinations'] ) return;
		$network = self::network_context() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '';
		echo '<h3 id="digitalisimo-backups-remote">Respaldos en los destinos remotos</h3>' . self::help_html( 'Sincroniza para ver los respaldos de DIGITALÍSIMO que hay en cada destino, también los de otro sitio que lo comparte. Así se migra o se recupera un sitio perdido. Al traer uno al almacén se descarga por fragmentos, se reanuda si se corta y se comprueba: los de este sitio, por el SHA-256 de su historial; los demás, como archivo no confiable (firma, cifrado, entradas y SQL). Después, los de este sitio vuelven a su fila en «Respaldos» y los demás aparecen en Herramientas → «Restaurar un archivo suelto».' );
		if ( $ready ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_SYNC );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SYNC ) . '">' . $network . self::return_field( 'servers', 'remote' ) . '<p><select name="destination"><option value="">Todos los destinos</option>';
			foreach ( $ready as $id ) echo '<option value="' . esc_attr( $id ) . '">' . esc_html( self::destination_label( $id ) ) . '</option>';
			echo '</select> <button class="button">Sincronizar</button></p></form>';
		}
		foreach ( $index['destinations'] as $summary ) echo '<p><strong>' . esc_html( $summary['name'] ) . '</strong> · sincronizado ' . esc_html( $summary['synced_at'] ) . ' · ' . (int) $summary['count'] . ' respaldo(s), ' . (int) $summary['importable'] . ' para importar' . ( $summary['other_sites'] ? ', ' . (int) $summary['other_sites'] . ' de otro sitio' : '' ) . ( '' !== $summary['error'] ? ' · <span class="digitalisimo-backups-badge is-warning">No respondió: ' . esc_html( $summary['error'] ) . '</span>' : '' ) . '</p>';
		if ( ! $index['items'] ) return;
		echo '<table class="widefat striped"><thead><tr><th>Archivo</th><th>Destino</th><th>Tamaño</th><th>Fecha</th><th>Estado</th><th></th></tr></thead><tbody>';
		foreach ( array_slice( $index['items'], 0, 200 ) as $row ) {
			echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( self::destination_label( (string) $row['destination'] ) ) . '</td><td>' . esc_html( size_format( (int) $row['size'] ) ) . '</td><td>' . esc_html( '' !== $row['date'] ? $row['date'] . ' UTC' : '—' ) . '</td><td>' . esc_html( $row['label'] ) . '</td><td>';
			if ( 'local' !== $row['state'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( self::ACTION_REMOTE_FETCH );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_REMOTE_FETCH ) . '"><input type="hidden" name="destination" value="' . esc_attr( $row['destination'] ) . '"><input type="hidden" name="name" value="' . esc_attr( $row['name'] ) . '">' . $network . self::return_field( 'servers', 'remote' ) . '<button class="button button-small" title="Descarga el respaldo por fragmentos al almacén privado y lo comprueba antes de ofrecerlo para restaurar">Traer al almacén</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	/** admin-post «Sincronizar». */
	public static function sync_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_SYNC );
		$result = self::service_sync( sanitize_key( wp_unslash( $_POST['destination'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		$parts = array();
		foreach ( $result['destinations'] as $summary ) $parts[] = $summary['name'] . ': ' . ( '' !== $summary['error'] ? 'no respondió (' . $summary['error'] . ')' : $summary['count'] . ' respaldo(s), ' . $summary['importable'] . ' para importar' );
		foreach ( (array) ( $result['pending'] ?? array() ) as $pending ) $parts[] = $pending['name'] . ': sin tiempo en esta petición, vuelve a sincronizar';
		self::redirect( true, 'Destinos sincronizados. ' . implode( ' · ', $parts ) . '.' );
	}
	/** admin-post «Traer al almacén» de un respaldo listado en un destino: encola el trabajo y avanza lo que cabe. */
	public static function remote_fetch_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_REMOTE_FETCH );
		$result = self::service_remote_fetch( sanitize_text_field( wp_unslash( $_POST['destination'] ?? '' ) ), sanitize_file_name( wp_unslash( $_POST['name'] ?? '' ) ), array( 'network' => self::network_context(), 'via' => 'admin-post' ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, $result['message'] );
	}

	/* ------------------------------------------------------------------ Fases del trabajo `fetch` */

	/** @internal Una fase de Digitalisimo_Backups_Fetch_Job. */
	public static function fetch_phase( $phase, array $job ) {
		if ( Digitalisimo_Backups_Job_Store::JOB_FORMAT !== (int) ( $job['spec']['engine']['job_format'] ?? 0 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El trabajo se creó con otra versión del motor; vuelve a pedir la descarga.' );
		switch ( $phase ) {
			case 'preflight': return self::fetch_preflight( $job );
			case 'receive': return self::fetch_receive( $job );
			case 'examine': return self::fetch_examine( $job );
			case 'place': return self::fetch_place( $job );
		}
		throw new Digitalisimo_Backups_Permanent_Error( 'La descarga está en una fase desconocida.' );
	}
	private static function fetch_paths( array $job ) {
		$dir = Digitalisimo_Backups_Job_Store::staging( (string) $job['id'] );
		// La extensión de lo descargado no importa: el contenedor se decide por el contenido (C11).
		return array( 'part' => $dir . '/remote.part', 'piece' => $dir . '/piece.tmp', 'done' => $dir . '/remote.bin', 'state' => $dir . '/receive.json', 'place' => $dir . '/place.json' );
	}
	/** Archivo JSON pequeño del área de trabajo (estado de `receive`, nombre elegido en `place`), o array() si no está. */
	private static function fetch_read( $path ) {
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		return is_array( $data ) ? $data : array();
	}
	private static function fetch_write( $path, array $data ) {
		if ( is_link( $path ) || false === @file_put_contents( $path . '.tmp', (string) wp_json_encode( $data ) ) || ! @rename( $path . '.tmp', $path ) ) throw new Exception( 'No se pudo guardar el avance de la descarga en el área de trabajo.' );
		self::private_file( $path );
	}
	/**
	 * @internal Para el motor (Digitalisimo_Backups_Fetch_Job::advanced()): el proceso que murió en `receive` dejó en disco más
	 * de lo que había cuando empezó su tramo. Entonces la muerte no gasta un intento (C1).
	 */
	public static function fetch_advanced( array $job ) {
		try {
			if ( 'receive' !== (string) ( $job['phase'] ?? '' ) ) return false;
			$paths = self::fetch_paths( $job );
			$state = self::fetch_read( $paths['state'] );
			if ( empty( $state['open'] ) ) return false;
			clearstatcache( true, $paths['part'] );
			return is_file( $paths['part'] ) && (int) filesize( $paths['part'] ) > (int) ( $state['start'] ?? PHP_INT_MAX );
		} catch ( Throwable $e ) {
			return false;
		}
	}
	/** Segundos que le quedan al tramo (para que ninguna petición de la descarga espere más), al menos 5. */
	private static function fetch_time_left( $deadline ) {
		return is_finite( $deadline ) ? max( 5, (int) floor( $deadline - microtime( true ) ) ) : 120;
	}
	/** Conexión, copia remota (la del historial o, para un archivo sin fila, la que lista el destino ahora) y espacio. */
	private static function fetch_preflight( array $job ) {
		$fetch = (array) $job['spec']['fetch'];
		$id = (string) $fetch['connection'];
		$name = (string) $fetch['name'];
		if ( ! Digitalisimo_Backups_Destinations::exists( $id ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La conexión de destino ya no existe.' );
		$problem = self::credentials_problem( $id );
		if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		Digitalisimo_Backups_Jobs::progress( 1, 'Buscando ' . $name . ' en ' . self::destination_label( $id ) . '…', true );
		$remote = array();
		$size = 0;
		if ( '' !== (string) $fetch['registered'] ) {
			$row = self::history_item( (string) $fetch['registered'] );
			if ( ! $row || empty( $row['remote']['provider'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo ya no está en el historial con su copia remota.' );
			if ( self::remote_connection_gone( (array) $row['remote'] ) ) throw new Digitalisimo_Backups_Permanent_Error( self::gone_text( (array) $row['remote'] ) );
			$remote = (array) $row['remote'];
			$size = (int) ( $row['size'] ?? ( $remote['size'] ?? 0 ) );
		}
		if ( ! $remote || $size <= 0 ) {
			$found = null;
			// El listado tampoco espera más de lo que le queda al tramo (C2).
			$cap = Digitalisimo_Backups_Http::$cap_timeout;
			try {
				Digitalisimo_Backups_Http::$cap_timeout = self::fetch_time_left( Digitalisimo_Backups_Jobs::step_deadline() );
				foreach ( (array) self::destination( $id )->list() as $item ) if ( (string) ( $item['name'] ?? '' ) === $name ) $found = $item;
			} finally {
				Digitalisimo_Backups_Http::$cap_timeout = $cap;
			}
			if ( ! $found || empty( $found['remote'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo ' . $name . ' ya no está en ' . self::destination_label( $id ) . '.' );
			if ( ! $remote ) $remote = (array) $found['remote'];
			if ( $size <= 0 ) $size = (int) ( $found['size'] ?? 0 );
		}
		if ( $size <= 0 ) throw new Digitalisimo_Backups_Permanent_Error( 'El destino no informa el tamaño de ' . $name . '; no se puede descargar por fragmentos.' );
		if ( $size > Digitalisimo_Backups_Uploads::MAX_SIZE ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo supera el tamaño máximo que se trae de un destino (64 GB).' );
		// Lo que ya llegó se descarga sólo una vez; un .dgbk necesita además sitio para descifrarlo al comprobarlo (si el
		// nombre engaña, la comprobación vuelve a medir el espacio con la cabecera del archivo).
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( self::local_directory() );
		$needed = $size * ( Digitalisimo_Backups_Uploads::encrypted_name( $name ) ? 2.05 : 1 ) + Digitalisimo_Backups_Security::MARGIN;
		if ( null !== $free && $free < $needed ) throw new Digitalisimo_Backups_Permanent_Error( 'No hay espacio en el almacén privado para traer ' . $name . ': se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( (int) $needed ) . ' libres y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.' );
		return array( 'next' => 'receive', 'checkpoint' => array( 'remote' => $remote, 'size' => $size, 'ranged' => (bool) self::destination( $id )->ranged() ), 'message' => 'Descargando ' . $name . ' de ' . self::destination_label( $id ) . '…' );
	}
	/** Tamaño del fragmento: 8 MiB por defecto (filtro `digitalisimo_backups_fetch_chunk`, entre 64 KiB y 64 MiB). */
	private static function fetch_chunk() {
		$chunk = function_exists( 'apply_filters' ) ? (int) apply_filters( 'digitalisimo_backups_fetch_chunk', 8388608 ) : 8388608;
		return max( 65536, min( 67108864, $chunk ) );
	}
	/**
	 * Descarga por fragmentos, siempre desde lo que ya está en disco: una muerte, un corte o el fin del tramo se reanudan
	 * donde quedó. Cada fragmento llega a un archivo aparte y sólo se anexa si es exactamente el pedido.
	 *
	 * `receive.json` (área del trabajo) guarda dónde empezó este tramo y el tamaño de fragmento: si el proceso muere con más
	 * bytes en disco, el motor no le cuenta un intento (fetch_advanced(), C1), y el tramo siguiente empieza con la mitad del
	 * fragmento. Ninguna petición espera más de lo que le queda al tramo (C2), y nunca se escribe más de lo pedido (C3).
	 */
	private static function fetch_receive( array $job ) {
		$fetch = (array) $job['spec']['fetch'];
		$pre = (array) ( $job['checkpoint']['preflight'] ?? array() );
		$size = (int) ( $pre['size'] ?? 0 );
		$remote = (array) ( $pre['remote'] ?? array() );
		if ( $size <= 0 || ! $remote ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta la copia remota que encontró la comprobación; se busca otra vez.', 'preflight' );
		$paths = self::fetch_paths( $job );
		foreach ( $paths as $path ) if ( is_link( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La descarga no puede ser un enlace simbólico.' );
		if ( is_file( $paths['done'] ) ) return array( 'next' => 'examine', 'checkpoint' => array( 'size' => $size ), 'message' => 'Descargado; comprobando el archivo…' );
		clearstatcache( true, $paths['part'] );
		$offset = is_file( $paths['part'] ) ? (int) filesize( $paths['part'] ) : 0;
		if ( $offset > $size ) { @unlink( $paths['part'] ); $offset = 0; }
		$destination = self::destination( (string) $fetch['connection'] );
		$ranged = $destination->ranged();
		// Sin lectura por rangos, lo que quedó a medias no sirve: se descarga entero (un paso).
		if ( ! $ranged && $offset > 0 ) { @unlink( $paths['part'] ); $offset = 0; }
		$state = self::fetch_read( $paths['state'] );
		$chunk = (int) ( $state['chunk'] ?? ( $job['checkpoint']['receive']['chunk'] ?? self::fetch_chunk() ) );
		// El tramo anterior murió sin cerrar: fragmentos de la mitad (uno lento no se repite igual).
		if ( ! empty( $state['open'] ) ) $chunk = (int) ( $chunk / 2 );
		$chunk = max( 65536, min( self::fetch_chunk() * 4, $chunk ) );
		$deadline = min( Digitalisimo_Backups_Jobs::step_deadline(), microtime( true ) + 20 );
		$start = $offset;
		self::fetch_write( $paths['state'], array( 'open' => true, 'start' => $start, 'chunk' => $chunk ) );
		$failure = null;
		while ( $offset < $size ) {
			try {
				$began = microtime( true );
				@unlink( $paths['piece'] );
				$length = $ranged ? min( $chunk, $size - $offset ) : $size;
				$got = (int) $destination->get_range( $remote, $paths['piece'], $offset, $length, array( 'total' => $size, 'timeout' => self::fetch_time_left( $deadline ) ) );
				clearstatcache( true, $paths['piece'] );
				if ( $got <= 0 || $got !== (int) @filesize( $paths['piece'] ) ) throw new Exception( 'El fragmento descargado no tiene el largo que dice el destino.' );
				if ( $offset + $got > $size ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de ' . self::destination_label( (string) $fetch['connection'] ) . ' es más largo de lo que se listó (cambió en el destino); vuelve a pedirlo.' );
				// Del fragmento a la descarga, por flujo (sin cargarlo en memoria); una escritura a medias se deshace.
				$out = @fopen( $paths['part'], 'ab' );
				$in = @fopen( $paths['piece'], 'rb' );
				$written = $out && $in ? stream_copy_to_stream( $in, $out ) : false;
				$flushed = $out && fflush( $out );
				if ( $in ) fclose( $in );
				if ( $out ) fclose( $out );
				clearstatcache( true, $paths['part'] );
				if ( $written !== $got || ! $flushed || (int) filesize( $paths['part'] ) !== $offset + $got ) {
					$handle = @fopen( $paths['part'], 'r+b' );
					if ( $handle ) { ftruncate( $handle, $offset ); fclose( $handle ); }
					throw new Exception( 'No se pudo guardar la descarga en el almacén privado (¿disco lleno?).' );
				}
				@unlink( $paths['piece'] );
				$offset += $got;
				// Fragmentos que caben en el tramo: la mitad si uno tardó más de 8 s, el doble si tardó menos de 2 s.
				$took = microtime( true ) - $began;
				if ( $ranged && $took > 8 ) $chunk = max( 65536, (int) ( $chunk / 2 ) );
				elseif ( $ranged && $took < 2 ) $chunk = min( self::fetch_chunk() * 4, $chunk * 2 );
				// El fragmento vigente, por si el proceso muere: el tramo siguiente empieza con la mitad.
				self::fetch_write( $paths['state'], array( 'open' => true, 'start' => $start, 'chunk' => $chunk ) );
				Digitalisimo_Backups_Jobs::progress( 5 + (int) floor( 80 * $offset / $size ), 'Descargando de ' . self::destination_label( (string) $fetch['connection'] ) . ': ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . ' de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size ) . '…' );
			} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
				@unlink( $paths['piece'] );
				throw $e;
			} catch ( Digitalisimo_Backups_Job_Cancelled $e ) {
				throw $e;
			} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
				throw $e;
			} catch ( Exception $e ) {
				@unlink( $paths['piece'] );
				$failure = $e;
				break;
			}
			if ( microtime( true ) >= $deadline ) break;
		}
		// El tramo cierra limpio (también con un corte que devuelve la fase): la mitad del fragmento si el corte fue un timeout.
		if ( $failure && $ranged ) $chunk = max( 65536, (int) ( $chunk / 2 ) );
		self::fetch_write( $paths['state'], array( 'open' => false, 'start' => $offset, 'chunk' => $chunk ) );
		if ( $failure ) {
			// Sin avance, el corte cuenta un intento; con avance, se espera un poco y se reanuda sin gastarlo.
			if ( $offset === $start ) throw $failure;
			return array( 'next' => 'receive', 'checkpoint' => array( 'offset' => $offset, 'chunk' => $chunk ), 'progress_made' => true, 'wait' => 5, 'message' => 'Corte en la descarga (' . Digitalisimo_Backups_Http::redact( $failure->getMessage() ) . '); se reanuda desde ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . '.' );
		}
		if ( $offset < $size ) return array( 'next' => 'receive', 'checkpoint' => array( 'offset' => $offset, 'chunk' => $chunk ), 'progress_made' => $offset > $start, 'message' => 'Descargando: ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . ' de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size ) . '…' );
		if ( ! @rename( $paths['part'], $paths['done'] ) ) throw new Exception( 'No se pudo cerrar la descarga en el área de trabajo.' );
		self::private_file( $paths['done'] );
		return array( 'next' => 'examine', 'checkpoint' => array( 'offset' => $offset, 'size' => $size ), 'message' => 'Descargado; comprobando el archivo…' );
	}
	/**
	 * Tamaño y SHA-256 (el del historial, si es de este sitio). Un archivo sin fila de este sitio pasa además toda la
	 * validación de archivos no confiables (la de la subida, B3). Lo que no es un respaldo válido se descarta y el trabajo
	 * falla sin reintentos; lo que impide comprobarlo (espacio, el servidor) se reintenta. El contenedor (ZIP o DGBK) sale
	 * del contenido, nunca del nombre (C11).
	 */
	private static function fetch_examine( array $job ) {
		$fetch = (array) $job['spec']['fetch'];
		$paths = self::fetch_paths( $job );
		$size = (int) ( $job['checkpoint']['preflight']['size'] ?? 0 );
		if ( is_link( $paths['done'] ) || ! is_file( $paths['done'] ) ) {
			if ( is_file( $paths['part'] ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La descarga no estaba completa; se continúa.', 'receive' );
			throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La descarga desapareció del área de trabajo; se descarga otra vez.', 'preflight' );
		}
		$from = self::destination_label( (string) $fetch['connection'] );
		clearstatcache( true, $paths['done'] );
		if ( (int) filesize( $paths['done'] ) !== $size ) {
			@unlink( $paths['done'] );
			throw new Digitalisimo_Backups_Permanent_Error( 'El archivo descargado de ' . $from . ' no tiene el tamaño ' . ( '' !== (string) $fetch['registered'] ? 'registrado' : 'listado' ) . '; no se usa.' );
		}
		Digitalisimo_Backups_Jobs::progress( 86, 'Calculando el SHA-256 de lo descargado…', true );
		$sha256 = @hash_file( 'sha256', $paths['done'] );
		if ( false === $sha256 ) throw new Exception( 'No se pudo leer lo descargado para calcular su SHA-256.' );
		$expected = strtolower( (string) $fetch['sha256'] );
		$registered = '' !== (string) $fetch['registered'] && '' !== $expected;
		if ( $registered && ! hash_equals( $expected, $sha256 ) ) {
			@unlink( $paths['done'] );
			throw new Digitalisimo_Backups_Permanent_Error( 'La copia descargada de ' . $from . ' no coincide con el SHA-256 registrado del respaldo; no se usa.' );
		}
		$container = Digitalisimo_Backups_Encrypted_File::is_encrypted( $paths['done'] ) ? 'dgbk' : 'zip';
		$result = array( 'size' => $size, 'sha256' => $sha256, 'registered' => $registered, 'container' => $container );
		if ( ! $registered ) {
			Digitalisimo_Backups_Jobs::progress( 88, 'Comprobando el archivo como no confiable: firma, cifrado, entradas, CRC y SHA-256 contra el manifiesto, y el SQL en seco…', true );
			// La comprobación sigue aunque el proxy corte la petición que la conduce.
			if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
			$report = self::upload_inspect( $paths['done'], '' );
			if ( is_wp_error( $report ) ) {
				if ( in_array( $report->get_error_code(), array( 'digitalisimo_backups_upload_invalid', 'digitalisimo_backups_upload_checksum' ), true ) ) {
					@unlink( $paths['done'] );
					throw new Digitalisimo_Backups_Permanent_Error( str_replace( 'y se descartó', 'y se descartó (no entra en el almacén)', $report->get_error_message() ) );
				}
				throw new Exception( $report->get_error_message() );
			}
			unset( $report['relocation'], $report['wp_config'] );
			$result = array_merge( $result, $report, array( 'registered' => false, 'container' => (string) ( $report['container'] ?? $container ) ) );
		}
		return array( 'next' => 'place', 'checkpoint' => array( 'sha256' => $sha256, 'result' => $result ), 'message' => 'Archivo comprobado; entrando en el almacén privado…' );
	}
	/**
	 * Un archivo del almacén (respaldo de DIGITALÍSIMO, registrado o no) con exactamente ese tamaño y SHA-256, o ''. Sólo se
	 * calcula el SHA-256 de los que tienen el mismo tamaño.
	 */
	private static function fetch_same_in_store( $sha256, $size ) {
		$dir = trailingslashit( self::local_directory() );
		foreach ( array_merge( glob( $dir . 'digitalisimo-*.zip' ) ?: array(), glob( $dir . 'digitalisimo-*.dgbk' ) ?: array(), glob( $dir . 'safety-before-restore-*.zip' ) ?: array() ) as $path ) {
			if ( is_link( $path ) || ! is_file( $path ) || (int) @filesize( $path ) !== (int) $size ) continue;
			if ( hash_equals( (string) $sha256, (string) @hash_file( 'sha256', $path ) ) ) return wp_basename( $path );
		}
		return '';
	}
	/**
	 * Entra en el almacén con `rename()` y el cerrojo corto del almacén: con el nombre de su fila (un respaldo de este sitio,
	 * también sobre una copia local dañada) o como «sin registro», con su nombre si está libre (upload_final_name()) y
	 * la extensión de su contenido. Si ese mismo contenido (tamaño y SHA-256) ya está en el almacén, no se duplica: se
	 * devuelve el que había (C5). El nombre elegido se anota antes del `rename()` (`place.json`): si el proceso muere justo
	 * después, el reintento lo reconoce por su SHA-256 y no lo descarga otra vez (C7).
	 */
	private static function fetch_place( array $job ) {
		$fetch = (array) $job['spec']['fetch'];
		$paths = self::fetch_paths( $job );
		$examine = (array) ( $job['checkpoint']['examine'] ?? array() );
		$sha256 = (string) ( $examine['sha256'] ?? '' );
		$result = (array) ( $examine['result'] ?? array() );
		if ( '' === $sha256 ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta la comprobación del archivo; se repite.', 'examine' );
		$storage = trailingslashit( self::local_directory() );
		$registered = ! empty( $result['registered'] ) ? (string) $fetch['registered'] : '';
		$name = (string) $fetch['name'];
		$size = (int) ( $result['size'] ?? 0 );
		$container = 'dgbk' === ( $result['container'] ?? '' ) ? 'dgbk' : 'zip';
		$placed = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $paths, $storage, $registered, $name, $container, $sha256, $size ) {
			$same = function( $file ) use ( $sha256 ) { $path = '' !== (string) $file ? self::local_path( (string) $file ) : ''; return '' !== $path && hash_equals( $sha256, (string) @hash_file( 'sha256', $path ) ); };
			$note = self::fetch_read( $paths['place'] );
			$chosen = (string) ( $note['target'] ?? '' );
			if ( ! is_file( $paths['done'] ) ) {
				foreach ( array_filter( array( $chosen, $registered ) ) as $candidate ) if ( $same( $candidate ) ) return array( $candidate, $candidate === $chosen && ! empty( $note['deduplicated'] ) );
				throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La descarga comprobada desapareció del área de trabajo; se descarga otra vez.', 'preflight' );
			}
			// El nombre que queda se anota antes de tocar nada: si el proceso muere aquí, el reintento lo reconoce.
			if ( '' !== $registered ) {
				if ( $same( $registered ) ) { self::fetch_write( $paths['place'], array( 'target' => $registered ) ); @unlink( $paths['done'] ); return array( $registered, false ); }
				$target = $registered;
			} else {
				$existing = self::fetch_same_in_store( $sha256, $size );
				if ( '' !== $existing ) { self::fetch_write( $paths['place'], array( 'target' => $existing, 'deduplicated' => true ) ); @unlink( $paths['done'] ); return array( $existing, true ); }
				$target = self::upload_final_name( $name, $container );
			}
			self::fetch_write( $paths['place'], array( 'target' => $target ) );
			// Un respaldo de este sitio reemplaza una copia local dañada con su nombre; uno «sin registro» nunca pisa nada.
			if ( ( '' === $registered && file_exists( $storage . $target ) ) || ! @rename( $paths['done'], $storage . $target ) ) throw new Exception( 'No se pudo colocar la copia comprobada en el almacén privado.' );
			self::private_file( $storage . $target );
			return array( $target, false );
		} );
		list( $file, $deduplicated ) = $placed;
		// Traer otra vez lo que ya estaba (sin fila) renueva su plazo en el almacén.
		if ( '' === $registered && ( ! $deduplicated || isset( self::fetched_records()[ $file ] ) ) && ! self::history_item( $file ) ) self::fetched_remember( $file, $sha256, (string) $fetch['connection'], $name );
		$from = self::destination_label( (string) $fetch['connection'] );
		if ( $deduplicated ) $message = 'Ese respaldo de ' . $from . ' ya estaba en el almacén privado con el mismo contenido (tamaño y SHA-256): ' . $file . '. No se guardó otra copia.';
		elseif ( '' !== $registered ) $message = 'Copia de ' . $from . ' descargada al almacén privado y verificada por el SHA-256 de su historial: ' . $file . '. Ya se puede restaurar.';
		elseif ( 'untrusted' === ( $result['trust'] ?? 'untrusted' ) ) $message = 'Respaldo de ' . $from . ' traído al almacén como «sin registro» y comprobado como archivo no confiable: ' . $file . '. Restaurarlo pide RESTAURAR SIN FIRMA (no tiene una firma válida de una clave de este sitio).';
		else $message = 'Respaldo de ' . $from . ' traído al almacén como «sin registro», comprobado y con una firma válida de una clave de este sitio: ' . $file . '.';
		if ( '' === $registered && ! $deduplicated ) $message .= ' Se borrará del almacén en ' . self::fetched_keep_days() . ' días si no se usa (sigue en el destino).';
		if ( ! empty( $result['needs_secret'] ) ) $message .= ' Está cifrado con una clave que este sitio no tiene: se comprueba entero al restaurarlo con su frase.';
		if ( ! empty( $result['problems'] ) && empty( $result['compatible'] ) ) $message .= ' Aviso: ' . implode( ' ', array_map( 'strval', (array) $result['problems'] ) );
		return array( 'next' => null, 'checkpoint' => array( 'file' => $file ), 'result' => array( 'file' => $file, 'connection' => (string) $fetch['connection'], 'deduplicated' => $deduplicated ) + $result, 'message' => $message );
	}

	/* ------------------------------------------------------------------ Retención de lo traído «sin registro» */

	/** Días que se conserva en el almacén un respaldo traído «sin registro» (filtro `digitalisimo_backups_fetched_keep_days`, 7). */
	private static function fetched_keep_days() {
		$days = function_exists( 'apply_filters' ) ? (int) apply_filters( 'digitalisimo_backups_fetched_keep_days', 7 ) : 7;
		return max( 1, min( 365, $days ) );
	}
	private static function fetched_records() {
		$value = is_multisite() ? get_site_option( self::FETCHED, array() ) : get_option( self::FETCHED, array() );
		return is_array( $value ) ? $value : array();
	}
	private static function fetched_save( array $records ) {
		if ( is_multisite() ) update_site_option( self::FETCHED, $records );
		else update_option( self::FETCHED, $records, false );
	}
	private static function fetched_remember( $file, $sha256, $connection, $name ) {
		$records = self::fetched_records();
		$records[ (string) $file ] = array( 'at' => time(), 'sha256' => (string) $sha256, 'connection' => (string) $connection, 'name' => (string) $name );
		self::fetched_save( array_slice( $records, -200, null, true ) );
	}
	/**
	 * Retención de lo traído «sin registro» que nadie usa (C5): pasados fetched_keep_days() días desde que se trajo, se
	 * borra del almacén (sigue en el destino, de donde se puede volver a traer). Nunca un archivo que tenga fila en el
	 * historial, que haya cambiado (otro SHA-256: se dejó de seguir) o que use una restauración sin terminar. La llama el
	 * vigilante horario de los trabajos. Devuelve los archivos borrados.
	 */
	public static function fetched_cleanup( $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$removed = array();
		try {
			$records = self::fetched_records();
			if ( ! $records ) return $removed;
			$busy = array();
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Jobs::is_restore( $job ) ) $busy[ wp_basename( (string) ( $job['spec']['file'] ?? '' ) ) ] = true;
			$changed = false;
			foreach ( $records as $file => $record ) {
				$path = self::local_path( (string) $file );
				if ( '' === $path || self::history_item( (string) $file ) ) { unset( $records[ $file ] ); $changed = true; continue; }
				if ( $now - (int) ( $record['at'] ?? $now ) < self::fetched_keep_days() * 86400 || isset( $busy[ (string) $file ] ) ) continue;
				if ( ! hash_equals( (string) ( $record['sha256'] ?? '' ), (string) @hash_file( 'sha256', $path ) ) ) { unset( $records[ $file ] ); $changed = true; continue; }
				if ( @unlink( $path ) || ! file_exists( $path ) ) { $removed[] = (string) $file; unset( $records[ $file ] ); $changed = true; }
			}
			if ( $changed ) self::fetched_save( $records );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: retención de lo traído de un destino: ' . $e->getMessage() );
		}
		return $removed;
	}
	/** Tras el SQL de una restauración: el registro de lo traído vuelve al que tenía este almacén (lo guarda el diario). */
	private static function fetched_from_journal() {
		$path = trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL;
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		if ( is_array( $data ) && is_array( $data['fetched'] ?? null ) ) self::fetched_save( $data['fetched'] );
	}
	/** Cuándo se borrará del almacén un archivo traído «sin registro» ('' si no es uno). */
	private static function fetched_expires( $file ) {
		$record = self::fetched_records()[ (string) $file ] ?? null;
		return is_array( $record ) ? gmdate( 'Y-m-d', (int) $record['at'] + self::fetched_keep_days() * 86400 ) : '';
	}
}
