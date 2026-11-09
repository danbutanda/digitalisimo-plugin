<?php
defined( 'ABSPATH' ) || exit;

/**
 * Restauración como trabajo del motor de trabajos (H2): cola, fases con punto de control, arrendamiento, latido y
 * reanudación, igual que un respaldo. Fases:
 *
 * 1. `inspect`: el paquete se comprueba entero antes de tocar nada (entradas, manifiesto v3 leído por partes, CRC y
 *    SHA-256 de cada entrada, espacio, el SQL en seco con la reubicación de E1 y el plan de origen a destino). Por tramos.
 * 2. `safety`: la copia previa, con las fases del respaldo (sus tramos, si las fases los tienen); queda bloqueada en el
 *    historial. La vuelta a la copia previa no hace otra.
 * 3. `database`: desde aquí, mantenimiento. El SQL se importa por tramos a tablas temporales con el prefijo de este sitio
 *    (`<prefijo>dgbkr_<marca>_<n>`): el sitio sigue sirviéndose de sus tablas, intactas. Tras una muerte se repite sólo la
 *    tabla que se estaba importando.
 * 4. `files`: los archivos que cambian se escriben a un área de trabajo, por tramos; el sitio sigue intacto. Carpetas de
 *    código (wp-admin, wp-includes, cada plugin y tema, mu-plugins) como unidades completas; medios y archivos sueltos,
 *    uno a uno. Se anota lo que el respaldo no tiene dentro de las raíces que gestiona (H3).
 * 5. `switch`: el cambio. Las tablas pasan de viejas a nuevas de una vez (MySQL: un solo `RENAME TABLE`, atómico; SQLite:
 *    una transacción), se reponen historial, llavero y credenciales, y los archivos se mueven con `rename()` (cada unidad
 *    de código de una vez), se retiran los ajenos y, al final, la carpeta de este plugin. Reanudable.
 * 6. `finish`: red, direcciones y cachés de la reubicación (E1).
 * 7. `cleanup`: fin del mantenimiento, tablas y archivos viejos, informe. Nunca falla la restauración.
 *
 * Si muere antes del cambio, la operación siguiente la reanuda (o, cancelada o fallida, se retira lo temporal y el sitio
 * queda como estaba). Si falla después de empezar el cambio, se encola la vuelta a la copia previa, que restaura la copia
 * entera con este mismo motor. Ver «Restauración por pasos» en docs/BACKUPS.md.
 */
final class Digitalisimo_Backups_Restore_Job {
	const PHASES = array( 'inspect', 'safety', 'database', 'files', 'switch', 'finish', 'cleanup' );
	const LABELS = array( 'inspect' => 'Verificación del respaldo', 'safety' => 'Copia previa', 'files' => 'Archivos', 'switch' => 'Cambio del sitio', 'finish' => 'Direcciones y cachés', 'cleanup' => 'Limpieza' );
	const FLOORS = array( 'inspect' => 0, 'safety' => 10, 'database' => 40, 'files' => 62, 'switch' => 86, 'finish' => 94, 'cleanup' => 97 );
	/** Fases en las que la restauración aún no cambió el sitio: se puede cancelar y una muerte no deja nada a medias. */
	const BEFORE_SWITCH = array( 'inspect', 'safety', 'database', 'files' );

	public static function run_phase( $phase, array $job ) { return Digitalisimo_Backups::restore_phase( $phase, $job ); }
	public static function on_terminal( array $job, $status, $message ) { return Digitalisimo_Backups::restore_on_terminal( $job, $status, $message ); }
	public static function discard( array $job, $phase ) {} // Cada fase empieza comprobando su punto de control.
	public static function cancellable( $phase ) { return in_array( $phase, self::BEFORE_SWITCH, true ); }
	/** La copia previa usa las fases del respaldo (0 a 100): ocupa su tramo de la barra de la restauración. */
	public static function progress_percent( $phase, $percent ) {
		if ( 'safety' !== $phase ) return (int) $percent;
		return self::FLOORS['safety'] + (int) floor( ( self::FLOORS['database'] - self::FLOORS['safety'] ) * max( 0, min( 100, (int) $percent ) ) / 100 );
	}
}
