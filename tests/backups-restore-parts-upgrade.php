<?php
/**
 * Revisión A1 de restaurar por partes: la restauración COMPLETA de un respaldo hecho con una versión anterior (1.0.75, de
 * rollback/) termina aunque su limpieza empiece en otro tick. Ese tick pone primero la carpeta de Backups del respaldo
 * (sin class-restore-parts.php) y después escribe el informe con Digitalisimo_Backups_Restore_Parts: la clase debe estar
 * cargada desde el principio de la petición, nunca a demanda (con la carga diferida, PHP 7.4 daba un fatal y dejaba
 * .maintenance; PHP 8 dejaba la limpieza fallida). Tres procesos de PHP reales (proc_open), como la CI:
 *   p1  el sitio tiene Backups 1.0.75; se crea el respaldo completo;
 *   p2  el sitio se actualiza al Backups del repositorio; restauración completa por la web hasta entrar en la limpieza;
 *   p3  otro tick termina la limpieza: el trabajo termina bien, sin fatal, sin mantenimiento y con la base del respaldo.
 * Con php-wasm (sin proc_open) se corre a mano: ver tests/backups-restore-parts-upgrade/.
 */
$php = getenv( 'PHP_BIN' ) ?: PHP_BINARY;
$repo = dirname( __DIR__ );
$zip = $repo . '/rollback/digitalisimo-backups-1.0.75.zip';
if ( ! is_file( $zip ) || ! class_exists( 'ZipArchive' ) ) { echo "FALLA · falta rollback/digitalisimo-backups-1.0.75.zip o ZipArchive.\n"; exit( 1 ); }
$S = sys_get_temp_dir() . '/digitalisimo-parts-upgrade-' . bin2hex( random_bytes( 4 ) );
function up_copy( $from, $to ) {
	if ( is_file( $from ) ) { @mkdir( dirname( $to ), 0700, true ); copy( $from, $to ); return; }
	@mkdir( $to, 0700, true );
	foreach ( scandir( $from ) as $name ) if ( '.' !== $name && '..' !== $name ) up_copy( $from . '/' . $name, $to . '/' . $name );
}
function up_rm( $path ) {
	if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return; }
	if ( ! is_dir( $path ) ) return;
	foreach ( scandir( $path ) as $name ) if ( '.' !== $name && '..' !== $name ) up_rm( $path . '/' . $name );
	@rmdir( $path );
}
foreach ( array( 'store', 'site/wp-admin', 'site/wp-includes', 'site/wp-content/plugins', 'site/wp-content/uploads', 'code' ) as $dir ) mkdir( $S . '/' . $dir, 0700, true );
foreach ( array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php' ) as $file ) file_put_contents( $S . '/site/' . $file, '<?php // ' . $file . "\n" );
// p1 corre con el código del repositorio fuera del sitio; p2 y p3 con el del sitio (el entorno carga ../../digitalisimo-backups).
up_copy( $repo . '/digitalisimo-backups', $S . '/code/digitalisimo-backups' );
up_copy( $repo . '/tests/lib', $S . '/code/tests/lib' );
up_copy( $repo . '/tests/lib', $S . '/site/wp-content/plugins/tests/lib' );
$archive = new ZipArchive();
$archive->open( $zip );
$archive->extractTo( $S . '/site/wp-content/plugins/' );
$archive->close();
$failures = 0;
$run = function( $step ) use ( $php, $S, &$failures ) {
	$process = proc_open( array( $php, __DIR__ . '/backups-restore-parts-upgrade/' . $step . '.php' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, array_merge( getenv(), array( 'DIGI_PARTS_UPGRADE' => $S ) ) );
	$out = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$code = proc_close( $process );
	foreach ( explode( "\n", trim( $out ) ) as $line ) if ( '' !== trim( $line ) ) { echo '  ' . $line . "\n"; if ( 0 === strpos( $line, 'FALLA' ) || false !== strpos( $line, 'Fatal error' ) ) $failures++; }
	return $code;
};
$run( 'p1' );
// El sitio se actualiza al Backups del repositorio (la versión con la restauración por partes).
up_rm( $S . '/site/wp-content/plugins/digitalisimo-backups' );
up_copy( $repo . '/digitalisimo-backups', $S . '/site/wp-content/plugins/digitalisimo-backups' );
$run( 'p2' );
$run( 'p3' );
if ( file_exists( $S . '/site/.maintenance' ) ) { echo "FALLA · el sitio quedó en mantenimiento.\n"; $failures++; }
$version = (string) @file_get_contents( $S . '/site/wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' );
if ( false === strpos( $version, '1.0.75' ) ) { echo "FALLA · no quedó el Backups del respaldo (1.0.75).\n"; $failures++; }
up_rm( $S );
echo $failures ? "FALLA: la restauración de un respaldo anterior no terminó tras una limpieza en otro tick.\n" : "DIGITALÍSIMO Backups: la restauración completa de un respaldo de 1.0.75 termina aunque su limpieza empiece en otro tick (la clase de partes ya está cargada).\n";
exit( $failures ? 1 : 0 );
