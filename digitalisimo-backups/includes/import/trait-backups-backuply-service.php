<?php
defined( 'ABSPATH' ) || exit;

/**
 * Casos de uso de la importación de respaldos de Backuply (I9), compartidos por la pantalla, REST y WP-CLI: ver si
 * Backuply está en el sitio y qué respaldos suyos hay, encolar su importación y traer uno desde un destino remoto al
 * almacén. La conversión la hace el motor de trabajos (Digitalisimo_Backups_Import_Job); el resultado es un ZIP «sin
 * registro» del almacén que se restaura como cualquier otro, con RESTAURAR SIN FIRMA.
 *
 * Como la subida, se gestiona desde el sitio principal (o la administración de red): el almacén es de toda la
 * instalación. Nunca se devuelve una ruta del servidor.
 */
trait Digitalisimo_Backups_Backuply_Service {
	/** Si Backuply está en este sitio y los respaldos suyos que se pueden importar (de su carpeta y del almacén). */
	public static function service_backuply() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		try {
			$items = Digitalisimo_Backups_Backuply_Import::sources();
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		$found = Digitalisimo_Backups_Backuply_Import::detected();
		return array(
			'detected' => $found['installed'] || $found['folder'],
			'installed' => $found['installed'],
			'active' => $found['active'],
			'backups' => $items,
			'note' => 'Importar convierte el .tar.gz de Backuply en un ZIP de Backups sin firma (no toca el sitio). Después aparece en Herramientas → «Restaurar un archivo suelto» y se restaura con la restauración por pasos, escribiendo RESTAURAR SIN FIRMA. Sólo se importan respaldos completos (base de datos y archivos).',
		);
	}
	/**
	 * Encola la importación de un respaldo de Backuply por su nombre; `source` (backuply o store) lo elige si el mismo
	 * nombre está en los dos sitios. Responde enseguida con el trabajo (202): lo hacen avanzar WP-Cron, la pantalla,
	 * `POST /jobs/tick` o WP-CLI.
	 */
	public static function service_backuply_import( $name, array $args = array() ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$name = (string) $name;
		$origin = (string) ( $args['source'] ?? '' );
		if ( '' !== $origin && ! in_array( $origin, array( 'backuply', 'store' ), true ) ) return self::service_error( 'backuply_source', 'El origen debe ser backuply (la carpeta de Backuply de este sitio) o store (el almacén privado).', 400 );
		if ( ! preg_match( Digitalisimo_Backups_Backuply_Import::NAME, $name ) ) return self::service_error( 'backuply_name', 'Indica el nombre de un respaldo de Backuply (.tar.gz) tal como aparece en la lista.', 400 );
		try {
			if ( '' === $origin ) {
				foreach ( array( 'backuply', 'store' ) as $candidate ) if ( '' !== Digitalisimo_Backups_Backuply_Import::source_path( $candidate, $name ) ) { $origin = $candidate; break; }
			}
			if ( '' === $origin || '' === Digitalisimo_Backups_Backuply_Import::source_path( $origin, $name ) ) return self::service_error( 'not_found', 'El respaldo de Backuply solicitado no está en la carpeta de Backuply de este sitio ni en el almacén privado.', 404 );
			$spec = Digitalisimo_Backups_Backuply_Import::spec( $origin, $name, $args );
			$queued = Digitalisimo_Backups_Jobs::enqueue_import( $spec );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		if ( ! empty( $queued['busy'] ) ) return self::service_error( 'busy', 'Hay una restauración en curso: importa el respaldo de Backuply cuando termine.', 409, array( 'job' => (string) $queued['job']['id'] ) );
		if ( ! empty( $queued['duplicate'] ) ) return self::service_error( 'duplicate', 'Ese respaldo de Backuply ya se está importando (trabajo ' . $queued['job']['id'] . ').', 409, array( 'job' => (string) $queued['job']['id'] ) );
		Digitalisimo_Backups_Jobs::schedule( ! empty( $args['spawn'] ) );
		return self::service_job_view( $queued['job'] );
	}
	/**
	 * Trae al almacén (`backuply/`) un respaldo de Backuply que está en un destino remoto (el contrato `list()` y `get()`,
	 * en streaming), para importarlo después. Se comprueba el tamaño que lista el destino y que sea gzip. Va en una sola
	 * petición: por la web sirve para respaldos que se descargan dentro del tiempo límite; los grandes, con WP-CLI.
	 */
	public static function service_backuply_fetch( $connection, $name, array $args = array() ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$connection = (string) $connection;
		$name = (string) $name;
		if ( ! preg_match( Digitalisimo_Backups_Backuply_Import::NAME, $name ) ) return self::service_error( 'backuply_name', 'Indica el nombre de un respaldo de Backuply (.tar.gz) tal como aparece en el destino.', 400 );
		if ( '' === Digitalisimo_Backups_Destinations::type_of( $connection ) ) return self::service_error( 'not_found', 'El destino ' . $connection . ' no existe.', 404 );
		$problem = self::credentials_problem( $connection );
		if ( '' !== $problem ) return self::service_error( 'credentials', $problem, 409 );
		$part = '';
		try {
			$store = Digitalisimo_Backups_Backuply_Import::store_directory( true );
			$destination = self::destination( $connection );
			$found = null;
			foreach ( (array) $destination->list() as $entry ) if ( (string) ( $entry['name'] ?? '' ) === $name ) $found = $entry;
			if ( ! $found ) return self::service_error( 'not_found', 'El destino no tiene un archivo llamado ' . $name . '.', 404 );
			$size = (int) ( $found['size'] ?? 0 );
			$free = Digitalisimo_Backups_Archive_Inspector::free_space( $store );
			if ( null !== $free && $size > 0 && $free < $size * 4 + Digitalisimo_Backups_Archive_Inspector::MARGIN ) return self::service_error( 'space', 'No hay espacio en el almacén privado para traer e importar este respaldo: se necesitan unos ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size * 4 + Digitalisimo_Backups_Archive_Inspector::MARGIN ) . ' (el archivo, el tar descomprimido y el ZIP convertido) y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.', 507 );
			if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
			Digitalisimo_Backups_Backuply_Import::clean_partials();
			$part = $store . '/.incoming-' . bin2hex( random_bytes( 8 ) ) . '.part';
			$destination->get( (array) $found['remote'], $part );
			clearstatcache( true, $part );
			if ( ! is_file( $part ) || ( $size > 0 && (int) filesize( $part ) !== $size ) ) throw new Exception( 'La descarga de ' . $name . ' no tiene el tamaño que lista el destino; no se usa.' );
			if ( "\x1f\x8b" !== (string) @file_get_contents( $part, false, null, 0, 2 ) ) throw new Exception( $name . ' no es un archivo .tar.gz (no está comprimido con gzip); no se usa.' );
			$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $store, $part, $name ) {
				$file = $name;
				while ( file_exists( $store . '/' . $file ) || is_link( $store . '/' . $file ) ) $file = substr( $name, 0, -7 ) . '-' . bin2hex( random_bytes( 3 ) ) . '.tar.gz';
				if ( ! @rename( $part, $store . '/' . $file ) ) throw new Exception( 'No se pudo mover el respaldo traído al almacén privado.' );
				@chmod( $store . '/' . $file, 0600 );
				return $file;
			} );
			$part = '';
			return array( 'source' => 'store', 'name' => $file, 'size' => (int) filesize( $store . '/' . $file ), 'message' => 'Respaldo de Backuply traído al almacén: ' . $file . '. Impórtalo para convertirlo en un ZIP de Backups.' );
		} catch ( Throwable $e ) {
			return self::service_error( 'fetch', 'No se pudo traer el respaldo de Backuply del destino: ' . $e->getMessage(), 500 );
		} finally {
			if ( '' !== $part ) @unlink( $part );
		}
	}

	/**
	 * Borra un `.tar.gz` de `backuply/` del almacén (subido o traído de un destino). Los de la carpeta de Backuply son de
	 * Backuply y no se tocan. No se borra uno que se está importando.
	 */
	public static function service_backuply_delete( $name ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$name = (string) $name;
		if ( ! preg_match( Digitalisimo_Backups_Backuply_Import::NAME, $name ) ) return self::service_error( 'backuply_name', 'Indica el nombre de un respaldo de Backuply (.tar.gz) del almacén privado.', 400 );
		try {
			$path = Digitalisimo_Backups_Backuply_Import::source_path( 'store', $name );
			if ( '' === $path ) return self::service_error( 'not_found', 'El almacén privado no tiene ese respaldo de Backuply.', 404 );
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Import_Job::is_import( $job ) && 'store' === ( $job['spec']['source']['origin'] ?? '' ) && $name === ( $job['spec']['source']['name'] ?? '' ) ) return self::service_error( 'busy', 'Ese respaldo de Backuply se está importando (trabajo ' . $job['id'] . '): cancela la importación o espera a que termine.', 409, array( 'job' => (string) $job['id'] ) );
			if ( ! @unlink( $path ) ) return self::service_error( 'storage', 'No se pudo borrar el respaldo de Backuply del almacén privado.', 500 );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return array( 'name' => $name, 'deleted' => true );
	}
	/** «Eliminar» de la tarjeta (admin-post): sólo los del almacén privado. */
	public static function backuply_delete_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_BACKUPLY_DELETE );
		$result = self::service_backuply_delete( (string) wp_unslash( $_POST['backuply_file'] ?? '' ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, 'Respaldo de Backuply borrado del almacén privado: ' . $result['name'] . '.' );
	}
	/** «Importar» de la pantalla (admin-post): encola y vuelve a Backups con el resultado. */
	public static function backuply_import_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_BACKUPLY_IMPORT );
		// El nombre no pasa por sanitize_file_name(): lo cambiaría (`.com.mx` → `.com_.mx`); lo valida la lista blanca del servicio.
		$result = self::service_backuply_import( (string) wp_unslash( $_POST['backuply_file'] ?? '' ), array( 'source' => sanitize_key( wp_unslash( $_POST['backuply_source'] ?? '' ) ), 'via' => 'admin-post', 'network' => ! empty( $_POST['digitalisimo_backups_network'] ), 'spawn' => true ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, 'Importación de Backuply en cola (trabajo ' . $result['id'] . '): su avance está en «Trabajos». Al terminar, el ZIP aparece en Herramientas → «Restaurar un archivo suelto».' );
	}
	/** Tarjeta «Respaldos de Backuply»: sólo si Backuply está en el sitio o hay respaldos suyos que importar. */
	private static function backuply_panel( $network ) {
		if ( is_multisite() && ! is_main_site() ) return;
		$found = Digitalisimo_Backups_Backuply_Import::detected();
		try {
			$items = Digitalisimo_Backups_Backuply_Import::sources();
		} catch ( Exception $e ) {
			$items = array();
		}
		if ( ! $found['installed'] && ! $found['folder'] && ! $items ) return;
		echo '<section class="digitalisimo-backups-card digitalisimo-backups-backuply" id="digitalisimo-backups-backuply"><h2>Respaldos de Backuply</h2>';
		echo '<p>' . esc_html( $found['installed'] ? 'Backuply está instalado en este sitio' . ( $found['active'] ? ' y activo' : '' ) . '.' : 'Este sitio tiene respaldos de Backuply.' ) . ' Puedes importar sus respaldos: cada uno se convierte, sin tocar el sitio, en un ZIP de Backups sin firma que aparece en Herramientas → «Restaurar un archivo suelto» y se restaura con RESTAURAR SIN FIRMA, también en otro dominio. Sólo se importan respaldos completos (base de datos y archivos). Un respaldo de Backuply que está en un destino remoto se trae primero con <code>wp digitalisimo-backups backuply fetch</code>; uno de otro servidor se sube en «Subir un respaldo» (.tar.gz).</p>';
		if ( ! $items ) { echo '<p>No hay respaldos de Backuply en este sitio ni en el almacén privado.</p></section>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Archivo</th><th>Origen</th><th>Tamaño</th><th>Contenido</th><th>Acción</th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$content = null === $item['database'] ? '—' : ( $item['database'] && $item['files'] ? 'Completo' : ( $item['database'] ? 'Sólo base de datos' : 'Sólo archivos' ) );
			echo '<tr><td>' . esc_html( $item['date'] ) . '</td><td>' . esc_html( $item['name'] ) . ( '' !== $item['site_url'] ? '<br><small>' . esc_html( $item['site_url'] ) . '</small>' : '' ) . '</td><td>' . esc_html( 'backuply' === $item['source'] ? 'Carpeta de Backuply' : 'Almacén privado' ) . '</td><td>' . esc_html( self::bytes_label( (int) $item['size'] ) ) . '</td><td>' . esc_html( $content ) . '</td><td>';
			if ( '' !== $item['imported'] ) echo '<small>Importado como ' . esc_html( $item['imported'] ) . '</small> ';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( self::ACTION_BACKUPLY_IMPORT );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_BACKUPLY_IMPORT ) . '"><input type="hidden" name="backuply_file" value="' . esc_attr( $item['name'] ) . '"><input type="hidden" name="backuply_source" value="' . esc_attr( $item['source'] ) . '">' . self::return_field( 'tools', 'import' ) . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small"' . ( false === $item['database'] || false === $item['files'] ? ' disabled title="Backups sólo restaura respaldos completos"' : '' ) . '>' . ( '' !== $item['imported'] ? 'Importar de nuevo' : 'Importar' ) . '</button></form>';
			if ( 'store' === $item['source'] ) {
				echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'¿Borrar este respaldo de Backuply del almacén privado?\')">';
				wp_nonce_field( self::ACTION_BACKUPLY_DELETE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_BACKUPLY_DELETE ) . '"><input type="hidden" name="backuply_file" value="' . esc_attr( $item['name'] ) . '">' . self::return_field( 'tools', 'import' ) . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small button-link-delete">Eliminar</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></section>';
	}
}
