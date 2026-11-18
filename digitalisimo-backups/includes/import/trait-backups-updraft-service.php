<?php
defined( 'ABSPATH' ) || exit;

/**
 * Casos de uso de la importación de conjuntos de UpdraftPlus (brecha #7), compartidos por la pantalla, REST y WP-CLI:
 * ver los conjuntos (de la carpeta de UpdraftPlus de este sitio y del almacén), importarlos (con la frase si su base de
 * datos está cifrada), traerlos de un destino remoto y borrar los del almacén. La conversión la hace el motor de
 * trabajos (Digitalisimo_Backups_Updraft_Import, que se carga sólo al usarse); el resultado es un ZIP de Backups que
 * entra en la lista de respaldos como «Importado» y se restaura con la restauración por pasos, con RESTAURAR SIN FIRMA.
 *
 * Como la subida y Backuply, se gestiona desde el sitio principal (o la administración de red). Nunca se devuelve una
 * ruta del servidor, y la frase de cifrado nunca se guarda: descifra la base de datos en el almacén al encolar.
 */
trait Digitalisimo_Backups_Updraft_Service {
	/** Carga la clase de la importación de UpdraftPlus (sólo cuando hace falta: pesa en cada petición de php-wasm). */
	public static function load_updraft_import() {
		if ( ! class_exists( 'Digitalisimo_Backups_Updraft_Import', false ) ) require_once __DIR__ . '/class-updraft-import.php';
	}
	/** Si UpdraftPlus está en este sitio y los conjuntos que se pueden importar (de su carpeta y del almacén). */
	public static function service_updraft() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_updraft_import();
		try {
			$items = Digitalisimo_Backups_Updraft_Import::sources();
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		$found = Digitalisimo_Backups_Updraft_Import::detected();
		return array(
			'detected' => $found['installed'] || $found['folder'] || (bool) $items,
			'installed' => $found['installed'],
			'active' => $found['active'],
			'sets' => $items,
			'note' => 'Importar convierte el conjunto de UpdraftPlus (su base de datos y sus ZIP) en un ZIP de Backups sin firma, sin tocar el sitio. Entra en la lista de respaldos como «Importado» y se restaura desde ahí con la restauración por pasos (copia previa, vuelta atrás, otra dirección), escribiendo RESTAURAR SIN FIRMA.',
		);
	}
	/**
	 * Encola la importación de un conjunto por su nombre (`backup_…_<nonce>`); `source` (updraft o store) lo elige si está
	 * en los dos sitios y `secret` es la frase de cifrado de UpdraftPlus si su base de datos está cifrada. Responde enseguida
	 * con el trabajo (202).
	 */
	public static function service_updraft_import( $id, array $args = array() ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_updraft_import();
		$id = (string) $id;
		$origin = (string) ( $args['source'] ?? '' );
		if ( '' !== $origin && ! in_array( $origin, array( 'updraft', 'store' ), true ) ) return self::service_error( 'updraft_source', 'El origen debe ser updraft (la carpeta de UpdraftPlus de este sitio) o store (el almacén privado).', 400 );
		if ( ! preg_match( Digitalisimo_Backups_Updraft_Import::SET, $id ) ) return self::service_error( 'updraft_name', 'Indica un conjunto de UpdraftPlus (backup_<fecha>_<sitio>_<código>) tal como aparece en la lista.', 400 );
		$decrypted = '';
		try {
			if ( '' === $origin ) foreach ( array( 'updraft', 'store' ) as $candidate ) if ( Digitalisimo_Backups_Updraft_Import::set_pieces( $candidate, $id ) ) { $origin = $candidate; break; }
			$pieces = '' === $origin ? array() : Digitalisimo_Backups_Updraft_Import::set_pieces( $origin, $id );
			if ( ! $pieces ) return self::service_error( 'not_found', 'El conjunto de UpdraftPlus solicitado no está en la carpeta de UpdraftPlus de este sitio ni en el almacén privado.', 404 );
			$item = Digitalisimo_Backups_Updraft_Import::describe( $origin, $id, $pieces );
			if ( ! $item['importable'] ) return self::service_error( 'updraft_incomplete', $item['problem'], 422 );
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) {
				if ( Digitalisimo_Backups_Jobs::is_restore( $job ) ) return self::service_error( 'busy', 'Hay una restauración en curso: importa el conjunto de UpdraftPlus cuando termine.', 409, array( 'job' => (string) $job['id'] ) );
				if ( Digitalisimo_Backups_Import_Job::is_import( $job ) && 'import:updraft:' . $origin . ':' . $id === ( $job['spec']['policy'] ?? '' ) ) return self::service_error( 'duplicate', 'Ese conjunto de UpdraftPlus ya se está importando (trabajo ' . $job['id'] . ').', 409, array( 'job' => (string) $job['id'] ) );
			}
			$plan = Digitalisimo_Backups_Updraft_Import::plan( $pieces );
			if ( $plan['encrypted'] ) {
				$secret = is_string( $args['secret'] ?? null ) ? (string) $args['secret'] : '';
				if ( '' === $secret ) return self::service_error( 'updraft_secret', 'La base de datos de este conjunto está cifrada (' . $plan['db'] . '): escribe la frase de cifrado de UpdraftPlus del sitio de origen. Sin ella no se puede descifrar ni importar.', 400 );
				$store = Digitalisimo_Backups_Updraft_Import::store_directory( true );
				$source = rtrim( Digitalisimo_Backups_Updraft_Import::origin_directory( $origin ), '/\\' ) . '/' . $plan['db'];
				$free = Digitalisimo_Backups_Archive_Inspector::free_space( $store );
				if ( null !== $free && $free < (int) @filesize( $source ) + Digitalisimo_Backups_Archive_Inspector::MARGIN ) return self::service_error( 'space', 'No hay espacio en el almacén privado para descifrar la base de datos del conjunto.', 507 );
				if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
				if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
				$decrypted = '.decrypted-' . bin2hex( random_bytes( 8 ) ) . '.gz';
				try {
					Digitalisimo_Backups_Updraft_Import::decrypt( $source, $secret, $store . '/' . $decrypted );
				} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
					$decrypted = '';
					return self::service_error( 'updraft_secret', $e->getMessage(), 400 );
				}
			}
			$spec = Digitalisimo_Backups_Updraft_Import::spec( $origin, $id, $args, $decrypted );
			$queued = Digitalisimo_Backups_Jobs::enqueue_import( $spec );
		} catch ( Exception $e ) {
			if ( '' !== $decrypted ) Digitalisimo_Backups_Updraft_Import::drop_decrypted( array( 'spec' => array( 'source' => array( 'decrypted' => $decrypted ) ) ) );
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		if ( ! empty( $queued['busy'] ) || ! empty( $queued['duplicate'] ) ) {
			if ( '' !== $decrypted ) Digitalisimo_Backups_Updraft_Import::drop_decrypted( array( 'spec' => array( 'source' => array( 'decrypted' => $decrypted ) ) ) );
			if ( ! empty( $queued['busy'] ) ) return self::service_error( 'busy', 'Hay una restauración en curso: importa el conjunto de UpdraftPlus cuando termine.', 409, array( 'job' => (string) $queued['job']['id'] ) );
			return self::service_error( 'duplicate', 'Ese conjunto de UpdraftPlus ya se está importando (trabajo ' . $queued['job']['id'] . ').', 409, array( 'job' => (string) $queued['job']['id'] ) );
		}
		Digitalisimo_Backups_Jobs::schedule( ! empty( $args['spawn'] ) );
		return self::service_job_view( $queued['job'] );
	}
	/**
	 * Trae al almacén (`updraft/`) las piezas de un conjunto que están en un destino remoto (UpdraftPlus las sube con su
	 * mismo nombre): el contrato `list()` y `get()`, en streaming, con el tamaño comprobado. En una sola petición: por la
	 * web, conjuntos que se descargan dentro del tiempo límite; los grandes, con WP-CLI.
	 */
	public static function service_updraft_fetch( $connection, $id ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_updraft_import();
		$connection = (string) $connection;
		$id = (string) $id;
		if ( ! preg_match( Digitalisimo_Backups_Updraft_Import::SET, $id ) ) return self::service_error( 'updraft_name', 'Indica un conjunto de UpdraftPlus (backup_<fecha>_<sitio>_<código>) tal como aparece en el destino.', 400 );
		if ( '' === Digitalisimo_Backups_Destinations::type_of( $connection ) ) return self::service_error( 'not_found', 'El destino ' . $connection . ' no existe.', 404 );
		$problem = self::credentials_problem( $connection );
		if ( '' !== $problem ) return self::service_error( 'credentials', $problem, 409 );
		$part = '';
		try {
			$store = Digitalisimo_Backups_Updraft_Import::store_directory( true );
			$destination = self::destination( $connection );
			$found = array();
			foreach ( (array) $destination->list() as $entry ) {
				$name = (string) ( $entry['name'] ?? '' );
				$piece = Digitalisimo_Backups_Updraft_Import::piece( $name );
				if ( $piece && $piece['set'] === $id ) $found[ $name ] = $entry;
			}
			if ( ! $found ) return self::service_error( 'not_found', 'El destino no tiene piezas del conjunto ' . $id . '.', 404 );
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Import_Job::is_updraft( $job ) && 'store' === ( $job['spec']['source']['origin'] ?? '' ) && $id === ( $job['spec']['source']['set'] ?? '' ) ) return self::service_error( 'busy', 'Ese conjunto se está importando (trabajo ' . $job['id'] . '): espera a que termine.', 409 );
			$total = 0;
			foreach ( $found as $entry ) $total += max( 0, (int) ( $entry['size'] ?? 0 ) );
			$free = Digitalisimo_Backups_Archive_Inspector::free_space( $store );
			if ( null !== $free && $free < $total * 3 + Digitalisimo_Backups_Archive_Inspector::MARGIN ) return self::service_error( 'space', 'No hay espacio en el almacén privado para traer e importar este conjunto: se necesitan unos ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $total * 3 + Digitalisimo_Backups_Archive_Inspector::MARGIN ) . ' y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.', 507 );
			if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
			Digitalisimo_Backups_Updraft_Import::clean_partials();
			ksort( $found );
			foreach ( $found as $name => $entry ) {
				$size = (int) ( $entry['size'] ?? 0 );
				$part = $store . '/.incoming-' . bin2hex( random_bytes( 8 ) ) . '.part';
				$destination->get( (array) $entry['remote'], $part );
				clearstatcache( true, $part );
				if ( ! is_file( $part ) || ( $size > 0 && (int) filesize( $part ) !== $size ) ) throw new Exception( 'La descarga de ' . $name . ' no tiene el tamaño que lista el destino; no se usa.' );
				Digitalisimo_Backups_Job_Store::with_lock( function() use ( $store, $part, $name ) {
					if ( is_link( $store . '/' . $name ) ) throw new Exception( 'Una pieza del almacén es un enlace simbólico.' );
					if ( ! @rename( $part, $store . '/' . $name ) ) throw new Exception( 'No se pudo mover la pieza traída al almacén privado.' );
					@chmod( $store . '/' . $name, 0600 );
				} );
				$part = '';
			}
			return array( 'source' => 'store', 'id' => $id, 'pieces' => array_keys( $found ), 'message' => 'Conjunto de UpdraftPlus traído al almacén: ' . $id . ' (' . count( $found ) . ' piezas). Impórtalo para convertirlo en un respaldo de Backups.' );
		} catch ( Throwable $e ) {
			return self::service_error( 'fetch', 'No se pudo traer el conjunto de UpdraftPlus del destino: ' . $e->getMessage(), 500 );
		} finally {
			if ( '' !== $part ) @unlink( $part );
		}
	}
	/** Borra del almacén las piezas de un conjunto (subidas o traídas de un destino). Las de la carpeta de UpdraftPlus son suyas. */
	public static function service_updraft_delete( $id ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_updraft_import();
		$id = (string) $id;
		if ( ! preg_match( Digitalisimo_Backups_Updraft_Import::SET, $id ) ) return self::service_error( 'updraft_name', 'Indica un conjunto de UpdraftPlus del almacén privado.', 400 );
		try {
			$pieces = Digitalisimo_Backups_Updraft_Import::set_pieces( 'store', $id );
			if ( ! $pieces ) return self::service_error( 'not_found', 'El almacén privado no tiene piezas de ese conjunto.', 404 );
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Import_Job::is_updraft( $job ) && 'store' === ( $job['spec']['source']['origin'] ?? '' ) && $id === ( $job['spec']['source']['set'] ?? '' ) ) return self::service_error( 'busy', 'Ese conjunto se está importando (trabajo ' . $job['id'] . '): cancela la importación o espera a que termine.', 409, array( 'job' => (string) $job['id'] ) );
			$dir = Digitalisimo_Backups_Updraft_Import::store_directory();
			foreach ( array_keys( $pieces ) as $name ) if ( ! @unlink( $dir . '/' . $name ) ) return self::service_error( 'storage', 'No se pudo borrar ' . $name . ' del almacén privado.', 500 );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return array( 'id' => $id, 'deleted' => count( $pieces ) );
	}
	/** «Importar» de la tarjeta (admin-post). La frase se usa tal cual y nunca se guarda. */
	public static function updraft_import_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_UPDRAFT_IMPORT );
		$secret = isset( $_POST['updraft_secret'] ) && is_string( $_POST['updraft_secret'] ) ? (string) wp_unslash( $_POST['updraft_secret'] ) : '';
		$result = self::service_updraft_import( (string) wp_unslash( $_POST['updraft_set'] ?? '' ), array( 'source' => sanitize_key( wp_unslash( $_POST['updraft_source'] ?? '' ) ), 'secret' => $secret, 'via' => 'admin-post', 'network' => ! empty( $_POST['digitalisimo_backups_network'] ), 'spawn' => true ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, 'Importación de UpdraftPlus en cola (trabajo ' . $result['id'] . '): su avance está en «Trabajos». Al terminar, el respaldo aparece en la lista como «Importado».' );
	}
	/** «Eliminar» de la tarjeta (admin-post): sólo las piezas del almacén privado. */
	public static function updraft_delete_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_UPDRAFT_DELETE );
		$result = self::service_updraft_delete( (string) wp_unslash( $_POST['updraft_set'] ?? '' ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, 'Piezas de UpdraftPlus borradas del almacén privado: ' . $result['id'] . '.' );
	}
	/** Tarjeta «UpdraftPlus» de «Importar de Backuply o UpdraftPlus». $items: los de sources(), si ya se buscaron. */
	private static function updraft_panel( $network = false, $items = null ) {
		self::load_updraft_import();
		if ( ! is_array( $items ) ) {
			try {
				$items = Digitalisimo_Backups_Updraft_Import::sources();
			} catch ( Exception $e ) {
				$items = array();
			}
		}
		echo '<h3>UpdraftPlus</h3><p class="description">Busca en la carpeta de UpdraftPlus de este sitio (<code>wp-content/updraft</code>) y en el almacén privado. Importar convierte el conjunto, sin tocar el sitio, en un respaldo de Backups que entra en la lista como «Importado» y se restaura desde ahí con la restauración por pasos (copia previa, vuelta atrás, otra dirección) escribiendo RESTAURAR SIN FIRMA. Un conjunto de otro servidor se sube pieza a pieza en «Subir un respaldo» (su …-db.gz y sus ZIP) o se trae de un destino con <code>wp digitalisimo-backups updraft fetch</code>.</p>';
		if ( ! $items ) { echo '<p>No se detectaron respaldos de UpdraftPlus.</p>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>Conjunto</th><th>Origen</th><th>Piezas</th><th>Tamaño</th><th>Acción</th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$entities = array();
			foreach ( (array) $item['entities'] as $entity => $count ) $entities[] = $entity . ( $count > 1 ? ' × ' . $count : '' );
			echo '<tr><td>' . esc_html( $item['id'] ) . ( '' !== $item['date'] ? '<br><small>' . esc_html( $item['date'] ) . '</small>' : '' ) . '</td><td>' . esc_html( 'updraft' === $item['source'] ? 'Carpeta de UpdraftPlus' : 'Almacén privado' ) . '</td><td>' . esc_html( ( $item['database'] ? 'base de datos' . ( $item['encrypted'] ? ' (cifrada)' : '' ) : 'sin base de datos' ) . ( $entities ? ', ' . implode( ', ', $entities ) : '' ) ) . '</td><td>' . esc_html( self::bytes_label( (int) $item['size'] ) ) . '</td><td>';
			if ( '' !== $item['imported'] ) echo '<small>Importado como ' . esc_html( $item['imported'] ) . '</small><br>';
			if ( '' !== $item['problem'] ) echo '<small>' . esc_html( $item['problem'] ) . '</small><br>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( self::ACTION_UPDRAFT_IMPORT );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_UPDRAFT_IMPORT ) . '"><input type="hidden" name="updraft_set" value="' . esc_attr( $item['id'] ) . '"><input type="hidden" name="updraft_source" value="' . esc_attr( $item['source'] ) . '">' . self::return_field( 'tools', 'import' ) . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' );
			if ( $item['encrypted'] ) echo '<input type="password" name="updraft_secret" placeholder="Frase de cifrado de UpdraftPlus" autocomplete="off"> ';
			echo '<button class="button button-small"' . ( $item['importable'] ? '' : ' disabled' ) . '>' . ( '' !== $item['imported'] ? 'Importar de nuevo' : 'Importar' ) . '</button></form>';
			if ( 'store' === $item['source'] ) {
				echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'¿Borrar las piezas de este conjunto del almacén privado?\')">';
				wp_nonce_field( self::ACTION_UPDRAFT_DELETE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_UPDRAFT_DELETE ) . '"><input type="hidden" name="updraft_set" value="' . esc_attr( $item['id'] ) . '">' . self::return_field( 'tools', 'import' ) . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small button-link-delete">Eliminar</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	/**
	 * Registra en la lista de respaldos el ZIP convertido de una importación como «Importado» (`origin` = `imported` con
	 * su bloque `import`): una fila de fuera, como las subidas (is_uploaded_row()), así que ni la retención la borra ni la
	 * restauración la toma por de esta instalación (H23 y «Conservar mi acceso»); sin firma de este sitio sigue pidiendo
	 * RESTAURAR SIN FIRMA. Idempotente: si ya hay fila con ese archivo, la devuelve. null si no se pudo registrar.
	 */
	public static function remember_imported( $path, array $info ) {
		$name = wp_basename( (string) $path );
		try {
			$checksum = @hash_file( 'sha256', $path );
			if ( false === $checksum ) return null;
			return self::change_history( function( &$history ) use ( $name, $path, $checksum, $info ) {
				foreach ( $history as $entry ) if ( ( $entry['file'] ?? '' ) === $name ) return $entry;
				$row = array(
					'date' => self::uploaded_date( (string) ( $info['created_at'] ?? '' ) ),
					'file' => $name,
					'size' => (int) filesize( $path ),
					'sha256' => (string) $checksum,
					'destination' => 'local',
					'scope' => ! empty( $info['multisite'] ) ? 'network' : 'installation',
					'site_id' => (int) get_current_blog_id(),
					'policy' => 'imported',
					'backup_type' => 'full',
					'remote' => array(),
					'locked' => false,
				) + Digitalisimo_Backups_Security::describe( $path ) + array(
					'origin' => 'imported',
					'import' => array( 'format' => (string) ( $info['format'] ?? '' ), 'source' => (string) ( $info['source'] ?? '' ), 'from' => (string) ( $info['origin'] ?? '' ) ),
					'upload' => array( 'at' => current_time( 'mysql' ), 'via' => (string) ( $info['via'] ?? '' ), 'user_id' => (int) ( $info['user_id'] ?? 0 ), 'name' => (string) ( $info['source'] ?? '' ), 'home' => (string) ( $info['home'] ?? '' ), 'verified' => false, 'signature' => 'unsigned', 'manifest_sha256' => '', 'site_key' => false, 'same_site' => false, 'problems' => array() ),
				);
				// El respaldo de una red: sus sitios (de la cabecera de UpdraftPlus), para elegir cuál se restaura en un individual.
				$sites = array();
				foreach ( array_slice( (array) ( $info['sites'] ?? array() ), 0, 500, true ) as $id => $site ) if ( is_array( $site ) && isset( $site['domain'] ) ) $sites[ (int) $id ] = Digitalisimo_Backups_Site_Extract::label( (string) $site['domain'], (string) ( $site['path'] ?? '/' ) );
				if ( $sites && ! empty( $info['multisite'] ) ) { ksort( $sites ); $row['sites'] = $sites; }
				$notices = array();
				foreach ( (array) ( $info['notices'] ?? array() ) as $text ) $notices[] = array( 'notice' => 'import-' . md5( (string) $text ), 'text' => (string) $text );
				if ( $notices ) $row['notices'] = $notices;
				array_unshift( $history, $row );
				$history = self::history_cap( $history );
				return $row;
			} );
		} catch ( Throwable $e ) {
			error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
			return null;
		}
	}
	/** Una fila «Importado» de una importación (UpdraftPlus): no la de una sincronización de destinos, que no lleva `import`. */
	public static function is_imported_row( $row ) {
		return is_array( $row ) && 'imported' === (string) ( $row['origin'] ?? '' ) && is_array( $row['import'] ?? null );
	}
	/**
	 * Una pieza de UpdraftPlus subida entra en `updraft/` del almacén con su nombre exacto (sus piezas se agrupan por
	 * nombre). La misma pieza subida otra vez la sustituye, salvo que su conjunto se esté importando.
	 */
	private static function upload_updraft_done( array $state, $path ) {
		self::load_updraft_import();
		$name = Digitalisimo_Backups_Updraft_Import::upload_name( (string) $state['name'] );
		$piece = Digitalisimo_Backups_Updraft_Import::piece( $name );
		if ( ! $piece ) throw new Exception( 'El nombre de la pieza de UpdraftPlus no es válido.' );
		foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Import_Job::is_updraft( $job ) && 'store' === ( $job['spec']['source']['origin'] ?? '' ) && $piece['set'] === ( $job['spec']['source']['set'] ?? '' ) ) throw new Exception( 'Ese conjunto de UpdraftPlus se está importando (trabajo ' . $job['id'] . '): espera a que termine para subir otra vez una pieza suya.' );
		$store = Digitalisimo_Backups_Updraft_Import::store_directory( true );
		self::upload_step( 'register', 'Guardando la pieza en el almacén privado para importar su conjunto…' );
		Digitalisimo_Backups_Job_Store::with_lock( function() use ( $path, $store, $name ) {
			if ( is_link( $store . '/' . $name ) ) throw new Exception( 'Una pieza del almacén es un enlace simbólico.' );
			if ( ! @rename( $path, $store . '/' . $name ) ) throw new Exception( 'No se pudo mover la pieza de UpdraftPlus subida al almacén privado.' );
			@chmod( $store . '/' . $name, 0600 );
		} );
		$pieces = Digitalisimo_Backups_Updraft_Import::set_pieces( 'store', $piece['set'] );
		$item = Digitalisimo_Backups_Updraft_Import::describe( 'store', $piece['set'], $pieces );
		$result = array( 'file' => $name, 'size' => (int) $state['size'], 'registered' => false, 'container' => 'updraft', 'updraft' => array( 'source' => 'store', 'set' => $piece['set'], 'pieces' => $item['pieces'], 'database' => $item['database'], 'encrypted' => $item['encrypted'] ), 'verified' => false, 'trust' => 'untrusted', 'confirmation' => 'RESTAURAR SIN FIRMA', 'needs_secret' => false, 'compatible' => false, 'problems' => array( 'Es una pieza de un respaldo de UpdraftPlus (' . $piece['set'] . ', ' . count( $item['pieces'] ) . ' en el almacén). Sube las demás piezas del conjunto (su …-db.gz y sus ZIP) e impórtalo en Herramientas → «Importar de Backuply o UpdraftPlus» (o con wp digitalisimo-backups updraft import ' . $piece['set'] . ').' ) );
		$state['phase'] = 'done';
		$state['result'] = $result;
		$state['updated_at'] = time();
		Digitalisimo_Backups_Uploads::write( $state );
		return $result;
	}
	/** El contenido de una pieza subida empieza como debe: un ZIP (PK) o un gzip; una base cifrada, cualquier cosa. */
	private static function upload_updraft_valid( $name, $path ) {
		self::load_updraft_import();
		$piece = Digitalisimo_Backups_Updraft_Import::piece( Digitalisimo_Backups_Updraft_Import::upload_name( (string) $name ) );
		if ( ! $piece ) return false;
		if ( $piece['crypt'] ) return (int) @filesize( $path ) > 0;
		$magic = (string) @file_get_contents( $path, false, null, 0, 4 );
		return 'zip' === $piece['ext'] ? in_array( $magic, array( "PK\x03\x04", "PK\x05\x06" ), true ) : 0 === strpos( $magic, "\x1f\x8b" );
	}
}
