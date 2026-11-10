<?php
/**
 * Un tramo del respaldo de Backuply (GET /wp-content/digi-web/backuply-run.php?token=…): lo que hace su petición
 * `backuply_handle_backup` (botón «Create Backup» con base de datos y archivos, en local) y, después, cada llamada a sí
 * mismo (`backuply_curl_backup`): `backuply_backup_execute()`, que incluye su backup_ins.php y termina con die().
 */
$digi_token = trim( (string) @file_get_contents( __DIR__ . '/token.txt' ) );
if ( '' === $digi_token || ! hash_equals( $digi_token, (string) ( $_GET['token'] ?? '' ) ) ) { http_response_code( 403 ); exit; }
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
wp_set_current_user( get_user_by( 'login', 'admin' )->ID );
if ( ! get_option( 'backuply_status' ) ) {
	if ( ! empty( $_GET['start'] ) ) {
		backuply_create_log_file();
		update_option( 'backuply_backup_stopped', false );
		update_option( 'backuply_status', array( 'backup_dir' => 1, 'backup_db' => 1, 'backup_location' => '', 'last_update' => time() ) );
	} else {
		echo 'sin respaldo en curso';
		exit;
	}
}
backuply_backup_execute();
echo 'tramo terminado';
