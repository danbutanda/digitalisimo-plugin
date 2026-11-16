<?php
defined( 'ABSPATH' ) || exit;

/**
 * La importación de un sitio en una red (Digitalisimo_Backups_Site_Import) dentro del motor de la restauración por pasos:
 * lo que cada fase hace distinto cuando el trabajo lleva `into` (ver «Importar un sitio en una red» en docs/BACKUPS.md).
 * Vive en Digitalisimo_Backups para usar sus piezas (área de trabajo, exportador SQL, ZIP, historial) sin abrirlas.
 *
 * En el área de trabajo del trabajo: `into.json` (el sitio de destino, su carpeta de medios, las reglas de los archivos y
 * si el sitio nuevo ya se creó), `merge.json` (las tablas temporales de `users` y `usermeta` del respaldo, que no se
 * cambian por las de la red sino que se funden en ellas), `users.json` (el diario de esa fusión) y `remap.json` (el de la
 * reasignación de la autoría).
 */
trait Digitalisimo_Backups_Site_Import_Engine {
	// Reglas de los archivos de la importación en curso (las fija restore_phase()); null: una restauración de siempre.
	private static $import_rules = null;

	// La especificación `into` del trabajo (sitio nuevo o reemplazo), o null.
	private static function import_into( array $job ) {
		$into = $job['spec']['into'] ?? null;
		return is_array( $into ) && in_array( (string) ( $into['mode'] ?? '' ), array( 'new', 'replace' ), true ) ? $into : null;
	}
	private static function import_state( array $job ) { return (array) ( self::restore_read_json( $job, 'into.json', false ) ?? array() ); }
	private static function import_target( array $job ) {
		$state = self::import_state( $job );
		return is_array( $state['target'] ?? null ) ? $state['target'] : (array) self::import_into( $job );
	}
	// Al empezar cada fase: las reglas de los archivos (restore_layout(), restore_target() y el recorrido las usan).
	private static function import_begin( array $job ) {
		self::$import_rules = null;
		if ( ! self::import_into( $job ) ) return;
		$state = self::import_state( $job );
		if ( is_array( $state['rules'] ?? null ) ) self::$import_rules = $state['rules'];
	}
	// El sitio que queda en mantenimiento (sólo ése: el resto de la red se sigue sirviendo).
	private static function import_maintenance( array $job ) {
		if ( ! self::import_into( $job ) ) return array();
		$target = self::import_target( $job );
		return array( 'domain' => (string) ( $target['domain'] ?? '' ), 'path' => (string) ( $target['path'] ?? '/' ) );
	}
	// ID previsto de un sitio nuevo (para comprobar el plan con la red intacta; el de verdad sale al crearlo).
	private static function import_predict_id() {
		global $wpdb;
		return max( 2, (int) $wpdb->get_var( 'SELECT MAX(blog_id) FROM ' . Digitalisimo_Backups_Relocate::quote_name( $wpdb->base_prefix . 'blogs' ) ) + 1 );
	}

	// Verificación (último paso): el sitio de destino, su descripción para E1, el plan y las reglas de los archivos. Devuelve
	// array( plan o null, destino ). Un sitio nuevo aún no existe: se comprueba con el ID previsto.
	private static function import_plan( array $job, array $data, array $source, array $args ) {
		$into = self::import_into( $job );
		$rollback = ! empty( $job['spec']['rollback'] );
		if ( 'new' === $into['mode'] ) {
			global $wpdb;
			$target = $into;
			$taken = $wpdb->get_var( $wpdb->prepare( 'SELECT blog_id FROM ' . Digitalisimo_Backups_Relocate::quote_name( $wpdb->base_prefix . 'blogs' ) . ' WHERE domain = %s AND path = %s', (string) $into['domain'], (string) $into['path'] ) );
			$state = self::import_state( $job );
			if ( $taken && empty( $state['created'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Ya hay un sitio en ' . (string) $into['label'] . ': elige otra dirección o reemplaza ese sitio. No se cambió nada de la red.' );
			$target['site_id'] = ! empty( $state['created'] ) ? (int) $state['target']['site_id'] : self::import_predict_id();
		} else {
			$target = Digitalisimo_Backups_Site_Import::existing_target( (int) $into['site_id'] );
			if ( is_wp_error( $target ) ) throw new Digitalisimo_Backups_Permanent_Error( $target->get_error_message() );
		}
		$target['siteurl'] = $target['url'];
		$uploads = Digitalisimo_Backups_Site_Import::uploads( (int) $target['site_id'], $target );
		$destination = Digitalisimo_Backups_Site_Import::destination( $target );
		$pairs = Digitalisimo_Backups_Site_Import::media_pairs( $data, $source, $uploads );
		$plan = null;
		if ( ! $rollback ) {
			$plan = Digitalisimo_Backups_Site_Import::plan( $source, $destination, $args, $pairs );
			if ( $plan->problems() ) throw new Digitalisimo_Backups_Permanent_Error( implode( ' ', $plan->problems() ) );
		}
		$zip = self::restore_zip( $job );
		try {
			self::$import_rules = null;
			$rules = Digitalisimo_Backups_Site_Import::file_rules( $zip, $data, $source, self::restore_layout(), $uploads['basedir'] );
		} finally {
			self::close_quietly( $zip );
		}
		if ( '' === (string) $rules['to_uploads'] ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de medios del sitio de destino (' . basename( (string) $uploads['basedir'] ) . ') no está dentro de WordPress: no se puede importar ahí.' );
		$state = self::import_state( $job );
		self::restore_write_json( $job, 'into.json', array( 'target' => $target, 'uploads' => $uploads, 'rules' => $rules, 'pairs' => $pairs, 'created' => ! empty( $state['created'] ), 'creating' => ! empty( $state['creating'] ) ) );
		self::$import_rules = $rules;
		return array( $plan, $destination, $pairs );
	}

	// Copia previa: al reemplazar, la de ese sitio (sus tablas, las metas de sus usuarios en él y sus medios), bloqueada en
	// el historial; un sitio nuevo no existía y no tiene nada que copiar.
	private static function import_safety( array $job ) {
		$into = self::import_into( $job );
		if ( 'new' === $into['mode'] ) return array( 'next' => 'database', 'checkpoint' => array( 'done' => time(), 'file' => '', 'none' => 'new' ), 'message' => 'Sitio nuevo: no hay nada que copiar antes (si la importación falla tras empezar el cambio, el sitio se borra con los usuarios que creó). Importando la base de datos a tablas temporales…' );
		if ( Digitalisimo_Backups_Step_Budget::request_remaining() < 2 ) return array( 'next' => 'safety', 'checkpoint' => (array) ( $job['checkpoint']['safety'] ?? array() ), 'wait' => 1, 'message' => 'Copia previa del sitio: sigue en la petición siguiente…' );
		try {
			$file = self::import_copy( $job, self::import_target( $job ) );
		} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo crear la copia previa del sitio, así que no se cambió nada de la red: ' . $e->getMessage() );
		}
		return array( 'next' => 'database', 'checkpoint' => array( 'done' => time(), 'file' => $file ), 'message' => 'Copia previa del sitio creada y bloqueada (' . $file . '); importando la base de datos a tablas temporales…' );
	}
	// La copia de un sitio de la red (alcance `network-site`): su SQL (sus tablas y, de la red, los usuarios con metas suyas
	// y sólo esas metas), sus medios en wp-content/uploads del paquete y el manifiesto v3 con el SHA-256 de cada entrada.
	// Se verifica entera antes de registrarla. Un paso (como la extracción de un sitio): un sitio enorme, por WP-CLI.
	private static function import_copy( array $job, array $target ) {
		global $wpdb;
		$id = (int) $target['site_id'];
		$staging = self::restore_staging( $job ) . '/copy';
		if ( ! is_dir( $staging ) && ! @mkdir( $staging, 0700, true ) ) throw new Exception( 'No se pudo preparar la copia previa en el área de trabajo.' );
		$label = (string) ( $target['label'] ?? $id );
		$slug = substr( sanitize_title( str_replace( array( '.', '/' ), '-', $label ) ), 0, 80 );
		$file = Digitalisimo_Backups_Site_Import::FILE_PREFIX . ( '' !== $slug ? $slug : 'sitio-' . $id ) . '-' . gmdate( 'Ymd-His' ) . '-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ) . '.zip';
		$sql = $staging . '/database.sql';
		$export = new Digitalisimo_Backups_Site_Copy_Export( $id );
		$mode = self::database_sql_to_file( $export->tables(), $sql, $export );
		$uploads = Digitalisimo_Backups_Site_Import::uploads( $id, $target );
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$content = wp_normalize_path( (string) realpath( WP_CONTENT_DIR ) );
		$rel_content = 0 === strpos( trailingslashit( $content ), $root ) ? substr( $content, strlen( $root ) ) : 'wp-content';
		$zip_path = $staging . '/' . $file;
		@unlink( $zip_path );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) throw new Exception( 'No se pudo crear el ZIP de la copia previa.' );
		$closing = false;
		try {
			self::zip_add_file( $zip, $sql, 'database.sql' );
			$entries = array( 'database.sql' => array( 'size' => (int) filesize( $sql ), 'sha256' => (string) hash_file( 'sha256', $sql ) ) );
			$basedir = rtrim( wp_normalize_path( (string) $uploads['basedir'] ), '/' );
			$storage = trailingslashit( wp_normalize_path( (string) realpath( self::local_directory() ) ) );
			if ( is_link( $basedir ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de medios del sitio es un enlace simbólico.' );
			if ( is_dir( $basedir ) ) {
				foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $basedir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY ) as $entry ) {
					$path = wp_normalize_path( $entry->getPathname() );
					if ( 0 === strpos( $path, $storage ) ) continue;
					if ( $entry->isLink() ) throw new Digitalisimo_Backups_Permanent_Error( 'La copia del sitio no puede omitir el enlace simbólico ' . substr( $path, strlen( $root ) ) . ': sustitúyelo por el archivo real.' );
					if ( ! $entry->isFile() ) continue;
					$name = 'wordpress/' . $rel_content . '/uploads' . substr( $path, strlen( $basedir ) );
					$size = @filesize( $path );
					$hash = @hash_file( 'sha256', $path );
					if ( false === $size || false === $hash ) throw new Exception( 'No se pudo leer ' . substr( $path, strlen( $root ) ) . ' para la copia previa.' );
					self::zip_add_file( $zip, $path, $name );
					$entries[ $name ] = array( 'size' => (int) $size, 'sha256' => (string) $hash );
				}
			}
			$origin = Digitalisimo_Backups_Relocate::describe( false );
			unset( $origin['network'], $origin['networks'], $origin['sites'], $origin['config'] );
			$origin = array_merge( $origin, array( 'home' => untrailingslashit( (string) $target['url'] ), 'siteurl' => untrailingslashit( (string) ( $target['siteurl'] ?? $target['url'] ) ), 'uploads_dir' => $basedir, 'prefix' => (string) $wpdb->base_prefix . $id . '_', 'multisite' => false, 'subdomain' => false ) );
			$manifest = array(
				'format' => 'digitalisimo-backup/v3',
				'backup_format_version' => 3,
				'backup_id' => wp_generate_uuid4(),
				'scope' => Digitalisimo_Backups_Site_Import::SCOPE,
				'type' => 'full',
				'created_at' => gmdate( 'c' ),
				'completed_at' => gmdate( 'c' ),
				'home_url' => untrailingslashit( (string) $target['url'] ),
				'site_url' => untrailingslashit( (string) ( $target['siteurl'] ?? $target['url'] ) ),
				'wordpress_version' => (string) get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
				'database_version' => method_exists( $wpdb, 'db_version' ) ? (string) $wpdb->db_version() : '',
				'database_name' => defined( 'DB_NAME' ) ? DB_NAME : '',
				'database_size' => $entries['database.sql']['size'],
				'database_consistency' => (string) $mode,
				'table_prefix' => (string) $wpdb->base_prefix . $id . '_',
				'origin' => $origin,
				'database_tables' => array_values( array_map( array( $export, 'name' ), $export->tables() ) ),
				'multisite' => false,
				'file_count' => count( $entries ) - 1,
				'uncompressed_size' => array_sum( array_column( $entries, 'size' ) ),
				'checksum_algorithm' => 'sha256',
				'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
				'excluded_secrets' => array( 'options:' . Digitalisimo_Backups_Keys::SECRET_PREFIX . '*' ),
				'entries' => $entries,
				'job_id' => (string) $job['id'],
				'packer' => 'ziparchive',
				'site_copy' => array( 'site_id' => $id, 'label' => $label, 'url' => untrailingslashit( (string) $target['url'] ), 'members' => count( $export->members() ), 'uploads' => array( 'baseurl' => (string) $uploads['baseurl'] ) ),
			);
			self::zip_add_string( $zip, 'manifest.json', (string) wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
			$closing = true;
			if ( ! $zip->close() || ! is_file( $zip_path ) ) throw new Exception( 'El ZIP de la copia previa no se pudo cerrar.' );
		} catch ( Throwable $e ) {
			if ( ! $closing ) self::close_quietly( $zip );
			@unlink( $zip_path );
			@unlink( $sql );
			throw $e;
		}
		@unlink( $sql );
		self::private_file( $zip_path );
		$check = new ZipArchive();
		if ( true !== $check->open( $zip_path, ZipArchive::CHECKCONS ) ) throw new Exception( 'La copia previa no supera la comprobación estructural del ZIP.' );
		try { self::verify_zip_contents( $check ); } finally { self::close_quietly( $check ); }
		$final = rtrim( self::local_directory(), '/\\' ) . '/' . $file;
		if ( ! @rename( $zip_path, $final ) ) throw new Exception( 'No se pudo dejar la copia previa en el almacén.' );
		self::private_file( $final );
		self::remember( $final, 'local', Digitalisimo_Backups_Site_Import::SCOPE, array( 'id' => Digitalisimo_Backups_Site_Import::POLICY, 'type' => 'full' ), array(), $id, true, array( 'job' => (string) $job['id'], 'site_copy' => array( 'site_id' => $id, 'label' => $label ) ) );
		return $file;
	}

	// Base de datos (antes de importar): el sitio nuevo se crea ahora, ya en mantenimiento, con su dirección; si su ID no
	// es el previsto, el plan y los nombres de las tablas se calculan otra vez con el de verdad. Repetible tras una muerte:
	// el sitio lleva la marca de este trabajo y se reconoce.
	private static function import_create( array $job ) {
		$into = self::import_into( $job );
		$state = self::import_state( $job );
		if ( 'new' !== $into['mode'] || ! empty( $state['created'] ) ) return;
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT blog_id FROM ' . Digitalisimo_Backups_Relocate::quote_name( $wpdb->base_prefix . 'blogs' ) . ' WHERE domain = %s AND path = %s', (string) $into['domain'], (string) $into['path'] ) );
		if ( $id > 0 ) {
			// Un sitio en esa dirección sólo es éste si lo creó este trabajo (su marca en las opciones del sitio).
			$mark = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM ' . Digitalisimo_Backups_Relocate::quote_name( $wpdb->base_prefix . $id . '_options' ) . ' WHERE option_name = %s', 'digitalisimo_backups_import_job' ) );
			if ( empty( $state['creating'] ) || (string) $mark !== (string) $job['id'] ) throw new Digitalisimo_Backups_Permanent_Error( 'Mientras tanto se creó otro sitio en ' . (string) $into['label'] . ': elige otra dirección. No se cambió nada de la red.' );
		} else {
			$state['creating'] = true;
			self::restore_write_json( $job, 'into.json', $state );
			if ( ! function_exists( 'wp_insert_site' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Esta versión de WordPress no puede crear sitios de la red (wp_insert_site).' );
			$created = wp_insert_site( array( 'domain' => (string) $into['domain'], 'path' => (string) $into['path'], 'network_id' => function_exists( 'get_main_network_id' ) ? (int) get_main_network_id() : 1, 'user_id' => 0, 'title' => 'Importando…', 'options' => array( 'digitalisimo_backups_import_job' => (string) $job['id'] ) ) );
			if ( is_wp_error( $created ) ) throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo crear el sitio nuevo en ' . (string) $into['label'] . ': ' . $created->get_error_message() );
			$id = (int) $created;
		}
		$state['created'] = true;
		$state['target']['site_id'] = $id;
		$existing = Digitalisimo_Backups_Site_Import::existing_target( $id );
		if ( is_array( $existing ) ) $state['target']['url'] = $existing['url'];
		$state['target']['siteurl'] = $state['target']['url'];
		self::restore_write_json( $job, 'into.json', $state );
		Digitalisimo_Backups_Job_Store::event( $job['id'], 'site_created', array( 'site' => $id ) );
		// Con el sitio creado: el plan, las reglas y los nombres de las tablas con su ID y su carpeta de medios de verdad.
		$data = self::restore_read_json( $job, 'metadata.json' );
		$saved = self::restore_read_json( $job, 'plan.json' );
		list( $plan, $destination, $pairs ) = self::import_plan( $job, $data, (array) $saved['origin'], (array) $saved['args'] );
		$saved['destination'] = $destination;
		$saved['pairs'] = $pairs;
		self::restore_write_json( $job, 'plan.json', $saved );
		$rows = self::restore_read_json( $job, 'tables.json' );
		if ( $plan && $plan->needed() ) {
			$names = self::restore_dest_names( $plan, array_column( $rows, 'backup' ) );
			foreach ( $rows as $n => $row ) $rows[ $n ]['dest'] = (string) ( $names[ $row['backup'] ] ?? $row['dest'] );
			self::restore_write_json( $job, 'tables.json', $rows );
		}
	}
	// Base de datos (al terminar la importación): `users` y `usermeta` del respaldo salen de las tablas que se cambian; se
	// funden en las de la red en el cambio y sus temporales se retiran en la limpieza.
	private static function import_split( array $job ) {
		global $wpdb;
		if ( is_array( self::restore_read_json( $job, 'merge.json', false ) ) ) return;
		$prefix = (string) $wpdb->base_prefix . (int) self::import_target( $job )['site_id'] . '_';
		$rows = self::restore_read_json( $job, 'tables.json' );
		$merge = array();
		foreach ( $rows as $n => $row ) {
			foreach ( array( 'users', 'usermeta' ) as $base ) if ( $prefix . $base === (string) $row['dest'] ) { $merge[ $base ] = $row; unset( $rows[ $n ] ); }
		}
		self::restore_write_json( $job, 'merge.json', $merge );
		self::restore_write_json( $job, 'tables.json', array_values( $rows ) );
	}

	// El cambio: los usuarios se funden en la red, la autoría se reasigna, las opciones del sitio se preparan y las tablas
	// del sitio pasan de una vez (como siempre). Por tramos; devuelve el cursor con `swapped_at` al terminar.
	private static function import_swap( array $job, array $cursor, $deadline ) {
		global $wpdb;
		$into = self::import_into( $job );
		$target = self::import_target( $job );
		$id = (int) $target['site_id'];
		$prefix = (string) $wpdb->base_prefix . $id . '_';
		$rollback = ! empty( $job['spec']['rollback'] );
		$tables = self::restore_read_json( $job, 'tables.json' );
		$merge = (array) ( self::restore_read_json( $job, 'merge.json', false ) ?? array() );
		$state = self::restore_swap_state( $tables );
		if ( 'partial' === $state ) throw new Digitalisimo_Backups_Permanent_Error( 'Las tablas del sitio quedaron a medio cambiar (parte de las temporales sigue ahí); se vuelve a la copia previa.' );
		if ( 'before' === $state ) {
			$temp = array();
			foreach ( $tables as $row ) $temp[ (string) $row['dest'] ] = (string) $row['temp'];
			$map = array();
			if ( isset( $merge['users'], $merge['usermeta'] ) ) {
				$journal = (array) ( self::restore_read_json( $job, 'users.json', false ) ?? array() );
				$save = function( array $journal ) use ( $job ) { self::restore_write_json( $job, 'users.json', $journal ); };
				$journal = Digitalisimo_Backups_Site_Import::merge_users( array( 'users' => (string) $merge['users']['temp'], 'usermeta' => (string) $merge['usermeta']['temp'], 'site_id' => $id, 'domain' => (string) $target['domain'], 'mode' => (string) $into['mode'], 'rollback' => $rollback ), $journal, $save, $deadline );
				if ( 'done' !== $journal['step'] ) { $cursor['users'] = count( (array) $journal['map'] ); return $cursor; }
				$map = (array) $journal['map'];
				// Los usuarios ya están en la red y las tablas del sitio aún no pasaron: un fallo aquí (las pruebas lo provocan con
				// este gancho) lo deshace la vuelta a la copia previa (reemplazo) o el borrado del sitio nuevo, con este diario.
				if ( function_exists( 'do_action' ) ) do_action( 'digitalisimo_backups_site_import_merged', (string) $job['id'], $journal );
			}
			$remap = (array) ( self::restore_read_json( $job, 'remap.json', false ) ?? array() );
			// La copia de un sitio de esta red (su copia previa) ya lleva los ID y las opciones de la red: vuelve tal cual.
			if ( Digitalisimo_Backups_Site_Import::SCOPE === (string) ( self::restore_read_json( $job, 'metadata.json' )['scope'] ?? '' ) ) $remap['finished'] = $remap['finished'] ?? time();
			if ( empty( $remap['finished'] ) ) {
				$remap = Digitalisimo_Backups_Site_Import::remap( $map, $temp, $prefix, $remap, function( array $remap ) use ( $job ) { self::restore_write_json( $job, 'remap.json', $remap ); } );
				if ( isset( $temp[ $prefix . 'options' ] ) ) $remap['options'] = Digitalisimo_Backups_Site_Import::prepare_options( $temp[ $prefix . 'options' ], self::restore_table_exists( $prefix . 'options' ) ? $prefix . 'options' : '', Digitalisimo_Backups_Site_Import::themes_of( $temp[ $prefix . 'options' ] ) );
				$remap['finished'] = time();
				self::restore_write_json( $job, 'remap.json', $remap );
			}
			$marker = self::restore_staging( $job ) . '/swap.started';
			if ( false === @file_put_contents( $marker, (string) time() ) ) throw new Exception( 'No se pudo anotar el cambio de tablas en el área de trabajo.' );
			try {
				$cursor['mode'] = self::restore_swap_tables( $tables );
			} catch ( Throwable $e ) {
				$after = self::restore_swap_state( $tables );
				if ( 'before' === $after ) throw $e;
				if ( 'partial' === $after ) throw new Digitalisimo_Backups_Permanent_Error( 'Las tablas del sitio quedaron a medio cambiar tras un error (' . $e->getMessage() . '); se vuelve a la copia previa.' );
				$cursor['mode'] = 'confirmed';
			}
		} else {
			$cursor['mode'] = $cursor['mode'] ?? 'resumed';
		}
		// La vuelta a la copia de un sitio: lo que la importación que falló añadió a la red y la copia no tiene.
		if ( $rollback && empty( $cursor['undone'] ) ) {
			$undo = (array) ( $job['spec']['rollback']['undo'] ?? array() );
			$cursor['undone'] = Digitalisimo_Backups_Site_Import::undo_additions( (array) ( $undo['created'] ?? array() ), (array) ( $undo['units'] ?? array() ), ABSPATH );
		}
		if ( function_exists( 'clean_blog_cache' ) ) clean_blog_cache( $id );
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$cursor['swapped_at'] = $cursor['swapped_at'] ?? time();
		return $cursor;
	}
	// La importación ya cambió algo de la red (usuarios creados o metas del sitio escritas), aunque las tablas no hayan
	// pasado todavía: si falla, hay que deshacerlo.
	private static function import_touched( array $job ) {
		$journal = self::restore_read_json( $job, 'users.json', false );
		return is_array( $journal ) && ( ! empty( $journal['created'] ) || ! empty( $journal['pending'] ) || in_array( (string) ( $journal['step'] ?? '' ), array( 'metas', 'others', 'done' ), true ) );
	}

	// Direcciones y cachés del sitio (E1 dentro del sitio) y su fila en la red.
	private static function import_finish( array $job, $plan ) {
		$id = (int) self::import_target( $job )['site_id'];
		$report = null;
		if ( $plan ) {
			$switched = function_exists( 'switch_to_blog' ) && (int) get_current_blog_id() !== $id;
			if ( $switched ) switch_to_blog( $id );
			try {
				$report = $plan->finish( array_column( self::restore_read_json( $job, 'tables.json' ), 'dest' ) );
			} finally {
				if ( $switched ) restore_current_blog();
			}
		}
		if ( function_exists( 'wp_update_site' ) && null !== Digitalisimo_Backups_Site_Import::site_row( $id ) ) {
			$public = function_exists( 'get_blog_option' ) ? get_blog_option( $id, 'blog_public', 1 ) : 1;
			wp_update_site( $id, array( 'last_updated' => current_time( 'mysql', true ), 'public' => (int) $public ) );
		}
		if ( function_exists( 'clean_blog_cache' ) ) clean_blog_cache( $id );
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		return $report;
	}
	// Limpieza: las temporales de `users` y `usermeta` del respaldo (ya fundidos).
	private static function import_cleanup( array $job ) {
		global $wpdb;
		$warnings = array();
		foreach ( (array) ( self::restore_read_json( $job, 'merge.json', false ) ?? array() ) as $row ) {
			if ( false === $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( (string) $row['temp'] ) ) ) $warnings[] = 'No se pudo retirar la tabla ' . $row['temp'] . '.';
		}
		return $warnings;
	}
	// Lo que la importación que falla deja hecho y la vuelta a la copia debe quitar: los usuarios que creó y las carpetas
	// de plugins y temas que instaló.
	private static function import_undo_list( array $job ) {
		$journal = (array) ( self::restore_read_json( $job, 'users.json', false ) ?? array() );
		$created = array_merge( (array) ( $journal['created'] ?? array() ), array() );
		// Uno a medio crear (la muerte entre el INSERT y su anotación): está en la red con el nombre que se le dio.
		if ( ! empty( $journal['pending'] ) ) {
			global $wpdb;
			$users = (string) ( $wpdb->users ?? '' ) ? (string) $wpdb->users : (string) $wpdb->base_prefix . 'users';
			foreach ( (array) $journal['pending'] as $login ) {
				$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . Digitalisimo_Backups_Relocate::quote_name( $users ) . ' WHERE user_login = %s', (string) $login ) );
				if ( $id > 0 ) $created[] = $id;
			}
		}
		$rules = (array) ( self::import_state( $job )['rules'] ?? array() );
		return array( 'created' => array_values( array_unique( array_map( 'intval', $created ) ) ), 'units' => array_keys( (array) ( $rules['units'] ?? array() ) ) );
	}
	// El final de un trabajo que no terminó bien. Un sitio nuevo se borra con lo que la importación añadió (usuarios,
	// plugins y temas), haya llegado o no al cambio; un reemplazo vuelve a su copia (restore_rollback_job(), con lo que
	// hay que quitar). Devuelve el mensaje, o null para seguir con el final de siempre.
	private static function import_terminal( array $job, $status, $message ) {
		global $wpdb;
		$into = self::import_into( $job );
		if ( ! $into || ! empty( $job['spec']['rollback'] ) ) return null;
		if ( 'cleanup' === (string) ( $job['phase'] ?? '' ) && 'cancelled' !== $status ) return null;
		$state = self::import_state( $job );
		$changed = self::restore_changed( $job );
		if ( 'replace' === $into['mode'] ) {
			if ( ! $changed ) foreach ( (array) ( self::restore_read_json( $job, 'merge.json', false ) ?? array() ) as $row ) $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( (string) $row['temp'] ) );
			return null;
		}
		if ( empty( $state['created'] ) && ! $changed ) return null;
		$id = (int) ( $state['target']['site_id'] ?? 0 );
		$done = array();
		try {
			$undo = self::import_undo_list( $job );
			$tables = array();
			foreach ( (array) self::restore_read_json( $job, 'tables.json', false ) as $row ) foreach ( array( 'dest', 'temp', 'old' ) as $key ) $tables[] = (string) $row[ $key ];
			foreach ( (array) ( self::restore_read_json( $job, 'merge.json', false ) ?? array() ) as $row ) $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( (string) $row['temp'] ) );
			foreach ( $tables as $table ) if ( false !== strpos( $table, 'dgbk' ) ) $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( $table ) );
			if ( $id > 0 ) Digitalisimo_Backups_Site_Import::delete_site( $id, $tables );
			$done = Digitalisimo_Backups_Site_Import::undo_additions( $undo['created'], $changed ? $undo['units'] : array(), ABSPATH );
			$tree = self::restore_read_json( $job, 'tree.json', false );
			if ( is_array( $tree ) && ! empty( $tree['root'] ) ) self::restore_remove_tree( (string) $tree['root'] );
			Digitalisimo_Backups_Job_Store::event( $job['id'], 'site_import_undone', array( 'site' => $id, 'users' => (int) ( $done['users'] ?? 0 ), 'units' => count( (array) ( $done['units'] ?? array() ) ) ) );
		} catch ( Throwable $e ) {
			Digitalisimo_Backups_Restore_Maintenance::release();
			return rtrim( (string) $message, '.' ) . '. No se pudo borrar del todo el sitio nuevo ' . (string) $into['label'] . ': ' . $e->getMessage() . ' Bórralo desde Sitios de la red; el resto de la red no cambió.';
		}
		try { Digitalisimo_Backups_Job_Store::remove_staging( $job['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		Digitalisimo_Backups_Restore_Maintenance::release();
		if ( 'cancelled' === $status ) return 'Importación cancelada: el sitio nuevo ' . (string) $into['label'] . ' se borró y la red quedó como estaba.';
		return rtrim( (string) $message, '.' ) . '. La importación no terminó y se deshizo: el sitio nuevo ' . (string) $into['label'] . ' se borró' . ( ! empty( $done['users'] ) ? ', con los ' . (int) $done['users'] . ' usuarios que había creado' : '' ) . ( ! empty( $done['units'] ) ? ' y los plugins o temas que había instalado' : '' ) . '; el resto de la red no cambió.';
	}

	// Informe de la importación para la respuesta, el correo, REST y WP-CLI (sólo nombres de usuario, ningún correo).
	private static function import_result( array $job ) {
		$into = self::import_into( $job );
		if ( ! $into ) return null;
		$target = self::import_target( $job );
		$state = self::import_state( $job );
		$rules = (array) ( $state['rules'] ?? array() );
		$journal = (array) ( self::restore_read_json( $job, 'users.json', false ) ?? array() );
		$remap = (array) ( self::restore_read_json( $job, 'remap.json', false ) ?? array() );
		$kinds = array_count_values( array_map( 'strval', (array) ( $journal['kind'] ?? array() ) ) );
		$renamed = array();
		if ( ! empty( $kinds['renamed'] ) ) {
			global $wpdb;
			$users = (string) ( $wpdb->users ?? '' ) ? (string) $wpdb->users : (string) $wpdb->base_prefix . 'users';
			foreach ( (array) $journal['kind'] as $old => $kind ) {
				if ( 'renamed' !== $kind || count( $renamed ) >= 50 ) continue;
				$login = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_login FROM ' . Digitalisimo_Backups_Relocate::quote_name( $users ) . ' WHERE ID = %d', (int) $journal['map'][ $old ] ) );
				if ( '' !== $login ) $renamed[] = $login;
			}
		}
		$users = array( 'matched' => (int) ( $kinds['matched'] ?? 0 ), 'by_email' => (int) ( $kinds['email'] ?? 0 ), 'created' => (int) ( $kinds['created'] ?? 0 ), 'renamed' => (int) ( $kinds['renamed'] ?? 0 ), 'kept_admins' => count( (array) ( $journal['admins'] ?? array() ) ), 'missing' => (int) ( $kinds['missing'] ?? 0 ) );
		$warnings = Digitalisimo_Backups_Site_Import::warnings( $rules );
		$text = ( empty( $job['spec']['rollback'] ) ? ( 'new' === $into['mode'] ? 'Importado como sitio nuevo de la red: ' : 'Sitio de la red reemplazado: ' ) : 'Sitio de la red devuelto a su copia previa: ' ) . (string) $target['url'] . ' (sitio ' . (int) $target['site_id'] . ').';
		if ( empty( $job['spec']['rollback'] ) ) {
			$text .= ' Usuarios: ' . $users['matched'] . ' ya eran de la red (entran con su contraseña de la red), ' . $users['by_email'] . ' coincidieron por correo con una cuenta de la red, ' . ( $users['created'] + $users['renamed'] ) . ' se crearon con su contraseña del respaldo' . ( $renamed ? ' (con otro nombre porque el suyo ya era de otra persona en la red: ' . implode( ', ', $renamed ) . '; también entran con su correo)' : '' ) . '.';
			if ( $users['kept_admins'] ) $text .= ' ' . $users['kept_admins'] . ' administradores del sitio siguen siéndolo.';
			$installed = array_merge( (array) ( $rules['installed']['plugins'] ?? array() ), (array) ( $rules['installed']['themes'] ?? array() ) );
			if ( $installed ) $text .= ' Instalados en la red: ' . implode( ', ', array_slice( $installed, 0, 10 ) ) . ' (activos sólo en este sitio).';
			if ( ! empty( $remap['options']['themes'] ) ) $text .= ' Tema habilitado sólo para este sitio: ' . implode( ', ', (array) $remap['options']['themes'] ) . '.';
		}
		$orphans = 0;
		foreach ( (array) ( $remap['report'] ?? array() ) as $column ) $orphans += (int) ( $column['orphans'] ?? 0 );
		if ( $orphans ) $text .= ' ' . $orphans . ' contenidos eran de un usuario que el respaldo ya no tenía: quedan sin autor.';
		if ( ! empty( $remap['options']['backups_options'] ) ) $text .= ' La configuración de Backups del respaldo no se aplicó: la de la red manda.';
		return array(
			'mode' => (string) $into['mode'],
			'site_id' => (int) $target['site_id'],
			'url' => (string) $target['url'],
			'users' => $users,
			'renamed' => $renamed,
			'skipped' => array_values( array_map( 'strval', (array) ( $journal['skipped'] ?? array() ) ) ),
			'plugins' => array_values( (array) ( $rules['installed']['plugins'] ?? array() ) ),
			'themes' => array_values( (array) ( $rules['installed']['themes'] ?? array() ) ),
			'kept' => (array) ( $rules['kept'] ?? array() ),
			'warnings' => $warnings,
			'text' => $text,
		);
	}
}
