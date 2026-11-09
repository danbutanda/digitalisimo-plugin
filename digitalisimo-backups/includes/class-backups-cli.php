<?php
defined( 'ABSPATH' ) || exit;

/**
 * Respaldos completos de WordPress o de toda la red desde WP-CLI, con los mismos casos de uso que la pantalla Backups.
 *
 * WP-CLI corre con la cuenta del sistema, que ya controla los archivos y wp-config.php, así que no pide capability. En
 * una red se ejecuta en el sitio principal (`--url`), y crear, restaurar o ejecutar una automatización piden además
 * `--network`, porque abarcan todos los sitios.
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
	public static function secret( $assoc_args ) {
		if ( array_key_exists( 'secret', $assoc_args ) ) WP_CLI::error( 'La frase o clave nunca va en la línea de órdenes, donde la verían otros procesos y el historial del shell. Usa --secret-file=<ruta> o --secret-stdin.' );
		if ( isset( $assoc_args['secret-file'] ) && ! empty( $assoc_args['secret-stdin'] ) ) WP_CLI::error( 'Usa --secret-file o --secret-stdin, no los dos.' );
		if ( isset( $assoc_args['secret-file'] ) ) {
			$path = (string) $assoc_args['secret-file'];
			if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) WP_CLI::error( 'No se puede leer el archivo de --secret-file.' );
			$secret = @file_get_contents( $path, false, null, 0, 65537 );
			if ( false === $secret || strlen( $secret ) > 65536 ) WP_CLI::error( 'El archivo de --secret-file no se puede leer o es demasiado grande para una frase o clave.' );
		} elseif ( ! empty( $assoc_args['secret-stdin'] ) ) {
			// En una terminal se pide sin eco; por una tubería se lee la primera línea. En los dos casos, la línea tal cual:
			// \cli\prompt() la recorta (trim), y la pantalla guarda la frase sin recortar, así que una frase con espacios
			// en los extremos no abriría su clave.
			$tty = null === self::$tty ? 'php://stdin' === self::$stdin && defined( 'STDIN' ) && function_exists( 'posix_isatty' ) && @posix_isatty( STDIN ) : (bool) self::$tty;
			$hide = $tty && is_callable( array( 'cli\\Shell', 'hide' ) );
			$handle = @fopen( self::$stdin, 'rb' );
			if ( $tty && defined( 'STDERR' ) ) fwrite( STDERR, 'Frase o clave del respaldo: ' );
			if ( $hide ) \cli\Shell::hide();
			try {
				$secret = $handle ? fgets( $handle, 65537 ) : false;
			} finally {
				if ( $hide ) \cli\Shell::hide( false );
				if ( $tty && defined( 'STDERR' ) ) fwrite( STDERR, "\n" );
				if ( $handle ) fclose( $handle );
			}
			if ( false === $secret ) WP_CLI::error( 'No llegó ninguna frase o clave por la entrada estándar.' );
		} else {
			return '';
		}
		$secret = preg_replace( '/\r?\n\z/', '', $secret );
		if ( '' === $secret ) WP_CLI::error( 'La frase o clave del respaldo está vacía.' );
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
		if ( $porcelain ) {
			WP_CLI::line( $view['file'] );
			return;
		}
		WP_CLI::success( 'Respaldo completo creado y verificado: ' . $view['file'] . ' (' . $view['destination_label'] . ( $view['encrypt'] ? ', cifrado' : '' ) . ').' );
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
		self::rows( $rows, $assoc_args, self::fields( $assoc_args, array( 'file', 'date', 'size', 'destination', 'copies', 'scope', 'container', 'signed', 'locked', 'restorable', 'needs_secret' ) ) );
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
		$view = self::result( Digitalisimo_Backups::service_create( array( 'destination' => (string) ( $assoc_args['destination'] ?? '' ), 'network' => ! empty( $assoc_args['network'] ), 'via' => 'cli', 'spawn' => $async ) ) );
		if ( $async ) {
			self::queued( $view, ! empty( $assoc_args['porcelain'] ) );
			return;
		}
		self::finish( self::drive( $view, ! empty( $assoc_args['porcelain'] ) ), ! empty( $assoc_args['porcelain'] ) );
	}

	/**
	 * Verifica un respaldo local sin tocar el sitio: firma, descifrado de un `.dgbk`, estructura, CRC y SHA-256 de cada
	 * archivo contra su manifiesto y el SQL en seco, con las mismas comprobaciones que la restauración.
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
	 * [--format=<format>]
	 * : table o json.
	 * ---
	 * default: table
	 * ---
	 */
	public function verify( $args, $assoc_args ) {
		$report = self::result( Digitalisimo_Backups::service_verify( (string) ( $args[0] ?? '' ), array( 'secret' => self::secret( $assoc_args ) ) ) );
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::line( wp_json_encode( $report ) );
			return;
		}
		$trust = array( 'signed' => 'firmado' . ( '' !== $report['key'] ? ' con la clave ' . $report['key'] : '' ), 'provenance' => 'registrado en este sitio', 'untrusted' => 'sin firma ni registro: restaurarlo pide RESTAURAR SIN FIRMA' );
		WP_CLI::success( 'Respaldo íntegro: ' . $report['file'] . ' (' . ( 'dgbk' === $report['container'] ? 'cifrado, ' : '' ) . $report['entries'] . ' entradas, SHA-256 ' . $report['sha256'] . ', ' . ( $trust[ $report['trust'] ] ?? $report['trust'] ) . ').' );
		if ( empty( $report['compatible'] ) ) WP_CLI::warning( 'No se puede restaurar en esta instalación: ' . implode( ' ', (array) $report['problems'] ) );
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
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups restore digitalisimo-backup-example-com-20261106-120000-1a2b3c4d.zip --confirm=RESTAURAR
	 *     wp digitalisimo-backups restore digitalisimo-backup-example-com-20261106-120000-1a2b3c4d.dgbk --confirm=RESTAURAR --secret-file=/root/frase.txt
	 */
	public function restore( $args, $assoc_args ) {
		$result = self::result( Digitalisimo_Backups::service_restore( (string) ( $args[0] ?? '' ), array( 'confirm' => (string) ( $assoc_args['confirm'] ?? '' ), 'network' => ! empty( $assoc_args['network'] ), 'secret' => self::secret( $assoc_args ), 'adopt' => ! empty( $assoc_args['adopt-secret'] ), 'via' => 'cli' ) ) );
		WP_CLI::success( 'Restauración terminada desde ' . $result['file'] . '. Copia previa bloqueada: ' . $result['safety_backup'] . '. Revisa los enlaces permanentes.' . ( '' !== $result['notice'] ? ' ' . $result['notice'] : '' ) );
	}
}

/** Automatizaciones de respaldo completo. */
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

/** Cola de trabajos: la misma que la tarjeta «Trabajos» de la pantalla. */
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
