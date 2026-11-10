<?php
defined( 'ABSPATH' ) || exit;

/**
 * Destinos remotos en Digitalisimo_Backups: resolución de conexiones (principal de cada tipo y adicionales con
 * nombre), transferencia por el contrato, descarga y borrado remotos, «Traer al almacén», pantalla «Servidores»
 * (campos guiados, «Probar conexión», conexiones adicionales) y OAuth (Google Drive, OneDrive, Dropbox con PKCE y
 * pCloud). Los secretos sólo pasan por el almacén cifrado de S4 y nunca vuelven al navegador.
 */
trait Digitalisimo_Backups_Destinations_Screen {

	/* ------------------------------------------------------------------ Resolución */

	/**
	 * Configuración completa de una conexión con sus secretos descifrados en memoria: la principal de un tipo usa los
	 * ajustes de siempre; una adicional, su fila de `digitalisimo_backups_connections` y sus campos del almacén.
	 */
	public static function connection_settings( $id ) {
		$settings = self::settings();
		if ( ! Digitalisimo_Backups_Destinations::is_connection_id( $id ) ) return $settings;
		$connections = Digitalisimo_Backups_Destinations::connections();
		if ( ! isset( $connections[ $id ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La conexión de destino ya no existe.' );
		$class = Digitalisimo_Backups_Destinations::class_of( $connections[ $id ]['type'] );
		$out = $connections[ $id ]['settings'] + $class::defaults();
		$out[ $class::PREFIX . 'name' ] = $connections[ $id ]['name'];
		$secrets = self::credentials_here() ? Digitalisimo_Backups_Credentials::read( self::credential_settings() ) : array();
		foreach ( $class::secrets() as $field ) $out[ $field ] = (string) ( $secrets[ $id . '/' . $field ] ?? '' );
		return $out;
	}
	/**
	 * Los datos de conexión que atan cada secreto (el dato adicional del AEAD): los ajustes de las conexiones principales
	 * y, con la clave `<id>/<dato>`, los de cada conexión adicional. $saved: ajustes guardados (por defecto, los actuales).
	 */
	private static function credential_settings( $saved = null ) {
		$out = wp_parse_args( is_array( $saved ) ? $saved : self::saved_settings(), self::defaults() );
		foreach ( Digitalisimo_Backups_Destinations::connections() as $id => $connection ) $out += self::connection_context( $id, $connection['settings'] + Digitalisimo_Backups_Destinations::class_of( $connection['type'] )::defaults() );
		return $out;
	}
	private static function connection_context( $id, array $values ) {
		$out = array();
		foreach ( $values as $key => $value ) if ( ! in_array( $key, Digitalisimo_Backups_Credentials::FIELDS, true ) ) $out[ $id . '/' . $key ] = $value;
		return $out;
	}
	/** Destino del contrato para una conexión; con un trabajo, su estado reanudable vive en el área de ese trabajo. */
	public static function destination( $id, $settings = null, $job_id = '', $state_path = '', $timeout = 0 ) {
		$type = Digitalisimo_Backups_Destinations::type_of( (string) $id );
		if ( '' === $type ) throw new Digitalisimo_Backups_Permanent_Error( 'El destino ' . (string) $id . ' no existe.' );
		$class = Digitalisimo_Backups_Destinations::class_of( $type );
		$context = array(
			'temp_dir' => function() { return self::local_directory(); },
			'progress' => function( $percent, $message ) { self::manual_progress( $percent, 'transferencia', $message ); },
			'connection' => (string) $id,
		);
		if ( '' !== (string) $job_id ) $context['state'] = new Digitalisimo_Backups_Transfer_State( Digitalisimo_Backups_Job_Store::directory( $job_id ) . '/' . Digitalisimo_Backups_Job_Store::STAGING . '/transfer.json' );
		if ( '' !== (string) $state_path ) $context['state'] = new Digitalisimo_Backups_Transfer_State( $state_path );
		if ( $timeout > 0 ) $context['timeout'] = (int) $timeout;
		return new $class( null === $settings ? self::connection_settings( $id ) : (array) $settings, $context );
	}
	/** Mensaje de un destino cuyas credenciales no se abren ('' si no es el caso). */
	public static function destination_problem( $id ) { return self::credentials_problem( (string) $id ); }
	/** Destinos que se pueden elegir en automatizaciones y como predeterminado: local, cada tipo y cada conexión. */
	private static function destination_choices() {
		$out = array( 'local' => self::destination_label( 'local' ) );
		foreach ( Digitalisimo_Backups_Destinations::types() as $type ) $out[ $type ] = self::destination_label( $type );
		foreach ( Digitalisimo_Backups_Destinations::connections() as $id => $connection ) $out[ $id ] = $connection['name'] . ' (' . Digitalisimo_Backups_Destinations::label( $connection['type'] ) . ')';
		return $out;
	}
	/** Sólo ofrece destinos remotos que ya cuentan con una conexión válida. */
	private static function manual_destinations( $settings ) {
		$destinations = array( 'local' => self::destination_label( 'local' ) );
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) if ( $class::ready( (array) $settings ) ) $destinations[ $type ] = Digitalisimo_Backups_Destinations::name_of( $type, (array) $settings );
		foreach ( Digitalisimo_Backups_Destinations::connections() as $id => $connection ) {
			try { $ready = Digitalisimo_Backups_Destinations::class_of( $connection['type'] )::ready( self::connection_settings( $id ) ); } catch ( Exception $e ) { $ready = false; }
			if ( $ready ) $destinations[ $id ] = $connection['name'];
		}
		return $destinations;
	}
	private static function destination_label( $destination ) {
		if ( 'local' === $destination ) return 'Hosting / local';
		$type = Digitalisimo_Backups_Destinations::type_of( (string) $destination );
		if ( '' === $type ) return 'Local';
		return Digitalisimo_Backups_Destinations::is_connection_id( $destination ) ? Digitalisimo_Backups_Destinations::name_of( $destination ) : Digitalisimo_Backups_Destinations::label( $type );
	}

	/* ------------------------------------------------------------------ Transferencia */

	/** @internal Fase «transfer»: credenciales leídas ahora, nunca guardadas en el trabajo; estado reanudable en su área. */
	public static function job_transfer( $file, $destination, $job_id = '' ) {
		$settings = self::settings();
		// Credenciales cifradas con otras claves de wp-config.php: repetir no lo arregla, así que el fallo es permanente y dice qué hacer.
		$problem = self::credentials_problem( $destination );
		if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem . ' El respaldo local verificado se conserva.' );
		if ( ! isset( self::manual_destinations( $settings )[ $destination ] ) || 'local' === $destination ) throw new Digitalisimo_Backups_Permanent_Error( 'El destino ' . self::destination_label( $destination ) . ' ya no tiene una conexión completa; el respaldo local verificado se conserva.' );
		$remote = self::destination( $destination, Digitalisimo_Backups_Destinations::is_connection_id( $destination ) ? null : $settings, $job_id )->put( $file );
		if ( ! is_array( $remote ) || empty( $remote['provider'] ) ) throw new Exception( 'El destino remoto no confirmó la copia.' );
		return $remote;
	}
	/**
	 * @internal Un trabajo que falla o se cancela durante «transfer» (lo llama el motor con el cerrojo de su registro
	 * tomado): sólo mueve el estado de su subida a `jobs/<uuid>/abandon.json`, sin ninguna llamada de red. El siguiente
	 * tick o el vigilante lo retiran del destino fuera de ese cerrojo (job_transfer_abandon_pending). Nunca lanza.
	 */
	public static function job_transfer_queue_abandon( array $job ) {
		try {
			$destination = (string) ( $job['spec']['destination'] ?? 'local' );
			if ( 'local' === $destination || empty( $job['id'] ) ) return;
			$dir = Digitalisimo_Backups_Job_Store::directory( $job['id'] );
			$from = $dir . '/' . Digitalisimo_Backups_Job_Store::STAGING . '/transfer.json';
			if ( ! is_file( $from ) || is_link( $from ) ) return;
			$state = json_decode( (string) @file_get_contents( $from ), true );
			if ( ! is_array( $state ) ) return;
			$state['destination'] = $destination;
			( new Digitalisimo_Backups_Transfer_State( $dir . '/' . self::ABANDON ) )->set( $state );
			@unlink( $from );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: no se pudo apartar la subida interrumpida: ' . Digitalisimo_Backups_Http::redact( $e->getMessage() ) );
		}
	}
	/** @internal Abandonos pendientes (rutas de `abandon.json`). */
	public static function job_transfer_pending_abandons() {
		try { return glob( Digitalisimo_Backups_Job_Store::root() . '/*/' . self::ABANDON ) ?: array(); } catch ( Throwable $e ) { return array(); }
	}
	/**
	 * @internal Retira del destino lo que dejaron las subidas interrumpidas (multipart de S3, temporales de SFTP, FTP,
	 * WebDAV o Dropbox), fuera del cerrojo del registro de trabajos y con tiempos de espera cortos. Cada abandono se
	 * intenta una sola vez; nunca lanza. Devuelve cuántos se intentaron.
	 */
	public static function job_transfer_abandon_pending( $limit = 5 ) {
		$done = 0;
		foreach ( array_slice( self::job_transfer_pending_abandons(), 0, $limit ) as $path ) {
			$claim = $path . '.running';
			if ( ! @rename( $path, $claim ) ) continue;
			$done++;
			try {
				$state = json_decode( (string) @file_get_contents( $claim ), true );
				$destination = is_array( $state ) ? (string) ( $state['destination'] ?? '' ) : '';
				if ( '' === Digitalisimo_Backups_Destinations::type_of( $destination ) || ! Digitalisimo_Backups_Destinations::exists( $destination ) ) continue;
				Digitalisimo_Backups_Http::$cap_timeout = self::ABANDON_TIMEOUT;
				self::destination( $destination, null, '', $claim, self::ABANDON_TIMEOUT )->abandon();
			} catch ( Throwable $e ) {
				if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: no se pudo retirar la subida interrumpida: ' . Digitalisimo_Backups_Http::redact( $e->getMessage() ) );
			} finally {
				Digitalisimo_Backups_Http::$cap_timeout = null;
				@unlink( $claim );
			}
		}
		return $done;
	}
	private static function transfer( $file, $s ) { return self::destination( $s['destination'], $s )->put( $file ); }
	private static function sftp( $file, $s ) { return self::destination( 'sftp', $s )->put( $file ); }
	private static function sftp_connection( $s ) { return self::destination( 'sftp', $s )->connection(); }
	private static function token( $url, $s, $prefix ) { return Digitalisimo_Backups_Destination_Cloud_Drive::refresh( $url, $s, $prefix ); }
	private static function google( $file, $s ) { return self::destination( 'google', $s )->put( $file ); }
	private static function onedrive( $file, $s ) { return self::destination( 'onedrive', $s )->put( $file ); }
	private static function keep_verified_cloud_copy( $provider, $id, $source, $token ) { self::destination( $provider, array() )->keep_verified( $id, $source, $token ); }
	private static function verify_cloud_copy( $provider, $id, $source, $token ) { self::destination( $provider, array() )->verify_download( $id, $source, $token ); }
	private static function resumable_upload( $url, $file, $headers = array(), $chunk_size = 8388608 ) { return self::destination( 'google', array() )->resumable_upload( $url, $file, $headers, $chunk_size ); }

	/* ------------------------------------------------------------------ Copias remotas del historial */

	/** Conexión de un recibo: la que guardó la transferencia o, en filas anteriores, el tipo. */
	private static function remote_connection( array $remote ) {
		$connection = (string) ( $remote['connection'] ?? '' );
		return '' !== $connection && '' !== Digitalisimo_Backups_Destinations::type_of( $connection ) ? $connection : (string) ( $remote['provider'] ?? '' );
	}
	/** Una conexión adicional que se eliminó en «Servidores» después de copiar en ella el respaldo de esta fila. */
	private static function remote_connection_gone( array $remote ) {
		$id = self::remote_connection( $remote );
		return Digitalisimo_Backups_Destinations::is_connection_id( $id ) && ! isset( Digitalisimo_Backups_Destinations::connections()[ $id ] );
	}
	private static function gone_text( array $remote ) {
		return 'La conexión de ' . Digitalisimo_Backups_Destinations::label( (string) ( $remote['provider'] ?? '' ) ) . ' donde se copió este respaldo se eliminó en «Servidores». La copia remota sigue en el servicio: descárgala desde allí. «Eliminar» sólo quita su fila del historial.';
	}
	/** Filas del historial cuya copia remota vive en la conexión $id. */
	private static function history_rows_in( $id ) {
		return count( array_filter( (array) get_option( self::HISTORY, array() ), function( $row ) use ( $id ) { return is_array( $row ) && (string) ( ( (array) ( $row['remote'] ?? array() ) )['connection'] ?? '' ) === $id; } ) );
	}
	/** Descarga la copia remota de una fila a $target en streaming y comprueba tamaño y SHA-256 con los del historial. */
	private static function fetch_remote_copy( array $item, $target ) {
		$remote = (array) ( $item['remote'] ?? array() );
		$id = self::remote_connection( $remote );
		if ( '' === $id ) throw new Exception( 'Este respaldo remoto fue creado antes de guardar su identificador. Conserva la copia remota o genera un respaldo nuevo.' );
		if ( self::remote_connection_gone( $remote ) ) throw new Digitalisimo_Backups_Permanent_Error( self::gone_text( $remote ) );
		$problem = self::credentials_problem( $id );
		if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		self::destination( $id )->get( $remote, $target );
		clearstatcache( true, $target );
		$expected = (string) ( $item['sha256'] ?? ( $remote['sha256'] ?? '' ) );
		$hash = @hash_file( 'sha256', $target );
		if ( false === $hash || ( '' !== $expected && ! hash_equals( $expected, $hash ) ) || ( isset( $item['size'] ) && (int) $item['size'] !== (int) @filesize( $target ) ) ) throw new Exception( 'La copia descargada de ' . self::destination_label( $id ) . ' no coincide con el SHA-256 registrado del respaldo; no se usa.' );
	}
	/** Descarga al navegador: primero a un temporal privado verificado, después por bloques (memoria constante). */
	private static function download_remote( $item ) {
		$remote = (array) ( $item['remote'] ?? array() );
		if ( empty( $remote['provider'] ) ) wp_die( 'Este respaldo remoto fue creado antes de guardar su identificador. Conserva la copia remota o genera un respaldo nuevo.' );
		if ( self::remote_connection_gone( $remote ) ) wp_die( esc_html( self::gone_text( $remote ) ) );
		$temp = @tempnam( self::local_directory(), '.download-' );
		if ( false === $temp ) wp_die( 'No se pudo preparar la descarga del respaldo remoto.' );
		// Si PHP termina sin pasar por el finally (el navegador corta, un error fatal), el temporal se borra al cerrar (C5).
		register_shutdown_function( function() use ( $temp ) { if ( is_file( $temp ) ) @unlink( $temp ); } );
		try {
			try {
				self::fetch_remote_copy( (array) $item, $temp );
			} catch ( Exception $e ) {
				// wp_die() termina con exit y no ejecuta el finally: el temporal (sin verificar) se borra antes.
				@unlink( $temp );
				wp_die( esc_html( 'No se pudo descargar el respaldo remoto. ' . Digitalisimo_Backups_Http::redact( $e->getMessage() ) ) );
			}
			// Por bloques y sin el búfer de salida de PHP, como «Descargar» de una copia local (H16).
			self::send_file( $temp, (string) $item['file'] );
		} finally {
			@unlink( $temp );
		}
	}
	/**
	 * Borra la copia remota de una fila. Si su conexión ya se eliminó no hay a dónde llamar: no toca nada remoto y
	 * devuelve el aviso para quitar sólo la fila. Devuelve '' cuando borró la copia remota.
	 */
	private static function delete_remote( $item ) {
		$remote = (array) ( $item['remote'] ?? array() );
		if ( empty( $remote['provider'] ) ) throw new Exception( 'Este respaldo remoto no tiene identificador para eliminarse desde WordPress.' );
		if ( self::remote_connection_gone( $remote ) ) return 'Se quitó la fila del historial. Su conexión de ' . Digitalisimo_Backups_Destinations::label( (string) $remote['provider'] ) . ' ya no existe, así que la copia remota no se tocó: bórrala desde el servicio si ya no la necesitas.';
		$id = self::remote_connection( $remote );
		$problem = self::credentials_problem( $id );
		if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		self::destination( $id )->delete( $remote );
		return '';
	}
	/**
	 * Trae al almacén privado la copia remota de un respaldo del historial (verificada contra su SHA-256) para
	 * restaurarlo o descargarlo sin volver al destino. Si ya está en el almacén no hace nada.
	 */
	public static function service_fetch( $file, array $args = array() ) {
		// Escribe en el almacén privado de toda la instalación: en una red pide el alcance de red, como crear o restaurar.
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$file = sanitize_file_name( (string) $file );
		$item = '' !== $file ? self::history_item( $file ) : array();
		if ( ! $item ) return self::service_error( 'not_found', 'El respaldo no está registrado.', 404 );
		if ( empty( ( (array) ( $item['remote'] ?? array() ) )['provider'] ) ) return self::service_error( 'not_remote', 'Este respaldo no tiene una copia remota registrada.', 400 );
		if ( self::remote_connection_gone( (array) $item['remote'] ) ) return self::service_error( 'connection_gone', self::gone_text( (array) $item['remote'] ), 410 );
		// Con el cerrojo de operaciones: ni un respaldo, ni una restauración, ni otra descarga tocan el almacén a la vez.
		$lock = self::service_lock();
		if ( is_wp_error( $lock ) ) return $lock;
		try {
			if ( '' !== self::local_path( $file ) ) return array( 'file' => $file, 'fetched' => false, 'message' => 'El respaldo ya está en el almacén privado.' );
			$target = trailingslashit( self::local_directory() ) . $file;
			$temp = $target . '.part-' . bin2hex( random_bytes( 6 ) );
			try {
				self::fetch_remote_copy( $item, $temp );
				if ( file_exists( $target ) || ! @rename( $temp, $target ) ) throw new Exception( 'No se pudo colocar la copia verificada en el almacén privado.' );
				@chmod( $target, 0600 );
			} finally {
				@unlink( $temp );
			}
		} catch ( Exception $e ) {
			return self::service_error( 'fetch_failed', Digitalisimo_Backups_Http::redact( $e->getMessage() ), 502 );
		} finally {
			self::release_operation_lock( $lock );
		}
		return array( 'file' => $file, 'fetched' => true, 'message' => 'Copia de ' . self::destination_label( self::remote_connection( (array) $item['remote'] ) ) . ' descargada al almacén privado y verificada por SHA-256.' );
	}
	/** «Traer al almacén» en el historial: sólo para una copia remota cuyo archivo ya no está en el almacén privado. */
	private static function fetch_button( $item ) {
		$file = (string) ( $item['file'] ?? '' );
		if ( '' === $file || empty( ( (array) ( $item['remote'] ?? array() ) )['provider'] ) ) return;
		try { if ( '' !== self::local_path( $file ) ) return; } catch ( Exception $e ) { return; }
		echo ' <form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_FETCH );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_FETCH ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '">' . ( self::network_context() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small" title="Descarga la copia remota al almacén privado y la verifica por SHA-256 para poder restaurarla">Traer al almacén</button></form>';
	}
	public static function fetch_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_FETCH );
		@set_time_limit( 0 );
		$result = self::service_fetch( wp_unslash( $_POST['file'] ?? '' ), array( 'network' => self::network_context() ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, $result['message'] );
	}

	/* ------------------------------------------------------------------ Pantalla «Servidores» */

	private static function servers_panel( $s, array $locked = array() ) {
		$editing = sanitize_key( $_GET['connection'] ?? '' );
		echo '<section class="digitalisimo-backups-card digitalisimo-backups-destinations"><h2>Servidores y nubes</h2><p>Configura las conexiones disponibles para respaldos. En Backups sólo aparecerán Local y las conexiones que ya estén listas. «Probar conexión» sube un archivo pequeño, lo verifica, lo descarga, lo busca al listar y lo borra.</p>';
		self::connection_table( $s, $locked );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-backups-connection-form">';
		wp_nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">' . ( self::network_context() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<table class="form-table"><tr><th><label for="destination">Destino predeterminado</label></th><td><select name="digitalisimo_backups[destination]" id="destination">';
		foreach ( self::destination_choices() as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $s['destination'], $value, false ) . '>' . esc_html( 'local' === $value ? 'Hosting / almacenamiento local' : $label ) . '</option>';
		echo '</select></td></tr>';
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) echo '<tr data-server="' . esc_attr( $type ) . '"><th>Nombre ' . esc_html( $class::label() ) . '</th><td><input name="digitalisimo_backups[' . esc_attr( $class::PREFIX ) . 'name]" value="' . esc_attr( (string) ( $s[ $class::PREFIX . 'name' ] ?? '' ) ) . '"></td></tr>';
		echo '</table>';
		self::connection_fields( $s, $locked );
		submit_button( 'Guardar conexiones' );
		echo '</form></section>';
		self::connection_form( $editing );
	}
	/** Tabla de conexiones: principal de cada tipo con algo configurado y las adicionales, con su estado. */
	private static function connection_table( $s, array $locked ) {
		$rows = array();
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) {
			$filled = false;
			foreach ( $class::fields() as $key => $field ) if ( '' !== (string) ( $s[ $key ] ?? '' ) && (string) ( $field['default'] ?? '' ) !== (string) $s[ $key ] ) $filled = true;
			if ( $filled || array_intersect( $locked, Digitalisimo_Backups_Credentials::fields_of( $type ) ) ) $rows[ $type ] = array( Digitalisimo_Backups_Destinations::name_of( $type, $s ), $type, $class::ready( $s ) );
		}
		foreach ( Digitalisimo_Backups_Destinations::connections() as $id => $connection ) {
			try { $ready = Digitalisimo_Backups_Destinations::class_of( $connection['type'] )::ready( self::connection_settings( $id ) ); } catch ( Exception $e ) { $ready = false; }
			$rows[ $id ] = array( $connection['name'], $connection['type'], $ready );
		}
		if ( ! $rows ) { echo '<p>Aún no hay conexiones remotas.</p>'; return; }
		echo '<table class="widefat striped digitalisimo-backups-connections"><thead><tr><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( $rows as $id => $row ) {
			list( $name, $type, $ready ) = $row;
			$problem = self::credentials_problem( $id );
			$state = '' !== $problem ? 'Vuelve a escribir sus credenciales' : ( $ready ? 'Lista' : 'Incompleta' );
			echo '<tr><td>' . esc_html( $name ) . ( Digitalisimo_Backups_Destinations::is_connection_id( $id ) ? '' : ' <span class="description">(principal)</span>' ) . '</td><td>' . esc_html( Digitalisimo_Backups_Destinations::label( $type ) ) . '</td><td><span class="digitalisimo-secret ' . ( $ready && '' === $problem ? 'is-set' : 'is-empty' ) . '">' . esc_html( $state ) . '</span></td><td>';
			if ( Digitalisimo_Backups_Destinations::is_connection_id( $id ) ) {
				echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'tab' => 'servers', 'connection' => $id ), self::admin_url() ) . '#digitalisimo-backups-connection' ) . '">Editar</a> <form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar esta conexión? Los respaldos ya copiados en ella no se borran.\')">';
				wp_nonce_field( self::ACTION_DELETE_CONNECTION );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE_CONNECTION ) . '"><input type="hidden" name="connection_id" value="' . esc_attr( $id ) . '">' . ( self::network_context() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small button-link-delete">Eliminar</button></form>';
			} else {
				echo '<span class="description">Se edita abajo, en «Conexiones externas».</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	/** Campos de la conexión principal de cada tipo (un tipo visible a la vez). */
	private static function connection_fields( $s, array $locked = array() ) {
		echo '<h2>Conexiones externas</h2><p>Elige un tipo para ver únicamente sus campos. Los secretos ya guardados no se muestran; deja el campo vacío para conservarlos.</p><p><label for="digitalisimo-server-type"><strong>Tipo de servidor</strong><br><select id="digitalisimo-server-type" class="digitalisimo-backups-type">';
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) echo '<option value="' . esc_attr( $type ) . '">' . esc_html( $class::label() ) . '</option>';
		echo '</select></label></p><table class="form-table">';
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) self::type_fields( $type, $s, $locked, 'digitalisimo_backups', '', $type );
		echo '</table>';
	}
	/**
	 * Filas de un tipo. $name: nombre del arreglo del formulario; $id_prefix: prefijo de los id (el formulario de una
	 * conexión adicional los repite); $connection: la conexión que se prueba o se conecta por OAuth.
	 */
	private static function type_fields( $type, $s, array $locked, $name, $id_prefix, $connection ) {
		$class = Digitalisimo_Backups_Destinations::class_of( $type );
		echo '<tr data-server="' . esc_attr( $type ) . '"><th colspan="2"><h3>' . esc_html( $class::label() ) . '</h3></th></tr>';
		foreach ( $class::fields() as $key => $field ) self::field( $key, $field['label'], $s, $field['type'] ?? 'text', $locked, $field, $name, $id_prefix, $type, $connection );
		if ( 'digitalisimo_backups' === $name ) self::forget_row( $type, $s, $locked );
		$help = self::type_help( $type );
		if ( $help ) echo '<tr data-server="' . esc_attr( $type ) . '"><th>Cómo obtener ' . esc_html( $class::label() ) . '</th><td><ol><li>' . implode( '</li><li>', $help ) . '</li></ol></td></tr>';
		echo '<tr data-server="' . esc_attr( $type ) . '"><th></th><td><p class="digitalisimo-backups-destination-actions">';
		if ( $class::oauth() && 'digitalisimo_backups_connection' === $name && ! Digitalisimo_Backups_Destinations::is_connection_id( $connection ) ) {
			// Una conexión adicional aún sin crear no tiene identificador: «Conectar» conectaría la principal.
			echo '<span class="description">Crea la conexión; después, en su edición, usa «Conectar ' . esc_html( $class::label() ) . '».</span> ';
		} elseif ( $class::oauth() ) {
			$saved = '' !== (string) ( $s[ $class::PREFIX . ( 'pcloud' === $type ? 'access_token' : 'refresh_token' ) ] ?? '' );
			echo '<a class="button button-secondary" href="' . esc_url( self::oauth_url( $type, $connection ) ) . '">Conectar ' . esc_html( $class::label() ) . '</a> <span class="digitalisimo-secret ' . ( $saved ? 'is-set' : 'is-empty' ) . '">' . ( $saved ? 'Conectado' : 'Sin conectar' ) . '</span> ';
		}
		echo '<button type="submit" class="button digitalisimo-backups-test" name="digitalisimo_backups_test" value="' . esc_attr( $connection ) . '" formaction="' . esc_url( add_query_arg( array( 'action' => self::ACTION_TEST_DESTINATION ), admin_url( 'admin-post.php' ) ) ) . '">Probar conexión</button> <span class="digitalisimo-backups-test-result" role="status" aria-live="polite"></span></p></td></tr>';
	}
	/**
	 * «Olvidar» las credenciales guardadas de un destino que ya no se usa, también las que ya no se abren (si no, su aviso
	 * quedaría para siempre): una casilla del mismo formulario, con su nonce y su capability. Sólo si hay algo guardado.
	 */
	private static function forget_row( $destination, $s, array $locked ) {
		$fields = Digitalisimo_Backups_Credentials::DESTINATIONS[ $destination ] ?? array();
		if ( ! array_filter( $fields, function( $field ) use ( $s, $locked ) { return ! empty( $s[ $field ] ) || in_array( $field, $locked, true ); } ) ) return;
		echo '<tr data-server="' . esc_attr( $destination ) . '"><th></th><td><label><input type="checkbox" name="digitalisimo_backups[forget][' . esc_attr( $destination ) . ']" value="1"> Olvidar las credenciales guardadas de ' . esc_html( self::destination_label( $destination ) ) . '</label><p class="description">Al guardar se retiran del sitio, también las que ya no se pueden abrir. Para un destino que ya no usas; lo que escribas ahora en sus campos sí se guarda.</p></td></tr>';
	}
	/** Pasos para crear las credenciales de cada proveedor; los de OAuth incluyen la URL de redirección exacta. */
	private static function type_help( $type ) {
		$callback = '<code>' . esc_html( self::oauth_callback_url() ) . '</code>';
		switch ( $type ) {
			case 'google':
				return array( 'Abre <strong>Google Cloud Console</strong> y crea o elige un proyecto.', 'Activa <strong>Google Drive API</strong> en APIs y servicios.', 'En Credenciales crea un cliente OAuth de tipo <strong>Aplicación web</strong>.', 'Agrega como URI de redirección autorizada: ' . $callback, 'Copia Client ID y Client secret aquí, guarda y usa «Conectar Google Drive».' );
			case 'onedrive':
				return array( 'Abre <strong>Microsoft Entra</strong> → App registrations → New registration.', 'En Authentication agrega esta URL: ' . $callback, 'Copia Client ID y Client secret, guarda y conecta la sesión.' );
			case 'dropbox':
				return array( 'En <strong>dropbox.com/developers/apps</strong> crea una app con acceso «App folder» (recomendado) o «Full Dropbox».', 'En Permissions marca <code>files.metadata.read</code>, <code>files.content.write</code> y <code>files.content.read</code>.', 'En Settings → OAuth 2, agrega como Redirect URI: ' . $callback . ' y activa «Allow public clients» (PKCE): no hace falta el App secret.', 'Copia el App key aquí, guarda y usa «Conectar Dropbox».' );
			case 'pcloud':
				return array( 'En <strong>docs.pcloud.com → My Apps</strong> crea una app con permiso de escritura.', 'Agrega como Redirect URI: ' . $callback, 'Copia Client ID y Client secret aquí, guarda y usa «Conectar pCloud». La región (EE. UU. o Europa) la indica pCloud al conectar.' );
			default:
				$class = Digitalisimo_Backups_Destinations::class_of( $type );
				return array_map( 'esc_html', $class::help() );
		}
	}
	/**
	 * Un campo guiado. Un secreto guardado nunca vuelve al navegador: el campo va vacío y su estado se anuncia en el
	 * placeholder. Mantiene el marcado de 1.0.60 para los campos de entonces.
	 */
	private static function field( $key, $label, $s, $type = 'text', array $locked = array(), array $spec = array(), $name = 'digitalisimo_backups', $id_prefix = '', $server = '', $connection = '' ) {
		$secret = ! empty( $spec['secret'] ) || in_array( $key, self::secret_fields(), true );
		if ( '' === $server ) $server = (string) Digitalisimo_Backups_Destinations::type_of( strstr( $key, '_', true ) ?: $key );
		$locked_key = '' !== $connection ? Digitalisimo_Backups_Destinations::secret_key( $connection, $key ) : $key;
		$placeholder = (string) ( $spec['placeholder'] ?? '' );
		if ( $secret && ! empty( $s[ $key ] ) ) $placeholder = 'Guardado · escribe sólo para reemplazar';
		elseif ( $secret && in_array( $locked_key, $locked, true ) ) $placeholder = 'Guardado con otras claves de wp-config.php · vuelve a escribirlo';
		$id = $id_prefix . $key;
		$field_name = $name . '[' . $key . ']';
		$value = $secret ? '' : (string) ( $s[ $key ] ?? '' );
		$show = isset( $spec['show'] ) ? ' data-show="' . esc_attr( $spec['show'] ) . '"' : '';
		echo '<tr data-server="' . esc_attr( $server ) . '"' . $show . '><th><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '">';
				foreach ( (array) ( $spec['options'] ?? array() ) as $option => $text ) {
					if ( is_array( $text ) ) {
						echo '<optgroup label="' . esc_attr( $option ) . '" data-group="' . esc_attr( $option ) . '">';
						foreach ( $text as $inner => $inner_text ) echo '<option value="' . esc_attr( $inner ) . '"' . selected( $value, (string) $inner, false ) . '>' . esc_html( $inner_text ) . '</option>';
						echo '</optgroup>';
						continue;
					}
					echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, (string) $option, false ) . '>' . esc_html( $text ) . '</option>';
				}
				echo '</select>';
				break;
			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '" value="1"' . checked( '1', $value, false ) . '> ' . esc_html( (string) ( $spec['text'] ?? $label ) ) . '</label>';
				break;
			case 'textarea':
				echo '<textarea class="large-text code" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '"' . ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . ( $secret ? ' autocomplete="off" spellcheck="false"' : '' ) . '>' . esc_html( $value ) . '</textarea>';
				break;
			default:
				$input = 'password' === $type ? 'password' : ( 'number' === $type ? 'number' : 'text' );
				$extra = 'number' === $type ? ' min="' . (int) ( $spec['min'] ?? 0 ) . '" max="' . (int) ( $spec['max'] ?? 65535 ) . '"' : '';
				if ( ! empty( $spec['pattern'] ) ) $extra .= ' pattern="' . esc_attr( $spec['pattern'] ) . '"';
				if ( ! empty( $spec['readonly'] ) ) $extra .= ' readonly';
				echo '<input class="regular-text" type="' . esc_attr( $input ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '"' . ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . ( $secret ? ' autocomplete="new-password"' : '' ) . $extra . '>';
		}
		if ( ! empty( $spec['help'] ) ) echo '<p class="description">' . esc_html( $spec['help'] ) . '</p>';
		if ( ! empty( $spec['warning'] ) ) echo '<p class="description digitalisimo-backups-warning">' . esc_html( $spec['warning'] ) . '</p>';
		echo '</td></tr>';
	}
	/** Formulario de una conexión adicional: crear (sin $editing) o editar una existente. */
	private static function connection_form( $editing ) {
		$connections = Digitalisimo_Backups_Destinations::connections();
		$current = isset( $connections[ $editing ] ) ? $connections[ $editing ] : null;
		$settings = $current ? self::connection_settings( $editing ) : array();
		$locked = $current ? Digitalisimo_Backups_Credentials::locked( self::credential_settings() ) : array();
		echo '<section class="digitalisimo-backups-card" id="digitalisimo-backups-connection"><h2>' . ( $current ? 'Editar «' . esc_html( $current['name'] ) . '»' : 'Añadir otra conexión' ) . '</h2><p>' . ( $current ? 'Los secretos ya guardados no se muestran; deja el campo vacío para conservarlos.' : 'Crea varias conexiones del mismo tipo con nombres distintos (por ejemplo, dos servidores SFTP o dos buckets). Cada una aparece como destino propio en Backups y en las automatizaciones.' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-backups-connection-form">';
		wp_nonce_field( self::ACTION_SAVE_CONNECTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE_CONNECTION ) . '"><input type="hidden" name="connection_id" value="' . esc_attr( $current ? $editing : '' ) . '">' . ( self::network_context() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<table class="form-table"><tr><th><label for="digitalisimo-connection-type">Tipo</label></th><td><select id="digitalisimo-connection-type" class="digitalisimo-backups-type" name="connection_type"' . ( $current ? ' disabled' : '' ) . '>';
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) echo '<option value="' . esc_attr( $type ) . '"' . selected( $current ? $current['type'] : '', $type, false ) . '>' . esc_html( $class::label() ) . '</option>';
		echo '</select>' . ( $current ? '<input type="hidden" name="connection_type" value="' . esc_attr( $current['type'] ) . '">' : '' ) . '</td></tr><tr><th><label for="digitalisimo-connection-name">Nombre</label></th><td><input class="regular-text" id="digitalisimo-connection-name" name="connection_name" value="' . esc_attr( $current ? $current['name'] : '' ) . '" required maxlength="80"></td></tr>';
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) {
			if ( $current && $type !== $current['type'] ) continue;
			self::type_fields( $type, $current ? $settings : $class::defaults(), $locked, 'digitalisimo_backups_connection', 'connection-', $current ? $editing : '' );
		}
		echo '</table><p><button class="button button-primary">' . ( $current ? 'Guardar conexión' : 'Crear conexión' ) . '</button>' . ( $current ? ' <a class="button" href="' . esc_url( add_query_arg( 'tab', 'servers', self::admin_url() ) ) . '">Cancelar</a>' : '' ) . '</p></form></section>';
	}

	/* ------------------------------------------------------------------ Guardar */

	/** El servicio de metadatos de la nube nunca es un destino: no se guarda (y Digitalisimo_Backups_Http no le envía nada). */
	private static function refuse_metadata( $class, array $values ) {
		foreach ( array_filter( $class::hosts( $values ) ) as $host ) {
			if ( 'metadata' === Digitalisimo_Backups_Http::internal( $host, false ) ) throw new Exception( '«' . $host . '» es ' . Digitalisimo_Backups_Http::INTERNAL_TEXT['metadata'] . ', no un destino de respaldos. No se guardó nada.' );
		}
	}
	public static function save() {
		// «Probar conexión» sin JavaScript: su `formaction` lleva action=…test_destination, pero el `action` oculto del
		// formulario va en el cuerpo y PHP le da prioridad, así que llega aquí. El botón pulsado viaja con el formulario:
		// se prueba y no se guarda nada (H13 del ensayo E2).
		if ( isset( $_POST['digitalisimo_backups_test'] ) ) { self::test_destination(); exit; }
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_SAVE ); if ( ! self::credentials_here() ) self::redirect( false, self::subsite_credentials_text() ); $old = self::settings(); $input = wp_unslash( (array) ( $_POST['digitalisimo_backups'] ?? array() ) ); $out = self::defaults();
		$out['destination'] = Digitalisimo_Backups_Destinations::exists( $input['destination'] ?? '' ) ? (string) $input['destination'] : 'local'; $out['retention'] = min( 100, max( 1, absint( $input['retention'] ?? 5 ) ) );
		try {
			foreach ( Digitalisimo_Backups_Destinations::CLASSES as $class ) { $values = $class::sanitize( $input, $old ); self::refuse_metadata( $class, $values ); $out = array_merge( $out, $values ); }
		} catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); }
		$out['full_schedule'] = array_key_exists( $input['full_schedule'] ?? '', self::schedule_options() ) ? $input['full_schedule'] : 'disabled'; $out['database_schedule'] = 'disabled';
		// «Olvidar»: las credenciales de los destinos marcados se retiran (también las que no se abren), salvo lo que se acabe de escribir.
		$forget_input = is_array( $input['forget'] ?? null ) ? $input['forget'] : array(); $forget = array();
		foreach ( Digitalisimo_Backups_Credentials::DESTINATIONS as $destination => $fields ) { if ( empty( $forget_input[ $destination ] ) ) continue; foreach ( $fields as $key ) { $typed = $input[ $key ] ?? ''; if ( is_string( $typed ) && '' !== trim( $typed ) ) continue; $out[ $key ] = ''; $forget[ $key ] = $destination; } }
		// Los secretos van cifrados al almacén, nunca en claro a los ajustes (sin AUTH_KEY y SECURE_AUTH_KEY propias, como hasta ahora y con aviso).
		try { $encrypted = self::store_settings( $out ); if ( $forget ) Digitalisimo_Backups_Credentials::remove( array_keys( $forget ) ); } catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); }
		$forgotten = array_map( array( __CLASS__, 'destination_label' ), array_values( array_unique( $forget ) ) );
		$unprotected = ! $encrypted && array_filter( array_intersect_key( $out, array_flip( self::secret_fields() ) ), function( $value ) { return is_string( $value ) && '' !== $value; } );
		self::sync_schedules( $out ); wp_safe_redirect( add_query_arg( array( 'backup' => 'ok', 'message' => rawurlencode( 'Destino y automatizaciones guardados.' . ( $forgotten ? ' Se olvidaron las credenciales de ' . implode( ' y ', $forgotten ) . '.' : '' ) . ( $unprotected ? ' Aviso: las credenciales se guardaron sin cifrar porque wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias; defínelas para cifrarlas.' : '' ) ) ), self::admin_url() ) ); exit;
	}
	/** Guarda una conexión adicional: valores no secretos en su fila; secretos sólo cifrados en el almacén. */
	private static function store_connection( $id, $type, $name, array $values ) {
		$class = Digitalisimo_Backups_Destinations::class_of( $type );
		$secrets = array();
		foreach ( $class::secrets() as $field ) {
			if ( '' !== (string) ( $values[ $field ] ?? '' ) ) $secrets[ Digitalisimo_Backups_Destinations::secret_key( $id, $field ) ] = (string) $values[ $field ];
			unset( $values[ $field ] );
		}
		if ( ! self::credentials_here() ) throw new Exception( self::subsite_credentials_text() );
		unset( $values[ $class::PREFIX . 'name' ] );
		if ( $secrets && ! Digitalisimo_Backups_Credentials::put( $secrets, self::connection_context( $id, $values ) ) ) throw new Exception( 'Las conexiones adicionales sólo guardan sus credenciales cifradas: define AUTH_KEY y SECURE_AUTH_KEY propias en wp-config.php (la conexión principal de cada tipo sí se puede guardar sin cifrar, con aviso).' );
		$connections = Digitalisimo_Backups_Destinations::connections();
		$connections[ $id ] = array( 'type' => $type, 'name' => $name, 'settings' => array_map( 'strval', $values ) );
		Digitalisimo_Backups_Destinations::save_connections( $connections );
	}
	public static function save_connection() {
		// «Probar conexión» sin JavaScript en el formulario de otra conexión: se prueba y no se guarda (ver save()).
		if ( isset( $_POST['digitalisimo_backups_test'] ) ) { self::test_destination(); exit; }
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_SAVE_CONNECTION );
		$id = sanitize_key( $_POST['connection_id'] ?? '' );
		$connections = Digitalisimo_Backups_Destinations::connections();
		$type = '' !== $id && isset( $connections[ $id ] ) ? $connections[ $id ]['type'] : sanitize_key( $_POST['connection_type'] ?? '' );
		if ( ! Digitalisimo_Backups_Destinations::is_type( $type ) ) self::redirect( false, 'Tipo de destino no válido.', null, 'servers' );
		if ( '' !== $id && ! isset( $connections[ $id ] ) ) self::redirect( false, 'La conexión ya no existe.', null, 'servers' );
		if ( '' === $id && count( $connections ) >= Digitalisimo_Backups_Destinations::MAX_CONNECTIONS ) self::redirect( false, 'Se alcanzó el máximo de ' . Digitalisimo_Backups_Destinations::MAX_CONNECTIONS . ' conexiones adicionales.', null, 'servers' );
		$creating = '' === $id;
		$name = sanitize_text_field( wp_unslash( (string) ( $_POST['connection_name'] ?? '' ) ) );
		if ( '' === $name ) self::redirect( false, 'Escribe un nombre para la conexión.', null, 'servers' );
		$class = Digitalisimo_Backups_Destinations::class_of( $type );
		try {
			$old = '' !== $id ? self::connection_settings( $id ) : $class::defaults();
			$values = $class::sanitize( wp_unslash( (array) ( $_POST['digitalisimo_backups_connection'] ?? array() ) ), $old );
			self::refuse_metadata( $class, $values );
			if ( '' === $id ) $id = Digitalisimo_Backups_Destinations::new_connection_id( $type );
			self::store_connection( $id, $type, $name, $values );
		} catch ( Exception $e ) { self::redirect( false, $e->getMessage(), null, 'servers' ); }
		// Una conexión recién creada se abre en su edición: ahí «Conectar» (OAuth) conecta ESA conexión.
		if ( $creating ) {
			wp_safe_redirect( add_query_arg( array( 'backup' => 'ok', 'message' => rawurlencode( 'Conexión «' . $name . '» creada.' . ( $class::oauth() ? ' Ahora usa «Conectar ' . $class::label() . '» en esta conexión.' : '' ) ), 'tab' => 'servers', 'connection' => $id ), self::admin_url() ) . '#digitalisimo-backups-connection' );
			exit;
		}
		self::redirect( true, 'Conexión «' . $name . '» guardada.', null, 'servers' );
	}
	public static function delete_connection() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_DELETE_CONNECTION );
		$id = sanitize_key( $_POST['connection_id'] ?? '' );
		$connections = Digitalisimo_Backups_Destinations::connections();
		if ( ! isset( $connections[ $id ] ) ) self::redirect( false, 'La conexión ya no existe.', null, 'servers' );
		$used = array_filter( self::policies(), function( $policy ) use ( $id ) { return ( $policy['destination'] ?? '' ) === $id; } );
		if ( $used || ( self::settings()['destination'] ?? '' ) === $id ) self::redirect( false, 'Esta conexión es el destino predeterminado o el de una automatización: cámbialo antes de eliminarla.', null, 'servers' );
		foreach ( Digitalisimo_Backups_Jobs::active() as $job ) {
			if ( ( $job['spec']['destination'] ?? '' ) === $id ) self::redirect( false, 'Hay un respaldo en curso hacia esta conexión: espera a que termine o cancélalo antes de eliminarla.', null, 'servers' );
		}
		$name = $connections[ $id ]['name'];
		$rows = self::history_rows_in( $id );
		unset( $connections[ $id ] );
		Digitalisimo_Backups_Destinations::save_connections( $connections );
		try { Digitalisimo_Backups_Credentials::remove( Digitalisimo_Backups_Credentials::fields_of( $id ) ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$note = $rows ? ' ' . ( 1 === $rows ? 'Un respaldo del historial está' : $rows . ' respaldos del historial están' ) . ' en ella: siguen en el destino, pero ya no se pueden traer ni descargar desde WordPress; «Eliminar» sólo quitará su fila, sin tocar la copia remota.' : ' Los respaldos ya copiados en ella siguen en el destino.';
		self::redirect( true, 'Conexión «' . $name . '» eliminada.' . $note, null, 'servers' );
	}

	/* ------------------------------------------------------------------ Probar conexión */

	/**
	 * Prueba una conexión con lo escrito en el formulario (un secreto vacío usa el guardado), sin guardar nada: sube,
	 * verifica, descarga, lista y borra un archivo pequeño. Devuelve array( ok, message, steps ).
	 */
	public static function service_test_destination( $id, array $input = array() ) {
		$id = (string) $id;
		if ( ! self::credentials_here() ) return array( 'ok' => false, 'message' => self::subsite_credentials_text(), 'steps' => array() );
		$type = Digitalisimo_Backups_Destinations::type_of( $id );
		if ( '' === $type ) {
			// Una conexión nueva aún sin identificador: se prueba con el tipo elegido y lo escrito.
			$type = Digitalisimo_Backups_Destinations::is_type( $input['__type'] ?? '' ) ? (string) $input['__type'] : '';
			if ( '' === $type ) return array( 'ok' => false, 'message' => 'Elige un tipo de destino.', 'steps' => array() );
		}
		if ( Digitalisimo_Backups_Destinations::is_connection_id( $id ) && ! isset( Digitalisimo_Backups_Destinations::connections()[ $id ] ) ) return array( 'ok' => false, 'message' => 'La conexión ya no existe.', 'steps' => array() );
		$class = Digitalisimo_Backups_Destinations::class_of( $type );
		try {
			$old = Digitalisimo_Backups_Destinations::is_type( $id ) || Digitalisimo_Backups_Destinations::is_connection_id( $id ) ? self::connection_settings( $id ) : $class::defaults();
			$settings = $input ? array_merge( $old, $class::sanitize( $input, $old ) ) : $old;
			$retype = $input ? self::retype_problem( $class, $old, $settings, $input ) : '';
			if ( '' !== $retype ) return array( 'ok' => false, 'message' => $retype, 'steps' => array(), 'warning' => '' );
			$warning = '';
			foreach ( array_filter( $class::hosts( $settings ) ) as $host ) {
				$kind = Digitalisimo_Backups_Http::internal( $host );
				if ( 'metadata' === $kind ) return array( 'ok' => false, 'message' => 'La prueba no se conecta a «' . $host . '»: es ' . Digitalisimo_Backups_Http::INTERNAL_TEXT['metadata'] . ', no un destino de respaldos.', 'steps' => array(), 'warning' => '' );
				if ( '' !== $kind && '' === $warning ) $warning = 'Atención: «' . $host . '» es ' . Digitalisimo_Backups_Http::INTERNAL_TEXT[ $kind ] . '; la prueba se conecta a un servicio interno. Úsalo sólo si ahí está de verdad tu servidor de respaldos. ';
			}
			if ( ! $class::ready( $settings ) ) {
				$problem = self::credentials_problem( $id );
				throw new Exception( '' !== $problem ? $problem : 'Completa los datos de ' . $class::label() . ( $class::oauth() ? ' y conéctalo' : '' ) . ' antes de probar.' );
			}
			$destination = new $class( $settings, array( 'temp_dir' => function() { return self::local_directory(); }, 'connection' => '' !== $id ? $id : $type ) );
			$steps = $destination->test();
		} catch ( Throwable $e ) {
			$warning = isset( $warning ) ? $warning : '';
			return array( 'ok' => false, 'message' => $warning . 'La prueba de ' . $class::label() . ' falló: ' . Digitalisimo_Backups_Http::redact( $e->getMessage() ), 'steps' => isset( $steps ) ? $steps : array(), 'warning' => $warning );
		}
		return array( 'ok' => true, 'message' => $warning . 'Conexión con ' . $class::label() . ' correcta: ' . implode( ' ', $steps ), 'steps' => $steps, 'warning' => $warning );
	}
	/**
	 * La prueba usa lo escrito y, si un secreto se deja vacío, el guardado; pero nunca lo envía a otro servidor: si cambió
	 * un dato al que ese secreto está atado (servidor, puerto, usuario, URL, endpoint, bucket, huella, TLS…) y no se volvió
	 * a escribir, pide escribirlo. Las carpetas no cuentan (no cambian a quién se envía). '' si se puede probar.
	 */
	private static function retype_problem( $class, array $old, array $settings, array $input ) {
		$fields = $class::fields();
		foreach ( $class::secrets() as $secret ) {
			if ( '' === (string) ( $old[ $secret ] ?? '' ) ) continue;
			if ( is_string( $input[ $secret ] ?? null ) && '' !== trim( $input[ $secret ] ) ) continue;
			foreach ( Digitalisimo_Backups_Credentials::CONTEXT[ $secret ] ?? array() as $field ) {
				if ( preg_match( '/_(path|folder|passive)$/', $field ) ) continue;
				if ( (string) ( $settings[ $field ] ?? '' ) === (string) ( $old[ $field ] ?? '' ) ) continue;
				return 'Cambiaste «' . ( $fields[ $field ]['label'] ?? $field ) . '» respecto a lo guardado y la prueba no envía el valor guardado de «' . ( $fields[ $secret ]['label'] ?? $secret ) . '» a otro servidor: escríbelo otra vez para probar' . ( $class::oauth() ? ', o guarda los cambios y vuelve a conectar' : '' ) . '.';
			}
		}
		return '';
	}
	/**
	 * Destinos remotos para REST y WP-CLI: identificador, tipo, nombre, si está listo y, si sus credenciales no se abren,
	 * qué hacer. Nunca un valor secreto.
	 */
	public static function service_destinations() {
		$allowed = self::service_scope( is_multisite(), false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		if ( ! self::credentials_here() ) return self::service_error( 'main_site', self::subsite_credentials_text(), 400 );
		$settings = self::settings();
		$rows = array();
		foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) $rows[] = array( 'id' => $type, 'type' => $type, 'name' => Digitalisimo_Backups_Destinations::name_of( $type, $settings ), 'primary' => true, 'ready' => $class::ready( $settings ), 'problem' => self::credentials_problem( $type ) );
		foreach ( Digitalisimo_Backups_Destinations::connections() as $id => $connection ) {
			try { $ready = Digitalisimo_Backups_Destinations::class_of( $connection['type'] )::ready( self::connection_settings( $id ) ); } catch ( Exception $e ) { $ready = false; }
			$rows[] = array( 'id' => $id, 'type' => $connection['type'], 'name' => $connection['name'], 'primary' => false, 'ready' => $ready, 'problem' => self::credentials_problem( $id ) );
		}
		return $rows;
	}
	/** «Probar conexión» de una conexión guardada, para REST y WP-CLI. */
	public static function service_test_saved( $id ) {
		$allowed = self::service_scope( is_multisite(), false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$id = sanitize_key( (string) $id );
		if ( ! Digitalisimo_Backups_Destinations::exists( $id ) || 'local' === $id ) return self::service_error( 'destination', 'El destino ' . $id . ' no existe.', 404 );
		$result = self::service_test_destination( $id );
		return $result['ok'] ? $result : self::service_error( 'destination_test', $result['message'], 502 );
	}
	/** Lo enviado por el formulario para la conexión que se prueba (principal o adicional). */
	private static function test_input() {
		$id = sanitize_key( (string) ( $_POST['digitalisimo_backups_test'] ?? '' ) );
		$input = isset( $_POST['digitalisimo_backups_connection'] ) ? (array) $_POST['digitalisimo_backups_connection'] : (array) ( $_POST['digitalisimo_backups'] ?? array() );
		$input = wp_unslash( $input );
		if ( '' === $id ) $input['__type'] = sanitize_key( $_POST['connection_type'] ?? '' );
		return array( $id, $input );
	}
	private static function test_nonce_ok() {
		$nonce = (string) ( $_POST['_wpnonce'] ?? '' );
		return function_exists( 'wp_verify_nonce' ) && ( wp_verify_nonce( $nonce, self::ACTION_SAVE ) || wp_verify_nonce( $nonce, self::ACTION_SAVE_CONNECTION ) );
	}
	public static function test_destination() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		if ( ! self::test_nonce_ok() ) wp_die( 'El enlace caducó. Vuelve a cargar la página.' );
		@set_time_limit( 0 );
		list( $id, $input ) = self::test_input();
		$result = self::service_test_destination( $id, $input );
		self::redirect( $result['ok'], $result['message'], null, 'servers' );
	}
	public static function ajax_test_destination() {
		if ( ! self::can_manage() ) wp_send_json_error( array( 'message' => 'No autorizado.' ), 403 );
		if ( ! self::test_nonce_ok() ) wp_send_json_error( array( 'message' => 'El enlace caducó. Vuelve a cargar la página.' ), 403 );
		@set_time_limit( 0 );
		list( $id, $input ) = self::test_input();
		$result = self::service_test_destination( $id, $input );
		if ( $result['ok'] ) wp_send_json_success( $result );
		wp_send_json_error( $result, 200 );
	}

	/* ------------------------------------------------------------------ OAuth */

	/** @internal Fila del estado OAuth pendiente del usuario actual (prefijo de la bóveda: fuera de todo respaldo). */
	public static function oauth_pending_key() { return Digitalisimo_Backups_Keys::SECRET_PREFIX . 'oauth_' . (int) get_current_user_id(); }
	private static function oauth_callback_url() { return add_query_arg( self::context_args(), admin_url( 'admin-post.php?action=' . self::ACTION_OAUTH ) ); }
	private static function oauth_url( $provider, $connection = '' ) {
		$args = array( 'action' => self::ACTION_OAUTH, 'provider' => $provider );
		if ( Digitalisimo_Backups_Destinations::is_connection_id( $connection ) ) $args['connection'] = $connection;
		return wp_nonce_url( add_query_arg( array_merge( $args, self::context_args() ), admin_url( 'admin-post.php' ) ), self::ACTION_OAUTH );
	}
	/**
	 * Conecta Google Drive, OneDrive, Dropbox (PKCE, sin App secret) o pCloud. Al volver del proveedor, el tipo y la
	 * conexión salen del estado guardado (el proveedor sólo devuelve `code` y `state`); el token va cifrado al almacén.
	 */
	public static function oauth_callback() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		// El estado pendiente (con el verificador PKCE de Dropbox) va en una fila digitalisimo_backups_vault_*, que ningún
		// respaldo exporta, y caduca a los 15 minutos.
		$pending_key = self::oauth_pending_key();
		$returning = ! empty( $_GET['code'] );
		$saved = $returning ? get_option( $pending_key, null ) : null;
		if ( ! is_array( $saved ) || (int) ( $saved['expires'] ?? 0 ) < time() ) $saved = null;
		$provider = sanitize_key( $_REQUEST['provider'] ?? ( is_array( $saved ) ? (string) ( $saved['provider'] ?? '' ) : '' ) );
		if ( ! in_array( $provider, array( 'google', 'onedrive', 'dropbox', 'pcloud' ), true ) ) self::redirect( false, 'Proveedor no válido.' );
		if ( ! self::credentials_here() ) self::redirect( false, self::subsite_credentials_text() );
		$connection = sanitize_key( $_REQUEST['connection'] ?? ( is_array( $saved ) ? (string) ( $saved['connection'] ?? '' ) : '' ) );
		if ( '' !== $connection && Digitalisimo_Backups_Destinations::type_of( $connection ) !== $provider ) self::redirect( false, 'Proveedor no válido.' );
		$id = '' !== $connection ? $connection : $provider;
		try { $settings = self::connection_settings( $id ); } catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); }
		$label = Digitalisimo_Backups_Destinations::label( $provider );
		if ( ! $returning ) {
			check_admin_referer( self::ACTION_OAUTH );
			$needs_secret = 'dropbox' !== $provider;
			$key = 'dropbox' === $provider ? 'dropbox_app_key' : $provider . '_client_id';
			if ( empty( $settings[ $key ] ) || ( $needs_secret && empty( $settings[ $provider . '_client_secret' ] ) ) ) self::redirect( false, 'dropbox' === $provider ? 'Guarda antes el App key de Dropbox.' : 'Guarda antes el Client ID y Client secret del proveedor.' );
			$state = wp_generate_password( 32, false, false );
			$pending = array( 'state' => $state, 'provider' => $provider, 'connection' => $connection );
			$redirect = self::oauth_callback_url();
			if ( 'google' === $provider ) $url = add_query_arg( array( 'client_id' => $settings['google_client_id'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'access_type' => 'offline', 'prompt' => 'consent', 'scope' => 'https://www.googleapis.com/auth/drive.file', 'state' => $state ), 'https://accounts.google.com/o/oauth2/v2/auth' );
			elseif ( 'onedrive' === $provider ) $url = add_query_arg( array( 'client_id' => $settings['onedrive_client_id'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'response_mode' => 'query', 'scope' => 'offline_access Files.ReadWrite', 'state' => $state ), 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize' );
			elseif ( 'dropbox' === $provider ) {
				$pending['verifier'] = Digitalisimo_Backups_Destination_Dropbox::code_verifier();
				$url = add_query_arg( array( 'client_id' => $settings['dropbox_app_key'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'token_access_type' => 'offline', 'code_challenge' => Digitalisimo_Backups_Destination_Dropbox::code_challenge( $pending['verifier'] ), 'code_challenge_method' => 'S256', 'state' => $state ), 'https://www.dropbox.com/oauth2/authorize' );
			} else $url = add_query_arg( array( 'client_id' => $settings['pcloud_client_id'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'state' => $state ), 'https://my.pcloud.com/oauth2/authorize' );
			update_option( $pending_key, $pending + array( 'expires' => time() + 15 * MINUTE_IN_SECONDS ), false );
			wp_redirect( $url ); exit;
		}
		if ( empty( $saved['state'] ) || ! hash_equals( (string) $saved['state'], (string) ( $_GET['state'] ?? '' ) ) || $provider !== ( $saved['provider'] ?? '' ) || $connection !== (string) ( $saved['connection'] ?? '' ) ) self::redirect( false, 'La autorización expiró o no coincide. Inténtalo nuevamente.' );
		delete_option( $pending_key );
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$values = array();
		if ( in_array( $provider, array( 'google', 'onedrive' ), true ) ) {
			$endpoint = 'google' === $provider ? 'https://oauth2.googleapis.com/token' : 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
			$response = wp_remote_post( $endpoint, array( 'timeout' => 30, 'body' => array( 'client_id' => $settings[ $provider . '_client_id' ], 'client_secret' => $settings[ $provider . '_client_secret' ], 'code' => $code, 'redirect_uri' => self::oauth_callback_url(), 'grant_type' => 'authorization_code' ) ) );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_wp_error( $response ) || empty( $data['refresh_token'] ) ) self::redirect( false, 'El proveedor no devolvió un refresh token. Revisa la URL de redirección registrada en su aplicación.' );
			$values[ $provider . '_refresh_token' ] = $data['refresh_token'];
		} elseif ( 'dropbox' === $provider ) {
			$token = Digitalisimo_Backups_Destination_Dropbox::exchange_code( (string) $settings['dropbox_app_key'], $code, (string) ( $saved['verifier'] ?? '' ), self::oauth_callback_url() );
			if ( '' === $token ) self::redirect( false, 'Dropbox no devolvió un refresh token. Revisa la Redirect URI y que la app permita clientes públicos (PKCE).' );
			$values['dropbox_refresh_token'] = $token;
		} else {
			$host = Digitalisimo_Backups_Destination_Pcloud::api_host( (string) ( $_GET['hostname'] ?? '' ), (string) ( $_GET['locationid'] ?? '' ) );
			$token = Digitalisimo_Backups_Destination_Pcloud::exchange_code( $host, (string) $settings['pcloud_client_id'], (string) $settings['pcloud_client_secret'], $code );
			if ( '' === $token ) self::redirect( false, 'pCloud no devolvió un token. Revisa el Client ID, el Client secret y la Redirect URI de la app.' );
			$values['pcloud_access_token'] = $token;
			$values['pcloud_host'] = $host;
		}
		// El token se guarda exactamente como lo devolvió el proveedor; sólo se rechaza lo que no se puede guardar.
		foreach ( $values as $field => $value ) {
			if ( ! in_array( $field, Digitalisimo_Backups_Credentials::FIELDS, true ) ) continue;
			$problem = Digitalisimo_Backups_Credentials::secret_problem( $value, $field );
			if ( '' !== $problem ) self::redirect( false, 'El proveedor devolvió un token que no se puede guardar (' . $problem . '). No se cambió nada.' );
		}
		try {
			if ( '' !== $connection ) {
				$connections = Digitalisimo_Backups_Destinations::connections();
				self::store_connection( $connection, $provider, $connections[ $connection ]['name'] ?? $label, array_merge( $settings, $values ) );
			} else {
				self::store_settings( array_merge( self::settings(), $values ) );
			}
		} catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); }
		self::redirect( true, $label . ' conectado correctamente.' );
	}
}
