<?php
/**
 * Revisión 2 de R1: la retención de lo traído de un destino (`digitalisimo_backups_fetched`) es de este almacén, como el
 * historial. Es una opción, y el SQL de una restauración trae la del respaldo: sin conservarla, lo traído «sin
 * registro» que se restauró (o cualquier otro) nunca se borraba a los 7 días. Ahora viaja en el diario de la
 * restauración y vuelve tras el cambio de tablas, también si el proceso muere y la operación siguiente aplica el diario.
 */
require __DIR__ . '/lib/backups-env.php';
function rf_check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( 'FALLA: ' . $message ); }
$B = 'Digitalisimo_Backups';
try {
	// El respaldo se hace sin registro de lo traído: su SQL no lo lleva.
	$backup = bk_backup( true );
	$loose = bk_storage() . '/digitalisimo-backup-otro-sitio-test-20261101-101010-0a1b2c3d.zip';
	copy( $backup, $loose );
	$record = array( basename( $loose ) => array( 'at' => time() - 86400, 'sha256' => hash_file( 'sha256', $loose ), 'connection' => 'webdav', 'name' => basename( $loose ) ) );
	update_option( $B::FETCHED, $record, false );
	$GLOBALS['bk_options']['blogname'] = 'Cambiado después del respaldo';
	$result = $B::service_restore( basename( $backup ), array( 'confirm' => 'RESTAURAR', 'via' => 'test' ) );
	for ( $i = 0; $i < 2000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	rf_check( ! is_wp_error( $result ) && 'Sitio de prueba' === ( $GLOBALS['bk_options']['blogname'] ?? '' ), 'La restauración no devolvió la base del respaldo: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $GLOBALS['bk_options']['blogname'] ?? null ) ) );
	rf_check( $record === get_option( $B::FETCHED ), 'La restauración reemplazó el registro de lo traído con el del respaldo: ' . json_encode( get_option( $B::FETCHED ) ) );
	// Pasados los 7 días, la retención lo borra (la restauración no lo dejó fuera de su alcance).
	rf_check( array( basename( $loose ) ) === $B::fetched_cleanup( time() + 7 * 86400 ) && ! is_file( $loose ), 'Tras restaurar, lo traído no se borró a los 7 días.' );
	// Un diario de una restauración interrumpida lo devuelve también.
	update_option( $B::FETCHED, $record, false );
	bk_call( $B, 'write_restore_journal' );
	update_option( $B::FETCHED, array(), false );
	bk_call( $B, 'recover_interrupted_restore' );
	rf_check( $record === get_option( $B::FETCHED ), 'El diario de una restauración interrumpida no devolvió el registro de lo traído.' );
	echo "OK: el registro de lo traído de un destino sobrevive a la restauración (diario)\n";
} finally {
	bk_cleanup();
}
