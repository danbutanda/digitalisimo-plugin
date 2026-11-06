<?php
/**
 * Compatibilidad en los dos sentidos con el código real de DIGITALÍSIMO Backups 1.0.58 (tomado de su ZIP publicado en
 * dist/ o rollback/): sus respaldos se verifican y restauran con el motor nuevo, y los del motor nuevo pasan su
 * verificador y su restauración, de modo que volver a la versión anterior sigue restaurando. También: filas antiguas
 * del historial, la opción del avance manual, el AJAX y los hooks de cron anteriores, y los restos que dejaba al morir.
 */
require __DIR__ . '/backups-jobs/env.php';
bk_no_backoff();

// Código real de 1.0.58, con la clase renombrada para convivir con la actual.
$package = null;
foreach ( array( 'dist', 'rollback' ) as $folder ) if ( is_file( __DIR__ . '/../' . $folder . '/digitalisimo-backups-1.0.58.zip' ) ) $package = __DIR__ . '/../' . $folder . '/digitalisimo-backups-1.0.58.zip';
bk_assert( $package, 'No se encontró digitalisimo-backups-1.0.58.zip en dist/ ni en rollback/.' );
$zip = new ZipArchive();
bk_assert( true === $zip->open( $package ), 'No se pudo abrir el paquete 1.0.58.' );
$legacy_source = $zip->getFromName( 'digitalisimo-backups/includes/class-backups.php' );
$zip->close();
bk_assert( is_string( $legacy_source ) && 1 === substr_count( $legacy_source, 'final class Digitalisimo_Backups {' ) && false === strpos( $legacy_source, 'Digitalisimo_Backups::' ), 'El código de 1.0.58 no tiene la forma esperada.' );
$legacy_file = $GLOBALS['bk_root'] . '/class-backups-1.0.58.php';
file_put_contents( $legacy_file, str_replace( 'final class Digitalisimo_Backups {', 'final class Digitalisimo_Backups_1058 {', $legacy_source ) );
require $legacy_file;
function legacy( $method ) { $reflection = new ReflectionMethod( 'Digitalisimo_Backups_1058', $method ); if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true ); return $reflection; }
function current_method( $method ) { $reflection = new ReflectionMethod( 'Digitalisimo_Backups', $method ); if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true ); return $reflection; }
function rows_for( $file ) { return array_values( array_filter( bk_history(), function( $row ) use ( $file ) { return ( $row['file'] ?? '' ) === $file; } ) ); }
function respond( callable $call ) { try { $call(); } catch ( RuntimeException $response ) { return $response->getMessage(); } bk_fail( 'El controlador no respondió.' ); }

// 1.0.58 → actual: un respaldo hecho y registrado por el código anterior se verifica y se restaura.
$old_file = legacy( 'create_network' )->invoke( null );
legacy( 'remember' )->invoke( null, $old_file, 'local', 'installation', array( 'id' => 'manual', 'type' => 'full' ) );
$old_row = rows_for( basename( $old_file ) )[0];
bk_assert( ! isset( $old_row['job'] ) && ! isset( $old_row['transfer'] ), 'La fila de 1.0.58 ya traía claves nuevas.' );
Digitalisimo_Backups::job_verify_package( $old_file );
$destination = current_method( 'history_destination' );
bk_assert( 'Hosting / local' === $destination->invoke( null, $old_row ), 'Una fila antigua no se muestra como antes.' );
$GLOBALS['wpdb']->queries = array();
current_method( 'restore_digitalisimo_zip' )->invoke( null, $old_file );
bk_assert( in_array( 'SET foreign_key_checks = 1', $GLOBALS['wpdb']->queries, true ), 'La restauración del respaldo de 1.0.58 no importó su SQL.' );
$safety = array_values( array_filter( bk_history(), function( $row ) { return 'pre-restore' === ( $row['policy'] ?? '' ); } ) );
bk_assert( 1 === count( $safety ) && $safety[0]['locked'] && 0 === strpos( $safety[0]['file'], 'safety-before-restore-digitalisimo-backup-' ) && rows_for( basename( $old_file ) ), 'La restauración no dejó la copia previa bloqueada o perdió la fila antigua.' );
Digitalisimo_Backups::job_verify_package( bk_storage() . '/' . $safety[0]['file'] );

// Actual → 1.0.58: un respaldo del motor nuevo pasa el verificador y la restauración de la versión anterior.
$job = bk_manual();
bk_run_until_done( $job['id'] );
$new_file = bk_storage() . '/' . $job['spec']['file'];
$check = new ZipArchive();
bk_assert( true === $check->open( $new_file, ZipArchive::CHECKCONS ), 'El ZIP nuevo no supera CHECKCONS.' );
legacy( 'verify_zip_contents' )->invoke( null, $check );
$check->close();
$GLOBALS['wpdb']->queries = array();
legacy( 'restore_digitalisimo_zip' )->invoke( null, $new_file );
bk_assert( in_array( 'SET foreign_key_checks = 1', $GLOBALS['wpdb']->queries, true ), 'La restauración de 1.0.58 no importó el SQL del paquete nuevo.' );
$manifest = bk_zip_entries( $new_file );
bk_assert( 'digitalisimo-backup/v3' === $manifest['format'] && 3 === $manifest['backup_format_version'] && $manifest['file_count'] === count( $manifest['entries'] ) - 1 && ! isset( $manifest['entries']['manifest.json'] ), 'El manifiesto nuevo cambió sus claves obligatorias.' );

// v2 de red completa e Updraft siguen reconociéndose como antes.
$v2 = bk_storage() . '/digitalisimo-network-v2.zip';
$zip = new ZipArchive();
$zip->open( $v2, ZipArchive::CREATE );
$zip->addFromString( 'database.sql', "SET NAMES utf8mb4;\n" );
$zip->addFromString( 'manifest.json', json_encode( array( 'format' => 'digitalisimo-backup/v2', 'scope' => 'network', 'type' => 'full' ) ) );
$zip->addFromString( 'wordpress/index.php', bk_tree()['index.php'] );
$zip->close();
current_method( 'restore_digitalisimo_zip' )->invoke( null, $v2 );
unlink( $v2 );
$updraft = WP_CONTENT_DIR . '/updraft';
mkdir( $updraft, 0700, true );
file_put_contents( $updraft . '/backup_2026-11-05-1200_Sitio_abcdef123456-db.gz', gzencode( "SET NAMES utf8mb4;\n" ) );
file_put_contents( $updraft . '/backup_2026-11-05-1200_Sitio_abcdef123456-plugins.zip', 'PK' );
$sets = Digitalisimo_Backups::detect_updraft_sets();
bk_assert( ! empty( $sets['backup_2026-11-05-1200_Sitio_abcdef123456']['complete'] ) && true === Digitalisimo_Backups::validate_backup_set( $sets['backup_2026-11-05-1200_Sitio_abcdef123456'] ), 'Un conjunto Updraft completo dejó de reconocerse.' );
Digitalisimo_Backups_Job_Store::remove_tree( $updraft );

// AJAX de 1.0.58 (pestaña abierta durante la actualización): la opción antigua no bloquea y las respuestas tienen su forma.
update_option( 'digitalisimo_backups_manual_job_17', array( 'token' => 'viejo', 'status' => 'running', 'updated' => time() ) );
$_POST = array( 'nonce' => 'ok', 'context' => 'site', 'destination' => 'local' );
$start = respond( array( 'Digitalisimo_Backups', 'manual_start' ) );
bk_assert( 0 === strpos( $start, 'OK:' ), 'La opción del avance manual de 1.0.58 bloqueó un respaldo: ' . $start );
$started = json_decode( substr( $start, 3 ), true );
bk_assert( 'queued' === $started['job']['status'] && array( 'status', 'phase', 'percent', 'message', 'file' ) === array_keys( $started['job'] ), 'manual_start no conserva la forma anterior.' );
$_POST['token'] = $started['token'];
$work = respond( array( 'Digitalisimo_Backups', 'manual_work' ) );
bk_assert( 'OK:{"status":"complete"}' === $work, 'manual_work no terminó el respaldo en la misma petición: ' . $work );
$status = json_decode( substr( respond( array( 'Digitalisimo_Backups', 'manual_status' ) ), 3 ), true );
bk_assert( 'complete' === $status['status'] && 100 === $status['percent'] && $started['view']['id'] === $started['token'] && '' !== $status['file'] && rows_for( $status['file'] ), 'manual_status no devuelve el resultado con la forma anterior.' );
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups', 'manual_work' ) ), 'ERROR:409:' ), 'manual_work aceptó repetir un trabajo terminado.' );

// Hooks de cron anteriores: conservan nombre y argumentos, encolan y avanzan.
$GLOBALS['bk_current_filter'] = 'digitalisimo_backups_full';
Digitalisimo_Backups::scheduled_backup( 1 );
$legacy_jobs = array_values( array_filter( Digitalisimo_Backups_Jobs::all(), function( $job ) { return 'legacy-full' === $job['spec']['policy']; } ) );
bk_assert( 1 === count( $legacy_jobs ) && 'success' === $legacy_jobs[0]['status'] && 'cron' === $legacy_jobs[0]['spec']['origin']['via'] && rows_for( $legacy_jobs[0]['spec']['file'] ), 'El hook digitalisimo_backups_full no encoló y completó el respaldo.' );
$GLOBALS['bk_current_filter'] = 'digitalisimo_backups_db';
$count = count( Digitalisimo_Backups_Jobs::all() );
Digitalisimo_Backups::scheduled_backup( 1 );
bk_assert( count( Digitalisimo_Backups_Jobs::all() ) === $count, 'El hook parcial digitalisimo_backups_db encoló un trabajo.' );
$GLOBALS['bk_current_filter'] = 'digitalisimo_backups_policy';
Digitalisimo_Backups::scheduled_policy( 'monthly-full', 1 );
$policy_jobs = array_values( array_filter( Digitalisimo_Backups_Jobs::all(), function( $job ) { return 'monthly-full' === $job['spec']['policy']; } ) );
bk_assert( 1 === count( $policy_jobs ) && 'success' === $policy_jobs[0]['status'] && 4 === $policy_jobs[0]['spec']['retain'] && 'policy' === $policy_jobs[0]['spec']['retention'], 'El hook de la política no encoló y completó su respaldo.' );

// Una restauración se niega mientras haya un respaldo pendiente.
$pending = bk_manual( 'local', 'pendiente' );
$_POST = array( '_wpnonce' => 'ok', 'confirmation' => 'RESTAURAR', 'backup_file' => basename( $new_file ) );
$GLOBALS['bk_cron'] = array(); // Lo que deja una restauración: la opción `cron` del SQL restaurado, sin el tick.
$refused = respond( array( 'Digitalisimo_Backups', 'restore_request' ) );
bk_assert( false !== strpos( $refused, 'backup=error' ) && false !== strpos( rawurldecode( urldecode( $refused ) ), 'Hay un respaldo pendiente o en curso' ), 'Se restauró con un respaldo pendiente.' );
bk_assert( false !== wp_next_scheduled( Digitalisimo_Backups_Jobs::HOOK_TICK ), 'Tras la restauración, el trabajo en cola quedó sin tick programado.' );
Digitalisimo_Backups_Jobs::cancel( $pending['id'] );

// Restos de 1.0.58 tras una muerte: el watchdog sólo retira los temporales de más de 24 h; nunca un ZIP con nombre final.
$old = time() - 90000;
$orphans = array( sys_get_temp_dir() . '/digitalisimo-sql-AbC123', bk_storage() . '/digitalisimo-backup-x-20261101-000000-abcd1234.zip.Xy12Z9', bk_storage() . '/.cloud-check-Qw3rTy' );
foreach ( $orphans as $path ) { file_put_contents( $path, 'resto' ); touch( $path, $old ); }
$recent = bk_storage() . '/digitalisimo-backup-y-20261105-000000-abcd1234.zip.Ab12Cd';
file_put_contents( $recent, 'resto reciente' );
$final_old = bk_storage() . '/digitalisimo-backup-sin-registrar-20261101-000000-abcd1234.zip';
file_put_contents( $final_old, 'nunca se borra' );
touch( $final_old, $old );
Digitalisimo_Backups_Jobs::watchdog();
foreach ( $orphans as $path ) bk_assert( ! file_exists( $path ), 'El watchdog no retiró ' . basename( $path ) );
bk_assert( is_file( $recent ) && is_file( $final_old ), 'El watchdog borró un resto reciente o un ZIP con nombre final.' );
unlink( $recent );
unlink( $final_old );

// Disparadores: watchdog horario único y desactivación que los retira (los trabajos se quedan en disco).
$GLOBALS['bk_cron'] = array();
Digitalisimo_Backups_Jobs::ensure_watchdog();
Digitalisimo_Backups_Jobs::ensure_watchdog();
bk_assert( array( Digitalisimo_Backups_Jobs::HOOK_WATCHDOG ) === array_values( $GLOBALS['bk_cron'] ), 'El watchdog no quedó programado una sola vez.' );
wp_schedule_single_event( time(), Digitalisimo_Backups_Jobs::HOOK_TICK );
$jobs = count( Digitalisimo_Backups_Jobs::all() );
Digitalisimo_Backups_Jobs::deactivate();
bk_assert( ! $GLOBALS['bk_cron'] && count( Digitalisimo_Backups_Jobs::all() ) === $jobs, 'Desactivar no retiró los disparadores o borró trabajos.' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: compatibilidad con 1.0.58 en los dos sentidos, AJAX y cron anteriores, y restos antiguos verificados.\n";
