<?php
/**
 * H1: restaurar desde la web con 64 MB. Un respaldo v3 con decenas de miles de entradas (el sitio del laboratorio tenía
 * 16 954 y su manifiesto, decodificado entero, agotaba 64 MB) se verifica (`verify` de REST y WP-CLI, la misma inspección
 * que la restauración) y se restaura entero por la restauración por pasos, con memory_limit=64M y 38 MB ya ocupados (lo
 * que ocupa WordPress con WooCommerce y Elements en una petición). La firma del manifiesto, su esquema, el índice, los
 * duplicados y el CRC/SHA-256 de cada entrada se leen por partes.
 * Debe fallar con el código anterior (E1): json_decode() del manifiesto entero agota la memoria.
 * BK_MEMORY_ENTRIES cambia el número de entradas (por defecto 40 000).
 */
require __DIR__ . '/lib/backups-env.php';
$entries = max( 1000, (int) ( getenv( 'BK_MEMORY_ENTRIES' ) ?: 40000 ) );
$storage = bk_storage();
$file = $storage . '/digitalisimo-backup-memoria-20261109-120000-00000001.zip';
// Un respaldo v3 completo, escrito a mano: SQL, archivos obligatorios y muchos medios pequeños.
$sql = "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\nDROP TABLE IF EXISTS `wp_options`;\nCREATE TABLE `wp_options` (\n  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `option_name` varchar(191) NOT NULL DEFAULT '',\n  `option_value` longtext NOT NULL,\n  PRIMARY KEY (`option_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\nINSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`) VALUES ('1','blogname','Sitio grande');\n\nSET foreign_key_checks = 1;\n";
$zip = new ZipArchive();
if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) throw new RuntimeException( 'No se pudo crear el ZIP de prueba.' );
$manifest_entries = array();
$add = function( $name, $content ) use ( $zip, &$manifest_entries ) {
	$zip->addFromString( $name, $content );
	$zip->setCompressionName( $name, ZipArchive::CM_STORE );
	$manifest_entries[ $name ] = array( 'size' => strlen( $content ), 'sha256' => hash( 'sha256', $content ) );
};
$add( 'database.sql', $sql );
foreach ( array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-content/plugins/example.php' ) as $name ) $add( 'wordpress/' . $name, file_get_contents( ABSPATH . $name ) );
for ( $i = 0; $i < $entries; $i++ ) $add( sprintf( 'wordpress/wp-content/uploads/2026/%02d/miniatura-%06d-150x150.jpg', $i % 12 + 1, $i ), 'm' . $i );
$total = 0;
foreach ( $manifest_entries as $entry ) $total += $entry['size'];
$manifest = array( 'format' => 'digitalisimo-backup/v3', 'backup_format_version' => 3, 'backup_id' => '11111111-2222-4333-8444-555555555555', 'type' => 'full', 'scope' => 'installation', 'multisite' => false, 'table_prefix' => 'wp_', 'database_tables' => array( 'wp_options' ), 'home_url' => 'https://example.test', 'site_url' => 'https://example.test', 'file_count' => count( $manifest_entries ) - 1, 'uncompressed_size' => $total, 'entries' => $manifest_entries );
$json = json_encode( $manifest );
$zip->addFromString( 'manifest.json', $json );
$zip->close();
$manifest_bytes = strlen( $json );
unset( $manifest, $json, $manifest_entries, $add, $zip );
gc_collect_cycles();
bk_call( 'Digitalisimo_Backups', 'remember', $file, 'local', 'installation', array( 'id' => 'manual', 'type' => 'full' ) );

// Los límites del hosting del propietario: 64 MB, con lo que ya ocupa WordPress con sus plugins en una petición.
ini_set( 'memory_limit', '64M' );
// Hasta 38 MB ocupados en total (lo que ocupa una petición de WordPress con WooCommerce y Elements, según el ensayo).
$ballast = str_repeat( "w", max( 0, 38 * 1048576 - memory_get_usage( true ) ) );
if ( getenv( "BK_VERBOSE" ) ) printf( "uso %.1f MB, real %.1f MB\n", memory_get_usage() / 1048576, memory_get_usage( true ) / 1048576 );
$base = memory_get_usage();
// La memoria no depende del número de entradas: además de no agotar los 64 MB, el pico adicional queda acotado (un
// json_decode() del manifiesto entero, de unos 20 MB con 40 000 entradas, lo pasaría). memory_reset_peak_usage() desde 8.2.
$bound = 12 * 1048576;
if ( function_exists( 'memory_reset_peak_usage' ) ) memory_reset_peak_usage();
$verified = Digitalisimo_Backups::service_verify( basename( $file ) );
if ( is_wp_error( $verified ) ) throw new RuntimeException( 'La verificación del respaldo grande falló: ' . $verified->get_error_message() );
if ( empty( $verified['verified'] ) || $entries + 8 !== (int) $verified['entries'] || 'provenance' !== $verified['trust'] ) throw new RuntimeException( 'La verificación no comprobó todas las entradas: ' . json_encode( $verified ) );
$verify_peak = memory_get_peak_usage() - $base;
if ( function_exists( 'memory_reset_peak_usage' ) && $verify_peak > $bound ) throw new RuntimeException( sprintf( 'La verificación no tiene la memoria acotada: %.1f MB adicionales con %d entradas.', $verify_peak / 1048576, $entries + 8 ) );
if ( function_exists( 'memory_reset_peak_usage' ) ) memory_reset_peak_usage();

// La restauración por pasos entera, por tramos: verificación (índice en disco, CRC y SHA-256), copia previa, base de
// datos a tablas temporales, cada archivo comparado y preparado, cambio y limpieza.
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 5000;
$started = microtime( true );
$restore = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false ) );
// En la web la respuesta sale tras 25 s de trabajo como mucho (keep-access, B8): lo demás sigue por ticks, como en el sitio.
if ( ! is_wp_error( $restore ) && ! empty( $restore['restoring'] ) ) {
	for ( $i = 0; $i < 5000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick();
	$done = Digitalisimo_Backups_Jobs::get( (string) $restore['job']['id'] );
	if ( $done && 'success' === $done['status'] ) $restore = (array) ( $done['result']['restore'] ?? array() ) + array( 'restored' => true );
}
$seconds = microtime( true ) - $started;
if ( is_wp_error( $restore ) || empty( $restore['restored'] ) ) throw new RuntimeException( 'La restauración del respaldo grande no terminó: ' . ( is_wp_error( $restore ) ? $restore->get_error_message() : json_encode( $restore ) ) );
$job = null;
foreach ( Digitalisimo_Backups_Jobs::all() as $candidate ) if ( 'restore' === $candidate['kind'] ) $job = $candidate;
if ( (int) ( $job['checkpoint']['inspect']['entries'] ?? 0 ) !== $entries + 8 ) throw new RuntimeException( 'La verificación de la restauración no comprobó todas las entradas.' );
$restore_peak = memory_get_peak_usage() - $base;
if ( function_exists( 'memory_reset_peak_usage' ) && $restore_peak > $bound ) throw new RuntimeException( sprintf( 'La restauración no tiene la memoria acotada: %.1f MB adicionales con %d entradas.', $restore_peak / 1048576, $entries + 8 ) );
if ( 'Sitio grande' !== ( $GLOBALS['bk_options']['blogname'] ?? '' ) || ! is_file( ABSPATH . sprintf( 'wp-content/uploads/2026/%02d/miniatura-%06d-150x150.jpg', ( $entries - 1 ) % 12 + 1, $entries - 1 ) ) ) throw new RuntimeException( 'La restauración no dejó la base de datos o los archivos del respaldo.' );
unset( $ballast );
bk_cleanup();
printf( "DIGITALÍSIMO Backups: %d entradas (manifiesto de %.1f MB) verificadas y restauradas por pasos con memory_limit=64M y 38 MB ocupados (%.0f s): pico adicional %.1f MB al verificar y %.1f MB al restaurar.\n", $entries + 8, $manifest_bytes / 1048576, $seconds, $verify_peak / 1048576, $restore_peak / 1048576 );
