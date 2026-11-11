<?php
defined( 'ABSPATH' ) || exit;

/**
 * Trabajo `fetch` (B1b, B2): trae al almacén privado un respaldo que está en un destino remoto, por el motor de trabajos
 * (cola, fases con punto de control, arrendamiento, latido, reintentos y cancelación), para restaurarlo después con la
 * restauración por pasos de 1.0.64. Tipo registrado con Digitalisimo_Backups_Jobs::register_kind(); las fases las ejecuta
 * Digitalisimo_Backups::fetch_phase() (trait Digitalisimo_Backups_Remote_Service).
 *
 * 1. `preflight`: la conexión y sus credenciales; vuelve a listar el destino (un respaldo de otro sitio se busca por su
 *    nombre: nunca se usa un identificador guardado en la base de datos) y comprueba el espacio libre.
 * 2. `receive`: descarga por fragmentos (`get_range()` del contrato de destinos, 8 MiB por defecto, adaptativo) al área
 *    de trabajo, siempre desde lo que ya está en disco: una muerte, un corte de red o el fin del tramo se reanudan donde
 *    quedó. Un destino sin lectura por rangos (FTP) descarga entero en un paso.
 * 3. `examine`: tamaño, SHA-256 (el del historial, si el respaldo es de este sitio) y, para un archivo que este sitio no
 *    registró (otro sitio o sin fila), toda la validación de archivos no confiables de «Seguridad», la misma de la subida
 *    (B3): firma, descifrado completo de un `.dgbk` con una clave de este sitio, entradas, CRC y SHA-256 contra el
 *    manifiesto y el SQL en seco. Un archivo hostil o alterado se descarta y el trabajo falla sin reintentos.
 * 4. `place`: entra en el almacén con `rename()`: con el nombre de su fila (de este sitio, verificado por su SHA-256) o
 *    como «sin registro» (pide `RESTAURAR SIN FIRMA` salvo firma válida de una clave de este sitio); si el mismo
 *    contenido ya estaba en el almacén, no se duplica. Lo traído «sin registro» se borra a los 7 días si nadie lo usa.
 *
 * Una muerte en `receive` con más bytes en disco no gasta un intento (advanced()); cada petición espera como mucho lo
 * que le queda al tramo y nunca escribe más de lo pedido.
 */
final class Digitalisimo_Backups_Fetch_Job {
	const KIND = 'fetch';
	const PHASES = array( 'preflight', 'receive', 'examine', 'place' );

	public static function job_phases() { return self::PHASES; }
	/** Datos de las fases propias (`preflight` es la del respaldo: sus nombres no se repiten con los de otros tipos). */
	public static function job_phase( $phase ) {
		$phases = array(
			'receive' => array( 'label' => 'Descarga del destino', 'floor' => 5, 'max' => 5, 'cancellable' => true, 'status' => 'transferring', 'interrupted' => ' La descarga se cortó varias veces sin avanzar: revisa la conexión con el destino y vuelve a pedirla (con WP-CLI no hay límite de tiempo).' ),
			'examine' => array( 'label' => 'Comprobación del archivo', 'floor' => 85, 'max' => 3, 'cancellable' => true, 'status' => 'verifying', 'interrupted' => ' La comprobación se cortó varias veces (el tiempo límite del servidor o del proxy): repítela con WP-CLI (wp digitalisimo-backups destinations fetch), que no tiene ese límite.' ),
			'place' => array( 'label' => 'Entrada en el almacén', 'floor' => 98, 'max' => 3, 'cancellable' => false, 'status' => 'verifying', 'refusal' => 'El archivo ya está comprobado y entrando en el almacén privado; si no lo quieres, bórralo en Herramientas → «Restaurar un archivo suelto» cuando termine.' ),
		);
		return $phases[ $phase ] ?? null;
	}
	public static function run_phase( $phase, array $job ) { return Digitalisimo_Backups::fetch_phase( $phase, $job ); }
	/** Para Digitalisimo_Backups_Jobs::interrupted(): una muerte en `receive` con más bytes en disco no gasta un intento. */
	public static function advanced( array $job ) { return Digitalisimo_Backups::fetch_advanced( $job ); }
	/** Cada fase empieza comprobando lo que hay en disco: la descarga a medias se conserva para reanudarla. */
	public static function discard( array $job, $phase ) {}
	/** Al fallar o cancelar: lo descargado a medias se retira (no ocupa el almacén). */
	public static function on_terminal( array $job, $status, $message ) {
		try { Digitalisimo_Backups_Job_Store::remove_staging( (string) $job['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	/** Lo que la tarjeta «Trabajos» dice de una descarga desde un destino. */
	public static function job_view( array $job, array $view ) {
		$spec = (array) ( $job['spec'] ?? array() );
		if ( self::KIND !== ( $spec['kind'] ?? '' ) ) return $view;
		$fetch = (array) ( $spec['fetch'] ?? array() );
		$result = (array) ( $job['result'] ?? array() );
		if ( 'preflight' === $view['phase'] && ! $view['terminal'] ) $view['phase_label'] = 'Conexión con el destino';
		if ( 'success' === $view['status'] ) $view['detail'] = (string) ( $job['progress']['message'] ?? '' );
		return array_merge( $view, array(
			'kind' => 'fetch',
			'origin_label' => 'Traer de un destino remoto',
			'destination_label' => 'desde ' . Digitalisimo_Backups::destination_name( (string) ( $fetch['connection'] ?? '' ) ),
			'file' => 'success' === $view['status'] ? (string) ( $result['file'] ?? '' ) : '',
			'fetch' => array(
				'name' => (string) ( $fetch['name'] ?? '' ),
				'connection' => (string) ( $fetch['connection'] ?? '' ),
				'registered' => '' !== (string) ( $fetch['registered'] ?? '' ),
				'trust' => (string) ( $result['trust'] ?? '' ),
				'confirmation' => (string) ( $result['confirmation'] ?? '' ),
			),
		) );
	}
}
Digitalisimo_Backups_Jobs::register_kind( Digitalisimo_Backups_Fetch_Job::KIND, 'Digitalisimo_Backups_Fetch_Job' );
// Pantalla: «Sincronizar» y «Traer al almacén» de un respaldo listado en un destino (admin-post con nonce propio).
if ( function_exists( 'add_action' ) ) {
	add_action( 'admin_post_digitalisimo_backups_sync', array( 'Digitalisimo_Backups', 'sync_request' ) );
	add_action( 'admin_post_digitalisimo_backups_remote_fetch', array( 'Digitalisimo_Backups', 'remote_fetch_request' ) );
	// Retención de los respaldos traídos «sin registro» que nadie usa, con el vigilante horario de los trabajos.
	add_action( Digitalisimo_Backups_Jobs::HOOK_WATCHDOG, array( 'Digitalisimo_Backups', 'fetched_cleanup' ), 30, 0 );
}
