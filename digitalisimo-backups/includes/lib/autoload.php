<?php
/**
 * Autoload de las bibliotecas incluidas con prefijo propio (scripts/vendor-phpseclib.sh): phpseclib 3 y
 * constant_time_encoding 2, sólo bajo `DigitalisimoBackups\Vendor\`. Lo carga el destino SFTP cuando la extensión ssh2
 * no está o la conexión elige el cliente incluido; nunca toca el phpseclib que traiga otro plugin.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'digitalisimo_backups_vendor_autoload' ) ) {
	function digitalisimo_backups_vendor_autoload( $class ) {
		static $prefixes = array(
			'DigitalisimoBackups\\Vendor\\phpseclib3\\' => 'phpseclib3/',
			'DigitalisimoBackups\\Vendor\\ParagonIE\\ConstantTime\\' => 'ParagonIE/ConstantTime/',
		);
		foreach ( $prefixes as $prefix => $dir ) {
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) continue;
			$relative = substr( $class, strlen( $prefix ) );
			// Sólo nombres de clase PHP: nada de «..» ni separadores raros llega a la ruta.
			if ( ! preg_match( '/^[A-Za-z0-9_]+(\\\\[A-Za-z0-9_]+)*$/', $relative ) ) return;
			$file = __DIR__ . '/' . $dir . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_file( $file ) ) require $file;
			return;
		}
	}
	spl_autoload_register( 'digitalisimo_backups_vendor_autoload' );
}
