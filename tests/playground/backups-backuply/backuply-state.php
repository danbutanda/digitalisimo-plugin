<?php
/** Estado del respaldo de Backuply para el ensayo: si sigue en curso, sus archivos y las últimas líneas de su registro. */
$digi_token = trim( (string) @file_get_contents( __DIR__ . '/token.txt' ) );
if ( '' === $digi_token || ! hash_equals( $digi_token, (string) ( $_GET['token'] ?? '' ) ) ) { http_response_code( 403 ); exit; }
require '/wordpress/wp-load.php';
$files = array();
foreach ( (array) glob( WP_CONTENT_DIR . '/backuply/backups-*/*.tar.gz' ) as $path ) $files[] = array( 'name' => basename( $path ), 'size' => filesize( $path ) );
$log = '';
if ( is_file( WP_CONTENT_DIR . '/backuply/backuply_log.php' ) ) $log = substr( (string) file_get_contents( WP_CONTENT_DIR . '/backuply/backuply_log.php' ), -2500 );
header( 'Content-Type: application/json; charset=utf-8' );
echo wp_json_encode( array( 'running' => (bool) get_option( 'backuply_status' ), 'status' => get_option( 'backuply_status' ), 'files' => $files, 'log' => $log ) );
