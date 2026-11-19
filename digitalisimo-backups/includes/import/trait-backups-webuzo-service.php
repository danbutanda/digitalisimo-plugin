<?php
defined( 'ABSPATH' ) || exit;

/**
 * Casos de uso de la importación de respaldos de cuenta de Webuzo, compartidos por la pantalla, REST y WP-CLI: ver los
 * respaldos (los del home de la cuenta y los del almacén), analizarlos e importar un WordPress suyo, traerlos de un
 * destino remoto y borrar los del almacén. La conversión la hace el motor de trabajos (Digitalisimo_Backups_Webuzo_Import,
 * que se carga sólo al usarse); el resultado es un ZIP que entra en la lista como «Importado» y se restaura con la
 * restauración por pasos, con RESTAURAR SIN FIRMA.
 *
 * Como Backuply y UpdraftPlus, se gestiona desde el sitio principal (o la administración de red). Nunca se devuelve una
 * ruta del servidor.
 */
trait Digitalisimo_Backups_Webuzo_Service {
	/** Carga la clase de la importación de Webuzo (sólo cuando hace falta). */
	public static function load_webuzo_import() {
		if ( ! class_exists( 'Digitalisimo_Backups_Webuzo_Import', false ) ) require_once __DIR__ . '/class-webuzo-import.php';
	}
	/** Los respaldos de cuenta de Webuzo que se pueden importar (home de la cuenta y almacén), con su análisis si lo hay. */
	public static function service_webuzo() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_webuzo_import();
		try {
			$items = Digitalisimo_Backups_Webuzo_Import::sources();
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return array(
			'detected' => (bool) $items,
			'backups' => $items,
			'note' => 'Importar toma de un respaldo de cuenta de Webuzo un WordPress (el único, o el que elijas si trae varios) con su base de datos y lo convierte, sin tocar el sitio, en un ZIP de Backups sin firma. Entra en la lista de respaldos como «Importado» y se restaura desde ahí con la restauración por pasos (copia previa, vuelta atrás, otra dirección), escribiendo RESTAURAR SIN FIRMA. Nada más de la cuenta (correo, certificados, DNS, FTP) se importa.',
		);
	}
	/**
	 * Encola la importación de un respaldo de Webuzo por su nombre; `source` (home o store) lo elige si está en los dos
	 * sitios y `site` es la carpeta del WordPress (relativa al home) si el respaldo trae varios. Sin `site`, el trabajo
	 * analiza el respaldo: con uno solo lo importa y con varios termina con la lista para elegir. Responde enseguida (202).
	 */
	public static function service_webuzo_import( $name, array $args = array() ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_webuzo_import();
		$name = (string) $name;
		$origin = (string) ( $args['source'] ?? '' );
		$site = Digitalisimo_Backups_Webuzo_Import::clean_site( (string) ( $args['site'] ?? '' ) );
		if ( '' !== $origin && ! in_array( $origin, array( 'home', 'store' ), true ) ) return self::service_error( 'webuzo_source', 'El origen debe ser home (el home de la cuenta de Webuzo) o store (el almacén privado).', 400 );
		if ( ! preg_match( Digitalisimo_Backups_Webuzo_Import::NAME, $name ) ) return self::service_error( 'webuzo_name', 'Indica el nombre de un respaldo de Webuzo (.tar.gz) tal como aparece en la lista.', 400 );
		if ( null === $site ) return self::service_error( 'webuzo_site', 'La carpeta del WordPress no es válida: indícala como aparece en el análisis (por ejemplo, public_html).', 400 );
		try {
			if ( '' === $origin ) foreach ( array( 'home', 'store' ) as $candidate ) if ( '' !== Digitalisimo_Backups_Webuzo_Import::source_path( $candidate, $name ) ) { $origin = $candidate; break; }
			$path = '' === $origin ? '' : Digitalisimo_Backups_Webuzo_Import::source_path( $origin, $name );
			if ( '' === $path ) return self::service_error( 'not_found', 'El respaldo de Webuzo solicitado no está en el home de la cuenta ni en el almacén privado.', 404 );
			$item = Digitalisimo_Backups_Webuzo_Import::describe( $origin, $path );
			if ( ! $item || ! $item['importable'] ) return self::service_error( 'webuzo_busy', $item ? $item['problem'] : 'El respaldo de Webuzo no está disponible.', 409 );
			// Que no se confundan: un respaldo del plugin Backuply va por su importador.
			if ( 'backuply' === Digitalisimo_Backups_Webuzo_Import::sniff( $path ) ) return self::service_error( 'webuzo_format', 'Este archivo es un respaldo del plugin Backuply, no de una cuenta de Webuzo: impórtalo con «Importar» de su fila de Backuply en Herramientas → «Importar de Backuply, UpdraftPlus o Webuzo» (o con wp digitalisimo-backups backuply import).', 422 );
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) {
				if ( Digitalisimo_Backups_Jobs::is_restore( $job ) ) return self::service_error( 'busy', 'Hay una restauración en curso: importa el respaldo de Webuzo cuando termine.', 409, array( 'job' => (string) $job['id'] ) );
				if ( Digitalisimo_Backups_Import_Job::is_import( $job ) && 'import:webuzo:' . $origin . ':' . $name === ( $job['spec']['policy'] ?? '' ) ) return self::service_error( 'duplicate', 'Ese respaldo de Webuzo ya se está importando o analizando (trabajo ' . $job['id'] . ').', 409, array( 'job' => (string) $job['id'] ) );
			}
			$spec = Digitalisimo_Backups_Webuzo_Import::spec( $origin, $name, (string) $site, $args );
			$queued = Digitalisimo_Backups_Jobs::enqueue_import( $spec );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		if ( ! empty( $queued['busy'] ) ) return self::service_error( 'busy', 'Hay una restauración en curso: importa el respaldo de Webuzo cuando termine.', 409, array( 'job' => (string) $queued['job']['id'] ) );
		if ( ! empty( $queued['duplicate'] ) ) return self::service_error( 'duplicate', 'Ese respaldo de Webuzo ya se está importando o analizando (trabajo ' . $queued['job']['id'] . ').', 409, array( 'job' => (string) $queued['job']['id'] ) );
		Digitalisimo_Backups_Jobs::schedule( ! empty( $args['spawn'] ) );
		$view = self::service_job_view( $queued['job'] );
		// Por tamaño: por la web puede no caber (cada tramo vuelve a descomprimir lo ya leído); se avisa ya al encolar.
		if ( ! empty( $item['large'] ) && 'cli' !== (string) ( $args['via'] ?? '' ) && is_array( $view ) ) $view['warning'] = (string) $item['advice'];
		return $view;
	}
	/**
	 * Trae al almacén (`webuzo/`) un respaldo de cuenta de Webuzo de un destino remoto (el contrato `list()` y `get()`, en
	 * streaming, con el tamaño comprobado), para importarlo después. Una sola petición: los grandes, con WP-CLI.
	 */
	public static function service_webuzo_fetch( $connection, $name ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_webuzo_import();
		$connection = (string) $connection;
		$name = (string) $name;
		if ( ! preg_match( Digitalisimo_Backups_Webuzo_Import::NAME, $name ) ) return self::service_error( 'webuzo_name', 'Indica el nombre de un respaldo de Webuzo (.tar.gz) tal como aparece en el destino.', 400 );
		if ( '' === Digitalisimo_Backups_Destinations::type_of( $connection ) ) return self::service_error( 'not_found', 'El destino ' . $connection . ' no existe.', 404 );
		$problem = self::credentials_problem( $connection );
		if ( '' !== $problem ) return self::service_error( 'credentials', $problem, 409 );
		$part = '';
		try {
			$store = Digitalisimo_Backups_Webuzo_Import::store_directory( true );
			$destination = self::destination( $connection );
			$found = null;
			foreach ( (array) $destination->list() as $entry ) if ( (string) ( $entry['name'] ?? '' ) === $name ) $found = $entry;
			if ( ! $found ) return self::service_error( 'not_found', 'El destino no tiene un archivo llamado ' . $name . '.', 404 );
			$size = (int) ( $found['size'] ?? 0 );
			$free = Digitalisimo_Backups_Archive_Inspector::free_space( $store );
			if ( null !== $free && $size > 0 && $free < $size * 3 + Digitalisimo_Backups_Archive_Inspector::MARGIN ) return self::service_error( 'space', 'No hay espacio en el almacén privado para traer e importar este respaldo: se necesitan unos ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size * 3 + Digitalisimo_Backups_Archive_Inspector::MARGIN ) . ' y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.', 507 );
			if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
			Digitalisimo_Backups_Webuzo_Import::clean_partials();
			$part = $store . '/.incoming-' . bin2hex( random_bytes( 8 ) ) . '.part';
			$destination->get( (array) $found['remote'], $part );
			clearstatcache( true, $part );
			if ( ! is_file( $part ) || ( $size > 0 && (int) filesize( $part ) !== $size ) ) throw new Exception( 'La descarga de ' . $name . ' no tiene el tamaño que lista el destino; no se usa.' );
			if ( 'webuzo' !== Digitalisimo_Backups_Webuzo_Import::sniff( $part ) ) throw new Exception( $name . ' no es un respaldo de cuenta de Webuzo (.tar.gz que empieza por backuply-data/); no se usa.' );
			$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $store, $part, $name ) {
				$file = $name;
				while ( file_exists( $store . '/' . $file ) || is_link( $store . '/' . $file ) ) $file = substr( $name, 0, -7 ) . '-' . bin2hex( random_bytes( 3 ) ) . '.tar.gz';
				if ( ! @rename( $part, $store . '/' . $file ) ) throw new Exception( 'No se pudo mover el respaldo traído al almacén privado.' );
				@chmod( $store . '/' . $file, 0600 );
				return $file;
			} );
			$part = '';
			return array( 'source' => 'store', 'name' => $file, 'size' => (int) filesize( $store . '/' . $file ), 'message' => 'Respaldo de Webuzo traído al almacén: ' . $file . '. Impórtalo para elegir su WordPress y convertirlo en un respaldo de Backups.' );
		} catch ( Throwable $e ) {
			return self::service_error( 'fetch', 'No se pudo traer el respaldo de Webuzo del destino: ' . $e->getMessage(), 500 );
		} finally {
			if ( '' !== $part ) @unlink( $part );
		}
	}
	/** Borra un `.tar.gz` de `webuzo/` del almacén (y su análisis). Los del home de la cuenta son del panel y no se tocan. */
	public static function service_webuzo_delete( $name ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::load_webuzo_import();
		$name = (string) $name;
		if ( ! preg_match( Digitalisimo_Backups_Webuzo_Import::NAME, $name ) ) return self::service_error( 'webuzo_name', 'Indica el nombre de un respaldo de Webuzo (.tar.gz) del almacén privado.', 400 );
		try {
			$path = Digitalisimo_Backups_Webuzo_Import::source_path( 'store', $name );
			if ( '' === $path ) return self::service_error( 'not_found', 'El almacén privado no tiene ese respaldo de Webuzo.', 404 );
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Import_Job::is_webuzo( $job ) && 'store' === ( $job['spec']['source']['origin'] ?? '' ) && $name === ( $job['spec']['source']['name'] ?? '' ) ) return self::service_error( 'busy', 'Ese respaldo de Webuzo se está importando (trabajo ' . $job['id'] . '): cancela la importación o espera a que termine.', 409, array( 'job' => (string) $job['id'] ) );
			clearstatcache( true, $path );
			Digitalisimo_Backups_Webuzo_Import::forget_analysis( 'store', $name, (int) filesize( $path ), (int) filemtime( $path ) );
			if ( ! @unlink( $path ) ) return self::service_error( 'storage', 'No se pudo borrar el respaldo de Webuzo del almacén privado.', 500 );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return array( 'name' => $name, 'deleted' => true );
	}
	/** «Importar» (o «Importar este WordPress») de la tarjeta (admin-post). */
	public static function webuzo_import_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_WEBUZO_IMPORT );
		// El nombre no pasa por sanitize_file_name() (lo cambiaría); lo valida la lista blanca del servicio, y la carpeta, clean_site().
		$result = self::service_webuzo_import( (string) wp_unslash( $_POST['webuzo_file'] ?? '' ), array( 'source' => sanitize_key( wp_unslash( $_POST['webuzo_source'] ?? '' ) ), 'site' => (string) wp_unslash( $_POST['webuzo_site'] ?? '' ), 'via' => 'admin-post', 'network' => ! empty( $_POST['digitalisimo_backups_network'] ), 'spawn' => true ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, 'Importación de Webuzo en cola (trabajo ' . $result['id'] . '): su avance se ve en el panel de esta sección. Si el respaldo trae varios WordPress, al terminar el análisis eliges cuál; al terminar, el respaldo aparece en la lista como «Importado».', null, '', (string) $result['id'] );
	}
	/** «Eliminar» de la tarjeta (admin-post): sólo los del almacén privado. */
	public static function webuzo_delete_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_WEBUZO_DELETE );
		$result = self::service_webuzo_delete( (string) wp_unslash( $_POST['webuzo_file'] ?? '' ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, 'Respaldo de Webuzo borrado del almacén privado: ' . $result['name'] . '.' );
	}
	/** Tarjeta «Respaldo de cuenta de Webuzo». $items: los de sources(), si ya se buscaron. */
	private static function webuzo_panel( $network = false, $items = null ) {
		self::load_webuzo_import();
		if ( ! is_array( $items ) ) {
			try {
				$items = Digitalisimo_Backups_Webuzo_Import::sources();
			} catch ( Exception $e ) {
				$items = array();
			}
		}
		echo '<h3 id="digitalisimo-backups-webuzo">Respaldo de cuenta de Webuzo</h3><p class="description">El respaldo completo de una cuenta que hace el panel de Webuzo (<code>&lt;usuario&gt;_backup_…tar.gz</code>): busca en el home de la cuenta y en el almacén privado. Importar toma un WordPress suyo (si trae varios, eliges cuál) con su base de datos y lo convierte, sin tocar el sitio, en un respaldo de Backups que entra en la lista como «Importado» y se restaura desde ahí escribiendo RESTAURAR SIN FIRMA, también en otro dominio. Nada más de la cuenta (correo, certificados, DNS, FTP) se importa. Uno de otro servidor se sube en «Subir un respaldo» o se trae de un destino con <code>wp digitalisimo-backups webuzo fetch</code>.</p>';
		if ( ! $items ) { echo '<p>No se detectaron respaldos de cuenta de Webuzo.</p>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Archivo</th><th>Origen</th><th>Tamaño</th><th>Acción</th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$hidden = function( $site = null ) use ( $item, $network ) {
				return '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_WEBUZO_IMPORT ) . '"><input type="hidden" name="webuzo_file" value="' . esc_attr( $item['name'] ) . '"><input type="hidden" name="webuzo_source" value="' . esc_attr( $item['source'] ) . '">' . ( null !== $site ? '<input type="hidden" name="webuzo_site" value="' . esc_attr( $site ) . '">' : '' ) . self::return_field( 'tools', 'import' ) . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' );
			};
			echo '<tr><td>' . esc_html( $item['date'] ) . '</td><td>' . esc_html( $item['name'] ) . ( $item['contains'] ? '<br><small>' . esc_html( 'Contiene: ' . implode( ', ', $item['contains'] ) ) . '</small>' : '' ) . '</td><td>' . esc_html( 'home' === $item['source'] ? 'Home de la cuenta' : 'Almacén privado' ) . '</td><td>' . esc_html( self::bytes_label( (int) $item['size'] ) ) . '</td><td>';
			foreach ( (array) $item['imported'] as $file ) echo '<small>Importado como ' . esc_html( $file ) . '</small><br>';
			if ( '' !== (string) ( $item['advice'] ?? '' ) ) echo '<small class="digitalisimo-backups-warning">' . esc_html( (string) $item['advice'] ) . '</small><br>';
			if ( '' !== $item['problem'] ) echo '<small>' . esc_html( $item['problem'] ) . '</small><br>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( self::ACTION_WEBUZO_IMPORT );
			echo $hidden() . '<button class="button button-small"' . ( $item['importable'] ? '' : ' disabled' ) . '>' . ( $item['analyzed'] ? 'Analizar de nuevo' : 'Importar' ) . '</button></form>';
			if ( 'store' === $item['source'] ) {
				echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'¿Borrar este respaldo de Webuzo del almacén privado?\')">';
				wp_nonce_field( self::ACTION_WEBUZO_DELETE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_WEBUZO_DELETE ) . '"><input type="hidden" name="webuzo_file" value="' . esc_attr( $item['name'] ) . '">' . self::return_field( 'tools', 'import' ) . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small button-link-delete">Eliminar</button></form>';
			}
			echo '</td></tr>';
			// Lo que trae (del análisis): cada WordPress con su botón; sin base, sin botón y el porqué.
			if ( $item['sites'] ) {
				echo '<tr><td></td><td colspan="4"><table class="widefat"><thead><tr><th>WordPress</th><th>Versión</th><th>Prefijo</th><th>Base de datos</th><th>Tamaño</th><th></th></tr></thead><tbody>';
				foreach ( (array) $item['sites'] as $site ) {
					echo '<tr><td><strong>' . esc_html( (string) $site['root'] ) . '</strong>' . ( '' !== (string) $site['domain'] ? '<br><small>' . esc_html( (string) $site['domain'] ) . '</small>' : '' ) . ( ! empty( $site['multisite'] ) ? '<br><small>Red (multisitio)</small>' : '' ) . '</td><td>' . esc_html( (string) $site['version'] ) . '</td><td><code>' . esc_html( (string) $site['prefix'] ) . '</code></td><td>' . esc_html( ! empty( $site['database'] ) ? (string) $site['db'] . ' (' . self::bytes_label( (int) $site['database_size'] ) . ' comprimida)' : 'No viene en el respaldo' ) . '</td><td>' . esc_html( self::bytes_label( (int) $site['size'] ) ) . '</td><td>';
					if ( ! empty( $site['importable'] ) ) {
						echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
						wp_nonce_field( self::ACTION_WEBUZO_IMPORT );
						echo $hidden( (string) $site['root'] ) . '<button class="button button-small button-primary"' . ( $item['importable'] ? '' : ' disabled' ) . '>Importar este WordPress</button></form>';
					} else {
						echo '<small>' . esc_html( (string) $site['problem'] ) . '</small>';
					}
					echo '</td></tr>';
				}
				echo '</tbody></table></td></tr>';
			}
		}
		echo '</tbody></table>';
	}
	/**
	 * Un respaldo de cuenta de Webuzo subido (un `.tar.gz` que empieza por `backuply-data/`) entra en `webuzo/` del
	 * almacén, con un nombre libre, para importarlo. Se reconoce solo aunque se suba como si fuera de Backuply.
	 */
	private static function upload_webuzo_done( array $state, $path ) {
		self::load_webuzo_import();
		self::upload_step( 'register', 'Guardándolo en el almacén privado para importarlo…' );
		$store = Digitalisimo_Backups_Webuzo_Import::store_directory( true );
		$name = Digitalisimo_Backups_Webuzo_Import::upload_name( (string) $state['name'] );
		if ( '' === $name ) $name = 'webuzo-upload-' . gmdate( 'Ymd-His' ) . '.tar.gz';
		$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $path, $store, $name ) {
			$file = $name;
			while ( file_exists( $store . '/' . $file ) || is_link( $store . '/' . $file ) ) $file = substr( $name, 0, -7 ) . '-' . bin2hex( random_bytes( 3 ) ) . '.tar.gz';
			if ( ! @rename( $path, $store . '/' . $file ) ) throw new Exception( 'No se pudo mover el respaldo de Webuzo subido al almacén privado.' );
			@chmod( $store . '/' . $file, 0600 );
			return $file;
		} );
		$result = array( 'file' => $file, 'size' => (int) $state['size'], 'registered' => false, 'container' => 'webuzo', 'webuzo' => array( 'source' => 'store', 'name' => $file ), 'verified' => false, 'trust' => 'untrusted', 'confirmation' => 'RESTAURAR SIN FIRMA', 'needs_secret' => false, 'compatible' => false, 'problems' => array( 'Es un respaldo de cuenta de Webuzo: impórtalo para elegir su WordPress y convertirlo en un respaldo de Backups (Herramientas → «Importar de Backuply, UpdraftPlus o Webuzo», o wp digitalisimo-backups webuzo import ' . $file . ').' ) );
		unset( $state['check'] );
		$state['phase'] = 'done';
		$state['result'] = $result;
		$state['updated_at'] = time();
		Digitalisimo_Backups_Uploads::write( $state );
		return $result;
	}
	/** Un `.tar.gz` subido es un respaldo de cuenta de Webuzo (por sus primeras entradas). */
	private static function upload_is_webuzo( $path ) {
		self::load_webuzo_import();
		return 'webuzo' === Digitalisimo_Backups_Webuzo_Import::sniff( $path );
	}
}
