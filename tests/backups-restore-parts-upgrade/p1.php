<?php
// Proceso 1: en el sitio está Backups 1.0.75 (sin la restauración por partes); se crea el respaldo completo con el código
// del repositorio (que lo trae dentro, en su carpeta del sitio).
require __DIR__ . '/common.php';
require getenv( 'DIGI_PARTS_UPGRADE' ) . '/code/tests/lib/backups-env.php';
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
$GLOBALS['bk_options']['blogname'] = 'Sitio del respaldo';
$GLOBALS['bk_options']['active_plugins'] = array( 'digitalisimo-backups/digitalisimo-backups.php' );
$backup = bk_backup( true );
$zip = new ZipArchive();
$zip->open( $backup );
$old = false !== strpos( (string) $zip->getFromName( 'wordpress/wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' ), '1.0.75' ) && false === $zip->locateName( 'wordpress/wp-content/plugins/digitalisimo-backups/includes/restore/class-restore-parts.php' );
$zip->close();
echo $old ? "p1: respaldo con Backups 1.0.75\n" : "FALLA · p1: el respaldo no trae la 1.0.75\n";
file_put_contents( getenv( 'DIGI_PARTS_UPGRADE' ) . '/backup.txt', basename( $backup ) );
up_save( getenv( 'DIGI_PARTS_UPGRADE' ) . '/state1.ser' );
