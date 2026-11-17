<?php
defined( 'ABSPATH' ) || exit;

/**
 * Trabajo `send`: «Enviar a un destino» un respaldo de la lista (hecho aquí o subido) que todavía no tiene copia remota,
 * por el motor de trabajos (cola, fases con punto de control, arrendamiento, latido, reintentos y cancelación), con el
 * mismo contrato de destinos que la fase «transfer» de un respaldo: subida a un nombre temporal, verificación y
 * publicación; los destinos por tramos (SFTP, S3 multipart, Dropbox, FTP) reanudan desde lo confirmado. Tipo registrado
 * con Digitalisimo_Backups_Jobs::register_kind(); las fases las ejecuta Digitalisimo_Backups::send_phase().
 *
 * 1. `preflight`: la fila sigue en la lista con su archivo en el almacén, con el SHA-256 de su fila (lo que se envía es
 *    exactamente lo registrado), sin otra copia remota, y el destino tiene una conexión completa con credenciales que se
 *    abren. La fila queda con su copia remota «pendiente».
 * 2. `deliver`: la copia, verificada por el destino; la fila queda con su copia remota (`transfer_destination`, `remote`,
 *    `transfer` = ok), como un respaldo hecho con ese destino. Un respaldo subido nunca cede su copia remota a la
 *    retención (no la registró el motor de esta instalación).
 *
 * Un fallo o una cancelación en `deliver` retira del destino lo que la subida dejó a medias (como «transfer») y la fila
 * lo dice (copia remota fallida o cancelada); el ZIP local nunca se toca.
 */
final class Digitalisimo_Backups_Send_Job {
	const KIND = 'send';
	const PHASES = array( 'preflight', 'deliver' );

	public static function job_phases() { return self::PHASES; }
	/** Datos de su fase propia (`preflight` es la del respaldo). */
	public static function job_phase( $phase ) {
		$phases = array(
			'deliver' => array( 'label' => 'Copia al destino', 'floor' => 5, 'max' => 5, 'cancellable' => true, 'status' => 'transferring', 'interrupted' => ' La copia al destino se cortó varias veces: revisa la conexión con el destino y vuelve a enviarla (con WP-CLI no hay límite de tiempo).' ),
		);
		return $phases[ $phase ] ?? null;
	}
	/** Cómo se llama en el panel de avance la fase que comparte con el respaldo (la de su vista en curso, job_view()). */
	public static function track_label( $phase ) { return 'preflight' === $phase ? 'Comprobación del respaldo' : ''; }
	public static function run_phase( $phase, array $job ) { return Digitalisimo_Backups::send_phase( $phase, $job ); }
	/** El estado reanudable de la subida vive en el área del trabajo y se conserva entre tramos. */
	public static function discard( array $job, $phase ) {}
	/** Al fallar o cancelar: lo que quedó a medias en el destino se retira y la fila dice que la copia no llegó. */
	public static function on_terminal( array $job, $status, $message ) {
		if ( 'deliver' === ( $job['phase'] ?? '' ) ) Digitalisimo_Backups::job_transfer_queue_abandon( $job );
		try { Digitalisimo_Backups_Job_Store::remove_staging( (string) $job['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		Digitalisimo_Backups::send_failed( $job, $status, (string) $message );
	}
	/** Lo que la tarjeta «Trabajos» dice del envío. */
	public static function job_view( array $job, array $view ) {
		$spec = (array) ( $job['spec'] ?? array() );
		if ( self::KIND !== ( $spec['kind'] ?? '' ) ) return $view;
		if ( 'preflight' === $view['phase'] && ! $view['terminal'] ) $view['phase_label'] = 'Comprobación del respaldo';
		if ( 'success' === $view['status'] ) $view['detail'] = (string) ( $job['progress']['message'] ?? '' );
		return array_merge( $view, array(
			'kind' => 'send',
			'origin_label' => 'Enviar a un destino: ' . basename( (string) ( $spec['file'] ?? '' ) ),
			'destination_label' => Digitalisimo_Backups::destination_name( (string) ( $spec['destination'] ?? '' ) ),
			'file' => 'success' === $view['status'] ? basename( (string) ( $spec['file'] ?? '' ) ) : '',
		) );
	}
}
Digitalisimo_Backups_Jobs::register_kind( Digitalisimo_Backups_Send_Job::KIND, 'Digitalisimo_Backups_Send_Job' );
if ( function_exists( 'add_action' ) ) add_action( 'admin_post_digitalisimo_backups_send', array( 'Digitalisimo_Backups', 'send_request' ) );
