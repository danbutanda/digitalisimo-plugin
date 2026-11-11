<?php
/**
 * Hallazgos del ensayo real E4 (/var/tmp/digi-wt/bk-lab/E4.md §6) que no tienen prueba propia:
 *   H25  `wp digitalisimo-backups status` en formato tabla imprimía «Array to string conversion» y «Array» en el campo
 *        `restore` ({state, notice}): ahora cada campo compuesto sale en texto legible;
 *   obs. durante la migración el destino decía «otra operación ocupa el almacén del origen; se espera» mientras el paquete
 *        avanzaba: el tick del origen no tomaba el almacén porque lo tenía el propio trabajo del paquete (otra petición
 *        o WP-Cron con su arrendamiento vivo). Eso ya no cuenta como «otra operación».
 * (H22, la fila sin copias que dejaba la retención remota, está en backups-remote-retention.php.)
 */
$root = sys_get_temp_dir() . '/digitalisimo-e4-findings-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
try {
	Digitalisimo_Backups_Cli::register();
	// H25: con un aviso o un error de PHP (como «Array to string conversion») la prueba falla.
	set_error_handler( function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
	try {
		list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'status' );
	} finally {
		restore_error_handler();
	}
	$text = implode( "\n", $out );
	digi_assert( 0 === $code && false === strpos( $text, 'Array' ), 'wp … status muestra «Array» en algún campo: ' . $text );
	digi_assert( (bool) preg_match( '/"campo":"restore","valor":"[^"]+"/u', $text ), 'wp … status no da el campo restore en texto: ' . $text );
	digi_assert( 'state: ok, notice: —, detalle: (a: 1, b: sí)' === Digitalisimo_Backups_Cli::status_text( array( 'state' => 'ok', 'notice' => null, 'detalle' => array( 'a' => 1, 'b' => true ) ) ), 'Un campo compuesto no sale en una línea legible.' );

	// Observación: el almacén lo tiene el propio trabajo del paquete (arrendamiento vivo) → no es «otra operación».
	$busy = new ReflectionMethod( 'Digitalisimo_Backups_Migrate', 'source_busy' );
	if ( PHP_VERSION_ID < 80100 ) $busy->setAccessible( true );
	$alive = array( 'lease' => array( 'owner' => 'x', 'heartbeat_at' => time() - 3 ) );
	$dead = array( 'lease' => array( 'owner' => 'x', 'heartbeat_at' => time() - Digitalisimo_Backups_Jobs::STALE_AFTER - 60 ) );
	digi_assert( false === $busy->invoke( null, true, $alive ), 'Con el paquete en marcha en otra petición, el origen se anuncia «ocupado por otra operación».' );
	digi_assert( true === $busy->invoke( null, true, array( 'lease' => null ) ) && true === $busy->invoke( null, true, $dead ), 'Con el almacén en manos de otra operación, el origen no se anuncia ocupado.' );
	digi_assert( false === $busy->invoke( null, false, array( 'lease' => null ) ), 'Sin cerrojo ocupado, el origen se anuncia ocupado.' );
	echo "OK: hallazgos del ensayo E4 (H25 y el «ocupado» de la migración)\n";
} finally {
	digi_rmtree( $root );
}
