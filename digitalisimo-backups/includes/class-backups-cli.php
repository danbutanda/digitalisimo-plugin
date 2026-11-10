<?php
defined( 'ABSPATH' ) || exit;

/**
 * Respaldos completos de WordPress o de toda la red desde WP-CLI, con los mismos casos de uso que la pantalla Backups.
 *
 * WP-CLI corre con la cuenta del sistema, que ya controla los archivos y wp-config.php, así que no pide capability. En
 * una red se ejecuta en el sitio principal (`--url`), y crear, restaurar, ejecutar una automatización o traer una copia
 * remota al almacén piden además `--network`, porque abarcan todos los sitios.
 *
 * Crear y ejecutar una automatización encolan un trabajo en la misma cola que la pantalla y, salvo con `--async`, lo
 * hacen avanzar en este proceso (sin límite de tiempo) hasta que termina. La frase o clave de un respaldo cifrado nunca
 * va en la línea de órdenes, donde la verían `ps` y el historial del shell: se lee de un archivo (`--secret-file`) o de
 * la entrada estándar (`--secret-stdin`), y nunca se imprime.
 *
 * Sin `DOCUMENT_ROOT` (WP-CLI y cron), el almacén privado sale de DIGITALISIMO_BACKUPS_STORAGE_DIR o de la ruta que
 * registró la pantalla Backups.
 */
final class Digitalisimo_Backups_Cli {
	/** Espera entre pasos de la cola (sustituible en las pruebas). */
	public static $sleep = 'sleep';
	/** Entrada estándar de `--secret-stdin` (sustituible en las pruebas). */
	public static $stdin = 'php://stdin';
	/** Si esa entrada es una terminal; null la detecta (sustituible en las pruebas). */
	public static $tty = null;

	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) return;
		WP_CLI::add_command( 'digitalisimo-backups', __CLASS__ );
		WP_CLI::add_command( 'digitalisimo-backups automation', 'Digitalisimo_Backups_Cli_Automation' );
		WP_CLI::add_command( 'digitalisimo-backups jobs', 'Digitalisimo_Backups_Cli_Jobs' );
		WP_CLI::add_command( 'digitalisimo-backups destinations', 'Digitalisimo_Backups_Cli_Destinations' );
		WP_CLI::add_command( 'digitalisimo-backups upload', 'Digitalisimo_Backups_Cli_Upload' );
		WP_CLI::add_command( 'digitalisimo-backups migration', 'Digitalisimo_Backups_Cli_Migration' );
	}
	/** Termina con el mensaje del motor y código 1. */
	public static function result( $result ) {
		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message();
			if ( 'digitalisimo_backups_secret_required' === $result->get_error_code() ) $message .= ' Pásala con --secret-file=<ruta> o --secret-stdin, nunca en la línea de órdenes.';
			WP_CLI::error( $message );
		}
		return $result;
	}
	public static function rows( array $rows, $assoc_args, array $fields ) {
		$format = (string) ( $assoc_args['format'] ?? 'table' );
		if ( 'table' === $format ) $rows = array_map( function( $row ) { return array_map( function( $value ) { return is_bool( $value ) ? ( $value ? 'sí' : 'no' ) : $value; }, $row ); }, $rows );
		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
	public static function fields( $assoc_args, array $default ) {
		return empty( $assoc_args['fields'] ) ? $default : array_map( 'trim', explode( ',', (string) $assoc_args['fields'] ) );
	}
	/**
	 * Frase o clave de un respaldo, tal cual (sólo se quita el salto de línea final). `--secret=<valor>` se rechaza:
	 * quedaría en la lista de procesos y en el historial del shell.
	 */
	public static function secret( $assoc_args, array $words = array() ) {
		// Textos de la opción que lo pidió: --secret-file/--secret-stdin (frase o clave de un respaldo) por defecto, o los de
		// la clave de migración (--from-key-file/--from-key-stdin).
		$words += array( 'file' => '--secret-file', 'stdin' => '--secret-stdin', 'what' => 'frase o clave', 'prompt' => 'Frase o clave del respaldo: ', 'empty' => 'La frase o clave del respaldo está vacía.' );
		if ( array_key_exists( 'secret', $assoc_args ) ) WP_CLI::error( 'La frase o clave nunca va en la línea de órdenes, donde la verían otros procesos y el historial del shell. Usa --secret-file=<ruta> o --secret-stdin.' );
		if ( isset( $assoc_args['secret-file'] ) && ! empty( $assoc_args['secret-stdin'] ) ) WP_CLI::error( 'Usa ' . $words['file'] . ' o ' . $words['stdin'] . ', no los dos.' );
		if ( isset( $assoc_args['secret-file'] ) ) {
			$path = (string) $assoc_args['secret-file'];
			if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) WP_CLI::error( 'No se puede leer el archivo de ' . $words['file'] . '.' );
			$secret = @file_get_contents( $path, false, null, 0, 65537 );
			if ( false === $secret || strlen( $secret ) > 65536 ) WP_CLI::error( 'El archivo de ' . $words['file'] . ' no se puede leer o es demasiado grande para una ' . $words['what'] . '.' );
		} elseif ( ! empty( $assoc_args['secret-stdin'] ) ) {
			// En una terminal se pide sin eco; por una tubería se lee la primera línea. En los dos casos, la línea tal cual:
			// \cli\prompt() la recorta (trim), y la pantalla guarda la frase sin recortar, así que una frase con espacios
			// en los extremos no abriría su clave.
			$tty = null === self::$tty ? 'php://stdin' === self::$stdin && defined( 'STDIN' ) && function_exists( 'posix_isatty' ) && @posix_isatty( STDIN ) : (bool) self::$tty;
			$hide = $tty && is_callable( array( 'cli\\Shell', 'hide' ) );
			$handle = @fopen( self::$stdin, 'rb' );
			if ( $tty && defined( 'STDERR' ) ) fwrite( STDERR, $words['prompt'] );
			if ( $hide ) \cli\Shell::hide();
			try {
				$secret = $handle ? fgets( $handle, 65537 ) : false;
			} finally {
				if ( $hide ) \cli\Shell::hide( false );
				if ( $tty && defined( 'STDERR' ) ) fwrite( STDERR, "\n" );
				if ( $handle ) fclose( $handle );
			}
			if ( false === $secret ) WP_CLI::error( 'No llegó ninguna ' . $words['what'] . ' por la entrada estándar.' );
		} else {
			return '';
		}
		$secret = preg_replace( '/\r?\n\z/', '', $secret );
		if ( '' === $secret ) WP_CLI::error( $words['empty'] );
		return $secret;
	}
	/**
	 * Hace avanzar la cola en este proceso hasta que el trabajo termina: cada tick corre sin límite de tiempo; con otra
	 * operación en curso, o mientras el trabajo espera su reintento, espera y vuelve a probar. Anota cada cambio de fase
	 * salvo con $quiet (`--porcelain`, que sólo imprime el resultado).
	 */
	public static function drive( array $view, $quiet = false ) {
		$id = (string) $view['id'];
		$last = '';
		$retry = '';
		while ( true ) {
			$line = $view['status_label'] . ( '' !== $view['phase_label'] ? ' · ' . $view['phase_label'] : '' ) . ' (' . (int) $view['percent'] . ' %)';
			if ( $line !== $last && ! $quiet ) WP_CLI::log( $line );
			$last = $line;
			if ( ! empty( $view['terminal'] ) ) return $view;
			if ( $view['retry_in'] && $view['retry_label'] !== $retry && ! $quiet ) WP_CLI::log( 'Reintento a las ' . $view['retry_label'] . ': ' . $view['message'] );
			$retry = (string) $view['retry_label'];
			$tick = self::result( Digitalisimo_Backups::service_tick() );
			if ( 'error' === $tick['result'] ) WP_CLI::error( 'La cola de respaldos no pudo avanzar' . ( '' !== $tick['message'] ? ': ' . $tick['message'] : '; revisa el registro de errores de PHP.' ) . ' El trabajo ' . $id . ' sigue en «Trabajos».' );
			$view = self::result( Digitalisimo_Backups::service_job( $id ) );
			if ( ! empty( $view['terminal'] ) ) continue;
			// Otra operación en el almacén, el reintento con espera o el trabajo en manos de otro proceso: espera y vuelve.
			if ( 'worked' !== $tick['result'] || $view['retry_in'] ) call_user_func( self::$sleep, max( 1, min( 30, (int) $view['retry_in'] ?: 5 ) ) );
		}
	}
	/** Resultado de un trabajo terminado: éxito con su archivo, o error con el motivo (código 1). */
	public static function finish( array $view, $porcelain ) {
		if ( 'success' !== $view['status'] ) WP_CLI::error( ( 'cancelled' === $view['status'] ? 'Trabajo cancelado: ' : 'El respaldo falló: ' ) . ( $view['error'] ?: $view['detail'] ) );
		// Avisos que cambian lo que es el respaldo (T2): siempre a la vista, también con --porcelain (por STDERR).
		foreach ( (array) ( $view['notices'] ?? array() ) as $notice ) WP_CLI::warning( $notice );
		if ( $porcelain ) {
			WP_CLI::line( $view['file'] );
			return;
		}
		WP_CLI::success( ( empty( $view['notices'] ) ? 'Respaldo completo creado y verificado: ' : 'Respaldo completo creado y verificado, con avisos: ' ) . $view['file'] . ' (' . $view['destination_label'] . ( $view['encrypt'] ? ', cifrado' : '' ) . ').' );
	}
	/** Encolado sin esperar: el trabajo lo continúan WP-Cron o `wp digitalisimo-backups jobs run`. */
	public static function queued( array $view, $porcelain ) {
		if ( $porcelain ) {
			WP_CLI::line( $view['id'] );
			return;
		}
		WP_CLI::success( 'Respaldo en cola (trabajo ' . $view['id'] . ', ' . $view['destination_label'] . '). Lo continúan WP-Cron o `wp digitalisimo-backups jobs run`; su estado, en `wp digitalisimo-backups jobs list`.' );
	}

	/**
	 * Muestra si la instalación puede respaldarse completa y el estado del almacén.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table o json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups status
	 */
	public function status( $args, $assoc_args ) {
		$status = self::result( Digitalisimo_Backups::service_status() );
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::line( wp_json_encode( $status ) );
			return;
		}
		$rows = array();
		foreach ( $status as $key => $value ) {
			if ( 'last_backup' === $key ) $value = $value ? $value['file'] . ' (' . $value['date'] . ')' : '';
			elseif ( 'destinations' === $key ) $value = implode( ', ', array_keys( (array) $value ) );
			elseif ( 'problems' === $key ) $value = implode( ' | ', (array) $value );
			elseif ( 'notifications' === $key ) $value = 'fallos: ' . ( empty( $value['failure'] ) ? 'no' : 'sí' ) . ', éxitos: ' . ( empty( $value['success'] ) ? 'no' : 'sí' ) . ', correo: ' . ( 'custom' === ( $value['recipient'] ?? '' ) ? 'configurado' : 'de administración' );
			$rows[] = array( 'campo' => $key, 'valor' => is_bool( $value ) ? ( $value ? 'sí' : 'no' ) : (string) $value );
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'campo', 'valor' ) );
		if ( empty( $status['ready'] ) ) WP_CLI::warning( 'Esta instalación todavía no puede respaldarse completa: ' . implode( ' ', (array) $status['problems'] ) );
	}

	/**
	 * Lista el historial de respaldos, del más reciente al más antiguo, con sus copias (local y remota) y si se puede
	 * restaurar desde el almacén.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Columnas, separadas por comas.
	 *
	 * [--format=<format>]
	 * : table, json, csv o yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$rows = self::result( Digitalisimo_Backups::service_backups() );
		// Las copias, como texto: «local, google» (la remota con su estado si no está verificada: «google (falló)»).
		$states = array( 'pending' => 'pendiente', 'failed' => 'falló', 'cancelled' => 'cancelada', 'legacy' => 'sin identificador' );
		foreach ( $rows as &$row ) $row['copies'] = implode( ', ', array_map( function( $copy ) use ( $states ) { return $copy['destination'] . ( isset( $states[ $copy['state'] ] ) ? ' (' . $states[ $copy['state'] ] . ')' : '' ); }, (array) $row['copies'] ) );
		unset( $row );
		// Los avisos del respaldo (T2), como texto.
		foreach ( $rows as &$row ) $row['notices'] = implode( ' ', (array) ( $row['notices'] ?? array() ) );
		unset( $row );
		self::rows( $rows, $assoc_args, self::fields( $assoc_args, array( 'file', 'date', 'size', 'destination', 'copies', 'scope', 'container', 'signed', 'locked', 'restorable', 'needs_secret', 'notices' ) ) );
	}

	/**
	 * Crea un respaldo completo de WordPress, o de toda la red en Multisite: lo encola y lo hace avanzar hasta que queda
	 * verificado y, con destino remoto, copiado.
	 *
	 * ## OPTIONS
	 *
	 * [--destination=<destination>]
	 * : Destino conectado (local, sftp, google u onedrive). Sin él, el de los ajustes.
	 *
	 * [--remote-only]
	 * : Sólo en el remoto: borra el ZIP local cuando la copia remota está verificada (restaurar exigirá `fetch`).
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 *
	 * [--async]
	 * : Sólo lo encola; lo continúan WP-Cron o `wp digitalisimo-backups jobs run`.
	 *
	 * [--porcelain]
	 * : Imprime sólo el nombre del archivo (con --async, el identificador del trabajo).
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups create
	 *     wp digitalisimo-backups create --network --destination=sftp
	 */
	public function create( $args, $assoc_args ) {
		$async = ! empty( $assoc_args['async'] );
		$view = self::result( Digitalisimo_Backups::service_create( array( 'destination' => (string) ( $assoc_args['destination'] ?? '' ), 'remote_only' => ! empty( $assoc_args['remote-only'] ), 'network' => ! empty( $assoc_args['network'] ), 'via' => 'cli', 'spawn' => $async ) ) );
		if ( $async ) {
			self::queued( $view, ! empty( $assoc_args['porcelain'] ) );
			return;
		}
		self::finish( self::drive( $view, ! empty( $assoc_args['porcelain'] ) ), ! empty( $assoc_args['porcelain'] ) );
	}

	/**
	 * Extrae un sitio de la red como WordPress individual: encola el paquete (alcance `extracted-site`) y lo hace avanzar
	 * hasta que queda verificado. Se restaura en un WordPress individual recién instalado con Backups, con otra URL.
	 *
	 * ## OPTIONS
	 *
	 * <site-id>
	 * : ID del sitio en la red (`wp site list`).
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio.
	 *
	 * [--super-admins=<mode>]
	 * : Superadministradores que no son miembros del sitio: none (ninguno viaja con su contraseña), managing (como administrador sólo quien tiene el correo de administración del sitio) o all.
	 * ---
	 * default: none
	 * options:
	 *   - none
	 *   - managing
	 *   - all
	 * ---
	 *
	 * [--secret-file=<path>]
	 * : Archivo con la frase propia de este paquete (16 caracteres o más): lo cifra y lo firma. Nunca la clave de la red.
	 *
	 * [--secret-stdin]
	 * : Lee esa frase de la entrada estándar (en una terminal, sin mostrarla).
	 *
	 * [--network-secrets]
	 * : Copia también las claves de API que el sitio hereda de la red (sin ella quedan vacías y el informe las nombra).
	 *
	 * [--destination=<destination>]
	 * : Destino conectado: local, el tipo de una conexión principal o el identificador de una adicional (wp digitalisimo-backups destinations list). Sin él, local.
	 *
	 * [--async]
	 * : Sólo lo encola; lo continúan WP-Cron o `wp digitalisimo-backups jobs run`.
	 *
	 * [--porcelain]
	 * : Imprime sólo el nombre del paquete (con --async, el identificador del trabajo).
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups extract 2 --network
	 *     wp digitalisimo-backups extract 2 --network --super-admins=none --porcelain
	 */
	public function extract( $args, $assoc_args ) {
		$async = ! empty( $assoc_args['async'] );
		$porcelain = ! empty( $assoc_args['porcelain'] );
		$view = self::result( Digitalisimo_Backups::service_extract( (int) ( $args[0] ?? 0 ), array( 'network' => ! empty( $assoc_args['network'] ), 'super_admins' => (string) ( $assoc_args['super-admins'] ?? 'none' ), 'network_secrets' => ! empty( $assoc_args['network-secrets'] ), 'destination' => (string) ( $assoc_args['destination'] ?? '' ), 'secret' => self::secret( $assoc_args ), 'via' => 'cli', 'spawn' => $async ) ) );
		if ( $async ) {
			self::queued( $view, $porcelain );
			return;
		}
		$view = self::drive( $view, $porcelain );
		if ( 'success' !== $view['status'] ) WP_CLI::error( ( 'cancelled' === $view['status'] ? 'Trabajo cancelado: ' : 'La extracción falló: ' ) . ( $view['error'] ?: $view['detail'] ) );
		if ( $porcelain ) {
			WP_CLI::line( $view['file'] );
			return;
		}
		WP_CLI::success( 'Sitio extraído y verificado: ' . $view['file'] . ' (' . $view['destination_label'] . ( $view['encrypt'] ? ', cifrado' : '' ) . '). Restáuralo en un WordPress individual recién instalado con Backups; `wp digitalisimo-backups verify ' . $view['file'] . ' --format=json` muestra su informe.' );
	}

	/**
	 * Verifica un respaldo local sin tocar el sitio: firma, descifrado de un `.dgbk`, estructura, CRC y SHA-256 de cada
	 * archivo contra su manifiesto y el SQL en seco, con las mismas comprobaciones que la restauración. Si viene de otra
	 * dirección, carpeta del servidor o prefijo, dice «Origen → este sitio» y qué se reescribiría al restaurarlo. Como la
	 * restauración nunca escribe wp-config.php, también lista las constantes que el respaldo define y este servidor no, las
	 * que sólo define este servidor y las que tienen otro valor (nunca muestra un valor secreto ni una ruta del servidor).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Nombre del archivo, tal como aparece en `wp digitalisimo-backups list`.
	 *
	 * [--secret-file=<path>]
	 * : Archivo con la frase o la clave de un respaldo cifrado o firmado con una clave que este sitio no tiene.
	 *
	 * [--secret-stdin]
	 * : Lee esa frase o clave de la entrada estándar (en una terminal, sin mostrarla).
	 *
	 * [--addresses=<addresses>]
	 * : Como en restore: current (conservar las direcciones de este sitio) o backup (las del respaldo).
	 * ---
	 * default: current
	 * options:
	 *   - current
	 *   - backup
	 * ---
	 *
	 * [--format=<format>]
	 * : table o json.
	 * ---
	 * default: table
	 * ---
	 */
	public function verify( $args, $assoc_args ) {
		$report = self::result( Digitalisimo_Backups::service_verify( (string) ( $args[0] ?? '' ), array( 'secret' => self::secret( $assoc_args ), 'addresses' => (string) ( $assoc_args['addresses'] ?? '' ) ) ) );
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::line( wp_json_encode( $report ) );
			return;
		}
		$trust = array( 'signed' => 'firmado' . ( '' !== $report['key'] ? ' con la clave ' . $report['key'] : '' ), 'provenance' => 'registrado en este sitio', 'untrusted' => 'sin firma ni registro: restaurarlo pide RESTAURAR SIN FIRMA' );
		WP_CLI::success( 'Respaldo íntegro: ' . $report['file'] . ' (' . ( 'dgbk' === $report['container'] ? 'cifrado, ' : '' ) . $report['entries'] . ' entradas, SHA-256 ' . $report['sha256'] . ', ' . ( $trust[ $report['trust'] ] ?? $report['trust'] ) . ').' );
		if ( empty( $report['compatible'] ) ) WP_CLI::warning( 'No se puede restaurar en esta instalación: ' . implode( ' ', (array) $report['problems'] ) );
		elseif ( ! empty( $report['relocation']['needed'] ) ) WP_CLI::log( $report['relocation']['text'] );
		// wp-config.php no se restaura: qué constantes del respaldo faltan o difieren aquí (los valores secretos nunca se
		// muestran). Va después y aparte de «Origen → este sitio»: un respaldo de otro servidor suele traer las dos cosas.
		$config = (array) ( $report['wp_config'] ?? array() );
		if ( ! empty( $config['differences'] ) ) {
			WP_CLI::warning( (string) $config['text'] );
			foreach ( Digitalisimo_Backups_Config_Diff::lines( (array) $config['differences'] ) as $line ) WP_CLI::log( '  ' . $line );
		}
	}

	/**
	 * Migra a este sitio otro WordPress (o red) en un paso: con la clave de migración generada en el origen, lo descarga
	 * cifrado, lo comprueba y lo restaura aquí conservando la dirección de este sitio. Antes crea una copia previa
	 * bloqueada (`wp digitalisimo-backups migration undo` la restaura). Avanza en este proceso hasta terminar.
	 *
	 * La clave nunca va en la línea de órdenes, donde la verían otros procesos y el historial del shell.
	 *
	 * ## OPTIONS
	 *
	 * [--from-key-file=<path>]
	 * : Archivo con la clave de migración.
	 *
	 * [--from-key-stdin]
	 * : Lee la clave de la entrada estándar (en una terminal, sin mostrarla).
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 *
	 * [--yes]
	 * : No pide confirmación (la migración reemplaza este sitio).
	 *
	 * [--async]
	 * : Sólo la encola; la continúan WP-Cron o `wp digitalisimo-backups jobs run`.
	 *
	 * [--porcelain]
	 * : Imprime sólo el identificador del trabajo.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups migrate --from-key-file=/root/clave-migracion.txt --yes
	 *     cat clave.txt | wp digitalisimo-backups migrate --from-key-stdin --network --yes
	 */
	public function migrate( $args, $assoc_args ) {
		$key = self::migration_key( $assoc_args );
		WP_CLI::confirm( 'La migración reemplaza ' . ( is_multisite() ? 'esta red' : 'este sitio' ) . ' por el del origen (con una copia previa para deshacerla). ¿Continuar?', $assoc_args );
		$async = ! empty( $assoc_args['async'] );
		$view = self::result( Digitalisimo_Backups::service_migrate( $key, array( 'confirm' => 'MIGRAR', 'network' => ! empty( $assoc_args['network'] ), 'via' => 'cli', 'spawn' => $async ) ) );
		$key = '';
		if ( $async ) {
			WP_CLI::line( ! empty( $assoc_args['porcelain'] ) ? $view['id'] : 'Migración en cola desde ' . $view['source'] . ' (trabajo ' . $view['id'] . '). La continúan WP-Cron o `wp digitalisimo-backups jobs run`.' );
			return;
		}
		$done = self::drive( $view, ! empty( $assoc_args['porcelain'] ) );
		if ( 'success' !== $done['status'] ) WP_CLI::error( ( 'cancelled' === $done['status'] ? 'Migración cancelada: ' : 'La migración falló: ' ) . ( $done['error'] ?: $done['detail'] ) );
		if ( ! empty( $assoc_args['porcelain'] ) ) { WP_CLI::line( $done['id'] ); return; }
		WP_CLI::success( $done['message'] );
	}
	/**
	 * Clave de migración de un archivo (`--from-key-file`) o de la entrada estándar (`--from-key-stdin`). En la línea de
	 * órdenes (`--from-key`, `--key`) es un error.
	 */
	public static function migration_key( $assoc_args ) {
		foreach ( array( 'from-key', 'key' ) as $name ) if ( array_key_exists( $name, $assoc_args ) ) WP_CLI::error( 'La clave de migración nunca va en la línea de órdenes, donde la verían otros procesos y el historial del shell. Usa --from-key-file=<ruta> o --from-key-stdin.' );
		$mapped = array();
		if ( isset( $assoc_args['from-key-file'] ) ) $mapped['secret-file'] = $assoc_args['from-key-file'];
		if ( ! empty( $assoc_args['from-key-stdin'] ) ) $mapped['secret-stdin'] = true;
		if ( ! $mapped ) WP_CLI::error( 'Falta la clave de migración: --from-key-file=<ruta> o --from-key-stdin.' );
		return self::secret( $mapped, array( 'file' => '--from-key-file', 'stdin' => '--from-key-stdin', 'what' => 'clave de migración', 'prompt' => 'Clave de migración: ', 'empty' => 'La clave de migración está vacía.' ) );
	}

	/**
	 * Restaura un respaldo local completo. Antes crea y bloquea una copia del estado actual; si algo falla, recupera desde ella.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Nombre del archivo, tal como aparece en `wp digitalisimo-backups list`.
	 *
	 * --confirm=<confirm>
	 * : RESTAURAR, o "RESTAURAR SIN FIRMA" para un archivo sin firma válida ni registro: la restauración reemplaza la base de datos y los archivos.
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 *
	 * [--secret-file=<path>]
	 * : Archivo con la frase o la clave de un respaldo cifrado o firmado con una clave que este sitio no tiene.
	 *
	 * [--secret-stdin]
	 * : Lee esa frase o clave de la entrada estándar (en una terminal, sin mostrarla).
	 *
	 * [--adopt-secret]
	 * : Usa la frase escrita para firmar y cifrar los respaldos nuevos.
	 *
	 * [--addresses=<addresses>]
	 * : Si el respaldo viene de otra dirección: current conserva las de este sitio y backup las del respaldo. Las rutas del servidor y el prefijo de tablas siguen siempre a este sitio.
	 * ---
	 * default: current
	 * options:
	 *   - current
	 *   - backup
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups restore digitalisimo-backup-example-com-20261106-120000-1a2b3c4d.zip --confirm=RESTAURAR
	 *     wp digitalisimo-backups restore digitalisimo-backup-example-com-20261106-120000-1a2b3c4d.dgbk --confirm=RESTAURAR --secret-file=/root/frase.txt
	 */
	public function restore( $args, $assoc_args ) {
		$result = self::result( Digitalisimo_Backups::service_restore( (string) ( $args[0] ?? '' ), array( 'confirm' => (string) ( $assoc_args['confirm'] ?? '' ), 'network' => ! empty( $assoc_args['network'] ), 'secret' => self::secret( $assoc_args ), 'adopt' => ! empty( $assoc_args['adopt-secret'] ), 'addresses' => (string) ( $assoc_args['addresses'] ?? 'current' ), 'via' => 'cli' ) ) );
		WP_CLI::success( 'Restauración terminada desde ' . $result['file'] . '. Copia previa bloqueada: ' . $result['safety_backup'] . '. Revisa los enlaces permanentes.' . ( '' !== $result['notice'] ? ' ' . $result['notice'] : '' ) );
	}
}

/**
 * Automatizaciones de respaldo completo: listarlas y ejecutar una ahora.
 */
final class Digitalisimo_Backups_Cli_Automation {
	/**
	 * Lista las automatizaciones y su próxima ejecución.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv o yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$rows = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_automations() );
		Digitalisimo_Backups_Cli::rows( $rows, $assoc_args, array( 'id', 'label', 'schedule', 'time', 'destination', 'retain', 'enabled', 'next_run' ) );
	}

	/**
	 * Ejecuta ahora una automatización completa, como su botón «Ejecutar ahora»: la encola y la hace avanzar hasta terminar.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Identificador de la automatización.
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 *
	 * [--async]
	 * : Sólo la encola; la continúan WP-Cron o `wp digitalisimo-backups jobs run`.
	 *
	 * [--porcelain]
	 * : Imprime sólo el nombre del archivo (con --async, el identificador del trabajo).
	 */
	public function run( $args, $assoc_args ) {
		$async = ! empty( $assoc_args['async'] );
		$view = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_run_automation( (string) ( $args[0] ?? '' ), array( 'network' => ! empty( $assoc_args['network'] ), 'via' => 'cli', 'spawn' => $async ) ) );
		if ( $async ) {
			Digitalisimo_Backups_Cli::queued( $view, ! empty( $assoc_args['porcelain'] ) );
			return;
		}
		Digitalisimo_Backups_Cli::finish( Digitalisimo_Backups_Cli::drive( $view, ! empty( $assoc_args['porcelain'] ) ), ! empty( $assoc_args['porcelain'] ) );
	}
}

/**
 * Cola de trabajos de respaldo, la misma de la tarjeta «Trabajos» de la pantalla: listar, cancelar y hacerla avanzar.
 */
final class Digitalisimo_Backups_Cli_Jobs {
	/**
	 * Lista los trabajos sin terminar y los últimos terminados.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Cuántos trabajos terminados mostrar además de los que siguen en curso.
	 * ---
	 * default: 8
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Columnas, separadas por comas.
	 *
	 * [--format=<format>]
	 * : table, json, csv o yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$rows = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_jobs( (int) ( $assoc_args['limit'] ?? 8 ) ) );
		Digitalisimo_Backups_Cli::rows( $rows, $assoc_args, Digitalisimo_Backups_Cli::fields( $assoc_args, array( 'id', 'status_label', 'phase_label', 'percent', 'origin_label', 'destination', 'created_label', 'file', 'detail' ) ) );
	}

	/**
	 * Cancela un trabajo: uno en cola termina ya; uno en curso se detiene en su siguiente punto de control. Un respaldo ya
	 * verificado y registrado no se cancela.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Identificador del trabajo, tal como aparece en `wp digitalisimo-backups jobs list`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups jobs cancel 0b6f6b8e-3c1d-4f7a-9a1e-2f4d5c6b7a8e
	 */
	public function cancel( $args, $assoc_args ) {
		$result = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_cancel( (string) ( $args[0] ?? '' ) ) );
		WP_CLI::success( $result['message'] );
	}

	/**
	 * Recupera un sitio que quedó a medio cambiar (una restauración que falló después del cambio y cuya vuelta a la copia
	 * previa no se pudo hacer o falló): vuelve a restaurar su copia previa, sin hacer otra, y espera a que termine.
	 *
	 * [--network]
	 * : Confirma el alcance de red (obligatorio en Multisite).
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups jobs rollback
	 */
	public function rollback( $args, $assoc_args ) {
		$result = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_restore_rollback( array( 'network' => ! empty( $assoc_args['network'] ) ) ) );
		WP_CLI::success( (string) ( $result['message'] ?? 'Se volvió a la copia previa.' ) . ( '' !== (string) ( $result['notice'] ?? '' ) ? ' ' . $result['notice'] : '' ) );
	}

	/**
	 * Hace avanzar la cola en este proceso hasta que no queda trabajo listo. Sirve para el cron del sistema, por ejemplo
	 * cada cinco minutos con `--quiet`, en un hosting que corta las peticiones largas.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups jobs run --quiet
	 */
	public function run( $args, $assoc_args ) {
		$steps = 0;
		do {
			$tick = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_tick() );
			if ( 'error' === $tick['result'] ) WP_CLI::error( 'La cola de respaldos no pudo avanzar' . ( '' !== $tick['message'] ? ': ' . $tick['message'] : '; revisa el registro de errores de PHP.' ) );
			if ( 'worked' === $tick['result'] ) $steps++;
			// Cada tick corre sin límite de tiempo: sigue mientras quede un trabajo listo detrás de otro que espera su reintento.
			$ready = array_filter( $tick['jobs'], function( $job ) { return ! $job['terminal'] && ! $job['running'] && ! $job['retry_in']; } );
		} while ( 'worked' === $tick['result'] && $ready && $steps < 100 );
		if ( 'busy' === $tick['result'] ) {
			WP_CLI::warning( 'Otra operación tiene el almacén; la cola sigue con el siguiente tick.' );
			return;
		}
		WP_CLI::success( $steps ? 'Cola avanzada.' : 'No había trabajo listo.' );
	}
}

/** Migración en un clic entre servidores y dominios: claves del origen, estado y deshacer en el destino. */
final class Digitalisimo_Backups_Cli_Migration {
	/**
	 * Estado de la migración: claves (sin secretos), migración en curso, la que se puede deshacer y el registro.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table o json.
	 * ---
	 * default: table
	 * ---
	 */
	public function status( $args, $assoc_args ) {
		$status = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_migration_status() );
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) { WP_CLI::line( wp_json_encode( $status ) ); return; }
		WP_CLI::line( 'Sitio: ' . $status['site'] . ( $status['multisite'] ? ' (red por ' . ( $status['subdomain_install'] ? 'subdominios' : 'subdirectorios' ) . ')' : '' ) );
		WP_CLI::line( 'HTTPS: ' . ( $status['https'] ? 'sí' : 'no' ) . ( $status['allow_http'] ? ' · laboratorio activado (HTTP y direcciones internas)' : '' ) );
		if ( '' !== $status['key_problem'] ) WP_CLI::warning( 'No puede generar claves: ' . $status['key_problem'] );
		if ( '' !== $status['pull_problem'] ) WP_CLI::warning( 'No puede recibir una migración: ' . $status['pull_problem'] );
		if ( $status['active'] ) WP_CLI::line( 'Migración en curso: ' . $status['active']['status_label'] . ' · ' . $status['active']['phase_label'] . ' (' . $status['active']['percent'] . ' %)' );
		if ( $status['undo'] ) WP_CLI::line( 'Se puede deshacer la migración de ' . $status['undo']['from'] . ' (' . $status['undo']['date'] . ') con la copia previa ' . $status['undo']['file'] . '.' );
		if ( $status['keys'] ) Digitalisimo_Backups_Cli::rows( $status['keys'], array( 'format' => 'table' ), array( 'id', 'state_label', 'expires_at', 'site' ) );
	}
	/**
	 * Genera una clave de migración de este sitio (o red) y la muestra una sola vez. Pégala en el destino:
	 * `wp digitalisimo-backups migrate --from-key-file=…` o Backups → Migrar.
	 *
	 * ## OPTIONS
	 *
	 * [--hours=<hours>]
	 * : Horas hasta que caduca (1 a 72).
	 * ---
	 * default: 24
	 * ---
	 *
	 * [--key-file=<path>]
	 * : Escribe la clave en este archivo nuevo (permisos 0600) en lugar de imprimirla.
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite (la clave se lleva la red entera).
	 *
	 * @subcommand create-key
	 */
	public function create_key( $args, $assoc_args ) {
		$result = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_migration_create_key( array( 'hours' => (int) ( $assoc_args['hours'] ?? 24 ), 'network' => ! empty( $assoc_args['network'] ) ) ) );
		$note = 'Clave ' . substr( $result['id'], 0, 8 ) . ' de ' . $result['source'] . ', de un solo uso; caduca el ' . gmdate( 'Y-m-d H:i', (int) $result['expires'] ) . ' UTC.';
		if ( isset( $assoc_args['key-file'] ) ) {
			$path = (string) $assoc_args['key-file'];
			// El archivo nace sólo legible por su dueño (umask) y se comprueba antes de escribir la clave: si no queda en
			// 0600 (un sistema de archivos que no lo admite), se borra y la clave se revoca.
			$mask = umask( 0077 );
			$handle = '' !== $path ? @fopen( $path, 'xb' ) : false;
			umask( $mask );
			if ( ! $handle ) { Digitalisimo_Backups::service_migration_revoke_key( $result['id'] ); WP_CLI::error( 'No se pudo crear ' . $path . ' (debe ser un archivo nuevo). La clave se revocó.' ); }
			@chmod( $path, 0600 );
			clearstatcache( true, $path );
			$private = 0 === ( (int) @fileperms( $path ) & 0077 ) || '\\' === DIRECTORY_SEPARATOR;
			$written = $private ? fwrite( $handle, $result['key'] . "\n" ) : false;
			fclose( $handle );
			if ( ! $private || strlen( $result['key'] ) + 1 !== (int) $written ) {
				@unlink( $path );
				Digitalisimo_Backups::service_migration_revoke_key( $result['id'] );
				WP_CLI::error( ! $private ? 'No se pudo dejar ' . $path . ' sólo legible por su dueño (0600). Se borró y la clave se revocó.' : 'No se pudo escribir la clave en ' . $path . '. Se borró y la clave se revocó.' );
			}
			WP_CLI::success( $note . ' Guardada en ' . $path . '.' );
			return;
		}
		WP_CLI::line( $result['key'] );
		if ( defined( 'STDERR' ) ) fwrite( STDERR, $note . " Se muestra una sola vez.\n" );
	}
	/**
	 * Lista las claves de migración (nunca su valor).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv o yaml.
	 * ---
	 * default: table
	 * ---
	 */
	public function keys( $args, $assoc_args ) {
		$status = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_migration_status() );
		Digitalisimo_Backups_Cli::rows( $status['keys'], $assoc_args, array( 'id', 'state', 'state_label', 'created_at', 'expires_at', 'site', 'failures' ) );
	}
	/**
	 * Revoca una clave de migración: deja de servir y su paquete se borra. El sitio no cambia.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Identificador de la clave (16 caracteres), tal como aparece en `wp digitalisimo-backups migration keys`.
	 */
	public function revoke( $args, $assoc_args ) {
		$key = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_migration_revoke_key( (string) ( $args[0] ?? '' ) ) );
		WP_CLI::success( 'Clave ' . substr( $key['id'], 0, 8 ) . ': ' . $key['state_label'] . '.' );
	}
	/**
	 * Deshace la última migración recibida: restaura su copia previa (y antes guarda el estado actual en otra).
	 *
	 * ## OPTIONS
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 *
	 * [--yes]
	 * : No pide confirmación.
	 */
	public function undo( $args, $assoc_args ) {
		WP_CLI::confirm( '¿Deshacer la última migración y volver al estado anterior?', $assoc_args );
		// En WP-CLI la restauración de la copia previa avanza hasta terminar en este proceso (con sus reintentos).
		$result = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_migration_undo( array( 'confirm' => 'DESHACER', 'network' => ! empty( $assoc_args['network'] ), 'via' => 'cli', 'drive_cli' => true ) ) );
		WP_CLI::success( 'Migración de ' . $result['from'] . ' deshecha con ' . $result['file'] . '. Revisa los enlaces permanentes.' . ( '' !== (string) ( $result['notice'] ?? '' ) ? ' ' . $result['notice'] : '' ) );
	}
}

/** Destinos remotos: listarlos, probarlos y traer una copia remota al almacén. */
final class Digitalisimo_Backups_Cli_Destinations {
	/**
	 * Destinos remotos y su estado (sin valores secretos).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv o yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$rows = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_destinations() );
		Digitalisimo_Backups_Cli::rows( $rows, $assoc_args, array( 'id', 'type', 'name', 'primary', 'ready', 'problem' ) );
	}
	/**
	 * «Probar conexión»: sube un archivo pequeño, lo verifica, lo descarga, lo busca al listar y lo borra.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : El tipo de una conexión principal (sftp, ftp, webdav, google, onedrive, dropbox, s3, s3compat, pcloud) o el identificador de una conexión adicional.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups destinations test s3compat
	 */
	public function test( $args, $assoc_args ) {
		$result = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_test_saved( (string) ( $args[0] ?? '' ) ) );
		WP_CLI::success( $result['message'] );
	}
	/**
	 * Trae al almacén privado la copia remota de un respaldo del historial, verificada por su SHA-256, para restaurarlo.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Nombre del archivo, tal como aparece en `wp digitalisimo-backups list`.
	 *
	 * [--network]
	 * : En Multisite, confirma el alcance de red: el almacén privado es de toda la instalación.
	 */
	public function fetch( $args, $assoc_args ) {
		$result = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_fetch( (string) ( $args[0] ?? '' ), array( 'network' => ! empty( $assoc_args['network'] ) ) ) );
		WP_CLI::success( $result['message'] );
	}
}

/**
 * Subidas de respaldos desde el navegador o REST: verlas, terminarlas sin límite de tiempo y descartarlas.
 */
final class Digitalisimo_Backups_Cli_Upload {
	/**
	 * Lista las subidas sin terminar y las cerradas de las últimas 24 h, de todas las cuentas.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv o yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$rows = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_upload_list() );
		foreach ( $rows as &$row ) $row['error'] = isset( $row['error'] ) ? (string) $row['error']['message'] : '';
		unset( $row );
		Digitalisimo_Backups_Cli::rows( $rows, $assoc_args, array( 'id', 'name', 'size', 'received', 'phase', 'attempts', 'error', 'updated_at' ) );
	}

	/**
	 * Termina una subida completa: la comprueba como archivo no confiable, sin el límite de tiempo de la web, y la deja
	 * en el almacén como «sin registro». Para una subida cuya comprobación se cortó en el navegador o por REST.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Identificador de la subida (wp digitalisimo-backups upload list).
	 *
	 * [--secret-file=<path>]
	 * : Archivo con la frase o la clave de un respaldo cifrado o firmado con una clave que este sitio no tiene.
	 *
	 * [--secret-stdin]
	 * : Lee esa frase o clave de la entrada estándar (en una terminal, sin mostrarla).
	 */
	public function finish( $args, $assoc_args ) {
		$result = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_upload_finish( (string) ( $args[0] ?? '' ), array( 'secret' => Digitalisimo_Backups_Cli::secret( $assoc_args ) ) ) );
		WP_CLI::success( 'Respaldo subido y comprobado: ' . $result['file'] . '. Para restaurarlo: --confirm="' . $result['confirmation'] . '"' . ( ! empty( $result['needs_secret'] ) ? ' y su frase o su clave' : '' ) . '.' );
		foreach ( (array) ( $result['problems'] ?? array() ) as $problem ) WP_CLI::warning( (string) $problem );
	}

	/**
	 * Descarta una subida sin terminar (de cualquier cuenta) y borra lo recibido.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Identificador de la subida.
	 */
	public function cancel( $args, $assoc_args ) {
		Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_upload_cancel( (string) ( $args[0] ?? '' ) ) );
		WP_CLI::success( 'Subida descartada; se borró lo recibido.' );
	}
}
