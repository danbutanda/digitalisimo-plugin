<?php
/**
 * Lote corto · I8: modo de mantenimiento durante una restauración, y el aviso al terminar o fallar.
 *
 * Mientras corren el SQL y los archivos, `ABSPATH/.maintenance` hace que WordPress responda «en mantenimiento» en vez de
 * servir un sitio a medias; se renueva cada minuto (WordPress lo ignora a los 10 minutos) y se retira siempre: al
 * terminar, al fallar (también tras recuperar la copia previa) y, si el proceso muere, en la operación siguiente, que
 * además avisa por correo a quien recibía los avisos antes del SQL. El `.maintenance` de un respaldo nunca se restaura,
 * y uno que sea un enlace simbólico nunca se sigue.
 */
$root = sys_get_temp_dir() . '/digitalisimo-maintenance-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
// La restauración es un trabajo por pasos con reintentos: aquí, sin esperas entre ellos.
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $hook, $value, ...$args ) { return 'digitalisimo_backups_job_backoff' === $hook ? 0 : $value; } }
digi_make_wordpress( $root . '/public' );
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
$GLOBALS['mails'] = array();
function wp_mail( $to, $subject, $body ) { $GLOBALS['mails'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body ); return true; }
/** Lo que decide WordPress (wp_is_maintenance_mode()): incluye `.maintenance` y mira si $upgrading tiene menos de 10 minutos. */
function digi_wp_maintenance() {
	$path = ABSPATH . '.maintenance';
	if ( ! file_exists( $path ) ) return false;
	$upgrading = 0;
	include $path;
	return time() - (int) $upgrading < 600;
}
/** La base de datos anota, en cada sentencia que importa, si en ese momento WordPress estaba en mantenimiento. */
class Digi_Maintenance_DB extends Digi_Test_DB {
	public $seen = array();
	public $die_after = 0;
	public function query( $sql ) {
		if ( 0 === strpos( $sql, 'INSERT INTO' ) ) {
			$this->seen[] = digi_wp_maintenance();
			if ( $this->die_after && count( $this->seen ) >= $this->die_after ) {
				// La muerte del proceso (como SIGKILL): ningún finally ni el apagado del motor de trabajos.
				$current = new ReflectionProperty( 'Digitalisimo_Backups_Jobs', 'current' );
				if ( PHP_VERSION_ID < 80100 ) $current->setAccessible( true );
				$current->setValue( null, null );
				exit;
			}
		}
		return parent::query( $sql );
	}
}
$GLOBALS['wpdb'] = new Digi_Maintenance_DB();
$db = $GLOBALS['wpdb'];
// Varias filas, para que el SQL tenga varias sentencias.
for ( $i = 2; $i <= 6; $i++ ) $db->rows[] = array( 'option_id' => (string) $i, 'option_name' => 'opcion_' . $i, 'option_value' => 'valor ' . $i );

$B = 'Digitalisimo_Backups';
$M = 'Digitalisimo_Backups_Maintenance';
$store = $root . '/private';
update_option( 'admin_email', 'admin@example.test' );
update_option( $B::OPTION, array( 'retention' => '100' ) );

// 1. Un respaldo hecho mientras WordPress se actualizaba lleva su `.maintenance`: nunca se restaura.
file_put_contents( ABSPATH . '.maintenance', '<?php $upgrading = 100;' );
$created = digi_backup();
digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo.' );
unlink( ABSPATH . '.maintenance' );

// 2. Restauración: todo el SQL corre en mantenimiento; al terminar el sitio vuelve.
digi_assert( false === digi_wp_maintenance(), 'El sitio empezó en mantenimiento.' );
$restored = $B::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
digi_assert( ! is_wp_error( $restored ), 'La restauración falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : '' ) );
digi_assert( count( $db->seen ) >= 6 && ! in_array( false, $db->seen, true ), 'El SQL de la restauración corrió sin el sitio en mantenimiento: ' . json_encode( $db->seen ) );
digi_assert( ! file_exists( ABSPATH . '.maintenance' ) && false === digi_wp_maintenance(), 'La restauración terminada dejó el sitio en mantenimiento (o restauró el .maintenance del respaldo).' );

// 3. Una restauración larga: la marca se renueva (WordPress la ignoraría a los 10 minutos) y se retira al parar.
$M::start();
file_put_contents( ABSPATH . '.maintenance', '<?php $upgrading = ' . ( time() - 3000 ) . '; // ' . $M::MARKER );
$written = new ReflectionProperty( $M, 'written' );
if ( PHP_VERSION_ID < 80100 ) $written->setAccessible( true );
$written->setValue( null, time() - 120 );
$M::heartbeat();
digi_assert( true === digi_wp_maintenance(), 'La renovación no mantuvo el mantenimiento en una restauración larga.' );
$M::stop();
digi_assert( ! file_exists( ABSPATH . '.maintenance' ), 'Parar el mantenimiento no retiró .maintenance.' );
// P2: al recuperar la copia previa, su verificación (que lee el ZIP entero) también renueva la marca.
$M::start();
file_put_contents( ABSPATH . '.maintenance', '<?php $upgrading = ' . ( time() - 3000 ) . '; // ' . $M::MARKER );
$written->setValue( null, time() - 120 );
$zip = new ZipArchive();
$zip->open( $root . '/private/' . $created['file'] );
$verify = new ReflectionMethod( $B, 'verify_zip_contents' );
if ( PHP_VERSION_ID < 80100 ) $verify->setAccessible( true );
$verify->invoke( null, $zip );
$zip->close();
digi_assert( true === digi_wp_maintenance(), 'La verificación de la copia previa no renovó el mantenimiento.' );
$M::stop();

// 4. Una restauración que falla después del SQL (y recupera la copia previa) también saca al sitio del mantenimiento.
// Un archivo del respaldo que no se puede escribir (cambió y su carpeta es de sólo lectura): falla el cambio, no la copia
// previa; la vuelta a la copia previa (que no tiene que escribirlo) lo deja como estaba y termina sin mantenimiento.
file_put_contents( WP_CONTENT_DIR . '/plugins/example.php', "<?php // plugin de ejemplo, cambiado\n" );
chmod( WP_CONTENT_DIR . '/plugins', 0555 );
$db->seen = array();
$failed = $B::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
digi_assert( is_wp_error( $failed ) && 500 === $failed->get_error_data()['status'] && $db->seen && ! in_array( false, $db->seen, true ), 'La restauración que debía fallar tras el SQL no lo hizo, o corrió sin mantenimiento: ' . json_encode( array( is_wp_error( $failed ) ? array( $failed->get_error_message(), $failed->get_error_data() ) : $failed, $db->seen ) ) );
digi_assert( ! file_exists( ABSPATH . '.maintenance' ), 'Una restauración fallida dejó el sitio en mantenimiento.' );
chmod( WP_CONTENT_DIR . '/plugins', 0755 );
file_put_contents( WP_CONTENT_DIR . '/plugins/example.php', "<?php // plugin de ejemplo\n" );

// 5. Un `.maintenance` que es un enlace simbólico nunca se sigue ni se borra (la copia previa, además, rechaza cualquier
// enlace dentro de la instalación, así que una restauración ni siquiera llega aquí).
$target = $root . '/fuera.txt';
file_put_contents( $target, 'no tocar' );
symlink( $target, ABSPATH . '.maintenance' );
$M::start();
$written->setValue( null, time() - 120 );
$M::heartbeat();
$M::stop();
digi_assert( 'no tocar' === file_get_contents( $target ) && is_link( ABSPATH . '.maintenance' ) && false === $M::clear_stale(), 'Se siguió o se borró un .maintenance que era un enlace simbólico.' );
unlink( ABSPATH . '.maintenance' );

// 6. El proceso muere en mitad del SQL: el sitio queda en mantenimiento y la operación siguiente lo retira y avisa.
// Desde la restauración por pasos, la operación siguiente la reanuda (el SQL va a tablas temporales y el sitio no cambió)
// y el mantenimiento se retira al terminar; un diario de la restauración en una sola petición (la de UpdraftPlus) sigue
// retirándolo y avisando como antes.
$GLOBALS['mails'] = array();
$db->seen = array();
$db->die_after = 3;
register_shutdown_function( function() use ( $B, $root, $created ) {
	try {
		$GLOBALS['wpdb']->die_after = 0;
		digi_assert( true === digi_wp_maintenance() && Digitalisimo_Backups_Maintenance::ours(), 'Al morir el proceso, el sitio no quedó en mantenimiento.' );
		for ( $i = 0; $i < 400 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
		$last = null;
		foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( Digitalisimo_Backups_Jobs::is_restore( $job ) && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
		digi_assert( $last && 'success' === $last['status'] && in_array( 'interrupted', array_column( Digitalisimo_Backups_Job_Store::events( $last['id'] ), 'type' ), true ), 'La operación siguiente no reanudó la restauración muerta en mitad del SQL.' );
		digi_assert( ! file_exists( ABSPATH . '.maintenance' ) && false === digi_wp_maintenance(), 'La operación siguiente no sacó al sitio del mantenimiento.' );
		digi_assert( ! $GLOBALS['mails'], 'Una restauración reanudada avisó de un fallo.' );
		// El diario de una restauración en una sola petición que murió (UpdraftPlus): la operación siguiente retira el
		// mantenimiento y avisa a quien recibía los avisos antes del SQL.
		Digitalisimo_Backups_Maintenance::start();
		$journal = new ReflectionMethod( $B, 'write_restore_journal' );
		if ( PHP_VERSION_ID < 80100 ) $journal->setAccessible( true );
		$journal->invoke( null, array() );
		update_option( 'admin_email', 'del-respaldo@example.test' );
		$lock = $B::try_operation_lock();
		$B::release_operation_lock( $lock );
		digi_assert( ! file_exists( ABSPATH . '.maintenance' ) && false === digi_wp_maintenance(), 'La operación siguiente no sacó al sitio del mantenimiento.' );
		digi_assert( 1 === count( $GLOBALS['mails'] ) && 'admin@example.test' === $GLOBALS['mails'][0]['to'] && false !== strpos( $GLOBALS['mails'][0]['subject'], 'La restauración falló' ) && false !== strpos( $GLOBALS['mails'][0]['body'], 'se interrumpió antes de terminar' ) && false !== strpos( $GLOBALS['mails'][0]['body'], 'restaura la copia previa safety-before-restore-' ), 'La restauración interrumpida no avisó a quien recibía los avisos antes del SQL: ' . json_encode( $GLOBALS['mails'] ) );
		digi_assert( false === strpos( $GLOBALS['mails'][0]['body'], $root ), 'El aviso de la restauración interrumpida lleva una ruta del servidor.' );
		echo "DIGITALÍSIMO Backups: modo de mantenimiento durante el SQL y los archivos, renovado, retirado al terminar, al fallar y tras una muerte (con aviso), sin restaurar el .maintenance del respaldo ni seguir enlaces.\n";
	} finally {
		digi_rmtree( $root );
	}
} );
$B::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
throw new RuntimeException( 'FALLA: la restauración debía morir en mitad del SQL.' );
