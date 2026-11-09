<?php
/**
 * Restaurar una red en otro dominio y cortar el proceso entre la importación del SQL y la extracción de los archivos
 * (exit(), que no ejecuta ningún finally, como un corte de Cloudflare más una recarga del servidor, el tiempo máximo o el
 * OOM killer). La red tiene que quedar coherente: `blogs`, `site`, `sitemeta.siteurl` y `home`/`siteurl` de cada sitio
 * con el dominio y la ruta de destino, para que WordPress la encuentre y se pueda entrar a restaurar la copia previa.
 * Antes de corregirlo, `blogs` y `site` seguían con el dominio de origen hasta finish(), que corre después de extraer
 * todos los archivos: la red no cargaba ni por el dominio nuevo ni por el viejo.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
$GLOBALS['ri_network'] = null;
if ( ! function_exists( 'get_network' ) ) { function get_network() { return $GLOBALS['ri_network'] ? (object) $GLOBALS['ri_network'] : null; } }
if ( ! function_exists( 'is_subdomain_install' ) ) { function is_subdomain_install() { return false; } }
if ( ! function_exists( 'switch_to_blog' ) ) { function switch_to_blog( $id ) { return true; } }
if ( ! function_exists( 'restore_current_blog' ) ) { function restore_current_blog() { return true; } }

function ri_db( $name, $prefix ) { return new Bk_Sqlite_DB( $GLOBALS['bk_root'] . '-' . $name . '.sqlite', $prefix ); }
function ri_use( Bk_Sqlite_DB $db, $home, array $network ) {
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $home;
	$GLOBALS['bk_options']['siteurl'] = $home;
	$GLOBALS['bk_multisite'] = true;
	$GLOBALS['ri_network'] = $network;
}
function ri_site_tables( Bk_Sqlite_DB $db, $prefix ) {
	$db->pdo->exec( "CREATE TABLE `{$prefix}options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL)" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}posts` (`ID` INTEGER PRIMARY KEY, `post_content` longtext NOT NULL, `guid` varchar(255) NOT NULL DEFAULT '')" );
}

// Origen: red por subdirectorios origen.com con el subsitio /uno/, prefijo wp_.
$origin = ri_db( 'origen', 'wp_' );
ri_site_tables( $origin, 'wp_' );
ri_site_tables( $origin, 'wp_2_' );
$origin->pdo->exec( 'CREATE TABLE `wp_blogs` (`blog_id` INTEGER PRIMARY KEY, `site_id` bigint(20) NOT NULL DEFAULT 1, `domain` varchar(200) NOT NULL, `path` varchar(100) NOT NULL)' );
$origin->pdo->exec( 'CREATE TABLE `wp_site` (`id` INTEGER PRIMARY KEY, `domain` varchar(200) NOT NULL, `path` varchar(100) NOT NULL)' );
$origin->pdo->exec( 'CREATE TABLE `wp_sitemeta` (`meta_id` INTEGER PRIMARY KEY, `site_id` bigint(20) NOT NULL DEFAULT 1, `meta_key` varchar(255), `meta_value` longtext)' );
$origin->pdo->exec( 'CREATE TABLE `wp_users` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60) NOT NULL)' );
$origin->pdo->exec( 'CREATE TABLE `wp_usermeta` (`umeta_id` INTEGER PRIMARY KEY, `user_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)' );
$origin->seed( 'wp_options', array( array( 'option_name' => 'home', 'option_value' => 'https://origen.com' ), array( 'option_name' => 'siteurl', 'option_value' => 'https://origen.com' ) ) );
$origin->seed( 'wp_2_options', array( array( 'option_name' => 'home', 'option_value' => 'https://origen.com/uno' ), array( 'option_name' => 'siteurl', 'option_value' => 'https://origen.com/uno' ) ) );
$origin->seed( 'wp_blogs', array( array( 'blog_id' => 1, 'site_id' => 1, 'domain' => 'origen.com', 'path' => '/' ), array( 'blog_id' => 2, 'site_id' => 1, 'domain' => 'origen.com', 'path' => '/uno/' ) ) );
$origin->seed( 'wp_site', array( array( 'id' => 1, 'domain' => 'origen.com', 'path' => '/' ) ) );
$origin->seed( 'wp_sitemeta', array( array( 'site_id' => 1, 'meta_key' => 'siteurl', 'meta_value' => 'https://origen.com/' ), array( 'site_id' => 1, 'meta_key' => 'subdomain_install', 'meta_value' => '0' ) ) );
$origin->seed( 'wp_users', array( array( 'ID' => 1, 'user_login' => 'admin' ) ) );
$origin->seed( 'wp_usermeta', array( array( 'user_id' => 1, 'meta_key' => 'wp_capabilities', 'meta_value' => 'a:1:{s:13:"administrator";b:1;}' ) ) );
ri_use( $origin, 'https://origen.com', array( 'domain' => 'origen.com', 'path' => '/' ) );
$backup = basename( bk_backup() );

// Destino: otra red (destino.mx, prefijo red_).
$dest = ri_db( 'destino', 'red_' );
ri_site_tables( $dest, 'red_' );
$dest->seed( 'red_options', array( array( 'option_name' => 'home', 'option_value' => 'https://destino.mx' ), array( 'option_name' => 'siteurl', 'option_value' => 'https://destino.mx' ) ) );
ri_use( $dest, 'https://destino.mx', array( 'domain' => 'destino.mx', 'path' => '/' ) );

register_shutdown_function( function() use ( $dest ) {
	$fail = function( $what ) { echo 'FALLA · ' . $what . "\n"; exit( 1 ); };
	if ( empty( $GLOBALS['ri_cut'] ) ) $fail( 'la restauración no llegó a la extracción de archivos.' );
	$blogs = array_map( function( $b ) { return $b['domain'] . $b['path']; }, $dest->rows( 'red_blogs' ) );
	if ( array( 'destino.mx/', 'destino.mx/uno/' ) !== $blogs ) $fail( 'tras el corte, blogs sigue con el dominio de origen y la red no carga: ' . json_encode( $blogs ) );
	if ( 'destino.mx' !== $dest->value( 'red_site', 'domain', 'id = 1' ) ) $fail( 'tras el corte, site sigue con el dominio de origen: ' . $dest->value( 'red_site', 'domain', 'id = 1' ) );
	if ( 'https://destino.mx/' !== $dest->value( 'red_sitemeta', 'meta_value', "meta_key = 'siteurl'" ) ) $fail( 'sitemeta.siteurl: ' . $dest->value( 'red_sitemeta', 'meta_value', "meta_key = 'siteurl'" ) );
	if ( 'https://destino.mx' !== $dest->value( 'red_options', 'option_value', "option_name = 'home'" ) || 'https://destino.mx/uno' !== $dest->value( 'red_2_options', 'option_value', "option_name = 'home'" ) ) $fail( 'home de los sitios.' );
	if ( null === $dest->value( 'red_usermeta', 'meta_value', "meta_key = 'red_capabilities'" ) ) $fail( 'los permisos no tienen el prefijo nuevo: no se podría entrar.' );
	bk_cleanup();
	foreach ( glob( $GLOBALS['bk_root'] . '-*' ) ?: array() as $file ) @unlink( $file );
	echo "DIGITALÍSIMO Backups: una red restaurada en otro dominio y cortada antes de extraer los archivos queda coherente (blogs, site, sitemeta, home y permisos con el destino).\n";
} );

// El proceso muere al empezar a escribir los archivos en la raíz de WordPress (después del SQL).
$GLOBALS['bk_on_mkdir'] = function( $path ) use ( $dest ) {
	if ( 0 !== strpos( str_replace( '\\', '/', $path ), rtrim( ABSPATH, '/' ) ) || ! in_array( 'red_site', $dest->names(), true ) ) return;
	$GLOBALS['ri_cut'] = true;
	exit;
};
Digitalisimo_Backups::service_restore( $backup, array( 'confirm' => 'RESTAURAR SIN FIRMA', 'network' => true ) );
echo "FALLA · la restauración terminó sin cortarse.\n";
exit( 1 );
