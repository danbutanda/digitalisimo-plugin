<?php
/**
 * Revisión del lote corto · R1: `wp digitalisimo-backups verify` en tabla muestra «Origen → este sitio» (la
 * reubicación) y las diferencias de wp-config.php a la vez, que es lo normal con un respaldo de otro servidor; con un
 * respaldo incompatible, el aviso de incompatibilidad y nunca la reubicación. Con el comando real y
 * Digitalisimo_Backups_Config_Diff real; el informe de verify es fijo.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CLI', true );
class WP_CLI {
	public static $out = array();
	public static function add_command( $name, $callable ) { return true; }
	public static function success( $message ) { self::$out[] = 'Success: ' . $message; }
	public static function warning( $message ) { self::$out[] = 'Warning: ' . $message; }
	public static function line( $message = '' ) { self::$out[] = (string) $message; }
	public static function log( $message ) { self::$out[] = (string) $message; }
	public static function error( $message ) { self::$out[] = 'Error: ' . $message; throw new RuntimeException( 'FALLA: ' . $message ); }
}
function is_wp_error( $value ) { return false; }
function wp_json_encode( $value ) { return json_encode( $value ); }
final class Digitalisimo_Backups {
	public static $report;
	public static function service_verify( $file, $args ) { return self::$report; }
}
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-config-diff.php';
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php';
function digi_assert( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( 'FALLA: ' . $message ); }

$relocation = 'Origen https://viejo.example → este sitio https://nuevo.example: se reescribirán 120 URL y el prefijo wp_ → abc_.';
$base = array( 'file' => 'digitalisimo-backup-x.zip', 'container' => 'zip', 'entries' => 10, 'sha256' => str_repeat( 'a', 64 ), 'trust' => 'untrusted', 'key' => '', 'compatible' => true, 'problems' => array(), 'relocation' => array( 'needed' => true, 'text' => $relocation ) );
$verify = function( array $report ) {
	Digitalisimo_Backups::$report = $report;
	WP_CLI::$out = array();
	( new Digitalisimo_Backups_Cli() )->verify( array( 'digitalisimo-backup-x.zip' ), array() );
	return WP_CLI::$out;
};
$config = function( $backup, $server ) { $diff = Digitalisimo_Backups_Config_Diff::compare( $backup, $server ); return array( 'available' => true, 'differences' => $diff, 'text' => Digitalisimo_Backups_Config_Diff::text( $diff ) ); };

// De otro servidor: la reubicación y las diferencias de wp-config.php, las dos.
$out = $verify( $base + array( 'wp_config' => $config( "<?php define('DB_PASSWORD','x1'); define('WP_DEBUG', true);", "<?php define('DB_PASSWORD','y2');" ) ) );
digi_assert( in_array( $relocation, $out, true ) && (bool) preg_grep( '/^Warning: wp-config\.php no se restaura/', $out ) && in_array( "  WP_DEBUG: sólo en el respaldo = 'true'", $out, true ), 'verify no muestra a la vez la reubicación y las diferencias de wp-config.php: ' . json_encode( $out ) );
// Con wp-config.php igual: sólo la reubicación.
$out = $verify( $base + array( 'wp_config' => $config( "<?php define('DB_NAME','a');", "<?php define('DB_NAME','a');" ) ) );
digi_assert( in_array( $relocation, $out, true ) && ! preg_grep( '/wp-config\.php/', $out ), 'Con wp-config.php igual, verify no muestra sólo la reubicación: ' . json_encode( $out ) );
// Incompatible: el aviso y nunca «Origen → este sitio».
$out = $verify( array( 'compatible' => false, 'problems' => array( 'No se puede restaurar directamente entre WordPress individual y Multisite.' ) ) + $base + array( 'wp_config' => $config( "<?php define('DB_NAME','a');", "<?php define('DB_NAME','b');" ) ) );
digi_assert( ! in_array( $relocation, $out, true ) && (bool) preg_grep( '/^Warning: No se puede restaurar en esta instalación/', $out ) && (bool) preg_grep( '/^Warning: wp-config\.php no se restaura/', $out ), 'Un respaldo incompatible mostró la reubicación o perdió el aviso: ' . json_encode( $out ) );

echo "DIGITALÍSIMO Backups: wp … verify muestra la reubicación y las diferencias de wp-config.php juntas, y nunca la reubicación de un respaldo incompatible.\n";
