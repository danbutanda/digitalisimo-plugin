<?php
/**
 * La estimación de «Conservar» del servidor (Digitalisimo_Backups_Chain::estimate()) para cada automatización del JSON
 * que recibe (un archivo), en el mismo orden: lo usa tests/backups-retain-chains-ui.mjs para comprobar que
 * assets/retention.js da lo mismo. Uso: php tests/backups-retain-chains/estimates.php <casos.json>
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../../digitalisimo-backups/includes/class-backups-chain.php';
$cases = json_decode( (string) file_get_contents( (string) ( $argv[1] ?? '' ) ), true );
if ( ! is_array( $cases ) ) { fwrite( STDERR, "Sin casos.\n" ); exit( 2 ); }
echo json_encode( array_map( array( 'Digitalisimo_Backups_Chain', 'estimate' ), $cases ), JSON_UNESCAPED_UNICODE );
