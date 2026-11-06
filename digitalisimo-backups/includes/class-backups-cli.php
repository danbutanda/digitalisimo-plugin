<?php
defined( 'ABSPATH' ) || exit;

/**
 * Respaldos completos de WordPress o de toda la red desde WP-CLI, con los mismos casos de uso que la pantalla Backups.
 *
 * WP-CLI corre con la cuenta del sistema, que ya controla los archivos y wp-config.php, así que no pide capability. En
 * una red se ejecuta en el sitio principal (`--url`), y crear, restaurar o ejecutar una automatización piden además
 * `--network`, porque abarcan todos los sitios.
 *
 * Sin `DOCUMENT_ROOT` (WP-CLI y cron), el almacén privado sale de DIGITALISIMO_BACKUPS_STORAGE_DIR o de la ruta que
 * registró la pantalla Backups.
 */
final class Digitalisimo_Backups_Cli {
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) return;
		WP_CLI::add_command( 'digitalisimo-backups', __CLASS__ );
		WP_CLI::add_command( 'digitalisimo-backups automation', 'Digitalisimo_Backups_Cli_Automation' );
	}
	/** Termina con el mensaje del motor y código 1. */
	public static function result( $result ) {
		if ( is_wp_error( $result ) ) WP_CLI::error( $result->get_error_message() );
		return $result;
	}
	public static function rows( array $rows, $assoc_args, array $fields ) {
		$format = (string) ( $assoc_args['format'] ?? 'table' );
		if ( 'table' === $format ) $rows = array_map( function( $row ) { return array_map( function( $value ) { return is_bool( $value ) ? ( $value ? 'sí' : 'no' ) : $value; }, $row ); }, $rows );
		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
	private static function fields( $assoc_args, array $default ) {
		return empty( $assoc_args['fields'] ) ? $default : array_map( 'trim', explode( ',', (string) $assoc_args['fields'] ) );
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
			$rows[] = array( 'campo' => $key, 'valor' => is_bool( $value ) ? ( $value ? 'sí' : 'no' ) : (string) $value );
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'campo', 'valor' ) );
		if ( empty( $status['ready'] ) ) WP_CLI::warning( 'Esta instalación todavía no puede respaldarse completa: ' . implode( ' ', (array) $status['problems'] ) );
	}

	/**
	 * Lista el historial de respaldos, del más reciente al más antiguo.
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
		self::rows( $rows, $assoc_args, self::fields( $assoc_args, array( 'file', 'date', 'size', 'destination', 'scope', 'policy', 'locked', 'available', 'restorable' ) ) );
	}

	/**
	 * Crea un respaldo completo de WordPress, o de toda la red en Multisite, y lo verifica antes de registrarlo.
	 *
	 * ## OPTIONS
	 *
	 * [--destination=<destination>]
	 * : Destino conectado (local, sftp, google u onedrive). Sin él, el de los ajustes.
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 *
	 * [--porcelain]
	 * : Imprime sólo el nombre del archivo.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups create
	 *     wp digitalisimo-backups create --network --destination=sftp
	 */
	public function create( $args, $assoc_args ) {
		$backup = self::result( Digitalisimo_Backups::service_create( array( 'destination' => (string) ( $assoc_args['destination'] ?? '' ), 'network' => ! empty( $assoc_args['network'] ) ) ) );
		if ( ! empty( $assoc_args['porcelain'] ) ) {
			WP_CLI::line( $backup['file'] );
			return;
		}
		WP_CLI::success( 'Respaldo completo creado y verificado: ' . $backup['file'] . ' (' . $backup['destination'] . ', SHA-256 ' . $backup['sha256'] . ').' );
	}

	/**
	 * Verifica un respaldo local sin tocar el sitio: estructura, CRC y SHA-256 de cada archivo contra su manifiesto.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Nombre del ZIP, tal como aparece en `wp digitalisimo-backups list`.
	 *
	 * [--format=<format>]
	 * : table o json.
	 * ---
	 * default: table
	 * ---
	 */
	public function verify( $args, $assoc_args ) {
		$report = self::result( Digitalisimo_Backups::service_verify( (string) ( $args[0] ?? '' ) ) );
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::line( wp_json_encode( $report ) );
			return;
		}
		WP_CLI::success( 'Respaldo íntegro: ' . $report['file'] . ' (' . $report['entries'] . ' entradas, SHA-256 ' . $report['sha256'] . ', ' . ( $report['registered'] ? 'registrado en el historial' : 'sin registro en el historial' ) . ').' );
	}

	/**
	 * Restaura un respaldo local completo. Antes crea y bloquea una copia del estado actual; si algo falla, recupera desde ella.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Nombre del ZIP, tal como aparece en `wp digitalisimo-backups list`.
	 *
	 * --confirm=<confirm>
	 * : Escribe RESTAURAR: la restauración reemplaza la base de datos y los archivos.
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-backups restore digitalisimo-backup-example-com-20261106-120000-1a2b3c4d.zip --confirm=RESTAURAR
	 */
	public function restore( $args, $assoc_args ) {
		$result = self::result( Digitalisimo_Backups::service_restore( (string) ( $args[0] ?? '' ), array( 'confirm' => (string) ( $assoc_args['confirm'] ?? '' ), 'network' => ! empty( $assoc_args['network'] ) ) ) );
		WP_CLI::success( 'Restauración terminada desde ' . $result['file'] . '. Copia previa bloqueada: ' . $result['safety_backup'] . '. Revisa los enlaces permanentes.' );
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
		Digitalisimo_Backups_Cli::rows( $rows, $assoc_args, array( 'id', 'label', 'schedule', 'destination', 'retain', 'enabled', 'next_run' ) );
	}

	/**
	 * Ejecuta ahora una automatización completa, como su botón «Ejecutar ahora».
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Identificador de la automatización.
	 *
	 * [--network]
	 * : Confirma el alcance de red; obligatorio en Multisite.
	 */
	public function run( $args, $assoc_args ) {
		$backup = Digitalisimo_Backups_Cli::result( Digitalisimo_Backups::service_run_automation( (string) ( $args[0] ?? '' ), array( 'network' => ! empty( $assoc_args['network'] ) ) ) );
		WP_CLI::success( 'Automatización ejecutada: ' . $backup['file'] . ' (' . $backup['destination'] . ').' );
	}
}
