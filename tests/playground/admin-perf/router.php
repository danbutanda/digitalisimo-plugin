<?php
/**
 * Enrutador del servidor nativo (`php -S`, modo NATIVE=1 de admin-perf.sh): sirve los archivos que existen y pasa lo
 * demás (la REST en /wp-json/, los enlaces permanentes) a index.php, como la reescritura de un servidor web.
 */
$digi_path = (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
$digi_file = $_SERVER['DOCUMENT_ROOT'] . $digi_path;
if ( '/' !== $digi_path && ( is_file( $digi_file ) || ( is_dir( $digi_file ) && is_file( rtrim( $digi_file, '/' ) . '/index.php' ) ) ) ) {
	return false;
}
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
