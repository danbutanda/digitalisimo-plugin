<?php
/**
 * Laboratorio H6: en Webuzo con OpenLiteSpeed, un cron recarga el servidor web de todos los sitios cuando algún
 * `.htaccess` cambia de fecha, y una de esas recargas cortó una restauración. Backups ya no reescribe lo que tiene el
 * contenido correcto: la restauración deja intacto (contenido y fecha) un `.htaccess`, `web.config` o `index.php` del
 * sitio idéntico al del respaldo y sólo escribe lo distinto, y la protección del almacén (`.htaccess` e `index.php`)
 * sólo se escribe si falta o no dice lo que debe.
 */
$root = sys_get_temp_dir() . '/digitalisimo-no-rewrite-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );

$B = 'Digitalisimo_Backups';
$store = $root . '/private';
$site = $root . '/public';
$old = time() - 2 * 86400;
$files = array(
	'.htaccess' => "# BEGIN WordPress\nRewriteEngine On\nRewriteRule . /index.php [L]\n# END WordPress\n",
	'web.config' => "<configuration><system.webServer><rewrite/></system.webServer></configuration>\n",
	'wp-content/index.php' => "<?php // Silence is golden.\n",
);
foreach ( $files as $name => $content ) { file_put_contents( $site . '/' . $name, $content ); touch( $site . '/' . $name, $old ); }
$mtime = function( $path ) { clearstatcache( true, $path ); return (int) filemtime( $path ); };

try {
	// La protección del almacén empieza con un contenido que no es el suyo (se comprueba al final).
	mkdir( $store, 0700 );
	file_put_contents( $store . '/.htaccess', "Allow from all\n" );
	update_option( $B::OPTION, array( 'retention' => '100' ) );
	$created = digi_backup();
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo.' );
	// Después del respaldo cambian dos archivos: uno con otro tamaño y otro con el mismo tamaño y otros bytes.
	file_put_contents( $site . '/wp-load.php', 'Contenido cambiado después del respaldo, más largo' );
	$settings = file_get_contents( $site . '/wp-settings.php' );
	file_put_contents( $site . '/wp-settings.php', strrev( $settings ) );
	$restored = $B::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( ! is_wp_error( $restored ), 'La restauración falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : '' ) );
	foreach ( $files as $name => $content ) digi_assert( $content === file_get_contents( $site . '/' . $name ) && $old === $mtime( $site . '/' . $name ), 'La restauración reescribió ' . $name . ', que ya era idéntico al del respaldo.' );
	digi_assert( 'Contenido de wp-load.php' === file_get_contents( $site . '/wp-load.php' ) && $settings === file_get_contents( $site . '/wp-settings.php' ), 'La restauración no devolvió un archivo que había cambiado (también con el mismo tamaño).' );
	// Con otro .htaccess en el sitio, la restauración sí escribe el del respaldo.
	file_put_contents( $site . '/.htaccess', "# reglas de otro servidor\n" );
	digi_assert( ! is_wp_error( $B::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) ) ) && $files['.htaccess'] === file_get_contents( $site . '/.htaccess' ), 'Un .htaccess distinto del del respaldo no se restauró.' );

	// La protección del almacén: la que tenía otro contenido se corrigió; la correcta no vuelve a escribirse.
	digi_assert( "Deny from all\n" === file_get_contents( $store . '/.htaccess' ) && "<?php // Silence is golden.\n" === file_get_contents( $store . '/index.php' ), 'La protección del almacén con otro contenido no se corrigió.' );
	touch( $store . '/.htaccess', $old );
	touch( $store . '/index.php', $old );
	digi_assert( ! is_wp_error( digi_backup() ) && $old === $mtime( $store . '/.htaccess' ) && $old === $mtime( $store . '/index.php' ), 'Un respaldo volvió a escribir la protección del almacén, que ya era correcta.' );

	echo "DIGITALÍSIMO Backups: la restauración y la protección del almacén no reescriben un .htaccess, web.config o index.php que ya es correcto.\n";
} finally {
	digi_rmtree( $root );
}
