<?php
/**
 * Sistema de archivos de N3f (theme-network.sh): move() falla al llevar el paquete descomprimido a la carpeta de una
 * copia del tema, como un disco lleno o un permiso que falta. Todo lo demás funciona igual. No se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

class Digi_Net_Failing_Filesystem extends WP_Filesystem_Direct {
	public function move( $source, $destination, $overwrite = false ) {
		if ( false !== strpos( (string) $source, '/wp-content/upgrade/' ) && 0 === strpos( basename( untrailingslashit( (string) $destination ) ), 'digitalisimo-theme' ) ) {
			file_put_contents( '/wordpress/wp-content/probe/movefail.log', getenv( 'DIGI_STEP' ) . ' ' . $source . ' -> ' . $destination . "\n", FILE_APPEND );
			return false;
		}
		return parent::move( $source, $destination, $overwrite );
	}
}
