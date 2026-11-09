<?php
/**
 * Laboratorio H5: el ZIP del respaldo (que lleva wp-config.php y la base de datos), los cerrojos y la protección del
 * almacén quedaban 0644 (php-wasm: 0666); sólo los protegía la carpeta 0700. Ahora todo archivo del almacén es 0600:
 * el SQL y el ZIP del área de trabajo mientras se escriben, el respaldo publicado (ZIP o DGBK), la copia previa de
 * una restauración, el ZIP descifrado para restaurar, los cerrojos y la protección del almacén.
 */
$root = sys_get_temp_dir() . '/digitalisimo-private-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );

$B = 'Digitalisimo_Backups';
$store = $root . '/private';
$mode = function( $path ) { clearstatcache( true, $path ); return is_file( $path ) ? sprintf( '%o', fileperms( $path ) & 0777 ) : 'no existe'; };
/** Permisos de lo que hay en el área de trabajo al terminar cada fase. */
$GLOBALS['seen'] = array();
Digitalisimo_Backups_Jobs::$phase_runner = function( $phase, $job ) use ( $mode ) {
	// Desde la restauración por pasos, el motor también corre trabajos de restauración: cada uno con sus fases.
	$outcome = 'restore' === ( $job['kind'] ?? 'backup' ) ? Digitalisimo_Backups_Restore_Job::run_phase( $phase, $job ) : Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
	foreach ( (array) glob( Digitalisimo_Backups_Job_Store::staging( $job['id'] ) . '/*' ) as $path ) $GLOBALS['seen'][ $phase . ':' . preg_replace( '/\.[0-9a-f]{8}\./', '.', basename( $path ) ) ] = $mode( $path );
	return $outcome;
};

try {
	update_option( $B::OPTION, array( 'retention' => '100' ) );
	$created = digi_backup();
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo.' );
	digi_assert( '600' === $mode( $store . '/' . $created['file'] ), 'El ZIP del respaldo (lleva wp-config.php) no es 0600: ' . $mode( $store . '/' . $created['file'] ) );
	digi_assert( '600' === ( $GLOBALS['seen']['database:database.sql'] ?? '' ) && '600' === ( $GLOBALS['seen']['pack:package.zip'] ?? '' ), 'El SQL o el ZIP del área de trabajo no son 0600: ' . json_encode( $GLOBALS['seen'] ) );
	foreach ( array( '.operation.lock', '.jobs.lock', 'index.php', '.htaccess' ) as $name ) digi_assert( '600' === $mode( $store . '/' . $name ), $name . ' del almacén no es 0600: ' . $mode( $store . '/' . $name ) );
	// Un archivo que ya estaba con otros permisos (de una versión anterior) se corrige en la siguiente operación.
	chmod( $store . '/.htaccess', 0644 );
	chmod( $store . '/.operation.lock', 0644 );
	digi_assert( ! is_wp_error( digi_backup() ) && '600' === $mode( $store . '/.htaccess' ) && '600' === $mode( $store . '/.operation.lock' ), 'Los archivos del almacén de una versión anterior no se corrigieron a 0600.' );

	// La copia previa a una restauración, que también lleva wp-config.php y la base de datos.
	$restored = $B::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( ! is_wp_error( $restored ) && '' !== $restored['safety_backup'] && '600' === $mode( $store . '/' . $restored['safety_backup'] ), 'La copia previa no es 0600: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : $mode( $store . '/' . $restored['safety_backup'] ) ) );

	// Cifrado: el DGBK publicado, su sellado en el área de trabajo y el ZIP descifrado para restaurarlo.
	Digitalisimo_Backups_Keys::set_passphrase( 'frase de los permisos privados' );
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => true, 'require_signed' => false ) );
	$GLOBALS['seen'] = array();
	$sealed = digi_backup();
	digi_assert( ! is_wp_error( $sealed ) && '.dgbk' === substr( $sealed['file'], -5 ) && '600' === $mode( $store . '/' . $sealed['file'] ), 'El respaldo cifrado no es 0600.' );
	digi_assert( '600' === ( $GLOBALS['seen']['verify:package.dgbk'] ?? '' ), 'El DGBK sellado en el área de trabajo no es 0600: ' . json_encode( $GLOBALS['seen'] ) );
	$report = Digitalisimo_Backups_Security::open( $store . '/' . $sealed['file'], array(), '' );
	$inner = $report['path'];
	$inner_mode = $mode( $inner );
	Digitalisimo_Backups_Security::close( $report );
	digi_assert( '600' === $inner_mode && 0 === strpos( $inner, $store . '/' ), 'El ZIP descifrado para restaurar no es 0600: ' . $inner_mode );

	echo "DIGITALÍSIMO Backups: respaldos, copia previa, DGBK, ZIP descifrado, temporales del área de trabajo, cerrojos y protección del almacén en 0600.\n";
} finally {
	digi_rmtree( $root );
}
