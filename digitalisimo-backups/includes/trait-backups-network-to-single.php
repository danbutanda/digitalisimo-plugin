<?php
defined( 'ABSPATH' ) || exit;

// Restaurar el respaldo de una red en un WordPress individual (docs/BACKUPS.md, «Restaurar una red en un WordPress
// individual»). Pedido del propietario del 2026-11-16: el respaldo subido de una red ya no sale «No se restaura aquí» en un
// WordPress individual. Se elige qué sitio de la red (por defecto el principal) y ése, convertido como «Extraer como
// WordPress individual» pero desde el respaldo (Digitalisimo_Backups_Site_Convert, trabajo `convert`), pasa a la
// restauración de siempre: verificación, copia previa, por pasos, mantenimiento, vuelta a la copia previa, H23,
// «Conservar mi acceso», por partes, incrementales y cifrado.
//
// Todo lo propio de esta función vive aquí, aislado de lo que comparten la red y el sitio individual: uploaded-list sólo
// llama a network_restores_as_site(), la confirmación a network_site_field() y los casos de uso a service_convert_*().
// Los comentarios son `//` (no docblocks): sin opcache, PHP guarda cada docblock con su método en cada petición.
trait Digitalisimo_Backups_Network_To_Single {
	// Una fila (subida, agregada o traída) que es el respaldo de una red y esta instalación es un WordPress individual: se
	// restaura un sitio suyo.
	private static function network_restores_as_site( array $row ) {
		return ! is_multisite() && 'network' === (string) ( $row['scope'] ?? '' );
	}
	// El manifiesto (primer nivel) es el de una red y esta instalación es un WordPress individual.
	public static function network_converts( $data ) {
		return is_array( $data ) && ! is_multisite() && ! empty( $data['multisite'] ) && 'installation' === (string) ( $data['scope'] ?? 'installation' );
	}
	// Los sitios de una red que guarda la fila (al subirlo o al abrir su panel): ID => etiqueta.
	private static function network_row_sites( array $row ) {
		$out = array();
		foreach ( (array) ( $row['sites'] ?? array() ) as $id => $label ) if ( (int) $id > 0 ) $out[ (int) $id ] = is_array( $label ) ? (string) ( $label['label'] ?? '' ) : (string) $label;
		ksort( $out );
		return $out;
	}
	// Los sitios del origen (la sonda de la verificación: Relocate::origin_of()) como los guarda la fila: ID => etiqueta.
	private static function network_sites_of( array $source ) {
		$out = array();
		foreach ( array_slice( (array) ( $source['sites'] ?? array() ), 0, 500, true ) as $id => $site ) if ( is_array( $site ) && isset( $site['domain'] ) ) $out[ (int) $id ] = Digitalisimo_Backups_Site_Extract::label( (string) $site['domain'], (string) ( $site['path'] ?? '/' ) );
		ksort( $out );
		return $out;
	}
	// El principal de la lista (el de la dirección de la red o el 1).
	private static function network_main_of( array $sites, $network = array() ) {
		$label = is_array( $network ) && isset( $network['domain'] ) ? Digitalisimo_Backups_Site_Extract::label( (string) $network['domain'], (string) ( $network['path'] ?? '/' ) ) : '';
		foreach ( $sites as $id => $site ) if ( '' !== $label && $site === $label ) return (int) $id;
		return isset( $sites[1] ) || ! $sites ? 1 : (int) min( array_keys( $sites ) );
	}

	// ------------------------------------------------------------------ Pantalla

	// En el panel «Restaurar» del respaldo de una red en un WordPress individual: qué pasa (se restaura un sitio de la red
	// como este WordPress) y la lista de sus sitios, con el principal por defecto. Sin la lista (un .dgbk sin su clave, una
	// fila de antes), el número del sitio.
	private static function network_site_field( array $item, $path, $shown ) {
		if ( ! self::network_restores_as_site( $item ) ) return '';
		$sites = self::network_row_sites( $item );
		if ( ! $sites && $shown && '' !== (string) $path && ! Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) {
			$sites = self::network_scan_sites( $path );
			if ( $sites ) self::history_update( (string) $item['file'], array( 'sites' => $sites ) );
		}
		$main = isset( $item['network_main'] ) ? (int) $item['network_main'] : self::network_main_of( $sites );
		$label = $sites[ $main ] ?? 'el principal';
		$out = '<p class="digitalisimo-backups-warning" data-network-site-summary><strong>Es el respaldo de una red y este WordPress es individual:</strong> se restaura <strong data-network-site-label>el sitio ' . esc_html( $label ) . '</strong> de la red como este WordPress (sus tablas, sus usuarios con sus roles, sus plugins, su tema y sus medios); lo demás de la red no entra.</p>';
		if ( $sites ) {
			$out .= '<p><label>Sitio de la red <select name="restore_site" data-network-site>';
			foreach ( $sites as $id => $name ) $out .= '<option value="' . (int) $id . '"' . ( $id === $main ? ' selected' : '' ) . '>' . esc_html( $name . ' (ID ' . $id . ( $id === $main ? ', principal' : '' ) . ')' ) . '</option>';
			$out .= '</select></label></p>';
		} else {
			$out .= '<p><label>Sitio de la red (su número; 1 es el principal) <input type="number" name="restore_site" min="1" step="1" value="1" data-network-site></label></p>';
		}
		// Como al extraer un sitio: los superadministradores que no son miembros y las claves de la red no viajan sin pedirlo.
		$out .= '<details class="digitalisimo-backups-network-options"><summary>Más del sitio de la red</summary><p><label>Superadministradores de la red que no son miembros del sitio <select name="restore_super_admins"><option value="none" selected>Ninguno</option><option value="managing">Sólo quien tiene el correo de administración del sitio</option><option value="all">Todos, como administradores</option></select></label></p><p><label><input type="checkbox" name="restore_network_secrets" value="1"> Copiar también las claves y cuentas que el sitio hereda de la red</label></p></details>';
		return $out;
	}
	// Lo que envió el panel «Restaurar» (o «Restaurar un archivo suelto»): el sitio de la red y sus opciones. Nada si no se
	// pidió (o el campo va vacío: el principal).
	private static function network_request() {
		$out = array();
		$site = isset( $_POST['restore_site'] ) && is_scalar( $_POST['restore_site'] ) ? trim( (string) wp_unslash( $_POST['restore_site'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- lo comprueba restore_request().
		if ( '' !== $site ) $out['site'] = $site;
		$mode = isset( $_POST['restore_super_admins'] ) && is_scalar( $_POST['restore_super_admins'] ) ? sanitize_key( wp_unslash( (string) $_POST['restore_super_admins'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $mode && 'none' !== $mode ) $out['super_admins'] = $mode;
		if ( ! empty( $_POST['restore_network_secrets'] ) ) $out['network_secrets'] = true; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return $out;
	}
	// Los sitios de un respaldo de red (ZIP legible), leyendo su SQL con la sonda de la reubicación. Vacío si no se puede.
	private static function network_scan_sites( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) return array();
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) return array();
		try {
			$data = self::package_metadata( $zip );
			if ( ! self::network_converts( $data ) ) return array();
			$probe = new Digitalisimo_Backups_Relocate_Probe( (string) ( $data['table_prefix'] ?? '' ) );
			Digitalisimo_Backups_Sql_Guard::for_backup( $data, false )->on_insert( $probe )->scan_zip( $zip );
			return self::network_sites_of( Digitalisimo_Backups_Relocate::origin_of( $data, $probe ) );
		} catch ( Throwable $e ) {
			return array();
		} finally {
			$zip->close();
		}
	}
	// «Origen → este sitio» de un archivo de red en un WordPress individual (la lista de «Restaurar un archivo suelto»).
	private static function network_hint( array $manifest ) {
		if ( ! self::network_converts( $manifest ) ) return null;
		$home = (string) ( $manifest['origin']['home'] ?? $manifest['home_url'] ?? '' );
		return array( 'label' => ' · red ' . ( '' !== $home ? $home : '' ) . ' → un sitio suyo como este WordPress', 'text' => 'Es el respaldo de una red y este WordPress es individual: se restaura el sitio de la red que elijas (por defecto, el principal) como este WordPress, con sus tablas, usuarios, plugins, tema y medios; sus direcciones pasan a las de este sitio.' );
	}

	// ------------------------------------------------------------------ Casos de uso

	// Las opciones de la conversión de una petición (`site`, `super_admins`, `network_secrets`), o WP_Error 400.
	private static function network_options( array $args ) {
		Digitalisimo_Backups_Site_Convert_Job::load();
		try {
			return Digitalisimo_Backups_Site_Convert::options( $args );
		} catch ( InvalidArgumentException $e ) {
			return self::service_error( 'site', $e->getMessage(), 400 );
		}
	}
	// `site` (o sus opciones) sólo valen al restaurar el respaldo de una red en un WordPress individual.
	private static function network_args_problem( array $args ) {
		foreach ( array( 'site', 'super_admins', 'network_secrets' ) as $key ) if ( isset( $args[ $key ] ) && '' !== $args[ $key ] && null !== $args[ $key ] ) return self::service_error( 'site', 'El sitio de la red (`site`) sólo se elige al restaurar el respaldo de una red en un WordPress individual.', 400 );
		return null;
	}
	// Restaurar el respaldo de una red en un WordPress individual (con el bloqueo de operaciones tomado y sin otros
	// trabajos): las opciones, la confirmación de su confianza y el trabajo `convert`, que convierte el sitio por tramos y
	// lo entrega a la restauración con los argumentos de esta petición. Devuelve el trabajo o el error.
	private static function service_convert_restore( array $report, $path, $confirmation, array $args, $addresses, $parts ) {
		$options = self::network_options( $args );
		if ( is_wp_error( $options ) ) return $options;
		if ( null !== $parts && ! empty( $parts['sites'] ) ) return self::service_error( 'parts', 'En un WordPress individual no se eligen los medios de un sitio de la red: elige el sitio con `site` y la parte «medios» entera.', 400 );
		try {
			Digitalisimo_Backups_Security::require_confirmation( $report, $confirmation );
		} catch ( Exception $e ) {
			return 'untrusted' === $report['trust'] && Digitalisimo_Backups_Security::settings()['require_signed'] ? self::service_error( 'signed_only', $e->getMessage(), 403 ) : self::service_error( 'confirm_unsigned', $e->getMessage(), 400 );
		}
		$adopted = false;
		$note = '';
		if ( ! empty( $args['adopt'] ) && 'secret' === $report['via'] && $report['root'] ) {
			try {
				Digitalisimo_Backups_Keys::adopt( $report['root'], $report['key'] );
				$adopted = true;
				$note = ' La frase escrita firma ahora los respaldos nuevos (clave ' . Digitalisimo_Backups_Keys::short( $report['key']['id'] ) . ').';
			} catch ( Exception $adopt_error ) {
				$note = ' No se guardó la frase: ' . $adopt_error->getMessage();
			}
		}
		$via = (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) );
		$safety_file = self::backup_file_name( 'safety-before-restore-' );
		$restore = array(
			'addresses' => $addresses,
			'via' => $via,
			'adopted' => $adopted,
			'safety_spec' => Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $safety_file, 'inline' => true ) ),
			'keep_access' => ! array_key_exists( 'keep_access', $args ) || ! empty( $args['keep_access'] ),
			'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'notice' => trim( $note ),
			'parts' => $parts,
		);
		return self::network_enqueue( $report, $path, $options, $restore, $via, self::service_token( $args ), $safety_file );
	}
	// Encola el trabajo `convert` con el respaldo abierto ($report: su ZIP, descifrado si era un .dgbk) y deja su plan
	// (Site_Convert::prepare()). $restore: lo que la restauración recibe al terminar la conversión (nunca la frase).
	private static function network_enqueue( array $report, $path, array $options, array $restore, $via, $token, $safety_file ) {
		$reader = Digitalisimo_Backups_Restore_Access::new_reader();
		$token = null !== $token && '' !== (string) $token ? (string) $token : Digitalisimo_Backups_Restore_Maintenance::new_token();
		$restore['token'] = $token;
		$restore['readers'] = array_values( array_unique( array_merge( array( $reader['hash'] ), (array) ( $restore['readers'] ?? array() ) ) ) );
		$user = (int) ( $restore['user_id'] ?? 0 );
		$spec = array(
			'scope' => 'installation',
			'type' => 'convert',
			'kind' => Digitalisimo_Backups_Site_Convert_Job::KIND,
			'destination' => 'local',
			'policy' => 'site-convert',
			'retain' => 1,
			'retention' => 'none',
			'site_id' => (int) get_current_blog_id(),
			'file' => wp_basename( (string) $path ),
			'inline' => false,
			'token' => $token,
			'safety' => (string) $safety_file,
			'reader' => array( 'hash' => $reader['hash'], 'issued' => $reader['issued'] ),
			'origin' => array( 'via' => (string) $via, 'user_id' => $user, 'blog_id' => (int) get_current_blog_id(), 'context' => 'site' ),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
			'convert' => array( 'site' => (int) $options['site'], 'super_admins' => (string) $options['super_admins'], 'network_secrets' => ! empty( $options['network_secrets'] ) ),
			'restore' => $restore,
		);
		$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
		if ( ! empty( $queued['duplicate'] ) ) return self::service_error( 'busy', 'Ya hay una conversión de un sitio de una red en curso.', 409, array( 'job' => (string) $queued['job']['id'] ) );
		$job = $queued['job'];
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		try {
			// El plan se prepara con el ZIP abierto donde está (libzip de PHP 7.4 vuelve a leerlo por su ruta); después, lo
			// descifrado (un .dgbk) o reconstruido pasa al área del trabajo y el plan apunta allí. Un ZIP del almacén se lee
			// donde está.
			$source = (string) $report['path'];
			Digitalisimo_Backups_Site_Convert::prepare( $report, $options, $staging, array( 'path' => $source, 'file' => wp_basename( (string) $path ) ) );
			if ( 'dgbk' === $report['container'] || ! empty( $report['staged'] ) ) {
				$target = $staging . '/source.zip';
				if ( ! @rename( $source, $target ) && ! @copy( $source, $target ) ) throw new Exception( 'No se pudo preparar el respaldo descifrado en el área de trabajo.' );
				if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
				Digitalisimo_Backups_Site_Convert::move_source( $staging, $target );
			}
		} catch ( Throwable $e ) {
			Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
			return self::service_error( 'restore_rejected', $e->getMessage(), 422 );
		}
		$job['reader_token'] = $reader['token'];
		return $job;
	}
	// Resultado de restaurar un sitio de una red: la conversión y la restauración que encola, por ticks (en WP-CLI, hasta
	// terminar). Sin terminar en esta petición: `restoring` con el trabajo en curso y el testigo del mantenimiento.
	private static function convert_outcome( array $convert, $copies, $cli, $reader = null ) {
		$reader = null === $reader ? (string) ( $convert['reader_token'] ?? '' ) : (string) $reader;
		$id = (string) $convert['id'];
		$job = self::restore_drive( $id, $cli );
		$restore_id = (string) ( $job['result']['restore_job'] ?? '' );
		if ( $job && 'success' === $job['status'] && '' !== $restore_id ) {
			$restore = self::restore_drive( $restore_id, $cli );
			$outcome = self::restore_outcome( $restore, $copies );
			if ( is_array( $outcome ) ) $outcome['follow'] = self::service_follow( $restore_id, $reader );
			if ( is_array( $outcome ) && ! isset( $outcome['parts'] ) ) $outcome['parts'] = Digitalisimo_Backups_Restore_Parts::report( Digitalisimo_Backups_Restore_Parts::from_spec( array( 'parts' => $convert['spec']['restore']['parts'] ?? null ) ) );
			return $outcome;
		}
		if ( ! $job ) return self::service_error( 'restore_failed', 'El trabajo de la conversión del sitio ya no existe.', 500 );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return self::service_error( 'cancelled' === $job['status'] ? 'restore_cancelled' : 'restore_rejected', 'El sitio de la red no se pudo convertir: ' . (string) ( $job['error']['message'] ?? 'la conversión no terminó.' ) . ( false === strpos( (string) ( $job['error']['message'] ?? '' ), 'No se cambió nada' ) ? ' No se cambió nada del sitio.' : '' ), 'cancelled' === $job['status'] ? 409 : 422 );
		return array( 'file' => basename( (string) $job['spec']['file'] ), 'restored' => false, 'restoring' => true, 'job' => self::service_job_view( $job ), 'notice' => 'Convirtiendo el sitio de la red en un WordPress individual (' . Digitalisimo_Backups_Jobs::phase_label( (string) $job['phase'], $job ) . '); después sigue la restauración. Las continúan la pantalla Backups, WP-Cron o POST /jobs/tick.', 'safety_backup' => (string) $job['spec']['safety'], 'maintenance_token' => (string) $job['spec']['token'], 'follow' => self::service_follow( $id, $reader ) );
	}
	// @internal Fase «site_handoff» del trabajo `convert`: abre el sitio convertido con la confianza del respaldo de la red
	// y encola la restauración de siempre con los argumentos que fijó la petición (o la migración). Si el proceso murió
	// justo después de encolarla, la reconoce por su ficha de lectura.
	public static function convert_handoff( array $job, array $plan ) {
		$dir = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		$target = $dir . '/' . Digitalisimo_Backups_Site_Convert::ZIP;
		$restore = (array) ( $job['spec']['restore'] ?? array() );
		$queued = (string) ( $job['checkpoint']['site_handoff']['restore_job'] ?? '' );
		foreach ( Digitalisimo_Backups_Jobs::all() as $other ) if ( '' === $queued && Digitalisimo_Backups_Jobs::is_restore( $other ) && array_intersect( (array) ( $restore['readers'] ?? array() ), (array) ( $other['spec']['readers'] ?? array() ) ) ) $queued = (string) $other['id'];
		if ( '' === $queued ) {
			// Quien espera el resultado (una migración) no cuenta; un respaldo que sólo espera en la cola tampoco.
			$except = array_merge( array( (string) $job['id'] ), array_map( 'strval', (array) ( $restore['except'] ?? array() ) ) );
			foreach ( Digitalisimo_Backups_Jobs::active() as $other ) {
				if ( in_array( (string) $other['id'], $except, true ) ) continue;
				if ( Digitalisimo_Backups_Jobs::restore_like( $other ) || ! empty( $other['lease'] ) || ! empty( $other['started_at'] ) ) return array( 'next' => 'site_handoff', 'wait' => 60, 'checkpoint' => array( 'waiting' => (string) $other['id'] ), 'message' => 'El sitio está convertido; espera a que termine otro trabajo para pasar a la restauración…' );
				$except[] = (string) $other['id'];
			}
			if ( ! is_file( $target ) || ! Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( $target ), (array) ( $job['checkpoint']['site_seal']['fp'] ?? array() ) ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El sitio convertido cambió o desapareció; se cierra otra vez.', 'site_seal' );
			$sha = @hash_file( 'sha256', $target );
			if ( false === $sha ) throw new Exception( 'No se pudo comprobar el sitio convertido.' );
			$report = Digitalisimo_Backups_Security::open( $target, 'untrusted' === ( $plan['trust'] ?? 'untrusted' ) ? array() : array( 'sha256' => $sha, 'origin' => 'created' ) );
			$report['source'] = rtrim( self::local_directory(), '/\\' ) . '/' . basename( (string) $job['spec']['file'] );
			$report['staged'] = true;
			if ( 'untrusted' === $report['trust'] ) $report['vouched'] = (string) ( $plan['vouched'] ?? '' );
			try {
				$manifest = Digitalisimo_Backups_Archive_Inspector::manifest( $report['zip'] );
				$notice = Digitalisimo_Backups_Site_Convert::notice( array( 'site' => array( 'id' => (int) ( $manifest['converted_from']['site'] ?? 0 ), 'label' => (string) ( $manifest['converted_from']['label'] ?? '' ) ) ) ) . ' ' . Digitalisimo_Backups_Site_Extract::restored_notice( $manifest );
				$args = array(
					'addresses' => 'backup' === ( $restore['addresses'] ?? '' ) ? 'backup' : 'current',
					'via' => (string) ( $restore['via'] ?? 'rest' ),
					'adopted' => ! empty( $restore['adopted'] ),
					'safety_spec' => (array) ( $restore['safety_spec'] ?? array() ),
					'sync' => false,
					'wp_config' => self::config_report( $report['zip'] ),
					'notice' => trim( $notice . ' ' . (string) ( $restore['notice'] ?? '' ) ),
					'keep_access' => ! array_key_exists( 'keep_access', $restore ) || ! empty( $restore['keep_access'] ),
					'token' => (string) ( $restore['token'] ?? '' ),
					'readers' => (array) ( $restore['readers'] ?? array() ),
					'user_id' => (int) ( $restore['user_id'] ?? 0 ),
					'parts' => is_array( $restore['parts'] ?? null ) ? $restore['parts'] : null,
					'except' => $except,
				);
				// Una migración: su copia previa marcada, tablas exactas y lo apartado (Digitalisimo_Backups::migration_enqueue()).
				foreach ( array( 'safety_extra', 'exact_tables', 'aside' ) as $key ) if ( array_key_exists( $key, $restore ) ) $args[ $key ] = $restore[ $key ];
				$restore_job = self::restore_enqueue( $report, $args );
			} finally {
				Digitalisimo_Backups_Security::close( $report );
				self::$restore_reader_token = '';
			}
			$queued = (string) $restore_job['id'];
		}
		return array( 'next' => null, 'checkpoint' => array( 'restore_job' => $queued ), 'result' => array( 'restore_job' => $queued ), 'message' => 'Sitio de la red convertido y entregado a la restauración (trabajo ' . $queued . ').' );
	}
	// @internal Un punto incremental de una red, ya reconstruido (Digitalisimo_Backups::chain_handoff()): su sitio elegido
	// se convierte antes de la restauración, con los argumentos que fijó la petición. Si el proceso murió justo después de
	// encolarla, la reconoce por su ficha de lectura. Devuelve el trabajo `convert`; lanza si no se puede.
	public static function chain_convert( array $report, array $restore ) {
		foreach ( Digitalisimo_Backups_Jobs::active() as $other ) if ( Digitalisimo_Backups_Site_Convert_Job::KIND === ( $other['spec']['kind'] ?? '' ) && array_intersect( (array) ( $restore['readers'] ?? array() ), (array) ( $other['spec']['restore']['readers'] ?? array() ) ) ) return $other;
		$options = self::network_options( (array) ( $restore['convert'] ?? array() ) );
		if ( is_wp_error( $options ) ) throw new Digitalisimo_Backups_Permanent_Error( $options->get_error_message() );
		unset( $restore['convert'] );
		$job = self::network_enqueue( $report, (string) $report['source'], $options, $restore, (string) ( $restore['via'] ?? 'rest' ), (string) ( $restore['token'] ?? '' ), (string) ( $restore['safety_spec']['file'] ?? '' ) );
		if ( is_wp_error( $job ) ) throw new Digitalisimo_Backups_Permanent_Error( $job->get_error_message() );
		return $job;
	}
	// @internal La migración de una red a este WordPress individual (Digitalisimo_Backups_Migrate::restore()): el paquete
	// abierto y comprobado pasa por la conversión del sitio elegido antes de su restauración, con los argumentos de
	// migration_enqueue(). Devuelve el trabajo `convert`, o lanza Exception con el motivo.
	public static function migration_convert( array $report, array $tag, $except, $site ) {
		$migration = Digitalisimo_Backups_Jobs::get( (string) $except );
		$spec = is_array( $migration ) ? (array) $migration['spec'] : array();
		$options = self::network_options( array( 'site' => $site ) );
		if ( is_wp_error( $options ) ) throw new Exception( $options->get_error_message() );
		$safety_file = self::backup_file_name( 'safety-before-restore-' );
		$restore = array(
			'addresses' => 'current',
			'via' => 'migration',
			'safety_spec' => Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $safety_file, 'inline' => true ) ),
			'safety_extra' => array( 'migration' => $tag ),
			'exact_tables' => true,
			'aside' => array( 'kind' => 'migration', 'migration' => (string) $except, 'from' => (string) ( $tag['from'] ?? '' ) ),
			'except' => array( (string) $except ),
			'user_id' => (int) ( $spec['origin']['user_id'] ?? 0 ),
			'keep_access' => ! array_key_exists( 'keep_access', $spec ) || ! empty( $spec['keep_access'] ),
			'readers' => isset( $spec['reader']['hash'] ) ? array( (string) $spec['reader']['hash'] ) : array(),
		);
		$job = self::network_enqueue( $report, (string) $report['source'], $options, $restore, 'migration', null, $safety_file );
		if ( is_wp_error( $job ) ) throw new Exception( $job->get_error_message() );
		return $job;
	}
	// `verify` del respaldo de una red en un WordPress individual: qué sitio se restauraría (y los de la red), qué viaja y
	// «origen → este sitio» con la dirección de ese sitio. Sin convertirlo (la restauración lo comprueba entero después).
	private static function network_verify( array $report, array $args ) {
		$options = self::network_options( $args );
		if ( is_wp_error( $options ) ) return $options;
		try {
			$site = Digitalisimo_Backups_Site_Convert::inspect_site( $report, $options, self::local_directory() );
		} catch ( Exception $e ) {
			return array( 'problems' => array( $e->getMessage() ), 'convert' => null, 'relocation' => null );
		}
		$data = $site['manifest'];
		$plan = Digitalisimo_Backups_Site_Extract::adjust_plan( Digitalisimo_Backups_Relocate::plan( $site['origin'] + array( 'extra_urls' => array() ), Digitalisimo_Backups_Relocate::describe(), array( 'addresses' => 'backup' === ( $args['addresses'] ?? '' ) ? 'backup' : 'current', 'tables' => $site['tables'] ) ), $data );
		unset( $site['origin'], $site['manifest'], $site['tables'] );
		return array( 'problems' => $plan->problems(), 'convert' => $site, 'relocation' => $plan->summary() );
	}
}
