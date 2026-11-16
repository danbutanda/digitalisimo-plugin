<?php
defined( 'ABSPATH' ) || exit;

/**
 * Trabajo `convert`: antes de restaurar el respaldo de una red en un WordPress individual, convierte por tramos
 * reanudables el sitio elegido en un paquete de sitio individual (Digitalisimo_Backups_Site_Convert) y lo entrega a la
 * restauración de siempre, como el trabajo `chain` hace con un punto incremental. La petición que restaura abrió y
 * comprobó el respaldo (SHA-256, firma, descifrado), pidió su confirmación y dejó el plan; este trabajo hace lo pesado con
 * el motor de trabajos (cola, arrendamiento, latido, reintentos, cancelación y reanudación). Nada de esto toca el sitio:
 * cancelar o fallar sólo retira el área de trabajo.
 *
 * Fases: `preflight` (el respaldo sigue igual), `site_read` (lo que el exportador lee de la red, del SQL del respaldo),
 * `site_sql` (el SQL del sitio), `site_files` (el ZIP del sitio), `site_seal` (su manifiesto) y `site_handoff` (la
 * restauración, Digitalisimo_Backups::convert_handoff()). La clase que convierte se carga sólo aquí (load()).
 */
final class Digitalisimo_Backups_Site_Convert_Job {
	const KIND = 'convert';
	const READ = 'site_read';
	const PHASES = array( 'preflight', 'site_read', 'site_sql', 'site_files', 'site_seal', 'site_handoff' );

	public static function load() {
		if ( ! class_exists( 'Digitalisimo_Backups_Site_Convert', false ) ) require_once __DIR__ . '/class-backups-site-convert.php';
	}
	public static function job_phases() { return self::PHASES; }
	// Sin `preflight`: sus datos son los de siempre (con su nombre, una consulta sin trabajo daría los de este tipo).
	public static function job_phase( $phase ) {
		$phases = array(
			'site_read' => array( 'label' => 'Lectura del sitio en el respaldo de la red', 'floor' => 3, 'max' => 3, 'cancellable' => true, 'status' => 'verifying', 'interrupted' => ' La lectura del respaldo de la red se cortó varias veces sin avanzar: repítela con WP-CLI (wp digitalisimo-backups restore … --site=N), que no tiene límite de tiempo.' ),
			'site_sql' => array( 'label' => 'Conversión de la base de datos del sitio', 'floor' => 25, 'max' => 3, 'cancellable' => true, 'status' => 'running', 'interrupted' => ' La conversión se cortó varias veces sin avanzar: repítela con WP-CLI, que no tiene límite de tiempo.' ),
			'site_files' => array( 'label' => 'Conversión de los archivos del sitio', 'floor' => 50, 'max' => 3, 'cancellable' => true, 'status' => 'running', 'interrupted' => ' La conversión se cortó varias veces sin avanzar: repítela con WP-CLI, que no tiene límite de tiempo.' ),
			'site_seal' => array( 'label' => 'Cierre del sitio convertido', 'floor' => 92, 'max' => 3, 'cancellable' => true, 'status' => 'verifying' ),
			'site_handoff' => array( 'label' => 'Entrega a la restauración', 'floor' => 98, 'max' => 3, 'cancellable' => false, 'status' => 'verifying', 'refusal' => 'El sitio ya está convertido y pasa a la restauración; cancélala en «Trabajos» si no la quieres.' ),
		);
		return $phases[ $phase ] ?? null;
	}
	public static function run_phase( $phase, array $job ) {
		self::load();
		$convert = 'Digitalisimo_Backups_Site_Convert';
		$dir = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		$plan = is_file( $dir . '/' . $convert::PLAN ) ? json_decode( (string) @file_get_contents( $dir . '/' . $convert::PLAN ), true ) : null;
		if ( ! is_array( $plan ) || $convert::VERSION !== (int) ( $plan['v'] ?? 0 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Falta la comprobación del respaldo de la red: vuelve a restaurarlo.' );
		if ( 'preflight' === $phase ) {
			$convert::open_source( $plan )->close();
			return array( 'next' => self::READ, 'checkpoint' => array( 'at' => time() ), 'message' => 'Leyendo el sitio en el respaldo de la red…' );
		}
		$steps = array( 'site_read' => array( 'read_slice', 'site_sql' ), 'site_sql' => array( 'sql_slice', 'site_files' ), 'site_files' => array( 'files_slice', 'site_seal' ) );
		if ( isset( $steps[ $phase ] ) ) {
			$cursor = (array) ( $job['checkpoint'][ $phase ]['resume'] ?? array() );
			$started = microtime( true );
			$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, $phase, $cursor );
			$deadline = Digitalisimo_Backups_Step_Budget::deadline( $cursor['scale'], Digitalisimo_Backups_Step_Budget::slice_cap() );
			$scale = $cursor['scale'];
			unset( $cursor['scale'], $cursor['last'] );
			try {
				$done = call_user_func_array( array( $convert, $steps[ $phase ][0] ), array( $plan, $dir, &$cursor, $deadline ) );
			} catch ( Digitalisimo_Backups_Resume_Invalid $e ) {
				// El ZIP a medias no coincide con su punto de control: los archivos empiezan de nuevo.
				return array( 'next' => 'site_files', 'checkpoint' => array( 'resume' => array() ), 'message' => 'Convirtiendo los archivos del sitio otra vez desde el principio…', 'warnings' => array( $e->getMessage() ) );
			}
			if ( ! $done ) {
				$cursor['scale'] = $scale;
				$cursor['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, (int) ( $cursor['units'] ?? 1 ), $scale );
				return array( 'next' => $phase, 'checkpoint' => array( 'resume' => $cursor ), 'message' => self::job_phase( $phase )['label'] . '…' );
			}
			return array( 'next' => $steps[ $phase ][1], 'checkpoint' => array( 'cursor' => $cursor ), 'message' => self::job_phase( $steps[ $phase ][1] )['label'] . '…' );
		}
		if ( 'site_seal' === $phase ) {
			$sql = (array) ( $job['checkpoint']['site_sql']['cursor'] ?? array() );
			$files = (array) ( $job['checkpoint']['site_files']['cursor'] ?? array() );
			if ( empty( $sql['done'] ) || 'done' !== ( $files['stage'] ?? '' ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La conversión no terminó; se retoma.', 'site_sql' );
			// Firmado con la clave del respaldo si este sitio la tiene en su llavero; con la frase escrita, la confianza ya la
			// comprobó la petición que restaura (la frase no se guarda) y el sitio se entrega como registrado.
			$root = null;
			if ( 'signed' === ( $plan['trust'] ?? '' ) && ! empty( $plan['key']['id'] ) ) {
				$found = Digitalisimo_Backups_Keys::resolve_by_id( (string) $plan['key']['id'] );
				if ( false !== $found ) $root = $found;
			}
			try {
				$convert::seal( $plan, $dir, $sql, $files, $root );
			} finally {
				if ( $root ) Digitalisimo_Backups_Sodium::memzero( $root );
			}
			return array( 'next' => 'site_handoff', 'checkpoint' => array( 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $dir . '/' . $convert::ZIP ) ), 'message' => 'Sitio convertido; pasa a la restauración…' );
		}
		if ( 'site_handoff' === $phase ) return Digitalisimo_Backups::convert_handoff( $job, $plan );
		throw new Digitalisimo_Backups_Permanent_Error( 'La conversión del sitio está en una fase desconocida.' );
	}
	/** Cada fase empieza desde su punto de control: nada que retirar al repetirla. */
	public static function discard( array $job, $phase ) {}
	/** Al fallar o cancelar: el área de trabajo (lo descifrado, lo leído y el ZIP a medias) se retira. El sitio no se tocó. */
	public static function on_terminal( array $job, $status, $message ) {
		try { Digitalisimo_Backups_Job_Store::remove_staging( (string) $job['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	public static function job_view( array $job, array $view ) {
		$spec = (array) ( $job['spec'] ?? array() );
		if ( self::KIND !== ( $spec['kind'] ?? '' ) ) return $view;
		$site = (int) ( $spec['convert']['site'] ?? 0 );
		return array_merge( $view, array(
			'kind' => 'convert',
			'origin_label' => 'Restaurar ' . ( $site > 0 ? 'el sitio ' . $site : 'el sitio principal' ) . ' de una red como este WordPress',
			'destination_label' => 'antes de restaurar ' . (string) ( $spec['file'] ?? '' ),
			'restore_job' => (string) ( $job['result']['restore_job'] ?? '' ),
		) );
	}
}
Digitalisimo_Backups_Jobs::register_kind( Digitalisimo_Backups_Site_Convert_Job::KIND, 'Digitalisimo_Backups_Site_Convert_Job' );
