<?php
/**
 * wp-config.php fuera de ABSPATH funciona: en la carpeta superior (donde WordPress también lo carga) o como enlace
 * simbólico a los archivos compartidos de un despliegue, se guarda en el respaldo y la restauración nunca lo escribe,
 * ni crea otro en ABSPATH que tapara el de la carpeta superior. Un enlace simbólico en cualquier otro lugar detiene el
 * respaldo con la ruta, su destino y qué hacer, sin dejar un ZIP incompleto.
 */
$root = sys_get_temp_dir() . '/digitalisimo-config-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/site/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/site/public/' );
define( 'WP_CONTENT_DIR', $root . '/site/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/site/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/site/public', null, false );
$parent = $root . '/site/wp-config.php';
$store = $root . '/private';
file_put_contents( $parent, "<?php // configuración en la carpeta superior\n" );

/** Contenido de una entrada del ZIP registrado. */
function digi_zip_entry( $file, $name ) {
	$zip = new ZipArchive();
	$zip->open( $file );
	$bytes = $zip->getFromName( $name );
	$zip->close();
	return $bytes;
}

try {
	// 1. En la carpeta superior.
	$status = Digitalisimo_Backups::service_status();
	digi_assert( true === $status['ready'] && 'parent' === $status['wp_config'], 'Una instalación con wp-config.php en la carpeta superior no quedó lista.' );
	$backup = Digitalisimo_Backups::service_create();
	digi_assert( ! is_wp_error( $backup ), 'El respaldo con wp-config.php en la carpeta superior falló: ' . ( is_wp_error( $backup ) ? $backup->get_error_message() : '' ) );
	digi_assert( "<?php // configuración en la carpeta superior\n" === digi_zip_entry( $store . '/' . $backup['file'], 'wordpress/wp-config.php' ), 'El respaldo no guardó wp-config.php de la carpeta superior.' );
	file_put_contents( $parent, "<?php // configuración editada después del respaldo\n" );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado\n" );
	$restored = Digitalisimo_Backups::service_restore( $backup['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( ! is_wp_error( $restored ) && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'La restauración con wp-config.php en la carpeta superior falló.' );
	digi_assert( ! file_exists( ABSPATH . 'wp-config.php' ), 'La restauración creó un wp-config.php en ABSPATH que taparía el de la carpeta superior.' );
	digi_assert( "<?php // configuración editada después del respaldo\n" === file_get_contents( $parent ), 'La restauración reescribió wp-config.php de la carpeta superior.' );

	// 2. Enlace simbólico a los archivos compartidos de un despliegue.
	mkdir( $root . '/shared', 0700 );
	file_put_contents( $root . '/shared/wp-config.php', "<?php // configuración compartida\n" );
	symlink( $root . '/shared/wp-config.php', ABSPATH . 'wp-config.php' );
	$status = Digitalisimo_Backups::service_status();
	digi_assert( true === $status['ready'] && 'abspath' === $status['wp_config'], 'Un wp-config.php enlazado no dejó la instalación lista.' );
	$linked = Digitalisimo_Backups::service_create();
	digi_assert( ! is_wp_error( $linked ), 'Un wp-config.php enlazado detuvo el respaldo: ' . ( is_wp_error( $linked ) ? $linked->get_error_message() : '' ) );
	digi_assert( "<?php // configuración compartida\n" === digi_zip_entry( $store . '/' . $linked['file'], 'wordpress/wp-config.php' ), 'El respaldo no guardó el contenido del wp-config.php enlazado.' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado otra vez\n" );
	$restored = Digitalisimo_Backups::service_restore( $linked['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( ! is_wp_error( $restored ) && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'La restauración con wp-config.php enlazado falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : '' ) );
	digi_assert( is_link( ABSPATH . 'wp-config.php' ) && realpath( ABSPATH . 'wp-config.php' ) === $root . '/shared/wp-config.php' && "<?php // configuración compartida\n" === file_get_contents( $root . '/shared/wp-config.php' ), 'La restauración rompió el enlace de wp-config.php o reescribió su destino.' );

	// 3. Cualquier otro enlace detiene el respaldo con un mensaje que nombra la ruta, su destino y qué hacer.
	mkdir( $root . '/vendor-plugin', 0700 );
	file_put_contents( $root . '/vendor-plugin/plugin.php', "<?php // plugin fuera de WordPress\n" );
	symlink( $root . '/vendor-plugin', WP_PLUGIN_DIR . '/enlazado' );
	$before = digi_stored( $store );
	$history = get_option( Digitalisimo_Backups::HISTORY );
	$error = Digitalisimo_Backups::service_create();
	digi_assert( is_wp_error( $error ) && 'El respaldo completo no puede omitir el enlace simbólico ' . WP_PLUGIN_DIR . '/enlazado (apunta a ' . $root . '/vendor-plugin). Sustitúyelo por el archivo o la carpeta real para respaldar la instalación completa.' === $error->get_error_message(), 'Un enlace simbólico no se explicó: ' . ( is_wp_error( $error ) ? $error->get_error_message() : 'el respaldo terminó' ) );
	digi_assert( $before === digi_stored( $store ) && $history === get_option( Digitalisimo_Backups::HISTORY ), 'El respaldo detenido dejó un ZIP incompleto o cambió el historial.' );
	unlink( WP_PLUGIN_DIR . '/enlazado' );

	// 4. Sin wp-config.php legible, ninguna superficie empieza.
	unlink( ABSPATH . 'wp-config.php' );
	unlink( $parent );
	$status = Digitalisimo_Backups::service_status();
	digi_assert( false === $status['ready'] && '' === $status['wp_config'] && in_array( 'No se puede leer wp-config.php en la raíz de WordPress ni en su carpeta superior.', $status['problems'], true ), 'El estado no avisó que falta wp-config.php.' );
	$error = Digitalisimo_Backups::service_create();
	digi_assert( is_wp_error( $error ) && 422 === $error->get_error_data()['status'], 'Se respaldó una instalación sin wp-config.php.' );
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: wp-config.php en la carpeta superior o enlazado se respalda y nunca se restaura encima; otros enlaces detienen el respaldo con un mensaje claro.\n";
