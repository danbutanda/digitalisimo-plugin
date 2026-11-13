<?php
defined( 'ABSPATH' ) || exit;

/**
 * Trabajo `chain`: reconstruye por tramos el respaldo completo de un punto incremental antes de restaurarlo (docs/BACKUPS.md,
 * «Incrementales»). La petición que restaura comprueba la cadena entera (Digitalisimo_Backups_Chain_Restore::prepare(),
 * con la frase si hace falta: los eslabones cifrados quedan descifrados en el área de este trabajo, como un `.dgbk` que se
 * restaura) y la confirmación; este trabajo hace lo pesado con el motor de trabajos (cola, arrendamiento, latido,
 * reintentos, cancelación y reanudación) y, al terminar, encola la restauración de siempre con ese ZIP completo.
 *
 * 0. `preflight` (la del respaldo): el plan que dejó la petición y los eslabones siguen ahí, iguales;
 * 1. `build`: el ZIP del punto por tramos (la base de datos y cada archivo del eslabón que dice el inventario, comprobado
 *    por su tamaño y su SHA-256), desde su punto de control tras una muerte;
 * 2. `seal`: su manifiesto de respaldo completo v3 (firmado con la clave de la cadena si este sitio la tiene);
 * 3. `handoff`: lo entrega a la restauración por pasos (Digitalisimo_Backups::chain_handoff()), que lo vuelve a comprobar
 *    entrada por entrada y hace la copia previa, el SQL y los archivos como con cualquier completo.
 * Nada de esto toca el sitio: cancelar o fallar sólo retira el área de trabajo.
 */
final class Digitalisimo_Backups_Chain_Job {
	const KIND = 'chain';
	const PHASES = array( 'preflight', 'build', 'seal', 'handoff' );

	public static function job_phases() { return self::PHASES; }
	public static function job_phase( $phase ) {
		$phases = array(
			'build' => array( 'label' => 'Reconstrucción del punto incremental', 'floor' => 5, 'max' => 3, 'cancellable' => true, 'status' => 'running', 'interrupted' => ' La reconstrucción se cortó varias veces sin avanzar: repítela con WP-CLI (wp digitalisimo-backups restore), que no tiene límite de tiempo.' ),
			'seal' => array( 'label' => 'Cierre del punto reconstruido', 'floor' => 92, 'max' => 3, 'cancellable' => true, 'status' => 'verifying' ),
			'handoff' => array( 'label' => 'Entrega a la restauración', 'floor' => 98, 'max' => 3, 'cancellable' => false, 'status' => 'verifying', 'refusal' => 'El punto ya está reconstruido y pasa a la restauración; cancélala en «Trabajos» si no la quieres.' ),
		);
		return $phases[ $phase ] ?? null;
	}
	public static function run_phase( $phase, array $job ) {
		$dir = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		$plan = json_decode( (string) @file_get_contents( $dir . '/' . Digitalisimo_Backups_Chain_Restore::PLAN ), true );
		if ( ! is_array( $plan ) || 1 !== (int) ( $plan['v'] ?? 0 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Falta la comprobación de la cadena de este punto: vuelve a restaurarlo.' );
		if ( 'preflight' === $phase ) {
			foreach ( (array) $plan['links'] as $index => $link ) {
				if ( ! Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( (string) $link['path'] ), (array) $link['fp'] ) ) throw new Digitalisimo_Backups_Permanent_Error( Digitalisimo_Backups_Chain_Restore::broken( (string) $plan['point']['label'], (int) $index, (int) $plan['point']['sequence'], 'cambió o desapareció del almacén antes de reconstruir el punto', 'Vuelve a restaurar.' ) );
			}
			return array( 'next' => 'build', 'checkpoint' => array( 'at' => time() ), 'message' => 'Reconstruyendo el punto incremental…' );
		}
		if ( 'build' === $phase ) {
			$cursor = (array) ( $job['checkpoint']['build']['resume'] ?? array() );
			$started = microtime( true );
			$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'build', $cursor );
			$deadline = Digitalisimo_Backups_Step_Budget::deadline( $cursor['scale'], Digitalisimo_Backups_Step_Budget::slice_cap() );
			$scale = $cursor['scale'];
			unset( $cursor['scale'], $cursor['last'] );
			try {
				$done = Digitalisimo_Backups_Chain_Restore::build_slice( $plan, $dir, $cursor, $deadline );
			} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
				throw $e;
			} catch ( Digitalisimo_Backups_Resume_Invalid $e ) {
				// El ZIP a medias no coincide con su punto de control: se empieza de nuevo (la comprobación ya está hecha).
				return array( 'next' => 'build', 'checkpoint' => array( 'resume' => array() ), 'message' => 'Reconstruyendo el punto otra vez desde el principio…', 'warnings' => array( $e->getMessage() ) );
			} catch ( Exception $e ) {
				// Una cadena que no supera la comprobación de su contenido no se arregla repitiendo.
				if ( false !== strpos( $e->getMessage(), 'está rota' ) ) throw new Digitalisimo_Backups_Permanent_Error( $e->getMessage() );
				throw $e;
			}
			if ( ! $done ) {
				$cursor['scale'] = $scale;
				$cursor['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, 1, $scale );
				return array( 'next' => 'build', 'checkpoint' => array( 'resume' => $cursor ), 'message' => 'Reconstruyendo el punto: ' . (int) $cursor['files'] . ' de ' . (int) $plan['files'] . ' archivos…' );
			}
			return array( 'next' => 'seal', 'checkpoint' => array( 'cursor' => $cursor ), 'message' => 'Punto reconstruido; cerrando su manifiesto…' );
		}
		if ( 'seal' === $phase ) {
			$cursor = (array) ( $job['checkpoint']['build']['cursor'] ?? array() );
			if ( 'done' !== ( $cursor['stage'] ?? '' ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La reconstrucción no terminó; se retoma.', 'build' );
			// Firmado con la clave de la cadena si este sitio la tiene en su llavero; si se escribió su frase, la confianza
			// ya la comprobó la petición que restaura (la frase no se guarda) y el punto se entrega como registrado.
			$root = null;
			if ( 'signed' === ( $plan['trust'] ?? '' ) && ! empty( $plan['key']['id'] ) ) {
				$found = Digitalisimo_Backups_Keys::resolve_by_id( (string) $plan['key']['id'] );
				if ( false !== $found ) $root = $found;
			}
			try {
				Digitalisimo_Backups_Chain_Restore::seal( $plan, $dir, $cursor, $root );
			} finally {
				if ( $root ) Digitalisimo_Backups_Sodium::memzero( $root );
			}
			return array( 'next' => 'handoff', 'checkpoint' => array( 'cursor' => $cursor, 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $dir . '/point.zip' ) ), 'message' => 'Punto reconstruido y cerrado; pasa a la restauración…' );
		}
		if ( 'handoff' === $phase ) return Digitalisimo_Backups::chain_handoff( $job, $plan );
		throw new Digitalisimo_Backups_Permanent_Error( 'La reconstrucción del punto está en una fase desconocida.' );
	}
	/** Cada fase empieza desde su punto de control: nada que retirar al repetirla. */
	public static function discard( array $job, $phase ) {}
	/** Al fallar o cancelar: el área de trabajo (lo descifrado y el ZIP a medias) se retira. El sitio no se tocó. */
	public static function on_terminal( array $job, $status, $message ) {
		try { Digitalisimo_Backups_Job_Store::remove_staging( (string) $job['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	public static function job_view( array $job, array $view ) {
		$spec = (array) ( $job['spec'] ?? array() );
		if ( self::KIND !== ( $spec['kind'] ?? '' ) ) return $view;
		return array_merge( $view, array(
			'kind' => 'chain',
			'origin_label' => 'Restaurar un punto incremental',
			'destination_label' => 'antes de restaurar ' . (string) ( $spec['file'] ?? '' ),
			'restore_job' => (string) ( $job['result']['restore_job'] ?? '' ),
		) );
	}
}
Digitalisimo_Backups_Jobs::register_kind( Digitalisimo_Backups_Chain_Job::KIND, 'Digitalisimo_Backups_Chain_Job' );
